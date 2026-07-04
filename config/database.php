<?php
// Load environment variables
require_once dirname(__FILE__) . '/env.php';

// Database configuration from environment variables
define('DB_HOST', getEnv('DB_HOST', 'localhost'));
define('DB_USER', getEnv('DB_USER', 'root'));
define('DB_PASS', getEnv('DB_PASS', ''));
define('DB_NAME', getEnv('DB_NAME', 'nexgen_hrms'));

/**
 * Write DB diagnostics to the PHP error log (never shown to end users).
 */
function dbLogError(string $context, string $message): void {
    error_log('[NexGen HRMS DB] ' . $context . ': ' . $message);
}

/**
 * Prepare a statement; logs and returns false on failure.
 *
 * @return mysqli_stmt|false
 */
function dbPrepare(mysqli $conn, string $sql, string $context = 'dbPrepare'): mysqli_stmt|false {
    $stmt = $conn->prepare($sql);
    if ($stmt === false) {
        $snippet = substr(preg_replace('/\s+/', ' ', $sql), 0, 400);
        dbLogError($context, 'prepare failed: ' . $conn->error . ' | SQL: ' . $snippet);
    }
    return $stmt;
}

/**
 * Execute a prepared statement; logs and returns false on failure.
 */
function dbExecute(mysqli_stmt $stmt, string $context = 'dbExecute'): bool {
    if (!$stmt->execute()) {
        dbLogError($context, 'execute failed: ' . $stmt->error);
        return false;
    }
    return true;
}

/**
 * Run a plain SQL query; logs and returns false on failure.
 *
 * @return mysqli_result|bool
 */
function dbQuery(mysqli $conn, string $sql, string $context = 'dbQuery'): mysqli_result|bool {
    $result = $conn->query($sql);
    if ($result === false) {
        $snippet = substr(preg_replace('/\s+/', ' ', $sql), 0, 400);
        dbLogError($context, 'query failed: ' . $conn->error . ' | SQL: ' . $snippet);
    }
    return $result;
}

// Create connection
function getDBConnection() {
    $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);

    if ($conn->connect_error) {
        dbLogError('connection', $conn->connect_error);
        $debug = function_exists('getEnv') ? (bool) getEnv('APP_DEBUG', false) : false;
        if ($debug) {
            die('Connection failed: ' . htmlspecialchars($conn->connect_error, ENT_QUOTES, 'UTF-8'));
        }
        die('Database connection failed. Please try again later.');
    }

    $conn->set_charset('utf8mb4');

    return $conn;
}

// Start session if not started
if (php_sapi_name() !== 'cli' && session_status() == PHP_SESSION_NONE && !headers_sent()) {
    session_start();
}
?>