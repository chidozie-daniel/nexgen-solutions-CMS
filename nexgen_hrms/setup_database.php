<?php
// Database setup script for NexGen HRMS
// This script will create the database and all necessary tables

$host = 'localhost';
$user = 'root';
$pass = '';
$db = 'nexgen_hrms';

/**
 * Split SQL file content into executable statements.
 * Handles:
 * - Lines starting with -- comments
 * - Multi-line statements
 * - Basic block comments (/* ... *\/) removed via regex
 */
function splitSqlStatements($sql) {
    // Remove /* */ block comments
    $sql = preg_replace('#/\\*.*?\\*/#s', '', $sql);

    $lines = preg_split("/\\r\\n|\\n|\\r/", $sql);
    $statements = [];
    $current = '';

    foreach ($lines as $line) {
        $trim = trim($line);
        if ($trim === '' || strpos($trim, '--') === 0) {
            continue;
        }

        $current .= ' ' . $trim;

        // End of statement when line ends with ;
        if (substr($trim, -1) === ';') {
            $stmt = trim($current);
            $stmt = rtrim($stmt, ';');
            if ($stmt !== '') {
                $statements[] = $stmt;
            }
            $current = '';
        }
    }

    // Any trailing statement without semicolon
    $stmt = trim($current);
    if ($stmt !== '') {
        $statements[] = $stmt;
    }

    return $statements;
}

// Create connection without database first
mysqli_report(MYSQLI_REPORT_OFF);

$conn = @new mysqli($host, $user, $pass);
if ($conn->connect_error) {
    $msg = "Connection failed: " . $conn->connect_error;
    $hint = "Start MySQL in XAMPP Control Panel (or ensure port 3306 is free), then re-run this script.";
    die("<div style='font-family: Arial, sans-serif; padding: 12px; border: 1px solid #f5c2c7; background: #f8d7da; color: #842029; border-radius: 6px;'>" .
        "<strong>Database connection error.</strong><br>" .
        htmlspecialchars($msg) . "<br><br>" .
        "<strong>Fix:</strong> " . htmlspecialchars($hint) .
        "</div>");
}

// Create database if not exists
$sql = "CREATE DATABASE IF NOT EXISTS $db";
if ($conn->query($sql) === TRUE) {
    echo "Database created successfully or already exists<br>";
} else {
    echo "Error creating database: " . $conn->error . "<br>";
}

// Select the database
$conn->select_db($db);

// Read and execute the SQL file
$sql_file = __DIR__ . '/nexgen_hrms.sql';
if (file_exists($sql_file)) {
    $sql = file_get_contents($sql_file);
    
    $statements = splitSqlStatements($sql);
    
    foreach ($statements as $statement) {
        if (!empty($statement)) {
            if ($conn->query($statement)) {
                echo "✓ Executed<br>";
            } else {
                // Suppress common re-run errors (duplicate indexes, already exists)
                $err = (string)$conn->error;
                $errLower = strtolower($err);
                $sLower = strtolower($statement);

                $isCreateIndex = (strpos($sLower, 'create index') === 0);
                $isDupIndex = ($isCreateIndex && (strpos($errLower, 'duplicate key name') !== false || strpos($errLower, 'already exists') !== false));
                $isTableExists = (strpos($errLower, 'already exists') !== false);

                if ($isDupIndex || $isTableExists) {
                    echo "↺ Skipped (already exists)<br>";
                } else {
                    echo "✗ Error: " . htmlspecialchars($err) . "<br>";
                    echo "<details><summary>Statement</summary><pre>" . htmlspecialchars($statement) . ";</pre></details><br>";
                }
            }
        }
    }
    
    echo "<br><strong>Database setup completed!</strong><br>";
    
    // Create default admin user with Admin@123
    $admin_password = password_hash('Admin@123', PASSWORD_DEFAULT);
    $admin_sql = "INSERT INTO users (employee_id, username, email, password, full_name, role, department, position, salary, hire_date, status) 
                  VALUES ('ADMIN001', 'admin', 'admin@nexgen.com', ?, 'System Administrator', 'admin', 'IT', 'Administrator', 100000.00, CURDATE(), 'active')
                  ON DUPLICATE KEY UPDATE password=?";
    
    $stmt = $conn->prepare($admin_sql);
    $stmt->bind_param("ss", $admin_password, $admin_password);
    if ($stmt->execute()) {
        echo "✓ Default admin user created (Username: admin, Password: Admin@123)<br>";
    } else {
        echo "✗ Error creating admin user: " . $conn->error . "<br>";
    }
    
    // Create sample HR user with hr123
    $hr_password = password_hash('hr123', PASSWORD_DEFAULT);
    $hr_sql = "INSERT INTO users (employee_id, username, email, password, full_name, role, department, position, salary, hire_date, status) 
                VALUES ('HR001', 'hrmanager', 'hr@nexgen.com', ?, 'Sarah Johnson', 'hr', 'Human Resources', 'HR Manager', 45000.00, CURDATE(), 'active')
                ON DUPLICATE KEY UPDATE password=?";
    
    $stmt = $conn->prepare($hr_sql);
    $stmt->bind_param("ss", $hr_password, $hr_password);
    if ($stmt->execute()) {
        echo "✓ Sample HR user created (Username: hrmanager, Password: hr123)<br>";
    } else {
        echo "✗ Error creating HR user: " . $conn->error . "<br>";
    }
    
    // Create sample Project Leader with pl123
    $pl_password = password_hash('pl123', PASSWORD_DEFAULT);
    $pl_sql = "INSERT INTO users (employee_id, username, email, password, full_name, role, department, position, salary, hire_date, status) 
                VALUES ('PL001', 'projlead', 'pl@nexgen.com', ?, 'Michael Chen', 'project_leader', 'Development', 'Project Leader', 55000.00, CURDATE(), 'active')
                ON DUPLICATE KEY UPDATE password=?";
    
    $stmt = $conn->prepare($pl_sql);
    $stmt->bind_param("ss", $pl_password, $pl_password);
    if ($stmt->execute()) {
        echo "✓ Sample Project Leader created (Username: projlead, Password: pl123)<br>";
    } else {
        echo "✗ Error creating Project Leader: " . $conn->error . "<br>";
    }
    
    // Create sample Employee with Employee@123
    $emp_password = password_hash('Employee@123', PASSWORD_DEFAULT);
    $emp_sql = "INSERT INTO users (employee_id, username, email, password, full_name, role, department, position, salary, hire_date, status) 
                 VALUES ('EMP001', 'employee', 'emp@nexgen.com', ?, 'John Doe', 'employee', 'Development', 'Developer', 40000.00, CURDATE(), 'active')
                 ON DUPLICATE KEY UPDATE password=?";
    
    $stmt = $conn->prepare($emp_sql);
    $stmt->bind_param("ss", $emp_password, $emp_password);
    if ($stmt->execute()) {
        echo "✓ Sample Employee created (Username: employee, Password: Employee@123)<br>";
    } else {
        echo "✗ Error creating Employee: " . $conn->error . "<br>";
    }
    
} else {
    echo "SQL file not found: $sql_file<br>";
}

$conn->close();
?>
