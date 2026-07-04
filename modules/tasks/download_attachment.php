<?php
define('APP_ENTRY_POINT', true);
require_once '../../includes/auth.php';
require_once '../../includes/functions.php';

Auth::requireLogin();

$attachment_id = (int)($_GET['id'] ?? 0);
if ($attachment_id <= 0) {
    setFlash('danger', 'Invalid attachment.');
    header('Location: ' . Auth::getBasePath() . '/modules/tasks/my_tasks.php');
    exit();
}

$conn = getDBConnection();
$user_id = (int)($_SESSION['user_id'] ?? 0);

// Get attachment and verify access
$sql = "SELECT ta.filename, ta.original_filename, ta.task_id, t.assigned_to, t.assigned_by, p.project_leader
        FROM task_attachments ta
        JOIN tasks t ON ta.task_id = t.id
        LEFT JOIN projects p ON t.project_id = p.id
        WHERE ta.id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $attachment_id);
$stmt->execute();
$attachment = $stmt->get_result()->fetch_assoc();

if (!$attachment) {
    setFlash('danger', 'Attachment not found.');
    header('Location: ' . Auth::getBasePath() . '/modules/tasks/my_tasks.php');
    exit();
}

// Verify access
$can_view = false;
if ((int)$attachment['assigned_to'] === $user_id) $can_view = true;
if ((int)$attachment['assigned_by'] === $user_id) $can_view = true;
if (Auth::hasRole('admin') || Auth::hasRole('hr')) $can_view = true;
if (Auth::hasRole('project_leader') && (int)($attachment['project_leader'] ?? 0) === $user_id) $can_view = true;

if (!$can_view) {
    setFlash('danger', 'You do not have permission to view this attachment.');
    header('Location: ' . Auth::getBasePath() . '/modules/tasks/view.php?id=' . $attachment['task_id']);
    exit();
}

$upload_dir = dirname(dirname(__FILE__)) . '/../uploads/task_attachments';
$path = $upload_dir . '/' . $attachment['filename'];

if (!is_file($path)) {
    setFlash('danger', 'File not found on server.');
    header('Location: ' . Auth::getBasePath() . '/modules/tasks/view.php?id=' . $attachment['task_id']);
    exit();
}

// Serve file
header('Content-Type: application/octet-stream');
header('Content-Disposition: attachment; filename="' . addslashes($attachment['original_filename']) . '"');
header('Content-Length: ' . @filesize($path));
readfile($path);
exit();
