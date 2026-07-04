<?php
/**
 * Enhanced Bulk User Import with Detailed Error Reporting
 * 
 * Features:
 * - Detailed error logging per row
 * - Email notifications to imported users
 * - Activity logging
 * - Download error report
 * - Support for optional fields
 */

define('APP_ENTRY_POINT', true);
require_once '../../includes/auth.php';
require_once '../../includes/functions.php';

Auth::requireRole('admin');

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_FILES['csv_file'])) {
    header('Location: users.php');
    exit();
}

// Verify CSRF token
if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
    setFlash('danger', 'Invalid security token. Please try again.');
    header('Location: users.php');
    exit();
}

$conn = getDBConnection();
$file = $_FILES['csv_file'];
$import_errors = [];
$import_success = [];
$import_skipped = [];

// Validation
if ($file['error'] !== UPLOAD_ERR_OK) {
    setFlash('danger', 'File upload error (Code: ' . $file['error'] . ')');
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

$row = 0;
$default_password = 'Nexgen@123';
$send_welcome_email = isset($_POST['send_welcome_email']) && $_POST['send_welcome_email'] === '1';

while (($data = fgetcsv($handle, 2000, ",")) !== FALSE) {
    $row++;
    
    // Skip header row
    if ($row === 1 && (strtolower($data[0] ?? '') === 'username' || strtolower($data[0] ?? '') === 'employee_id')) {
        continue;
    }
    
    // Skip empty rows
    if (empty($data) || (count($data) === 1 && empty($data[0]))) {
        continue;
    }
    
    $row_data = [
        'row' => $row,
        'username' => sanitizeText($data[0] ?? '', 30),
        'email' => trim($data[1] ?? ''),
        'full_name' => sanitizeText($data[2] ?? '', 120),
        'role' => strtolower(trim($data[3] ?? 'employee')),
        'department' => sanitizeText($data[4] ?? '', 60),
        'position' => sanitizeText($data[5] ?? '', 80),
        'salary' => !empty($data[6]) ? (float)$data[6] : null,
        'employee_id' => sanitizeText($data[7] ?? '', 20),
        'hire_date' => !empty($data[8]) ? $data[8] : null,
        'phone' => sanitizeText($data[9] ?? '', 20),
    ];
    
    // Generate employee ID if not provided
    if (empty($row_data['employee_id'])) {
        $row_data['employee_id'] = 'EMP' . date('Y') . str_pad($row, 4, '0', STR_PAD_LEFT);
    }
    
    // Validation
    $errors = [];
    
    if (empty($row_data['username'])) {
        $errors[] = 'Username required';
    } elseif (!isValidUsername($row_data['username'])) {
        $errors[] = 'Invalid username (3-30 chars, letters/numbers/underscore/dash/dot)';
    }
    
    if (empty($row_data['email'])) {
        $errors[] = 'Email required';
    } elseif (!isValidEmail($row_data['email'])) {
        $errors[] = 'Invalid email format';
    }
    
    if (empty($row_data['full_name'])) {
        $errors[] = 'Full name required';
    }
    
    $allowed_roles = ['employee', 'project_leader', 'hr', 'admin'];
    if (!in_array($row_data['role'], $allowed_roles, true)) {
        $errors[] = 'Invalid role (must be: ' . implode(', ', $allowed_roles) . ')';
    }
    
    if ($row_data['salary'] !== null && $row_data['salary'] < 0) {
        $errors[] = 'Salary cannot be negative';
    }
    
    if (!empty($row_data['hire_date']) && !isValidDate($row_data['hire_date'])) {
        $errors[] = 'Invalid hire date format (use YYYY-MM-DD)';
    }
    
    // Check for duplicates
    if (empty($errors)) {
        $check = $conn->prepare("SELECT id FROM users WHERE username = ? OR email = ?");
        $check->bind_param("ss", $row_data['username'], $row_data['email']);
        $check->execute();
        if ($check->get_result()->num_rows > 0) {
            $errors[] = 'Username or email already exists';
        }
    }
    
    if (!empty($errors)) {
        $import_errors[] = [
            'row' => $row,
            'username' => $row_data['username'],
            'email' => $row_data['email'],
            'errors' => $errors
        ];
        continue;
    }
    
    // Insert user
    $password_hash = password_hash($default_password, PASSWORD_DEFAULT);
    
    $sql = "INSERT INTO users (username, password, email, full_name, role, employee_id, department, position, salary, hire_date, phone, status, created_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'active', NOW())";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ssssssssdss", 
        $row_data['username'],
        $password_hash,
        $row_data['email'],
        $row_data['full_name'],
        $row_data['role'],
        $row_data['employee_id'],
        $row_data['department'],
        $row_data['position'],
        $row_data['salary'],
        $row_data['hire_date'],
        $row_data['phone']
    );
    
    if ($stmt->execute()) {
        $new_user_id = $conn->insert_id;
        $import_success[] = [
            'row' => $row,
            'user_id' => $new_user_id,
            'username' => $row_data['username'],
            'full_name' => $row_data['full_name'],
            'email' => $row_data['email'],
            'role' => $row_data['role']
        ];
        
        // Send welcome email if enabled
        if ($send_welcome_email) {
            $user_data = [
                'email' => $row_data['email'],
                'full_name' => $row_data['full_name'],
                'username' => $row_data['username'],
                'role' => $row_data['role']
            ];
            notifyUserAccountCreated($user_data, $default_password);
        }
        
        // Log activity
        logAdminActivity('USER_IMPORTED', "User imported via bulk upload: {$row_data['full_name']}", $new_user_id, [
            'role' => $row_data['role'],
            'imported_by' => $_SESSION['full_name'] ?? 'Admin',
            'row_number' => $row
        ]);
    } else {
        $import_errors[] = [
            'row' => $row,
            'username' => $row_data['username'],
            'email' => $row_data['email'],
            'errors' => ['Database error: ' . $conn->error]
        ];
    }
}

fclose($handle);

// Store results in session for display
$_SESSION['import_results'] = [
    'success' => $import_success,
    'errors' => $import_errors,
    'skipped' => $import_skipped,
    'total_rows' => $row - 1, // Exclude header
    'timestamp' => date('Y-m-d H:i:s')
];

// Flash message
$success_count = count($import_success);
$error_count = count($import_errors);
$message = "Import complete: $success_count successful";
if ($error_count > 0) {
    $message .= ", $error_count errors";
}

setFlash($success_count > 0 ? 'success' : 'warning', $message);
header('Location: users.php?import=complete');
exit();
?>
