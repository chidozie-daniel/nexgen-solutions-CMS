<?php
/**
 * Bulk Attendance Upload
 * 
 * Import attendance records from CSV (e.g., from biometric devices)
 * 
 * CSV Format:
 * employee_id,date,check_in,check_out,status
 * EMP20260001,2026-03-30,09:00:00,18:00:00,present
 * 
 * Status options: present, absent, late, half_day, remote
 */

define('APP_ENTRY_POINT', true);
require_once '../../includes/auth.php';
require_once '../../includes/functions.php';

Auth::requireRole(['admin', 'hr']);

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_FILES['csv_file'])) {
    header('Location: ../attendance/manage.php');
    exit();
}

// Verify CSRF token
if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
    setFlash('danger', 'Invalid security token.');
    header('Location: ../attendance/manage.php');
    exit();
}

$conn = getDBConnection();
$file = $_FILES['csv_file'];
$import_errors = [];
$import_success = [];

// Validation
if ($file['error'] !== UPLOAD_ERR_OK) {
    setFlash('danger', 'File upload error.');
    header('Location: ../attendance/manage.php');
    exit();
}

if ($file['size'] > 10 * 1024 * 1024) {
    setFlash('danger', 'CSV file is too large (max 10MB).');
    header('Location: ../attendance/manage.php');
    exit();
}

$ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
if ($ext !== 'csv') {
    setFlash('danger', 'Only CSV files are allowed.');
    header('Location: ../attendance/manage.php');
    exit();
}

$handle = fopen($file['tmp_name'], 'r');
if (!$handle) {
    setFlash('danger', 'Could not open file.');
    header('Location: ../attendance/manage.php');
    exit();
}

$row = 0;
$allowed_status = ['present', 'absent', 'late', 'half_day', 'remote'];
$overwrite_existing = isset($_POST['overwrite_existing']) && $_POST['overwrite_existing'] === '1';

// Start transaction for bulk import
$conn->begin_transaction();

