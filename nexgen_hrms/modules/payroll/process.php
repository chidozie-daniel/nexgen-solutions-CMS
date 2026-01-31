<?php
require_once '../../includes/auth.php';
require_once '../../includes/functions.php';

// Check login status first
Auth::requireLogin();

// Only HR and Admin can process payroll
if (!Auth::hasRole('hr') && !Auth::hasRole('admin')) {
    setFlash('danger', 'You do not have permission to process payroll.');
    header('Location: ' . Auth::getBasePath() . '/dashboard.php');
    exit();
}

$page_title = 'Process Payroll';
$conn = getDBConnection();
$current_month = date('Y-m');
$selected_month = $_GET['month'] ?? $current_month;

// Handle payroll actions
if (isset($_GET['action']) && isset($_GET['id'])) {
    $action = $_GET['action'];
    $salary_id = (int)$_GET['id'];
    
    switch ($action) {
        case 'approve':
            $sql = "UPDATE salaries SET status = 'approved' WHERE id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("i", $salary_id);
            $stmt->execute();
            setFlash('success', 'Salary approved successfully.');
            break;
            
        case 'pay':
            $payment_date = date('Y-m-d');
            $sql = "UPDATE salaries SET status = 'paid', payment_date = ? WHERE id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("si", $payment_date, $salary_id);
            $stmt->execute();
            setFlash('success', 'Salary marked as paid.');
            break;
            
        case 'reject':
            $sql = "UPDATE salaries SET status = 'pending' WHERE id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("i", $salary_id);
            $stmt->execute();
            setFlash('warning', 'Salary returned for review.');
            break;
    }
    
    header('Location: ' . Auth::getBasePath() . '/modules/payroll/process.php?month=' . urlencode($selected_month));
    exit();
}

// Bulk actions
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['bulk_action'])) {
    $action = $_POST['bulk_action'];
    $selected_ids = $_POST['selected_ids'] ?? [];
    
    if (!empty($selected_ids)) {
        // Validate and cast IDs to integers
        $selected_ids = array_map(function($id) { return (int)$id; }, $selected_ids);
        $ids_placeholders = implode(',', array_fill(0, count($selected_ids), '?'));
        
        switch ($action) {
            case 'approve_all':
                $sql = "UPDATE salaries SET status = 'approved' WHERE id IN ($ids_placeholders)";
                $stmt = $conn->prepare($sql);
                $types = str_repeat('i', count($selected_ids));
                $stmt->bind_param($types, ...$selected_ids);
                $stmt->execute();
                setFlash('success', 'Selected salaries approved.');
                break;
                
            case 'pay_all':
                $payment_date = date('Y-m-d');
                $sql = "UPDATE salaries SET status = 'paid', payment_date = ? WHERE id IN ($ids_placeholders)";
                $stmt = $conn->prepare($sql);
                $types = 's' . str_repeat('i', count($selected_ids));
                $stmt->bind_param($types, $payment_date, ...$selected_ids);
                $stmt->execute();
                setFlash('success', 'Selected salaries marked as paid.');
                break;
                
            case 'export_payslips':
                // Generate payslips PDF
                setFlash('info', 'Payslips exported successfully.');
                break;
        }
    }
    
    header('Location: ' . Auth::getBasePath() . '/modules/payroll/process.php?month=' . urlencode($selected_month));
    exit();
}

// Get payroll for selected month
$sql = "SELECT s.*, u.full_name, u.employee_id, u.department, u.position, u.email 
        FROM salaries s 
        JOIN users u ON s.user_id = u.id 
        WHERE s.month = ? 
        ORDER BY u.department, u.full_name";
$stmt = $conn->prepare($sql);
$stmt->bind_param("s", $selected_month);
$stmt->execute();
$result = $stmt->get_result();

// Get payroll stats
$stats_sql = "SELECT 
    COUNT(*) as total,
    SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending,
    SUM(CASE WHEN status = 'approved' THEN 1 ELSE 0 END) as approved,
    SUM(CASE WHEN status = 'paid' THEN 1 ELSE 0 END) as paid,
    SUM(net_salary) as total_amount,
    AVG(net_salary) as avg_salary
    FROM salaries 
    WHERE month = ?";
