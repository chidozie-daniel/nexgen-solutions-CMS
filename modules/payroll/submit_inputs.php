<?php
// Define entry point constant
define('APP_ENTRY_POINT', true);

require_once '../../includes/auth.php';
require_once '../../includes/functions.php';

// Check login status first
Auth::requireLogin();

// Only project leaders, HR, and Admin can submit payroll inputs
if (!Auth::hasRole('project_leader') && !Auth::hasRole('hr') && !Auth::hasRole('admin')) {
    setFlash('danger', 'You do not have permission to submit payroll inputs.');
    header('Location: ' . Auth::getBasePath() . '/dashboard.php');
    exit();
}

$page_title = 'Submit Payroll Inputs';
$conn = getDBConnection();
$user_id = $_SESSION['user_id'];
$current_month = date('Y-m');

// Get team members (for project leaders)
$team_members = [];
if (Auth::hasRole('project_leader')) {
    $sql = "SELECT DISTINCT u.id, u.full_name, u.employee_id, u.position, u.salary 
            FROM users u 
            JOIN tasks t ON u.id = t.assigned_to 
            WHERE t.assigned_by = ? 
            AND u.status = 'active'
            UNION
            SELECT u.id, u.full_name, u.employee_id, u.position, u.salary 
            FROM users u 
            JOIN project_members pm ON u.id = pm.user_id 
            JOIN projects p ON pm.project_id = p.id 
            WHERE p.project_leader = ? 
            AND u.status = 'active'";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ii", $user_id, $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
} else {
    // HR and Admin can see all employees
    $sql = "SELECT id, full_name, employee_id, position, salary 
            FROM users 
            WHERE status = 'active' 
            AND role != 'admin' 
            ORDER BY full_name";
    $result = $conn->query($sql);
}
while ($row = $result->fetch_assoc()) {
    $team_members[$row['id']] = $row;
}

