<?php
require_once '../../includes/auth.php';
require_once '../../includes/functions.php';

// Check login status first
Auth::requireLogin();

// Only project leaders, HR, and Admin can add team members
if (!Auth::hasRole('project_leader') && !Auth::hasRole('hr') && !Auth::hasRole('admin')) {
    setFlash('danger', 'You do not have permission to add team members.');
        header('Location: ' . Auth::getBasePath() . '/dashboard.php');
    exit();
}

if ($_SERVER['REQUEST_METHOD'] != 'POST' || !isset($_POST['project_id']) || !isset($_POST['user_id'])) {
    setFlash('danger', 'Invalid request.');
    header('Location: ' . Auth::getBasePath() . '/modules/projects/index.php');
    exit();
}

$conn = getDBConnection();
$project_id = $_POST['project_id'];
$user_id = $_POST['user_id'];
$role = $_POST['role'] ?? 'member';
$current_user_id = $_SESSION['user_id'];

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

if ($project['project_leader'] != $current_user_id && !Auth::hasRole('hr') && !Auth::hasRole('admin')) {
    setFlash('danger', 'Only project leader can add team members.');
    header('Location: ' . Auth::getBasePath() . '/modules/projects/details.php?id=' . $project_id);
    exit();
}

// Check if member already exists
$existing_sql = "SELECT id FROM project_members WHERE project_id = ? AND user_id = ?";
$existing_stmt = $conn->prepare($existing_sql);
$existing_stmt->bind_param("ii", $project_id, $user_id);
$existing_stmt->execute();
$existing_result = $existing_stmt->get_result();

if ($existing_result->num_rows > 0) {
    setFlash('warning', 'User is already a team member of this project.');
    header('Location: ' . Auth::getBasePath() . '/modules/projects/details.php?id=' . $project_id);
    exit();
}

// Add team member
$sql = "INSERT INTO project_members (project_id, user_id, role, joined_date) 
        VALUES (?, ?, ?, CURDATE())";

$stmt = $conn->prepare($sql);
$stmt->bind_param("iis", $project_id, $user_id, $role);

if ($stmt->execute()) {
    setFlash('success', 'Team member added successfully!');
} else {
    setFlash('danger', 'Error adding team member.');
}

header('Location: ' . Auth::getBasePath() . '/modules/projects/details.php?id=' . $project_id);
exit();
?>