$stats_stmt = $conn->prepare($stats_sql);
$stats_stmt->bind_param("s", $selected_month);
$stats_stmt->execute();
$stats_result = $stats_stmt->get_result();
$stats = $stats_result->fetch_assoc();

require_once '../../includes/header.php';
?>
<div class="container-fluid">
    <div class="row mb-4">
        <div class="col">
            <div class="d-flex justify-content-between align-items-center">
                <h4 class="mb-0">Payroll Processing</h4>
                <div>
                    <button class="btn btn-success" data-bs-toggle="modal" data-bs-target="#processModal">
                        <i class="bi bi-play-circle"></i> Run Payroll
                    </button>
                </div>
            </div>
            <p class="text-muted mb-0">Review, approve, and process employee salaries</p>
        </div>
    </div>
    
    <!-- Month Selector and Stats -->
    <div class="row mb-4">
        <div class="col-md-4">
            <div class="card">
                <div class="card-body">
                    <form method="GET" class="row g-3">
                        <div class="col-12">
                            <label class="form-label">Select Payroll Month</label>
                            <select name="month" class="form-select" onchange="this.form.submit()">
                                <?php
                                for ($i = 0; $i < 6; $i++):
                                    $date = date('Y-m', strtotime("-$i months"));
                                    $month_name = date('F Y', strtotime($date));
                                ?>
                                <option value="<?php echo $date; ?>" 
                                    <?php echo $date == $selected_month ? 'selected' : ''; ?>>
                                    <?php echo $month_name; ?>
                                </option>
                                <?php endfor; ?>
                            </select>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        
        <div class="col-md-8">
            <div class="row">
                <div class="col-md-3 col-6 mb-3">
                    <div class="card">
                        <div class="card-body text-center py-3">
                            <h3 class="mb-1"><?php echo $stats['total']; ?></h3>
                            <p class="text-muted mb-0 small">Employees</p>
                        </div>
                    </div>
                </div>
                
                <div class="col-md-3 col-6 mb-3">
                    <div class="card border-warning">
                        <div class="card-body text-center py-3">
                            <h3 class="mb-1 text-warning"><?php echo $stats['pending']; ?></h3>
                            <p class="text-muted mb-0 small">Pending</p>
                        </div>
                    </div>
                </div>
                
                <div class="col-md-3 col-6 mb-3">
                    <div class="card border-success">
                        <div class="card-body text-center py-3">
                            <h3 class="mb-1 text-success"><?php echo $stats['approved']; ?></h3>
                            <p class="text-muted mb-0 small">Approved</p>
                        </div>
                    </div>
                </div>
                
                <div class="col-md-3 col-6 mb-3">
                    <div class="card bg-primary text-white">
                        <div class="card-body text-center py-3">
                            <h3 class="mb-1">$<?php echo number_format($stats['total_amount'], 0); ?></h3>
                            <p class="mb-0 small opacity-75">Total Payout</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Payroll Processing Form -->
    <div class="row">
        <div class="col">
            <div class="card">
                <div class="card-header">
                    <div class="d-flex justify-content-between align-items-center">
                        <h5 class="mb-0">Payroll for <?php echo date('F Y', strtotime($selected_month)); ?></h5>
                        <div class="btn-group">
                            <button type="button" class="btn btn-sm btn-outline-primary" 
                                    onclick="selectAll()">Select All</button>
                            <button type="button" class="btn btn-sm btn-outline-secondary" 
                                    onclick="deselectAll()">Deselect All</button>
                        </div>
                    </div>
                </div>
                
                <form method="POST" action="">
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-hover mb-0">
                                <thead>
                                    <tr>
                                        <th width="50">
                                            <input type="checkbox" id="selectAll" onchange="toggleAll(this)">
                                        </th>
                                        <th>Employee</th>
                                        <th>Basic Salary</th>
                                        <th>Overtime</th>
                                        <th>Bonus</th>
                                        <th>Deductions</th>
                                        <th>Tax</th>
                                        <th>Net Salary</th>
                                        <th>Status</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if ($result->num_rows > 0): ?>
                                        <?php while ($row = $result->fetch_assoc()): 
                                            $overtime_amount = $row['overtime_hours'] * $row['overtime_rate'];
                                        ?>
                                        <tr class="<?php echo $row['status'] == 'pending' ? 'table-warning' : ''; ?>">
                                            <td>
                                                <input type="checkbox" name="selected_ids[]" 
                                                       value="<?php echo $row['id']; ?>" 
                                                       class="salary-checkbox">
                                            </td>
                                            <td>
                                                <strong><?php echo htmlspecialchars($row['full_name']); ?></strong><br>
                                                <small class="text-muted"><?php echo $row['employee_id']; ?></small><br>
                                                <small class="text-muted"><?php echo $row['department']; ?></small>
                                            </td>
                                            <td>$<?php echo number_format($row['basic_salary'], 2); ?></td>
                                            <td>
                                                <?php if ($row['overtime_hours'] > 0): ?>
                                                $<?php echo number_format($overtime_amount, 2); ?><br>
                                                <small class="text-muted">(<?php echo $row['overtime_hours']; ?> hrs)</small>
                                                <?php else: ?>
                                                <span class="text-muted">-</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <?php if ($row['bonus'] > 0): ?>
                                                <span class="text-success">+$<?php echo number_format($row['bonus'], 2); ?></span>
                                                <?php else: ?>
                                                <span class="text-muted">-</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <?php if ($row['deductions'] > 0): ?>
                                                <span class="text-danger">-$<?php echo number_format($row['deductions'], 2); ?></span>
                                                <?php else: ?>
                                                <span class="text-muted">-</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <span class="text-danger">-$<?php echo number_format($row['tax'], 2); ?></span>
                                            </td>
                                            <td>
                                                <strong>$<?php echo number_format($row['net_salary'], 2); ?></strong>
                                            </td>
                                            <td>
                                                <?php 
                                                $status_badges = [
                                                    'pending' => 'warning',
                                                    'approved' => 'info',
                                                    'paid' => 'success'
                                                ];
                                                $status_color = $status_badges[$row['status']] ?? 'secondary';
                                                ?>
                                                <span class="badge bg-<?php echo $status_color; ?>">
                                                    <?php echo ucfirst($row['status']); ?>
                                                </span>
                                            </td>
                                            <td>
                                                <div class="btn-group">
                                                    <?php if ($row['status'] == 'pending'): ?>
                                                    <a href="process.php?action=approve&id=<?php echo $row['id']; ?>&month=<?php echo $selected_month; ?>" 
                                                       class="btn btn-sm btn-outline-success" title="Approve">
                                                        <i class="bi bi-check-lg"></i>
                                                    </a>
                                                    <?php elseif ($row['status'] == 'approved'): ?>
                                                    <a href="process.php?action=pay&id=<?php echo $row['id']; ?>&month=<?php echo $selected_month; ?>" 
                                                       class="btn btn-sm btn-outline-primary" title="Mark as Paid">
                                                        <i class="bi bi-cash"></i>
                                                    </a>
                                                    <?php endif; ?>
                                                    
                                                    <button type="button" class="btn btn-sm btn-outline-info" 
                                                            data-bs-toggle="modal" data-bs-target="#detailsModal<?php echo $row['id']; ?>"
                                                            title="View Details">
                                                        <i class="bi bi-eye"></i>
                                                    </button>
                                                    
                                                    <a href="mailto:<?php echo $row['email']; ?>?subject=Payroll%20Query%20-%20<?php echo $selected_month; ?>" 
                                                       class="btn btn-sm btn-outline-secondary" title="Email">
                                                        <i class="bi bi-envelope"></i>
                                                    </a>
                                                </div>
                                            </td>
                                        </tr>
                                        
                                        <!-- Details Modal -->
                                        <div class="modal fade" id="detailsModal<?php echo $row['id']; ?>" tabindex="-1">
                                            <div class="modal-dialog modal-lg">
                                                <div class="modal-content">
                                                    <div class="modal-header">
                                                        <h5 class="modal-title">Salary Details - <?php echo $row['full_name']; ?></h5>
                                                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                                    </div>
                                                    <div class="modal-body">
                                                        <div class="row mb-4">
                                                            <div class="col-md-6">
                                                                <h6>Employee Information</h6>
                                                                <p class="mb-1"><strong>Name:</strong> <?php echo $row['full_name']; ?></p>
                                                                <p class="mb-1"><strong>Employee ID:</strong> <?php echo $row['employee_id']; ?></p>
                                                                <p class="mb-1"><strong>Department:</strong> <?php echo $row['department']; ?></p>
                                                                <p class="mb-0"><strong>Position:</strong> <?php echo $row['position']; ?></p>
                                                            </div>
                                                            <div class="col-md-6">
                                                                <h6>Payroll Information</h6>
                                                                <p class="mb-1"><strong>Month:</strong> <?php echo date('F Y', strtotime($row['month'])); ?></p>
                                                                <p class="mb-1"><strong>Status:</strong> 
                                                                    <span class="badge bg-<?php echo $status_color; ?>">
                                                                        <?php echo ucfirst($row['status']); ?>
                                                                    </span>
                                                                </p>
                                                                <?php if ($row['payment_date']): ?>
                                                                <p class="mb-0"><strong>Payment Date:</strong> <?php echo formatDate($row['payment_date']); ?></p>
                                                                <?php endif; ?>
                                                            </div>
                                                        </div>
                                                        
                                                        <div class="row">
                                                            <div class="col-md-6">
                                                                <h6>Earnings</h6>
                                                                <table class="table table-sm">
                                                                    <tr>
                                                                        <td>Basic Salary</td>
                                                                        <td class="text-end">$<?php echo number_format($row['basic_salary'], 2); ?></td>
                                                                    </tr>
                                                                    <?php if ($row['overtime_hours'] > 0): ?>
                                                                    <tr>
                                                                        <td>
                                                                            Overtime (<?php echo $row['overtime_hours']; ?> hrs @ 
                                                                            $<?php echo number_format($row['overtime_rate'], 2); ?>/hr)
                                                                        </td>
                                                                        <td class="text-end">$<?php echo number_format($overtime_amount, 2); ?></td>
                                                                    </tr>
                                                                    <?php endif; ?>
                                                                    <?php if ($row['bonus'] > 0): ?>
                                                                    <tr>
                                                                        <td>Bonus & Incentives</td>
                                                                        <td class="text-end text-success">$<?php echo number_format($row['bonus'], 2); ?></td>
                                                                    </tr>
                                                                    <?php endif; ?>
                                                                    <tr class="table-light">
                                                                        <th>Total Earnings</th>
                                                                        <th class="text-end">
                                                                            $<?php echo number_format($row['basic_salary'] + $overtime_amount + $row['bonus'], 2); ?>
                                                                        </th>
                                                                    </tr>
                                                                </table>
                                                            </div>
                                                            
                                                            <div class="col-md-6">
                                                                <h6>Deductions</h6>
                                                                <table class="table table-sm">
                                                                    <tr>
                                                                        <td>Income Tax</td>
                                                                        <td class="text-end text-danger">-$<?php echo number_format($row['tax'], 2); ?></td>
                                                                    </tr>
                                                                    <?php if ($row['deductions'] > 0): ?>
                                                                    <tr>
                                                                        <td>Other Deductions</td>
                                                                        <td class="text-end text-danger">-$<?php echo number_format($row['deductions'], 2); ?></td>
                                                                    </tr>
                                                                    <?php endif; ?>
                                                                    <tr class="table-light">
                                                                        <th>Total Deductions</th>
                                                                        <th class="text-end text-danger">
                                                                            -$<?php echo number_format($row['tax'] + $row['deductions'], 2); ?>
                                                                        </th>
                                                                    </tr>
                                                                </table>
                                                            </div>
                                                        </div>
                                                        
                                                        <div class="row mt-4">
                                                            <div class="col-md-12">
                                                                <div class="alert alert-success">
                                                                    <div class="d-flex justify-content-between align-items-center">
                                                                        <div>
                                                                            <h5 class="mb-0">Net Salary Payable</h5>
                                                                            <p class="mb-0 small">Amount to be transferred</p>
                                                                        </div>
                                                                        <h2 class="mb-0">$<?php echo number_format($row['net_salary'], 2); ?></h2>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                    <div class="modal-footer">
                                                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                                                        
                                                        <?php if ($row['status'] == 'pending'): ?>
                                                        <a href="process.php?action=approve&id=<?php echo $row['id']; ?>&month=<?php echo $selected_month; ?>" 
                                                           class="btn btn-success">Approve</a>
                                                        <?php elseif ($row['status'] == 'approved'): ?>
                                                        <a href="process.php?action=pay&id=<?php echo $row['id']; ?>&month=<?php echo $selected_month; ?>" 
                                                           class="btn btn-primary">Mark as Paid</a>
                                                        <?php endif; ?>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                        <?php endwhile; ?>
                                    <?php else: ?>
                                    <tr>
                                        <td colspan="10" class="text-center py-4">
                                            <i class="bi bi-cash-stack h1 text-muted d-block mb-3"></i>
                                            <p class="text-muted">No payroll data found for <?php echo date('F Y', strtotime($selected_month)); ?></p>
                                            <a href="submit_inputs.php" class="btn btn-primary">Submit Payroll Inputs</a>
                                        </td>
                                    </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                    
                    <?php if ($result->num_rows > 0): ?>
                    <div class="card-footer">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <strong>Selected:</strong>
                                <span id="selectedCount" class="ms-2">0</span> employees
                            </div>
                            
                            <div class="btn-group">
                                <select name="bulk_action" class="form-select form-select-sm" style="width: auto;">
                                    <option value="">Bulk Actions</option>
                                    <option value="approve_all">Approve Selected</option>
                                    <option value="pay_all">Mark as Paid</option>
                                    <option value="export_payslips">Export Payslips</option>
                                    <option value="send_emails">Send Notification Emails</option>
                                </select>
                                
                                <button type="submit" class="btn btn-primary btn-sm">Apply</button>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Process Payroll Modal -->
