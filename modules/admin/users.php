<?php
// Define entry point constant
define('APP_ENTRY_POINT', true);

require_once '../../includes/auth.php';
require_once '../../includes/functions.php';

// Check login status and require admin role
Auth::requireRole('admin');

$conn = getDBConnection();

// Handle user actions via POST
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    // Verify CSRF token
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        setFlash('danger', 'Invalid security token. Please try again.');
        header('Location: users.php');
        exit();
    }
    
    $action = htmlspecialchars($_POST['action'], ENT_QUOTES, 'UTF-8');
    $user_id = (int)($_POST['user_id'] ?? 0);

    $allowed_actions = ['activate', 'suspend', 'delete'];
    if (!in_array($action, $allowed_actions, true) || $user_id <= 0) {
        setFlash('danger', 'Invalid request.');
        header('Location: ' . Auth::getBasePath() . '/modules/admin/users.php');
        exit();
    }
    
    $verify_sql = "SELECT id FROM users WHERE id = ?";
    $verify_stmt = dbPrepare($conn, $verify_sql, 'users verify exists');
    if (!$verify_stmt) {
        setFlash('danger', 'Could not complete the request. Please try again.');
        header('Location: ' . Auth::getBasePath() . '/modules/admin/users.php');
        exit();
    }
    $verify_stmt->bind_param("i", $user_id);
    if (!dbExecute($verify_stmt, 'users verify exists')) {
        $verify_stmt->close();
        setFlash('danger', 'Could not complete the request. Please try again.');
        header('Location: ' . Auth::getBasePath() . '/modules/admin/users.php');
        exit();
    }
    if ($verify_stmt->get_result()->num_rows === 0) {
        $verify_stmt->close();
        setFlash('danger', 'User not found.');
        header('Location: ' . Auth::getBasePath() . '/modules/admin/users.php');
        exit();
    }
    $verify_stmt->close();

    switch ($action) {
        case 'activate':
            $get_user_sql = "SELECT email, full_name, username FROM users WHERE id = ?";
            $get_user_stmt = dbPrepare($conn, $get_user_sql, 'users activate fetch');
            $user_data = null;
            if ($get_user_stmt) {
                $get_user_stmt->bind_param("i", $user_id);
                if (dbExecute($get_user_stmt, 'users activate fetch')) {
                    $user_data = $get_user_stmt->get_result()->fetch_assoc();
                }
                $get_user_stmt->close();
            }

            $sql = "UPDATE users SET status = 'active' WHERE id = ?";
            $stmt = dbPrepare($conn, $sql, 'users activate');
            if (!$stmt) {
                setFlash('danger', 'Could not activate user. Please try again.');
                break;
            }
            $stmt->bind_param("i", $user_id);
            if (!dbExecute($stmt, 'users activate')) {
                $stmt->close();
                setFlash('danger', 'Could not activate user. Please try again.');
                break;
            }
            $stmt->close();

            if ($user_data) {
                notifyUserStatusChanged($user_data, 'active', $_SESSION['full_name'] ?? 'Admin');
                logAdminActivity('USER_ACTIVATED', "User account activated: {$user_data['full_name']}", $user_id, ['new_status' => 'active']);
            }

            setFlash('success', 'User activated successfully.');
            break;

        case 'suspend':
            $get_user_sql = "SELECT email, full_name, username FROM users WHERE id = ?";
            $get_user_stmt = dbPrepare($conn, $get_user_sql, 'users suspend fetch');
            $user_data = null;
            if ($get_user_stmt) {
                $get_user_stmt->bind_param("i", $user_id);
                if (dbExecute($get_user_stmt, 'users suspend fetch')) {
                    $user_data = $get_user_stmt->get_result()->fetch_assoc();
                }
                $get_user_stmt->close();
            }

            $sql = "UPDATE users SET status = 'suspended' WHERE id = ?";
            $stmt = dbPrepare($conn, $sql, 'users suspend');
            if (!$stmt) {
                setFlash('danger', 'Could not suspend user. Please try again.');
                break;
            }
            $stmt->bind_param("i", $user_id);
            if (!dbExecute($stmt, 'users suspend')) {
                $stmt->close();
                setFlash('danger', 'Could not suspend user. Please try again.');
                break;
            }
            $stmt->close();

            if ($user_data) {
                notifyUserStatusChanged($user_data, 'suspended', $_SESSION['full_name'] ?? 'Admin');
                logAdminActivity('USER_SUSPENDED', "User account suspended: {$user_data['full_name']}", $user_id, ['new_status' => 'suspended']);
            }

            setFlash('warning', 'User suspended.');
            break;

        case 'delete':
            $has_dependencies = false;
            $dep_check_failed = false;

            $check_sql = "SELECT COUNT(*) as count FROM leaves WHERE user_id = ?";
            $stmt = dbPrepare($conn, $check_sql, 'users delete check leaves');
            if (!$stmt) {
                $dep_check_failed = true;
            } else {
                $stmt->bind_param("i", $user_id);
                if (!dbExecute($stmt, 'users delete check leaves')) {
                    $dep_check_failed = true;
                } else {
                    $row = $stmt->get_result()->fetch_assoc();
                    if ((int)($row['count'] ?? 0) > 0) {
                        $has_dependencies = true;
                    }
                }
                $stmt->close();
            }

            if (!$dep_check_failed && !$has_dependencies) {
                $check_sql = "SELECT COUNT(*) as count FROM tasks WHERE assigned_to = ? OR assigned_by = ?";
                $stmt = dbPrepare($conn, $check_sql, 'users delete check tasks');
                if (!$stmt) {
                    $dep_check_failed = true;
                } else {
                    $stmt->bind_param("ii", $user_id, $user_id);
                    if (!dbExecute($stmt, 'users delete check tasks')) {
                        $dep_check_failed = true;
                    } else {
                        $row = $stmt->get_result()->fetch_assoc();
                        if ((int)($row['count'] ?? 0) > 0) {
                            $has_dependencies = true;
                        }
                    }
                    $stmt->close();
                }
            }

            if (!$dep_check_failed && !$has_dependencies) {
                $check_sql = "SELECT COUNT(*) as count FROM projects WHERE project_leader = ?";
                $stmt = dbPrepare($conn, $check_sql, 'users delete check projects');
                if (!$stmt) {
                    $dep_check_failed = true;
                } else {
                    $stmt->bind_param("i", $user_id);
                    if (!dbExecute($stmt, 'users delete check projects')) {
                        $dep_check_failed = true;
                    } else {
                        $row = $stmt->get_result()->fetch_assoc();
                        if ((int)($row['count'] ?? 0) > 0) {
                            $has_dependencies = true;
                        }
                    }
                    $stmt->close();
                }
            }

            if ($dep_check_failed) {
                setFlash('danger', 'Could not verify whether this user can be deactivated. Please try again.');
            } elseif ($has_dependencies) {
                setFlash('danger', 'Cannot delete user with existing records. Suspend instead.');
            } else {
                $sql = "UPDATE users SET status = 'inactive' WHERE id = ?";
                $stmt = dbPrepare($conn, $sql, 'users deactivate');
                if (!$stmt) {
                    setFlash('danger', 'Could not deactivate user. Please try again.');
                } else {
                    $stmt->bind_param("i", $user_id);
                    if (!dbExecute($stmt, 'users deactivate')) {
                        setFlash('danger', 'Could not deactivate user. Please try again.');
                    } else {
                        setFlash('success', 'User deactivated.');
                    }
                    $stmt->close();
                }
            }
            break;
    }
    
    header('Location: ' . Auth::getBasePath() . '/modules/admin/users.php');
    exit();
}

