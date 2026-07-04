<?php
define('APP_ENTRY_POINT', true);
require_once '../../includes/auth.php';
require_once '../../includes/functions.php';

Auth::requireLogin();

if (!Auth::hasRole('hr') && !Auth::hasRole('admin')) {
    setFlash('danger', 'You do not have permission to export newsletter subscribers.');
    header('Location: ' . Auth::getBasePath() . '/dashboard.php');
    exit();
}

$conn = getDBConnection();

// Get all active subscribers
$sql = "SELECT email, name, phone, source, subscribed_at, total_emails_sent, notes 
        FROM newsletter_subscribers 
        WHERE status = 'active'
        ORDER BY subscribed_at DESC";
$result = $conn->query($sql);

// Set headers for CSV download
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="newsletter_subscribers_' . date('Y-m-d') . '.csv"');

// Open output stream
$output = fopen('php://output', 'w');

// Add BOM for UTF-8 to support Excel opening with proper UTF-8
fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));

// Add column headers
fputcsv($output, [
    'Email',
    'Name', 
    'Phone',
    'Source',
    'Subscribed Date',
    'Total Emails Sent',
    'Notes'
]);

// Add data rows
while ($row = $result->fetch_assoc()) {
    fputcsv($output, [
        $row['email'],
        $row['name'] ?? '',
        $row['phone'] ?? '',
        $row['source'],
        date('Y-m-d', strtotime($row['subscribed_at'])),
        $row['total_emails_sent'],
        $row['notes'] ?? ''
    ]);
}

fclose($output);
exit();
