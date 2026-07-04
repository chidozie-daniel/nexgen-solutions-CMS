<?php
// Define entry point constant to allow access
define('APP_ENTRY_POINT', true);

require_once '../../includes/auth.php';
require_once '../../includes/functions.php';

// Check login status first
Auth::requireLogin();

if ($_SERVER['REQUEST_METHOD'] != 'POST' || !isset($_POST['task_id'])) {
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
$user_id = $_SESSION['user_id'];

// Get task details to check permissions
$check_sql = "SELECT assigned_by, assigned_to FROM tasks WHERE id = ?";
$check_stmt = $conn->prepare($check_sql);
$check_stmt->bind_param("i", $task_id);
$check_stmt->execute();
$check_result = $check_stmt->get_result();
$task = $check_result->fetch_assoc();

if (!$task) {
    setFlash('danger', 'Task not found.');
    header('Location: ' . Auth::getBasePath() . '/modules/tasks/my_tasks.php');
    exit();
}

// Check if user can update this task
$can_update = false;
if ($task['assigned_by'] == $user_id || $task['assigned_to'] == $user_id || 
    Auth::hasRole('hr') || Auth::hasRole('admin')) {
    $can_update = true;
}

if (!$can_update) {
    setFlash('danger', 'You do not have permission to update this task.');
    header('Location: ' . Auth::getBasePath() . '/modules/tasks/my_tasks.php');
    exit();
}

// Update task
$title = sanitizeText($_POST['title'] ?? '', 120);
$description = sanitizeText($_POST['description'] ?? '', 2000, true);
$priority = $_POST['priority'] ?? '';
$due_date = $_POST['due_date'] ?? null;

// Validation
$errors = [];
if ($title === '') {
    $errors[] = 'Task title is required.';
}
$allowed_priority = ['low', 'medium', 'high', 'critical'];
if (!in_array($priority, $allowed_priority, true)) {
    $errors[] = 'Invalid priority selected.';
}
if ($due_date && !isValidDate($due_date)) {
    $errors[] = 'Invalid due date.';
} elseif ($due_date && strtotime($due_date) < strtotime(date('Y-m-d'))) {
    $errors[] = 'Due date cannot be in the past.';
}
if (!empty($errors)) {
    setFlash('danger', implode('<br>', $errors));
    header('Location: ' . Auth::getBasePath() . '/modules/tasks/view.php?id=' . $task_id);
    exit();
}

$sql = "UPDATE tasks SET 
        title = ?, 
        description = ?, 
        priority = ?, 
        due_date = ?, 
        updated_at = NOW() 
        WHERE id = ?";

$stmt = $conn->prepare($sql);
$stmt->bind_param("ssssi", $title, $description, $priority, $due_date, $task_id);

if ($stmt->execute()) {
    setFlash('success', 'Task updated successfully!');
} else {
    setFlash('danger', 'Error updating task.');
}

     header('Location: ' . Auth::getBasePath() . '/modules/tasks/view.php?id=' . $task_id);
exit();
?>
