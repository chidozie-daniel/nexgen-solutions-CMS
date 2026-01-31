<?php
require_once '../../includes/auth.php';
require_once '../../includes/functions.php';

// Check login status first
Auth::requireLogin();

if (!Auth::hasRole('admin')) {
    setFlash('danger', 'Access denied.');
    header('Location: ' . Auth::getBasePath() . '/dashboard.php');
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $conn = getDBConnection();
    
    $user_id = (int)($_POST['user_id'] ?? 0);
    $full_name = trim($_POST['full_name']);
    $role = $_POST['role'];
    $status = $_POST['status'];
    $department = trim($_POST['department']);
    $position = trim($_POST['position']);
    $salary = !empty($_POST['salary']) ? (float)$_POST['salary'] : null;

    // Validation
    if (!$user_id || empty($full_name)) {
        setFlash('danger', 'Invalid input data.');
        header('Location: users.php');
        exit();
    }
    
    // Update Query
    $sql = "UPDATE users SET 
            full_name = ?, 
            role = ?, 
            status = ?, 
            department = ?, 
            position = ?, 
            salary = ?,
            updated_at = NOW()
            WHERE id = ?";
            
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("sssssdi", $full_name, $role, $status, $department, $position, $salary, $user_id);
    
    if ($stmt->execute()) {
        setFlash('success', 'User updated successfully.');
    } else {
        setFlash('danger', 'Error updating user: ' . $conn->error);
    }
    
    header('Location: users.php');
    exit();
} else {
    header('Location: users.php');
    exit();
}
?>
