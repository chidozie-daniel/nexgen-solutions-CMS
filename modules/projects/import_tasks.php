<?php
/**
 * Bulk Task Upload for Projects
 * 
 * Import multiple tasks at once for a project
 * 
 * CSV Format:
 * title,description,assigned_to_email,priority,due_date,status,progress
 * "Design Homepage","Create mockup...",john@example.com,high,2026-04-15,pending,0
 */

define('APP_ENTRY_POINT', true);
require_once '../../includes/auth.php';
require_once '../../includes/functions.php';

Auth::requireRole(['admin', 'hr', 'project_leader']);

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_FILES['csv_file'])) {
    $project_id = $_GET['project_id'] ?? 0;
    header('Location: ../projects/details.php?id=' . $project_id);
    exit();
}

// Verify CSRF token
if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
    setFlash('danger', 'Invalid security token.');
    $project_id = $_POST['project_id'] ?? 0;
    header('Location: ../projects/details.php?id=' . $project_id);
    exit();
}

$conn = getDBConnection();
$user_id = $_SESSION['user_id'];
$role = $_SESSION['role'];
$file = $_FILES['csv_file'];
$project_id = (int)($_POST['project_id'] ?? 0);

$import_errors = [];
$import_success = [];

// Validation
if ($project_id <= 0) {
    setFlash('danger', 'Invalid project ID.');
    header('Location: ../projects/index.php');
    exit();
}

// Verify user has access to this project
$proj_check = $conn->prepare("SELECT project_leader, project_name FROM projects WHERE id = ?");
$proj_check->bind_param("i", $project_id);
$proj_check->execute();
$project = $proj_check->get_result()->fetch_assoc();

if (!$project) {
    setFlash('danger', 'Project not found.');
    header('Location: ../projects/index.php');
    exit();
}

$can_manage = ($role === 'admin' || $role === 'hr' || $project['project_leader'] == $user_id);
if (!$can_manage) {
    setFlash('danger', 'You do not have permission to add tasks to this project.');
    header('Location: ../projects/details.php?id=' . $project_id);
    exit();
}

if ($file['error'] !== UPLOAD_ERR_OK) {
    setFlash('danger', 'File upload error.');
    header('Location: ../projects/details.php?id=' . $project_id);
    exit();
}

if ($file['size'] > 5 * 1024 * 1024) {
    setFlash('danger', 'CSV file is too large (max 5MB).');
    header('Location: ../projects/details.php?id=' . $project_id);
    exit();
}

$ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
if ($ext !== 'csv') {
    setFlash('danger', 'Only CSV files are allowed.');
    header('Location: ../projects/details.php?id=' . $project_id);
    exit();
}

$handle = fopen($file['tmp_name'], 'r');
if (!$handle) {
    setFlash('danger', 'Could not open file.');
    header('Location: ../projects/details.php?id=' . $project_id);
    exit();
}

$row = 0;
$allowed_priority = ['low', 'medium', 'high', 'critical'];
$allowed_status = ['pending', 'in_progress', 'review', 'completed', 'cancelled'];
$notify_assignees = isset($_POST['notify_assignees']) && $_POST['notify_assignees'] === '1';

// Start transaction for bulk import
$conn->begin_transaction();
$transaction_failed = false;

