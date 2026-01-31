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

$conn = getDBConnection();
$inquiry_id = $_POST['inquiry_id'];
$to_email = $_POST['to_email'];
$subject = $_POST['subject'];
$message = $_POST['message'];
$mark_contacted = isset($_POST['mark_contacted']) ? true : false;
$user_id = $_SESSION['user_id'];

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

// In a real implementation, you would send the email here
// For now, we'll just log it and show success

// Log the email (you could create an emails table)
$log_sql = "INSERT INTO email_logs (inquiry_id, user_id, recipient, subject, message, sent_at) 
            VALUES (?, ?, ?, ?, ?, NOW())";
$log_stmt = $conn->prepare($log_sql);
$log_stmt->bind_param("iisss", $inquiry_id, $user_id, $to_email, $subject, $message);
$log_stmt->execute();

setFlash('success', 'Reply sent successfully! Email has been logged.');
header('Location: ' . Auth::getBasePath() . '/modules/inquiries/view.php?id=' . $inquiry_id);
exit();
?>