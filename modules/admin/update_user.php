<?php
// Define entry point constant to allow access
define('APP_ENTRY_POINT', true);

require_once '../../includes/auth.php';
require_once '../../includes/functions.php';

// Check login status and require admin role
Auth::requireRole('admin');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Verify CSRF token
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        setFlash('danger', 'Invalid security token. Please try again.');
        header('Location: users.php');
        exit();
    }
    
    $conn = getDBConnection();
    
    $user_id = (int)($_POST['user_id'] ?? 0);
    $full_name = sanitizeText($_POST['full_name'] ?? '', 120);
    $role = $_POST['role'] ?? '';
    $status = $_POST['status'] ?? '';
    $department = sanitizeText($_POST['department'] ?? '', 60);
    $position = sanitizeText($_POST['position'] ?? '', 80);
    $salary = !empty($_POST['salary']) ? $_POST['salary'] : null;

    // Validation
    $errors = [];
    if (!$user_id || empty($full_name)) {
        $errors[] = 'Invalid input data.';
    }
    $allowed_roles = ['employee', 'project_leader', 'hr', 'admin'];
    if (!in_array($role, $allowed_roles, true)) {
        $errors[] = 'Invalid role selected.';
    }
    $allowed_status = ['active', 'inactive', 'suspended'];
    if (!in_array($status, $allowed_status, true)) {
        $errors[] = 'Invalid status selected.';
    }
    if ($salary !== null && $salary !== '') {
        if (!isNonNegativeNumber($salary)) {
            $errors[] = 'Salary must be a non-negative number.';
        } else {
            $salary = (float)$salary;
        }
    } else {
        $salary = null;
    }
    
    if (!empty($errors)) {
        setFlash('danger', implode('<br>', $errors));
        header('Location: users.php');
        exit();
    }
    
    // Get old user data first
    $get_old_sql = "SELECT * FROM users WHERE id = ?";
    $get_old_stmt = $conn->prepare($get_old_sql);
    $get_old_stmt->bind_param("i", $user_id);
    $get_old_stmt->execute();
    $old_data = $get_old_stmt->get_result()->fetch_assoc();
    
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
        // Send role change notification if role changed
        if ($old_data && $old_data['role'] !== $role) {
            notifyUserRoleChanged(
                ['email' => $old_data['email'], 'full_name' => $old_data['full_name'], 'username' => $old_data['username']],
                $old_data['role'],
                $role,
                $_SESSION['full_name'] ?? 'Admin'
            );
        }
        
        // Send status change notification if status changed
        if ($old_data && $old_data['status'] !== $status) {
            notifyUserStatusChanged(
                ['email' => $old_data['email'], 'full_name' => $old_data['full_name'], 'username' => $old_data['username']],
                $status,
                $_SESSION['full_name'] ?? 'Admin'
            );
        }
        
        // Log activity
        logAdminActivity('USER_UPDATED', "User profile updated: {$full_name}", $user_id, [
            'old_data' => $old_data,
            'new_data' => compact('full_name', 'role', 'status', 'department', 'position', 'salary')
        ]);
        
        setFlash('success', 'User updated successfully.');
    } else {
        error_log("Error updating user (user_id: $user_id): " . $conn->error);
        setFlash('danger', 'Error updating user. Please try again.');
    }
    
    header('Location: users.php');
    exit();
} else {
    header('Location: users.php');
    exit();
}
?>
