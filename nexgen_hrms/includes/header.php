<?php
// Start session at the VERY TOP
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Use absolute path relative to the root of nexgen_hrms
$base_dir = dirname(dirname(__FILE__));
require_once $base_dir . '/includes/auth.php';
require_once $base_dir . '/includes/functions.php';

// This header is used for authenticated pages; enforce login before any output.
Auth::requireLogin();

$current_user = Auth::getCurrentUser();

// Calculate base URL for navigation links (use centralized helper)
// Auth::getBasePath() returns either '' (if hosted at domain root)
// or '/your_app_folder' when hosted inside a subfolder.
$base_url = Auth::getBasePath();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>NexGen HRMS - <?php echo $page_title ?? 'Dashboard'; ?></title>
    
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    
    <link rel="stylesheet" href="<?php echo $base_url; ?>/assets/css/style.css">
</head>
<body>
    <!-- Sidebar -->
    <div class="sidebar">
        <div class="sidebar-brand">
            <h4 class="mb-0"><i class="bi bi-building"></i> <span>NexGen HRMS</span></h4>
        </div>
        
        <div class="sidebar-nav">
            <?php if (Auth::isLoggedIn()): ?>
            <!-- Dashboard -->
            <div class="nav-item">
                <a href="<?php echo $base_url; ?>/dashboard.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'dashboard.php' ? 'active' : ''; ?>">
                    <i class="bi bi-speedometer2"></i>
                    <span class="nav-text">Dashboard</span>
                </a>
            </div>
            
            <!-- Leave Management -->
            <div class="nav-item">
                <a href="<?php echo $base_url; ?>/modules/leave/apply.php" class="nav-link <?php echo strpos($_SERVER['PHP_SELF'], 'leave') !== false ? 'active' : ''; ?>">
                    <i class="bi bi-calendar-event"></i>
                    <span class="nav-text">Leave Management</span>
                </a>
            </div>
            
            <!-- Task Management -->
            <div class="nav-item">
                <a href="<?php echo $base_url; ?>/modules/tasks/my_tasks.php" class="nav-link <?php echo strpos($_SERVER['PHP_SELF'], 'tasks') !== false ? 'active' : ''; ?>">
                    <i class="bi bi-check-square"></i>
                    <span class="nav-text">Tasks</span>
                </a>
            </div>
            
            <!-- Projects -->
            <div class="nav-item">
                <a href="<?php echo $base_url; ?>/modules/projects/index.php" class="nav-link <?php echo strpos($_SERVER['PHP_SELF'], 'projects') !== false ? 'active' : ''; ?>">
                    <i class="bi bi-folder"></i>
                    <span class="nav-text">Projects</span>
                </a>
            </div>
            
            <!-- Payroll -->
            <div class="nav-item">
                <a href="<?php echo $base_url; ?>/modules/payroll/my_salary.php" class="nav-link <?php echo strpos($_SERVER['PHP_SELF'], 'payroll') !== false ? 'active' : ''; ?>">
                    <i class="bi bi-cash-stack"></i>
                    <span class="nav-text">Payroll</span>
                </a>
            </div>
            
            <!-- HR Only Features -->
            <?php if (Auth::hasRole('hr') || Auth::hasRole('admin')): ?>
            <div class="nav-item">
                <a href="<?php echo $base_url; ?>/modules/leave/manage.php" class="nav-link">
                    <i class="bi bi-people"></i>
                    <span class="nav-text">HR Management</span>
                </a>
            </div>
            <?php endif; ?>
            
            <!-- Project Leader Features -->
            <?php if (Auth::hasRole('project_leader') || Auth::hasRole('admin')): ?>
            <div class="nav-item">
                <a href="<?php echo $base_url; ?>/modules/tasks/assign.php" class="nav-link">
                    <i class="bi bi-person-plus"></i>
                    <span class="nav-text">Assign Tasks</span>
                </a>
            </div>
            <?php endif; ?>
            
            <!-- Admin Only Features -->
            <?php if (Auth::hasRole('admin')): ?>
            <div class="nav-item">
                <a href="<?php echo $base_url; ?>/modules/admin/users.php" class="nav-link">
                    <i class="bi bi-gear"></i>
                    <span class="nav-text">Admin Panel</span>
                </a>
            </div>
            <?php endif; ?>
            
            <!-- Public Website -->
            <div class="nav-item mt-5">
                <a href="<?php echo $base_url; ?>/index.php" class="nav-link" target="_blank">
                    <i class="bi bi-globe"></i>
                    <span class="nav-text">Public Website</span>
                </a>
            </div>
            
            <!-- Logout -->
            <div class="nav-item mt-3">
                <a href="<?php echo $base_url; ?>/logout.php" class="nav-link text-warning">
                    <i class="bi bi-box-arrow-right"></i>
                    <span class="nav-text">Logout</span>
                </a>
            </div>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- Main Content -->
    <div class="main-content">
        <!-- Header -->
        <?php if (Auth::isLoggedIn()): ?>
        <div class="main-header">
            <div>
                <h3 class="mb-0"><?php echo $page_title ?? 'Dashboard'; ?></h3>
                <p class="text-muted mb-0 small">
                    <?php 
                    echo date('l, F j, Y');
                    if (isset($page_subtitle)) {
                        echo ' | ' . $page_subtitle;
                    }
                    ?>
                </p>
            </div>
            
            <div class="user-dropdown">
                <button class="btn dropdown-toggle" type="button" data-bs-toggle="dropdown">
                    <div class="user-avatar">
                        <?php 
                        $name_parts = explode(' ', $current_user['full_name']);
                        $initials = '';
                        foreach ($name_parts as $part) {
                            $initials .= strtoupper(substr($part, 0, 1));
                        }
                        echo substr($initials, 0, 2);
                        ?>
                    </div>
                    <span><?php echo $current_user['full_name']; ?></span>
                </button>
                <ul class="dropdown-menu dropdown-menu-end">
                    <li><h6 class="dropdown-header"><?php echo getRoleBadge($_SESSION['role']); ?></h6></li>
                    <li><hr class="dropdown-divider"></li>
                    <li><a class="dropdown-item" href="<?php echo $base_url; ?>/profile.php"><i class="bi bi-person me-2"></i>My Profile</a></li>
                    <li><a class="dropdown-item" href="<?php echo $base_url; ?>/user_settings.php"><i class="bi bi-gear me-2"></i>Settings</a></li>
                    <li><hr class="dropdown-divider"></li>
                    <li><a class="dropdown-item text-danger" href="<?php echo $base_url; ?>/logout.php"><i class="bi bi-box-arrow-right me-2"></i>Logout</a></li>
                </ul>
            </div>
        </div>
        <?php endif; ?>
        
        <!-- Flash Messages -->
        <?php
        $flash = getFlash();
        if ($flash): ?>
        <div class="alert-flash">
            <div class="alert alert-<?php echo $flash['type']; ?> alert-dismissible fade show" role="alert">
                <?php echo $flash['message']; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        </div>
        <?php endif; ?>