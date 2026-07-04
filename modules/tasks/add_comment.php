<?php
// Define entry point constant
define('APP_ENTRY_POINT', true);

require_once '../../includes/auth.php';
require_once '../../includes/functions.php';

// Check login status first
Auth::requireLogin();

if ($_SERVER['REQUEST_METHOD'] != 'POST' || !isset($_POST['task_id']) || !isset($_POST['comment'])) {
    setFlash('danger', 'Invalid request.');
    header('Location: ' . Auth::getBasePath() . '/modules/tasks/my_tasks.php');
    exit();
}

// Verify CSRF token
if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
    setFlash('danger', 'Invalid security token. Please try again.');
    header('Location: ' . Auth::getBasePath() . '/modules/tasks/my_tasks.php');
    exit();
}

$conn = getDBConnection();
$task_id = $_POST['task_id'];
$comment = sanitizeText($_POST['comment'] ?? '', 2000, true);
$user_id = $_SESSION['user_id'];

if (!is_numeric($task_id) || (int)$task_id <= 0) {
    setFlash('danger', 'Invalid task.');
    header('Location: ' . Auth::getBasePath() . '/modules/tasks/my_tasks.php');
    exit();
}
if ($comment === '' || strlen($comment) < 2) {
    setFlash('danger', 'Comment cannot be empty.');
    header('Location: ' . Auth::getBasePath() . '/modules/tasks/view.php?id=' . $task_id);
    exit();
}

// Check if user has access to this task
$check_sql = "SELECT t.id 
              FROM tasks t 
              LEFT JOIN project_members pm ON t.project_id = pm.project_id 
              WHERE t.id = ? 
              AND (t.assigned_to = ? OR t.assigned_by = ? OR pm.user_id = ?)";
$check_stmt = $conn->prepare($check_sql);
$check_stmt->bind_param("iiii", $task_id, $user_id, $user_id, $user_id);
$check_stmt->execute();
$check_result = $check_stmt->get_result();

if ($check_result->num_rows == 0 && !Auth::hasRole('hr') && !Auth::hasRole('admin')) {
    setFlash('danger', 'You do not have access to this task.');
    header('Location: ' . Auth::getBasePath() . '/modules/tasks/my_tasks.php');
    exit();
}

// Add comment
$sql = "INSERT INTO task_comments (task_id, user_id, comment) VALUES (?, ?, ?)";
$stmt = $conn->prepare($sql);
$stmt->bind_param("iis", $task_id, $user_id, $comment);

if ($stmt->execute()) {
    $comment_id = $conn->insert_id;

    // Log activity
    logActivity('TASK_COMMENT', 'Comment added to task', 'task_comments', $comment_id);

    // Get task details with assignee and assigner info
    $task_sql = "SELECT t.*, u1.full_name as assigner_name, u1.id as assigner_id, u1.email as assigner_email,
                        u2.full_name as assignee_name, u2.id as assignee_id, u2.email as assignee_email
                 FROM tasks t
                 JOIN users u1 ON t.assigned_by = u1.id
                 JOIN users u2 ON t.assigned_to = u2.id
                 WHERE t.id = ?";
    $task_stmt = $conn->prepare($task_sql);
    $task_stmt->bind_param("i", $task_id);
    $task_stmt->execute();
    $task_data = $task_stmt->get_result()->fetch_assoc();

    if ($task_data) {
        $current_user = Auth::getCurrentUser();
        $notify_msg = $current_user['full_name'] . ' added a comment on task: "' . $task_data['title'] . '"';
        $notify_url = '/modules/tasks/view.php?id=' . $task_id;

        // Notify assigner if comment is NOT from them
        if ($task_data['assigner_id'] != $user_id) {
            createNotification($task_data['assigner_id'],
                'New Comment on Task',
                $notify_msg,
                'info',
                'task',
                $notify_url,
                $user_id
            );
        }

        // Notify assignee if comment is NOT from them
        if ($task_data['assignee_id'] != $user_id) {
            createNotification($task_data['assignee_id'],
                'New Comment on Task',
                $notify_msg,
                'info',
                'task',
                $notify_url,
                $user_id
            );
        }

        // Check if "notify team" was requested (via checkbox in form)
        $notify_team = isset($_POST['notify_team']) && $_POST['notify_team'] === '1';
        if ($notify_team && isNotificationEnabled('task')) {
            // Send email notification to both assigner and assignee
            $email_subject = "New Comment on Task: " . $task_data['title'];
            $email_body = "
                <div style='font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; padding: 20px;'>
                    <h2 style='color: #0d6efd;'>New Comment Added</h2>
                    <p>Hello <strong>" . htmlspecialchars($task_data['assignee_name']) . "</strong>,</p>
                    <p><strong>" . htmlspecialchars($current_user['full_name']) . "</strong> added a comment on task: <strong>" . htmlspecialchars($task_data['title']) . "</strong></p>
                    <div style='background: #f8f9fa; padding: 15px; border-radius: 5px; margin: 20px 0;'>
                        <p style='margin: 0;'>" . nl2br(htmlspecialchars($comment)) . "</p>
                    </div>
                    <p style='margin-top: 30px;'>Best regards,<br><strong>NexGen HRMS Team</strong></p>
                </div>
            ";
            sendEmailNotification($task_data['assignee_email'], $email_subject, $email_body);
            if ($task_data['assigner_email'] != $task_data['assignee_email']) {
                sendEmailNotification($task_data['assigner_email'], $email_subject, $email_body);
            }
        }
    }

    setFlash('success', 'Comment added successfully!');
} else {
    error_log("Error adding comment to task $task_id by user $user_id: " . $stmt->error);
    setFlash('danger', 'Error adding comment. Please try again.');
}

header('Location: ' . Auth::getBasePath() . '/modules/tasks/view.php?id=' . $task_id);
exit();
?>
