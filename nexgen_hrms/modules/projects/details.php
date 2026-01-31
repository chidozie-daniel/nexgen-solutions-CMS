<?php
require_once '../../includes/auth.php';
require_once '../../includes/functions.php';

// Check login status first
Auth::requireLogin();

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    setFlash('danger', 'Invalid project ID.');
    header('Location: ' . Auth::getBasePath() . '/modules/projects/index.php');
    exit();
}

$project_id = $_GET['id'];
$conn = getDBConnection();
$user_id = $_SESSION['user_id'];
$role = $_SESSION['role'];

// Get project details
$sql = "SELECT p.*, u.full_name as leader_name 
        FROM projects p 
        LEFT JOIN users u ON p.project_leader = u.id 
        WHERE p.id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $project_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows == 0) {
    setFlash('danger', 'Project not found.');
    header('Location: ' . Auth::getBasePath() . '/modules/projects/index.php');
    exit();
}

$project = $result->fetch_assoc();

// Check if user has access to this project
if ($role == 'employee') {
    $check_sql = "SELECT 1 FROM project_members WHERE project_id = ? AND user_id = ?";
    $check_stmt = $conn->prepare($check_sql);
    $check_stmt->bind_param("ii", $project_id, $user_id);
    $check_stmt->execute();
    $check_result = $check_stmt->get_result();
    
    if ($check_result->num_rows == 0 && $project['project_leader'] != $user_id) {
        setFlash('danger', 'You do not have access to this project.');
        header('Location: ' . Auth::getBasePath() . '/modules/projects/index.php');
        exit();
    }
}

// Get project team members
$team_sql = "SELECT u.id, u.full_name, u.employee_id, u.department, u.position, pm.role, pm.joined_date 
             FROM project_members pm 
             JOIN users u ON pm.user_id = u.id 
             WHERE pm.project_id = ? 
             ORDER BY u.full_name";
$team_stmt = $conn->prepare($team_sql);
$team_stmt->bind_param("i", $project_id);
$team_stmt->execute();
$team_result = $team_stmt->get_result();

// Get project tasks
$tasks_sql = "SELECT t.*, u.full_name as assigned_to_name 
              FROM tasks t 
              LEFT JOIN users u ON t.assigned_to = u.id 
              WHERE t.project_id = ? 
              ORDER BY t.priority DESC, t.due_date ASC";
$tasks_stmt = $conn->prepare($tasks_sql);
$tasks_stmt->bind_param("i", $project_id);
$tasks_stmt->execute();
$tasks_result = $tasks_stmt->get_result();

// Get task statistics
$stats_sql = "SELECT 
    COUNT(*) as total_tasks,
    SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed_tasks,
    SUM(CASE WHEN status = 'in_progress' THEN 1 ELSE 0 END) as in_progress_tasks,
    SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending_tasks,
    AVG(progress) as avg_progress
    FROM tasks 
    WHERE project_id = ?";
$stats_stmt = $conn->prepare($stats_sql);
$stats_stmt->bind_param("i", $project_id);
$stats_stmt->execute();
$stats_result = $stats_stmt->get_result();
$stats = $stats_result->fetch_assoc();

// Calculate project progress
$project_progress = $stats['avg_progress'] ? round($stats['avg_progress']) : 0;

