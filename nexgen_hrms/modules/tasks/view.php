<?php
require_once '../../includes/auth.php';
require_once '../../includes/functions.php';

// Check login status first
Auth::requireLogin();

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    setFlash('danger', 'Invalid task ID.');
    header('Location: ' . Auth::getBasePath() . '/modules/tasks/my_tasks.php');
    exit();
}

$task_id = $_GET['id'];
$conn = getDBConnection();
$user_id = $_SESSION['user_id'];
$role = $_SESSION['role'];

// Get task details
$sql = "SELECT t.*, 
        u1.full_name as assigned_to_name,
        u2.full_name as assigned_by_name,
        p.project_name,
        p.project_code,
        p.project_leader
        FROM tasks t
        LEFT JOIN users u1 ON t.assigned_to = u1.id
        LEFT JOIN users u2 ON t.assigned_by = u2.id
        LEFT JOIN projects p ON t.project_id = p.id
        WHERE t.id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $task_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows == 0) {
    setFlash('danger', 'Task not found.');
    header('Location: ' . Auth::getBasePath() . '/modules/tasks/my_tasks.php');
    exit();
}

$task = $result->fetch_assoc();

// Check if user has access to this task
if ($task['assigned_to'] != $user_id && $task['assigned_by'] != $user_id && 
    !Auth::hasRole('hr') && !Auth::hasRole('admin')) {
    setFlash('danger', 'You do not have permission to view this task.');
    header('Location: ' . Auth::getBasePath() . '/modules/tasks/my_tasks.php');
    exit();
}

$is_assigned_to_me = ($task['assigned_to'] == $user_id);
$is_assigned_by_me = ($task['assigned_by'] == $user_id);
$is_overdue = ($task['due_date'] && $task['due_date'] < date('Y-m-d') && $task['status'] != 'completed');

// Handle progress update
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_progress'])) {
    $progress = $_POST['progress'];
    $status = $_POST['status'];
    $comments = $_POST['comments'] ?? '';
    
    $update_sql = "UPDATE tasks SET progress = ?, status = ?, updated_at = NOW()";
    
    if ($status == 'completed') {
        $update_sql .= ", completion_date = CURDATE()";
    }
    
    $update_sql .= " WHERE id = ?";
    
    $update_stmt = $conn->prepare($update_sql);
    $update_stmt->bind_param("isi", $progress, $status, $task_id);
    
    if ($update_stmt->execute()) {
        // Add comment to task history
        if (!empty($comments)) {
            $comment_sql = "INSERT INTO task_comments (task_id, user_id, comment) VALUES (?, ?, ?)";
            $comment_stmt = $conn->prepare($comment_sql);
            $comment_stmt->bind_param("iis", $task_id, $user_id, $comments);
            $comment_stmt->execute();
        }
        
        setFlash('success', 'Task progress updated successfully!');
        header('Location: ' . Auth::getBasePath() . '/modules/tasks/view.php?id=' . $task_id);
        exit();
    }
}

// Get task comments/history
$comments_sql = "SELECT tc.*, u.full_name, u.profile_image 
                 FROM task_comments tc 
                 JOIN users u ON tc.user_id = u.id 
                 WHERE tc.task_id = ? 
                 ORDER BY tc.created_at DESC";
$comments_stmt = $conn->prepare($comments_sql);
$comments_stmt->bind_param("i", $task_id);
$comments_stmt->execute();
$comments_result = $comments_stmt->get_result();