// Get all users with pending task counts
$sql = "SELECT u.*, 
        (SELECT COUNT(*) FROM tasks t WHERE t.assigned_to = u.id AND t.status NOT IN ('completed', 'cancelled')) as pending_tasks
        FROM users u 
        ORDER BY 
        CASE u.role 
            WHEN 'admin' THEN 1
            WHEN 'hr' THEN 2
            WHEN 'project_leader' THEN 3
            ELSE 4
        END, u.full_name";
$result = dbQuery($conn, $sql, 'users list');
$user_rows = [];
if ($result instanceof mysqli_result) {
    while ($u = $result->fetch_assoc()) {
        $user_rows[] = $u;
    }
    $result->free();
}

$stats_sql = "SELECT 
    COUNT(*) as total,
    SUM(CASE WHEN role = 'admin' THEN 1 ELSE 0 END) as admins,
    SUM(CASE WHEN role = 'hr' THEN 1 ELSE 0 END) as hr,
    SUM(CASE WHEN role = 'project_leader' THEN 1 ELSE 0 END) as leaders,
    SUM(CASE WHEN role = 'employee' THEN 1 ELSE 0 END) as employees,
    SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) as active,
    SUM(CASE WHEN status = 'inactive' THEN 1 ELSE 0 END) as inactive,
    SUM(CASE WHEN status = 'suspended' THEN 1 ELSE 0 END) as suspended
    FROM users";
