<?php
require_once '../../includes/auth.php';
require_once '../../includes/functions.php';

// Check login status first
Auth::requireLogin();

if ($_SERVER['REQUEST_METHOD'] != 'POST' || !isset($_POST['project_id'])) {
    setFlash('danger', 'Invalid request.');
    header('Location: ' . Auth::getBasePath() . '/modules/projects/index.php');
    exit();
}

$project_id = $_POST['project_id'];
$user_id = $_SESSION['user_id'];
$role = $_SESSION['role'];

$conn = getDBConnection();

// Check permissions (Admin, HR, or Project Leader of that project)
$check_sql = "SELECT project_leader FROM projects WHERE id = ?";
$check_stmt = $conn->prepare($check_sql);
$check_stmt->bind_param("i", $project_id);
$check_stmt->execute();
$project = $check_stmt->get_result()->fetch_assoc();

if (!$project) {
    setFlash('danger', 'Project not found.');
    header('Location: ' . Auth::getBasePath() . '/modules/projects/index.php');
    exit();
}

if ($role != 'admin' && $role != 'hr' && $project['project_leader'] != $user_id) {
    setFlash('danger', 'You do not have permission to delete this project.');
    header('Location: ' . Auth::getBasePath() . '/modules/projects/index.php');
    exit();
}

// Perform Deletion (Transaction)
$conn->begin_transaction();

try {
    // 1. Delete tasks
    $del_tasks = "DELETE FROM tasks WHERE project_id = ?";
    $stmt = $conn->prepare($del_tasks);
    $stmt->bind_param("i", $project_id);
    $stmt->execute();
    
    // 2. Delete members
    $del_members = "DELETE FROM project_members WHERE project_id = ?";
    $stmt = $conn->prepare($del_members);
    $stmt->bind_param("i", $project_id);
    $stmt->execute();
    
    // 3. Delete project
    $del_project = "DELETE FROM projects WHERE id = ?";
    $stmt = $conn->prepare($del_project);
    $stmt->bind_param("i", $project_id);
    if (!$stmt->execute()) {
        throw new Exception("Error deleting project.");
    }
    
    $conn->commit();
    setFlash('success', 'Project deleted successfully.');
    
} catch (Exception $e) {
    $conn->rollback();
    setFlash('danger', 'Error deleting project: ' . $e->getMessage());
}

header('Location: ' . Auth::getBasePath() . '/modules/projects/index.php');
exit();
?>
