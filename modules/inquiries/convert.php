<?php
// Define entry point constant
define('APP_ENTRY_POINT', true);

require_once '../../includes/auth.php';
require_once '../../includes/functions.php';

// Check login status first
Auth::requireLogin();

// Only HR and Admin can convert inquiries
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
$user_id = (int)($_SESSION['user_id'] ?? 0);

$inquiry_id = $_POST['inquiry_id'];
$client_type = sanitizeText($_POST['client_type'] ?? '', 50);
$sales_rep = $_POST['sales_rep'] ?? null;
$estimated_value = $_POST['estimated_value'] ?? 0;
$notes = sanitizeText($_POST['notes'] ?? '', 1000, true);

$errors = [];
if (!is_numeric($inquiry_id) || (int)$inquiry_id <= 0) {
    $errors[] = 'Invalid inquiry.';
}
$allowed_client_types = ['corporate', 'individual', 'government', 'non_profit'];
if ($client_type === '' || !in_array(strtolower($client_type), $allowed_client_types, true)) {
    $errors[] = 'Invalid client type.';
}
if ($estimated_value !== '' && !isNonNegativeNumber($estimated_value)) {
    $errors[] = 'Estimated value must be a non-negative number.';
} else {
    $estimated_value = (float)$estimated_value;
}

if ($sales_rep !== null && $sales_rep !== '' && (!is_numeric($sales_rep) || (int)$sales_rep <= 0)) {
    $errors[] = 'Invalid sales rep.';
} else {
    $sales_rep = ($sales_rep === '' || $sales_rep === null) ? null : (int)$sales_rep;
}

if (!empty($errors)) {
    setFlash('danger', implode('<br>', $errors));
    header('Location: ' . Auth::getBasePath() . '/modules/inquiries/view.php?id=' . $inquiry_id);
    exit();
}

// Update inquiry status
$update_sql = "UPDATE inquiries 
               SET status = 'converted', 
                   assigned_to = COALESCE(?, assigned_to),
                   notes = CONCAT(IFNULL(notes, ''), '\nConverted to client. Type: ', ?, '. Estimated Value: $', ?, '. Notes: ', ?) 
               WHERE id = ?";
$update_stmt = $conn->prepare($update_sql);
$update_stmt->bind_param("isdsi", $sales_rep, $client_type, $estimated_value, $notes, $inquiry_id);

if ($update_stmt->execute()) {
    if ($user_id > 0) {
        $log_stmt = $conn->prepare("INSERT INTO inquiry_activity (inquiry_id, user_id, activity_type, notes) VALUES (?, ?, 'convert', ?)");
        $log_notes = "Converted inquiry. Client type: " . $client_type . ". Estimated value: $" . number_format((float)$estimated_value, 2);
        $log_stmt->bind_param("iis", $inquiry_id, $user_id, $log_notes);
        $log_stmt->execute();
    }
    setFlash('success', 'Inquiry successfully converted to client status.');
} else {
    setFlash('danger', 'Error converting inquiry.');
}

header('Location: ' . Auth::getBasePath() . '/modules/inquiries/view.php?id=' . $inquiry_id);
exit();
?>