$page_title = 'Task: ' . $task['title'];
require_once '../../includes/header.php';
?>
<div class="container-fluid">
    <!-- Task Header -->
    <div class="row mb-4">
        <div class="col">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h4 class="mb-1"><?php echo htmlspecialchars($task['title']); ?></h4>
                    <p class="text-muted mb-0">
                        <?php if ($task['project_name']): ?>
                        Project: <?php echo htmlspecialchars($task['project_name']); ?> | 
                        <?php endif; ?>
                        Task ID: #TASK-<?php echo str_pad($task['id'], 5, '0', STR_PAD_LEFT); ?>
                    </p>
                </div>
                <div class="btn-group">
                    <a href="my_tasks.php" class="btn btn-outline-secondary">
                        <i class="bi bi-arrow-left"></i> Back to Tasks
                    </a>
                    <?php if ($is_assigned_to_me || $role == 'hr' || $role == 'admin'): ?>
                    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#updateProgressModal">
                        <i class="bi bi-pencil"></i> Update Progress
                    </button>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Task Details -->
    <div class="row">
        <div class="col-md-8">
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="mb-0">Task Details</h5>
                </div>
                <div class="card-body">
                    <div class="row mb-4">
                        <div class="col-md-6">
                            <table class="table table-sm">
                                <tr>
                                    <th width="40%">Status:</th>
                                    <td><?php echo getStatusBadge($task['status']); ?></td>
                                </tr>
                                <tr>
                                    <th>Priority:</th>
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
                                </tr>
                                <tr>
                                    <th>Progress:</th>
                                    <td>
                                        <div class="progress" style="height: 15px; width: 200px;">
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
                                </tr>
                                <tr>
                                    <th>Assigned To:</th>
                                    <td><?php echo htmlspecialchars($task['assigned_to_name']); ?></td>
                                </tr>
                            </table>
                        </div>
                        <div class="col-md-6">
                            <table class="table table-sm">
                                <tr>
                                    <th width="40%">Assigned By:</th>
                                    <td><?php echo htmlspecialchars($task['assigned_by_name']); ?></td>
                                </tr>
                                <tr>
                                    <th>Assigned On:</th>
                                    <td><?php echo formatDate($task['created_at']); ?></td>
                                </tr>
                                <tr>
                                    <th>Due Date:</th>
                                    <td class="<?php echo $is_overdue ? 'text-danger fw-bold' : ''; ?>">
                                        <?php echo $task['due_date'] ? formatDate($task['due_date']) : 'Not set'; ?>
                                        <?php if ($is_overdue): ?>
                                        <br><span class="badge bg-danger">Overdue</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <?php if ($task['completion_date']): ?>
                                <tr>
                                    <th>Completed On:</th>
                                    <td><?php echo formatDate($task['completion_date']); ?></td>
                                </tr>
                                <?php endif; ?>
                            </table>
                        </div>
                    </div>
                    
                    <div class="mb-4">
                        <h6>Description</h6>
                        <div class="card bg-light">
                            <div class="card-body">
                                <?php echo nl2br(htmlspecialchars($task['description'])); ?>
                            </div>
                        </div>
                    </div>
                    
                    <?php if ($task['project_name']): ?>
                    <div class="mb-3">
                        <h6>Project Information</h6>
                        <div class="card">
                            <div class="card-body py-2">
                                <strong><?php echo $task['project_name']; ?></strong> 
                                (<?php echo htmlspecialchars($task['project_code']); ?>)
                                          <a href="<?php echo $base_url; ?>/modules/projects/details.php?id=<?php echo $task['project_id']; ?>" 
                                   class="btn btn-sm btn-outline-primary float-end">
                                    View Project
                                </a>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>
                    
                    <?php 
                    $is_project_leader = ($task['project_leader'] ?? 0) == $user_id; // Check if user is leader of the task's project
                    if ($is_assigned_by_me || $role == 'hr' || $role == 'admin' || $is_project_leader): 
                    ?>
                    <div class="d-flex gap-2">
                        <button class="btn btn-sm btn-outline-primary" 
                                data-bs-toggle="modal" data-bs-target="#editTaskModal">
                            <i class="bi bi-pencil"></i> Edit Task
                        </button>
                        <?php if ($task['status'] != 'completed'): ?>
                        <button class="btn btn-sm btn-outline-success" 
                                onclick="completeTask()">
                            <i class="bi bi-check-circle"></i> Mark Complete
                        </button>
                        <?php endif; ?>
                        
                        <?php 
                        // Only show delete if they have permission to delete (same as edit usually, but let's be explicit if needed, 
                        // essentially same group: Admin, HR, Leader, or Creator)
                        ?>
                        <button class="btn btn-sm btn-outline-danger" 
                                data-bs-toggle="modal" data-bs-target="#deleteTaskModal">
                            <i class="bi bi-trash"></i> Delete
                        </button>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Comments Section -->
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">Comments & Updates</h5>
                    <button class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#addCommentModal">
                        <i class="bi bi-plus-circle"></i> Add Comment
                    </button>
                </div>
                <div class="card-body">
                    <!-- Comment Form -->
                    <form method="POST" action="add_comment.php" class="mb-4">
                        <input type="hidden" name="task_id" value="<?php echo $task_id; ?>">
                        <div class="mb-3">
                            <label class="form-label">Add Comment</label>
                            <textarea class="form-control" name="comment" rows="3" 
                                      placeholder="Add your comment or update..."></textarea>
                        </div>
                        <div class="d-flex justify-content-between">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="notifyTeam">
                                <label class="form-check-label" for="notifyTeam">
                                    Notify team members
                                </label>
                            </div>
                            <button type="submit" class="btn btn-primary">Post Comment</button>
                        </div>
                    </form>
                    
                    <!-- Comments List -->
                    <?php if ($comments_result->num_rows > 0): ?>
                    <div class="timeline">
                        <?php while ($comment = $comments_result->fetch_assoc()): ?>
                        <div class="timeline-item mb-4">
                            <div class="timeline-marker"></div>
                            <div class="timeline-content">
                                <div class="d-flex align-items-center mb-2">
                                    <div class="user-avatar me-2">
                                        <?php 
                                        $name_parts = explode(' ', $comment['full_name']);
                                        $initials = '';
                                        foreach ($name_parts as $part) {
                                            $initials .= strtoupper(substr($part, 0, 1));
                                        }
                                        echo substr($initials, 0, 2);
                                        ?>
                                    </div>
                                    <div>
                                        <strong><?php echo htmlspecialchars($comment['full_name']); ?></strong>
                                        <small class="text-muted ms-2">
                                            <?php echo formatDate($comment['created_at'], 'M d, Y h:i A'); ?>
                                        </small>
                                    </div>
                                </div>
                                <div class="card bg-light">
                                    <div class="card-body py-2">
                                        <?php echo nl2br(htmlspecialchars($comment['comment'])); ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <?php endwhile; ?>
                    </div>
                    <?php else: ?>
                    <div class="text-center py-4">
                        <i class="bi bi-chat-left-text h1 text-muted d-block mb-3"></i>
                        <p class="text-muted">No comments yet.</p>
                        <p class="small text-muted">Be the first to add a comment or update.</p>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <div class="col-md-4">
            <!-- Quick Stats -->
            <div class="card mb-4">
                <div class="card-header">
                    <h6 class="mb-0">Task Statistics</h6>
                </div>
                <div class="card-body">
                    <div class="row text-center">
                        <div class="col-6 mb-3">
                            <div class="fw-bold fs-4"><?php echo $task['progress']; ?>%</div>
                            <div class="text-muted small">Progress</div>
                        </div>
                        <div class="col-6 mb-3">
                            <div class="fw-bold fs-4">
                                <?php 
                                if ($task['due_date']) {
                                    $today = new DateTime();
                                    $due_date = new DateTime($task['due_date']);
                                    $interval = $today->diff($due_date);
                                    $days = $interval->days;
                                    
                                    if ($interval->invert) {
                                        echo '-' . $days;
                                    } else {
                                        echo $days;
                                    }
                                } else {
                                    echo 'N/A';
                                }
                                ?>
                            </div>
                            <div class="text-muted small">Days <?php echo $is_overdue ? 'Overdue' : 'Left'; ?></div>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <h6>Time Spent</h6>
                        <div class="input-group input-group-sm">
                            <input type="number" class="form-control" value="0" min="0" max="100">
                            <span class="input-group-text">hours</span>
                            <button class="btn btn-outline-primary" type="button">Log Time</button>
                        </div>
                        <small class="text-muted">Track time spent on this task</small>
                    </div>
                </div>
            </div>
            
            <!-- Attachments -->
            <div class="card mb-4">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h6 class="mb-0">Attachments</h6>
                    <button class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#uploadModal">
                        <i class="bi bi-upload"></i>
                    </button>
                </div>
                <div class="card-body">
                    <?php if ($task['attachment']): ?>
                    <div class="list-group list-group-flush">
                        <div class="list-group-item d-flex justify-content-between align-items-center">
                            <div>
                                <i class="bi bi-file-earmark-text me-2"></i>
                                <span>document.pdf</span>
                            </div>
                            <div class="btn-group">
                                <a href="#" class="btn btn-sm btn-outline-primary">
                                    <i class="bi bi-download"></i>
                                </a>
                                <button class="btn btn-sm btn-outline-danger">
                                    <i class="bi bi-trash"></i>
                                </button>
                            </div>
                        </div>
                    </div>
                    <?php else: ?>
                    <div class="text-center py-3">
                        <i class="bi bi-paperclip h3 text-muted d-block mb-2"></i>
                        <p class="text-muted small mb-0">No attachments</p>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Related Tasks -->
            <div class="card">
                <div class="card-header">
                    <h6 class="mb-0">Related Tasks</h6>
                </div>
                <div class="card-body">
                    <?php
                    $related_sql = "SELECT id, title, status, progress 
                                    FROM tasks 
                                    WHERE project_id = ? 
                                    AND id != ? 
                                    AND assigned_to = ?
                                    ORDER BY due_date ASC 
                                    LIMIT 3";
                    $related_stmt = $conn->prepare($related_sql);
                    $related_stmt->bind_param("iii", $task['project_id'], $task_id, $task['assigned_to']);
                    $related_stmt->execute();
                    $related_result = $related_stmt->get_result();
                    ?>
                    
                    <?php if ($related_result->num_rows > 0): ?>
                    <div class="list-group list-group-flush">
                        <?php while ($related = $related_result->fetch_assoc()): ?>
                        <a href="view.php?id=<?php echo $related['id']; ?>" 
                           class="list-group-item list-group-item-action">
                            <div class="d-flex justify-content-between align-items-center">
                                <span class="small"><?php echo htmlspecialchars($related['title']); ?></span>
                                <span class="badge bg-<?php echo $related['status'] == 'completed' ? 'success' : 'warning'; ?>">
                                    <?php echo $related['progress']; ?>%
                                </span>
                            </div>
                        </a>
                        <?php endwhile; ?>
                    </div>
                    <?php else: ?>
                    <p class="text-muted small mb-0">No related tasks found.</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Update Progress Modal -->
