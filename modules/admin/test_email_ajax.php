<?php
define('APP_ENTRY_POINT', true);
require_once '../../includes/auth.php';
require_once '../../includes/functions.php';
require_once '../../includes/email_config.php';

Auth::requireRole('admin');

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method.']);
    exit();
}

if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
    echo json_encode(['success' => false, 'message' => 'Invalid security token. Please refresh the page and try again.']);
    exit();
}

$email = $_POST['email'] ?? '';

if (empty($email) || !isValidEmail($email)) {
    echo json_encode(['success' => false, 'message' => 'Please enter a valid email address.']);
    exit();
}

$subject = "NexGen HRMS - SMTP Test Email";
$message = "This is a test email from your NexGen HRMS System Settings.\n\n";
$message .= "If you are receiving this, your SMTP configuration is working correctly!\n\n";
$message .= "Timestamp: " . date('Y-m-d H:i:s');

if (sendEmailNotification($email, $subject, $message)) {
    echo json_encode(['success' => true, 'message' => 'Test email sent successfully to ' . $email]);
} else {
    echo json_encode(['success' => false, 'message' => 'Failed to send test email. Check PHP error logs for details.']);
}
?>
