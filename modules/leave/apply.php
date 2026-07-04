<?php
// Define entry point constant
define('APP_ENTRY_POINT', true);

require_once '../../includes/auth.php';
require_once '../../includes/functions.php';

// Check login status first
Auth::requireLogin();

$page_title = 'Apply for Leave';
$conn = getDBConnection();
$user_id = $_SESSION['user_id'];

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Verify CSRF token
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        setFlash('danger', 'Invalid security token. Please try again.');
        header('Location: apply.php');
        exit();
    }
    
    $leave_type = $_POST['leave_type'] ?? '';
    $start_date = $_POST['start_date'] ?? '';
    $end_date = $_POST['end_date'] ?? '';
    $reason = sanitizeText($_POST['reason'] ?? '', 1000, true);

    $errors = [];
    $allowed_types = ['annual', 'sick', 'casual'];
    if (!in_array($leave_type, $allowed_types, true)) {
        $errors[] = 'Please select a valid leave type.';
    }
    if (!isValidDate($start_date) || !isValidDate($end_date)) {
        $errors[] = 'Please provide valid start and end dates.';
    } elseif (strtotime($end_date) < strtotime($start_date)) {
        $errors[] = 'End date cannot be before start date.';
    }
    if ($reason === '' || strlen($reason) < 5) {
        $errors[] = 'Please provide a brief reason for leave.';
    }

    if (!empty($errors)) {
        setFlash('danger', implode('<br>', $errors));
    } else {
        // Calculate days intelligently
        $start = new DateTime($start_date);
        $end = new DateTime($end_date);
        
        $duration_days = 0;
        $temp_date = clone $start;
        $working_days_only = in_array($leave_type, ['annual', 'sick', 'casual']);
        
        while ($temp_date <= $end) {
            if ($working_days_only) {
                // Skip weekends (6 = Saturday, 7 = Sunday) for standard leave types
                if ($temp_date->format('N') < 6) {
                    $duration_days++;
                }
            } else {
                $duration_days++;
            }
            $temp_date->modify('+1 day');
        }
        
        if ($duration_days == 0) {
            setFlash('danger', 'The selected period contains 0 valid working days.');
        } else {
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
                // Check if employee has a Project Leader assigned (via tasks OR project_members)
                $pl_check_sql = "SELECT DISTINCT u.id, u.full_name
                                 FROM users u
                                 WHERE u.role = 'project_leader'
                                 AND u.status = 'active'
                                 AND (
                                     u.id IN (SELECT assigned_by FROM tasks WHERE assigned_to = ?)
                                     OR u.id IN (
                                         SELECT p.project_leader FROM project_members pm
                                         JOIN projects p ON pm.project_id = p.id
                                         WHERE pm.user_id = ? AND p.project_leader IS NOT NULL
                                     )
                                 )";
                $pl_check_stmt = $conn->prepare($pl_check_sql);
                $pl_check_stmt->bind_param("ii", $user_id, $user_id);
                $pl_check_stmt->execute();
                $pl_check_result = $pl_check_stmt->get_result();
                $has_pl = ($pl_check_result->num_rows > 0);

                // Determine initial PL recommendation status
                if ($has_pl) {
                    $pl_recommendation = 'pending';
                    $leave_status = 'pending';
                } else {
                    // No PL assigned - auto-skip PL stage, go directly to HR
                    $pl_recommendation = 'recommended';
                    $leave_status = 'pending';
                }

                // Insert leave application with PL recommendation status
                $sql = "INSERT INTO leaves (user_id, leave_type, start_date, end_date, duration_days, reason, status, pl_recommendation)
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?)";

                $stmt = $conn->prepare($sql);
                $stmt->bind_param("isssisss", $user_id, $leave_type, $start_date, $end_date, $duration_days, $reason, $leave_status, $pl_recommendation);

                if ($stmt->execute()) {
                    $leave_id = $conn->insert_id;

                    // Log activity
                    $log_detail = 'Employee applied for leave: ' . $leave_type;
                    if (!$has_pl) {
                        $log_detail .= ' (No PL assigned - auto-routed to HR)';
                    }
                    logActivity('LEAVE_APPLY', $log_detail, 'leaves', $leave_id, null, [
                        'leave_type' => $leave_type,
                        'start_date' => $start_date,
                        'end_date' => $end_date,
                        'duration_days' => $duration_days,
                        'has_pl' => $has_pl
                    ]);

                    // Get user data for notification
                    $user_data = Auth::getCurrentUser();

                    if ($has_pl) {
                        // SEQUENTIAL WORKFLOW: Notify ONLY PL(s) - HR will be notified AFTER PL reviews
                        while ($pl = $pl_check_result->fetch_assoc()) {
                            createNotification($pl['id'],
                                'New Leave Request from ' . $user_data['full_name'],
                                $user_data['full_name'] . ' has applied for ' . $leave_type . ' leave from ' . formatDate($start_date, 'M d') . ' to ' . formatDate($end_date, 'M d') . ' (' . $duration_days . ' days). Please review and provide your recommendation.',
                                'warning',
                                'leave',
                                '/modules/leave/manage.php',
                                $user_id
                            );
                        }

                        $success_message = 'Leave application submitted successfully! Your Project Leader will review it first.';
                    } else {
                        // NO PL assigned: Notify HR directly (auto-skipped PL stage)
                        $hr_sql = "SELECT id, full_name, email FROM users WHERE role = 'hr' AND status = 'active'";
                        $hr_result = $conn->query($hr_sql);

                        while ($hr = $hr_result->fetch_assoc()) {
                            createNotification($hr['id'],
                                'New Leave Request: ' . $user_data['full_name'],
                                $user_data['full_name'] . ' has applied for ' . $leave_type . ' leave (' . $duration_days . ' days). No Project Leader is assigned - ready for your decision.',
                                'warning',
                                'leave',
                                '/modules/leave/manage.php',
                                $user_id
                            );
                        }

                        $success_message = 'Leave application submitted successfully! HR will review your request.';
                    }

                    setFlash('success', $success_message);
                    header('Location: ' . Auth::getBasePath() . '/modules/leave/my_leaves.php');
                    exit();
                } else {
                    setFlash('danger', 'Error submitting leave application.');
                }
            }
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
                        <?php echo csrfField(); ?>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="leave_type" class="form-label">Leave Type</label>
                                <select class="form-select" id="leave_type" name="leave_type" data-required="true">
                                    <option value="">Select Leave Type</option>
                                    <option value="annual">Annual Leave</option>
                                    <option value="sick">Sick Leave</option>
                                    <option value="casual">Casual Leave</option>
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
                                <input type="date" class="form-control" id="start_date" name="start_date" data-required="true" 
                                       min="<?php echo date('Y-m-d'); ?>">
                            </div>
                            
                            <div class="col-md-6 mb-3">
                                <label for="end_date" class="form-label">End Date</label>
                                <input type="date" class="form-control" id="end_date" name="end_date" data-required="true">
                            </div>
                            
                            <div class="col-md-12 mb-3">
                                <label for="reason" class="form-label">Reason for Leave</label>
                                <textarea class="form-control" id="reason" name="reason" rows="4" data-required="true" 
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
            'casual': <?php echo $casual_balance; ?>
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
