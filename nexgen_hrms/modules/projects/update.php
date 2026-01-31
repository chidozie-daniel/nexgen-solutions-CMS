<?php
require_once '../../includes/auth.php';
require_once '../../includes/functions.php';

// Check login status first
Auth::requireLogin();

// Only project leaders, HR, and Admin can update projects
if (!Auth::hasRole('project_leader') && !Auth::hasRole('hr') && !Auth::hasRole('admin')) {
    setFlash('danger', 'You do not have permission to update projects.');
    header('Location: ' . Auth::getBasePath() . '/dashboard.php');
    exit();
}

if ($_SERVER['REQUEST_METHOD'] != 'POST' || !isset($_POST['project_id'])) {
    setFlash('danger', 'Invalid request.');
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
$project_name = trim($_POST['project_name']);
$project_code = trim($_POST['project_code']);
$description = trim($_POST['description']);
$client_name = trim($_POST['client_name']);
$start_date = $_POST['start_date'];
$end_date = $_POST['end_date'];
$budget = $_POST['budget'] ?? 0;
$status = $_POST['status'];

// Validation
$errors = [];

// Check specific validation
if (strtotime($end_date) < strtotime($start_date)) {
    $errors[] = "End date cannot be before start date.";
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