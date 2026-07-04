<?php
// Define entry point constant
define('APP_ENTRY_POINT', true);

require_once '../../includes/auth.php';
require_once '../../includes/functions.php';

// Check login status and require HR, admin, or project leader role
Auth::requireRole(['hr', 'admin', 'project_leader']);

$conn = getDBConnection();
$user_id = $_SESSION['user_id'];

// Handle leave actions via POST
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && isset($_POST['id'])) {
    // Verify CSRF token
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        setFlash('danger', 'Invalid security token. Please try again.');
        header('Location: manage.php');
        exit();
    }
    
    $action = $_POST['action'];
    $leave_id = (int)$_POST['id'];
    $remarks = sanitizeText($_POST['remarks'] ?? '', 1000, true);

    $allowed_actions = ['approve', 'reject', 'recommend', 'not_recommend', 'cancel', 'delete', 'bulk_delete'];
    if (!in_array($action, $allowed_actions, true) || $leave_id <= 0) {
        setFlash('danger', 'Invalid leave action.');
        header('Location: manage.php');
        exit();
    }

    // Handle bulk delete (delete all leaves matching criteria)
    if ($action === 'bulk_delete') {
        if (!Auth::hasRole('admin')) {
            setFlash('danger', 'Only Admin can delete leave applications.');
            header('Location: ' . Auth::getBasePath() . '/modules/leave/manage.php');
            exit();
        }

        $delete_criteria = $_POST['delete_criteria'] ?? '';
        $deleted_count = 0;

        switch ($delete_criteria) {
            case 'cancelled':
                $del_sql = "DELETE FROM leaves WHERE status = 'cancelled'";
                break;
            case 'rejected':
                $del_sql = "DELETE FROM leaves WHERE status = 'rejected'";
                break;
            case 'old_approved':
                $del_sql = "DELETE FROM leaves WHERE status = 'approved' AND end_date < DATE_SUB(CURDATE(), INTERVAL 90 DAY)";
                break;
            case 'all_test_data':
                $del_sql = "DELETE FROM leaves WHERE reason LIKE '%Test%' OR reason LIKE '%test%'";
                break;
            default:
                setFlash('danger', 'Invalid delete criteria.');
                header('Location: ' . Auth::getBasePath() . '/modules/leave/manage.php');
                exit();
        }

        $del_stmt = $conn->prepare($del_sql);
        if ($del_stmt->execute()) {
            $deleted_count = $del_stmt->affected_rows;
            logActivity('LEAVE_BULK_DELETE', "Bulk deleted $deleted_count leave applications ($delete_criteria)", 'leaves', 0);
            setFlash('success', "Successfully deleted $deleted_count leave application(s).");
        } else {
            error_log("Error bulk deleting leaves by admin $user_id: " . $del_stmt->error);
            setFlash('danger', 'Error deleting leave applications. Please try again.');
        }
        header('Location: ' . Auth::getBasePath() . '/modules/leave/manage.php');
        exit();
    }

    // Handle deletion separately (different logic)
    if ($action === 'delete') {
        if (!Auth::hasRole('admin')) {
            setFlash('danger', 'Only Admin can delete leave applications.');
            header('Location: ' . Auth::getBasePath() . '/modules/leave/manage.php');
            exit();
        }

        // Get leave details before deletion for logging
        $leave_sql = "SELECT l.*, u.full_name as employee_name FROM leaves l JOIN users u ON l.user_id = u.id WHERE l.id = ?";
        $leave_stmt = $conn->prepare($leave_sql);
        $leave_stmt->bind_param("i", $leave_id);
        $leave_stmt->execute();
        $leave_data = $leave_stmt->get_result()->fetch_assoc();

        if (!$leave_data) {
            setFlash('danger', 'Leave application not found.');
            header('Location: ' . Auth::getBasePath() . '/modules/leave/manage.php');
            exit();
        }

        $delete_stmt = $conn->prepare("DELETE FROM leaves WHERE id = ?");
        $delete_stmt->bind_param("i", $leave_id);

        if ($delete_stmt->execute()) {
            logActivity('LEAVE_DELETE', 'Leave application deleted: ' . $leave_data['employee_name'] . ' - ' . $leave_data['leave_type'], 'leaves', $leave_id);
            setFlash('success', 'Leave application deleted successfully.');
        } else {
            error_log("Error deleting leave ID $leave_id by admin $user_id: " . $delete_stmt->error);
            setFlash('danger', 'Error deleting leave application. Please try again.');
        }
        header('Location: ' . Auth::getBasePath() . '/modules/leave/manage.php');
        exit();
    }

    $sql = "UPDATE leaves SET status = ?, approved_by = ?, hr_remarks = ? WHERE id = ?";
    $stmt = $conn->prepare($sql);
    
    switch ($action) {
        case 'approve':
            if (!Auth::hasRole('hr') && !Auth::hasRole('admin')) {
                setFlash('danger', 'Only HR or Admin can approve leaves.');
                header('Location: ' . Auth::getBasePath() . '/modules/leave/manage.php');
                exit();
            }
            $status = 'approved';
            $sql = "UPDATE leaves SET status = ?, approved_by = ?, hr_remarks = ? WHERE id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("sisi", $status, $user_id, $remarks, $leave_id);
            $message = 'Leave approved successfully.';
            break;
            
        case 'reject':
            if (!Auth::hasRole('hr') && !Auth::hasRole('admin')) {
                setFlash('danger', 'Only HR or Admin can reject leaves.');
                header('Location: ' . Auth::getBasePath() . '/modules/leave/manage.php');
                exit();
            }
            $status = 'rejected';
            $sql = "UPDATE leaves SET status = ?, approved_by = ?, hr_remarks = ? WHERE id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("sisi", $status, $user_id, $remarks, $leave_id);
            $message = 'Leave rejected.';
            break;
            
        case 'recommend':
            if (!Auth::hasRole('project_leader') && !Auth::hasRole('admin')) {
                setFlash('danger', 'Only Project Leaders can recommend leaves.');
                header('Location: ' . Auth::getBasePath() . '/modules/leave/manage.php');
                exit();
            }
            $recommendation = 'recommended';
            $sql = "UPDATE leaves SET pl_recommendation = ?, pl_remarks = ? WHERE id = ? AND pl_recommendation IN ('none', 'pending')";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("ssi", $recommendation, $remarks, $leave_id);
            $message = 'Leave recommended to HR.';
            break;

        case 'not_recommend':
            if (!Auth::hasRole('project_leader') && !Auth::hasRole('admin')) {
                setFlash('danger', 'Only Project Leaders can review leaves.');
                header('Location: ' . Auth::getBasePath() . '/modules/leave/manage.php');
                exit();
            }
            $recommendation = 'not_recommended';
            $sql = "UPDATE leaves SET pl_recommendation = ?, pl_remarks = ? WHERE id = ? AND pl_recommendation IN ('none', 'pending')";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("ssi", $recommendation, $remarks, $leave_id);
            $message = 'Leave reviewed (Not Recommended).';
            break;

        case 'cancel':
            $status = 'cancelled';
            $sql = "UPDATE leaves SET status = ?, approved_by = ?, hr_remarks = ? WHERE id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("sisi", $status, $user_id, $remarks, $leave_id);
            $message = 'Leave cancelled.';
            break;
    }
    
    if ($stmt->execute()) {
        $affected_rows = $stmt->affected_rows;

        // For PL actions, check if the update actually changed anything (prevents double-reviewing)
        if (in_array($action, ['recommend', 'not_recommend']) && $affected_rows == 0) {
            setFlash('danger', 'This leave has already been reviewed by a Project Leader.');
            header('Location: ' . Auth::getBasePath() . '/modules/leave/manage.php');
            exit();
        }

        // Get leave details for notification
        $leave_sql = "SELECT l.*, u.full_name as employee_name, u.email as employee_email, u.full_name, u.email
                      FROM leaves l
                      JOIN users u ON l.user_id = u.id
                      WHERE l.id = ?";
        $leave_stmt = $conn->prepare($leave_sql);
        $leave_stmt->bind_param("i", $leave_id);
        $leave_stmt->execute();
        $leave_data = $leave_stmt->get_result()->fetch_assoc();

        // Log activity
        logActivity('LEAVE_' . strtoupper($action), 'Leave ' . $action . ': ' . $leave_data['employee_name'], 'leaves', $leave_id);

        // Send notifications based on action
        switch ($action) {
            case 'approve':
                // Notify employee
                createNotification($leave_data['user_id'],
                    'Leave Application Approved!',
                    'Your ' . $leave_data['leave_type'] . ' leave from ' . formatDate($leave_data['start_date'], 'M d') . ' to ' . formatDate($leave_data['end_date'], 'M d') . ' has been approved.' . ($remarks ? ' Remarks: ' . $remarks : ''),
                    'success',
                    'leave',
                    '/modules/leave/my_leaves.php',
                    $user_id
                );
                // Send email
                sendLeaveNotificationEmail($leave_data, 'approved', $remarks);
                break;

            case 'reject':
                // Notify employee
                createNotification($leave_data['user_id'],
                    'Leave Application Update',
                    'Your ' . $leave_data['leave_type'] . ' leave application has been rejected.' . ($remarks ? ' Remarks: ' . $remarks : ' Please contact HR for more information.'),
                    'danger',
                    'leave',
                    '/modules/leave/my_leaves.php',
                    $user_id
                );
                // Send email
                sendLeaveNotificationEmail($leave_data, 'rejected', $remarks);
                break;

            case 'recommend':
                // SEQUENTIAL WORKFLOW: PL has reviewed (Recommended) - NOW notify HR to make final decision
                $rec_label = 'Recommended';
                $hr_sql = "SELECT id, full_name FROM users WHERE role = 'hr' AND status = 'active'";
                $hr_result = $conn->query($hr_sql);
                while ($hr = $hr_result->fetch_assoc()) {
                    createNotification($hr['id'],
                        'Leave Ready for Your Decision - PL Recommended',
                        $leave_data['employee_name'] . '\'s ' . $leave_data['leave_type'] . ' leave (' . $leave_data['duration_days'] . ' days) has been reviewed by the Project Leader and RECOMMENDED. Please make the final approval/rejection decision.',
                        'success',
                        'leave',
                        '/modules/leave/manage.php',
                        $user_id
                    );
                }

                // Also notify Admin for oversight
                $admin_sql = "SELECT id, full_name FROM users WHERE role = 'admin' AND status = 'active'";
                $admin_result = $conn->query($admin_sql);
                while ($admin = $admin_result->fetch_assoc()) {
                    createNotification($admin['id'],
                        'PL Reviewed Leave - Ready for HR Decision',
                        $leave_data['employee_name'] . '\'s leave has been recommended by PL. Awaiting HR decision.',
                        'info',
                        'leave',
                        '/modules/leave/manage.php',
                        $user_id
                    );
                }
                break;

            case 'not_recommend':
                // SEQUENTIAL WORKFLOW: PL has reviewed (Not Recommended) - NOW notify HR to make final decision
                $hr_sql = "SELECT id, full_name FROM users WHERE role = 'hr' AND status = 'active'";
                $hr_result = $conn->query($hr_sql);
                while ($hr = $hr_result->fetch_assoc()) {
                    createNotification($hr['id'],
                        'Leave Ready for Your Decision - PL Not Recommended',
                        $leave_data['employee_name'] . '\'s ' . $leave_data['leave_type'] . ' leave (' . $leave_data['duration_days'] . ' days) has been reviewed by the Project Leader and NOT RECOMMENDED. Please review and make the final decision.' . ($remarks ? ' PL Remarks: ' . $remarks : ''),
                        'warning',
                        'leave',
                        '/modules/leave/manage.php',
                        $user_id
                    );
                }

                // Also notify Admin for oversight
                $admin_sql = "SELECT id, full_name FROM users WHERE role = 'admin' AND status = 'active'";
                $admin_result = $conn->query($admin_sql);
                while ($admin = $admin_result->fetch_assoc()) {
                    createNotification($admin['id'],
                        'PL Reviewed Leave - Not Recommended',
                        $leave_data['employee_name'] . '\'s leave has NOT been recommended by PL. Awaiting HR decision.',
                        'warning',
                        'leave',
                        '/modules/leave/manage.php',
                        $user_id
                    );
                }
                break;
        }

        setFlash('success', $message);
    } else {
        error_log("Error performing leave action '$action' on leave ID $leave_id by user $user_id: " . $stmt->error);
        setFlash('danger', 'Error processing leave request. Please try again.');
    }
    header('Location: ' . Auth::getBasePath() . '/modules/leave/manage.php');
    exit();
}