// Handle Bulk Upload
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_FILES['bulk_file'])) {
    // Verify CSRF token
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        setFlash('danger', 'Invalid security token. Please try again.');
        header('Location: submit_inputs.php');
        exit();
    }
    
    $file = $_FILES['bulk_file'];
    $month = $_POST['month'];
    $overwrite = isset($_POST['overwrite']) && $_POST['overwrite'] == 'on';
    
    if (!isValidMonth($month)) {
        setFlash('danger', 'Invalid payroll month.');
        header('Location: ' . Auth::getBasePath() . '/modules/payroll/submit_inputs.php');
        exit();
    }
    if ($file['error'] != 0) {
        setFlash('danger', 'File upload error.');
        header('Location: ' . Auth::getBasePath() . '/modules/payroll/submit_inputs.php');
        exit();
    }
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if ($ext !== 'csv') {
        setFlash('danger', 'Only CSV files are allowed.');
        header('Location: ' . Auth::getBasePath() . '/modules/payroll/submit_inputs.php');
        exit();
    }

    if (($handle = fopen($file['tmp_name'], "r")) !== FALSE) {
        fgetcsv($handle); // Skip header row
        $imported_count = 0;

        // Start transaction for bulk import
        $conn->begin_transaction();
        try {
            while (($data = fgetcsv($handle, 1000, ",")) !== FALSE) {
            // CSV columns: employee_id_str, full_name, overtime_hours, bonus, deductions, notes
            $emp_id_str = $data[0];
            $ot_hours = (float)($data[2] ?? 0);
            $bonus = (float)($data[3] ?? 0);
            $deductions = (float)($data[4] ?? 0);

            if ($emp_id_str === '' || !isNonNegativeNumber($ot_hours) || !isNonNegativeNumber($bonus) || !isNonNegativeNumber($deductions)) {
                continue;
            }
            
            // Find user by employee_id string
            $find_sql = "SELECT id, salary FROM users WHERE employee_id = ?";
            $find_stmt = $conn->prepare($find_sql);
            $find_stmt->bind_param("s", $emp_id_str);
            $find_stmt->execute();
            $user_data = $find_stmt->get_result()->fetch_assoc();
            
            if ($user_data) {
                $uid = $user_data['id'];
                $salary = $user_data['salary'];
                $ot_rate = $salary / 160 * 1.5;
                $ot_amount = $ot_hours * $ot_rate;
                $tax = $salary * 0.1;
                $net = $salary + $ot_amount + $bonus - $deductions - $tax;
                
                // Process update or insert...
                $check_sql = "SELECT id FROM salaries WHERE user_id = ? AND month = ?";
                $check_stmt = $conn->prepare($check_sql);
                $check_stmt->bind_param("is", $uid, $month);
                $check_stmt->execute();
                
                if ($check_stmt->get_result()->num_rows > 0) {
                    if ($overwrite) {
                        $sql = "UPDATE salaries SET overtime_hours = ?, overtime_rate = ?, bonus = ?, deductions = ?, tax = ?, net_salary = ?, approved_by = ?, status = 'pending' WHERE user_id = ? AND month = ?";
                        $stmt = $conn->prepare($sql);
                        $stmt->bind_param("ddddddiis", $ot_hours, $ot_rate, $bonus, $deductions, $tax, $net, $user_id, $uid, $month);
                        $stmt->execute();
                        $imported_count++;
                    }
                } else {
                    $sql = "INSERT INTO salaries (user_id, month, basic_salary, overtime_hours, overtime_rate, bonus, deductions, tax, net_salary, approved_by, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending')";
                    $stmt = $conn->prepare($sql);
                    $stmt->bind_param("isdddddddi", $uid, $month, $salary, $ot_hours, $ot_rate, $bonus, $deductions, $tax, $net, $user_id);
                    if (!$stmt->execute()) {
                        $stmt->close();
                        throw new Exception("Failed to insert payroll entry for employee $emp_id_str");
                    }
                    $stmt->close();
                    $imported_count++;
                }
            }
        }
        fclose($handle);
        $conn->commit();
        setFlash('success', "Imported $imported_count payroll entries successfully!");
        header('Location: ' . Auth::getBasePath() . '/modules/payroll/submit_inputs.php?month=' . urlencode($month));
        exit();
    }
} catch (Exception $e) {
    $conn->rollback();
    fclose($handle);
    error_log("Payroll import failed: " . $e->getMessage());
    setFlash('danger', 'Import failed: ' . $e->getMessage() . '. All changes have been rolled back.');
    header('Location: ' . Auth::getBasePath() . '/modules/payroll/submit_inputs.php');
    exit();
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['submit_inputs'])) {
    // Verify CSRF token
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        setFlash('danger', 'Invalid security token. Please try again.');
        header('Location: submit_inputs.php');
        exit();
    }
    
    $employee_ids = $_POST['employee_ids'] ?? [];
    $month = $_POST['month'];
    $success_count = 0;

    if (!isValidMonth($month)) {
        setFlash('danger', 'Invalid payroll month.');
        header('Location: ' . Auth::getBasePath() . '/modules/payroll/submit_inputs.php');
        exit();
    }

    // Start transaction for bulk update
    $conn->begin_transaction();
    try {
        foreach ($employee_ids as $emp_id) {
        if (!is_numeric($emp_id) || (int)$emp_id <= 0) {
            continue;
        }
        $overtime_hours = $_POST['overtime_' . $emp_id] ?? 0;
        $bonus = $_POST['bonus_' . $emp_id] ?? 0;
        $deductions = $_POST['deductions_' . $emp_id] ?? 0;

        if (!isNonNegativeNumber($overtime_hours) || !isNonNegativeNumber($bonus) || !isNonNegativeNumber($deductions)) {
            continue;
        }
        
        // Get employee's basic salary
        $emp_sql = "SELECT salary FROM users WHERE id = ?";
        $emp_stmt = $conn->prepare($emp_sql);
        $emp_stmt->bind_param("i", $emp_id);
        $emp_stmt->execute();
        $emp_result = $emp_stmt->get_result();
        $employee = $emp_result->fetch_assoc();
        
        if ($employee) {
            $basic_salary = $employee['salary'];
            $overtime_rate = $basic_salary / 160 * 1.5;
            $overtime_amount = $overtime_hours * $overtime_rate;
            $tax = $basic_salary * 0.1;
            $net_salary = $basic_salary + $overtime_amount + $bonus - $deductions - $tax;
            
            // Check if payroll entry already exists
            $check_sql = "SELECT id FROM salaries WHERE user_id = ? AND month = ?";
            $check_stmt = $conn->prepare($check_sql);
            $check_stmt->bind_param("is", $emp_id, $month);
            $check_stmt->execute();
            $check_result = $check_stmt->get_result();
            
            if ($check_result->num_rows > 0) {
                // Update existing entry
                $update_sql = "UPDATE salaries SET 
                              overtime_hours = ?, 
                              overtime_rate = ?, 
                              bonus = ?, 
                              deductions = ?, 
                              tax = ?, 
                              net_salary = ?, 
                              approved_by = ?, 
                              status = 'pending' 
                              WHERE user_id = ? AND month = ?";
                $stmt = $conn->prepare($update_sql);
                $stmt->bind_param("ddddddiis", $overtime_hours, $overtime_rate, $bonus, $deductions, $tax, $net_salary, $user_id, $emp_id, $month);
            } else {
                // Insert new entry
                $insert_sql = "INSERT INTO salaries 
                              (user_id, month, basic_salary, overtime_hours, overtime_rate, 
                              bonus, deductions, tax, net_salary, approved_by, status) 
                              VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending')";
                $stmt = $conn->prepare($insert_sql);
                $stmt->bind_param("isdddddddi", $emp_id, $month, $basic_salary, $overtime_hours, $overtime_rate, $bonus, $deductions, $tax, $net_salary, $user_id);
            }
            
            if ($stmt->execute()) {
                $success_count++;
            } else {
                $stmt->close();
                throw new Exception("Failed to update payroll for employee ID $emp_id");
            }
            $stmt->close();
        }
        
        $conn->commit();
    } catch (Exception $e) {
        $conn->rollback();
        error_log("Payroll submission failed: " . $e->getMessage());
        setFlash('danger', 'Failed to submit payroll: ' . $e->getMessage() . '. All changes rolled back.');
        header('Location: ' . Auth::getBasePath() . '/modules/payroll/submit_inputs.php');
        exit();
    }

    if ($success_count > 0) {
        setFlash('success', "Payroll inputs for $success_count team members submitted successfully!");
    } else {
        setFlash('danger', 'No payroll inputs were updated.');
    }
    
    header('Location: ' . Auth::getBasePath() . '/modules/payroll/submit_inputs.php?month=' . urlencode($month));
    exit();
}

