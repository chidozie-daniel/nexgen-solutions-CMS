<?php
// Define entry point constant
define('APP_ENTRY_POINT', true);

require_once '../../includes/auth.php';
require_once '../../includes/functions.php';

// Check login status first
Auth::requireLogin();

// Only HR and Admin can send replies
if (!Auth::hasRole('hr') && !Auth::hasRole('admin')) {
    setFlash('danger', 'You do not have permission to send replies.');
    header('Location: ' . Auth::getBasePath() . '/dashboard.php');
    exit();
}

if ($_SERVER['REQUEST_METHOD'] != 'POST' || !isset($_POST['inquiry_id']) || !isset($_POST['to_email'])) {
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
$to_email = trim($_POST['to_email'] ?? '');
$subject = sanitizeText($_POST['subject'] ?? '', 200);
$message = sanitizeText($_POST['message'] ?? '', 4000, true);
$mark_contacted = isset($_POST['mark_contacted']) ? true : false;
$user_id = $_SESSION['user_id'];

$errors = [];
if (!is_numeric($inquiry_id) || (int)$inquiry_id <= 0) {
    $errors[] = 'Invalid inquiry.';
}
if (!isValidEmail($to_email)) {
    $errors[] = 'Invalid recipient email.';
}
if ($subject === '') {
    $errors[] = 'Subject is required.';
}
if ($message === '' || strlen($message) < 5) {
    $errors[] = 'Message is required.';
}
if (!empty($errors)) {
    setFlash('danger', implode('<br>', $errors));
    header('Location: ' . Auth::getBasePath() . '/modules/inquiries/view.php?id=' . $inquiry_id);
    exit();
}

// Get inquiry details
$sql = "SELECT * FROM inquiries WHERE id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $inquiry_id);
$stmt->execute();
$result = $stmt->get_result();
$inquiry = $result->fetch_assoc();

if (!$inquiry) {
    setFlash('danger', 'Inquiry not found.');
    header('Location: ' . Auth::getBasePath() . '/modules/inquiries/list.php');
    exit();
}

// Update inquiry status if marked as contacted
if ($mark_contacted) {
    $update_sql = "UPDATE inquiries SET status = 'contacted', assigned_to = ? WHERE id = ?";
    $update_stmt = $conn->prepare($update_sql);
    $update_stmt->bind_param("ii", $user_id, $inquiry_id);
    $update_stmt->execute();
}

// Load email configuration
require_once '../../includes/email_config.php';

// Send the email
$email_sent = sendEmailNotification($to_email, $subject, $message);

// Log the email to database
$log_sql = "INSERT INTO email_logs (inquiry_id, user_id, recipient, subject, message, status, sent_at) 
            VALUES (?, ?, ?, ?, ?, ?, NOW())";
$log_stmt = $conn->prepare($log_sql);
$email_status = $email_sent ? 'sent' : 'failed';
$log_stmt->bind_param("iissss", $inquiry_id, $user_id, $to_email, $subject, $message, $email_status);
$log_stmt->execute();

if ($email_sent) {
    setFlash('success', 'Reply sent successfully! Email has been delivered.');
} else {
    setFlash('warning', 'Reply saved, but email delivery failed. Please check your email configuration.');
}
header('Location: ' . Auth::getBasePath() . '/modules/inquiries/view.php?id=' . $inquiry_id);
exit();
?>
