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
$attachment_id = (int)($_POST['attachment_id'] ?? 0);

if ($task_id <= 0 || $attachment_id <= 0) {
    setFlash('danger', 'Invalid request.');
    header('Location: ' . Auth::getBasePath() . '/modules/tasks/my_tasks.php');
    exit();
}

$conn = getDBConnection();
$user_id = (int)($_SESSION['user_id'] ?? 0);

// Get attachment details
$sql = "SELECT ta.filename, ta.original_filename, t.assigned_to, t.assigned_by, p.project_leader
        FROM task_attachments ta
        JOIN tasks t ON ta.task_id = t.id
        LEFT JOIN projects p ON t.project_id = p.id
        WHERE ta.id = ? AND ta.task_id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("ii", $attachment_id, $task_id);
$stmt->execute();
$attachment = $stmt->get_result()->fetch_assoc();

if (!$attachment) {
    setFlash('danger', 'Attachment not found.');
    header('Location: ' . Auth::getBasePath() . '/modules/tasks/view.php?id=' . $task_id);
    exit();
}

$can_delete = false;
if ((int)$attachment['assigned_by'] === $user_id) $can_delete = true;
if (Auth::hasRole('admin') || Auth::hasRole('hr')) $can_delete = true;
if (Auth::hasRole('project_leader') && (int)($attachment['project_leader'] ?? 0) === $user_id) $can_delete = true;

if (!$can_delete) {
    setFlash('danger', 'You do not have permission to delete this attachment.');
    header('Location: ' . Auth::getBasePath() . '/modules/tasks/view.php?id=' . $task_id);
    exit();
}

// Delete file
$upload_dir = dirname(dirname(__FILE__)) . '/../uploads/task_attachments';
$path = $upload_dir . '/' . basename($attachment['filename']);
if (is_file($path)) {
    @unlink($path);
}

// Delete record
$del = $conn->prepare("DELETE FROM task_attachments WHERE id = ?");
$del->bind_param("i", $attachment_id);
$del->execute();

logActivity('TASK_ATTACHMENT_DELETE', 'Task attachment deleted: ' . $attachment['original_filename'], 'task_attachments', $attachment_id);

setFlash('success', 'Attachment deleted.');
header('Location: ' . Auth::getBasePath() . '/modules/tasks/view.php?id=' . $task_id);
exit();
