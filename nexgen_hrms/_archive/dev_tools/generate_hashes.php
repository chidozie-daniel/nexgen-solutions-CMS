<?php
/**
 * DEV TOOL (archived): Generates password hashes and example INSERT statements.
 *
 * This file is intentionally archived (not web-accessible by default) because it can leak credentials patterns.
 * If you need it, run from CLI: php _archive/dev_tools/generate_hashes.php
 */

$passwords = [
    'Admin@123' => ['username' => 'admin', 'role' => 'admin'],
    'hr123' => ['username' => 'hrmanager', 'role' => 'hr'],
    'pl123' => ['username' => 'projlead', 'role' => 'project_leader'],
    'Employee@123' => ['username' => 'employee', 'role' => 'employee'],
];

foreach ($passwords as $password => $meta) {
    $username = $meta['username'];
    $role = $meta['role'];
    $hash = password_hash($password, PASSWORD_DEFAULT);
    echo "INSERT IGNORE INTO users (employee_id, username, email, password, full_name, role, department, position, salary, status) VALUES ('{$username}', '{$username}', '{$username}@nexgensolutions.com', '{$hash}', 'User {$username}', '{$role}', 'IT', 'Staff', 40000.00, 'active');" . PHP_EOL;
}


