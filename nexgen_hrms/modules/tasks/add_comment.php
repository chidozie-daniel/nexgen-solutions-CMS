<?php
require_once '../../includes/auth.php';
require_once '../../includes/functions.php';

// Check login status first
Auth::requireLogin();

if ($_SERVER['REQUEST_METHOD'] != 'POST' || !isset($_POST['task_id']) || !isset($_POST['comment'])) {
    setFlash('danger', 'Invalid request.');
    header('Location: ' . Auth::getBasePath() . '/modules/tasks/my_tasks.php');
    exit();
}

$conn = getDBConnection();
$task_id = $_POST['task_id'];
$comment = $_POST['comment'];
$user_id = $_SESSION['user_id'];

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
    setFlash('success', 'Comment added successfully!');
} else {
    setFlash('danger', 'Error adding comment.');
}

header('Location: ' . Auth::getBasePath() . '/modules/tasks/view.php?id=' . $task_id);
exit();
?>