<?php
// Define entry point constant to allow access
define('APP_ENTRY_POINT', true);

require_once '../../includes/auth.php';
require_once '../../includes/functions.php';

// Check login status and require project leader, HR, or admin role
Auth::requireRole(['project_leader', 'hr', 'admin']);

if ($_SERVER['REQUEST_METHOD'] != 'POST' || !isset($_POST['task_id']) || !isset($_POST['project_id'])) {
    setFlash('danger', 'Invalid request.');
    if (isset($_POST['project_id'])) {
        header('Location: ' . Auth::getBasePath() . '/modules/projects/details.php?id=' . $_POST['project_id']);
    } else {
        header('Location: ' . Auth::getBasePath() . '/modules/projects/index.php');
    }
    exit();
}

// Verify CSRF token
if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
    setFlash('danger', 'Invalid security token. Please try again.');
    header('Location: ' . Auth::getBasePath() . '/modules/projects/index.php');
    exit();
}

$task_id = $_POST['task_id'];
$project_id = $_POST['project_id'];
$user_id = $_SESSION['user_id'];
$role = $_SESSION['role'];

if (!is_numeric($task_id) || (int)$task_id <= 0 || !is_numeric($project_id) || (int)$project_id <= 0) {
    setFlash('danger', 'Invalid task or project.');
    header('Location: ' . Auth::getBasePath() . '/modules/projects/index.php');
    exit();
}

$conn = getDBConnection();

// Check permissions (Admin, HR, or Project Leader)
// We need to fetch the project leader to confirm
$proj_sql = "SELECT project_leader FROM projects WHERE id = ?";
$proj_stmt = $conn->prepare($proj_sql);
$proj_stmt->bind_param("i", $project_id);
$proj_stmt->execute();
$project = $proj_stmt->get_result()->fetch_assoc();

if (!$project) {
    setFlash('danger', 'Project not found.');
    header('Location: ' . Auth::getBasePath() . '/modules/projects/index.php');
    exit();
}

if ($role != 'admin' && $role != 'hr' && $project['project_leader'] != $user_id) {
    setFlash('danger', 'You do not have permission to delete tasks.');
    header('Location: ' . Auth::getBasePath() . '/modules/projects/details.php?id=' . $project_id);
    exit();
}

// Delete task and related comments
$conn->begin_transaction();
try {
    // First delete all comments related to this task
    $delete_comments_sql = "DELETE FROM task_comments WHERE task_id = ?";
    $comments_stmt = $conn->prepare($delete_comments_sql);
    if ($comments_stmt) {
        $comments_stmt->bind_param("i", $task_id);
        $comments_stmt->execute();
        $comments_stmt->close();
    }
    
    // Then delete the task
    $sql = "DELETE FROM tasks WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $task_id);
    
    if ($stmt->execute()) {
        $stmt->close();
        $conn->commit();
        setFlash('success', 'Task and related comments deleted successfully.');
    } else {
        $stmt->close();
        throw new Exception('Failed to delete task');
    }
} catch (Exception $e) {
    $conn->rollback();
    error_log("Error deleting task $task_id: " . $e->getMessage());
    setFlash('danger', 'Error deleting task. Please try again.');
}

header('Location: ' . Auth::getBasePath() . '/modules/projects/details.php?id=' . $project_id);
exit();
?>
