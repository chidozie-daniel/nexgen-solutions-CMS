<?php
require_once '../../includes/auth.php';
require_once '../../includes/functions.php';

// Check login status first
Auth::requireLogin();

// Only admin can access
if (!Auth::hasRole('admin')) {
    setFlash('danger', 'Access denied. Admin only.');
    header('Location: ' . Auth::getBasePath() . '/dashboard.php');
    exit();
}

$conn = getDBConnection();

// Handle user actions via POST
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = htmlspecialchars($_POST['action'], ENT_QUOTES, 'UTF-8');
    $user_id = (int)($_POST['user_id'] ?? 0);
    
    // Verify user exists
    $verify_sql = "SELECT id FROM users WHERE id = ?";
    $verify_stmt = $conn->prepare($verify_sql);
    $verify_stmt->bind_param("i", $user_id);
    $verify_stmt->execute();
    if ($verify_stmt->get_result()->num_rows === 0) {
        setFlash('danger', 'User not found.');
        header('Location: ' . Auth::getBasePath() . '/modules/admin/users.php');
        exit();
    }
    
    switch ($action) {
        case 'activate':
            $sql = "UPDATE users SET status = 'active' WHERE id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("i", $user_id);
            $stmt->execute();
            setFlash('success', 'User activated successfully.');
            break;
            
        case 'suspend':
            $sql = "UPDATE users SET status = 'suspended' WHERE id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("i", $user_id);
            $stmt->execute();
            setFlash('warning', 'User suspended.');
            break;
            
        case 'delete':
            // Check dependencies
            $has_dependencies = false;
            
            // Check leaves
            $check_sql = "SELECT COUNT(*) as count FROM leaves WHERE user_id = ?";
            $stmt = $conn->prepare($check_sql);
            $stmt->bind_param("i", $user_id);
            $stmt->execute();
            $count = $stmt->get_result()->fetch_assoc()['count'];
            if ($count > 0) $has_dependencies = true;
            
            // Check tasks
            if (!$has_dependencies) {
                $check_sql = "SELECT COUNT(*) as count FROM tasks WHERE assigned_to = ? OR assigned_by = ?";
                $stmt = $conn->prepare($check_sql);
                $stmt->bind_param("ii", $user_id, $user_id);
                $stmt->execute();
                $count = $stmt->get_result()->fetch_assoc()['count'];
                if ($count > 0) $has_dependencies = true;
            }
            
            // Check projects
            if (!$has_dependencies) {
                $check_sql = "SELECT COUNT(*) as count FROM projects WHERE project_leader = ?";
                $stmt = $conn->prepare($check_sql);
                $stmt->bind_param("i", $user_id);
                $stmt->execute();
                $count = $stmt->get_result()->fetch_assoc()['count'];
                if ($count > 0) $has_dependencies = true;
            }
            
            if ($has_dependencies) {
                setFlash('danger', 'Cannot delete user with existing records. Suspend instead.');
            } else {
                $sql = "UPDATE users SET status = 'inactive' WHERE id = ?";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("i", $user_id);
                $stmt->execute();
                setFlash('success', 'User deactivated.');
            }
            break;
    }
    
    header('Location: ' . Auth::getBasePath() . '/modules/admin/users.php');
    exit();
}

// Get all users
$sql = "SELECT * FROM users ORDER BY 
        CASE role 
            WHEN 'admin' THEN 1
            WHEN 'hr' THEN 2
            WHEN 'project_leader' THEN 3
            ELSE 4
        END, full_name";
$result = $conn->query($sql);

// Get stats
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
$stats_result = $conn->query($stats_sql);
$stats = $stats_result->fetch_assoc();