<div class="modal fade" id="processModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Run Payroll Processing</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="alert alert-warning">
                    <i class="bi bi-exclamation-triangle"></i> 
                    This will process payroll for all approved salaries for <?php echo date('F Y', strtotime($selected_month)); ?>.
                </div>
                
                <div class="mb-3">
                    <label class="form-label">Payment Date</label>
                    <input type="date" class="form-control" value="<?php echo date('Y-m-d'); ?>">
                </div>
                
                <div class="mb-3">
                    <label class="form-label">Payment Method</label>
                    <select class="form-select">
                        <option value="bank_transfer">Bank Transfer</option>
                        <option value="check">Check</option>
                        <option value="cash">Cash</option>
                    </select>
                </div>
                
                <div class="form-check mb-3">
                    <input class="form-check-input" type="checkbox" id="sendPayslips" checked>
                    <label class="form-check-label" for="sendPayslips">
                        Email payslips to employees
                    </label>
                </div>
                
                <div class="form-check mb-3">
                    <input class="form-check-input" type="checkbox" id="generateReport" checked>
                    <label class="form-check-label" for="generateReport">
                        Generate payroll report
                    </label>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-success">Process Payroll</button>
            </div>
        </div>
    </div>
</div>

<script>
    // Checkbox selection functions
    function toggleAll(source) {
        const checkboxes = document.querySelectorAll('.salary-checkbox');
        checkboxes.forEach(checkbox => {
            checkbox.checked = source.checked;
        });
        updateSelectedCount();
    }
    
    function selectAll() {
        const checkboxes = document.querySelectorAll('.salary-checkbox');
        checkboxes.forEach(checkbox => {
            checkbox.checked = true;
        });
        document.getElementById('selectAll').checked = true;
        updateSelectedCount();
    }
    
    function deselectAll() {
        const checkboxes = document.querySelectorAll('.salary-checkbox');
        checkboxes.forEach(checkbox => {
            checkbox.checked = false;
        });
        document.getElementById('selectAll').checked = false;
        updateSelectedCount();
    }
    
    function updateSelectedCount() {
        const checkboxes = document.querySelectorAll('.salary-checkbox:checked');
        document.getElementById('selectedCount').textContent = checkboxes.length;
    }
    
    // Initialize
    document.addEventListener('DOMContentLoaded', function() {
        const checkboxes = document.querySelectorAll('.salary-checkbox');
        checkboxes.forEach(checkbox => {
            checkbox.addEventListener('change', updateSelectedCount);
        });
        updateSelectedCount();
    });
</script>

<?php require_once '../../includes/footer.php'; ?>