// Get existing payroll inputs for the selected month
$selected_month = $_GET['month'] ?? $current_month;
$existing_inputs = [];
$inputs_sql = "SELECT * FROM salaries WHERE month = ?";
$inputs_stmt = $conn->prepare($inputs_sql);
$inputs_stmt->bind_param("s", $selected_month);
$inputs_stmt->execute();
$inputs_result = $inputs_stmt->get_result();

while ($row = $inputs_result->fetch_assoc()) {
    $existing_inputs[$row['user_id']] = $row;
}
require_once '../../includes/header.php';
?>
<div class="container-fluid">
    <div class="row mb-4">
        <div class="col">
            <div class="d-flex justify-content-between align-items-center">
                <h4 class="mb-0">Submit Payroll Inputs</h4>
                <div>
                    <button class="btn btn-outline-primary" data-bs-toggle="modal" data-bs-target="#bulkUploadModal">
                        <i class="bi bi-upload"></i> Bulk Upload
                    </button>
                </div>
            </div>
            <p class="text-muted mb-0">Submit overtime, bonuses, and deductions for your team members</p>
        </div>
    </div>
    
    <!-- Month Selector -->
    <div class="row mb-4">
        <div class="col">
            <div class="card">
                <div class="card-body">
                    <form method="GET" class="row g-3 align-items-center">
                        <div class="col-md-4">
                            <label class="form-label">Select Month</label>
                            <select name="month" class="form-select" onchange="this.form.submit()">
                                <?php
                                $selected_month = $_GET['month'] ?? $current_month;
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
                        
                        <div class="col-md-8">
                            <div class="alert alert-info mb-0">
                                <i class="bi bi-info-circle"></i> 
                                Submit payroll inputs by the last day of each month. 
                                HR will review and process payments by the 7th of the following month.
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
    
    <?php if (empty($team_members)): ?>
    <div class="row">
        <div class="col">
            <div class="card">
                <div class="card-body text-center py-5">
                    <i class="bi bi-people h1 text-muted d-block mb-3"></i>
                    <p class="text-muted">No team members found.</p>
                    <p class="small text-muted">
                        You need to have team members assigned to your projects or tasks to submit payroll inputs.
                    </p>
                </div>
            </div>
        </div>
    </div>
    <?php else: ?>
    
    <!-- Payroll Inputs Form -->
    <div class="row">
        <div class="col">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">Team Members - <?php echo date('F Y', strtotime($selected_month)); ?></h5>
                </div>
                <div class="card-body p-0">
                    <form method="POST" action="">
                        <?php echo csrfField(); ?>
                        <input type="hidden" name="month" value="<?php echo $selected_month; ?>">

                        <div class="table-responsive">
                            <table class="table table-hover mb-0">
                                <thead>
                                    <tr>
                                        <th>Employee</th>
                                        <th>Basic salary</th>
                                        <th width="120">OT Hours</th>
                                        <th>OT Amount</th>
                                        <th width="120">Bonus</th>
                                        <th width="120">Deductions</th>
                                        <th>Tax (10%)</th>
                                        <th>Net Salary</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($team_members as $member_id => $member): 
                                        $existing = $existing_inputs[$member_id] ?? null;
                                        $overtime_hours = $existing['overtime_hours'] ?? 0;
                                        $bonus = $existing['bonus'] ?? 0;
                                        $deductions = $existing['deductions'] ?? 0;
                                        $overtime_rate = $member['salary'] / 160 * 1.5;
                                        $overtime_amount = $overtime_hours * $overtime_rate;
                                        $tax = $member['salary'] * 0.1;
                                        $net_salary = $member['salary'] + $overtime_amount + $bonus - $deductions - $tax;
                                    ?>
                                    <tr>
                                        <td>
                                            <input type="hidden" name="employee_ids[]" value="<?php echo $member_id; ?>">
                                            <strong><?php echo htmlspecialchars($member['full_name']); ?></strong><br>
                                            <small class="text-muted"><?php echo htmlspecialchars($member['employee_id'] ?? 'N/A'); ?></small><br>
                                            <small class="text-muted"><?php echo htmlspecialchars($member['position'] ?? 'N/A'); ?></small>
                                        </td>
                                        
                                        <td>
                                            <span class="fw-bold">$<?php echo number_format($member['salary'], 2); ?></span>
                                        </td>
                                        
                                        <td style="width: 150px;">
                                            <input type="number" step="0.5" min="0" max="100" 
                                                   class="form-control form-control-sm overtime-input" 
                                                   name="overtime_<?php echo $member_id; ?>" 
                                                   value="<?php echo $overtime_hours; ?>"
                                                   data-member-id="<?php echo $member_id; ?>"
                                                   value="<?php echo $overtime_hours; ?>" 
                                                   data-member-id="<?php echo $member_id; ?>"
                                                   data-salary="<?php echo $member['salary']; ?>">
                                        </td>
                                        <td id="overtime_amount_<?php echo $member_id; ?>" class="text-muted">$<?php echo number_format($overtime_amount, 2); ?></td>
                                        <td>
                                            <input type="number" step="10" min="0" name="bonus_<?php echo $member_id; ?>" 
                                                   class="form-control form-control-sm bonus-input font-weight-bold text-success" 
                                                   value="<?php echo $bonus; ?>" 
                                                   data-member-id="<?php echo $member_id; ?>"
                                                   data-salary="<?php echo $member['salary']; ?>">
                                        </td>
                                        <td>
                                            <input type="number" step="10" min="0" name="deductions_<?php echo $member_id; ?>" 
                                                   class="form-control form-control-sm deduction-input text-danger" 
                                                   value="<?php echo $deductions; ?>" 
                                                   data-member-id="<?php echo $member_id; ?>"
                                                   data-salary="<?php echo $member['salary']; ?>">
                                        </td>
                                        <td class="text-danger">-$<?php echo number_format($tax, 2); ?></td>
                                        <td class="fw-bold text-primary" id="net_salary_<?php echo $member_id; ?>">
                                            $<?php echo number_format($net_salary, 2); ?>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                                <tfoot>
                                    <tr class="table-light fw-bold">
                                        <td colspan="7" class="text-end">Total Team Net Salary:</td>
                                        <td id="total_salary" class="text-primary">$0.00</td>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>
                        
                        <div class="card-footer bg-white py-3">
                            <div class="d-flex justify-content-between align-items-center">
                                <div class="text-muted small">
                                    <i class="bi bi-info-circle"></i> Changes are autosaved to current view, but click Submit to finalize.
                                </div>
                                <div class="btn-group">
                                    <button type="submit" name="submit_inputs" class="btn btn-primary">
                                        <i class="bi bi-save"></i> Submit All Inputs
                                    </button>
                                </div>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Summary Card -->
    <div class="row mt-4">
        <div class="col-md-6">
            <div class="card">
                <div class="card-header">
                    <h6 class="mb-0">Payroll Calculation Guidelines</h6>
                </div>
                <div class="card-body">
                    <ul class="mb-0 small">
                        <li><strong>Overtime Rate:</strong> 1.5× normal hourly rate</li>
                        <li><strong>Hourly Rate:</strong> Basic Salary ÷ 160 working hours</li>
                        <li><strong>Bonuses:</strong> Performance incentives, project completion bonuses</li>
                        <li><strong>Deductions:</strong> Loan repayments, advances, equipment damage</li>
                        <li><strong>Tax:</strong> Calculated as 10% of basic salary</li>
                        <li><strong>Net Salary:</strong> Basic + Overtime + Bonus - Deductions - Tax</li>
                    </ul>
                </div>
            </div>
        </div>
        
        <div class="col-md-6">
            <div class="card">
                <div class="card-header">
                    <h6 class="mb-0">Submission Notes</h6>
                </div>
                <div class="card-body">
                    <textarea class="form-control" rows="4" placeholder="Add any notes or special instructions for HR..." disabled></textarea>
                    <small class="text-muted">Notes display is not yet implemented (will be saved in a future update).</small>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>
