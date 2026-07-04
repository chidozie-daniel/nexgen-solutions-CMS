<?php
// Define entry point constant
define('APP_ENTRY_POINT', true);

require_once '../../includes/auth.php';
require_once '../../includes/functions.php';

// Check login status and require project leader, HR, or admin role
Auth::requireRole(['project_leader', 'hr', 'admin']);

if ($_SERVER['REQUEST_METHOD'] != 'POST' || !isset($_POST['project_id'])) {
    setFlash('danger', 'Invalid request.');
    header('Location: ' . Auth::getBasePath() . '/modules/projects/index.php');
    exit();
}

// Verify CSRF token
if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
    setFlash('danger', 'Invalid security token. Please try again.');
    header('Location: ' . Auth::getBasePath() . '/modules/projects/index.php');
    exit();
}

$conn = getDBConnection();
$project_id = $_POST['project_id'];
$user_id = $_SESSION['user_id'];

// Check if user is the project leader
$check_sql = "SELECT project_leader FROM projects WHERE id = ?";
$check_stmt = $conn->prepare($check_sql);
$check_stmt->bind_param("i", $project_id);
$check_stmt->execute();
$check_result = $check_stmt->get_result();
$project = $check_result->fetch_assoc();

if (!$project) {
    setFlash('danger', 'Project not found.');
    header('Location: ' . Auth::getBasePath() . '/modules/projects/index.php');
    exit();
}

if ($project['project_leader'] != $user_id && !Auth::hasRole('hr') && !Auth::hasRole('admin')) {
    setFlash('danger', 'Only project leader can update this project.');
    header('Location: ' . Auth::getBasePath() . '/modules/projects/details.php?id=' . $project_id);
    exit();
}

// Update project
$project_name = sanitizeText($_POST['project_name'] ?? '', 120);
$project_code = sanitizeText($_POST['project_code'] ?? '', 30);
$description = sanitizeText($_POST['description'] ?? '', 2000, true);
$client_name = sanitizeText($_POST['client_name'] ?? '', 120);
$start_date = $_POST['start_date'];
$end_date = $_POST['end_date'];
$budget = $_POST['budget'] ?? 0;
$status = $_POST['status'];

// Validation
$errors = [];

if ($project_name === '') {
    $errors[] = 'Project name is required.';
}
if ($project_code === '' || !preg_match('/^[A-Za-z0-9_-]{3,30}$/', $project_code)) {
    $errors[] = 'Project code must be 3-30 characters (letters, numbers, dash, underscore).';
}
if (!isValidDate($start_date) || !isValidDate($end_date)) {
    $errors[] = 'Please provide valid start and end dates.';
} elseif (strtotime($end_date) < strtotime($start_date)) {
    $errors[] = "End date cannot be before start date.";
}
$allowed_status = ['planning', 'active', 'on_hold', 'completed', 'cancelled'];
if (!in_array($status, $allowed_status, true)) {
    $errors[] = 'Invalid status selected.';
}
if ($budget !== '' && !isNonNegativeNumber($budget)) {
    $errors[] = 'Budget must be a non-negative number.';
} else {
    $budget = (float)$budget;
}

// Check for duplicate project code (excluding this project)
$dup_sql = "SELECT id FROM projects WHERE project_code = ? AND id != ?";
$dup_stmt = $conn->prepare($dup_sql);
$dup_stmt->bind_param("si", $project_code, $project_id);
$dup_stmt->execute();
if ($dup_stmt->get_result()->num_rows > 0) {
    $errors[] = "Project code '$project_code' already exists.";
}

if (!empty($errors)) {
    setFlash('danger', implode('<br>', $errors));
    header('Location: ' . Auth::getBasePath() . '/modules/projects/details.php?id=' . $project_id);
    exit();
}

$sql = "UPDATE projects SET 
        project_name = ?, 
        project_code = ?, 
        description = ?, 
        client_name = ?, 
        start_date = ?, 
        end_date = ?, 
        budget = ?, 
        status = ? 
        WHERE id = ?";

$stmt = $conn->prepare($sql);
$stmt->bind_param("ssssssdsi", $project_name, $project_code, $description, 
                 $client_name, $start_date, $end_date, $budget, $status, $project_id);

if ($stmt->execute()) {
    setFlash('success', 'Project updated successfully!');
} else {
    setFlash('danger', 'Error updating project.');
}

header('Location: ' . Auth::getBasePath() . '/modules/projects/details.php?id=' . $project_id);
exit();
?>