<div class="modal fade" id="updateProgressModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" action="">
                <div class="modal-header">
                    <h5 class="modal-title">Update Task Progress</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="update_progress" value="1">
                    
                    <div class="mb-3">
                        <label class="form-label">Progress (%)</label>
                        <input type="range" class="form-range" min="0" max="100" step="5" 
                               id="progressRange" name="progress" value="<?php echo $task['progress']; ?>"
                               oninput="document.getElementById('progressValue').innerText = this.value + '%'">
                        <div class="d-flex justify-content-between">
                            <small>0%</small>
                            <span id="progressValue" class="fw-bold"><?php echo $task['progress']; ?>%</span>
                            <small>100%</small>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Status</label>
                        <select class="form-select" name="status" required>
                            <option value="pending" <?php echo $task['status'] == 'pending' ? 'selected' : ''; ?>>Pending</option>
                            <option value="in_progress" <?php echo $task['status'] == 'in_progress' ? 'selected' : ''; ?>>In Progress</option>
                            <option value="review" <?php echo $task['status'] == 'review' ? 'selected' : ''; ?>>Under Review</option>
                            <option value="completed" <?php echo $task['status'] == 'completed' ? 'selected' : ''; ?>>Completed</option>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Comments (Optional)</label>
                        <textarea class="form-control" name="comments" rows="3" 
                                  placeholder="Add any comments about this update..."></textarea>
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

