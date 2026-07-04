<?php
// Define entry point constant to allow access
define('APP_ENTRY_POINT', true);

require_once '../../includes/auth.php';
require_once '../../includes/functions.php';

// Check login status and require admin role
Auth::requireRole('admin');

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['csv_file'])) {
    // Verify CSRF token
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        setFlash('danger', 'Invalid security token. Please try again.');
        header('Location: users.php');
        exit();
    }
    
    $file = $_FILES['csv_file'];
    
    // basic validation
    if ($file['error'] !== UPLOAD_ERR_OK) {
        setFlash('danger', 'File upload error.');
        header('Location: users.php');
        exit();
    }
    if ($file['size'] > 5 * 1024 * 1024) {
        setFlash('danger', 'CSV file is too large (max 5MB).');
        header('Location: users.php');
        exit();
    }
    
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if ($ext !== 'csv') {
        setFlash('danger', 'Only CSV files are allowed.');
        header('Location: users.php');
        exit();
    }
    
    $handle = fopen($file['tmp_name'], 'r');
    if (!$handle) {
        setFlash('danger', 'Could not open file.');
        header('Location: users.php');
        exit();
    }
    
    $conn = getDBConnection();
    $row = 0;
    $success = 0;
    $skipped = 0;
    $errors = 0;
    
    // Default password if not provided
    $default_pass = 'Nexgen@123';
    
    while (($data = fgetcsv($handle, 1000, ",")) !== FALSE) {
        $row++;
        // Skip header row if it looks like a header (e.g. contains 'username')
        if ($row === 1 && strtolower($data[0]) === 'username') {
            continue;
        }
        
        // Expected columns: username, email, full_name, role, department, position, salary
        $username = sanitizeText($data[0] ?? '', 30);
        $email = trim($data[1] ?? '');
        $full_name = sanitizeText($data[2] ?? '', 120);
        $role = strtolower(trim($data[3] ?? 'employee'));
        $department = sanitizeText($data[4] ?? '', 60);
        $position = sanitizeText($data[5] ?? '', 80);
        $salary = $data[6] ?? null;
        
        if ($username === '' || $email === '') {
            $errors++;
            continue;
        }
        if (!isValidUsername($username) || !isValidEmail($email)) {
            $errors++;
            continue;
        }
        $allowed_roles = ['employee', 'project_leader', 'hr', 'admin'];
        if (!in_array($role, $allowed_roles, true)) {
            $errors++;
            continue;
        }
        if ($salary !== null && $salary !== '') {
            if (!isNonNegativeNumber($salary)) {
                $errors++;
                continue;
            }
            $salary = (float)$salary;
        } else {
            $salary = null;
        }
        
        // Check if exists
        $check = $conn->prepare("SELECT id FROM users WHERE username = ? OR email = ?");
        $check->bind_param("ss", $username, $email);
        $check->execute();
        if ($check->get_result()->num_rows > 0) {
            $skipped++;
            continue;
        }
        
        // Insert
        // Generate employee ID (Simple random for now, ideally strictly sequential)
        $employee_id = 'EMP' . rand(1000, 9999); 
        $password_hash = password_hash($default_pass, PASSWORD_DEFAULT);
        
        $sql = "INSERT INTO users (username, password, email, full_name, role, employee_id, department, position, salary, status, created_at) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'active', NOW())";
        
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ssssssssd", $username, $password_hash, $email, $full_name, $role, $employee_id, $department, $position, $salary);
        
        if ($stmt->execute()) {
            $success++;
            // Get the newly created user data
            $new_user_id = $stmt->insert_id;
            $new_user_sql = "SELECT email, full_name, username, role FROM users WHERE id = ?";
            $new_user_stmt = $conn->prepare($new_user_sql);
            $new_user_stmt->bind_param("i", $new_user_id);
            $new_user_stmt->execute();
            $new_user_data = $new_user_stmt->get_result()->fetch_assoc();
            
            // Send welcome email
            if ($new_user_data) {
                notifyUserAccountCreated($new_user_data, $default_pass);
                logAdminActivity('USER_CREATED', "User account created via import: {$new_user_data['full_name']}", $new_user_id, ['role' => $role, 'imported_by' => $_SESSION['full_name'] ?? 'Admin']);
            }
        } else {
            $errors++;
        }
    }
    
    fclose($handle);
    
    $msg = "Import complete. Added: $success, Skipped: $skipped, Errors: $errors.";
    setFlash($success > 0 ? 'success' : 'warning', $msg);
    header('Location: users.php');
    exit();
    
} else {
    header('Location: users.php');
    exit();
}
?>
