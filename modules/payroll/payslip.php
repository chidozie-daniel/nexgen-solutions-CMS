<?php
// Define entry point constant to allow access
define('APP_ENTRY_POINT', true);

require_once '../../includes/auth.php';
require_once '../../includes/functions.php';

// Check login status first
Auth::requireLogin();

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    die("Invalid request");
}

$salary_id = $_GET['id'];
$conn = getDBConnection();
$user_id = $_SESSION['user_id'];
$role = $_SESSION['role'];

// Fetch salary record
$sql = "SELECT s.*, u.full_name, u.employee_id, u.department, u.position 
        FROM salaries s 
        JOIN users u ON s.user_id = u.id 
        WHERE s.id = ?";

// If not admin/hr, ensure user owns the record
if ($role != 'admin' && $role != 'hr') {
    $sql .= " AND s.user_id = ?";
}

$stmt = $conn->prepare($sql);

if ($role != 'admin' && $role != 'hr') {
    $stmt->bind_param("ii", $salary_id, $user_id);
} else {
    $stmt->bind_param("i", $salary_id);
}

$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows == 0) {
    die("Payslip not found or access denied.");
}

$payslip = $result->fetch_assoc();
$month_name = date('F Y', strtotime($payslip['month'] . '-01'));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payslip - <?php echo $month_name; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            background-color: #f5f7fa;
            color: #333;
        }
        .payslip-container {
            max-width: 800px;
            margin: 30px auto;
            background: white;
            padding: 40px;
            border-radius: 8px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.05);
        }
        .company-logo {
            font-size: 24px;
            font-weight: bold;
            color: #0072ff;
        }
        .payslip-title {
            text-align: right;
            color: #666;
        }
        .table-borderless td {
            padding: 5px 0;
        }
        .amount-col {
            text-align: right;
            font-weight: 500;
        }
        .total-row {
            border-top: 2px solid #eee;
            border-bottom: 2px double #eee;
            font-weight: bold;
            background-color: #f8f9fa;
        }
        
        @media print {
            body { background: white; }
            .payslip-container {
                box-shadow: none;
                margin: 0;
                padding: 0;
            }
            .no-print { display: none; }
        }
    </style>
</head>
<body>

