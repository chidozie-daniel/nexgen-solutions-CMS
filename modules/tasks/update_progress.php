<?php
/**
 * Update Task Progress Handler
 * 
 * Handles task progress updates from any module
 * Accessible by: Assignee, Assigner, Project Leader (of project), HR, Admin
 */

define('APP_ENTRY_POINT', true);
require_once '../../includes/auth.php';
require_once '../../includes/functions.php';

Auth::requireLogin();

if ($_SERVER['REQUEST_METHOD'] != 'POST') {
    setFlash('danger', 'Invalid request method.');
    header('Location: ' . Auth::getBasePath() . '/dashboard.php');
    exit();
}

// Verify CSRF token
if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
    setFlash('danger', 'Invalid security token. Please try again.');
    header('Location: ' . Auth::getBasePath() . '/dashboard.php');
    exit();
}

$conn = getDBConnection();
$user_id = $_SESSION['user_id'];
$role = $_SESSION['role'];

$task_id = (int)($_POST['task_id'] ?? 0);
$project_id = isset($_POST['project_id']) ? (int)$_POST['project_id'] : null;
$progress = (int)($_POST['progress'] ?? 0);
$status = $_POST['status'] ?? 'pending';

// Validate input
if ($task_id <= 0) {
    setFlash('danger', 'Invalid task ID.');
    header('Location: ' . Auth::getBasePath() . ($project_id ? '/modules/projects/details.php?id=' . $project_id : '/modules/tasks/my_tasks.php'));
    exit();
}

if ($progress < 0 || $progress > 100) {
    setFlash('danger', 'Progress must be between 0 and 100.');
    header('Location: ' . Auth::getBasePath() . ($project_id ? '/modules/projects/details.php?id=' . $project_id : '/modules/tasks/my_tasks.php'));
    exit();
}

$allowed_status = ['pending', 'in_progress', 'review', 'completed'];
if (!in_array($status, $allowed_status, true)) {
    setFlash('danger', 'Invalid status.');
    header('Location: ' . Auth::getBasePath() . ($project_id ? '/modules/projects/details.php?id=' . $project_id : '/modules/tasks/my_tasks.php'));
    exit();
}

// Get task details
$sql = "SELECT t.*, p.project_leader
        FROM tasks t
        LEFT JOIN projects p ON t.project_id = p.id
        WHERE t.id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $task_id);
$stmt->execute();
$task = $stmt->get_result()->fetch_assoc();

if (!$task) {
    setFlash('danger', 'Task not found.');
    header('Location: ' . Auth::getBasePath() . ($project_id ? '/modules/projects/details.php?id=' . $project_id : '/modules/tasks/my_tasks.php'));
    exit();
}

// Check permission to update
$can_update = false;

// Assignee can update their own tasks
if ($task['assigned_to'] == $user_id) {
    $can_update = true;
}

// Assigner (PL/Admin/HR) can update tasks they assigned
if ($task['assigned_by'] == $user_id) {
    $can_update = true;
}

// HR and Admin can update any task
if (Auth::hasRole('hr') || Auth::hasRole('admin')) {
    $can_update = true;
}

// Project Leader can update if they lead the project
if (Auth::hasRole('project_leader') && $task['project_leader'] == $user_id) {
    $can_update = true;
}

if (!$can_update) {
    setFlash('danger', 'You do not have permission to update this task.');
    header('Location: ' . Auth::getBasePath() . ($project_id ? '/modules/projects/details.php?id=' . $project_id : '/modules/tasks/my_tasks.php'));
    exit();
}

// Update task
$update_sql = "UPDATE tasks SET progress = ?, status = ?, updated_at = NOW()";

if ($status == 'completed') {
    $update_sql .= ", completion_date = CURDATE()";
} elseif ($task['completion_date'] && $status != 'completed') {
    // If task was completed but status changed, remove completion date
    $update_sql .= ", completion_date = NULL";
}

$update_sql .= " WHERE id = ?";
$update_stmt = $conn->prepare($update_sql);
$update_stmt->bind_param("isi", $progress, $status, $task_id);

if ($update_stmt->execute()) {
    // Get task details for notification
    $notif_sql = "SELECT t.*, u.full_name as assigner_name, u.email as assigner_email
                  FROM tasks t
                  JOIN users u ON t.assigned_by = u.id
                  WHERE t.id = ?";
    $notif_stmt = $conn->prepare($notif_sql);
    $notif_stmt->bind_param("i", $task_id);
    $notif_stmt->execute();
    $task_data = $notif_stmt->get_result()->fetch_assoc();
    
    // Log activity
    logActivity('TASK_PROGRESS', "Task progress updated to {$progress}% - Status: {$status}", 'tasks', $task_id);
    
    // Notify assigner if progress changed significantly or completed
    if ($task_data && $task_data['assigned_by'] != $user_id) {
        $notification_msg = "Task \"{$task_data['title']}\" progress updated to {$progress}%.";
        $notif_type = 'info';
        
        if ($status == 'completed') {
            $notification_msg = "Task \"{$task_data['title']}\" has been COMPLETED!";
            $notif_type = 'success';
            
            // Send email notification
            $email_subject = "Task Completed: {$task_data['title']}";
            $email_body = "
                <div style='font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; padding: 20px;'>
                    <h2 style='color: #28a745;'>Task Completed! ✓</h2>
                    <p>Hello <strong>{$task_data['assigner_name']}</strong>,</p>
                    <p>Great news! The task <strong>{$task_data['title']}</strong> has been marked as <strong>COMPLETED</strong>.</p>
                    <div style='background: #f8f9fa; padding: 15px; border-radius: 5px; margin: 20px 0;'>
                        <p><strong>Progress:</strong> 100%</p>
                        <p><strong>Status:</strong> Completed</p>
                        <p><strong>Completed by:</strong> " . htmlspecialchars($_SESSION['full_name']) . "</p>
                    </div>
                    <p style='margin-top: 30px;'>Best regards,<br><strong>NexGen HRMS Team</strong></p>
                </div>
            ";
            sendEmailNotification($task_data['assigner_email'], $email_subject, $email_body);
            
        } elseif ($progress >= 100 || $progress % 25 == 0) {
            // Notify on milestones (25%, 50%, 75%, 100%)
            $notification_msg = "Task \"{$task_data['title']}\" progress updated to {$progress}%.";
            
            if ($progress >= 75) {
                $notif_type = 'warning';
            }
        }
        
        // Create in-app notification
        createNotification(
            $task_data['assigned_by'],
            $status == 'completed' ? 'Task Completed! ✓' : 'Task Progress Update',
            $notification_msg,
            $notif_type,
            'task',
            'modules/tasks/view.php?id=' . $task_id,
            $user_id
        );
    }
    
    setFlash('success', 'Task progress updated successfully!');
} else {
    error_log("Error updating task progress (task_id: $task_id): " . $conn->error);
    setFlash('danger', 'Error updating task progress. Please try again.');
}

// Redirect back to referring page or default
if ($project_id) {
    header('Location: ' . Auth::getBasePath() . '/modules/projects/details.php?id=' . $project_id);
} else {
    header('Location: ' . Auth::getBasePath() . '/modules/tasks/my_tasks.php');
}
exit();
?>
