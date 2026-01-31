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

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['csv_file'])) {
    $file = $_FILES['csv_file'];
    
    // basic validation
    if ($file['error'] !== UPLOAD_ERR_OK) {
        setFlash('danger', 'File upload error.');
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
        $username = trim($data[0] ?? '');
        $email = trim($data[1] ?? '');
        $full_name = trim($data[2] ?? '');
        $role = strtolower(trim($data[3] ?? 'employee'));
        $department = trim($data[4] ?? '');
        $position = trim($data[5] ?? '');
        $salary = !empty($data[6]) ? (float)$data[6] : null;
        
        if (empty($username) || empty($email)) {
            $errors++;
            continue;
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