$stats_result = dbQuery($conn, $stats_sql, 'users stats');
$stats = [
    'total' => 0,
    'admins' => 0,
    'hr' => 0,
    'leaders' => 0,
    'employees' => 0,
    'active' => 0,
    'inactive' => 0,
    'suspended' => 0,
];
if ($stats_result instanceof mysqli_result) {
    $stats_row = $stats_result->fetch_assoc();
    if (is_array($stats_row)) {
        $stats = array_merge($stats, $stats_row);
    }
    $stats_result->free();
}

$page_title = 'User Management';
require_once '../../includes/header.php';
?>
<div class="container-fluid">
    <div class="row mb-4 align-items-center">
        <div class="col">
            <h1 class="h2 fw-800 mb-1">User Ecosystem</h1>
            <p class="text-muted fw-500 mb-0">Orchestrate system access, roles, and enterprise-wide permissions.</p>
        </div>
        <div class="col-auto">
            <div class="d-flex gap-2">
                <a href="<?php echo $base_url; ?>/modules/admin/settings.php" class="btn btn-outline-secondary d-flex align-items-center">
                    <i class="bi bi-sliders me-2"></i> Settings
                </a>
                <a href="<?php echo $base_url; ?>/modules/admin/reports.php" class="btn btn-outline-secondary d-flex align-items-center">
                    <i class="bi bi-graph-up me-2"></i> Reports
                </a>
                <a href="<?php echo $base_url; ?>/modules/admin/announcements.php" class="btn btn-outline-secondary d-flex align-items-center">
                    <i class="bi bi-megaphone me-2"></i> Announcements
                </a>
                <a href="<?php echo $base_url; ?>/modules/admin/backup_restore.php" class="btn btn-outline-secondary d-flex align-items-center">
                    <i class="bi bi-database-down me-2"></i> Backup
                </a>
                <a href="<?php echo $base_url; ?>/register.php" class="btn btn-primary d-flex align-items-center">
                    <i class="bi bi-person-plus me-2"></i> Deploy New User
                </a>
                <button class="btn btn-outline-secondary d-flex align-items-center" data-bs-toggle="modal" data-bs-target="#importModal">
                    <i class="bi bi-cloud-arrow-up me-2"></i> Batch Import
                </button>
            </div>
        </div>
    </div>
    
    <!-- User Stats -->
    <div class="row g-4 mb-5">
        <div class="col-xl-3 col-lg-4 col-sm-6">
            <div class="stat-card glass h-100">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <div class="stat-label">TOTAL OPERATIVES</div>
                        <div class="stat-value"><?php echo $stats['total']; ?></div>
                    </div>
                    <div class="stat-icon bg-primary bg-opacity-10 text-primary">
                        <i class="bi bi-people"></i>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-xl-3 col-lg-4 col-sm-6">
            <div class="stat-card glass h-100 border-start border-4 border-danger">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <div class="stat-label">ADMINISTRATORS</div>
                        <div class="stat-value text-danger"><?php echo $stats['admins']; ?></div>
                    </div>
                    <div class="stat-icon bg-danger bg-opacity-10 text-danger">
                        <i class="bi bi-shield-lock"></i>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-3 col-lg-4 col-sm-6">
            <div class="stat-card glass h-100 border-start border-4 border-warning">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <div class="stat-label">HR MANAGERS</div>
                        <div class="stat-value text-warning"><?php echo $stats['hr']; ?></div>
                    </div>
                    <div class="stat-icon bg-warning bg-opacity-10 text-warning">
                        <i class="bi bi-person-badge"></i>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-xl-3 col-lg-4 col-sm-6">
            <div class="stat-card glass h-100 border-start border-4 border-primary">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <div class="stat-label">PROJECT LEADERS</div>
                        <div class="stat-value text-primary"><?php echo $stats['leaders']; ?></div>
                    </div>
                    <div class="stat-icon bg-primary bg-opacity-10 text-primary">
                        <i class="bi bi-rocket-takeoff"></i>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-3 col-lg-4 col-sm-6">
            <div class="stat-card glass h-100 border-start border-4 border-info">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <div class="stat-label">EMPLOYEES</div>
                        <div class="stat-value text-info"><?php echo $stats['employees']; ?></div>
                    </div>
                    <div class="stat-icon bg-info bg-opacity-10 text-info">
                        <i class="bi bi-person-workspace"></i>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-xl-3 col-lg-4 col-sm-6">
            <div class="stat-card glass h-100 border-start border-4 border-success">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <div class="stat-label">ACTIVE NODES</div>
                        <div class="stat-value text-success"><?php echo $stats['active']; ?></div>
                    </div>
                    <div class="stat-icon bg-success bg-opacity-10 text-success">
                        <i class="bi bi-activity"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Users Table -->
    <div class="row">
        <div class="col">
            <div class="glass border-0 shadow-sm rounded-4 overflow-hidden">
                <div class="p-4 border-bottom bg-white bg-opacity-50">
                    <div class="row align-items-center">
                        <div class="col">
                            <h5 class="mb-0 fw-700">Operational Directory</h5>
                        </div>
                        <div class="col-auto">
                            <div class="input-group users-search-group">
                                <span class="input-group-text bg-light border-0"><i class="bi bi-search"></i></span>
                                <input type="text" class="form-control bg-light border-0 shadow-none" placeholder="Search by name, role, ID..." id="searchUsers">
                            </div>
                        </div>
                    </div>
                </div>
                <div class="p-0">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0" id="usersTable">
                            <thead class="bg-light">
                                <tr>
                                    <th class="ps-4">UID</th>
                                    <th>IDENTITY</th>
                                    <th>ACCESS</th>
                                    <th>DEPARTMENT</th>
                                    <th>REMUNERATION</th>
                                    <th>ACTIVITY</th>
                                    <th>LOAD</th>
                                    <th>STATE</th>
                                    <th class="pe-4 text-end">OPERATIONS</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (count($user_rows) > 0): ?>
                                    <?php $modals_html = ''; ?>
                                    <?php foreach ($user_rows as $row): 
                                        $status_badges = [
                                            'active' => 'success',
                                            'inactive' => 'secondary',
                                            'suspended' => 'danger'
                                        ];
                                        $status_color = $status_badges[$row['status']] ?? 'secondary';
                                    ?>
                                    <tr>
                                        <td class="ps-4">
                                            <span class="badge bg-light text-dark border fw-700">#<?php echo $row['employee_id']; ?></span>
                                        </td>
                                        <td>
                                            <div class="d-flex align-items-center">
                                                <div class="user-avatar me-3 shadow-sm border border-2 border-white" style="width: 42px; height: 42px; font-size: 0.9rem;">
                                                    <?php 
                                                    $name_parts = explode(' ', $row['full_name']);
                                                    echo strtoupper(substr($name_parts[0], 0, 1) . (isset($name_parts[1]) ? substr($name_parts[1], 0, 1) : ''));
                                                    ?>
                                                </div>
                                                <div>
                                                    <div class="fw-700 text-dark"><?php echo htmlspecialchars($row['full_name']); ?></div>
                                                    <div class="text-muted small"><?php echo htmlspecialchars($row['email'] ?? 'N/A'); ?></div>
                                                </div>
                                            </div>
                                        </td>
                                        <td>
                                            <div class="fw-700 text-primary small"><?php echo htmlspecialchars($row['username']); ?></div>
                                            <div class="opacity-75"><?php echo getRoleBadge($row['role']); ?></div>
                                        </td>
                                        <td>
                                            <span class="badge bg-light text-secondary border fw-600"><?php echo htmlspecialchars($row['department'] ?? 'GENERAL'); ?></span>
                                        </td>
                                        <td>
                                            <?php if ($row['salary']): ?>
                                            <div class="fw-700 text-dark">$<?php echo number_format($row['salary'], 2); ?></div>
                                            <div class="text-muted smallest text-uppercase">Monthly</div>
                                            <?php else: ?>
                                            <span class="text-muted italic small">Not Disclosed</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if ($row['last_login']): ?>
                                            <div class="fw-600 text-dark small"><?php echo date('M d, H:i', strtotime($row['last_login'])); ?></div>
                                            <div class="text-success smallest fw-700 text-uppercase">Online Record</div>
                                            <?php else: ?>
                                            <div class="text-muted italic small">Never Active</div>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <div class="d-flex align-items-center">
                                                <span class="fw-800 text-dark me-2"><?php echo $row['pending_tasks']; ?></span>
                                                <div class="progress flex-grow-1" style="height: 4px; min-width: 40px;">
                                                    <div class="progress-bar <?php echo $row['pending_tasks'] > 5 ? 'bg-danger' : ($row['pending_tasks'] > 2 ? 'bg-warning' : 'bg-success'); ?>" 
                                                         style="width: <?php echo min(100, $row['pending_tasks'] * 10); ?>%"></div>
                                                </div>
                                            </div>
                                            <div class="text-muted smallest text-uppercase">Pending Tasks</div>
                                        </td>
                                        <td>
                                            <?php
                                                $status_classes = [
                                                    'active' => 'bg-success bg-opacity-10 text-success border-success border-opacity-25',
                                                    'inactive' => 'bg-secondary bg-opacity-10 text-secondary border-secondary border-opacity-25',
                                                    'suspended' => 'bg-danger bg-opacity-10 text-danger border-danger border-opacity-25'
                                                ];
                                                $status_class = $status_classes[$row['status']] ?? 'bg-secondary';
                                            ?>
                                            <span class="badge <?php echo $status_class; ?> border fw-700 px-3 py-2">
                                                <i class="bi bi-circle-fill me-1 smallest"></i> <?php echo strtoupper($row['status']); ?>
                                            </span>
                                        </td>
                                        <td class="pe-4 text-end">
                                            <div class="d-flex justify-content-end gap-2">
                                                <button class="btn btn-icon-only glass-hover" data-bs-toggle="modal" data-bs-target="#editModal<?php echo $row['id']; ?>" title="Configure">
                                                    <i class="bi bi-sliders2 text-primary"></i>
                                                </button>
                                                <div class="dropdown">
                                                    <button class="btn btn-icon-only glass-hover" data-bs-toggle="dropdown">
                                                        <i class="bi bi-three-dots-vertical"></i>
                                                    </button>
                                                    <ul class="dropdown-menu dropdown-menu-end glass border shadow-lg mt-2">
                                                        <li><a class="dropdown-item fw-600" href="#" data-bs-toggle="modal" data-bs-target="#viewModal<?php echo $row['id']; ?>">
                                                            <i class="bi bi-eye me-2 text-primary"></i> Profile Core
                                                        </a></li>
                                                        <li><hr class="dropdown-divider opacity-10"></li>
                                                        
                                                        <?php if ($row['status'] != 'active'): ?>
                                                        <li>
                                                            <form method="POST" class="m-0">
                                                                <?php echo csrfField(); ?>
                                                                <input type="hidden" name="action" value="activate">
                                                                <input type="hidden" name="user_id" value="<?php echo $row['id']; ?>">
                                                                <button type="submit" class="dropdown-item fw-600 text-success">
                                                                    <i class="bi bi-shield-check me-2"></i> Authorized
                                                                </button>
                                                            </form>
                                                        </li>
                                                        <?php endif; ?>
                                                        
                                                        <?php if ($row['status'] != 'suspended'): ?>
                                                        <li>
                                                            <form method="POST" class="m-0">
                                                                <?php echo csrfField(); ?>
                                                                <input type="hidden" name="action" value="suspend">
                                                                <input type="hidden" name="user_id" value="<?php echo $row['id']; ?>">
                                                                <button type="submit" class="dropdown-item fw-600 text-warning">
                                                                    <i class="bi bi-shield-slash me-2"></i> Suspend Access
                                                                </button>
                                                            </form>
                                                        </li>
                                                        <?php endif; ?>
                                                        
                                                        <?php if ($row['id'] != $_SESSION['user_id']): ?>
                                                        <li>
                                                            <form method="POST" class="m-0" onsubmit="return confirm('Archive this operative?');">
                                                                <?php echo csrfField(); ?>
                                                                <input type="hidden" name="action" value="delete">
                                                                <input type="hidden" name="user_id" value="<?php echo $row['id']; ?>">
                                                                <button type="submit" class="dropdown-item fw-600 text-danger">
                                                                    <i class="bi bi-archive me-2"></i> Terminate Node
                                                                </button>
                                                            </form>
                                                        </li>
                                                        <?php endif; ?>
                                                    </ul>
                                                </div>
                                            </div>
                                        </td>
                                    </tr>
                                    <?php
                                        ob_start();
                                    ?>
                                    <!-- View Modal -->
                                    <div class="modal fade" id="viewModal<?php echo $row['id']; ?>" tabindex="-1">
                                        <div class="modal-dialog modal-lg">
                                            <div class="modal-content">
                                                <div class="modal-header">
                                                    <h5 class="modal-title">User Details - <?php echo htmlspecialchars($row['full_name']); ?></h5>
                                                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                                </div>
                                                <div class="modal-body">
                                                    <div class="row">
                                                        <div class="col-md-4 text-center">
                                                            <div class="user-avatar mx-auto mb-3" style="width: 100px; height: 100px; font-size: 2rem;">
                                                                <?php 
                                                                $name_parts = explode(' ', $row['full_name']);
                                                                $initials = '';
                                                                foreach ($name_parts as $part) {
                                                                    $initials .= strtoupper(substr($part, 0, 1));
                                                                }
                                                                echo substr($initials, 0, 2);
                                                                ?>
                                                            </div>
                                                            <h5><?php echo htmlspecialchars($row['full_name']); ?></h5>
                                                            <p class="text-muted"><?php echo htmlspecialchars($row['position'] ?? 'N/A'); ?></p>
                                                        </div>
                                                        
                                                        <div class="col-md-8">
                                                            <table class="table table-sm">
                                                                <tr>
                                                                    <th width="40%">Employee ID:</th>
                                                                    <td><?php echo $row['employee_id']; ?></td>
                                                                </tr>
                                                                <tr>
                                                                    <th>Username:</th>
                                                                    <td><?php echo $row['username']; ?></td>
                                                                </tr>
                                                                <tr>
                                                                    <th>Email:</th>
                                                                    <td><?php echo $row['email']; ?></td>
                                                                </tr>
                                                                <tr>
                                                                    <th>Role:</th>
                                                                    <td><?php echo getRoleBadge($row['role']); ?></td>
                                                                </tr>
                                                                <tr>
                                                                    <th>Department:</th>
                                                                    <td><?php echo $row['department']; ?></td>
                                                                </tr>
                                                                <tr>
                                                                    <th>Position:</th>
                                                                    <td><?php echo $row['position']; ?></td>
                                                                </tr>
                                                                <tr>
                                                                    <th>Salary:</th>
                                                                    <td>$<?php echo number_format($row['salary'], 2); ?> monthly</td>
                                                                </tr>
                                                                <tr>
                                                                    <th>Status:</th>
                                                                    <td>
                                                                        <span class="badge bg-<?php echo $status_color; ?>">
                                                                            <?php echo ucfirst($row['status']); ?>
                                                                        </span>
                                                                    </td>
                                                                </tr>
                                                                <tr>
                                                                    <th>Hire Date:</th>
                                                                    <td><?php echo formatDate($row['hire_date']); ?></td>
                                                                </tr>
                                                                <tr>
                                                                    <th>Last Login:</th>
                                                                    <td>
                                                                        <?php if ($row['last_login']): ?>
                                                                        <?php echo formatDate($row['last_login'], 'F d, Y h:i A'); ?>
                                                                        <?php else: ?>
                                                                        Never logged in
                                                                        <?php endif; ?>
                                                                    </td>
                                                                </tr>
                                                            </table>
                                                        </div>
                                                    </div>
                                                </div>
                                                <div class="modal-footer">
                                                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                                                    <button type="button" class="btn btn-primary" 
                                                            data-bs-toggle="modal" data-bs-target="#editModal<?php echo $row['id']; ?>"
                                                            data-bs-dismiss="modal">
                                                        Edit User
                                                    </button>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <!-- Edit Modal -->
                                    <div class="modal fade" id="editModal<?php echo $row['id']; ?>" tabindex="-1">
                                        <div class="modal-dialog">
                                            <div class="modal-content">
                                                <form method="POST" action="update_user.php">
                                                    <?php echo csrfField(); ?>
                                                    <div class="modal-header">
                                                        <h5 class="modal-title">Edit User - <?php echo htmlspecialchars($row['full_name']); ?></h5>
                                                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                                    </div>
                                                    <div class="modal-body">
                                                        <input type="hidden" name="user_id" value="<?php echo $row['id']; ?>">
                                                        
                                                        <div class="mb-3">
                                                            <label class="form-label">Full Name</label>
                                                            <input type="text" class="form-control" name="full_name" 
                                                                   value="<?php echo htmlspecialchars($row['full_name']); ?>" required>
                                                        </div>
                                                        
                                                        <div class="row mb-3">
                                                            <div class="col-md-6">
                                                                <label class="form-label">Role</label>
                                                                <select class="form-select" name="role" data-required="true">
                                                                    <option value="employee" <?php echo $row['role'] == 'employee' ? 'selected' : ''; ?>>Employee</option>
                                                                    <option value="project_leader" <?php echo $row['role'] == 'project_leader' ? 'selected' : ''; ?>>Project Leader</option>
                                                                    <option value="hr" <?php echo $row['role'] == 'hr' ? 'selected' : ''; ?>>HR</option>
                                                                    <option value="admin" <?php echo $row['role'] == 'admin' ? 'selected' : ''; ?>>Admin</option>
                                                                </select>
                                                            </div>
                                                            <div class="col-md-6">
                                                                <label class="form-label">Status</label>
                                                                <select class="form-select" name="status" data-required="true">
                                                                    <option value="active" <?php echo $row['status'] == 'active' ? 'selected' : ''; ?>>Active</option>
                                                                    <option value="inactive" <?php echo $row['status'] == 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                                                                    <option value="suspended" <?php echo $row['status'] == 'suspended' ? 'selected' : ''; ?>>Suspended</option>
                                                                </select>
                                                            </div>
                                                        </div>
                                                        
                                                        <div class="row mb-3">
                                                            <div class="col-md-6">
                                                                <label class="form-label">Department</label>
                                                                <input type="text" class="form-control" name="department" 
                                                                       value="<?php echo htmlspecialchars($row['department']); ?>">
                                                            </div>
                                                            <div class="col-md-6">
                                                                <label class="form-label">Position</label>
                                                                <input type="text" class="form-control" name="position" 
                                                                       value="<?php echo htmlspecialchars($row['position']); ?>">
                                                            </div>
                                                        </div>
                                                        
                                                        <div class="mb-3">
                                                            <label class="form-label">Salary ($)</label>
                                                            <input type="number" step="0.01" class="form-control" name="salary" 
                                                                   value="<?php echo $row['salary']; ?>">
                                                        </div>
                                                    </div>
                                                    <div class="modal-footer">
                                                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                                        <button type="submit" class="btn btn-primary">Save Changes</button>
                                                    </div>
                                                </form>
                                            </div>
                                        </div>
                                    </div>
                                    <?php
                                        $modals_html .= ob_get_clean();
                                    ?>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                <tr>
                                    <td colspan="9" class="text-center py-4">
                                        <i class="bi bi-people h1 text-muted d-block mb-3"></i>
                                        <p class="text-muted">No users found.</p>
                                        <a href="<?php echo $base_url; ?>/register.php" class="btn btn-primary">Add First User</a>
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
    <?php if (!empty($modals_html)) echo $modals_html; ?>