// Get all leave applications with filters
// Admin sees all by default, HR sees leaves after PL review, PL sees team leaves pending review
$status_filter = $_GET['status'] ?? (Auth::hasRole('admin') ? 'all' : 'pending');
$department_filter = $_GET['department'] ?? 'all';
$type_filter = $_GET['type'] ?? 'all';

$sql = "SELECT l.*, u.full_name, u.employee_id, u.department, u.position
        FROM leaves l
        JOIN users u ON l.user_id = u.id
        WHERE 1=1";

$params = [];
$types = "";

// SEQUENTIAL WORKFLOW: Role-based visibility filtering
if (Auth::hasRole('project_leader') && !Auth::hasRole('hr') && !Auth::hasRole('admin')) {
    // PL sees: leaves from their team members that haven't been reviewed yet (pl_recommendation IN ('none', 'pending'))
    $sql .= " AND l.pl_recommendation IN ('none', 'pending')";
    $sql .= " AND (u.id IN (SELECT assigned_to FROM tasks WHERE assigned_by = ?)
              OR u.id IN (SELECT user_id FROM project_members pm JOIN projects p ON pm.project_id = p.id WHERE p.project_leader = ?))";
    $params[] = $user_id;
    $params[] = $user_id;
    $types .= "ii";
} elseif (Auth::hasRole('hr') && !Auth::hasRole('admin')) {
    // HR sees: leaves that PL has reviewed (recommended/not_recommended)
    //          OR leaves where no PL is assigned (pl_recommendation = 'recommended' due to auto-skip)
    $sql .= " AND l.pl_recommendation IN ('recommended', 'not_recommended')";
} elseif (Auth::hasRole('admin')) {
    // Admin sees: ALL leaves (no additional filters)
    // No additional WHERE clause added
}

