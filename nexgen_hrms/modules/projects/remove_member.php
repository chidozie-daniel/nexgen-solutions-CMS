<?php
require_once '../../includes/auth.php';
require_once '../../includes/functions.php';

// Check login status first
Auth::requireLogin();

if ($_SERVER['REQUEST_METHOD'] != 'POST' || !isset($_POST['project_id']) || !isset($_POST['user_id'])) {
    setFlash('danger', 'Invalid request.');
    header('Location: ' . Auth::getBasePath() . '/modules/projects/index.php');
    exit();
}

$project_id = $_POST['project_id'];
$member_id = $_POST['user_id'];
$user_id = $_SESSION['user_id'];
$role = $_SESSION['role'];

$conn = getDBConnection();

// Check project exists and get leader
$check_sql = "SELECT project_leader FROM projects WHERE id = ?";
$check_stmt = $conn->prepare($check_sql);
$check_stmt->bind_param("i", $project_id);
$check_stmt->execute();
$project_result = $check_stmt->get_result();
$project = $project_result->fetch_assoc();

if (!$project) {
    setFlash('danger', 'Project not found.');
    header('Location: ' . Auth::getBasePath() . '/modules/projects/index.php');
    exit();
}

// Check permissions
if ($role != 'admin' && $role != 'hr' && $project['project_leader'] != $user_id) {
    setFlash('danger', 'You do not have permission to remove members.');
    header('Location: ' . Auth::getBasePath() . '/modules/projects/details.php?id=' . $project_id);
    exit();
}

// Prevent removing the project leader
if ($member_id == $project['project_leader']) {
    setFlash('danger', 'Cannot remove the project leader from the project.');
    header('Location: ' . Auth::getBasePath() . '/modules/projects/details.php?id=' . $project_id);
    exit();
}

// Remove member
$sql = "DELETE FROM project_members WHERE project_id = ? AND user_id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("ii", $project_id, $member_id);

if ($stmt->execute()) {
    // Optionally: Unassign their tasks? or keep them assigned but user is not in project?
    // Good practice: Set their tasks to unassigned (NULL) or keep as is.
    // Let's keep as is for history, or maybe just leave it provided the code handles non-member assignees.
    setFlash('success', 'Member removed successfully.');
} else {
    setFlash('danger', 'Error removing member.');
}

header('Location: ' . Auth::getBasePath() . '/modules/projects/details.php?id=' . $project_id);
exit();
?>