<div class="container">
    <div class="payslip-container">
        <!-- Header -->
        <div class="row mb-5">
            <div class="col-6">
                <div class="company-logo">NexGen HRMS</div>
                <div class="small text-muted">
                    123 Tech Park, Silicon Valley<br>
                    San Francisco, CA 94000<br>
                    contact@nexgen.com
                </div>
            </div>
            <div class="col-6 payslip-title">
                <h4 class="mb-0">PAYSLIP</h4>
                <div class="small">For the month of <?php echo $month_name; ?></div>
                <div class="small mt-2">
                    <strong>Ref:</strong> <?php echo 'SAL-' . str_replace('-', '', $payslip['month']) . '-' . $payslip['id']; ?>
                </div>
            </div>
        </div>

        <!-- Employee Details -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="card bg-light border-0">
                    <div class="card-body">
                        <div class="row">
                            <div class="col-6">
                                <table class="table table-borderless table-sm mb-0">
                                    <tr>
                                        <td class="text-muted" width="100">Name:</td>
                                        <td><strong><?php echo htmlspecialchars($payslip['full_name']); ?></strong></td>
                                    </tr>
                                    <tr>
                                        <td class="text-muted">ID:</td>
                                        <td><?php echo htmlspecialchars($payslip['employee_id']); ?></td>
                                    </tr>
                                    <tr>
                                        <td class="text-muted">Title:</td>
                                        <td><?php echo htmlspecialchars($payslip['position']); ?></td>
                                    </tr>
                                </table>
                            </div>
                            <div class="col-6">
                                <table class="table table-borderless table-sm mb-0">
                                    <tr>
                                        <td class="text-muted">Department:</td>
                                        <td><?php echo htmlspecialchars($payslip['department']); ?></td>
                                    </tr>
                                    <tr>
                                        <td class="text-muted">Paid On:</td>
                                        <td><?php echo $payslip['payment_date'] ? date('M d, Y', strtotime($payslip['payment_date'])) : 'Pending'; ?></td>
                                    </tr>
                                    <tr>
                                        <td class="text-muted">Method:</td>
                                        <td><?php echo ucfirst(str_replace('_', ' ', $payslip['payment_method'])); ?></td>
                                    </tr>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Salary Breakdown -->
        <div class="row">
            <div class="col-6">
                <h6 class="text-uppercase text-muted small mb-3">Earnings</h6>
                <table class="table table-sm">
                    <tr>
                        <td>Basic Salary</td>
                        <td class="amount-col"><?php echo number_format($payslip['basic_salary'], 2); ?></td>
                    </tr>
                    <?php if ($payslip['overtime_hours'] > 0): ?>
                    <tr>
                        <td>Overtime (<?php echo $payslip['overtime_hours']; ?>h)</td>
                        <td class="amount-col"><?php echo number_format($payslip['overtime_hours'] * $payslip['overtime_rate'], 2); ?></td>
                    </tr>
                    <?php endif; ?>
                    <?php if ($payslip['bonus'] > 0): ?>
                    <tr>
                        <td>Bonus / Incentives</td>
                        <td class="amount-col"><?php echo number_format($payslip['bonus'], 2); ?></td>
                    </tr>
                    <?php endif; ?>
                    <tr class="total-row">
                        <td>Total Earnings</td>
                        <td class="amount-col">
                            <?php 
                            $total_earnings = $payslip['basic_salary'] + ($payslip['overtime_hours'] * $payslip['overtime_rate']) + $payslip['bonus'];
                            echo number_format($total_earnings, 2); 
                            ?>
                        </td>
                    </tr>
                </table>
            </div>
            
            <div class="col-6">
                <h6 class="text-uppercase text-muted small mb-3">Deductions</h6>
                <table class="table table-sm">
                    <tr>
                        <td>Income Tax</td>
                        <td class="amount-col text-danger">-<?php echo number_format($payslip['tax'], 2); ?></td>
                    </tr>
                    <?php if ($payslip['deductions'] > 0): ?>
                    <tr>
                        <td>Other Deductions</td>
                        <td class="amount-col text-danger">-<?php echo number_format($payslip['deductions'], 2); ?></td>
                    </tr>
                    <?php endif; ?>
                    <tr class="total-row">
                        <td>Total Deductions</td>
                        <td class="amount-col text-danger">
                            -<?php echo number_format($payslip['tax'] + $payslip['deductions'], 2); ?>
                        </td>
                    </tr>
                </table>
            </div>
        </div>

        <!-- Net Pay -->
        <div class="row mt-4">
            <div class="col-12">
                <div class="alert alert-success d-flex justify-content-between align-items-center mb-0">
                    <span class="text-uppercase fw-bold">Net Payable</span>
                    <span class="fs-4 fw-bold">$<?php echo number_format($payslip['net_salary'], 2); ?></span>
                </div>
            </div>
        </div>

        <!-- Footer -->
        <div class="row mt-5">
            <div class="col-12 text-center text-muted small">
                <p>This is a computer-generated document. No signature is required.</p>
                <p class="mb-0">NexGen HRMS &copy; <?php echo date('Y'); ?></p>
            </div>
        </div>
        
        <!-- Print Button -->
        <div class="text-center mt-4 no-print">
            <button onclick="window.print()" class="btn btn-primary btn-lg">
                <i class="bi bi-printer"></i> Print Payslip
            </button>
            <button onclick="window.close()" class="btn btn-outline-secondary btn-lg ms-2">
                Close
            </button>
        </div>
    </div>
</div>

</body>
</html>