$page_title = 'User Management';
require_once '../../includes/header.php';
?>
<div class="container-fluid">
    <div class="module-hero">
        <div class="container">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h1 class="mb-2">User Management</h1>
                    <p class="mb-0">Manage system users, roles, and permissions</p>
                </div>
                <div>
                    <a href="<?php echo $base_url; ?>/register.php" class="btn btn-light text-primary fw-bold">
                        <i class="bi bi-person-plus"></i> Add New User
                    </a>
                    <button class="btn btn-outline-light ms-2" data-bs-toggle="modal" data-bs-target="#importModal">
                        <i class="bi bi-upload"></i> Import
                    </button>
                </div>
            </div>
        </div>
    </div>
    
    <!-- User Stats -->
    <div class="row mb-4">
        <div class="col-md-2 col-6 mb-3">
            <div class="card">
                <div class="card-body text-center py-3">
                    <h3 class="mb-1"><?php echo $stats['total']; ?></h3>
                    <p class="text-muted mb-0 small">Total Users</p>
                </div>
            </div>
        </div>
        
        <div class="col-md-2 col-6 mb-3">
            <div class="card border-danger">
                <div class="card-body text-center py-3">
                    <h3 class="mb-1 text-danger"><?php echo $stats['admins']; ?></h3>
                    <p class="text-muted mb-0 small">Admins</p>
                </div>
            </div>
        </div>
        
        <div class="col-md-2 col-6 mb-3">
            <div class="card border-warning">
                <div class="card-body text-center py-3">
                    <h3 class="mb-1 text-warning"><?php echo $stats['hr']; ?></h3>
                    <p class="text-muted mb-0 small">HR Staff</p>
                </div>
            </div>
        </div>
        
        <div class="col-md-2 col-6 mb-3">
            <div class="card border-primary">
                <div class="card-body text-center py-3">
                    <h3 class="mb-1 text-primary"><?php echo $stats['leaders']; ?></h3>
                    <p class="text-muted mb-0 small">Project Leaders</p>
                </div>
            </div>
        </div>
        
        <div class="col-md-2 col-6 mb-3">
            <div class="card border-success">
                <div class="card-body text-center py-3">
                    <h3 class="mb-1 text-success"><?php echo $stats['employees']; ?></h3>
                    <p class="text-muted mb-0 small">Employees</p>
                </div>
            </div>
        </div>
        
        <div class="col-md-2 col-6 mb-3">
            <div class="card bg-light">
                <div class="card-body text-center py-3">
                    <h3 class="mb-1"><?php echo $stats['active']; ?></h3>
                    <p class="text-muted mb-0 small">Active Users</p>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Users Table -->
    <div class="row">
        <div class="col">
            <div class="card">
                <div class="card-header">
                    <div class="d-flex justify-content-between align-items-center">
                        <h5 class="mb-0">All Users</h5>
                        <div class="input-group" style="width: 300px;">
                            <input type="text" class="form-control" placeholder="Search users..." id="searchUsers">
                            <button class="btn btn-outline-secondary" type="button">
                                <i class="bi bi-search"></i>
                            </button>
                        </div>
                    </div>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover mb-0" id="usersTable">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Employee Info</th>
                                    <th>Role</th>
                                    <th>Department</th>
                                    <th>Salary</th>
                                    <th>Status</th>
                                    <th>Join Date</th>
                                    <th>Last Login</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if ($result->num_rows > 0): ?>
                                    <?php while ($row = $result->fetch_assoc()): 
                                        $status_badges = [
                                            'active' => 'success',
                                            'inactive' => 'secondary',
                                            'suspended' => 'danger'
                                        ];
                                        $status_color = $status_badges[$row['status']] ?? 'secondary';
                                    ?>
                                    <tr>
                                        <td>
                                            <strong><?php echo $row['employee_id']; ?></strong>
                                        </td>
                                        <td>
                                            <div class="d-flex align-items-center">
                                                <div class="user-avatar me-3">
                                                    <?php 
                                                    $name_parts = explode(' ', $row['full_name']);
                                                    $initials = '';
                                                    foreach ($name_parts as $part) {
                                                        $initials .= strtoupper(substr($part, 0, 1));
                                                    }
                                                    echo substr($initials, 0, 2);
                                                    ?>
                                                </div>
                                                <div>
                                                    <strong><?php echo htmlspecialchars($row['full_name']); ?></strong><br>
                                                    <small class="text-muted"><?php echo htmlspecialchars($row['email'] ?? 'N/A'); ?></small><br>
                                                    <small class="text-muted"><?php echo htmlspecialchars($row['username'] ?? 'N/A'); ?></small>
                                                </div>
                                            </div>
                                        </td>
                                        <td>
                                            <?php echo getRoleBadge($row['role']); ?><br>
                                            <small class="text-muted"><?php echo htmlspecialchars($row['position'] ?? 'N/A'); ?></small>
                                        </td>
                                        <td><?php echo htmlspecialchars($row['department'] ?? 'N/A'); ?></td>
                                        <td>
                                            <?php if ($row['salary']): ?>
                                            <strong>$<?php echo number_format($row['salary'], 2); ?></strong><br>
                                            <small class="text-muted">Monthly</small>
                                            <?php else: ?>
                                            <span class="text-muted">Not set</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <span class="badge bg-<?php echo $status_color; ?>">
                                                <?php echo ucfirst($row['status']); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <?php echo $row['hire_date'] ? formatDate($row['hire_date']) : 'N/A'; ?>
                                        </td>
                                        <td>
                                            <?php if ($row['last_login']): ?>
                                            <small><?php echo formatDate($row['last_login'], 'M d'); ?></small><br>
                                            <small class="text-muted"><?php echo date('h:i A', strtotime($row['last_login'])); ?></small>
                                            <?php else: ?>
                                            <span class="text-muted">Never</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <div class="btn-group">
                                                <button type="button" class="btn btn-sm btn-outline-primary" 
                                                        data-bs-toggle="modal" data-bs-target="#editModal<?php echo $row['id']; ?>">
                                                    <i class="bi bi-pencil"></i>
                                                </button>
                                                
                                                <button type="button" class="btn btn-sm btn-outline-secondary dropdown-toggle" 
                                                        data-bs-toggle="dropdown">
                                                    <i class="bi bi-gear"></i>
                                                </button>
                                                <ul class="dropdown-menu">
                                                    <li><a class="dropdown-item" href="#" 
                                                           data-bs-toggle="modal" data-bs-target="#viewModal<?php echo $row['id']; ?>">
                                                        <i class="bi bi-eye me-2"></i>View Details
                                                    </a></li>
                                                    
                                                    <?php if ($row['status'] != 'active'): ?>
                                                    <li>
                                                        <form method="POST" class="m-0" style="display:inline;">
                                                            <input type="hidden" name="action" value="activate">
                                                            <input type="hidden" name="user_id" value="<?php echo $row['id']; ?>">
                                                            <button type="submit" class="dropdown-item">
                                                                <i class="bi bi-check-circle me-2 text-success"></i>Activate
                                                            </button>
                                                        </form>
                                                    </li>
                                                    <?php endif; ?>
                                                    
                                                    <?php if ($row['status'] != 'suspended'): ?>
                                                    <li>
                                                        <form method="POST" class="m-0" style="display:inline;">
                                                            <input type="hidden" name="action" value="suspend">
                                                            <input type="hidden" name="user_id" value="<?php echo $row['id']; ?>">
                                                            <button type="submit" class="dropdown-item">
                                                                <i class="bi bi-slash-circle me-2 text-warning"></i>Suspend
                                                            </button>
                                                        </form>
                                                    </li>
                                                    <?php endif; ?>
                                                    
                                                    <?php if ($row['status'] != 'inactive' && $row['id'] != $_SESSION['user_id']): ?>
                                                    <li>
                                                        <form method="POST" class="m-0" style="display:inline;" onsubmit="return confirm('Are you sure you want to deactivate this user?');">
                                                            <input type="hidden" name="action" value="delete">
                                                            <input type="hidden" name="user_id" value="<?php echo $row['id']; ?>">
                                                            <button type="submit" class="dropdown-item text-danger">
                                                                <i class="bi bi-trash me-2"></i>Deactivate
                                                            </button>
                                                        </form>
                                                    </li>
                                                    <?php endif; ?>
                                                    
                                                    <li><hr class="dropdown-divider"></li>
                                                    
                                                    <li><a class="dropdown-item" href="#">
                                                        <i class="bi bi-key me-2"></i>Reset Password
                                                    </a></li>
                                                </ul>
                                            </div>
                                        </td>
                                    </tr>
                                    
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
                                                                <select class="form-select" name="role" required>
                                                                    <option value="employee" <?php echo $row['role'] == 'employee' ? 'selected' : ''; ?>>Employee</option>
                                                                    <option value="project_leader" <?php echo $row['role'] == 'project_leader' ? 'selected' : ''; ?>>Project Leader</option>
                                                                    <option value="hr" <?php echo $row['role'] == 'hr' ? 'selected' : ''; ?>>HR</option>
                                                                    <option value="admin" <?php echo $row['role'] == 'admin' ? 'selected' : ''; ?>>Admin</option>
                                                                </select>
                                                            </div>
                                                            <div class="col-md-6">
                                                                <label class="form-label">Status</label>
                                                                <select class="form-select" name="status" required>
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
                                    <?php endwhile; ?>
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
</div>

<!-- Import Modal -->
<div class="modal fade" id="importModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form action="import_users.php" method="POST" enctype="multipart/form-data">
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
                        <a href="template.csv" class="btn btn-outline-primary">
                            <i class="bi bi-download"></i> Download Template
                        </a>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Upload CSV File</label>
                        <input type="file" name="csv_file" class="form-control" accept=".csv" required>
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