<?php
require_once '../../includes/auth.php';
require_once '../../includes/functions.php';

// Check login status first
Auth::requireLogin();

$page_title = 'Apply for Leave';
$conn = getDBConnection();
$user_id = $_SESSION['user_id'];

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $leave_type = $_POST['leave_type'];
    $start_date = $_POST['start_date'];
    $end_date = $_POST['end_date'];
    $reason = $_POST['reason'];
    
    // Calculate days
    $start = new DateTime($start_date);
    $end = new DateTime($end_date);
    $interval = $start->diff($end);
    $duration_days = $interval->days + 1; // Inclusive
    
    // Check leave balance
    $balance = getLeaveBalance($user_id, $leave_type);
    if ($duration_days > $balance) {
        setFlash('danger', "Insufficient $leave_type leave balance. Available: $balance days");
    } else {
        // Check for overlapping leaves
        $check_sql = "SELECT COUNT(*) as count FROM leaves 
                      WHERE user_id = ? 
                      AND status != 'rejected' 
                      AND start_date <= ? 
                      AND end_date >= ?";
        $check_stmt = $conn->prepare($check_sql);
        $check_stmt->bind_param("iss", $user_id, $end_date, $start_date);
        $check_stmt->execute();
        $is_overlap = $check_stmt->get_result()->fetch_assoc()['count'] > 0;

        if ($is_overlap) {
             setFlash('danger', "You already have a pending or approved leave application for this period.");
        } else {
            // Insert leave application
            $sql = "INSERT INTO leaves (user_id, leave_type, start_date, end_date, duration_days, reason, status) 
                    VALUES (?, ?, ?, ?, ?, ?, 'pending')";
            
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("isssis", $user_id, $leave_type, $start_date, $end_date, $duration_days, $reason);
            
            if ($stmt->execute()) {
                setFlash('success', 'Leave application submitted successfully!');
                header('Location: ' . Auth::getBasePath() . '/modules/leave/my_leaves.php');
                exit();
            } else {
                setFlash('danger', 'Error submitting leave application.');
            }
        }
    }
}

// Get leave balances
$annual_balance = getLeaveBalance($user_id, 'annual');
$sick_balance = getLeaveBalance($user_id, 'sick');
$casual_balance = getLeaveBalance($user_id, 'casual');

require_once '../../includes/header.php';
?>
<div class="container-fluid">
    <div class="module-hero">
        <div class="container">
            <h1 class="mb-2">Apply for Leave</h1>
            <p class="mb-0">Submit a new leave request for approval</p>
        </div>
    </div>

    <div class="container-fluid">
        <div class="row">
            <div class="col-md-8">
                <div class="card">
                    <div class="card-header bg-transparent">
                        <h5 class="mb-0 text-primary">New Application</h5>
                    </div>
                <div class="card-body">
                    <form method="POST" action="<?php echo $base_url; ?>/modules/leave/apply.php">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="leave_type" class="form-label">Leave Type</label>
                                <select class="form-select" id="leave_type" name="leave_type" required>
                                    <option value="">Select Leave Type</option>
                                    <option value="annual">Annual Leave</option>
                                    <option value="sick">Sick Leave</option>
                                    <option value="casual">Casual Leave</option>
                                    <option value="maternity">Maternity Leave</option>
                                    <option value="paternity">Paternity Leave</option>
                                    <option value="unpaid">Unpaid Leave</option>
                                </select>
                            </div>
                            
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Available Balance</label>
                                <div class="alert alert-info py-2">
                                    <div id="balance_display">
                                        Select a leave type to see balance
                                    </div>
                                </div>
                            </div>
                            
                            <div class="col-md-6 mb-3">
                                <label for="start_date" class="form-label">Start Date</label>
                                <input type="date" class="form-control" id="start_date" name="start_date" required 
                                       min="<?php echo date('Y-m-d'); ?>">
                            </div>
                            
                            <div class="col-md-6 mb-3">
                                <label for="end_date" class="form-label">End Date</label>
                                <input type="date" class="form-control" id="end_date" name="end_date" required>
                            </div>
                            
                            <div class="col-md-12 mb-3">
                                <label for="reason" class="form-label">Reason for Leave</label>
                                <textarea class="form-control" id="reason" name="reason" rows="4" required 
                                          placeholder="Please provide details of your leave request..."></textarea>
                            </div>
                            
                            <div class="col-md-12">
                                <button type="submit" class="btn btn-primary">Submit Application</button>
                                <a href="<?php echo $base_url; ?>/modules/leave/my_leaves.php" class="btn btn-outline-secondary">Cancel</a>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        
        <div class="col-md-4">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">Leave Balances</h5>
                </div>
                <div class="card-body">
                    <div class="list-group">
                        <div class="list-group-item d-flex justify-content-between align-items-center">
                            Annual Leave
                            <span class="badge bg-primary rounded-pill"><?php echo $annual_balance; ?> days</span>
                        </div>
                        <div class="list-group-item d-flex justify-content-between align-items-center">
                            Sick Leave
                            <span class="badge bg-warning text-dark rounded-pill"><?php echo $sick_balance; ?> days</span>
                        </div>
                        <div class="list-group-item d-flex justify-content-between align-items-center">
                            Casual Leave
                            <span class="badge bg-success rounded-pill"><?php echo $casual_balance; ?> days</span>
                        </div>
                    </div>
                    
                    <div class="mt-4">
                        <h6>Leave Policy</h6>
                        <ul class="small text-muted">
                            <li>Submit leave applications at least 3 days in advance</li>
                            <li>Emergency leaves can be applied on the same day</li>
                            <li>Maximum 15 days of continuous leave allowed</li>
                            <li>Medical certificate required for sick leave > 3 days</li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    // Update balance display when leave type changes
    document.getElementById('leave_type').addEventListener('change', function() {
        const balances = {
            'annual': <?php echo $annual_balance; ?>,
            'sick': <?php echo $sick_balance; ?>,
            'casual': <?php echo $casual_balance; ?>,
            'maternity': 90,
            'paternity': 7,
            'unpaid': 'Unlimited'
        };
        
        const type = this.value;
        const balance = balances[type] || 'N/A';
        const display = document.getElementById('balance_display');
        
        if (type) {
            display.innerHTML = `<strong>${type.charAt(0).toUpperCase() + type.slice(1)} Leave:</strong> ${balance} days available`;
        } else {
            display.innerHTML = 'Select a leave type to see balance';
        }
    });
    
    // Set minimum end date based on start date
    document.getElementById('start_date').addEventListener('change', function() {
        const endDate = document.getElementById('end_date');
        endDate.min = this.value;
        if (endDate.value && endDate.value < this.value) {
            endDate.value = this.value;
        }
    });
</script>

<?php require_once '../../includes/footer.php'; ?>