<!-- Edit Task Modal -->
<div class="modal fade" id="editTaskModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" action="update.php">
                <input type="hidden" name="task_id" value="<?php echo $task_id; ?>">
                <div class="modal-header">
                    <h5 class="modal-title">Edit Task</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Task Title</label>
                        <input type="text" class="form-control" name="title" 
                               value="<?php echo htmlspecialchars($task['title']); ?>" required>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Description</label>
                        <textarea class="form-control" name="description" rows="3"><?php echo htmlspecialchars($task['description']); ?></textarea>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Priority</label>
                            <select class="form-select" name="priority">
                                <option value="low" <?php echo $task['priority'] == 'low' ? 'selected' : ''; ?>>Low</option>
                                <option value="medium" <?php echo $task['priority'] == 'medium' ? 'selected' : ''; ?>>Medium</option>
                                <option value="high" <?php echo $task['priority'] == 'high' ? 'selected' : ''; ?>>High</option>
                                <option value="critical" <?php echo $task['priority'] == 'critical' ? 'selected' : ''; ?>>Critical</option>
                            </select>
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Due Date</label>
                            <input type="date" class="form-control" name="due_date" 
                                   value="<?php echo $task['due_date']; ?>">
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

<!-- Add Comment Modal -->
<div class="modal fade" id="addCommentModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" action="add_comment.php">
                <input type="hidden" name="task_id" value="<?php echo $task_id; ?>">
                <div class="modal-header">
                    <h5 class="modal-title">Add Comment</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Comment</label>
                        <textarea class="form-control" name="comment" rows="4" required 
                                  placeholder="Enter your comment..."></textarea>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Attachment</label>
                        <input type="file" class="form-control" name="attachment">
                    </div>
                    
                    <div class="form-check mb-3">
                        <input class="form-check-input" type="checkbox" name="notify_team" id="notifyTeamModal" checked>
                        <label class="form-check-label" for="notifyTeamModal">
                            Notify team members via email
                        </label>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Post Comment</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Upload Modal -->
