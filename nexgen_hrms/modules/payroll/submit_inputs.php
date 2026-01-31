<?php
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

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['submit_inputs'])) {
    $employee_id = $_POST['employee_id'];
    $month = $_POST['month'];
    $overtime_hours = $_POST['overtime_hours'] ?? 0;
    $bonus = $_POST['bonus'] ?? 0;
    $deductions = $_POST['deductions'] ?? 0;
    $notes = $_POST['notes'] ?? '';
    
    // Get employee's basic salary
    $emp_sql = "SELECT salary FROM users WHERE id = ?";
    $emp_stmt = $conn->prepare($emp_sql);
    $emp_stmt->bind_param("i", $employee_id);
    $emp_stmt->execute();
    $emp_result = $emp_stmt->get_result();
    $employee = $emp_result->fetch_assoc();
    
    if ($employee) {
        $basic_salary = $employee['salary'];
        $overtime_rate = $basic_salary / 160 * 1.5; // Assuming 160 working hours per month
        $overtime_amount = $overtime_hours * $overtime_rate;
        
        // Calculate tax (simplified)
        $tax = $basic_salary * 0.1; // 10% tax for simplicity
        
        // Calculate net salary
        $net_salary = $basic_salary + $overtime_amount + $bonus - $deductions - $tax;
        
        // Check if payroll entry already exists
        $check_sql = "SELECT id FROM salaries WHERE user_id = ? AND month = ?";
        $check_stmt = $conn->prepare($check_sql);
        $check_stmt->bind_param("is", $employee_id, $month);
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
            
            $update_stmt = $conn->prepare($update_sql);
            $update_stmt->bind_param("ddddddis", 
                $overtime_hours, $overtime_rate, $bonus, $deductions, $tax, 
                $net_salary, $user_id, $employee_id, $month);
        } else {
            // Insert new entry
            $insert_sql = "INSERT INTO salaries 
                          (user_id, month, basic_salary, overtime_hours, overtime_rate, 
                          bonus, deductions, tax, net_salary, approved_by, status) 
                          VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending')";
            
            $insert_stmt = $conn->prepare($insert_sql);
            $insert_stmt->bind_param("isdddddddi", 
                $employee_id, $month, $basic_salary, $overtime_hours, $overtime_rate, 
                $bonus, $deductions, $tax, $net_salary, $user_id);
        }
        
        if (isset($update_stmt) && $update_stmt->execute()) {
            setFlash('success', 'Payroll inputs updated successfully!');
        } elseif (isset($insert_stmt) && $insert_stmt->execute()) {
            setFlash('success', 'Payroll inputs submitted successfully!');
        } else {
            setFlash('danger', 'Error submitting payroll inputs.');
        }
        
        header('Location: ' . Auth::getBasePath() . '/modules/payroll/submit_inputs.php');
        exit();
    }
}

// Get existing payroll inputs for current month
$existing_inputs = [];
$inputs_sql = "SELECT * FROM salaries WHERE month = ?";
$inputs_stmt = $conn->prepare($inputs_sql);
$inputs_stmt->bind_param("s", $current_month);
$inputs_stmt->execute();
$inputs_result = $inputs_stmt->get_result();

while ($row = $inputs_result->fetch_assoc()) {
    $existing_inputs[$row['user_id']] = $row;
}
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
                        <input type="hidden" name="month" value="<?php echo $selected_month; ?>">
                        
                        <div class="table-responsive">
                            <table class="table table-hover mb-0">
                                <thead>
                                    <tr>
                                        <th>Employee</th>
                                        <th>Basic Salary</th>
                                        <th>Overtime Hours</th>
                                        <th>Overtime Amount</th>
                                        <th>Bonus</th>
                                        <th>Deductions</th>
                                        <th>Net Salary</th>
                                        <th>Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($team_members as $member_id => $member): 
                                        $existing = $existing_inputs[$member_id] ?? null;
                                        $overtime_hours = $existing['overtime_hours'] ?? 0;
                                        $bonus = $existing['bonus'] ?? 0;
                                        $deductions = $existing['deductions'] ?? 0;
                                        $overtime_rate = $existing['overtime_rate'] ?? ($member['salary'] / 160 * 1.5);
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
                                                   data-salary="<?php echo $member['salary']; ?>">
                                            <small class="text-muted">hours</small>
                                        </td>
                                        
                                        <td>
                                            <span id="overtime_amount_<?php echo $member_id; ?>" class="fw-bold">
                                                $<?php echo number_format($overtime_amount, 2); ?>
                                            </span>
                                        </td>
                                        
                                        <td style="width: 150px;">
                                            <input type="number" step="0.01" min="0" 
                                                   class="form-control form-control-sm bonus-input" 
                                                   name="bonus_<?php echo $member_id; ?>" 
                                                   value="<?php echo $bonus; ?>"
                                                   data-member-id="<?php echo $member_id; ?>">
                                        </td>
                                        
                                        <td style="width: 150px;">
                                            <input type="number" step="0.01" min="0" 
                                                   class="form-control form-control-sm deduction-input" 
                                                   name="deductions_<?php echo $member_id; ?>" 
                                                   value="<?php echo $deductions; ?>"
                                                   data-member-id="<?php echo $member_id; ?>">
                                        </td>
                                        
                                        <td>
                                            <span id="net_salary_<?php echo $member_id; ?>" class="fw-bold text-success">
                                                $<?php echo number_format($net_salary, 2); ?>
                                            </span>
                                        </td>
                                        
                                        <td>
                                            <?php if ($existing): ?>
                                            <span class="badge bg-<?php echo $existing['status'] == 'pending' ? 'warning' : 'success'; ?>">
                                                <?php echo ucfirst($existing['status']); ?>
                                            </span>
                                            <?php else: ?>
                                            <span class="badge bg-secondary">Not Submitted</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        
                        <div class="card-footer">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <strong>Total Team Salary:</strong>
                                    <span id="total_salary" class="ms-2 fs-4 text-success">$0.00</span>
                                </div>
                                <div>
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
                    <textarea class="form-control" rows="4" placeholder="Add any notes or special instructions for HR..."></textarea>
                    <small class="text-muted">These notes will be visible to HR during payroll processing.</small>
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
            <div class="modal-header">
                <h5 class="modal-title">Bulk Upload Payroll Data</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="alert alert-info">
                    <i class="bi bi-info-circle"></i> 
                    Download the template, fill in the data, and upload the CSV file.
                </div>
                
                <div class="mb-3">
                    <a href="template.csv" class="btn btn-outline-primary">
                        <i class="bi bi-download"></i> Download Template
                    </a>
                </div>
                
                <div class="mb-3">
                    <label class="form-label">Upload CSV File</label>
                    <input type="file" class="form-control" accept=".csv">
                </div>
                
                <div class="form-check mb-3">
                    <input class="form-check-input" type="checkbox" id="overwriteExisting">
                    <label class="form-check-label" for="overwriteExisting">
                        Overwrite existing entries for this month
                    </label>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary">Upload File</button>
            </div>
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
        const memberIds = <?php echo json_encode(array_keys($team_members)); ?>;
        
        memberIds.forEach(memberId => {
            const netSalaryText = document.getElementById(`net_salary_${memberId}`).textContent;
            const netSalary = parseFloat(netSalaryText.replace('$', '').replace(',', '')) || 0;
            total += netSalary;
        });
        
        document.getElementById('total_salary').textContent = 
            `$${total.toFixed(2)}`;
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