if ($status_filter != 'all') {
    $sql .= " AND l.status = ?";
    $params[] = $status_filter;
    $types .= "s";
}

if ($department_filter != 'all') {
    $sql .= " AND u.department = ?";
    $params[] = $department_filter;
    $types .= "s";
}

if ($type_filter != 'all') {
    $sql .= " AND l.leave_type = ?";
    $params[] = $type_filter;
    $types .= "s";
}

// Add date range filter if provided
if (!empty($_GET['date_from']) && isValidDate($_GET['date_from'])) {
    $sql .= " AND l.start_date >= ?";
    $params[] = $_GET['date_from'];
    $types .= "s";
}

if (!empty($_GET['date_to']) && isValidDate($_GET['date_to'])) {
    $sql .= " AND l.end_date <= ?";
    $params[] = $_GET['date_to'];
    $types .= "s";
}

$sql .= " ORDER BY l.created_at DESC";
$stmt = $conn->prepare($sql);

if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}

$stmt->execute();
$result = $stmt->get_result();

// Get departments for filter
$dept_sql = "SELECT DISTINCT department FROM users WHERE department IS NOT NULL ORDER BY department";
$dept_result = $conn->query($dept_sql);

// Get stats
$stats_sql = "SELECT 
    COUNT(*) as total,
    SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending,
    SUM(CASE WHEN status = 'approved' THEN 1 ELSE 0 END) as approved,
    SUM(CASE WHEN status = 'rejected' THEN 1 ELSE 0 END) as rejected,
    SUM(CASE WHEN status = 'cancelled' THEN 1 ELSE 0 END) as cancelled,
    SUM(duration_days) as total_days
    FROM leaves";
$stats_result = $conn->query($stats_sql);
$stats = $stats_result->fetch_assoc();

