<?php
define('APP_ENTRY_POINT', true);
require_once '../../includes/auth.php';
require_once '../../includes/functions.php';

Auth::requireLogin();

if (!Auth::hasRole('hr') && !Auth::hasRole('admin')) {
    setFlash('danger', 'You do not have permission to manage newsletter subscribers.');
    header('Location: ' . Auth::getBasePath() . '/dashboard.php');
    exit();
}

$conn = getDBConnection();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_subscriber'])) {
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        setFlash('danger', 'Invalid security token.');
        header('Location: list.php');
        exit();
    }

    $email = trim($_POST['email'] ?? '');
    $name = sanitizeText($_POST['name'] ?? '', 100);
    $phone = sanitizeText($_POST['phone'] ?? '', 20);
    $notes = sanitizeText($_POST['notes'] ?? '', 4000, true);

    $errors = [];
    if (!isValidEmail($email)) {
        $errors[] = 'Valid email is required.';
    }

    if (empty($errors)) {
        // Check if email already exists
        $check_sql = "SELECT id FROM newsletter_subscribers WHERE email = ?";
        $check_stmt = $conn->prepare($check_sql);
        $check_stmt->bind_param("s", $email);
        $check_stmt->execute();
        $check_result = $check_stmt->get_result();
        
        if ($check_result->num_rows > 0) {
            setFlash('warning', 'This email is already subscribed.');
        } else {
            $sql = "INSERT INTO newsletter_subscribers (email, name, phone, source, status, notes) 
                    VALUES (?, ?, ?, 'manual_entry', 'active', ?)";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("ssss", $email, $name, $phone, $notes);
            
            if ($stmt->execute()) {
                setFlash('success', 'Subscriber added successfully.');
            } else {
                setFlash('danger', 'Failed to add subscriber. Please try again.');
            }
        }
    } else {
        setFlash('danger', implode('<br>', $errors));
    }
}

header('Location: list.php');
exit();
