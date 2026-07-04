<?php
// Define entry point constant
define('APP_ENTRY_POINT', true);

require_once '../../includes/auth.php';
require_once '../../includes/functions.php';

// Check login status first
Auth::requireLogin();

// Only HR and Admin can close inquiries
if (!Auth::hasRole('hr') && !Auth::hasRole('admin')) {
    setFlash('danger', 'You do not have permission to perform this action.');
    header('Location: ' . Auth::getBasePath() . '/dashboard.php');
    exit();
}

if ($_SERVER['REQUEST_METHOD'] != 'POST' || !isset($_POST['inquiry_id'])) {
    setFlash('danger', 'Invalid request.');
    header('Location: ' . Auth::getBasePath() . '/modules/inquiries/list.php');
    exit();
}

// Verify CSRF token
if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
    setFlash('danger', 'Invalid security token. Please try again.');
    header('Location: ' . Auth::getBasePath() . '/modules/inquiries/list.php');
    exit();
}

$conn = getDBConnection();
$inquiry_id = $_POST['inquiry_id'];
$close_reason = sanitizeText($_POST['close_reason'] ?? '', 200);
$notes = sanitizeText($_POST['notes'] ?? '', 1000, true);
$user_id = (int)($_SESSION['user_id'] ?? 0);

if (!is_numeric($inquiry_id) || (int)$inquiry_id <= 0) {
    setFlash('danger', 'Invalid inquiry.');
    header('Location: ' . Auth::getBasePath() . '/modules/inquiries/list.php');
    exit();
}
if ($close_reason === '') {
    setFlash('danger', 'Close reason is required.');
    header('Location: ' . Auth::getBasePath() . '/modules/inquiries/view.php?id=' . $inquiry_id);
    exit();
}

// Update inquiry status
$update_sql = "UPDATE inquiries SET status = 'closed', notes = CONCAT(IFNULL(notes, ''), '\nClosed. Reason: ', ?, '. Notes: ', ?) WHERE id = ?";
$update_stmt = $conn->prepare($update_sql);
$update_stmt->bind_param("ssi", $close_reason, $notes, $inquiry_id);

if ($update_stmt->execute()) {
    // Log activity
    if ($user_id > 0) {
        $log_stmt = $conn->prepare("INSERT INTO inquiry_activity (inquiry_id, user_id, activity_type, notes) VALUES (?, ?, 'close', ?)");
        $log_notes = "Closed inquiry. Reason: " . $close_reason;
        if ($notes !== '') $log_notes .= ". Notes: " . $notes;
        $log_stmt->bind_param("iis", $inquiry_id, $user_id, $log_notes);
        $log_stmt->execute();
        $log_stmt->close();
    }
    setFlash('success', 'Inquiry closed successfully.');
} else {
    setFlash('danger', 'Error closing inquiry.');
}

header('Location: ' . Auth::getBasePath() . '/modules/inquiries/view.php?id=' . $inquiry_id);
exit();
?>
