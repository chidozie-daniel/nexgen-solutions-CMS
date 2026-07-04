<?php
// Define entry point constant
define('APP_ENTRY_POINT', true);

require_once '../../includes/auth.php';
require_once '../../includes/functions.php';

// Check login status first
Auth::requireLogin();

$page_title = 'My Salary Details';
$conn = getDBConnection();
$user_id = $_SESSION['user_id'];
$role = $_SESSION['role'];

// Get salary history
$sql = "SELECT * FROM salaries WHERE user_id = ? ORDER BY month DESC";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();

// Get current user's salary info
$user_sql = "SELECT salary, department, position FROM users WHERE id = ?";
$user_stmt = $conn->prepare($user_sql);
$user_stmt->bind_param("i", $user_id);
$user_stmt->execute();
$user_result = $user_stmt->get_result();
$user_info = $user_result->fetch_assoc();

$page_title = 'My Salary Details';
require_once '../../includes/header.php';
?>

<div class="container-fluid">
    <div class="module-hero">
        <div class="container">
            <div>
                <h1 class="mb-2">My Salary Details</h1>
                <p class="mb-0">View your salary information and payment history</p>
            </div>
        </div>
    </div>
    
    <!-- Current Salary Info -->
    <div class="container-fluid">
        <div class="row mb-4">
            <div class="col-md-4 mb-3">
                <div class="stat-card">
                    <div>
                        <h6>Basic Salary</h6>
                        <h3>$<?php echo number_format($user_info['salary'], 2); ?></h3>
                        <p class="mb-0 small text-muted">Monthly</p>
                    </div>
                </div>
            </div>
            <div class="col-md-4 mb-3">
                <div class="card bg-success text-white h-100">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h6 class="card-title text-white">Department</h6>
                                <h4 class="mb-0"><?php echo htmlspecialchars($user_info['department'] ?? 'N/A'); ?></h4>
                                <p class="mb-0 small opacity-75">Current Department</p>
                            </div>
                            <i class="bi bi-building h1 opacity-50"></i>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="col-md-4 mb-3">
                <div class="card bg-warning text-dark h-100">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h6 class="card-title text-dark">Position</h6>
                                <h4 class="mb-0"><?php echo htmlspecialchars($user_info['position'] ?? 'N/A'); ?></h4>
                                <p class="mb-0 small opacity-75">Current Role</p>
                            </div>
                            <i class="bi bi-person-badge h1 opacity-50"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Salary History -->
    <div class="row">
        <div class="col">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">Salary History</h5>
                    <div>
                        <select class="form-select form-select-sm" id="yearFilter" style="width: auto;">
                            <option value="">All Years</option>
                            <?php
                            $year_sql = "SELECT DISTINCT YEAR(STR_TO_DATE(CONCAT(month, '-01'), '%Y-%m-%d')) as year 
                                        FROM salaries WHERE user_id = ? ORDER BY year DESC";
                            $year_stmt = $conn->prepare($year_sql);
                            $year_stmt->bind_param("i", $user_id);
                            $year_stmt->execute();
                            $year_result = $year_stmt->get_result();
                            while ($year_row = $year_result->fetch_assoc()):
                            ?>
                            <option value="<?php echo $year_row['year']; ?>"><?php echo $year_row['year']; ?></option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                </div>
                <div class="card-body">
                    <?php if ($result->num_rows > 0): ?>
                    <div class="table-responsive">
                        <table class="table table-hover" id="salaryTable">
                            <thead>
                                <tr>
                                    <th>Month</th>
                                    <th>Basic Salary</th>
                                    <th>Overtime</th>
                                    <th>Bonus</th>
                                    <th>Deductions</th>
                                    <th>Tax</th>
                                    <th>Net Salary</th>
                                    <th>Status</th>
                                    <th>Payment Date</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php while ($row = $result->fetch_assoc()): 
                                    $month_name = date('F Y', strtotime($row['month'] . '-01'));
                                ?>
                                <tr>
                                    <td>
                                        <strong><?php echo $month_name; ?></strong><br>
                                        <small class="text-muted"><?php echo $row['month']; ?></small>
                                    </td>
                                    <td>$<?php echo number_format($row['basic_salary'], 2); ?></td>
                                    <td>
                                        <?php if ($row['overtime_hours'] > 0): ?>
                                        $<?php echo number_format($row['overtime_hours'] * $row['overtime_rate'], 2); ?><br>
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
                                        $salary_status = [
                                            'pending' => 'warning',
                                            'approved' => 'info',
                                            'paid' => 'success',
                                            'cancelled' => 'danger'
                                        ];
                                        $status_color = $salary_status[$row['status']] ?? 'secondary';
                                        ?>
                                        <span class="badge bg-<?php echo $status_color; ?>">
                                            <?php echo ucfirst($row['status']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php echo $row['payment_date'] ? formatDate($row['payment_date']) : 'Not Paid'; ?>
                                    </td>
                                    <td>
                                        <button type="button" class="btn btn-sm btn-outline-primary" 
                                                data-bs-toggle="modal" data-bs-target="#salaryModal<?php echo $row['id']; ?>">
                                            <i class="bi bi-receipt"></i> Payslip
                                        </button>
                                    </td>
                                </tr>
                                
                                <!-- Salary Details Modal -->
                                <div class="modal fade" id="salaryModal<?php echo $row['id']; ?>" tabindex="-1">
                                    <div class="modal-dialog modal-lg">
                                        <div class="modal-content">
                                            <div class="modal-header">
                                                <h5 class="modal-title">Payslip - <?php echo $month_name; ?></h5>
                                                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                            </div>
                                            <div class="modal-body">
                                                <!-- Payslip Header -->
                                                <div class="row mb-4">
                                                    <div class="col-md-6">
                                                        <h6>NexGen Solutions</h6>
                                                        <p class="mb-1 small text-muted">123 Tech Street</p>
                                                        <p class="mb-1 small text-muted">San Francisco, CA 94107</p>
                                                        <p class="mb-0 small text-muted">Tax ID: 123-456-789</p>
                                                    </div>
                                                    <div class="col-md-6 text-end">
                                                        <h6>Payslip</h6>
                                                        <p class="mb-1 small text-muted">Period: <?php echo $month_name; ?></p>
                                                        <p class="mb-1 small text-muted">Payment Date: <?php echo formatDate($row['payment_date']); ?></p>
                                                        <p class="mb-0 small text-muted">Status: <?php echo ucfirst($row['status']); ?></p>
                                                    </div>
                                                </div>
                                                
                                                <!-- Employee Info -->
                                                <div class="row mb-4">
                                                    <div class="col-md-6">
                                                        <div class="card">
                                                            <div class="card-body py-2">
                                                                <h6>Employee Information</h6>
                                                                <p class="mb-1 small">
                                                                    <strong>Name:</strong> <?php echo $_SESSION['full_name']; ?>
                                                                </p>
                                                                <p class="mb-1 small">
                                                                    <strong>Employee ID:</strong> <?php echo $_SESSION['employee_id']; ?>
                                                                </p>
                                                                <p class="mb-0 small">
                                                                    <strong>Department:</strong> <?php echo $_SESSION['department']; ?>
                                                                </p>
                                                            </div>
                                                        </div>
                                                    </div>
                                                    <div class="col-md-6">
                                                        <div class="card">
                                                            <div class="card-body py-2">
                                                                <h6>Payment Details</h6>
                                                                <p class="mb-1 small">
                                                                    <strong>Payment Method:</strong> 
                                                                    <?php echo ucfirst(str_replace('_', ' ', $row['payment_method'])); ?>
                                                                </p>
                                                                <p class="mb-1 small">
                                                                    <strong>Bank Account:</strong> ****1234
                                                                </p>
                                                                <p class="mb-0 small">
                                                                    <strong>Reference:</strong> SAL-<?php echo $row['month']; ?>-<?php echo $_SESSION['employee_id']; ?>
                                                                </p>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                                
                                                <!-- Salary Breakdown -->
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
                                                                <td>Overtime (<?php echo $row['overtime_hours']; ?> hrs @ $<?php echo $row['overtime_rate']; ?>/hr)</td>
                                                                <td class="text-end">$<?php echo number_format($row['overtime_hours'] * $row['overtime_rate'], 2); ?></td>
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
                                                                    $<?php echo number_format($row['basic_salary'] + ($row['overtime_hours'] * $row['overtime_rate']) + $row['bonus'], 2); ?>
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
                                                
                                                <!-- Net Salary -->
                                                <div class="row mt-4">
                                                    <div class="col-md-12">
                                                        <div class="alert alert-success">
                                                            <div class="d-flex justify-content-between align-items-center">
                                                                <div>
                                                                    <h5 class="mb-0">Net Salary Payable</h5>
                                                                    <p class="mb-0 small">Amount transferred to your bank account</p>
                                                                </div>
                                                                <h2 class="mb-0">$<?php echo number_format($row['net_salary'], 2); ?></h2>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                                
                                                <!-- Footer Notes -->
                                                <div class="row mt-3">
                                                    <div class="col-md-12">
                                                        <p class="small text-muted mb-0">
                                                            <strong>Notes:</strong> This is a computer-generated payslip. No signature required.
                                                        </p>
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="modal-footer">
                                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                                                <button type="button" class="btn btn-primary" onclick="printPayslip(<?php echo $row['id']; ?>)">
                                                    <i class="bi bi-printer"></i> Print
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php else: ?>
                    <div class="text-center py-5">
                        <i class="bi bi-cash-stack h1 text-muted d-block mb-3"></i>
                        <p class="text-muted">No salary records found.</p>
                        <p class="small text-muted">
                            Your salary records will appear here after payroll processing.
                        </p>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Salary Calculation Info -->
    <div class="row mt-4">
        <div class="col-md-6">
            <div class="card">
                <div class="card-header">
                    <h6 class="mb-0">Salary Calculation Formula</h6>
                </div>
                <div class="card-body">
                    <div class="alert alert-info">
                        <p class="mb-2"><strong>Net Salary = Basic + Overtime + Bonus - Deductions - Tax</strong></p>
                        <ul class="mb-0 small">
                            <li><strong>Basic Salary:</strong> Fixed monthly salary as per employment contract</li>
                            <li><strong>Overtime:</strong> Calculated as hours × rate (1.5× normal rate)</li>
                            <li><strong>Bonus:</strong> Performance-based incentives</li>
                            <li><strong>Deductions:</strong> Loan repayments, advances, other adjustments</li>
                            <li><strong>Tax:</strong> Income tax as per government regulations</li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-md-6">
            <div class="card">
                <div class="card-header">
                    <h6 class="mb-0">Payroll Schedule</h6>
                </div>
                <div class="card-body">
                    <ul class="list-group list-group-flush">
                        <li class="list-group-item d-flex justify-content-between align-items-center">
                            Payroll Processing
                            <span class="badge bg-primary">1st - 5th of each month</span>
                        </li>
                        <li class="list-group-item d-flex justify-content-between align-items-center">
                            Salary Payment
                            <span class="badge bg-success">7th of each month</span>
                        </li>
                        <li class="list-group-item d-flex justify-content-between align-items-center">
                            Overtime Submission
                            <span class="badge bg-warning text-dark">Last day of month</span>
                        </li>
                        <li class="list-group-item d-flex justify-content-between align-items-center">
                            Payslip Availability
                            <span class="badge bg-info">8th of each month</span>
                        </li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    function printPayslip(salaryId) {
        // Open print view for payslip
        window.open('payslip.php?id=' + salaryId, '_blank');
    }
    
    // Filter by year
    document.getElementById('yearFilter').addEventListener('change', function() {
        const year = this.value;
        const rows = document.querySelectorAll('#salaryTable tbody tr');
        
        rows.forEach(row => {
            const monthCell = row.querySelector('td:first-child small');
            if (monthCell) {
                const rowYear = monthCell.textContent.split('-')[0];
                if (!year || rowYear === year) {
                    row.style.display = '';
                } else {
                    row.style.display = 'none';
                }
            }
        });
    });
</script>

<?php require_once '../../includes/footer.php'; ?>