</div>

<!-- Bulk Upload Modal -->
<div class="modal fade" id="bulkUploadModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" action="<?php echo $base_url; ?>/modules/payroll/submit_inputs.php" enctype="multipart/form-data">
                <div class="modal-header">
                    <h5 class="modal-title">Bulk Upload Payroll Data</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <?php echo csrfField(); ?>
                    <input type="hidden" name="month" value="<?php echo htmlspecialchars($selected_month); ?>">

                    <div class="alert alert-info">
                        <i class="bi bi-info-circle"></i>
                        Download the template, fill in the data, and upload the CSV file.
                    </div>

                    <div class="mb-3">
                        <a href="<?php echo $base_url; ?>/modules/admin/download_csv_template.php?type=payroll" class="btn btn-outline-primary">
                            <i class="bi bi-download"></i> Download Template
                        </a>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Upload CSV File</label>
                        <input type="file" name="bulk_file" class="form-control" accept=".csv" required>
                    </div>

                    <div class="form-check mb-3">
                        <input class="form-check-input" type="checkbox" name="overwrite" id="overwriteExisting" value="on">
                        <label class="form-check-label" for="overwriteExisting">
                            Overwrite existing entries for this month
                        </label>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Upload File</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
    // Calculate salary in real-time
    function calculateSalary(memberId, salary) {
        const overtimeInput = document.querySelector(`input[name="overtime_${memberId}"]`);
        const bonusInput = document.querySelector(`input[name="bonus_${memberId}"]`);
        const deductionInput = document.querySelector(`input[name="deductions_${memberId}"]`);
        
        const overtimeHours = parseFloat(overtimeInput.value) || 0;
        const bonus = parseFloat(bonusInput.value) || 0;
        const deductions = parseFloat(deductionInput.value) || 0;
        
        // Calculate overtime rate (1.5× hourly rate)
        const hourlyRate = salary / 160;
        const overtimeRate = hourlyRate * 1.5;
        const overtimeAmount = overtimeHours * overtimeRate;
        
        // Calculate tax (10% of basic)
        const tax = salary * 0.1;
        
        // Calculate net salary
        const netSalary = salary + overtimeAmount + bonus - deductions - tax;
        
        // Update display
        document.getElementById(`overtime_amount_${memberId}`).textContent = 
            `$${overtimeAmount.toFixed(2)}`;
        document.getElementById(`net_salary_${memberId}`).textContent = 
            `$${netSalary.toFixed(2)}`;
        
        // Update total
        updateTotalSalary();
    }
    
    // Update total team salary
    function updateTotalSalary() {
        let total = 0;
        const netSalaries = document.querySelectorAll('[id^="net_salary_"]');
        
        netSalaries.forEach(el => {
            const netSalaryText = el.textContent;
            const netSalary = parseFloat(netSalaryText.replace('$', '').replace(/,/g, '')) || 0;
            total += netSalary;
        });
        
        document.getElementById('total_salary').textContent = 
            `$${total.toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2})}`;
    }
    
    // Initialize event listeners
    document.addEventListener('DOMContentLoaded', function() {
        // Add input listeners to all salary fields
        document.querySelectorAll('.overtime-input, .bonus-input, .deduction-input').forEach(input => {
            const memberId = input.dataset.memberId;
            const salary = parseFloat(input.dataset.salary) || 0;
            
            input.addEventListener('input', function() {
                calculateSalary(memberId, salary);
            });
        });
        
        // Calculate initial totals
        <?php foreach ($team_members as $member_id => $member): ?>
        calculateSalary(<?php echo $member_id; ?>, <?php echo $member['salary']; ?>);
        <?php endforeach; ?>
        
        updateTotalSalary();
    });
</script>

<?php require_once '../../includes/footer.php'; ?>
