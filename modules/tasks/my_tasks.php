<?php
// Define entry point constant
define('APP_ENTRY_POINT', true);

require_once '../../includes/auth.php';
require_once '../../includes/functions.php';

// Check login status first
Auth::requireLogin();

$page_title = 'My Tasks';
$conn = getDBConnection();
$user_id = $_SESSION['user_id'];
$role = $_SESSION['role'];

// Update task progress
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_progress'])) {
    // Verify CSRF token
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        setFlash('danger', 'Invalid security token. Please try again.');
        header('Location: my_tasks.php');
        exit();
    }

    $task_id = $_POST['task_id'] ?? '';
    $progress = $_POST['progress'] ?? '';
    $status = $_POST['status'] ?? '';

    $errors = [];
    if (!is_numeric($task_id) || (int)$task_id <= 0) {
        $errors[] = 'Invalid task.';
    }
    if (!is_numeric($progress) || (int)$progress < 0 || (int)$progress > 100) {
        $errors[] = 'Progress must be between 0 and 100.';
    }
    $allowed_status = ['pending', 'in_progress', 'review', 'completed'];
    if (!in_array($status, $allowed_status, true)) {
        $errors[] = 'Invalid status selected.';
    }

    // Verify user has permission to update this task
    $check_sql = "SELECT assigned_to, assigned_by FROM tasks WHERE id = ?";
    $check_stmt = $conn->prepare($check_sql);
    $check_stmt->bind_param("i", $task_id);
    $check_stmt->execute();
    $task_check = $check_stmt->get_result()->fetch_assoc();
    
    $can_update = false;
    if ($task_check) {
        // Assignee can update their own tasks
        if ($task_check['assigned_to'] == $user_id) {
            $can_update = true;
        }
        // Assigner (PL/Admin/HR) can update tasks they assigned
        if ($task_check['assigned_by'] == $user_id) {
            $can_update = true;
        }
        // HR and Admin can update any task
        if (Auth::hasRole('hr') || Auth::hasRole('admin')) {
            $can_update = true;
        }
        // Project Leader can update if they lead the project
        if (Auth::hasRole('project_leader')) {
            $proj_sql = "SELECT p.project_leader
                         FROM projects p
                         JOIN tasks t ON p.id = t.project_id
                         WHERE t.id = ? AND p.project_leader = ?";
            $proj_stmt = $conn->prepare($proj_sql);
            $proj_stmt->bind_param("ii", $task_id, $user_id);
            $proj_stmt->execute();
            $proj_check = $proj_stmt->get_result();
            if ($proj_check && $proj_check->num_rows > 0) {
                $can_update = true;
            }
        }
    }
    
    if (!$can_update) {
        $errors[] = 'You do not have permission to update this task.';
    }

    if (!empty($errors)) {
        setFlash('danger', implode('<br>', $errors));
        header('Location: ' . Auth::getBasePath() . '/modules/tasks/my_tasks.php');
        exit();
    }

    $sql = "UPDATE tasks SET progress = ?, status = ?, updated_at = NOW()";

    if ($status == 'completed') {
        $sql .= ", completion_date = CURDATE()";
    }

    $sql .= " WHERE id = ?";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param("isi", $progress, $status, $task_id);

    if ($stmt->execute()) {
        // Get task details for notification
        $task_sql = "SELECT t.*, u.full_name as assigner_name 
                     FROM tasks t 
                     JOIN users u ON t.assigned_by = u.id 
                     WHERE t.id = ?";
        $task_stmt = $conn->prepare($task_sql);
        $task_stmt->bind_param("i", $task_id);
        $task_stmt->execute();
        $task_data = $task_stmt->get_result()->fetch_assoc();
        
        // Log activity
        logActivity('TASK_PROGRESS', 'Task progress updated to ' . $progress . '%', 'tasks', $task_id);
        
        // Notify task assigner if progress changed significantly or completed
        if ($task_data && $task_data['assigned_by'] != $user_id) {
            $notification_msg = 'Task "' . $task_data['title'] . '" progress updated to ' . $progress . '%.';
            if ($status == 'completed') {
                $notification_msg = 'Task "' . $task_data['title'] . '" has been COMPLETED!';
                createNotification($task_data['assigned_by'],
                    'Task Completed! ✓',
                    $notification_msg,
                    'success',
                    'task',
                    '/modules/tasks/view.php?id=' . $task_id,
                    $user_id
                );
            } elseif ($progress >= 100 || $progress % 25 == 0) {
                // Notify on milestones (25%, 50%, 75%, 100%)
                createNotification($task_data['assigned_by'],
                    'Task Progress Update',
                    $notification_msg,
                    'info',
                    'task',
                    '/modules/tasks/view.php?id=' . $task_id,
                    $user_id
                );
            }
        }
        
        setFlash('success', 'Task progress updated successfully!');
    }
    header('Location: ' . Auth::getBasePath() . '/modules/tasks/my_tasks.php');
    exit();
}