</div>

<!-- Import Modal -->
<div class="modal fade" id="importModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form action="import_users.php" method="POST" enctype="multipart/form-data">
                <?php echo csrfField(); ?>
                <div class="modal-header">
                    <h5 class="modal-title">Import Users</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="alert alert-info">
                        <i class="bi bi-info-circle"></i> 
                        Download the template file, fill in user details, and upload it here.
                    </div>
                    
                    <div class="mb-3">
                        <a href="<?php echo $base_url; ?>/modules/admin/download_csv_template.php?type=users" class="btn btn-outline-primary">
                            <i class="bi bi-download"></i> Download Template
                        </a>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Upload CSV File</label>
                        <input type="file" name="csv_file" class="form-control" accept=".csv" data-required="true">
                    </div>
                    
                    <div class="form-check mb-3">
                        <input class="form-check-input" type="checkbox" name="send_welcome" id="sendWelcomeEmail">
                        <label class="form-check-label" for="sendWelcomeEmail">
                            Send welcome email to new users
                        </label>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Import Users</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
    // Search functionality
    document.getElementById('searchUsers').addEventListener('input', function() {
        const searchTerm = this.value.toLowerCase();
        const rows = document.querySelectorAll('#usersTable tbody tr');
        
        rows.forEach(row => {
            const text = row.textContent.toLowerCase();
            row.style.display = text.includes(searchTerm) ? '' : 'none';
        });
    });
</script>

<?php require_once '../../includes/footer.php'; ?>