try {
    while (($data = fgetcsv($handle, 2000, ",")) !== FALSE) {
    $row++;
    
    // Skip header row
    if ($row === 1 && (strtolower($data[0] ?? '') === 'title' || strtolower($data[0] ?? '') === 'task_title')) {
        continue;
    }
    
    // Skip empty rows
    if (empty($data) || (count($data) === 1 && empty($data[0]))) {
        continue;
    }
    
    $row_data = [
        'row' => $row,
        'title' => sanitizeText($data[0] ?? '', 255),
        'description' => sanitizeText($data[1] ?? '', 2000, true),
        'assigned_to_email' => trim($data[2] ?? ''),
        'priority' => strtolower(trim($data[3] ?? 'medium')),
        'due_date' => !empty($data[4]) ? $data[4] : null,
        'status' => strtolower(trim($data[5] ?? 'pending')),
        'progress' => !empty($data[6]) ? (int)$data[6] : 0,
    ];
    
    // Validation
    $errors = [];
    
    if (empty($row_data['title'])) {
        $errors[] = 'Task title required';
    } elseif (strlen($row_data['title']) < 5) {
        $errors[] = 'Task title must be at least 5 characters';
    }
    
    if (!empty($row_data['assigned_to_email'])) {
        if (!isValidEmail($row_data['assigned_to_email'])) {
            $errors[] = 'Invalid assignee email format';
        } else {
            // Get user ID from email
            $user_check = $conn->prepare("SELECT id, full_name FROM users WHERE email = ? AND status = 'active'");
            $user_check->bind_param("s", $row_data['assigned_to_email']);
            $user_check->execute();
            $user_result = $user_check->get_result();
            
            if ($user_result->num_rows === 0) {
                $errors[] = 'Assignee not found or inactive';
            } else {
                $user = $user_result->fetch_assoc();
                $row_data['assigned_to'] = $user['id'];
                $row_data['assigned_to_name'] = $user['full_name'];
            }
        }
    } else {
        $errors[] = 'Assignee email required';
    }
    
    if (!in_array($row_data['priority'], $allowed_priority, true)) {
        $errors[] = 'Invalid priority (must be: ' . implode(', ', $allowed_priority) . ')';
    }
    
    if (!empty($row_data['due_date']) && !isValidDate($row_data['due_date'], 'Y-m-d')) {
        $errors[] = 'Invalid due date format (use YYYY-MM-DD)';
    }
    
    if (!in_array($row_data['status'], $allowed_status, true)) {
        $errors[] = 'Invalid status (must be: ' . implode(', ', $allowed_status) . ')';
    }
    
    if ($row_data['progress'] < 0 || $row_data['progress'] > 100) {
        $errors[] = 'Progress must be between 0 and 100';
    }
    
    if (!empty($errors)) {
        $import_errors[] = [
            'row' => $row,
            'title' => $row_data['title'],
            'errors' => $errors
        ];
        continue;
    }

    // Insert task
    $sql = "INSERT INTO tasks (project_id, title, description, assigned_to, assigned_by, priority, status, due_date, progress, created_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param("issiiissi",
        $project_id,
        $row_data['title'],
        $row_data['description'],
        $row_data['assigned_to'],
        $user_id,
        $row_data['priority'],
        $row_data['status'],
        $row_data['due_date'],
        $row_data['progress']
    );

    if ($stmt->execute()) {
        $task_id = $conn->insert_id;
        $import_success[] = [
            'row' => $row,
            'task_id' => $task_id,
            'title' => $row_data['title'],
            'assigned_to' => $row_data['assigned_to_name'],
            'due_date' => $row_data['due_date']
        ];

        // Send notification if enabled
        if ($notify_assignees) {
            $task_data = [
                'email' => $row_data['assigned_to_email'],
                'full_name' => $row_data['assigned_to_name'],
                'title' => $row_data['title']
            ];
            sendTaskAssignmentEmail($task_data, $row_data['title'], $row_data['due_date'] ?? 'Not set');

            // Create in-app notification
            createNotification(
                $row_data['assigned_to'],
                '📋 New Task Assigned: ' . $row_data['title'],
                'You have been assigned a new task in project: ' . $project['project_name'],
                'info',
                'task',
                '../tasks/view.php?id=' . $task_id,
                $user_id
            );
        }

        // Log activity
        logAdminActivity('TASK_BULK_IMPORTED',
            "Task imported via bulk upload: {$row_data['title']}",
            'tasks',
            $task_id,
            [
                'project_id' => $project_id,
                'project_name' => $project['project_name'],
                'imported_by' => $_SESSION['full_name'] ?? 'Unknown'
            ]
        );
    } else {
        $stmt->close();
        throw new Exception("Database error at row $row: " . $conn->error);
    }
}

// If we got here, all rows processed successfully
fclose($handle);
$conn->commit();

// Store results
$_SESSION['task_import_results'] = [
    'success' => $import_success,
    'errors' => $import_errors,
    'project_id' => $project_id,
    'project_name' => $project['project_name'],
    'total_rows' => $row - 1,
    'timestamp' => date('Y-m-d H:i:s')
];

// Flash message
$success_count = count($import_success);
$error_count = count($import_errors);
$message = "Task import complete: $success_count tasks created";
if ($error_count > 0) {
    $message .= ", $error_count errors";
}

setFlash($success_count > 0 ? 'success' : 'warning', $message);
header('Location: ../projects/details.php?id=' . $project_id . '&import=complete');
exit();

} catch (Exception $e) {
    // Rollback all changes if any error occurs
    $conn->rollback();
    fclose($handle);
    
    error_log("Task import failed for project $project_id: " . $e->getMessage());
    setFlash('danger', 'Import failed: ' . $e->getMessage() . '. All changes have been rolled back.');
    header('Location: ../projects/details.php?id=' . $project_id);
    exit();
}
?>
