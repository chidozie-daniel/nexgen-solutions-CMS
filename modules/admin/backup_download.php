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

$include_data = isset($_POST['include_data']);
$drop_tables = isset($_POST['drop_tables']);

$conn = getDBConnection();
$db_res = $conn->query("SELECT DATABASE() as db");
$db_row = $db_res ? $db_res->fetch_assoc() : null;
$db_name = $db_row['db'] ?? 'database';

// Build SQL dump in-memory (suitable for small/medium DBs)
$sql_out = [];
$sql_out[] = "-- NexGen HRMS Database Backup";
$sql_out[] = "-- Generated: " . date('Y-m-d H:i:s');
$sql_out[] = "-- Database: " . $db_name;
$sql_out[] = "SET sql_mode='NO_AUTO_VALUE_ON_ZERO';";
$sql_out[] = "SET time_zone = '+00:00';";
$sql_out[] = "START TRANSACTION;";
$sql_out[] = "SET foreign_key_checks = 0;";
$sql_out[] = "";

$tables = [];
$tbl_res = $conn->query("SHOW TABLES");
while ($tbl_res && ($r = $tbl_res->fetch_array(MYSQLI_NUM))) {
    $tables[] = $r[0];
}

foreach ($tables as $table) {
    $table_esc = str_replace('`', '``', $table);

    $create_res = $conn->query("SHOW CREATE TABLE `{$table_esc}`");
    $create_row = $create_res ? $create_res->fetch_assoc() : null;
    $create_sql = $create_row['Create Table'] ?? null;
    if (!$create_sql) continue;

    $sql_out[] = "";
    $sql_out[] = "-- --------------------------------------------------------";
    $sql_out[] = "-- Table structure for table `{$table}`";
    $sql_out[] = "-- --------------------------------------------------------";
    if ($drop_tables) {
        $sql_out[] = "DROP TABLE IF EXISTS `{$table_esc}`;";
    }
    $sql_out[] = $create_sql . ";";

    if (!$include_data) continue;

    $data_res = $conn->query("SELECT * FROM `{$table_esc}`");
    if (!$data_res) continue;

    $sql_out[] = "";
    $sql_out[] = "-- Dumping data for table `{$table}`";

    $fields = [];
    $field_count = $data_res->field_count;
    $meta = $data_res->fetch_fields();
    foreach ($meta as $f) $fields[] = "`" . str_replace('`', '``', $f->name) . "`";

    while ($row = $data_res->fetch_assoc()) {
        $vals = [];
        foreach ($meta as $f) {
            $v = $row[$f->name];
            if ($v === null) {
                $vals[] = "NULL";
            } else {
                $vals[] = "'" . $conn->real_escape_string((string)$v) . "'";
            }
        }
        $sql_out[] = "INSERT INTO `{$table_esc}` (" . implode(',', $fields) . ") VALUES (" . implode(',', $vals) . ");";
    }
}

$sql_out[] = "";
$sql_out[] = "SET foreign_key_checks = 1;";
$sql_out[] = "COMMIT;";
$sql_out[] = "";

$dump = implode("\n", $sql_out);

$filename = 'nexgen_hrms_backup_' . date('Ymd_His') . '.sql';
header('Content-Type: application/sql');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Content-Length: ' . strlen($dump));
echo $dump;
exit();

