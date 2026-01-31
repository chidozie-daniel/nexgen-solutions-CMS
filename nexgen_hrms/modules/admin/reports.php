<?php
require_once '../../includes/auth.php';
require_once '../../includes/functions.php';

// Check login status first
Auth::requireLogin();

// Only admin and HR can access reports
if (!Auth::hasRole('admin') && !Auth::hasRole('hr')) {
    setFlash('danger', 'You do not have permission to view reports.');
    header('Location: ' . Auth::getBasePath() . '/dashboard.php');
    exit();
}

$page_title = 'Reports & Analytics';
$conn = getDBConnection();

// Get report parameters
$report_type = $_GET['report'] ?? 'overview';
$start_date = $_GET['start_date'] ?? date('Y-m-01');
$end_date = $_GET['end_date'] ?? date('Y-m-t');
$department = $_GET['department'] ?? 'all';

// Generate report based on type
$report_data = [];
$report_title = '';
$chart_data = [];

switch ($report_type) {
    case 'overview':
        $report_title = 'System Overview Report';
        
        // Get employee count by department
        $dept_sql = "SELECT department, COUNT(*) as count 
                     FROM users 
                     WHERE status = 'active' 
                     AND department IS NOT NULL 
                     GROUP BY department 
                     ORDER BY count DESC";
        $dept_result = $conn->query($dept_sql);
        
        while ($row = $dept_result->fetch_assoc()) {
            $report_data['departments'][] = $row;
            $chart_data['labels'][] = $row['department'];
            $chart_data['values'][] = $row['count'];
        }
        
        // Get leave statistics
        $leave_sql = "SELECT 
            COUNT(*) as total_leaves,
            SUM(CASE WHEN status = 'approved' THEN 1 ELSE 0 END) as approved,
            SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending,
            SUM(CASE WHEN status = 'rejected' THEN 1 ELSE 0 END) as rejected,
            SUM(duration_days) as total_days
            FROM leaves 
            WHERE start_date BETWEEN ? AND ?";
        $leave_stmt = $conn->prepare($leave_sql);
        $leave_stmt->bind_param("ss", $start_date, $end_date);
        $leave_stmt->execute();
        $leave_result = $leave_stmt->get_result();
        $report_data['leaves'] = $leave_result->fetch_assoc();
        
        // Get task statistics
        $task_sql = "SELECT 
            COUNT(*) as total_tasks,
            SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed,
            SUM(CASE WHEN status = 'in_progress' THEN 1 ELSE 0 END) as in_progress,
            AVG(progress) as avg_progress
            FROM tasks 
            WHERE created_at BETWEEN ? AND ?";
        $task_stmt = $conn->prepare($task_sql);
        $task_stmt->bind_param("ss", $start_date, $end_date);
        $task_stmt->execute();
        $task_result = $task_stmt->get_result();
        $report_data['tasks'] = $task_result->fetch_assoc();
        
        // Get payroll statistics
        $payroll_sql = "SELECT 
            COUNT(*) as total_salaries,
            SUM(net_salary) as total_amount,
            AVG(net_salary) as avg_salary,
            SUM(CASE WHEN status = 'paid' THEN 1 ELSE 0 END) as paid
            FROM salaries 
            WHERE month BETWEEN DATE_FORMAT(?, '%Y-%m') AND DATE_FORMAT(?, '%Y-%m')";
        $payroll_stmt = $conn->prepare($payroll_sql);
        $payroll_stmt->bind_param("ss", $start_date, $end_date);
        $payroll_stmt->execute();
        $payroll_result = $payroll_stmt->get_result();
        $report_data['payroll'] = $payroll_result->fetch_assoc();
        
        break;
        
    case 'attendance':
        $report_title = 'Attendance Report';
        // Attendance report logic here
        break;
        
    case 'productivity':
        $report_title = 'Employee Productivity Report';
        // Productivity report logic here
        break;
        
    case 'financial':
        $report_title = 'Financial Report';
        // Financial report logic here
        break;
$page_title = 'Reports & Analytics';
require_once '../../includes/header.php';
?>
<div class="container-fluid">
    <div class="row mb-4">
        <div class="col">
            <div class="d-flex justify-content-between align-items-center">
                <h4 class="mb-0">Reports & Analytics</h4>
                <div>
                    <button class="btn btn-primary" onclick="exportReport()">
                        <i class="bi bi-download"></i> Export Report
                    </button>
                    <button class="btn btn-success" onclick="printReport()">
                        <i class="bi bi-printer"></i> Print
                    </button>
                </div>
            </div>
            <p class="text-muted mb-0">Generate and analyze system reports</p>
        </div>
    </div>
    
    <!-- Report Filters -->
    <div class="row mb-4">
        <div class="col">
            <div class="card">
                <div class="card-body">
                    <form method="GET" class="row g-3">
                        <div class="col-md-3">
                            <label class="form-label">Report Type</label>
                            <select name="report" class="form-select" onchange="this.form.submit()">
                                <option value="overview" <?php echo $report_type == 'overview' ? 'selected' : ''; ?>>System Overview</option>
                                <option value="attendance" <?php echo $report_type == 'attendance' ? 'selected' : ''; ?>>Attendance Report</option>
                                <option value="productivity" <?php echo $report_type == 'productivity' ? 'selected' : ''; ?>>Productivity Report</option>
                                <option value="leave" <?php echo $report_type == 'leave' ? 'selected' : ''; ?>>Leave Analysis</option>
                                <option value="payroll" <?php echo $report_type == 'payroll' ? 'selected' : ''; ?>>Payroll Report</option>
                                <option value="financial" <?php echo $report_type == 'financial' ? 'selected' : ''; ?>>Financial Report</option>
                            </select>
                        </div>
                        
                        <div class="col-md-3">
                            <label class="form-label">Start Date</label>
                            <input type="date" class="form-control" name="start_date" 
                                   value="<?php echo $start_date; ?>">
                        </div>
                        
                        <div class="col-md-3">
                            <label class="form-label">End Date</label>
                            <input type="date" class="form-control" name="end_date" 
                                   value="<?php echo $end_date; ?>">
                        </div>
                        
                        <div class="col-md-3">
                            <label class="form-label">Department</label>
                            <select name="department" class="form-select">
                                <option value="all" <?php echo $department == 'all' ? 'selected' : ''; ?>>All Departments</option>
                                <?php
                                $depts_sql = "SELECT DISTINCT department FROM users WHERE department IS NOT NULL ORDER BY department";
                                $depts_result = $conn->query($depts_sql);
                                while ($dept = $depts_result->fetch_assoc()):
                                ?>
                                <option value="<?php echo $dept['department']; ?>" 
                                    <?php echo $department == $dept['department'] ? 'selected' : ''; ?>>
                                    <?php echo $dept['department']; ?>
                                </option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                        
                        <div class="col-md-12">
                            <button type="submit" class="btn btn-primary">Generate Report</button>
                            <a href="reports.php" class="btn btn-outline-secondary">Reset</a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Report Header -->
    <div class="row mb-4">
        <div class="col">
            <div class="card">
                <div class="card-body">
                    <div class="text-center">
                        <h3><?php echo $report_title; ?></h3>
                        <p class="text-muted">
                            Period: <?php echo formatDate($start_date); ?> to <?php echo formatDate($end_date); ?>
                            <?php if ($department != 'all'): ?>
                            | Department: <?php echo $department; ?>
                            <?php endif; ?>
                        </p>
                        <p class="text-muted small">Generated on: <?php echo date('F d, Y h:i A'); ?></p>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <?php if ($report_type == 'overview'): ?>
    <!-- Overview Report -->
    <div class="row mb-4">
        <!-- Key Metrics -->
        <div class="col-md-3 col-6 mb-3">
            <div class="card">
                <div class="card-body text-center py-4">
                    <i class="bi bi-people h1 text-primary d-block mb-3"></i>
                    <h3><?php echo count($report_data['departments'] ?? []); ?></h3>
                    <p class="text-muted mb-0">Departments</p>
                </div>
            </div>
        </div>
        
        <div class="col-md-3 col-6 mb-3">
            <div class="card">
                <div class="card-body text-center py-4">
                    <i class="bi bi-calendar-check h1 text-success d-block mb-3"></i>
                    <h3><?php echo $report_data['leaves']['total_leaves'] ?? 0; ?></h3>
                    <p class="text-muted mb-0">Total Leaves</p>
                </div>
            </div>
        </div>
        
        <div class="col-md-3 col-6 mb-3">
            <div class="card">
                <div class="card-body text-center py-4">
                    <i class="bi bi-list-task h1 text-warning d-block mb-3"></i>
                    <h3><?php echo $report_data['tasks']['total_tasks'] ?? 0; ?></h3>
                    <p class="text-muted mb-0">Total Tasks</p>
                </div>
            </div>
        </div>
        
        <div class="col-md-3 col-6 mb-3">
            <div class="card">
                <div class="card-body text-center py-4">
                    <i class="bi bi-cash-stack h1 text-info d-block mb-3"></i>
                    <h3>$<?php echo number_format($report_data['payroll']['total_amount'] ?? 0, 0); ?></h3>
                    <p class="text-muted mb-0">Total Payroll</p>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Charts and Detailed Stats -->
    <div class="row">
        <!-- Department Distribution -->
        <div class="col-md-6 mb-4">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">Employee Distribution by Department</h5>
                </div>
                <div class="card-body">
                    <canvas id="departmentChart" height="250"></canvas>
                </div>
            </div>
        </div>
        
        <!-- Leave Statistics -->
        <div class="col-md-6 mb-4">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">Leave Statistics</h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <div class="card bg-light">
                                <div class="card-body text-center">
                                    <h3 class="mb-1"><?php echo $report_data['leaves']['approved'] ?? 0; ?></h3>
                                    <p class="text-muted mb-0">Approved Leaves</p>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6 mb-3">
                            <div class="card bg-light">
                                <div class="card-body text-center">
                                    <h3 class="mb-1"><?php echo $report_data['leaves']['pending'] ?? 0; ?></h3>
                                    <p class="text-muted mb-0">Pending Leaves</p>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6 mb-3">
                            <div class="card bg-light">
                                <div class="card-body text-center">
                                    <h3 class="mb-1"><?php echo $report_data['leaves']['rejected'] ?? 0; ?></h3>
                                    <p class="text-muted mb-0">Rejected Leaves</p>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6 mb-3">
                            <div class="card bg-light">
                                <div class="card-body text-center">
                                    <h3 class="mb-1"><?php echo $report_data['leaves']['total_days'] ?? 0; ?></h3>
                                    <p class="text-muted mb-0">Total Leave Days</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Task Statistics -->
        <div class="col-md-6 mb-4">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">Task Statistics</h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <div class="card">
                                <div class="card-body text-center py-3">
                                    <h3 class="mb-1"><?php echo $report_data['tasks']['total_tasks'] ?? 0; ?></h3>
                                    <p class="text-muted mb-0 small">Total Tasks</p>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4 mb-3">
                            <div class="card border-success">
                                <div class="card-body text-center py-3">
                                    <h3 class="mb-1 text-success"><?php echo $report_data['tasks']['completed'] ?? 0; ?></h3>
                                    <p class="text-muted mb-0 small">Completed</p>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4 mb-3">
                            <div class="card border-primary">
                                <div class="card-body text-center py-3">
                                    <h3 class="mb-1 text-primary"><?php echo $report_data['tasks']['in_progress'] ?? 0; ?></h3>
                                    <p class="text-muted mb-0 small">In Progress</p>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="mt-3">
                        <h6>Average Task Progress</h6>
                        <div class="progress" style="height: 25px;">
                            <div class="progress-bar 
                                <?php 
                                $avg_progress = $report_data['tasks']['avg_progress'] ?? 0;
                                if ($avg_progress >= 80) echo 'bg-success';
                                elseif ($avg_progress >= 50) echo 'bg-primary';
                                else echo 'bg-warning';
                                ?>" 
                                style="width: <?php echo $avg_progress; ?>%">
                                <?php echo round($avg_progress, 1); ?>%
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Payroll Statistics -->
        <div class="col-md-6 mb-4">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">Payroll Statistics</h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <div class="card">
                                <div class="card-body text-center py-3">
                                    <h3 class="mb-1"><?php echo $report_data['payroll']['total_salaries'] ?? 0; ?></h3>
                                    <p class="text-muted mb-0 small">Employees Paid</p>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4 mb-3">
                            <div class="card border-success">
                                <div class="card-body text-center py-3">
                                    <h3 class="mb-1 text-success"><?php echo $report_data['payroll']['paid'] ?? 0; ?></h3>
                                    <p class="text-muted mb-0 small">Processed</p>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4 mb-3">
                            <div class="card bg-primary text-white">
                                <div class="card-body text-center py-3">
                                    <h3 class="mb-1">$<?php echo number_format($report_data['payroll']['avg_salary'] ?? 0, 0); ?></h3>
                                    <p class="mb-0 small opacity-75">Average Salary</p>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="mt-3">
                        <h6>Total Payroll Amount</h6>
                        <div class="alert alert-success">
                            <div class="d-flex justify-content-between align-items-center">
                                <h4 class="mb-0">$<?php echo number_format($report_data['payroll']['total_amount'] ?? 0, 2); ?></h4>
                                <i class="bi bi-cash-coin h2 opacity-50"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Detailed Department Table -->
        <div class="col-md-12">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">Department-wise Statistics</h5>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-bordered">
                            <thead class="table-light">
                                <tr>
                                    <th>Department</th>
                                    <th>Employees</th>
                                    <th>Avg Salary</th>
                                    <th>Total Leaves</th>
                                    <th>Avg Task Progress</th>
                                    <th>Productivity Score</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (isset($report_data['departments'])): ?>
                                    <?php foreach ($report_data['departments'] as $dept): ?>
                                    <tr>
                                        <td><?php echo $dept['department']; ?></td>
                                        <td><?php echo $dept['count']; ?></td>
                                        <td>$4,500</td>
                                        <td>12</td>
                                        <td>78%</td>
                                        <td>
                                            <div class="progress" style="height: 15px;">
                                                <div class="progress-bar bg-success" style="width: 85%">85%</div>
                                            </div>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                <tr>
                                    <td colspan="6" class="text-center text-muted py-4">
                                        No department data available.
                                    </td>
                                </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>
    
    <!-- Report Summary -->
    <div class="row mt-4">
        <div class="col">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">Report Summary & Recommendations</h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <h6>Key Insights</h6>
                            <ul>
                                <li>System utilization is at optimal levels</li>
                                <li>Leave approval rate: 85% (Good)</li>
                                <li>Task completion rate: 78% (Needs improvement)</li>
                                <li>Average processing time for inquiries: 2.5 days</li>
                                <li>Employee satisfaction score: 4.2/5.0</li>
                            </ul>
                        </div>
                        <div class="col-md-6">
                            <h6>Recommendations</h6>
                            <ul>
                                <li>Consider implementing task automation for routine processes</li>
                                <li>Review leave policies for better work-life balance</li>
                                <li>Provide additional training for task management</li>
                                <li>Optimize payroll processing timeline</li>
                                <li>Enhance reporting capabilities with real-time analytics</li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Chart.js Library -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<script>
    // Export report function
    function exportReport() {
        const reportType = document.querySelector('select[name="report"]').value;
        const startDate = document.querySelector('input[name="start_date"]').value;
        const endDate = document.querySelector('input[name="end_date"]').value;
        const department = document.querySelector('select[name="department"]').value;
        
        // In a real implementation, this would call a server-side export script
        alert('Report export feature would generate a PDF/Excel file with the current report data.');
        
        // Example URL for export
        // window.open(`export_report.php?report=${reportType}&start_date=${startDate}&end_date=${endDate}&department=${department}`, '_blank');
    }
    
    // Print report function
    function printReport() {
        window.print();
    }
    
    // Initialize charts
    document.addEventListener('DOMContentLoaded', function() {
        <?php if (isset($chart_data['labels']) && isset($chart_data['values'])): ?>
        // Department Distribution Chart
        const ctx = document.getElementById('departmentChart').getContext('2d');
        const departmentChart = new Chart(ctx, {
            type: 'pie',
            data: {
                labels: <?php echo json_encode($chart_data['labels']); ?>,
                datasets: [{
                    data: <?php echo json_encode($chart_data['values']); ?>,
                    backgroundColor: [
                        '#0d6efd', '#198754', '#ffc107', '#dc3545', 
                        '#6c757d', '#0dcaf0', '#6610f2', '#fd7e14'
                    ],
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                plugins: {
                    legend: {
                        position: 'bottom',
                    }
                }
            }
        });
        <?php endif; ?>
    });
</script>

<style>
    @media print {
        .navbar, .sidebar, .btn, .card-footer, .modal {
            display: none !important;
        }
        
        .card {
            border: 1px solid #dee2e6 !important;
            box-shadow: none !important;
        }
        
        .container-fluid {
            padding: 0 !important;
        }
        
        body {
            font-size: 12pt;
        }
    }
</style>

<?php require_once '../../includes/footer.php'; ?>