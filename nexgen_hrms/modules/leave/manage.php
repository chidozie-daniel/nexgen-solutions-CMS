<?php
require_once '../../includes/auth.php';
require_once '../../includes/functions.php';

// Check login status first
Auth::requireLogin();

// Only HR and Admin can manage leaves
if (!Auth::hasRole('hr') && !Auth::hasRole('admin')) {
    setFlash('danger', 'You do not have permission to manage leaves.');
    header('Location: ' . Auth::getBasePath() . '/dashboard.php');
    exit();
}

$conn = getDBConnection();
$user_id = $_SESSION['user_id'];

// Handle leave actions
if (isset($_GET['action']) && isset($_GET['id'])) {
    $action = $_GET['action'];
    $leave_id = $_GET['id'];
    $remarks = $_POST['remarks'] ?? '';
    
    $sql = "UPDATE leaves SET status = ?, approved_by = ?, hr_remarks = ? WHERE id = ?";
    $stmt = $conn->prepare($sql);
    
    switch ($action) {
        case 'approve':
            $status = 'approved';
            $stmt->bind_param("sisi", $status, $user_id, $remarks, $leave_id);
            $message = 'Leave approved successfully.';
            break;
            
        case 'reject':
            $status = 'rejected';
            $stmt->bind_param("sisi", $status, $user_id, $remarks, $leave_id);
            $message = 'Leave rejected.';
            break;
            
        case 'cancel':
            $status = 'cancelled';
            $stmt->bind_param("sisi", $status, $user_id, $remarks, $leave_id);
            $message = 'Leave cancelled.';
            break;
    }
    
    if ($stmt->execute()) {
        setFlash('success', $message);
    }
    header('Location: ' . Auth::getBasePath() . '/modules/leave/manage.php');
    exit();
}

// Get all leave applications with filters
$status_filter = $_GET['status'] ?? 'pending';
$department_filter = $_GET['department'] ?? 'all';
$type_filter = $_GET['type'] ?? 'all';

$sql = "SELECT l.*, u.full_name, u.employee_id, u.department, u.position 
        FROM leaves l 
        JOIN users u ON l.user_id = u.id 
        WHERE 1=1";
        
$params = [];
$types = "";

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
                <div>
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
                                    <th>Status</th>
                                    <th>Applied On</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if ($result->num_rows > 0): ?>
                                    <?php while ($row = $result->fetch_assoc()): ?>
                                    <tr class="<?php echo $row['status'] == 'pending' ? 'table-warning' : ''; ?>">
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
                                        <td><?php echo getStatusBadge($row['status']); ?></td>
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
                                                
                                                <?php if ($row['status'] == 'pending'): ?>
                                                <button type="button" class="btn btn-sm btn-outline-success" 
                                                        data-bs-toggle="modal" data-bs-target="#approveModal<?php echo $row['id']; ?>">
                                                    <i class="bi bi-check-lg"></i>
                                                </button>
                                                
                                                <button type="button" class="btn btn-sm btn-outline-danger" 
                                                        data-bs-toggle="modal" data-bs-target="#rejectModal<?php echo $row['id']; ?>">
                                                    <i class="bi bi-x-lg"></i>
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
                                                    
                                                    <?php if ($row['hr_remarks']): ?>
                                                    <div class="mb-3">
                                                        <h6>HR Remarks</h6>
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
                                                <form method="POST" action="<?php echo $base_url; ?>/modules/leave/manage.php?action=approve&id=<?php echo $row['id']; ?>">
                                                    <div class="modal-header">
                                                        <h5 class="modal-title">Approve Leave Request</h5>
                                                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                                    </div>
                                                    <div class="modal-body">
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
                                                <form method="POST" action="<?php echo $base_url; ?>/modules/leave/manage.php?action=reject&id=<?php echo $row['id']; ?>">
                                                    <div class="modal-header">
                                                        <h5 class="modal-title">Reject Leave Request</h5>
                                                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                                    </div>
                                                    <div class="modal-body">
                                                        <div class="alert alert-warning">
                                                            <i class="bi bi-exclamation-triangle"></i> 
                                                            Please provide a reason for rejecting this leave request.
                                                        </div>
                                                        
                                                        <div class="mb-3">
                                                            <label class="form-label">Employee: <?php echo htmlspecialchars($row['full_name']); ?></label>
                                                        </div>
                                                        
                                                        <div class="mb-3">
                                                            <label class="form-label">Rejection Reason *</label>
                                                            <textarea class="form-control" name="remarks" rows="3" required 
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
                                    
                                    <?php endwhile; ?>
                                <?php else: ?>
                                <tr>
                                    <td colspan="10" class="text-center py-4">
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

<script>
    // Auto-refresh page every 60 seconds for pending leaves
    <?php if ($status_filter == 'pending'): ?>
    setTimeout(() => {
        location.reload();
    }, 60000);
    <?php endif; ?>
</script>

<?php require_once '../../includes/footer.php'; ?>