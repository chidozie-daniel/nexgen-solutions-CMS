<?php
define('APP_ENTRY_POINT', true);
require_once '../../includes/auth.php';
require_once '../../includes/functions.php';

Auth::requireLogin();

if (!Auth::hasRole('hr') && !Auth::hasRole('admin')) {
    setFlash('danger', 'You do not have permission to perform this action.');
    header('Location: ' . Auth::getBasePath() . '/dashboard.php');
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    setFlash('danger', 'Invalid request.');
    header('Location: ' . Auth::getBasePath() . '/modules/inquiries/list.php');
    exit();
}

if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
    setFlash('danger', 'Invalid security token. Please try again.');
    header('Location: ' . Auth::getBasePath() . '/modules/inquiries/list.php');
    exit();
}

$inquiry_id = (int)($_POST['inquiry_id'] ?? 0);
$call_notes = sanitizeText($_POST['call_notes'] ?? '', 2000, true);
$follow_up_date = $_POST['follow_up_date'] ?? '';
$mark_contacted = isset($_POST['mark_contacted']) ? 1 : 0;
$user_id = (int)($_SESSION['user_id'] ?? 0);

if ($inquiry_id <= 0) {
    setFlash('danger', 'Invalid inquiry.');
    header('Location: ' . Auth::getBasePath() . '/modules/inquiries/list.php');
    exit();
}
if ($call_notes === '' || strlen($call_notes) < 2) {
    setFlash('danger', 'Call notes are required.');
    header('Location: ' . Auth::getBasePath() . '/modules/inquiries/view.php?id=' . $inquiry_id);
    exit();
}
if ($follow_up_date !== '' && !isValidDate($follow_up_date, 'Y-m-d')) {
    setFlash('danger', 'Invalid follow-up date.');
    header('Location: ' . Auth::getBasePath() . '/modules/inquiries/view.php?id=' . $inquiry_id);
    exit();
}

$conn = getDBConnection();

// Insert call log activity
$stmt = $conn->prepare("INSERT INTO inquiry_activity (inquiry_id, user_id, activity_type, notes, follow_up_date) VALUES (?, ?, 'call', ?, ?)");
$fud = $follow_up_date !== '' ? $follow_up_date : null;
$stmt->bind_param("iiss", $inquiry_id, $user_id, $call_notes, $fud);
$stmt->execute();

// Optionally mark contacted
if ($mark_contacted) {
    $upd = $conn->prepare("UPDATE inquiries SET status = 'contacted', assigned_to = ? WHERE id = ?");
    $upd->bind_param("ii", $user_id, $inquiry_id);
    $upd->execute();
}

setFlash('success', 'Call log saved.');
header('Location: ' . Auth::getBasePath() . '/modules/inquiries/view.php?id=' . $inquiry_id);
exit();

