<?php
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
    $task_id = $_POST['task_id'];
    $progress = $_POST['progress'];
    $status = $_POST['status'];
    
    $sql = "UPDATE tasks SET progress = ?, status = ?, updated_at = NOW()";
    
    if ($status == 'completed') {
        $sql .= ", completion_date = CURDATE()";
    }
    
    $sql .= " WHERE id = ? AND assigned_to = ?";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("isii", $progress, $status, $task_id, $user_id);
    
    if ($stmt->execute()) {
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
        <div class="col">
            <div class="card">
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-hover" id="tasksTable">
                            <thead>
                                <tr>
                                    <th>Task ID</th>
                                    <th>Title</th>
                                    <?php if ($role == 'project_leader' || $role == 'hr' || $role == 'admin'): ?>
                                    <th>Assigned To</th>
                                    <?php endif; ?>
                                    <th>Project</th>
                                    <th>Priority</th>
                                    <th>Due Date</th>
                                    <th>Progress</th>
                                    <th>Status</th>
                                    <th>Assigned By</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if ($result->num_rows > 0): ?>
                                    <?php while ($row = $result->fetch_assoc()): 
                                        $is_overdue = ($row['due_date'] && $row['due_date'] < date('Y-m-d') && $row['status'] != 'completed');
                                    ?>
                                    <tr class="<?php echo $is_overdue ? 'table-danger' : ''; ?>">
                                        <td>#TASK-<?php echo str_pad($row['id'], 5, '0', STR_PAD_LEFT); ?></td>
                                        <td>
                                            <strong><?php echo htmlspecialchars($row['title']); ?></strong><br>
                                            <small class="text-muted"><?php echo htmlspecialchars(substr($row['description'] ?? '', 0, 50)); ?>...</small>
                                        </td>
                                        
                                        <?php if ($role == 'project_leader' || $role == 'hr' || $role == 'admin'): ?>
                                        <td><?php echo htmlspecialchars($row['assigned_to_name'] ?? 'N/A'); ?></td>
                                        <?php endif; ?>
                                        
                                        <td>
                                            <?php if ($row['project_name']): ?>
                                            <span class="badge bg-info"><?php echo htmlspecialchars($row['project_name']); ?></span>
                                            <?php else: ?>
                                            <span class="text-muted">-</span>
                                            <?php endif; ?>
                                        </td>
                                        
                                        <td>
                                            <?php 
                                            $priority_badges = [
                                                'low' => 'secondary',
                                                'medium' => 'primary',
                                                'high' => 'warning',
                                                'critical' => 'danger'
                                            ];
                                            $badge_color = $priority_badges[$row['priority']] ?? 'secondary';
                                            ?>
                                            <span class="badge bg-<?php echo $badge_color; ?>">
                                                <?php echo ucfirst($row['priority']); ?>
                                            </span>
                                        </td>
                                        
                                        <td>
                                            <?php if ($row['due_date']): ?>
                                            <span class="<?php echo $is_overdue ? 'text-danger fw-bold' : ''; ?>">
                                                <?php echo formatDate($row['due_date']); ?>
                                            </span>
                                            <?php if ($is_overdue): ?>
                                            <br><small class="badge bg-danger">Overdue</small>
                                            <?php endif; ?>
                                            <?php else: ?>
                                            <span class="text-muted">Not set</span>
                                            <?php endif; ?>
                                        </td>
                                        
                                        <td>
                                            <div class="progress" style="height: 20px;">
                                                <div class="progress-bar 
                                                    <?php 
                                                    if ($row['progress'] >= 100) echo 'bg-success';
                                                    elseif ($row['progress'] >= 50) echo 'bg-primary';
                                                    else echo 'bg-warning';
                                                    ?>" 
                                                    role="progressbar" 
                                                    style="width: <?php echo $row['progress']; ?>%">
                                                    <?php echo $row['progress']; ?>%
                                                </div>
                                            </div>
                                        </td>
                                        
                                        <td><?php echo getStatusBadge($row['status']); ?></td>
                                        
                                        <td>
                                            <small><?php echo htmlspecialchars($row['assigned_by_name'] ?? 'N/A'); ?></small><br>
                                            <small class="text-muted"><?php echo formatDate($row['created_at'], 'M d'); ?></small>
                                        </td>
                                        
                                        <td>
                                            <button type="button" class="btn btn-sm btn-outline-primary" 
                                                    data-bs-toggle="modal" data-bs-target="#viewModal<?php echo $row['id']; ?>">
                                                <i class="bi bi-eye"></i>
                                            </button>
                                            
                                            <?php if ($row['assigned_to'] == $user_id && $row['status'] != 'completed'): ?>
                                            <button type="button" class="btn btn-sm btn-outline-success" 
                                                    data-bs-toggle="modal" data-bs-target="#updateModal<?php echo $row['id']; ?>">
                                                <i class="bi bi-pencil"></i>
                                            </button>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                    
                                    <!-- View Modal -->
                                    <div class="modal fade" id="viewModal<?php echo $row['id']; ?>" tabindex="-1">
                                        <div class="modal-dialog modal-lg">
                                            <div class="modal-content">
                                                <div class="modal-header">
                                                    <h5 class="modal-title">Task Details #TASK-<?php echo str_pad($row['id'], 5, '0', STR_PAD_LEFT); ?></h5>
                                                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                                </div>
                                                <div class="modal-body">
                                                    <div class="row mb-3">
                                                        <div class="col-md-8">
                                                            <h6><?php echo htmlspecialchars($row['title']); ?></h6>
                                                            <p class="text-muted"><?php echo nl2br(htmlspecialchars($row['description'])); ?></p>
                                                        </div>
                                                        <div class="col-md-4">
                                                            <div class="card">
                                                                <div class="card-body">
                                                                    <p><strong>Status:</strong> <?php echo getStatusBadge($row['status']); ?></p>
                                                                    <p><strong>Priority:</strong> 
                                                                        <span class="badge bg-<?php echo $badge_color; ?>">
                                                                            <?php echo ucfirst($row['priority']); ?>
                                                                        </span>
                                                                    </p>
                                                                    <p><strong>Progress:</strong> <?php echo $row['progress']; ?>%</p>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                    
                                                    <div class="row">
                                                        <div class="col-md-6">
                                                            <table class="table table-sm">
                                                                <tr>
                                                                    <th>Assigned To:</th>
                                                                    <td><?php echo htmlspecialchars($row['assigned_to_name'] ?? 'You'); ?></td>
                                                                </tr>
                                                                <tr>
                                                                    <th>Assigned By:</th>
                                                                    <td><?php echo htmlspecialchars($row['assigned_by_name'] ?? 'N/A'); ?></td>
                                                                </tr>
                                                                <tr>
                                                                    <th>Assigned On:</th>
                                                                    <td><?php echo formatDate($row['created_at'], 'F d, Y'); ?></td>
                                                                </tr>
                                                            </table>
                                                        </div>
                                                        <div class="col-md-6">
                                                            <table class="table table-sm">
                                                                <tr>
                                                                    <th>Due Date:</th>
                                                                    <td class="<?php echo $is_overdue ? 'text-danger fw-bold' : ''; ?>">
                                                                        <?php echo $row['due_date'] ? formatDate($row['due_date'], 'F d, Y') : 'Not set'; ?>
                                                                        <?php if ($is_overdue): ?>
                                                                        <span class="badge bg-danger ms-2">Overdue</span>
                                                                        <?php endif; ?>
                                                                    </td>
                                                                </tr>
                                                                <tr>
                                                                    <th>Project:</th>
                                                                    <td><?php echo htmlspecialchars($row['project_name'] ?: 'None'); ?></td>
                                                                </tr>
                                                                <?php if ($row['completion_date']): ?>
                                                                <tr>
                                                                    <th>Completed On:</th>
                                                                    <td><?php echo formatDate($row['completion_date'], 'F d, Y'); ?></td>
                                                                </tr>
                                                                <?php endif; ?>
                                                            </table>
                                                        </div>
                                                    </div>
                                                </div>
                                                <div class="modal-footer">
                                                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <!-- Update Progress Modal -->
                                    <?php if ($row['assigned_to'] == $user_id && $row['status'] != 'completed'): ?>
                                    <div class="modal fade" id="updateModal<?php echo $row['id']; ?>" tabindex="-1">
                                        <div class="modal-dialog">
                                            <div class="modal-content">
                                                <form method="POST" action="">
                                                    <div class="modal-header">
                                                        <h5 class="modal-title">Update Task Progress</h5>
                                                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                                    </div>
                                                    <div class="modal-body">
                                                        <input type="hidden" name="task_id" value="<?php echo $row['id']; ?>">
                                                        <input type="hidden" name="update_progress" value="1">
                                                        
                                                        <div class="mb-3">
                                                            <label class="form-label">Progress (%)</label>
                                                            <input type="range" class="form-range" min="0" max="100" step="5" 
                                                                   id="progressRange<?php echo $row['id']; ?>" 
                                                                   name="progress" value="<?php echo $row['progress']; ?>"
                                                                   oninput="document.getElementById('progressValue<?php echo $row['id']; ?>').innerText = this.value + '%'">
                                                            <div class="d-flex justify-content-between">
                                                                <small>0%</small>
                                                                <span id="progressValue<?php echo $row['id']; ?>" class="fw-bold">
                                                                    <?php echo $row['progress']; ?>%
                                                                </span>
                                                                <small>100%</small>
                                                            </div>
                                                        </div>
                                                        
                                                        <div class="mb-3">
                                                            <label class="form-label">Status</label>
                                                            <select class="form-select" name="status">
                                                                <option value="pending" <?php echo $row['status'] == 'pending' ? 'selected' : ''; ?>>Pending</option>
                                                                <option value="in_progress" <?php echo $row['status'] == 'in_progress' ? 'selected' : ''; ?>>In Progress</option>
                                                                <option value="review" <?php echo $row['status'] == 'review' ? 'selected' : ''; ?>>Under Review</option>
                                                                <option value="completed" <?php echo $row['status'] == 'completed' ? 'selected' : ''; ?>>Completed</option>
                                                            </select>
                                                        </div>
                                                        
                                                        <div class="alert alert-info">
                                                            <i class="bi bi-info-circle"></i> 
                                                            Task will be marked as completed automatically when progress reaches 100%.
                                                        </div>
                                                    </div>
                                                    <div class="modal-footer">
                                                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                                        <button type="submit" class="btn btn-primary">Update Progress</button>
                                                    </div>
                                                </form>
                                            </div>
                                        </div>
                                    </div>
                                    <?php endif; ?>
                                    
                                    <?php endwhile; ?>
                                <?php else: ?>
                                <tr>
                                    <td colspan="<?php echo ($role == 'project_leader' || $role == 'hr' || $role == 'admin') ? '10' : '9'; ?>" class="text-center py-4">
                                        <i class="bi bi-inbox h1 text-muted d-block mb-3"></i>
                                        <p class="text-muted">No tasks found.</p>
                                        <?php if ($role == 'project_leader' || $role == 'hr' || $role == 'admin'): ?>
                                        <a href="assign.php" class="btn btn-primary">Assign New Task</a>
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

<script>
    // Initialize tooltips
    document.addEventListener('DOMContentLoaded', function() {
        var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
        var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
            return new bootstrap.Tooltip(tooltipTriggerEl);
        });
    });
</script>

<?php require_once '../../includes/footer.php'; ?>