$page_title = 'Project: ' . $project['project_name'];
require_once '../../includes/header.php';
?>
<div class="container-fluid">
    <!-- Project Header -->
    <div class="module-hero">
        <div class="container">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h1 class="mb-2"><?php echo htmlspecialchars($project['project_name']); ?></h1>
                    <p class="mb-0 text-white-50">
                        <?php echo $project['project_code']; ?> | 
                        Project Leader: <?php echo $project['leader_name']; ?>
                    </p>
                </div>
                <div class="btn-group">
                    <a href="<?php echo $base_url; ?>/modules/projects/index.php" class="btn btn-outline-light">
                        <i class="bi bi-arrow-left"></i> Back
                    </a>
                    <?php if ($project['project_leader'] == $user_id || $role == 'hr' || $role == 'admin'): ?>
                    <button class="btn btn-light text-primary fw-bold" data-bs-toggle="modal" data-bs-target="#editModal">
                        <i class="bi bi-pencil"></i> Edit
                    </button>
                    <form action="delete.php" method="POST" class="d-inline" onsubmit="return confirm('Are you sure you want to delete this project?');">
                        <input type="hidden" name="project_id" value="<?php echo $project['id']; ?>">
                        <button type="submit" class="btn btn-outline-light text-danger border-light">
                            <i class="bi bi-trash"></i>
                        </button>
                    </form>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Project Stats -->
    <div class="row mb-4">
        <div class="col-md-3 col-6 mb-3">
            <div class="card">
                <div class="card-body text-center py-3">
                    <h3 class="mb-1"><?php echo $stats['total_tasks'] ?? 0; ?></h3>
                    <p class="text-muted mb-0 small">Total Tasks</p>
                </div>
            </div>
        </div>
        
        <div class="col-md-3 col-6 mb-3">
            <div class="card border-success">
                <div class="card-body text-center py-3">
                    <h3 class="mb-1 text-success"><?php echo $stats['completed_tasks'] ?? 0; ?></h3>
                    <p class="text-muted mb-0 small">Completed</p>
                </div>
            </div>
        </div>
        
        <div class="col-md-3 col-6 mb-3">
            <div class="card border-warning">
                <div class="card-body text-center py-3">
                    <h3 class="mb-1 text-warning"><?php echo $stats['pending_tasks'] ?? 0; ?></h3>
                    <p class="text-muted mb-0 small">Pending</p>
                </div>
            </div>
        </div>
        
        <div class="col-md-3 col-6 mb-3">
            <div class="card bg-light">
                <div class="card-body text-center py-3">
                    <h3 class="mb-1"><?php echo $project_progress; ?>%</h3>
                    <p class="text-muted mb-0 small">Overall Progress</p>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Project Details -->
    <div class="row mb-4">
        <div class="col-md-8">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">Project Information</h5>
                </div>
                <div class="card-body">
                    <div class="row mb-4">
                        <div class="col-md-6">
                            <table class="table table-sm">
                                <tr>
                                    <th width="40%">Project Code:</th>
                                    <td><?php echo $project['project_code']; ?></td>
                                </tr>
                                <tr>
                                    <th>Status:</th>
                                    <td>
                                        <?php 
                                        $status_badges = [
                                            'planning' => 'secondary',
                                            'active' => 'primary',
                                            'on_hold' => 'warning',
                                            'completed' => 'success',
                                            'cancelled' => 'danger'
                                        ];
                                        $status_color = $status_badges[$project['status']] ?? 'secondary';
                                        ?>
                                        <span class="badge bg-<?php echo $status_color; ?>">
                                            <?php echo ucfirst(str_replace('_', ' ', $project['status'])); ?>
                                        </span>
                                    </td>
                                </tr>
                                <tr>
                                    <th>Start Date:</th>
                                    <td><?php echo formatDate($project['start_date']); ?></td>
                                </tr>
                                <tr>
                                    <th>End Date:</th>
                                    <td>
                                        <?php echo formatDate($project['end_date']); ?>
                                        <?php 
                                        if ($project['end_date'] && $project['status'] == 'active') {
                                            $today = new DateTime();
                                            $end_date = new DateTime($project['end_date']);
                                            $interval = $today->diff($end_date);
                                            $days_left = $interval->days;
                                            
                                            if ($interval->invert) {
                                                echo '<span class="badge bg-danger ms-2">Overdue by ' . $days_left . ' days</span>';
                                            } else {
                                                echo '<span class="badge bg-info ms-2">' . $days_left . ' days left</span>';
                                            }
                                        }
                                        ?>
                                    </td>
                                </tr>
                            </table>
                        </div>
                        <div class="col-md-6">
                            <table class="table table-sm">
                                <tr>
                                    <th width="40%">Client:</th>
                                    <td><?php echo $project['client_name'] ?: 'N/A'; ?></td>
                                </tr>
                                <tr>
                                    <th>Budget:</th>
                                    <td>
                                        <?php if ($project['budget']): ?>
                                        $<?php echo number_format($project['budget'], 2); ?>
                                        <?php else: ?>
                                        <span class="text-muted">Not set</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <tr>
                                    <th>Project Leader:</th>
                                    <td><?php echo $project['leader_name']; ?></td>
                                </tr>
                                <tr>
                                    <th>Created:</th>
                                    <td><?php echo formatDate($project['created_at']); ?></td>
                                </tr>
                            </table>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <h6>Project Description</h6>
                        <div class="card bg-light">
                            <div class="card-body">
                                <?php echo nl2br(htmlspecialchars($project['description'])); ?>
                            </div>
                        </div>
                    </div>
                    
                    <?php if ($project['project_leader'] == $user_id || $role == 'hr' || $role == 'admin'): ?>
                    <div class="d-flex gap-2">
                        <button class="btn btn-sm btn-outline-primary" 
                                data-bs-toggle="modal" data-bs-target="#addTaskModal">
                            <i class="bi bi-plus-circle"></i> Add Task
                        </button>
                        <button class="btn btn-sm btn-outline-success" 
                                data-bs-toggle="modal" data-bs-target="#addMemberModal">
                            <i class="bi bi-person-plus"></i> Add Team Member
                        </button>
                                <a href="<?php echo $base_url; ?>/modules/tasks/assign.php?project_id=<?php echo $project_id; ?>" 
                                    class="btn btn-sm btn-outline-info">
                            <i class="bi bi-list-task"></i> Assign Tasks
                        </a>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <div class="col-md-4">
            <!-- Progress Card -->
            <div class="card mb-4">
                <div class="card-header">
                    <h6 class="mb-0">Project Progress</h6>
                </div>
                <div class="card-body text-center">
                    <div class="mb-3">
                        <div class="progress" style="height: 20px;">
                            <div class="progress-bar 
                                <?php 
                                if ($project_progress >= 80) echo 'bg-success';
                                elseif ($project_progress >= 50) echo 'bg-primary';
                                else echo 'bg-warning';
                                ?>" 
                                style="width: <?php echo $project_progress; ?>%">
                                <?php echo $project_progress; ?>%
                            </div>
                        </div>
                    </div>
                    <div class="row text-center small">
                        <div class="col-4">
                            <div class="fw-bold"><?php echo $stats['total_tasks'] ?? 0; ?></div>
                            <div class="text-muted">Total</div>
                        </div>
                        <div class="col-4">
                            <div class="fw-bold"><?php echo $stats['completed_tasks'] ?? 0; ?></div>
                            <div class="text-muted">Done</div>
                        </div>
                        <div class="col-4">
                            <div class="fw-bold"><?php echo $stats['pending_tasks'] ?? 0; ?></div>
                            <div class="text-muted">Pending</div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Quick Actions -->
            <div class="card">
                <div class="card-header">
                    <h6 class="mb-0">Quick Actions</h6>
                </div>
                <div class="card-body">
                    <div class="list-group list-group-flush">
                                <a href="<?php echo $base_url; ?>/modules/tasks/my_tasks.php?project=<?php echo $project_id; ?>" 
                                    class="list-group-item list-group-item-action">
                            <i class="bi bi-list-task me-2"></i>View All Tasks
                        </a>
                        <a href="#" class="list-group-item list-group-item-action" 
                           data-bs-toggle="modal" data-bs-target="#reportModal">
                            <i class="bi bi-file-earmark-text me-2"></i>Generate Report
                        </a>
                        <a href="mailto:?subject=Project Update: <?php echo urlencode($project['project_name']); ?>" 
                           class="list-group-item list-group-item-action">
                            <i class="bi bi-envelope me-2"></i>Email Team
                        </a>
                        <a href="#" class="list-group-item list-group-item-action">
                            <i class="bi bi-calendar-event me-2"></i>Schedule Meeting
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Team Members -->
    <div class="row mb-4">
        <div class="col">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">Team Members</h5>
                    <span class="badge bg-primary"><?php echo $team_result->num_rows; ?> members</span>
                </div>
                <div class="card-body">
                    <?php if ($team_result->num_rows > 0): ?>
                    <div class="row">
                        <?php while ($member = $team_result->fetch_assoc()): ?>
                        <div class="col-md-3 col-6 mb-3">
                            <div class="card h-100 relative-container">
                                <div class="card-body text-center">
                                    <?php if (($project['project_leader'] == $user_id || $role == 'hr' || $role == 'admin') && $member['id'] != $project['project_leader']): ?>
                                    <form action="remove_member.php" method="POST" class="position-absolute top-0 end-0 m-2" onsubmit="return confirm('Remove this member from the project?');">
                                        <input type="hidden" name="project_id" value="<?php echo $project_id; ?>">
                                        <input type="hidden" name="user_id" value="<?php echo $member['id']; ?>">
                                        <button type="submit" class="btn btn-sm btn-link text-danger p-0">
                                            <i class="bi bi-x-circle-fill fs-5"></i>
                                        </button>
                                    </form>
                                    <?php endif; ?>
                                    
                                    <div class="user-avatar mx-auto mb-3">
                                        <?php 
                                        $name_parts = explode(' ', $member['full_name']);
                                        $initials = '';
                                        foreach ($name_parts as $part) {
                                            $initials .= strtoupper(substr($part, 0, 1));
                                        }
                                        echo substr($initials, 0, 2);
                                        ?>
                                    </div>
                                    <h6 class="mb-1"><?php echo $member['full_name']; ?></h6>
                                    <p class="text-muted small mb-1"><?php echo $member['position']; ?></p>
                                    <p class="text-muted small mb-2"><?php echo $member['employee_id']; ?></p>
                                    <span class="badge bg-info"><?php echo $member['department']; ?></span>
                                </div>
                                <div class="card-footer text-center py-2">
                                    <small class="text-muted">
                                        Joined: <?php echo formatDate($member['joined_date']); ?>
                                    </small>
                                </div>
                            </div>
                        </div>
                        <?php endwhile; ?>
                    </div>
                    <?php else: ?>
                    <div class="text-center py-4">
                        <i class="bi bi-people h1 text-muted d-block mb-3"></i>
                        <p class="text-muted">No team members added yet.</p>
                        <?php if ($project['project_leader'] == $user_id || $role == 'hr' || $role == 'admin'): ?>
                        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addMemberModal">
                            <i class="bi bi-person-plus"></i> Add Team Members
                        </button>
                        <?php endif; ?>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Project Tasks -->
    <div class="row">
        <div class="col">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">Project Tasks</h5>
                          <a href="<?php echo $base_url; ?>/modules/tasks/assign.php?project_id=<?php echo $project_id; ?>" 
                              class="btn btn-sm btn-primary">
                        <i class="bi bi-plus-circle"></i> Assign New Task
                    </a>
                </div>
                <div class="card-body p-0">
                    <?php if ($tasks_result->num_rows > 0): ?>
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead>
                                <tr>
                                    <th>Task</th>
                                    <th>Assigned To</th>
                                    <th>Priority</th>
                                    <th>Due Date</th>
                                    <th>Progress</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php while ($task = $tasks_result->fetch_assoc()): 
                                    $is_overdue = ($task['due_date'] && $task['due_date'] < date('Y-m-d') && $task['status'] != 'completed');
                                ?>
                                <tr class="<?php echo $is_overdue ? 'table-danger' : ''; ?>">
                                    <td>
                                        <strong><?php echo htmlspecialchars($task['title']); ?></strong><br>
                                        <small class="text-muted"><?php echo htmlspecialchars(substr($task['description'] ?? '', 0, 50)); ?>...</small>
                                    </td>
                                    <td><?php echo htmlspecialchars($task['assigned_to_name'] ?? 'N/A'); ?></td>
                                    <td>
                                        <?php 
                                        $priority_badges = [
                                            'low' => 'secondary',
                                            'medium' => 'primary',
                                            'high' => 'warning',
                                            'critical' => 'danger'
                                        ];
                                        $badge_color = $priority_badges[$task['priority']] ?? 'secondary';
                                        ?>
                                        <span class="badge bg-<?php echo $badge_color; ?>">
                                            <?php echo ucfirst($task['priority']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php if ($task['due_date']): ?>
                                        <span class="<?php echo $is_overdue ? 'text-danger fw-bold' : ''; ?>">
                                            <?php echo formatDate($task['due_date']); ?>
                                        </span>
                                        <?php if ($is_overdue): ?>
                                        <br><small class="badge bg-danger">Overdue</small>
                                        <?php endif; ?>
                                        <?php else: ?>
                                        <span class="text-muted">Not set</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <div class="progress" style="height: 15px;">
                                            <div class="progress-bar 
                                                <?php 
                                                if ($task['progress'] >= 100) echo 'bg-success';
                                                elseif ($task['progress'] >= 50) echo 'bg-primary';
                                                else echo 'bg-warning';
                                                ?>" 
                                                style="width: <?php echo $task['progress']; ?>%">
                                                <?php echo $task['progress']; ?>%
                                            </div>
                                        </div>
                                    </td>
                                    <td><?php echo getStatusBadge($task['status']); ?></td>
                                    <td>
                                    <td>
                                        <div class="btn-group">
                                            <a href="<?php echo $base_url; ?>/modules/tasks/view.php?id=<?php echo $task['id']; ?>" 
                                                class="btn btn-sm btn-outline-primary" title="View Task">
                                                <i class="bi bi-eye"></i>
                                            </a>
                                            <?php if ($project['project_leader'] == $user_id || $role == 'hr' || $role == 'admin'): ?>
                                            <form action="delete_task.php" method="POST" class="d-inline" onsubmit="return confirm('Are you sure you want to delete this task?');">
                                                <input type="hidden" name="task_id" value="<?php echo $task['id']; ?>">
                                                <input type="hidden" name="project_id" value="<?php echo $project['id']; ?>">
                                                <button type="submit" class="btn btn-sm btn-outline-danger" title="Delete Task">
                                                    <i class="bi bi-trash"></i>
                                                </button>
                                            </form>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php else: ?>
                    <div class="text-center py-4">
                        <i class="bi bi-list-task h1 text-muted d-block mb-3"></i>
                        <p class="text-muted">No tasks assigned to this project yet.</p>
                                <a href="<?php echo $base_url; ?>/modules/tasks/assign.php?project_id=<?php echo $project_id; ?>" 
                                    class="btn btn-primary">
                            <i class="bi bi-plus-circle"></i> Assign First Task
                        </a>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Edit Project Modal -->
<div class="modal fade" id="editModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form method="POST" action="update.php">
                <input type="hidden" name="project_id" value="<?php echo $project_id; ?>">
                <div class="modal-header">
                    <h5 class="modal-title">Edit Project</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Project Name</label>
                            <input type="text" class="form-control" name="project_name" 
                                   value="<?php echo htmlspecialchars($project['project_name']); ?>" required>
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Project Code</label>
                            <input type="text" class="form-control" name="project_code" 
                                   value="<?php echo $project['project_code']; ?>" required>
                        </div>
                        
                        <div class="col-md-12 mb-3">
                            <label class="form-label">Description</label>
                            <textarea class="form-control" name="description" rows="4"><?php echo htmlspecialchars($project['description']); ?></textarea>
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Client Name</label>
                            <input type="text" class="form-control" name="client_name" 
                                   value="<?php echo htmlspecialchars($project['client_name']); ?>">
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Budget ($)</label>
                            <input type="number" step="0.01" class="form-control" name="budget" 
                                   value="<?php echo $project['budget']; ?>">
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Start Date</label>
                            <input type="date" class="form-control" name="start_date" 
                                   value="<?php echo $project['start_date']; ?>" required>
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label class="form-label">End Date</label>
                            <input type="date" class="form-control" name="end_date" 
                                   value="<?php echo $project['end_date']; ?>" required>
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Status</label>
                            <select class="form-select" name="status" required>
                                <option value="planning" <?php echo $project['status'] == 'planning' ? 'selected' : ''; ?>>Planning</option>
                                <option value="active" <?php echo $project['status'] == 'active' ? 'selected' : ''; ?>>Active</option>
                                <option value="on_hold" <?php echo $project['status'] == 'on_hold' ? 'selected' : ''; ?>>On Hold</option>
                                <option value="completed" <?php echo $project['status'] == 'completed' ? 'selected' : ''; ?>>Completed</option>
                                <option value="cancelled" <?php echo $project['status'] == 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                            </select>
                        </div>
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

<!-- Add Task Modal -->
<div class="modal fade" id="addTaskModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" action="<?php echo $base_url; ?>/modules/tasks/assign.php">
                <input type="hidden" name="project_id" value="<?php echo $project_id; ?>">
                <div class="modal-header">
                    <h5 class="modal-title">Add New Task</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Task Title</label>
                        <input type="text" class="form-control" name="title" required>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Description</label>
                        <textarea class="form-control" name="description" rows="3"></textarea>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Assign To</label>
                            <select class="form-select" name="assigned_to" required>
                                <option value="">Select Team Member</option>
                                <?php 
                                $team_result->data_seek(0);
                                while ($member = $team_result->fetch_assoc()):
                                ?>
                                <option value="<?php echo $member['id']; ?>">
                                    <?php echo $member['full_name']; ?>
                                </option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Priority</label>
                            <select class="form-select" name="priority">
                                <option value="medium">Medium</option>
                                <option value="low">Low</option>
                                <option value="high">High</option>
                                <option value="critical">Critical</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Due Date</label>
                        <input type="date" class="form-control" name="due_date" 
                               min="<?php echo date('Y-m-d'); ?>">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Add Task</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Add Member Modal -->
<div class="modal fade" id="addMemberModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" action="add_member.php">
                <input type="hidden" name="project_id" value="<?php echo $project_id; ?>">
                <div class="modal-header">
                    <h5 class="modal-title">Add Team Member</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Select Employee</label>
                        <select class="form-select" name="user_id" required>
                            <option value="">Choose employee...</option>
                            <?php
                            $all_employees_sql = "SELECT id, full_name, employee_id, department 
                                                FROM users 
                                                WHERE status = 'active' 
                                                AND role IN ('employee', 'project_leader')
                                                AND id NOT IN (
                                                    SELECT user_id FROM project_members WHERE project_id = ?
                                                )
                                                ORDER BY full_name";
                            $all_employees_stmt = $conn->prepare($all_employees_sql);
                            $all_employees_stmt->bind_param("i", $project_id);
                            $all_employees_stmt->execute();
                            $all_employees_result = $all_employees_stmt->get_result();
                            
                            while ($emp = $all_employees_result->fetch_assoc()):
                            ?>
                            <option value="<?php echo $emp['id']; ?>">
                                <?php echo $emp['full_name']; ?> (<?php echo $emp['employee_id']; ?>) - <?php echo $emp['department']; ?>
                            </option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Role in Project</label>
                        <select class="form-select" name="role">
                            <option value="member">Team Member</option>
                            <option value="team_lead">Team Lead</option>
                            <option value="contributor">Contributor</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Add to Project</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Report Modal -->
<div class="modal fade" id="reportModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Generate Project Report</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label class="form-label">Report Type</label>
                    <select class="form-select" id="reportType">
                        <option value="summary">Project Summary</option>
                        <option value="tasks">Task Progress Report</option>
                        <option value="team">Team Performance Report</option>
                        <option value="financial">Financial Report</option>
                    </select>
                </div>
                
                <div class="mb-3">
                    <label class="form-label">Format</label>
                    <div class="row">
                        <div class="col-4">
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="format" id="formatPdf" checked>
                                <label class="form-check-label" for="formatPdf">PDF</label>
                            </div>
                        </div>
                        <div class="col-4">
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="format" id="formatExcel">
                                <label class="form-check-label" for="formatExcel">Excel</label>
                            </div>
                        </div>
                        <div class="col-4">
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="format" id="formatWord">
                                <label class="form-check-label" for="formatWord">Word</label>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="mb-3">
                    <label class="form-label">Include Data From</label>
                    <input type="date" class="form-control" value="<?php echo $project['start_date']; ?>">
                    <small class="text-muted">Start date</small>
                </div>
                
                <div class="mb-3">
                    <label class="form-label">To</label>
                    <input type="date" class="form-control" value="<?php echo date('Y-m-d'); ?>">
                    <small class="text-muted">End date</small>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary">Generate Report</button>
            </div>
        </div>
    </div>
</div>

<script>
    // Initialize date pickers
    document.addEventListener('DOMContentLoaded', function() {
        // Set minimum dates
        const today = new Date().toISOString().split('T')[0];
        document.querySelectorAll('input[type="date"][min]').forEach(input => {
            if (!input.min) {
                input.min = today;
            }
        });
    });
</script>

<?php require_once '../../includes/footer.php'; ?>