$page_title = 'Manage Leave Applications';
require_once '../../includes/header.php';
?>
<div class="container-fluid">
    <div class="row mb-4">
        <div class="col">
            <div class="d-flex justify-content-between align-items-center">
                <h4 class="mb-0">Leave Management</h4>
                <div class="d-flex gap-2">
                    <?php if (Auth::hasRole('admin')): ?>
                    <button type="button" class="btn btn-outline-danger" data-bs-toggle="modal" data-bs-target="#bulkDeleteModal">
                        <i class="bi bi-trash"></i> Cleanup
                    </button>
                    <?php endif; ?>
                    <a href="<?php echo $base_url; ?>/modules/admin/reports.php" class="btn btn-outline-primary">
                        <i class="bi bi-file-earmark-text"></i> Generate Reports
                    </a>
                </div>
            </div>
            <p class="text-muted mb-0">Approve, reject, or manage employee leave applications</p>
        </div>
    </div>
    
    <!-- Stats Cards -->
    <div class="row mb-4">
        <div class="col-md-2 col-6 mb-3">
            <div class="card">
                <div class="card-body text-center py-3">
                    <h3 class="mb-1"><?php echo $stats['total']; ?></h3>
                    <p class="text-muted mb-0 small">Total Leaves</p>
                </div>
            </div>
        </div>
        
        <div class="col-md-2 col-6 mb-3">
            <div class="card border-warning">
                <div class="card-body text-center py-3">
                    <h3 class="mb-1 text-warning"><?php echo $stats['pending']; ?></h3>
                    <p class="text-muted mb-0 small">Pending</p>
                </div>
            </div>
        </div>
        
        <div class="col-md-2 col-6 mb-3">
            <div class="card border-success">
                <div class="card-body text-center py-3">
                    <h3 class="mb-1 text-success"><?php echo $stats['approved']; ?></h3>
                    <p class="text-muted mb-0 small">Approved</p>
                </div>
            </div>
        </div>
        
        <div class="col-md-2 col-6 mb-3">
            <div class="card border-danger">
                <div class="card-body text-center py-3">
                    <h3 class="mb-1 text-danger"><?php echo $stats['rejected']; ?></h3>
                    <p class="text-muted mb-0 small">Rejected</p>
                </div>
            </div>
        </div>
        
        <div class="col-md-2 col-6 mb-3">
            <div class="card border-secondary">
                <div class="card-body text-center py-3">
                    <h3 class="mb-1 text-secondary"><?php echo $stats['cancelled']; ?></h3>
                    <p class="text-muted mb-0 small">Cancelled</p>
                </div>
            </div>
        </div>
        
        <div class="col-md-2 col-6 mb-3">
            <div class="card bg-light">
                <div class="card-body text-center py-3">
                    <h3 class="mb-1"><?php echo $stats['total_days']; ?></h3>
                    <p class="text-muted mb-0 small">Total Days</p>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Filters -->
    <div class="row mb-4">
        <div class="col">
            <div class="card">
                <div class="card-body">
                    <form method="GET" class="row g-3">
                        <div class="col-md-3">
                            <label class="form-label">Status</label>
                            <select name="status" class="form-select" onchange="this.form.submit()">
                                <option value="all" <?php echo $status_filter == 'all' ? 'selected' : ''; ?>>All Status</option>
                                <option value="pending" <?php echo $status_filter == 'pending' ? 'selected' : ''; ?>>Pending</option>
                                <option value="approved" <?php echo $status_filter == 'approved' ? 'selected' : ''; ?>>Approved</option>
                                <option value="rejected" <?php echo $status_filter == 'rejected' ? 'selected' : ''; ?>>Rejected</option>
                            </select>
                        </div>
                        
                        <div class="col-md-3">
                            <label class="form-label">Department</label>
                            <select name="department" class="form-select" onchange="this.form.submit()">
                                <option value="all" <?php echo $department_filter == 'all' ? 'selected' : ''; ?>>All Departments</option>
                                <?php while ($dept = $dept_result->fetch_assoc()): ?>
                                <option value="<?php echo $dept['department']; ?>" 
                                    <?php echo $department_filter == $dept['department'] ? 'selected' : ''; ?>>
                                    <?php echo $dept['department']; ?>
                                </option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                        
                        <div class="col-md-3">
                            <label class="form-label">Leave Type</label>
                            <select name="type" class="form-select" onchange="this.form.submit()">
                                <option value="all" <?php echo $type_filter == 'all' ? 'selected' : ''; ?>>All Types</option>
                                <option value="annual" <?php echo $type_filter == 'annual' ? 'selected' : ''; ?>>Annual</option>
                                <option value="sick" <?php echo $type_filter == 'sick' ? 'selected' : ''; ?>>Sick</option>
                                <option value="casual" <?php echo $type_filter == 'casual' ? 'selected' : ''; ?>>Casual</option>
                                <option value="maternity" <?php echo $type_filter == 'maternity' ? 'selected' : ''; ?>>Maternity</option>
                                <option value="paternity" <?php echo $type_filter == 'paternity' ? 'selected' : ''; ?>>Paternity</option>
                            </select>
                        </div>
                        
                        <div class="col-md-3">
                            <label class="form-label">Date Range</label>
                            <div class="input-group">
                                <input type="date" class="form-control" name="date_from" 
                                       value="<?php echo $_GET['date_from'] ?? ''; ?>">
                                <span class="input-group-text">to</span>
                                <input type="date" class="form-control" name="date_to" 
                                       value="<?php echo $_GET['date_to'] ?? ''; ?>">
                            </div>
                        </div>
                        
                        <div class="col-md-12">
                            <button type="submit" class="btn btn-primary">Apply Filters</button>
                            <a href="<?php echo $base_url; ?>/modules/leave/manage.php" class="btn btn-outline-secondary">Reset</a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Leave Applications Table -->
    <div class="row">
        <div class="col">
            <div class="card">
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead>
                                <tr>
                                    <th>Leave ID</th>
                                    <th>Employee</th>
                                    <th>Type</th>
                                    <th>Period</th>
                                    <th>Duration</th>
                                    <th>Reason</th>
                                    <th>Department</th>
                                    <th>Workflow Stage</th>
                                    <th>Status</th>
                                    <th>Applied On</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if ($result->num_rows > 0): ?>
                                    <?php while ($row = $result->fetch_assoc()): ?>
                                    <?php
                                        // Determine workflow stage for display
                                        $pl_rec = $row['pl_recommendation'] ?? 'none';
                                        $leave_status = $row['status'];

                                        if ($leave_status !== 'pending') {
                                            $workflow_stage = '<span class="badge bg-secondary">Finalized</span>';
                                        } elseif ($pl_rec === 'pending' || $pl_rec === 'none') {
                                            $workflow_stage = '<span class="badge bg-info text-dark"><i class="bi bi-person-badge me-1"></i>Awaiting PL Review</span>';
                                        } elseif ($pl_rec === 'recommended') {
                                            $workflow_stage = '<span class="badge bg-success"><i class="bi bi-check-circle me-1"></i>PL Recommended</span><br><span class="badge bg-warning text-dark mt-1"><i class="bi bi-arrow-down-circle me-1"></i>Ready for HR</span>';
                                        } elseif ($pl_rec === 'not_recommended') {
                                            $workflow_stage = '<span class="badge bg-warning text-dark"><i class="bi bi-x-circle me-1"></i>PL Not Recommended</span><br><span class="badge bg-warning text-dark mt-1"><i class="bi bi-arrow-down-circle me-1"></i>Ready for HR</span>';
                                        } else {
                                            $workflow_stage = '<span class="badge bg-light text-dark">Unknown</span>';
                                        }

                                        // Determine if HR can approve/reject (only after PL has reviewed)
                                        $hr_can_act = ($pl_rec === 'recommended' || $pl_rec === 'not_recommended') && $leave_status === 'pending';
                                    ?>
                                    <tr class="<?php echo $leave_status == 'pending' ? 'table-warning' : ''; ?>">
                                        <td>#LV-<?php echo str_pad($row['id'], 5, '0', STR_PAD_LEFT); ?></td>
                                        <td>
                                            <strong><?php echo htmlspecialchars($row['full_name']); ?></strong><br>
                                            <small class="text-muted"><?php echo htmlspecialchars($row['employee_id'] ?? 'N/A'); ?></small><br>
                                            <small class="text-muted"><?php echo htmlspecialchars($row['position'] ?? 'N/A'); ?></small>
                                        </td>
                                        <td>
                                            <span class="badge bg-info"><?php echo ucfirst($row['leave_type']); ?></span>
                                        </td>
                                        <td>
                                            <?php echo formatDate($row['start_date']); ?><br>
                                            <small>to <?php echo formatDate($row['end_date']); ?></small>
                                        </td>
                                        <td><?php echo $row['duration_days']; ?> days</td>
                                        <td>
                                            <small class="text-muted" title="<?php echo htmlspecialchars($row['reason'] ?? ''); ?>">
                                                <?php echo htmlspecialchars(substr($row['reason'] ?? '', 0, 50)); ?>
                                                <?php echo strlen($row['reason'] ?? '') > 50 ? '...' : ''; ?>
                                            </small>
                                        </td>
                                        <td><?php echo htmlspecialchars($row['department'] ?? 'N/A'); ?></td>
                                        <td><?php echo $workflow_stage; ?></td>
                                        <td><?php echo getStatusBadge($leave_status); ?></td>
                                        <td>
                                            <small><?php echo formatDate($row['created_at'], 'M d'); ?></small><br>
                                            <small class="text-muted"><?php echo date('h:i A', strtotime($row['created_at'])); ?></small>
                                        </td>
                                        <td>
                                            <div class="btn-group">
                                                <button type="button" class="btn btn-sm btn-outline-primary"
                                                        data-bs-toggle="modal" data-bs-target="#viewModal<?php echo $row['id']; ?>">
                                                    <i class="bi bi-eye"></i>
                                                </button>

                                                <?php if ($leave_status == 'pending' && Auth::hasRole('project_leader')): ?>
                                                <button type="button" class="btn btn-sm btn-outline-info"
                                                        data-bs-toggle="modal" data-bs-target="#recommendModal<?php echo $row['id']; ?>" title="Recommend">
                                                    <i class="bi bi-hand-thumbs-up"></i>
                                                </button>

                                                <button type="button" class="btn btn-sm btn-outline-warning"
                                                        data-bs-toggle="modal" data-bs-target="#notRecommendModal<?php echo $row['id']; ?>" title="Not Recommend">
                                                    <i class="bi bi-hand-thumbs-down"></i>
                                                </button>
                                                <?php endif; ?>

                                                <?php if ($leave_status == 'pending' && Auth::hasRole('hr') && !Auth::hasRole('admin')): ?>
                                                    <?php if ($hr_can_act): ?>
                                                    <button type="button" class="btn btn-sm btn-outline-success"
                                                            data-bs-toggle="modal" data-bs-target="#approveModal<?php echo $row['id']; ?>" title="Approve">
                                                        <i class="bi bi-check-lg"></i>
                                                    </button>

                                                    <button type="button" class="btn btn-sm btn-outline-danger"
                                                            data-bs-toggle="modal" data-bs-target="#rejectModal<?php echo $row['id']; ?>" title="Reject">
                                                        <i class="bi bi-x-lg"></i>
                                                    </button>
                                                    <?php else: ?>
                                                    <button type="button" class="btn btn-sm btn-outline-secondary disabled" title="Waiting for PL Review" disabled>
                                                        <i class="bi bi-hourglass-split"></i>
                                                    </button>
                                                    <?php endif; ?>
                                                <?php endif; ?>

                                                <?php if ($leave_status == 'pending' && Auth::hasRole('admin')): ?>
                                                    <button type="button" class="btn btn-sm btn-outline-success"
                                                            data-bs-toggle="modal" data-bs-target="#approveModal<?php echo $row['id']; ?>" title="Approve (Admin Override)">
                                                        <i class="bi bi-check-lg"></i>
                                                    </button>

                                                    <button type="button" class="btn btn-sm btn-outline-danger"
                                                            data-bs-toggle="modal" data-bs-target="#rejectModal<?php echo $row['id']; ?>" title="Reject">
                                                        <i class="bi bi-x-lg"></i>
                                                    </button>
                                                <?php endif; ?>

                                                <?php if (Auth::hasRole('admin')): ?>
                                                <button type="button" class="btn btn-sm btn-outline-danger"
                                                        data-bs-toggle="modal" data-bs-target="#deleteModal<?php echo $row['id']; ?>" title="Delete Application">
                                                    <i class="bi bi-trash"></i>
                                                </button>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                    </tr>
                                    
                                    <!-- View Modal -->
                                    <div class="modal fade" id="viewModal<?php echo $row['id']; ?>" tabindex="-1">
                                        <div class="modal-dialog modal-lg">
                                            <div class="modal-content">
                                                <div class="modal-header">
                                                    <h5 class="modal-title">Leave Details</h5>
                                                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                                </div>
                                                <div class="modal-body">
                                                    <div class="row mb-4">
                                                        <div class="col-md-8">
                                                            <h6>Employee Information</h6>
                                                            <p class="mb-1"><strong>Name:</strong> <?php echo htmlspecialchars($row['full_name']); ?></p>
                                                            <p class="mb-1"><strong>Employee ID:</strong> <?php echo htmlspecialchars($row['employee_id'] ?? 'N/A'); ?></p>
                                                            <p class="mb-1"><strong>Department:</strong> <?php echo htmlspecialchars($row['department'] ?? 'N/A'); ?></p>
                                                            <p class="mb-0"><strong>Position:</strong> <?php echo htmlspecialchars($row['position'] ?? 'N/A'); ?></p>
                                                        </div>
                                                        <div class="col-md-4">
                                                            <div class="card">
                                                                <div class="card-body">
                                                                    <h6>Leave Summary</h6>
                                                                    <p class="mb-1"><strong>Type:</strong> <?php echo ucfirst($row['leave_type']); ?></p>
                                                                    <p class="mb-1"><strong>Status:</strong> <?php echo getStatusBadge($row['status']); ?></p>
                                                                    <p class="mb-1"><strong>Workflow:</strong><br><?php
                                                                        $ws_pl = $row['pl_recommendation'] ?? 'none';
                                                                        if ($ws_pl === 'pending' || $ws_pl === 'none') {
                                                                            echo '<span class="badge bg-info text-dark">Awaiting PL Review</span>';
                                                                        } elseif ($ws_pl === 'recommended') {
                                                                            echo '<span class="badge bg-success">PL Recommended</span>';
                                                                        } elseif ($ws_pl === 'not_recommended') {
                                                                            echo '<span class="badge bg-warning text-dark">PL Not Recommended</span>';
                                                                        } else {
                                                                            echo '<span class="badge bg-secondary">N/A</span>';
                                                                        }
                                                                    ?></p>
                                                                    <p class="mb-1"><strong>Duration:</strong> <?php echo $row['duration_days']; ?> days</p>
                                                                    <p class="mb-0"><strong>Applied:</strong> <?php echo formatDate($row['created_at']); ?></p>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                    
                                                    <div class="row mb-4">
                                                        <div class="col-md-6">
                                                            <h6>Leave Period</h6>
                                                            <div class="card">
                                                                <div class="card-body">
                                                                    <p class="mb-2"><strong>Start Date:</strong> <?php echo formatDate($row['start_date'], 'l, F d, Y'); ?></p>
                                                                    <p class="mb-0"><strong>End Date:</strong> <?php echo formatDate($row['end_date'], 'l, F d, Y'); ?></p>
                                                                </div>
                                                            </div>
                                                        </div>
                                                        <div class="col-md-6">
                                                            <h6>Contact Information</h6>
                                                            <div class="card">
                                                                <div class="card-body">
                                                                    <p class="mb-2"><strong>During Leave:</strong> Employee will be reachable at their personal contact.</p>
                                                                    <p class="mb-0"><strong>Work Handover:</strong> Completed to team members.</p>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                    
                                                    <div class="mb-4">
                                                        <h6>Reason for Leave</h6>
                                                        <div class="card">
                                                            <div class="card-body">
                                                                <?php echo nl2br(htmlspecialchars($row['reason'])); ?>
                                                            </div>
                                                        </div>
                                                    </div>

                                                    <?php
                                                        $view_pl_rec = $row['pl_recommendation'] ?? 'none';
                                                        if ($view_pl_rec !== 'none' && $view_pl_rec !== 'pending'):
                                                    ?>
                                                    <div class="mb-3">
                                                        <h6>Project Leader Review</h6>
                                                        <div class="card <?php echo $view_pl_rec === 'recommended' ? 'border-success' : 'border-warning'; ?>">
                                                            <div class="card-body">
                                                                <p class="mb-1">
                                                                    <strong>Decision:</strong>
                                                                    <?php echo $view_pl_rec === 'recommended'
                                                                        ? '<span class="badge bg-success">Recommended</span>'
                                                                        : '<span class="badge bg-warning text-dark">Not Recommended</span>'; ?>
                                                                </p>
                                                                <?php if ($row['pl_remarks']): ?>
                                                                <p class="mb-0"><strong>Remarks:</strong> <?php echo nl2br(htmlspecialchars($row['pl_remarks'])); ?></p>
                                                                <?php endif; ?>
                                                            </div>
                                                        </div>
                                                    </div>
                                                    <?php endif; ?>

                                                    <?php if ($row['hr_remarks']): ?>
                                                    <div class="mb-3">
                                                        <h6>HR Decision Remarks</h6>
                                                        <div class="card bg-light">
                                                            <div class="card-body">
                                                                <?php echo nl2br(htmlspecialchars($row['hr_remarks'])); ?>
                                                            </div>
                                                        </div>
                                                    </div>
                                                    <?php endif; ?>
                                                </div>
                                                <div class="modal-footer">
                                                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                                                    <?php if ($row['status'] == 'pending'): ?>
                                                    <button type="button" class="btn btn-success" 
                                                            data-bs-toggle="modal" data-bs-target="#approveModal<?php echo $row['id']; ?>"
                                                            data-bs-dismiss="modal">
                                                        Approve Leave
                                                    </button>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <!-- Approve Modal -->
                                    <?php if ($row['status'] == 'pending'): ?>
                                    <div class="modal fade" id="approveModal<?php echo $row['id']; ?>" tabindex="-1">
                                        <div class="modal-dialog">
                                            <div class="modal-content">
                                                <form method="POST" action="">
                                                    <?php echo csrfField(); ?>
                                                    <input type="hidden" name="action" value="approve">
                                                    <input type="hidden" name="id" value="<?php echo $row['id']; ?>">
                                                    <div class="modal-header">
                                                        <h5 class="modal-title">Approve Leave Request</h5>
                                                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                                    </div>
                                                    <div class="modal-body">
                                                        <?php
                                                            $pl_rec_status = $row['pl_recommendation'] ?? 'none';
                                                            if ($pl_rec_status === 'recommended'):
                                                        ?>
                                                        <div class="alert alert-success">
                                                            <i class="bi bi-check-circle-fill me-1"></i>
                                                            <strong>PL Recommended</strong> - The Project Leader has recommended this leave. You are about to make the final approval.
                                                        </div>
                                                        <?php elseif ($pl_rec_status === 'not_recommended'): ?>
                                                        <div class="alert alert-warning">
                                                            <i class="bi bi-exclamation-triangle-fill me-1"></i>
                                                            <strong>PL Did NOT Recommend</strong> - The Project Leader has not recommended this leave, but you can still approve it if appropriate.
                                                        </div>
                                                        <?php else: ?>
                                                        <div class="alert alert-info">
                                                            <i class="bi bi-info-circle me-1"></i>
                                                            <strong>No PL Review Required</strong> - This employee has no assigned Project Leader. You can approve directly.
                                                        </div>
                                                        <?php endif; ?>

                                                        <?php if ($row['pl_remarks']): ?>
                                                        <div class="mb-3">
                                                            <label class="form-label text-muted">PL Remarks:</label>
                                                            <div class="card bg-light">
                                                                <div class="card-body py-2 small">
                                                                    <?php echo nl2br(htmlspecialchars($row['pl_remarks'])); ?>
                                                                </div>
                                                            </div>
                                                        </div>
                                                        <?php endif; ?>

                                                        <div class="alert alert-success">
                                                            <i class="bi bi-check-circle"></i>
                                                            You are about to approve this leave request.
                                                        </div>

                                                        <div class="mb-3">
                                                            <label class="form-label">Employee: <?php echo htmlspecialchars($row['full_name']); ?></label>
                                                        </div>

                                                        <div class="mb-3">
                                                            <label class="form-label">Leave Period:</label>
                                                            <p class="form-control-static">
                                                                <?php echo formatDate($row['start_date']); ?> to <?php echo formatDate($row['end_date']); ?>
                                                                (<?php echo $row['duration_days']; ?> days)
                                                            </p>
                                                        </div>

                                                        <div class="mb-3">
                                                            <label class="form-label">HR Remarks (Optional)</label>
                                                            <textarea class="form-control" name="remarks" rows="3"
                                                                      placeholder="Add any remarks or conditions..."></textarea>
                                                        </div>

                                                        <div class="form-check mb-3">
                                                            <input class="form-check-input" type="checkbox" id="sendEmail<?php echo $row['id']; ?>" checked>
                                                            <label class="form-check-label" for="sendEmail<?php echo $row['id']; ?>">
                                                                Send approval notification email to employee
                                                            </label>
                                                        </div>
                                                    </div>
                                                    <div class="modal-footer">
                                                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                                        <button type="submit" class="btn btn-success">Approve Leave</button>
                                                    </div>
                                                </form>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Reject Modal -->
                                    <div class="modal fade" id="rejectModal<?php echo $row['id']; ?>" tabindex="-1">
                                        <div class="modal-dialog">
                                            <div class="modal-content">
                                                <form method="POST" action="">
                                                    <?php echo csrfField(); ?>
                                                    <input type="hidden" name="action" value="reject">
                                                    <input type="hidden" name="id" value="<?php echo $row['id']; ?>">
                                                    <div class="modal-header">
                                                        <h5 class="modal-title">Reject Leave Request</h5>
                                                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                                    </div>
                                                    <div class="modal-body">
                                                        <?php
                                                            $pl_rec_status_rej = $row['pl_recommendation'] ?? 'none';
                                                            if ($pl_rec_status_rej === 'not_recommended'):
                                                        ?>
                                                        <div class="alert alert-warning">
                                                            <i class="bi bi-exclamation-triangle-fill me-1"></i>
                                                            <strong>PL Did NOT Recommend</strong> - The Project Leader has already flagged this leave. Please provide your rejection reason.
                                                        </div>
                                                        <?php elseif ($pl_rec_status_rej === 'recommended'): ?>
                                                        <div class="alert alert-info">
                                                            <i class="bi bi-info-circle me-1"></i>
                                                            <strong>Note:</strong> PL recommended this leave, but you are overriding and rejecting it.
                                                        </div>
                                                        <?php endif; ?>

                                                        <div class="alert alert-warning">
                                                            <i class="bi bi-exclamation-triangle"></i>
                                                            Please provide a reason for rejecting this leave request.
                                                        </div>
                                                        
                                                        <div class="mb-3">
                                                            <label class="form-label">Employee: <?php echo htmlspecialchars($row['full_name']); ?></label>
                                                        </div>
                                                        
                                                        <div class="mb-3">
                                                            <label class="form-label">Rejection Reason *</label>
                                                            <textarea class="form-control" name="remarks" rows="3" data-required="true" 
                                                                      placeholder="Explain why this leave is being rejected..."></textarea>
                                                        </div>
                                                        
                                                        <div class="form-check mb-3">
                                                            <input class="form-check-input" type="checkbox" id="sendRejectEmail<?php echo $row['id']; ?>" checked>
                                                            <label class="form-check-label" for="sendRejectEmail<?php echo $row['id']; ?>">
                                                                Send rejection notification email to employee
                                                            </label>
                                                        </div>
                                                    </div>
                                                    <div class="modal-footer">
                                                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                                        <button type="submit" class="btn btn-danger">Reject Leave</button>
                                                    </div>
                                                </form>
                                            </div>
                                        </div>
                                    </div>
                                    <?php endif; ?>

                                    <!-- Recommend Modal -->
                                    <?php if ($row['status'] == 'pending' && Auth::hasRole('project_leader')): ?>
                                    <div class="modal fade" id="recommendModal<?php echo $row['id']; ?>" tabindex="-1">
                                        <div class="modal-dialog">
                                            <div class="modal-content">
                                                <form method="POST" action="">
                                                    <?php echo csrfField(); ?>
                                                    <input type="hidden" name="action" value="recommend">
                                                    <input type="hidden" name="id" value="<?php echo $row['id']; ?>">
                                                    <div class="modal-header">
                                                        <h5 class="modal-title"><i class="bi bi-hand-thumbs-up me-1"></i>Recommend Leave Request</h5>
                                                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                                    </div>
                                                    <div class="modal-body">
                                                        <div class="alert alert-info">
                                                            <i class="bi bi-info-circle"></i>
                                                            <strong>Sequential Workflow:</strong> By recommending this leave, it will be forwarded to HR for their final approval/rejection decision.
                                                        </div>
                                                        <div class="mb-3">
                                                            <label class="form-label">Employee: <?php echo htmlspecialchars($row['full_name']); ?></label>
                                                        </div>
                                                        <div class="mb-3">
                                                            <label class="form-label">Recommendation Remarks (Optional)</label>
                                                            <textarea class="form-control" name="remarks" rows="3" placeholder="Add any comments for HR..."></textarea>
                                                        </div>
                                                    </div>
                                                    <div class="modal-footer">
                                                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                                        <button type="submit" class="btn btn-primary">
                                                            <i class="bi bi-send me-1"></i>Recommend & Forward to HR
                                                        </button>
                                                    </div>
                                                </form>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Not Recommend Modal -->
                                    <div class="modal fade" id="notRecommendModal<?php echo $row['id']; ?>" tabindex="-1">
                                        <div class="modal-dialog">
                                            <div class="modal-content">
                                                <form method="POST" action="">
                                                    <?php echo csrfField(); ?>
                                                    <input type="hidden" name="action" value="not_recommend">
                                                    <input type="hidden" name="id" value="<?php echo $row['id']; ?>">
                                                    <div class="modal-header">
                                                        <h5 class="modal-title"><i class="bi bi-hand-thumbs-down me-1"></i>Review Leave (Not Recommend)</h5>
                                                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                                    </div>
                                                    <div class="modal-body">
                                                        <div class="alert alert-warning">
                                                            <i class="bi bi-exclamation-triangle"></i>
                                                            <strong>Sequential Workflow:</strong> This doesn't reject the leave. It informs HR that you don't recommend it, but HR will still make the final decision.
                                                        </div>
                                                        <div class="mb-3">
                                                            <label class="form-label">Employee: <?php echo htmlspecialchars($row['full_name']); ?></label>
                                                        </div>
                                                        <div class="mb-3">
                                                            <label class="form-label">Reason for NOT recommending *</label>
                                                            <textarea class="form-control" name="remarks" rows="3" data-required="true" placeholder="Explain why you don't recommend this..."></textarea>
                                                        </div>
                                                    </div>
                                                    <div class="modal-footer">
                                                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                                        <button type="submit" class="btn btn-warning">
                                                            <i class="bi bi-send me-1"></i>Submit Review to HR
                                                        </button>
                                                    </div>
                                                </form>
                                            </div>
                                        </div>
                                    </div>
                                    <?php endif; ?>

                                    <!-- Delete Confirmation Modal (Admin Only) -->
                                    <?php if (Auth::hasRole('admin')): ?>
                                    <div class="modal fade" id="deleteModal<?php echo $row['id']; ?>" tabindex="-1">
                                        <div class="modal-dialog">
                                            <div class="modal-content">
                                                <form method="POST" action="" onsubmit="return confirm('Are you absolutely sure you want to PERMANENTLY DELETE this leave application? This cannot be undone.');">
                                                    <?php echo csrfField(); ?>
                                                    <input type="hidden" name="action" value="delete">
                                                    <input type="hidden" name="id" value="<?php echo $row['id']; ?>">
                                                    <div class="modal-header bg-danger text-white">
                                                        <h5 class="modal-title"><i class="bi bi-exclamation-triangle me-2"></i>Delete Leave Application</h5>
                                                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                                                    </div>
                                                    <div class="modal-body">
                                                        <div class="alert alert-danger">
                                                            <i class="bi bi-exclamation-triangle"></i>
                                                            <strong>Warning:</strong> This action cannot be undone!
                                                        </div>
                                                        <p>You are about to permanently delete:</p>
                                                        <div class="card bg-light">
                                                            <div class="card-body py-2">
                                                                <strong>Employee:</strong> <?php echo htmlspecialchars($row['full_name']); ?><br>
                                                                <strong>Leave Type:</strong> <?php echo ucfirst($row['leave_type']); ?><br>
                                                                <strong>Period:</strong> <?php echo formatDate($row['start_date']); ?> to <?php echo formatDate($row['end_date']); ?><br>
                                                                <strong>Status:</strong> <?php echo getStatusBadge($row['status']); ?>
                                                            </div>
                                                        </div>
                                                        <p class="text-muted small mt-3">This is useful for cleaning up test data or erroneous applications.</p>
                                                    </div>
                                                    <div class="modal-footer">
                                                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                                        <button type="submit" class="btn btn-danger">
                                                            <i class="bi bi-trash me-1"></i> Delete Permanently
                                                        </button>
                                                    </div>
                                                </form>
                                            </div>
                                        </div>
                                    </div>
                                    <?php endif; ?>

                                    <?php endwhile; ?>
                                <?php else: ?>
                                <tr>
                                    <td colspan="11" class="text-center py-4">
                                        <i class="bi bi-calendar-check h1 text-muted d-block mb-3"></i>
                                        <p class="text-muted">No leave applications found.</p>
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
</div>

