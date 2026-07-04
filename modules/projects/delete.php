<?php
// Define entry point constant to allow access
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

$project_id = $_POST['project_id'];
$user_id = $_SESSION['user_id'];
$role = $_SESSION['role'];

if (!is_numeric($project_id) || (int)$project_id <= 0) {
    setFlash('danger', 'Invalid project.');
    header('Location: ' . Auth::getBasePath() . '/modules/projects/index.php');
    exit();
}

$conn = getDBConnection();

$check_sql = "SELECT project_leader FROM projects WHERE id = ?";
$check_stmt = dbPrepare($conn, $check_sql, 'project delete permission');
if (!$check_stmt) {
    setFlash('danger', 'Could not verify the project. Please try again.');
    header('Location: ' . Auth::getBasePath() . '/modules/projects/index.php');
    exit();
}
$check_stmt->bind_param("i", $project_id);
if (!dbExecute($check_stmt, 'project delete permission')) {
    $check_stmt->close();
    setFlash('danger', 'Could not verify the project. Please try again.');
    header('Location: ' . Auth::getBasePath() . '/modules/projects/index.php');
    exit();
}
$project = $check_stmt->get_result()->fetch_assoc();
$check_stmt->close();

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
    $del_tasks = "DELETE FROM tasks WHERE project_id = ?";
    $stmt = dbPrepare($conn, $del_tasks, 'project delete tasks');
    if (!$stmt) {
        throw new Exception('prepare tasks');
    }
    $stmt->bind_param("i", $project_id);
    if (!dbExecute($stmt, 'project delete tasks')) {
        $stmt->close();
        throw new Exception('execute tasks');
    }
    $stmt->close();

    $del_members = "DELETE FROM project_members WHERE project_id = ?";
    $stmt = dbPrepare($conn, $del_members, 'project delete members');
    if (!$stmt) {
        throw new Exception('prepare members');
    }
    $stmt->bind_param("i", $project_id);
    if (!dbExecute($stmt, 'project delete members')) {
        $stmt->close();
        throw new Exception('execute members');
    }
    $stmt->close();

    $del_project = "DELETE FROM projects WHERE id = ?";
    $stmt = dbPrepare($conn, $del_project, 'project delete row');
    if (!$stmt) {
        throw new Exception('prepare project');
    }
    $stmt->bind_param("i", $project_id);
    if (!dbExecute($stmt, 'project delete row')) {
        $stmt->close();
        throw new Exception('execute project');
    }
    $stmt->close();

    $conn->commit();
    setFlash('success', 'Project deleted successfully.');
} catch (Exception $e) {
    $conn->rollback();
    dbLogError('project delete transaction', $e->getMessage());
    setFlash('danger', 'Could not delete the project. Please try again.');
}

header('Location: ' . Auth::getBasePath() . '/modules/projects/index.php');
exit();
?>
