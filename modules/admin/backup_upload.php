<?php
define('APP_ENTRY_POINT', true);

require_once '../../includes/auth.php';
require_once '../../includes/functions.php';

Auth::requireRole('admin');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    setFlash('danger', 'Invalid request.');
    header('Location: ' . Auth::getBasePath() . '/modules/admin/backup_restore.php');
    exit();
}

if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
    setFlash('danger', 'Invalid security token. Please try again.');
    header('Location: ' . Auth::getBasePath() . '/modules/admin/backup_restore.php');
    exit();
}

if (!isset($_POST['confirm_restore'])) {
    setFlash('danger', 'Please confirm restore.');
    header('Location: ' . Auth::getBasePath() . '/modules/admin/backup_restore.php');
    exit();
}

if (!isset($_FILES['sql_file']) || ($_FILES['sql_file']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
    setFlash('danger', 'Upload failed. Please try again.');
    header('Location: ' . Auth::getBasePath() . '/modules/admin/backup_restore.php');
    exit();
}

$tmp = $_FILES['sql_file']['tmp_name'];
$size = (int)($_FILES['sql_file']['size'] ?? 0);
if ($size <= 0) {
    setFlash('danger', 'Empty SQL file.');
    header('Location: ' . Auth::getBasePath() . '/modules/admin/backup_restore.php');
    exit();
}
if ($size > (20 * 1024 * 1024)) {
    setFlash('danger', 'SQL file is too large (max 20MB recommended).');
    header('Location: ' . Auth::getBasePath() . '/modules/admin/backup_restore.php');
    exit();
}

$sql = file_get_contents($tmp);
if ($sql === false || trim($sql) === '') {
    setFlash('danger', 'Could not read SQL file.');
    header('Location: ' . Auth::getBasePath() . '/modules/admin/backup_restore.php');
    exit();
}

$conn = getDBConnection();
$conn->set_charset('utf8mb4');

// Run as multi-query (works for typical dumps)
$ok = $conn->multi_query($sql);
if (!$ok) {
    setFlash('danger', 'Restore failed: ' . htmlspecialchars($conn->error));
    header('Location: ' . Auth::getBasePath() . '/modules/admin/backup_restore.php');
    exit();
}

do {
    $res = $conn->store_result();
    if ($res instanceof mysqli_result) {
        $res->free();
    }
} while ($conn->more_results() && $conn->next_result());

if ($conn->errno) {
    setFlash('danger', 'Restore finished with errors: ' . htmlspecialchars($conn->error));
} else {
    setFlash('success', 'Database restore completed.');
}

header('Location: ' . Auth::getBasePath() . '/modules/admin/backup_restore.php');
exit();

