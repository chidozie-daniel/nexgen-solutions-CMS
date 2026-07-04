<?php
define('APP_ENTRY_POINT', true);
require_once '../../includes/auth.php';
require_once '../../includes/functions.php';

Auth::requireLogin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    setFlash('danger', 'Invalid request method.');
    header('Location: ' . Auth::getBasePath() . '/modules/tasks/my_tasks.php');
    exit();
}

if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
    setFlash('danger', 'Invalid security token. Please try again.');
    header('Location: ' . Auth::getBasePath() . '/modules/tasks/my_tasks.php');
    exit();
}

$task_id = (int)($_POST['task_id'] ?? 0);
$hours = $_POST['hours'] ?? '';
$note = sanitizeText($_POST['note'] ?? '', 255, true);

if ($task_id <= 0) {
    setFlash('danger', 'Invalid task.');
    header('Location: ' . Auth::getBasePath() . '/modules/tasks/my_tasks.php');
    exit();
}

if (!isNonNegativeNumber($hours) || (float)$hours <= 0 || (float)$hours > 24) {
    setFlash('danger', 'Hours must be a number between 0 and 24.');
    header('Location: ' . Auth::getBasePath() . '/modules/tasks/view.php?id=' . $task_id);
    exit();
}

$conn = getDBConnection();
$user_id = (int)($_SESSION['user_id'] ?? 0);

// Verify access to task
$sql = "SELECT t.id, t.assigned_to, t.assigned_by, p.project_leader
        FROM tasks t
        LEFT JOIN projects p ON t.project_id = p.id
        WHERE t.id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $task_id);
$stmt->execute();
$task = $stmt->get_result()->fetch_assoc();

if (!$task) {
    setFlash('danger', 'Task not found.');
    header('Location: ' . Auth::getBasePath() . '/modules/tasks/my_tasks.php');
    exit();
}

$can_log = false;
if ((int)$task['assigned_to'] === $user_id || (int)$task['assigned_by'] === $user_id) $can_log = true;
if (Auth::hasRole('admin') || Auth::hasRole('hr')) $can_log = true;
if (Auth::hasRole('project_leader') && (int)($task['project_leader'] ?? 0) === $user_id) $can_log = true;

if (!$can_log) {
    setFlash('danger', 'You do not have permission to log time on this task.');
    header('Location: ' . Auth::getBasePath() . '/modules/tasks/view.php?id=' . $task_id);
    exit();
}

// Log time entry
$logged_at = date('Y-m-d');
$ins = $conn->prepare("INSERT INTO task_time_logs (task_id, user_id, hours, note, logged_at) VALUES (?, ?, ?, ?, ?)");
$h = (float)$hours;
$ins->bind_param("iidss", $task_id, $user_id, $h, $note, $logged_at);

if ($ins->execute()) {
    logActivity('TASK_TIME', "Time logged: {$h}h", 'tasks', $task_id, null, ['hours' => $h]);
    setFlash('success', 'Time logged successfully.');
} else {
    error_log("Error logging time for task $task_id by user $user_id: " . $ins->error);
    setFlash('danger', 'Error logging time. Please try again.');
}
header('Location: ' . Auth::getBasePath() . '/modules/tasks/view.php?id=' . $task_id);
exit();