<div class="modal fade" id="uploadModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Upload Attachment</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label class="form-label">Select File</label>
                    <input type="file" class="form-control" accept=".pdf,.doc,.docx,.xls,.xlsx,.jpg,.png">
                </div>
                
                <div class="mb-3">
                    <label class="form-label">Description</label>
                    <input type="text" class="form-control" placeholder="Brief description of the file">
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary">Upload</button>
            </div>
        </div>
    </div>
</div>

<!-- Delete Task Modal -->
<div class="modal fade" id="deleteTaskModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Delete Task</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="alert alert-danger">
                    <i class="bi bi-exclamation-triangle"></i> 
                    Are you sure you want to delete this task? This action cannot be undone.
                </div>
                
                <p><strong>Task:</strong> <?php echo htmlspecialchars($task['title']); ?></p>
                <p><strong>Assigned To:</strong> <?php echo $task['assigned_to_name']; ?></p>
                
                <div class="form-check mb-3">
                    <input class="form-check-input" type="checkbox" id="notifyAssignee">
                    <label class="form-check-label" for="notifyAssignee">
                        Notify assignee about task deletion
                    </label>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <a href="delete.php?id=<?php echo $task_id; ?>" class="btn btn-danger">Delete Task</a>
            </div>
        </div>
    </div>
</div>

<script>
    function completeTask() {
        if (confirm('Mark this task as completed?')) {
            document.getElementById('progressRange').value = 100;
            document.getElementById('progressValue').innerText = '100%';
            
            const form = document.createElement('form');
            form.method = 'POST';
            form.action = '';
            
            const progressInput = document.createElement('input');
            progressInput.type = 'hidden';
            progressInput.name = 'progress';
            progressInput.value = '100';
            form.appendChild(progressInput);
            
            const statusInput = document.createElement('input');
            statusInput.type = 'hidden';
            statusInput.name = 'status';
            statusInput.value = 'completed';
            form.appendChild(statusInput);
            
            const updateInput = document.createElement('input');
            updateInput.type = 'hidden';
            updateInput.name = 'update_progress';
            updateInput.value = '1';
            form.appendChild(updateInput);
            
            document.body.appendChild(form);
            form.submit();
        }
    }
    
    // Initialize date picker
    document.addEventListener('DOMContentLoaded', function() {
        const today = new Date().toISOString().split('T')[0];
        document.querySelector('input[name="due_date"]').min = today;
    });
</script>

<style>
    .timeline {
        position: relative;
        padding-left: 30px;
    }
    
    .timeline-item {
        position: relative;
    }
    
    .timeline-marker {
        position: absolute;
        left: -30px;
        top: 10px;
        width: 12px;
        height: 12px;
        border-radius: 50%;
        background-color: #0d6efd;
        border: 2px solid white;
        box-shadow: 0 0 0 3px rgba(13, 110, 253, 0.2);
    }
    
    .timeline-content {
        background: white;
        padding: 0;
    }
    
    .user-avatar {
        width: 32px;
        height: 32px;
        border-radius: 50%;
        background-color: #0d6efd;
        color: white;
        display: flex;
        align-items: center;
        justify-content: center;
        font-weight: bold;
        font-size: 0.875rem;
    }
</style>

<?php require_once '../../includes/footer.php'; ?>