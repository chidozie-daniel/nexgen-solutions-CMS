<?php
require_once 'includes/header.php';

$current_user = Auth::getCurrentUser();
$user_id = $_SESSION['user_id'];
$role = $_SESSION['role'];

// Get dashboard stats based on role
$conn = getDBConnection();

// Fetch active announcements from database
$announcements_sql = "SELECT a.*, u.full_name as creator_name 
                      FROM announcements a 
                      LEFT JOIN users u ON a.created_by = u.id 
                      WHERE a.is_active = 1 
                      AND (a.expires_at IS NULL OR a.expires_at > NOW())
                      AND (a.target_audience = 'all' OR a.target_audience = ?)
                      ORDER BY 
                        CASE a.priority 
                            WHEN 'urgent' THEN 1 
                            WHEN 'high' THEN 2 
                            WHEN 'medium' THEN 3 
                            ELSE 4 
                        END,
                        a.created_at DESC 
                      LIMIT 1";
$ann_stmt = $conn->prepare($announcements_sql);
$ann_stmt->bind_param("s", $role);
$ann_stmt->execute();
$announcement = $ann_stmt->get_result()->fetch_assoc();
?>



<div class="container-fluid">
    
    <!-- Welcome Section -->
    <div class="row mb-4">
        <div class="col">
            <div class="card border-0 shadow-xl hero-section overflow-hidden rounded-4">
                <div class="card-body p-5">
                    <div class="row align-items-center">
                        <div class="col-md-8">
                            <span class="badge bg-primary bg-opacity-10 text-primary mb-3 px-3 py-2 border border-primary border-opacity-25">
                                <i class="bi bi-stars me-2"></i>NexGen HRMS Enterprise
                            </span>
                            <h1 class="display-5 mb-3 text-dark fw-800">Welcome back, <?php echo htmlspecialchars($current_user['full_name']); ?>!</h1>
                            <p class="lead text-secondary mb-4">
                                <?php
                                $greetings = [
                                    "Your leadership continues to inspire and drive our collective success.",
                                    "Ready to shape the future of NexGen Solutions today?",
                                    "Transformation begins with your vision. Let's make an impact.",
                                    "Optimizing the pulse of our organization, one task at a time."
                                ];
                                echo $greetings[array_rand($greetings)];
                                ?>
                            </p>
                            <div class="d-flex gap-3 welcome-meta">
                                <span class="glass px-4 py-2 rounded-3 text-dark fw-600 shadow-sm">
                                    <i class="bi bi-calendar3 me-2 text-primary"></i><?php echo date('l, F j, Y'); ?>
                                </span>
                                <span class="glass px-4 py-2 rounded-3 text-dark fw-600 shadow-sm">
                                    <i class="bi bi-clock me-2 text-primary"></i><span id="realtime-clock"><?php echo date('h:i A'); ?></span>
                                </span>
                            </div>
                        </div>
                        <div class="col-md-4 text-center d-none d-md-block">
                            <div class="position-relative">
                                <div class="position-absolute top-50 start-50 translate-middle w-100 h-100 bg-primary opacity-5 rounded-circle blur-3xl"></div>
                                <i class="bi bi-person-workspace text-primary opacity-10" style="font-size: 12rem;"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Stats Overview -->
    <div class="row mb-5">
        <?php if ($role == 'employee' || $role == 'project_leader'): ?>
        <!-- Employee Stats -->
        <div class="col-xl-3 col-md-6 mb-4">
            <div class="stat-card h-100">
                <div class="stat-icon shadow-sm">
                    <i class="bi bi-calendar-check-fill"></i>
                </div>
                <h3 class="mb-1">
                    <?php 
                    $sql = "SELECT COUNT(*) as count FROM leaves WHERE user_id = ? AND status = 'approved' AND YEAR(start_date) = YEAR(CURDATE())";
                    $stmt = $conn->prepare($sql);
                    $stmt->bind_param("i", $user_id);
                    $stmt->execute();
                    echo $stmt->get_result()->fetch_assoc()['count'];
                    ?>
                </h3>
                <p class="text-secondary fw-500 mb-0">Leaves Approved</p>
                <div class="mt-3 small text-success fw-600"><i class="bi bi-graph-up me-1"></i> Annual quota tracking</div>
            </div>
        </div>
        
        <div class="col-xl-3 col-md-6 mb-4">
            <div class="stat-card warning h-100">
                <div class="stat-icon shadow-sm">
                    <i class="bi bi-hourglass-split"></i>
                </div>
                <h3 class="mb-1">
                    <?php 
                    $sql = "SELECT COUNT(*) as count FROM tasks WHERE assigned_to = ? AND status = 'pending'";
                    $stmt = $conn->prepare($sql);
                    $stmt->bind_param("i", $user_id);
                    $stmt->execute();
                    echo $stmt->get_result()->fetch_assoc()['count'];
                    ?>
                </h3>
                <p class="text-secondary fw-500 mb-0">Pending Tasks</p>
                <div class="mt-3 small text-warning fw-600"><i class="bi bi-exclamation-circle me-1"></i> Priority required</div>
            </div>
        </div>
        
        <div class="col-xl-3 col-md-6 mb-4">
            <div class="stat-card success h-100">
                <div class="stat-icon shadow-sm">
                    <i class="bi bi-check-all"></i>
                </div>
                <h3 class="mb-1">
                    <?php 
                    $sql = "SELECT COUNT(*) as count FROM tasks WHERE assigned_to = ? AND status = 'completed' AND MONTH(completion_date) = MONTH(CURDATE())";
                    $stmt = $conn->prepare($sql);
                    $stmt->bind_param("i", $user_id);
                    $stmt->execute();
                    echo $stmt->get_result()->fetch_assoc()['count'];
                    ?>
                </h3>
                <p class="text-secondary fw-500 mb-0">Monthly Completion</p>
                <div class="mt-3 small text-success fw-600"><i class="bi bi-award me-1"></i> Top performance</div>
            </div>
        </div>
        
        <div class="col-xl-3 col-md-6 mb-4">
            <div class="stat-card danger h-100">
                <div class="stat-icon shadow-sm">
                    <i class="bi bi-lightning-fill"></i>
                </div>
                <h3 class="mb-1">
                    <?php 
                    $sql = "SELECT COUNT(*) as count FROM tasks WHERE assigned_to = ? AND status = 'in_progress'";
                    $stmt = $conn->prepare($sql);
                    $stmt->bind_param("i", $user_id);
                    $stmt->execute();
                    echo $stmt->get_result()->fetch_assoc()['count'];
                    ?>
                </h3>
                <p class="text-secondary fw-500 mb-0">In Active Flow</p>
                <div class="mt-3 small text-danger fw-600"><i class="bi bi-activity me-1"></i> Current focus</div>
            </div>
        </div>
        <?php endif; ?>
        
        <?php if ($role == 'hr' || $role == 'admin'): ?>
        <!-- HR Stats -->
        <div class="col-xl-3 col-md-6 mb-4">
            <div class="stat-card h-100">
                <div class="stat-icon shadow-sm">
                    <i class="bi bi-people-fill"></i>
                </div>
                <h3 class="mb-1">
                    <?php 
                    $sql = "SELECT COUNT(*) as count FROM users WHERE status = 'active'";
                    $result = $conn->query($sql);
                    echo $result->fetch_assoc()['count'];
                    ?>
                </h3>
                <p class="text-secondary fw-500 mb-0">Total Talent Pool</p>
                <div class="mt-3 small text-primary fw-600"><i class="bi bi-arrow-up-right me-1"></i> Growing workforce</div>
            </div>
        </div>
        
        <div class="col-xl-3 col-md-6 mb-4">
            <div class="stat-card warning h-100">
                <div class="stat-icon shadow-sm">
                    <i class="bi bi-calendar2-range-fill"></i>
                </div>
                <h3 class="mb-1">
                    <?php 
                    $sql = "SELECT COUNT(*) as count FROM leaves WHERE status = 'pending'";
                    $result = $conn->query($sql);
                    echo $result->fetch_assoc()['count'];
                    ?>
                </h3>
                <p class="text-secondary fw-500 mb-0">Pending Approvals</p>
                <div class="mt-3 small text-warning fw-600"><i class="bi bi-clock-history me-1"></i> Attention needed</div>
            </div>
        </div>
        
        <div class="col-xl-3 col-md-6 mb-4">
            <div class="stat-card success h-100">
                <div class="stat-icon shadow-sm">
                    <i class="bi bi-wallet2"></i>
                </div>
                <h3 class="mb-1">
                    <?php 
                    $sql = "SELECT COUNT(DISTINCT user_id) as count FROM salaries WHERE month = DATE_FORMAT(CURDATE(), '%Y-%m') AND status = 'paid'";
                    $result = $conn->query($sql);
                    echo $result->fetch_assoc()['count'];
                    ?>
                </h3>
                <p class="text-secondary fw-500 mb-0">Payroll Settled</p>
                <div class="mt-3 small text-success fw-600"><i class="bi bi-check-circle-fill me-1"></i> This current month</div>
            </div>
        </div>
        
        <div class="col-xl-3 col-md-6 mb-4">
            <div class="stat-card danger h-100">
                <div class="stat-icon shadow-sm">
                    <i class="bi bi-megaphone-fill"></i>
                </div>
                <h3 class="mb-1">
                    <?php 
                    $sql = "SELECT COUNT(*) as count FROM inquiries WHERE status = 'new'";
                    $result = $conn->query($sql);
                    echo $result->fetch_assoc()['count'];
                    ?>
                </h3>
                <p class="text-secondary fw-500 mb-0">Unread Enquiries</p>
                <div class="mt-3 small text-danger fw-600"><i class="bi bi-fire me-1"></i> Immediate action</div>
            </div>
        </div>
        <?php endif; ?>
    </div>
    
    <div class="row">
        <!-- Main Actions & Recent -->
        <div class="col-lg-8">
            <!-- Quick Actions Grid -->
            <div class="card border-0 shadow-md mb-4 rounded-4">
                <div class="card-header bg-white border-0 pt-4 px-4">
                    <h5 class="mb-0 fw-bold d-flex align-items-center">
                        <span class="p-2 bg-warning bg-opacity-10 rounded-3 me-3">
                            <i class="bi bi-lightning-charge-fill text-warning"></i>
                        </span>
                        Strategic Shortcuts
                    </h5>
                </div>
                <div class="card-body p-4">
                    <div class="row g-4">
                        <?php if ($role == 'employee'): ?>
                        <div class="col-md-4">
                            <a href="<?php echo $base_url; ?>/modules/leave/apply.php" class="quick-action-btn-new p-4 rounded-4 bg-primary bg-opacity-5 text-decoration-none d-block">
                                <div class="icon-circle bg-primary text-white mb-3 shadow-sm">
                                    <i class="bi bi-calendar-plus-fill"></i>
                                </div>
                                <h6 class="fw-700 text-dark mb-1">Apply Leave</h6>
                                <p class="small text-secondary mb-0">Request time off easily</p>
                            </a>
                        </div>
                        <div class="col-md-4">
                            <a href="<?php echo $base_url; ?>/modules/projects/index.php" class="quick-action-btn-new p-4 rounded-4 bg-purple bg-opacity-5 text-decoration-none d-block">
                                <div class="icon-circle bg-purple text-white mb-3 shadow-sm" style="background-color: #6f42c1 !important;">
                                    <i class="bi bi-folder-fill"></i>
                                </div>
                                <h6 class="fw-700 text-dark mb-1">My Projects</h6>
                                <p class="small text-secondary mb-0">View assigned projects</p>
                            </a>
                        </div>
                        <?php endif; ?>
                        
                        <?php if ($role == 'project_leader' || $role == 'hr' || $role == 'admin'): ?>
                        <div class="col-md-4">
                            <a href="<?php echo $base_url; ?>/modules/tasks/assign.php" class="quick-action-btn-new p-4 rounded-4 bg-success bg-opacity-5 text-decoration-none d-block">
                                <div class="icon-circle bg-success text-white mb-3 shadow-sm">
                                    <i class="bi bi-plus-circle-fill"></i>
                                </div>
                                <h6 class="fw-700 text-dark mb-1">Assign Task</h6>
                                <p class="small text-secondary mb-0">Distribute team workload</p>
                            </a>
                        </div>
                        <?php endif; ?>
                        
                        <div class="col-md-4">
                            <a href="<?php echo $base_url; ?>/modules/tasks/my_tasks.php" class="quick-action-btn-new p-4 rounded-4 bg-info bg-opacity-5 text-decoration-none d-block">
                                <div class="icon-circle bg-info text-white mb-3 shadow-sm">
                                    <i class="bi bi-kanban-fill"></i>
                                </div>
                                <h6 class="fw-700 text-dark mb-1">My Dashboard</h6>
                                <p class="small text-secondary mb-0">Track your progression</p>
                            </a>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Recent Tasks Table -->
            <div class="card border-0 shadow-md rounded-4 mb-4">
                <div class="card-header bg-white border-0 pt-4 px-4 d-flex justify-content-between align-items-center milestone-header">
                    <h5 class="mb-0 fw-bold d-flex align-items-center">
                        <span class="p-2 bg-primary bg-opacity-10 rounded-3 me-3">
                            <i class="bi bi-stack text-primary"></i>
                        </span>
                        Project Milestones
                    </h5>
                    <a href="<?php echo $base_url; ?>/modules/tasks/my_tasks.php" class="btn btn-light btn-sm rounded-pill px-4 fw-600">Explore All</a>
                </div>
                <div class="card-body p-4">
                    <div class="table-responsive">
                        <table class="table align-middle">
                            <thead>
                                <tr>
                                    <th>Project Task</th>
                                    <th>Supervisor</th>
                                    <th class="text-center">Status</th>
                                    <th>Timeline</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                $sql = "SELECT t.*, u.full_name as assigned_by_name 
                                        FROM tasks t 
                                        LEFT JOIN users u ON t.assigned_by = u.id 
                                        WHERE t.assigned_to = ? 
                                        ORDER BY t.created_at DESC 
                                        LIMIT 4";
                                $stmt = $conn->prepare($sql);
                                $stmt->bind_param("i", $user_id);
                                $stmt->execute();
                                $result = $stmt->get_result();
                                
                                if ($result->num_rows > 0):
                                    while ($row = $result->fetch_assoc()):
                                ?>
                                <tr>
                                    <td>
                                        <div class="fw-700 text-dark"><?php echo htmlspecialchars($row['title']); ?></div>
                                        <div class="progress mt-2" style="height: 6px; width: 120px;">
                                            <div class="progress-bar bg-primary" style="width: <?php echo $row['progress']; ?>%"></div>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="d-flex align-items-center">
                                            <div class="avatar-xs bg-light rounded-circle text-primary me-2 fw-800 d-flex align-items-center justify-content-center" style="width: 24px; height: 24px; font-size: 0.7rem;">
                                                <?php echo substr($row['assigned_by_name'], 0, 1); ?>
                                            </div>
                                            <span class="small text-secondary fw-500"><?php echo htmlspecialchars($row['assigned_by_name']); ?></span>
                                        </div>
                                    </td>
                                    <td class="text-center">
                                        <?php echo getStatusBadge($row['status']); ?>
                                    </td>
                                    <td>
                                        <div class="small fw-600 text-dark"><?php echo formatDate($row['due_date'], 'M d, Y'); ?></div>
                                    </td>
                                </tr>
                                <?php
                                    endwhile;
                                else:
                                ?>
                                <tr>
                                    <td colspan="4" class="text-center py-5">
                                        <div class="opacity-25 mb-3"><i class="bi bi-inbox-fill display-4"></i></div>
                                        <p class="text-secondary fw-500">No active milestones found in your queue.</p>
                                    </td>
                                </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <!-- Sidebar Widgets -->
        <div class="col-lg-4">
            <!-- Team Presence -->
            <div class="card border-0 shadow-md rounded-4 mb-4">
                <div class="card-header bg-white border-0 pt-4 px-4">
                    <h5 class="mb-0 fw-bold">Executive Pulse</h5>
                </div>
                <div class="card-body p-4">
                    <div class="d-flex flex-column gap-3">
                        <?php
                        // Mock active users or team members
                        $team_sql = "SELECT full_name, role, status FROM users WHERE id != ? ORDER BY last_login DESC LIMIT 5";
                        $team_stmt = $conn->prepare($team_sql);
                        $team_stmt->bind_param("i", $user_id);
                        $team_stmt->execute();
                        $team_res = $team_stmt->get_result();
                        while($member = $team_res->fetch_assoc()):
                        ?>
                        <div class="d-flex align-items-center gap-3 p-2 rounded-3 hover-bg-light transition-all">
                            <div class="avatar-status-container">
                                <div class="user-avatar m-0 !w-40 !h-40">
                                    <?php echo substr($member['full_name'], 0, 1); ?>
                                </div>
                                <span class="status-indicator <?php echo $member['status'] == 'active' ? 'bg-success' : 'bg-secondary'; ?>"></span>
                            </div>
                            <div class="flex-grow-1">
                                <div class="fw-700 text-dark small"><?php echo htmlspecialchars($member['full_name']); ?></div>
                                <div class="text-secondary" style="font-size: 0.7rem;"><?php echo getRoleBadge($member['role']); ?></div>
                            </div>
                        </div>
                        <?php endwhile; ?>
                    </div>
                </div>
            </div>

            <!-- Announcements / Notifications -->
            <div class="card border-0 shadow-md rounded-4 bg-dark text-white overflow-hidden mb-4">
                <div class="card-body p-4 position-relative">
                    <div class="position-absolute top-0 end-0 p-4 opacity-10">
                        <i class="bi bi-megaphone display-1"></i>
                    </div>
                    <h6 class="text-primary fw-800 mb-3 text-uppercase letter-spacing-1">Announcements</h6>
                    <?php if ($announcement): ?>
                    <h5 class="mb-3 fw-700"><?php echo htmlspecialchars($announcement['title']); ?></h5>
                    <p class="small text-light opacity-75 mb-4"><?php echo htmlspecialchars($announcement['content']); ?></p>
                    <?php if ($announcement['priority'] == 'urgent' || $announcement['priority'] == 'high'): ?>
                    <span class="badge bg-<?php echo $announcement['priority'] == 'urgent' ? 'danger' : 'warning'; ?> text-dark mb-2">
                        <i class="bi bi-exclamation-triangle me-1"></i><?php echo ucfirst($announcement['priority']); ?>
                    </span>
                    <?php endif; ?>
                    <p class="small text-light opacity-50 mb-0">
                        <i class="bi bi-clock me-1"></i>Posted <?php echo formatDate($announcement['created_at'], 'M d, Y'); ?>
                        <?php if ($announcement['creator_name']): ?>
                        by <?php echo htmlspecialchars($announcement['creator_name']); ?>
                        <?php endif; ?>
                    </p>
                    <?php else: ?>
                    <h5 class="mb-3 fw-700">No Announcements</h5>
                    <p class="small text-light opacity-75 mb-0">Check back later for updates.</p>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Active Projects Widget -->
            <div class="card border-0 shadow-md rounded-4 mb-4">
                <div class="card-header bg-white border-0 pt-4 px-4 d-flex justify-content-between align-items-center">
                    <h5 class="mb-0 fw-bold d-flex align-items-center">
                        <span class="p-2 bg-success bg-opacity-10 rounded-3 me-2">
                            <i class="bi bi-folder text-success"></i>
                        </span>
                        Active Projects
                    </h5>
                    <a href="<?php echo $base_url; ?>/modules/projects/index.php" class="btn btn-sm btn-outline-primary rounded-3 px-4 py-2 text-nowrap">
                        <i class="bi bi-arrow-right me-1"></i>View All
                    </a>
                </div>
                <div class="card-body p-0">
                    <?php
                    // Get active projects for this user (or all for admin)
                    if ($role == 'admin' || $role == 'hr') {
                        $proj_sql = "SELECT p.*, u.full_name as leader_name 
                                    FROM projects p 
                                    LEFT JOIN users u ON p.project_leader = u.id 
                                    WHERE p.status = 'active' 
                                    ORDER BY p.created_at DESC LIMIT 3";
                        $proj_stmt = $conn->prepare($proj_sql);
                    } else {
                        $proj_sql = "SELECT p.*, u.full_name as leader_name 
                                    FROM projects p 
                                    LEFT JOIN users u ON p.project_leader = u.id 
                                    LEFT JOIN project_members pm ON p.id = pm.project_id 
                                    WHERE p.status = 'active' AND (p.project_leader = ? OR pm.user_id = ?)
                                    ORDER BY p.created_at DESC LIMIT 3";
                        $proj_stmt = $conn->prepare($proj_sql);
                        $proj_stmt->bind_param("ii", $user_id, $user_id);
                    }
                    
                    if ($proj_stmt) {
                        if ($role != 'admin' && $role != 'hr') {
                            $proj_stmt->bind_param("ii", $user_id, $user_id);
                        }
                        $proj_stmt->execute();
                        $projects_result = $proj_stmt->get_result();
                        
                        if ($projects_result->num_rows > 0):
                    ?>
                    <div class="list-group list-group-flush">
                        <?php while ($proj = $projects_result->fetch_assoc()): ?>
                        <a href="<?php echo $base_url; ?>/modules/projects/details.php?id=<?php echo $proj['id']; ?>" 
                           class="list-group-item list-group-item-action border-0 px-4 py-3">
                            <div class="d-flex w-100 justify-content-between align-items-center">
                                <div>
                                    <h6 class="mb-1 fw-bold text-dark"><?php echo htmlspecialchars($proj['project_name']); ?></h6>
                                    <small class="text-muted">
                                        <i class="bi bi-person me-1"></i><?php echo htmlspecialchars($proj['leader_name']); ?>
                                    </small>
                                </div>
                                <span class="badge bg-success rounded-pill">Active</span>
                            </div>
                        </a>
                        <?php endwhile; ?>
                    </div>
                    <?php 
                        else:
                    ?>
                    <div class="text-center py-4">
                        <i class="bi bi-folder-x display-4 text-muted mb-2"></i>
                        <p class="text-muted small mb-0">No active projects</p>
                    </div>
                    <?php 
                        endif;
                    } else {
                        echo '<div class="text-center py-3 text-danger">Error loading projects</div>';
                    }
                    ?>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
    .fw-800 { font-weight: 800; }
    .fw-700 { font-weight: 700; }
    .fw-600 { font-weight: 600; }
    .fw-500 { font-weight: 500; }
    .letter-spacing-1 { letter-spacing: 1px; }
    .blur-3xl { filter: blur(64px); }
    
    .quick-action-btn-new {
        transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        border: 1px solid transparent;
    }
    
    .quick-action-btn-new:hover {
        transform: translateY(-8px);
        background: white !important;
        border-color: rgba(79, 70, 229, 0.1);
        box-shadow: var(--shadow-xl);
    }
    
    .icon-circle {
        width: 44px;
        height: 44px;
        border-radius: 12px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.25rem;
    }
    
    .avatar-status-container {
        position: relative;
    }
    
    .status-indicator {
        position: absolute;
        bottom: 2px;
        right: 2px;
        width: 10px;
        height: 10px;
        border: 2px solid white;
        border-radius: 50%;
    }
    
    .hover-bg-light:hover {
        background-color: #f8fafc;
    }
    
    .transition-all {
        transition: all 0.2s ease;
    }

    @media (max-width: 991.98px) {
        .quick-action-btn-new {
            padding: 1rem !important;
        }

        .quick-action-btn-new:hover {
            transform: none;
        }
    }

    @media (max-width: 767.98px) {
        .welcome-meta {
            flex-wrap: wrap;
            gap: 0.5rem !important;
        }

        .hero-section .display-5 {
            font-size: 1.9rem;
        }

        .milestone-header {
            gap: 0.75rem;
            flex-wrap: wrap;
        }
    }
</style>

<script>
    // Real-time clock update
    function updateClock() {
        const now = new Date();
        const options = { hour: '2-digit', minute: '2-digit', hour12: true };
        document.getElementById('realtime-clock').textContent = now.toLocaleTimeString('en-US', options);
    }
    setInterval(updateClock, 30000);
</script>

<?php require_once 'includes/footer.php'; ?>
