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
if ($task_id <= 0) {
    setFlash('danger', 'Invalid task.');
    header('Location: ' . Auth::getBasePath() . '/modules/tasks/my_tasks.php');
    exit();
}

if (!isset($_FILES['attachment']) || $_FILES['attachment']['error'] !== UPLOAD_ERR_OK) {
    setFlash('danger', 'Please select a file to upload.');
    header('Location: ' . Auth::getBasePath() . '/modules/tasks/view.php?id=' . $task_id);
    exit();
}

$conn = getDBConnection();
$user_id = (int)($_SESSION['user_id'] ?? 0);

// Load task + permissions
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
    header('Location: ' . Auth::getBasePath() . '/modules/tasks/my_tasks.php');
    exit();
}

$can_upload = false;
if ($task['assigned_to'] == $user_id || $task['assigned_by'] == $user_id) $can_upload = true;
if (Auth::hasRole('admin') || Auth::hasRole('hr')) $can_upload = true;
if (Auth::hasRole('project_leader') && (int)($task['project_leader'] ?? 0) === $user_id) $can_upload = true;

if (!$can_upload) {
    setFlash('danger', 'You do not have permission to upload attachments for this task.');
    header('Location: ' . Auth::getBasePath() . '/modules/tasks/view.php?id=' . $task_id);
    exit();
}

$file = $_FILES['attachment'];
$orig_name = $file['name'] ?? 'attachment';
$ext = strtolower(pathinfo($orig_name, PATHINFO_EXTENSION));
$allowed = ['pdf','doc','docx','xls','xlsx','png','jpg','jpeg'];
if (!in_array($ext, $allowed, true)) {
    setFlash('danger', 'Invalid file type. Allowed: ' . implode(', ', $allowed));
    header('Location: ' . Auth::getBasePath() . '/modules/tasks/view.php?id=' . $task_id);
    exit();
}

if (($file['size'] ?? 0) > 10 * 1024 * 1024) {
    setFlash('danger', 'File too large. Maximum size is 10MB.');
    header('Location: ' . Auth::getBasePath() . '/modules/tasks/view.php?id=' . $task_id);
    exit();
}

$upload_dir = dirname(dirname(__FILE__)) . '/../uploads/task_attachments';
if (!is_dir($upload_dir)) {
    @mkdir($upload_dir, 0755, true);
}

$safe_name = 'task_' . $task_id . '_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
$dest_path = $upload_dir . '/' . $safe_name;

if (!move_uploaded_file($file['tmp_name'], $dest_path)) {
    setFlash('danger', 'Upload failed. Please try again.');
    header('Location: ' . Auth::getBasePath() . '/modules/tasks/view.php?id=' . $task_id);
    exit();
}

// Insert attachment record
$sql = "INSERT INTO task_attachments (task_id, filename, original_filename, file_size, uploaded_by) VALUES (?, ?, ?, ?, ?)";
$stmt = $conn->prepare($sql);
$orig_name = $file['name'] ?? 'attachment';
$file_size = $file['size'] ?? 0;
$stmt->bind_param("issii", $task_id, $safe_name, $orig_name, $file_size, $user_id);

if ($stmt->execute()) {
    logActivity('TASK_ATTACHMENT', 'Task attachment uploaded', 'task_attachments', $conn->insert_id, null, [
        'filename' => $safe_name,
        'original_name' => $orig_name,
        'uploaded_by' => $user_id
    ]);

    setFlash('success', 'Attachment uploaded successfully.');
} else {
    // Clean up uploaded file if database insert failed
    @unlink($dest_path);
    error_log("Error inserting attachment record for task $task_id: " . $stmt->error);
    setFlash('danger', 'Error saving attachment record. Please try again.');
}
header('Location: ' . Auth::getBasePath() . '/modules/tasks/view.php?id=' . $task_id);
exit();

