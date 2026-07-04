<?php
// Define entry point constant to allow access
define('APP_ENTRY_POINT', true);

require_once '../../includes/auth.php';
require_once '../../includes/functions.php';

// Check login status and require authentication
Auth::requireLogin();

// Must be POST request
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    setFlash('danger', 'Invalid request method.');
    header('Location: ' . Auth::getBasePath() . '/modules/tasks/my_tasks.php');
    exit();
}

// Verify CSRF token
if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
    setFlash('danger', 'Invalid security token. Please try again.');
    header('Location: ' . Auth::getBasePath() . '/modules/tasks/my_tasks.php');
    exit();
}

if (!isset($_POST['id']) || !is_numeric($_POST['id'])) {
    setFlash('danger', 'Invalid task ID.');
    header('Location: ' . Auth::getBasePath() . '/modules/tasks/my_tasks.php');
    exit();
}

$task_id = (int)$_POST['id'];
$user_id = $_SESSION['user_id'];
$role = $_SESSION['role'];
$conn = getDBConnection();

// Get task details to check permissions
$sql = "SELECT t.*, p.project_leader
        FROM tasks t
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

// Check permissions
// Can delete if: Admin, HR, Project Leader of project, or Creator of task
$can_delete = false;
if (Auth::hasRole('admin') || Auth::hasRole('hr')) {
    $can_delete = true;
} elseif ($task['project_leader'] == $user_id) {
    $can_delete = true;
} elseif ($task['assigned_by'] == $user_id) {
    $can_delete = true;
}

if (!$can_delete) {
    setFlash('danger', 'You do not have permission to delete this task.');
    header('Location: ' . Auth::getBasePath() . '/modules/tasks/view.php?id=' . $task_id);
    exit();
}

// Perform Deletion (Transaction to delete comments and time logs too)
$conn->begin_transaction();

try {
    // Get task details for notification BEFORE deleting
    $notify_assignee = isset($_POST['notify_assignee']) && $_POST['notify_assignee'] === '1';
    $task_info = null;
    if ($notify_assignee) {
        $task_sql = "SELECT t.*, u.full_name as assignee_name, u.email as assignee_email
                     FROM tasks t
                     JOIN users u ON t.assigned_to = u.id
                     WHERE t.id = ?";
        $task_stmt = $conn->prepare($task_sql);
        $task_stmt->bind_param("i", $task_id);
        $task_stmt->execute();
        $task_info = $task_stmt->get_result()->fetch_assoc();
    }

    // Delete comments
    $del_comments = "DELETE FROM task_comments WHERE task_id = ?";
    $stmt = $conn->prepare($del_comments);
    $stmt->bind_param("i", $task_id);
    $stmt->execute();

    // Delete time logs
    $del_time = "DELETE FROM task_time_logs WHERE task_id = ?";
    $stmt = $conn->prepare($del_time);
    $stmt->bind_param("i", $task_id);
    $stmt->execute();

    // Delete task
    $del_task = "DELETE FROM tasks WHERE id = ?";
    $stmt = $conn->prepare($del_task);
    $stmt->bind_param("i", $task_id);
    if (!$stmt->execute()) {
        throw new Exception("Error deleting task.");
    }

    $conn->commit();

    // Send notification AFTER successful deletion
    if ($notify_assignee && $task_info) {
        createNotification($task_info['assigned_to'],
            'Task Deleted',
            'The task "' . $task_info['title'] . '" has been deleted.',
            'info',
            'task',
            '/modules/tasks/my_tasks.php',
            $user_id
        );
    }

    setFlash('success', 'Task deleted successfully.');
    header('Location: ' . Auth::getBasePath() . '/modules/tasks/my_tasks.php');

} catch (Exception $e) {
    $conn->rollback();
    setFlash('danger', 'Error deleting task: ' . $e->getMessage());
    header('Location: ' . Auth::getBasePath() . '/modules/tasks/view.php?id=' . $task_id);
}

exit();
?>