<!-- Bulk Delete Modal (Admin Only) -->
<?php if (Auth::hasRole('admin')): ?>
<div class="modal fade" id="bulkDeleteModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" action="" onsubmit="return confirm('Are you sure you want to bulk delete leave applications? This cannot be undone!');">
                <?php echo csrfField(); ?>
                <input type="hidden" name="action" value="bulk_delete">
                <input type="hidden" name="id" value="1">
                <div class="modal-header bg-danger text-white">
                    <h5 class="modal-title"><i class="bi bi-trash me-2"></i>Leave Cleanup Tool</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="alert alert-warning">
                        <i class="bi bi-exclamation-triangle"></i>
                        <strong>Warning:</strong> This will permanently delete multiple leave applications!
                    </div>
                    <p class="mb-3">Select what to delete:</p>
                    <div class="form-check mb-2">
                        <input class="form-check-input" type="radio" name="delete_criteria" id="deleteCancelled" value="cancelled" checked>
                        <label class="form-check-label" for="deleteCancelled">
                            <span class="badge bg-secondary">Cancelled</span> - All cancelled leave requests
                        </label>
                    </div>
                    <div class="form-check mb-2">
                        <input class="form-check-input" type="radio" name="delete_criteria" id="deleteRejected" value="rejected">
                        <label class="form-check-label" for="deleteRejected">
                            <span class="badge bg-danger">Rejected</span> - All rejected leave requests
                        </label>
                    </div>
                    <div class="form-check mb-2">
                        <input class="form-check-input" type="radio" name="delete_criteria" id="deleteOld" value="old_approved">
                        <label class="form-check-label" for="deleteOld">
                            <span class="badge bg-success">Old Approved</span> - Approved leaves that ended 90+ days ago
                        </label>
                    </div>
                    <div class="form-check mb-3">
                        <input class="form-check-input" type="radio" name="delete_criteria" id="deleteTest" value="all_test_data">
                        <label class="form-check-label" for="deleteTest">
                            <span class="badge bg-warning text-dark">Test Data</span> - All leaves with "Test" in reason
                        </label>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-danger">
                        <i class="bi bi-trash me-1"></i> Delete Selected
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
<?php endif; ?>

<script>
    // Auto-refresh page every 60 seconds for pending leaves
    <?php if ($status_filter == 'pending'): ?>
    setTimeout(() => {
        location.reload();
    }, 60000);
    <?php endif; ?>
</script>

<?php require_once '../../includes/footer.php'; ?>
