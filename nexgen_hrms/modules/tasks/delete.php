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

// Perform Deletion (Transaction to delete comments too)
$conn->begin_transaction();

try {
    // Delete comments
    $del_comments = "DELETE FROM task_comments WHERE task_id = ?";
    $stmt = $conn->prepare($del_comments);
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
    setFlash('success', 'Task deleted successfully.');
    header('Location: ' . Auth::getBasePath() . '/modules/tasks/my_tasks.php');
    
} catch (Exception $e) {
    $conn->rollback();
    setFlash('danger', 'Error deleting task: ' . $e->getMessage());
    header('Location: ' . Auth::getBasePath() . '/modules/tasks/view.php?id=' . $task_id);
}

exit();
?>
