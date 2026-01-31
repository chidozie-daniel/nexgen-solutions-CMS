<?php
require_once '../../includes/auth.php';
require_once '../../includes/functions.php';

// Check login status first
Auth::requireLogin();

if ($_SERVER['REQUEST_METHOD'] != 'POST' || !isset($_POST['task_id']) || !isset($_POST['project_id'])) {
    setFlash('danger', 'Invalid request.');
    if (isset($_POST['project_id'])) {
        header('Location: ' . Auth::getBasePath() . '/modules/projects/details.php?id=' . $_POST['project_id']);
    } else {
        header('Location: ' . Auth::getBasePath() . '/modules/projects/index.php');
    }
    exit();
}

$task_id = $_POST['task_id'];
$project_id = $_POST['project_id'];
$user_id = $_SESSION['user_id'];
$role = $_SESSION['role'];

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

// Delete task
$sql = "DELETE FROM tasks WHERE id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $task_id);

if ($stmt->execute()) {
    setFlash('success', 'Task deleted successfully.');
} else {
    setFlash('danger', 'Error deleting task.');
}

header('Location: ' . Auth::getBasePath() . '/modules/projects/details.php?id=' . $project_id);
exit();
?>
