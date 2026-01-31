<?php
require_once 'includes/header.php';

$current_user = Auth::getCurrentUser();
$user_id = $_SESSION['user_id'];
$role = $_SESSION['role'];

// Get dashboard stats based on role
 $conn = getDBConnection();
 ?>



<div class="container-fluid">
    
    <!-- Welcome Section -->
    <div class="row mb-4">
        <div class="col">
            <div class="card border-0 shadow-lg hero-section">
                <div class="card-body p-4">
                    <div class="row align-items-center">
                        <div class="col-md-8">
                            <h3 class="card-title text-dark mb-2 fw-bold">Welcome back, <?php echo htmlspecialchars($current_user['full_name']); ?>!</h3>
                            <p class="card-text text-dark-50 mb-0" style="font-size: 1.1rem;">
                                <?php
                                $greetings = [
                                    "Hope you're having a productive day!",
                                    "Ready to tackle today's tasks?",
                                    "Great to see you again!",
                                    "Your dedication drives our success!"
                                ];
                                echo $greetings[array_rand($greetings)];
                                ?>
                            </p>
                            <div class="mt-3">
                                <span class="badge bg-white text-primary px-3 py-2">
                                    <i class="bi bi-calendar3 me-2"></i><?php echo date('l, F j, Y'); ?>
                                </span>
                            </div>
                        </div>
                        <div class="col-md-4 text-end">
                            <div class="d-inline-block p-3 bg-white bg-opacity-20 rounded-circle">
                                <i class="bi bi-person-circle text-white" style="font-size: 5rem;"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Stats Overview -->
    <div class="row mb-4">
        <?php if ($role == 'employee' || $role == 'project_leader'): ?>
        <!-- Employee Stats -->
        <div class="col-md-3 mb-3">
            <div class="stat-card">
                <div class="stat-icon">
                    <i class="bi bi-calendar-check"></i>
                </div>
                <h3 class="mb-2">
                    <?php 
                    $sql = "SELECT COUNT(*) as count FROM leaves WHERE user_id = ? AND status = 'approved' AND YEAR(start_date) = YEAR(CURDATE())";
                    $stmt = $conn->prepare($sql);
                    $stmt->bind_param("i", $user_id);
                    $stmt->execute();
                    echo $stmt->get_result()->fetch_assoc()['count'];
                    ?>
                </h3>
                <p class="text-muted mb-0">Leaves Approved This Year</p>
            </div>
        </div>
        
        <div class="col-md-3 mb-3">
            <div class="stat-card warning">
                <div class="stat-icon">
                    <i class="bi bi-list-task"></i>
                </div>
                <h3 class="mb-2">
                    <?php 
                    $sql = "SELECT COUNT(*) as count FROM tasks WHERE assigned_to = ? AND status = 'pending'";
                    $stmt = $conn->prepare($sql);
                    $stmt->bind_param("i", $user_id);
                    $stmt->execute();
                    echo $stmt->get_result()->fetch_assoc()['count'];
                    ?>
                </h3>
                <p class="text-muted mb-0">Pending Tasks</p>
            </div>
        </div>
        
        <div class="col-md-3 mb-3">
            <div class="stat-card success">
                <div class="stat-icon">
                    <i class="bi bi-check-circle"></i>
                </div>
                <h3 class="mb-2">
                    <?php 
                    $sql = "SELECT COUNT(*) as count FROM tasks WHERE assigned_to = ? AND status = 'completed' AND MONTH(completion_date) = MONTH(CURDATE())";
                    $stmt = $conn->prepare($sql);
                    $stmt->bind_param("i", $user_id);
                    $stmt->execute();
                    echo $stmt->get_result()->fetch_assoc()['count'];
                    ?>
                </h3>
                <p class="text-muted mb-0">Tasks Completed This Month</p>
            </div>
        </div>
        
        <div class="col-md-3 mb-3">
            <div class="stat-card danger">
                <div class="stat-icon">
                    <i class="bi bi-clock"></i>
                </div>
                <h3 class="mb-2">
                    <?php 
                    $sql = "SELECT COUNT(*) as count FROM tasks WHERE assigned_to = ? AND status = 'in_progress'";
                    $stmt = $conn->prepare($sql);
                    $stmt->bind_param("i", $user_id);
                    $stmt->execute();
                    echo $stmt->get_result()->fetch_assoc()['count'];
                    ?>
                </h3>
                <p class="text-muted mb-0">Tasks In Progress</p>
            </div>
        </div>
        <?php endif; ?>
        
        <?php if ($role == 'hr' || $role == 'admin'): ?>
        <!-- HR Stats -->
        <div class="col-md-3 mb-3">
            <div class="stat-card">
                <div class="stat-icon">
                    <i class="bi bi-people"></i>
                </div>
                <h3 class="mb-2">
                    <?php 
                    $sql = "SELECT COUNT(*) as count FROM users WHERE status = 'active'";
                    $result = $conn->query($sql);
                    echo $result->fetch_assoc()['count'];
                    ?>
                </h3>
                <p class="text-muted mb-0">Total Employees</p>
            </div>
        </div>
        
        <div class="col-md-3 mb-3">
            <div class="stat-card warning">
                <div class="stat-icon">
                    <i class="bi bi-calendar-x"></i>
                </div>
                <h3 class="mb-2">
                    <?php 
                    $sql = "SELECT COUNT(*) as count FROM leaves WHERE status = 'pending'";
                    $result = $conn->query($sql);
                    echo $result->fetch_assoc()['count'];
                    ?>
                </h3>
                <p class="text-muted mb-0">Pending Leave Requests</p>
            </div>
        </div>
        
        <div class="col-md-3 mb-3">
            <div class="stat-card success">
                <div class="stat-icon">
                    <i class="bi bi-cash-stack"></i>
                </div>
                <h3 class="mb-2">
                    <?php 
                    $sql = "SELECT COUNT(DISTINCT user_id) as count FROM salaries WHERE month = DATE_FORMAT(CURDATE(), '%Y-%m') AND status = 'paid'";
                    $result = $conn->query($sql);
                    echo $result->fetch_assoc()['count'];
                    ?>
                </h3>
                <p class="text-muted mb-0">Paid This Month</p>
            </div>
        </div>
        
        <div class="col-md-3 mb-3">
            <div class="stat-card danger">
                <div class="stat-icon">
                    <i class="bi bi-chat-left-text"></i>
                </div>
                <h3 class="mb-2">
                    <?php 
                    $sql = "SELECT COUNT(*) as count FROM inquiries WHERE status = 'new'";
                    $result = $conn->query($sql);
                    echo $result->fetch_assoc()['count'];
                    ?>
                </h3>
                <p class="text-muted mb-0">New Inquiries</p>
            </div>
        </div>
        <?php endif; ?>
    </div>
    
    <!-- Quick Actions -->
    <div class="row mb-4">
        <div class="col">
            <div class="card border-0 shadow">
                <div class="card-header bg-white border-bottom section-header">
                    <h5 class="mb-0 fw-bold"><i class="bi bi-lightning-charge-fill text-warning me-2"></i>Quick Actions</h5>
                </div>
                <div class="card-body">
                    <div class="row g-3">
                        <?php if ($role == 'employee'): ?>
                        <div class="col-md-3 col-sm-6">
                            <a href="<?php echo $base_url; ?>/modules/leave/apply.php" class="btn btn-outline-primary w-100 py-4 rounded-3 border-2 fw-semibold quick-action-btn" style="transition: all 0.3s;">
                                <i class="bi bi-calendar-plus h3 d-block mb-2"></i>
                                Apply for Leave
                            </a>
                        </div>
                        <?php endif; ?>
                        
                        <?php if ($role == 'project_leader' || $role == 'hr' || $role == 'admin'): ?>
                        <div class="col-md-3 col-sm-6">
                            <a href="<?php echo $base_url; ?>/modules/tasks/assign.php" class="btn btn-outline-success w-100 py-4 rounded-3 border-2 fw-semibold quick-action-btn" style="transition: all 0.3s;">
                                <i class="bi bi-person-plus h3 d-block mb-2"></i>
                                Assign Task
                            </a>
                        </div>
                        <?php endif; ?>
                        
                        <div class="col-md-3 col-sm-6">
                            <a href="<?php echo $base_url; ?>/modules/tasks/my_tasks.php" class="btn btn-outline-info w-100 py-4 rounded-3 border-2 fw-semibold quick-action-btn" style="transition: all 0.3s;">
                                <i class="bi bi-list-check h3 d-block mb-2"></i>
                                View My Tasks
                            </a>
                        </div>
                        
                        <div class="col-md-3 col-sm-6">
                            <a href="<?php echo $base_url; ?>/modules/payroll/my_salary.php" class="btn btn-outline-warning w-100 py-4 rounded-3 border-2 fw-semibold quick-action-btn" style="transition: all 0.3s;">
                                <i class="bi bi-cash-coin h3 d-block mb-2"></i>
                                View Salary
                            </a>
                        </div>
                        
                        <div class="col-md-3 col-sm-6">
                            <a href="<?php echo $base_url; ?>/modules/projects/index.php" class="btn btn-outline-dark w-100 py-4 rounded-3 border-2 fw-semibold quick-action-btn" style="transition: all 0.3s;">
                                <i class="bi bi-folder2-open h3 d-block mb-2"></i>
                                Projects
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <style>
        .quick-action-btn:hover {
            transform: translateY(-5px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.2);
        }
    </style>
    
    <!-- Recent Activities -->
    <div class="row">
        <div class="col-md-6 mb-4">
            <div class="card border-0 shadow">
                <div class="card-header bg-white d-flex justify-content-between align-items-center border-bottom">
                    <h5 class="mb-0 fw-bold"><i class="bi bi-list-task text-primary me-2"></i>Recent Tasks</h5>
                    <a href="<?php echo $base_url; ?>/modules/tasks/my_tasks.php" class="btn btn-sm btn-primary rounded-pill">View All</a>
                </div>
                <div class="card-body p-0">
                    <div class="list-group list-group-flush">
                        <?php
                        $sql = "SELECT t.*, u.full_name as assigned_by_name 
                                FROM tasks t 
                                LEFT JOIN users u ON t.assigned_by = u.id 
                                WHERE t.assigned_to = ? 
                                ORDER BY t.created_at DESC 
                                LIMIT 5";
                        $stmt = $conn->prepare($sql);
                        $stmt->bind_param("i", $user_id);
                        $stmt->execute();
                        $result = $stmt->get_result();
                        
                        if ($result->num_rows > 0):
                            while ($row = $result->fetch_assoc()):
                        ?>
                        <div class="list-group-item">
                            <div class="d-flex w-100 justify-content-between">
                                <h6 class="mb-1"><?php echo htmlspecialchars($row['title']); ?></h6>
                                <small><?php echo getStatusBadge($row['status']); ?></small>
                            </div>
                            <p class="mb-1 small text-muted">Assigned by: <?php echo htmlspecialchars($row['assigned_by_name']); ?></p>
                            <small class="text-muted">
                                Due: <?php echo formatDate($row['due_date']); ?> | 
                                Progress: <?php echo $row['progress']; ?>%
                            </small>
                        </div>
                        <?php
                            endwhile;
                        else:
                        ?>
                        <div class="list-group-item text-center text-muted py-4">
                            <i class="bi bi-inbox h3"></i>
                            <p class="mb-0">No tasks assigned yet</p>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-md-6 mb-4">
            <div class="card border-0 shadow">
                <div class="card-header bg-white d-flex justify-content-between align-items-center border-bottom">
                    <h5 class="mb-0 fw-bold"><i class="bi bi-calendar-event text-success me-2"></i>Recent Leaves</h5>
                    <a href="<?php echo $base_url; ?>/modules/leave/my_leaves.php" class="btn btn-sm btn-primary rounded-pill">View All</a>
                </div>
                <div class="card-body p-0">
                    <div class="list-group list-group-flush">
                        <?php
                        $sql = "SELECT * FROM leaves WHERE user_id = ? ORDER BY created_at DESC LIMIT 5";
                        $stmt = $conn->prepare($sql);
                        $stmt->bind_param("i", $user_id);
                        $stmt->execute();
                        $result = $stmt->get_result();
                        
                        if ($result->num_rows > 0):
                            while ($row = $result->fetch_assoc()):
                        ?>
                        <div class="list-group-item">
                            <div class="d-flex w-100 justify-content-between">
                                <h6 class="mb-1"><?php echo ucfirst($row['leave_type']); ?> Leave</h6>
                                <small><?php echo getStatusBadge($row['status']); ?></small>
                            </div>
                            <p class="mb-1 small"><?php echo htmlspecialchars($row['reason']); ?></p>
                            <small class="text-muted">
                                <?php echo formatDate($row['start_date']); ?> - <?php echo formatDate($row['end_date']); ?> | 
                                <?php echo $row['duration_days']; ?> days
                            </small>
                        </div>
                        <?php
                            endwhile;
                        else:
                        ?>
                        <div class="list-group-item text-center text-muted py-4">
                            <i class="bi bi-calendar-x h3"></i>
                            <p class="mb-0">No leave applications yet</p>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- For HR/Admin: Pending Approvals -->
    <?php if ($role == 'hr' || $role == 'admin'): ?>
    <div class="row">
        <div class="col">
            <div class="card border-0 shadow">
                <div class="card-header bg-white border-bottom section-header">
                    <h5 class="mb-0 fw-bold"><i class="bi bi-clock-history text-warning me-2"></i>Pending Approvals</h5>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Employee</th>
                                    <th>Type</th>
                                    <th>Period</th>
                                    <th>Reason</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                $sql = "SELECT l.*, u.full_name, u.employee_id 
                                        FROM leaves l 
                                        JOIN users u ON l.user_id = u.id 
                                        WHERE l.status = 'pending' 
                                        ORDER BY l.created_at DESC 
                                        LIMIT 10";
                                $result = $conn->query($sql);
                                
                                if ($result->num_rows > 0):
                                    while ($row = $result->fetch_assoc()):
                                ?>
                                <tr>
                                    <td>
                                        <strong><?php echo htmlspecialchars($row['full_name']); ?></strong><br>
                                        <small class="text-muted"><?php echo htmlspecialchars($row['employee_id']); ?></small>
                                    </td>
                                    <td><?php echo htmlspecialchars(ucfirst($row['leave_type'])); ?></td>
                                    <td>
                                        <?php echo formatDate($row['start_date']); ?><br>
                                        <small>to <?php echo formatDate($row['end_date']); ?></small>
                                    </td>
                                    <td><?php echo htmlspecialchars(substr($row['reason'], 0, 50)); ?>...</td>
                                    <td><?php echo getStatusBadge($row['status']); ?></td>
                                    <td>
                                                     <a href="<?php echo $base_url; ?>/modules/leave/manage.php?action=view&id=<?php echo $row['id']; ?>" 
                                           class="btn btn-sm btn-primary btn-action" title="View">
                                            <i class="bi bi-eye"></i>
                                        </a>
                                                     <a href="<?php echo $base_url; ?>/modules/leave/manage.php?action=approve&id=<?php echo $row['id']; ?>" 
                                           class="btn btn-sm btn-success btn-action" title="Approve">
                                            <i class="bi bi-check-lg"></i>
                                        </a>
                                                     <a href="<?php echo $base_url; ?>/modules/leave/manage.php?action=reject&id=<?php echo $row['id']; ?>" 
                                           class="btn btn-sm btn-danger btn-action" title="Reject">
                                            <i class="bi bi-x-lg"></i>
                                        </a>
                                    </td>
                                </tr>
                                <?php
                                    endwhile;
                                else:
                                ?>
                                <tr>
                                    <td colspan="6" class="text-center text-muted py-4">
                                        <i class="bi bi-check-circle h3"></i>
                                        <p class="mb-0">No pending approvals</p>
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
</div>

<?php require_once 'includes/footer.php'; ?>