// Get tasks based on role
if ($role == 'project_leader' || $role == 'hr' || $role == 'admin') {
    $sql = "SELECT t.*, 
            u1.full_name as assigned_to_name,
            u2.full_name as assigned_by_name,
            p.project_name
            FROM tasks t
            LEFT JOIN users u1 ON t.assigned_to = u1.id
            LEFT JOIN users u2 ON t.assigned_by = u2.id
            LEFT JOIN projects p ON t.project_id = p.id
            WHERE t.assigned_by = ? OR t.assigned_to = ?
            ORDER BY t.due_date ASC";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ii", $user_id, $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
} else {
    $sql = "SELECT t.*, 
            u.full_name as assigned_by_name,
            p.project_name
            FROM tasks t
            LEFT JOIN users u ON t.assigned_by = u.id
            LEFT JOIN projects p ON t.project_id = p.id
            WHERE t.assigned_to = ?
            ORDER BY 
                CASE 
                    WHEN t.priority = 'critical' THEN 1
                    WHEN t.priority = 'high' THEN 2
                    WHEN t.priority = 'medium' THEN 3
                    ELSE 4
                END,
                t.due_date ASC";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
}

$page_title = 'My Tasks';
require_once '../../includes/header.php';
?>

<div class="container-fluid">
    <div class="module-hero">
        <div class="container">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h1 class="mb-2">My Tasks</h1>
                    <p class="mb-0">Track and manage your assigned tasks</p>
                </div>
                <?php if ($role == 'project_leader' || $role == 'hr' || $role == 'admin'): ?>
                <a href="<?php echo $base_url; ?>/modules/tasks/assign.php" class="btn btn-warning">
                    <i class="bi bi-plus-circle"></i> Assign Task
                </a>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <!-- Task Stats -->
    <div class="row mb-4">
        <?php
        if ($role == 'project_leader' || $role == 'hr' || $role == 'admin') {
            $stats_sql = "SELECT 
                COUNT(*) as total,
                SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending,
                SUM(CASE WHEN status = 'in_progress' THEN 1 ELSE 0 END) as in_progress,
                SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed,
                SUM(CASE WHEN due_date < CURDATE() AND status != 'completed' THEN 1 ELSE 0 END) as overdue
                FROM tasks 
                WHERE assigned_to = ? OR assigned_by = ?";
            $stats_stmt = $conn->prepare($stats_sql);
            $stats_stmt->bind_param("ii", $user_id, $user_id);
        } else {
            $stats_sql = "SELECT 
                COUNT(*) as total,
                SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending,
                SUM(CASE WHEN status = 'in_progress' THEN 1 ELSE 0 END) as in_progress,
                SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed,
                SUM(CASE WHEN due_date < CURDATE() AND status != 'completed' THEN 1 ELSE 0 END) as overdue
                FROM tasks 
                WHERE assigned_to = ?";
            $stats_stmt = $conn->prepare($stats_sql);
            $stats_stmt->bind_param("i", $user_id);
        }
        
        $stats_stmt->execute();
        $stats_result = $stats_stmt->get_result();
        $stats = $stats_result->fetch_assoc();
        ?>
        
        <div class="col-md-2 col-6 mb-3">
            <div class="stat-card">
                <div class="stat-icon">
                    <i class="bi bi-list-task"></i>
                </div>
                <h3 class="mb-2"><?php echo $stats['total']; ?></h3>
                <p class="text-muted mb-0">Total Tasks</p>
            </div>
        </div>
        
        <div class="col-md-2 col-6 mb-3">
            <div class="stat-card warning">
                <div class="stat-icon">
                    <i class="bi bi-clock"></i>
                </div>
                <h3 class="mb-2"><?php echo $stats['pending']; ?></h3>
                <p class="text-muted mb-0">Pending</p>
            </div>
        </div>
        
        <div class="col-md-2 col-6 mb-3">
            <div class="stat-card">
                <div class="stat-icon">
                    <i class="bi bi-play-circle"></i>
                </div>
                <h3 class="mb-2"><?php echo $stats['in_progress']; ?></h3>
                <p class="text-muted mb-0">In Progress</p>
            </div>
        </div>
        
        <div class="col-md-2 col-6 mb-3">
            <div class="stat-card success">
                <div class="stat-icon">
                    <i class="bi bi-check-circle"></i>
                </div>
                <h3 class="mb-2"><?php echo $stats['completed']; ?></h3>
                <p class="text-muted mb-0">Completed</p>
            </div>
        </div>
        
        <div class="col-md-2 col-6 mb-3">
            <div class="stat-card danger">
                <div class="stat-icon">
                    <i class="bi bi-exclamation-triangle"></i>
                </div>
                <h3 class="mb-2"><?php echo $stats['overdue']; ?></h3>
                <p class="text-muted mb-0">Overdue</p>
            </div>
        </div>
        
        <div class="col-md-2 col-6 mb-3">
            <div class="stat-card info">
                <div class="stat-icon">
                    <i class="bi bi-bar-chart"></i>
                </div>
                <?php
                $avg_progress_sql = "SELECT AVG(progress) as avg_progress FROM tasks WHERE assigned_to = ? AND status != 'completed'";
                $avg_stmt = $conn->prepare($avg_progress_sql);
                $avg_stmt->bind_param("i", $user_id);
                $avg_stmt->execute();
                $avg_result = $avg_stmt->get_result();
                $avg_progress = round($avg_result->fetch_assoc()['avg_progress'] ?? 0, 1);
                ?>
                <h3 class="mb-2"><?php echo $avg_progress; ?>%</h3>
                <p class="text-muted mb-0">Avg Progress</p>
            </div>
        </div>
    </div>
    
    <!-- Tasks Table -->
    <div class="row">
        <div class="col-12">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white border-0 py-3">
                    <div class="d-flex justify-content-between align-items-center">
                        <h5 class="mb-0 fw-bold"><i class="bi bi-kanban-fill text-primary me-2"></i>Your Tasks</h5>
                        <div class="d-flex gap-2">
                            <input type="text" class="form-control form-control-sm" id="searchTasks" placeholder="🔍 Search tasks..." style="width: 200px;">
                            <select class="form-select form-select-sm" id="filterPriority" style="width: auto;">
                                <option value="">All Priorities</option>
                                <option value="critical">Critical</option>
                                <option value="high">High</option>
                                <option value="medium">Medium</option>
                                <option value="low">Low</option>
                            </select>
                            <select class="form-select form-select-sm" id="filterStatus" style="width: auto;">
                                <option value="">All Status</option>
                                <option value="pending">Pending</option>
                                <option value="in_progress">In Progress</option>
                                <option value="review">Review</option>
                                <option value="completed">Completed</option>
                            </select>
                        </div>
                    </div>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0" id="tasksTable">
                            <thead class="bg-light">
                                <tr>
                                    <th class="ps-4">Task</th>
                                    <th>Project</th>
                                    <th>Priority</th>
                                    <th>Due Date</th>
                                    <th style="width: 150px;">Progress</th>
                                    <th>Status</th>
                                    <th class="text-end pe-4">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if ($result->num_rows > 0): ?>
                                    <?php 
                                    $task_count = 0;
                                    while ($row = $result->fetch_assoc()): 
                                        $task_count++;
                                        $is_overdue = ($row['due_date'] && strtotime($row['due_date']) < time() && $row['status'] != 'completed');
                                        $priority_colors = [
                                            'low' => 'secondary',
                                            'medium' => 'primary',
                                            'high' => 'warning',
                                            'critical' => 'danger'
                                        ];
                                        $progress_color = $row['progress'] >= 100 ? 'success' : ($row['progress'] >= 50 ? 'primary' : 'warning');
                                    ?>
                                    <tr class="<?php echo $is_overdue ? 'table-danger' : ''; ?>" 
                                        data-priority="<?php echo $row['priority']; ?>" 
                                        data-status="<?php echo $row['status']; ?>">
                                        <td class="ps-4">
                                            <div class="d-flex flex-column">
                                                <a href="view.php?id=<?php echo $row['id']; ?>" class="text-decoration-none fw-semibold text-dark">
                                                    <?php echo htmlspecialchars($row['title']); ?>
                                                </a>
                                                <small class="text-muted text-truncate" style="max-width: 400px;">
                                                    <?php echo htmlspecialchars(substr($row['description'] ?? '', 0, 80)); ?>...
                                                </small>
                                                <div class="d-flex gap-2 mt-1">
                                                    <span class="badge bg-light text-dark">
                                                        <i class="bi bi-person me-1"></i><?php echo htmlspecialchars($row['assigned_by_name'] ?? 'N/A'); ?>
                                                    </span>
                                                    <?php if ($is_overdue): ?>
                                                    <span class="badge bg-danger">
                                                        <i class="bi bi-exclamation-circle me-1"></i>Overdue
                                                    </span>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        </td>
                                        <td>
                                            <?php if ($row['project_name']): ?>
                                            <span class="badge bg-info bg-opacity-10 text-info border border-info border-opacity-25">
                                                <i class="bi bi-folder me-1"></i><?php echo htmlspecialchars($row['project_name']); ?>
                                            </span>
                                            <?php else: ?>
                                            <span class="text-muted">—</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <span class="badge bg-<?php echo $priority_colors[$row['priority']] ?? 'secondary'; ?>">
                                                <i class="bi bi-flag-fill me-1"></i><?php echo ucfirst($row['priority']); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <div class="d-flex flex-column">
                                                <span class="<?php echo $is_overdue ? 'text-danger fw-bold' : 'text-dark'; ?>">
                                                    <?php echo $row['due_date'] ? date('M d, Y', strtotime($row['due_date'])) : 'Not set'; ?>
                                                </span>
                                                <?php if ($is_overdue): ?>
                                                <small class="text-danger fw-bold">
                                                    <i class="bi bi-clock"></i>
                                                    <?php echo floor((time() - strtotime($row['due_date'])) / 86400); ?> days late
                                                </small>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                        <td>
                                            <div class="d-flex align-items-center gap-2">
                                                <div class="progress flex-grow-1" style="height: 8px;">
                                                    <div class="progress-bar bg-<?php echo $progress_color; ?>" 
                                                         role="progressbar" 
                                                         style="width: <?php echo $row['progress']; ?>%" 
                                                         aria-valuenow="<?php echo $row['progress']; ?>" 
                                                         aria-valuemin="0" 
                                                         aria-valuemax="100">
                                                    </div>
                                                </div>
                                                <span class="text-muted small fw-medium" style="min-width: 35px;">
                                                    <?php echo $row['progress']; ?>%
                                                </span>
                                            </div>
                                        </td>
                                        <td>
                                            <?php echo getStatusBadge($row['status']); ?>
                                        </td>
                                        <td class="text-end pe-4">
                                            <div class="btn-group" role="group">
                                                <a href="view.php?id=<?php echo $row['id']; ?>" 
                                                   class="btn btn-sm btn-outline-primary" 
                                                   data-bs-toggle="tooltip" 
                                                   title="View Details">
                                                    <i class="bi bi-eye"></i>
                                                </a>
                                                <?php if ($row['assigned_to'] == $user_id && $row['status'] != 'completed'): ?>
                                                <button type="button"
                                                        class="btn btn-sm btn-outline-success"
                                                        data-bs-toggle="modal"
                                                        data-bs-target="#updateModal<?php echo $row['id']; ?>"
                                                        title="Update Progress">
                                                    <i class="bi bi-pencil"></i>
                                                </button>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                    </tr>

                                    <!-- Update Progress Modal -->
                                    <?php if ($row['assigned_to'] == $user_id && $row['status'] != 'completed'): ?>
                                    <div class="modal fade" id="updateModal<?php echo $row['id']; ?>" tabindex="-1">
                                        <div class="modal-dialog">
                                            <div class="modal-content">
                                                <form action="my_tasks.php" method="POST">
                                                    <?php echo csrfField(); ?>
                                                    <input type="hidden" name="update_progress" value="1">
                                                    <input type="hidden" name="task_id" value="<?php echo $row['id']; ?>">
                                                    <div class="modal-header">
                                                        <h5 class="modal-title">Update Task Progress</h5>
                                                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                                    </div>
                                                    <div class="modal-body">
                                                        <div class="mb-3">
                                                            <label class="form-label">Task: <?php echo htmlspecialchars($row['title']); ?></label>
                                                        </div>
                                                        <div class="mb-3">
                                                            <label for="progress<?php echo $row['id']; ?>" class="form-label">Progress: <span id="progressVal<?php echo $row['id']; ?>"><?php echo $row['progress']; ?></span>%</label>
                                                            <input type="range" class="form-range" name="progress" id="progress<?php echo $row['id']; ?>" 
                                                                   min="0" max="100" step="5" value="<?php echo $row['progress']; ?>"
                                                                   oninput="document.getElementById('progressVal<?php echo $row['id']; ?>').innerText = this.value">
                                                        </div>
                                                        <div class="mb-3">
                                                            <label class="form-label">Status</label>
                                                            <select class="form-select" name="status">
                                                                <option value="pending" <?php echo $row['status'] == 'pending' ? 'selected' : ''; ?>>Pending</option>
                                                                <option value="in_progress" <?php echo $row['status'] == 'in_progress' ? 'selected' : ''; ?>>In Progress</option>
                                                                <option value="review" <?php echo $row['status'] == 'review' ? 'selected' : ''; ?>>Ready for Review</option>
                                                                <option value="completed" <?php echo $row['status'] == 'completed' ? 'selected' : ''; ?>>Completed</option>
                                                            </select>
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
                                    <?php endif; ?>
                                    
                                    <?php endwhile; ?>
                                <?php else: ?>
                                <tr>
                                    <td colspan="7" class="text-center py-5">
                                        <div class="mb-3">
                                            <i class="bi bi-clipboard-check display-1 text-muted"></i>
                                        </div>
                                        <h5 class="text-muted">No tasks found</h5>
                                        <p class="text-muted mb-3">You don't have any tasks assigned yet.</p>
                                        <?php if ($role == 'project_leader' || $role == 'hr' || $role == 'admin'): ?>
                                        <a href="assign.php" class="btn btn-primary">
                                            <i class="bi bi-plus-circle me-2"></i>Assign New Task
                                        </a>
                                        <?php endif; ?>
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