try {
    while (($data = fgetcsv($handle, 1000, ",")) !== FALSE) {
    $row++;
    
    // Skip header row
    if ($row === 1 && (strtolower($data[0] ?? '') === 'employee_id' || strtolower($data[0] ?? '') === 'emp_id')) {
        continue;
    }
    
    // Skip empty rows
    if (empty($data) || (count($data) === 1 && empty($data[0]))) {
        continue;
    }
    
    $row_data = [
        'row' => $row,
        'employee_id' => sanitizeText($data[0] ?? '', 20),
        'date' => $data[1] ?? '',
        'check_in' => !empty($data[2]) ? $data[2] : null,
        'check_out' => !empty($data[3]) ? $data[3] : null,
        'status' => strtolower(trim($data[4] ?? 'present')),
        'notes' => sanitizeText($data[5] ?? '', 255, true),
    ];
    
    // Validation
    $errors = [];
    
    if (empty($row_data['employee_id'])) {
        $errors[] = 'Employee ID required';
    }
    
    if (empty($row_data['date'])) {
        $errors[] = 'Date required';
    } elseif (!isValidDate($row_data['date'], 'Y-m-d')) {
        $errors[] = 'Invalid date format (use YYYY-MM-DD)';
    }
    
    if (!in_array($row_data['status'], $allowed_status, true)) {
        $errors[] = 'Invalid status (must be: ' . implode(', ', $allowed_status) . ')';
    }
    
    // Get user ID from employee_id
    $user_id = null;
    if (empty($errors)) {
        $user_check = $conn->prepare("SELECT id, full_name FROM users WHERE employee_id = ? AND status = 'active'");
        $user_check->bind_param("s", $row_data['employee_id']);
        $user_check->execute();
        $user_result = $user_check->get_result();
        
        if ($user_result->num_rows === 0) {
            $errors[] = 'Employee not found or inactive';
        } else {
            $user = $user_result->fetch_assoc();
            $user_id = $user['id'];
            $row_data['user_id'] = $user_id;
            $row_data['full_name'] = $user['full_name'];
        }
    }
    
    // Check for existing record
    if (empty($errors)) {
        $existing = $conn->prepare("SELECT id FROM attendance WHERE user_id = ? AND date = ?");
        $existing->bind_param("is", $user_id, $row_data['date']);
        $existing->execute();
        
        if ($existing->get_result()->num_rows > 0) {
            if (!$overwrite_existing) {
                $errors[] = 'Record already exists for this date (enable overwrite to update)';
            } else {
                // Will update instead of insert
                $row_data['is_update'] = true;
                $existing_record = $existing->get_result()->fetch_assoc();
                $row_data['existing_id'] = $existing_record['id'];
            }
        }
    }
    
    if (!empty($errors)) {
        $import_errors[] = [
            'row' => $row,
            'employee_id' => $row_data['employee_id'],
            'date' => $row_data['date'],
            'errors' => $errors
        ];
        continue;
    }
    
    // Calculate working hours if check-in/out provided
    $working_hours = null;
    if ($row_data['check_in'] && $row_data['check_out']) {
        $check_in_dt = new DateTime($row_data['check_in']);
        $check_out_dt = new DateTime($row_data['check_out']);
        $interval = $check_in_dt->diff($check_out_dt);
        $working_hours = $interval->h + ($interval->i / 60);
    }
    
    // Insert or update
    if (isset($row_data['is_update'])) {
        $sql = "UPDATE attendance SET 
                check_in = ?, check_out = ?, status = ?, notes = ?, working_hours = ?, updated_at = NOW()
                WHERE id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ssssdi", 
            $row_data['check_in'],
            $row_data['check_out'],
            $row_data['status'],
            $row_data['notes'],
            $working_hours,
            $row_data['existing_id']
        );
    } else {
        $sql = "INSERT INTO attendance (user_id, date, check_in, check_out, status, notes, working_hours, created_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, NOW())";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("isssssd", 
            $user_id,
            $row_data['date'],
            $row_data['check_in'],
            $row_data['check_out'],
            $row_data['status'],
            $row_data['notes'],
            $working_hours
        );
    }
    
    if ($stmt->execute()) {
        $import_success[] = [
            'row' => $row,
            'employee_id' => $row_data['employee_id'],
            'full_name' => $row_data['full_name'],
            'date' => $row_data['date'],
            'status' => $row_data['status'],
            'action' => isset($row_data['is_update']) ? 'updated' : 'created'
        ];
        
        // Log activity
        logAdminActivity('ATTENDANCE_IMPORTED', 
            "Attendance imported for {$row_data['full_name']} on {$row_data['date']}", 
            'attendance', 
            $row_data['existing_id'] ?? $conn->insert_id,
            [
                'status' => $row_data['status'],
                'imported_by' => $_SESSION['full_name'] ?? 'Admin/HR'
            ]
        );
    } else {
        throw new Exception("Database error at row $row: " . $conn->error);
    }
}

fclose($handle);
$conn->commit();

// Store results
$_SESSION['attendance_import_results'] = [
    'success' => $import_success,
    'errors' => $import_errors,
    'total_rows' => $row - 1,
    'timestamp' => date('Y-m-d H:i:s')
];

// Flash message
$success_count = count($import_success);
$error_count = count($import_errors);
$message = "Attendance import complete: $success_count records";
if ($error_count > 0) {
    $message .= ", $error_count errors";
}

setFlash($success_count > 0 ? 'success' : 'warning', $message);
header('Location: ../attendance/manage.php?import=complete');
exit();

} catch (Exception $e) {
    // Rollback all changes if any error occurs
    $conn->rollback();
    fclose($handle);
    
    error_log("Attendance import failed: " . $e->getMessage());
    setFlash('danger', 'Import failed: ' . $e->getMessage() . '. All changes have been rolled back.');
    header('Location: ../attendance/manage.php');
    exit();
}
?>