<!-- Filter Script -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Initialize tooltips
    var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl);
    });
    
    // Filter functionality
    const priorityFilter = document.getElementById('filterPriority');
    const statusFilter = document.getElementById('filterStatus');
    
    function filterTasks() {
        const selectedPriority = priorityFilter.value.toLowerCase();
        const selectedStatus = statusFilter.value.toLowerCase();
        const rows = document.querySelectorAll('#tasksTable tbody tr[data-priority]');
        
        rows.forEach(row => {
            const taskPriority = row.dataset.priority.toLowerCase();
            const taskStatus = row.dataset.status.toLowerCase();
            
            const priorityMatch = !selectedPriority || taskPriority === selectedPriority;
            const statusMatch = !selectedStatus || taskStatus === selectedStatus;
            
            row.style.display = (priorityMatch && statusMatch) ? '' : 'none';
        });
    }
    
    priorityFilter.addEventListener('change', filterTasks);
    statusFilter.addEventListener('change', filterTasks);

    // Task search functionality
    const searchInput = document.getElementById('searchTasks');
    if (searchInput) {
        searchInput.addEventListener('input', function() {
            const searchTerm = this.value.toLowerCase();
            const rows = document.querySelectorAll('#tasksTable tbody tr');

            rows.forEach(row => {
                const taskTitle = row.querySelector('td:first-child a')?.textContent.toLowerCase() || '';
                const taskDesc = row.querySelector('td:first-child small')?.textContent.toLowerCase() || '';
                const projectName = row.querySelector('td:nth-child(2) span')?.textContent.toLowerCase() || '';

                const matchesSearch = !searchTerm || 
                    taskTitle.includes(searchTerm) || 
                    taskDesc.includes(searchTerm) || 
                    projectName.includes(searchTerm);

                const priorityMatch = !selectedPriority || row.dataset.priority.toLowerCase() === selectedPriority;
                const statusMatch = !selectedStatus || row.dataset.status.toLowerCase() === selectedStatus;

                row.style.display = (matchesSearch && priorityMatch && statusMatch) ? '' : 'none';
            });
        });
    }
});
</script>

<?php require_once '../../includes/footer.php'; ?>
