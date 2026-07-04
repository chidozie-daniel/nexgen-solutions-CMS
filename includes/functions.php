<?php
// Prevent direct access to this file
if (!defined('APP_ENTRY_POINT') && php_sapi_name() !== 'cli') {
    http_response_code(403);
    exit('Direct access not allowed');
}

// Use absolute path relative to the root of nexgen_hrms
$base_dir = dirname(dirname(__FILE__));
require_once $base_dir . '/config/database.php';
require_once $base_dir . '/includes/email_config.php';

// Redirect function
function redirect($url) {
    header("Location: $url");
    exit();
}

/**
 * Get setting value from database
 * @param string $key Setting key
 * @param mixed $default Default value if not found
 * @return mixed
 */
function getSetting($key, $default = null) {
    static $settings_cache = null;
    
    if ($settings_cache === null) {
        $settings_cache = [];
        $conn = getDBConnection();
        $res = dbQuery($conn, 'SELECT setting_key, setting_value FROM settings', 'getSetting');
        if ($res instanceof mysqli_result) {
            while ($row = $res->fetch_assoc()) {
                $settings_cache[$row['setting_key']] = $row['setting_value'];
            }
            $res->free();
        }
    }
    
    return $settings_cache[$key] ?? $default;
}

// Flash message function
function setFlash($type, $message) {
    $_SESSION['flash'] = [
        'type' => $type,
        'message' => $message
    ];
}

function getFlash() {
    if (isset($_SESSION['flash'])) {
        $flash = $_SESSION['flash'];
        unset($_SESSION['flash']);
        return $flash;
    }
    return null;
}

// Format date
function formatDate($date, $format = 'Y-m-d') {
    if (empty($date)) return '';
    return date($format, strtotime($date));
}

// Validation helpers
function sanitizeText($value, $max_len = 255, $allow_newlines = false) {
    $value = trim((string)$value);
    if ($allow_newlines) {
        $value = str_replace(["\r\n", "\r"], "\n", $value);
    } else {
        $value = preg_replace('/\s+/', ' ', $value);
    }
    if ($max_len && strlen($value) > $max_len) {
        $value = substr($value, 0, $max_len);
    }
    return $value;
}

function isValidEmail($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

function isValidDate($date, $format = 'Y-m-d') {
    if (!$date) return false;
    $d = DateTime::createFromFormat($format, $date);
    return $d && $d->format($format) === $date;
}

function isValidMonth($month) {
    if (!preg_match('/^\d{4}-\d{2}$/', $month)) return false;
    return isValidDate($month . '-01', 'Y-m-d');
}

function isNonNegativeNumber($value) {
    return is_numeric($value) && (float)$value >= 0;
}

function isValidUsername($username) {
    return preg_match('/^[a-zA-Z0-9_.-]{3,30}$/', $username) === 1;
}

// Get role badge
function getRoleBadge($role) {
    $badges = [
        'admin' => '<span class="badge bg-danger">Admin</span>',
        'hr' => '<span class="badge bg-warning text-dark">HR</span>',
        'project_leader' => '<span class="badge bg-primary">Project Leader</span>',
        'employee' => '<span class="badge bg-secondary">Employee</span>'
    ];
    return $badges[$role] ?? '<span class="badge bg-light text-dark">Unknown</span>';
}

// Get status badge
function getStatusBadge($status) {
    $badges = [
        'pending' => '<span class="badge bg-warning text-dark">Pending</span>',
        'approved' => '<span class="badge bg-success">Approved</span>',
        'rejected' => '<span class="badge bg-danger">Rejected</span>',
        'active' => '<span class="badge bg-success">Active</span>',
        'inactive' => '<span class="badge bg-secondary">Inactive</span>',
        'completed' => '<span class="badge bg-success">Completed</span>',
        'in_progress' => '<span class="badge bg-primary">In Progress</span>'
    ];
    return $badges[$status] ?? '<span class="badge bg-light text-dark">'.$status.'</span>';
}

// Get recommendation badge
function getRecommendationBadge($status) {
    $badges = [
        'none' => '<span class="badge bg-light text-muted">No Review</span>',
        'pending' => '<span class="badge bg-info text-dark">Pending Review</span>',
        'recommended' => '<span class="badge bg-success">Recommended</span>',
        'not_recommended' => '<span class="badge bg-warning text-dark">Not Recommended</span>'
    ];
    return $badges[$status] ?? '<span class="badge bg-light text-dark">'.$status.'</span>';
}

/**
 * Get users for dropdown/selection
 * @param bool $exclude_self - Exclude current user
 * @param array $roles - Filter by specific roles (default: all active users)
 * @param bool $include_details - Include department and position in result
 * @return array Array of user objects
 */
function getEmployees($exclude_self = true, $roles = [], $include_details = true) {
    $conn = getDBConnection();
    
    // Build SELECT clause
    $select_fields = 'id, employee_id, full_name';
    if ($include_details) {
        $select_fields .= ', department, position, role';
    }
    
    // Build WHERE clause
    $where_clauses = ["status = 'active'"];
    
    if ($exclude_self && isset($_SESSION['user_id'])) {
        $where_clauses[] = 'id != ?';
    }
    
    if (!empty($roles)) {
        $role_placeholders = implode(',', array_fill(0, count($roles), '?'));
        $where_clauses[] = "role IN ($role_placeholders)";
    }
    
    $where_sql = implode(' AND ', $where_clauses);
    $sql = "SELECT $select_fields FROM users WHERE $where_sql ORDER BY full_name";
    
    // Bind parameters
    $params = [];
    $types = '';
    
    if ($exclude_self && isset($_SESSION['user_id'])) {
        $params[] = $_SESSION['user_id'];
        $types .= 'i';
    }
    
    if (!empty($roles)) {
        foreach ($roles as $role) {
            $params[] = $role;
            $types .= 's';
        }
    }
    
    if (!empty($params)) {
        $stmt = $conn->prepare($sql);
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $result = $stmt->get_result();
    } else {
        $result = $conn->query($sql);
    }
    
    $employees = [];
    while ($row = $result->fetch_assoc()) {
        $employees[] = $row;
    }
    
    return $employees;
}

/**
 * Get employees only (non-management users)
 * @param bool $exclude_self - Exclude current user
 * @return array Array of employee objects
 */
function getEmployeesOnly($exclude_self = true) {
    return getEmployees($exclude_self, ['employee'], true);
}

/**
 * Get project leaders for selection
 * @param bool $exclude_self - Exclude current user
 * @return array Array of project leader objects
 */
function getProjectLeaders($exclude_self = true) {
    return getEmployees($exclude_self, ['project_leader'], true);
}

/**
 * Get HR and Admin users for selection
 * @param bool $exclude_self - Exclude current user
 * @return array Array of HR/Admin objects
 */
function getHRAndAdmin($exclude_self = true) {
    return getEmployees($exclude_self, ['hr', 'admin'], true);
}

/**
 * Get users appropriate for project team based on creator role
 * @param string $creator_role - Role of the person creating the project
 * @param bool $exclude_self - Exclude current user
 * @return array Array of appropriate team member candidates
 */
function getProjectTeamMembers($creator_role, $exclude_self = true) {
    if ($creator_role === 'admin' || $creator_role === 'hr') {
        // HR/Admin should see Project Leaders and Employees
        return getEmployees($exclude_self, ['project_leader', 'employee'], true);
    } else {
        // Project Leaders should see Employees only
        return getEmployees($exclude_self, ['employee'], true);
    }
}

// Get projects for dropdown
function getProjects() {
    $conn = getDBConnection();
    $sql = "SELECT id, project_code, project_name FROM projects WHERE status != 'completed' AND status != 'cancelled' ORDER BY project_name";
    $result = $conn->query($sql);
    
    $projects = [];
    while ($row = $result->fetch_assoc()) {
        $projects[] = $row;
    }
    
    return $projects;
}

// Generate a unique project code from database settings
function generateProjectCode() {
    $conn = getDBConnection();
    
    // Load prefix from settings (default to 'PROJ' if not found)
    $prefix = getSettingValue('project_code_prefix', 'PROJ', $conn);
    
    $year = date('y');
    $month = date('m');
    $random = str_pad(rand(1, 999), 3, '0', STR_PAD_LEFT);
    
    $conn->close();
    
    return $prefix . $year . $month . $random;
}

// Fetch a single integer value from a simple query (e.g., SELECT COUNT(...))
function fetchCountQuery($sql) {
    $conn = getDBConnection();
    $result = $conn->query($sql);
    if (!$result) return 0;
    $row = $result->fetch_array();
    return isset($row[0]) ? intval($row[0]) : 0;
}

// Fetch a single integer value from a prepared statement with dynamic params
function fetchCountPrepared($sql, $types, $params = []) {
    $conn = getDBConnection();
    $stmt = $conn->prepare($sql);
    if (!$stmt) return 0;

    // Bind parameters dynamically
    $bind_names = [];
    $bind_names[] = $types;
    for ($i = 0; $i < count($params); $i++) {
        $bind_name = 'bind' . $i;
        $$bind_name = $params[$i];
        $bind_names[] = &$$bind_name;
    }

    call_user_func_array([$stmt, 'bind_param'], $bind_names);

    if (!$stmt->execute()) return 0;
    $result = $stmt->get_result();
    if (!$result) return 0;
    $row = $result->fetch_array();
    return isset($row[0]) ? intval($row[0]) : 0;
}

// Get user's leave balance
function getLeaveBalance($user_id, $type = 'annual') {
    $conn = getDBConnection();
    $current_year = date('Y');
    
    $sql = "SELECT 
        COALESCE(SUM(duration_days), 0) as used_days 
    FROM leaves 
    WHERE user_id = ? 
    AND leave_type = ? 
    AND YEAR(start_date) = ? 
    AND status = 'approved'";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("isi", $user_id, $type, $current_year);
    $stmt->execute();
    $result = $stmt->get_result();
    $used = $result->fetch_assoc()['used_days'] ?? 0;

    // Load leave allocations from database settings
    $annual = intval(getSettingValue('leave_annual_days', 15, $conn));
    $sick = intval(getSettingValue('leave_sick_days', 10, $conn));
    $casual = intval(getSettingValue('leave_casual_days', 7, $conn));
    
    $allocations = [
        'annual' => $annual,
        'sick' => $sick,
        'casual' => $casual
    ];

    $conn->close();
    return ($allocations[$type] ?? 0) - $used;
}

// Get progress color class based on percentage from database settings
function getProgressColorClass($progress) {
    $conn = getDBConnection();
    
    // Load thresholds from settings
    $low_threshold = intval(getSettingValue('progress_color_low_threshold', 50, $conn));
    $mid_threshold = intval(getSettingValue('progress_color_mid_threshold', 80, $conn));
    
    $conn->close();
    
    // Return CSS class based on thresholds
    if ($progress >= 100) {
        return 'bg-success';
    } elseif ($progress >= $mid_threshold) {
        return 'bg-primary';
    } elseif ($progress >= $low_threshold) {
        return 'bg-warning';
    } else {
        return 'bg-danger';
    }
}

// ==========================================
// CSRF Token Functions
// ==========================================

/**
 * Generate a CSRF token and store it in the session
 * @return string The generated token
 */
function generateCSRFToken() {
    if (session_status() == PHP_SESSION_NONE) {
        session_start();
    }
    
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    
    return $_SESSION['csrf_token'];
}

/**
 * Verify a CSRF token from a form submission
 * @param string|null $token The token to verify
 * @return bool True if valid, false otherwise
 */
function verifyCSRFToken($token) {
    if (session_status() == PHP_SESSION_NONE) {
        session_start();
    }
    
    if (empty($_SESSION['csrf_token']) || empty($token)) {
        return false;
    }
    
    return hash_equals($_SESSION['csrf_token'], $token);
}

/**
 * Output a hidden CSRF token field for forms
 * @return string HTML input field
 */
function csrfField() {
    $token = generateCSRFToken();
    return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars($token) . '">';
}

/**
 * Regenerate CSRF token (use after successful form submission)
 * @return string The new token
 */
function regenerateCSRFToken() {
    if (session_status() == PHP_SESSION_NONE) {
        session_start();
    }

    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    return $_SESSION['csrf_token'];
}

// ==========================================
// Notification Functions
// ==========================================

/**
 * Create a notification for a user
 * @param int $user_id - The user to notify
 * @param string $title - Notification title
 * @param string $message - Notification message
 * @param string $type - info, success, warning, danger
 * @param string $category - leave, task, project, payroll, inquiry, announcement, system
 * @param string|null $action_url - URL to go when clicked
 * @param int|null $created_by - User who created the notification
 * @return int Notification ID
 */
function createNotification($user_id, $title, $message, $type = 'info', $category = 'info', $action_url = null, $created_by = null) {
    $conn = getDBConnection();

    if ($created_by === null) {
        $sql = "INSERT INTO notifications (user_id, title, message, type, category, action_url, created_by) 
                VALUES (?, ?, ?, ?, ?, ?, NULL)";
        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            return 0;
        }
        $stmt->bind_param("isssss", $user_id, $title, $message, $type, $category, $action_url);
    } else {
        $sql = "INSERT INTO notifications (user_id, title, message, type, category, action_url, created_by) 
                VALUES (?, ?, ?, ?, ?, ?, ?)";
        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            return 0;
        }
        $stmt->bind_param("isssssi", $user_id, $title, $message, $type, $category, $action_url, $created_by);
    }

    if ($stmt->execute()) {
        $id = (int) $conn->insert_id;
        $stmt->close();
        return $id;
    }
    $stmt->close();
    return 0;
}

/**
 * Create notifications for multiple users
 * @param array $user_ids - Array of user IDs
 * @param string $title - Notification title
 * @param string $message - Notification message
 * @param string $type - info, success, warning, danger
 * @param string $category - leave, task, project, payroll, inquiry, announcement, system
 * @param string|null $action_url - URL to go when clicked
 * @param int|null $created_by - User who created the notification
 * @return int Number of notifications created
 */
function createNotificationForMultipleUsers($user_ids, $title, $message, $type = 'info', $category = 'info', $action_url = null, $created_by = null) {
    $count = 0;
    foreach ($user_ids as $user_id) {
        if (createNotification($user_id, $title, $message, $type, $category, $action_url, $created_by)) {
            $count++;
        }
    }
    return $count;
}

/**
 * Get unread notification count for a user
 * @param int $user_id
 * @return int
 */
function getUnreadNotificationCount($user_id) {
    $conn = getDBConnection();
    $sql = "SELECT COUNT(*) as count FROM notifications WHERE user_id = ? AND is_read = 0";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();
    return (int)($result['count'] ?? 0);
}

/**
 * Get recent notifications for a user
 * @param int $user_id
 * @param int $limit
 * @return array
 */
function getRecentNotifications($user_id, $limit = 10) {
    $conn = getDBConnection();
    $sql = "SELECT n.*, u.full_name as creator_name 
            FROM notifications n 
            LEFT JOIN users u ON n.created_by = u.id 
            WHERE n.user_id = ? 
            ORDER BY n.created_at DESC 
            LIMIT ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ii", $user_id, $limit);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $notifications = [];
    while ($row = $result->fetch_assoc()) {
        $notifications[] = $row;
    }
    return $notifications;
}

/**
 * Mark notification as read
 * @param int $notification_id
 * @param int $user_id
 * @return bool
 */
function markNotificationAsRead($notification_id, $user_id) {
    $conn = getDBConnection();
    $sql = "UPDATE notifications SET is_read = 1 WHERE id = ? AND user_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ii", $notification_id, $user_id);
    return $stmt->execute();
}

/**
 * Mark all notifications as read for a user
 * @param int $user_id
 * @return bool
 */
function markAllNotificationsAsRead($user_id) {
    $conn = getDBConnection();
    $sql = "UPDATE notifications SET is_read = 1 WHERE user_id = ? AND is_read = 0";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $user_id);
    return $stmt->execute();
}

// ==========================================
// Activity Log Functions
// ==========================================

/**
 * Log a user activity
 * @param string $action - Action name (e.g., 'USER_LOGIN', 'LEAVE_APPROVE')
 * @param string $description - Human-readable description
 * @param string|null $table_name - Affected table
 * @param int|null $record_id - Affected record ID
 * @param array|null $old_values - Old values (associative array)
 * @param array|null $new_values - New values (associative array)
 * @return int Log ID
 */
function logActivity($action, $description, $table_name = null, $record_id = null, $old_values = null, $new_values = null) {
    $conn = getDBConnection();
    
    $user_id = $_SESSION['user_id'] ?? null;
    $ip_address = $_SERVER['REMOTE_ADDR'] ?? null;
    $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? null;
    
    $old_values_json = $old_values ? json_encode($old_values) : null;
    $new_values_json = $new_values ? json_encode($new_values) : null;
    
    $sql = "INSERT INTO activity_logs (user_id, action, description, table_name, record_id, old_values, new_values, ip_address, user_agent) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("isssissss", $user_id, $action, $description, $table_name, $record_id, $old_values_json, $new_values_json, $ip_address, $user_agent);
    
    if ($stmt->execute()) {
        return $conn->insert_id;
    }
    return 0;
}

/**
 * Get activity logs for a specific record
 * @param string $table_name
 * @param int $record_id
 * @param int $limit
 * @return array
 */
function getActivityLogs($table_name, $record_id, $limit = 20) {
    $conn = getDBConnection();
    $sql = "SELECT al.*, u.full_name as user_name 
            FROM activity_logs al 
            LEFT JOIN users u ON al.user_id = u.id 
            WHERE al.table_name = ? AND al.record_id = ? 
            ORDER BY al.created_at DESC 
            LIMIT ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("sii", $table_name, $record_id, $limit);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $logs = [];
    while ($row = $result->fetch_assoc()) {
        $logs[] = $row;
    }
    return $logs;
}

/**
 * Get recent activity logs for a user
 * @param int $user_id
 * @param int $limit
 * @return array
 */
function getUserActivityLogs($user_id, $limit = 50) {
    $conn = getDBConnection();
    $sql = "SELECT * FROM activity_logs WHERE user_id = ? ORDER BY created_at DESC LIMIT ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ii", $user_id, $limit);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $logs = [];
    while ($row = $result->fetch_assoc()) {
        $logs[] = $row;
    }
    return $logs;
}

// ==========================================
// Email Notification Helper (Placeholder)
// ==========================================

/**
 * Check if a specific notification type is enabled
 * @param string $type notification type (leave, task, payroll, etc)
 * @return bool
 */
function isNotificationEnabled($type) {
    $setting_key = 'notify_' . $type;
    return getSetting($setting_key, '1') === '1';
}

/**
 * Send leave notification email
 * @param array $user - User data
 * @param string $leave_status - approved/rejected/pending
 * @param string $remarks - HR/PL remarks
 * @return bool
 */
function sendLeaveNotificationEmail($user, $leave_status, $remarks = '') {
    if (!isNotificationEnabled('leave')) {
        return true; // Notification disabled by admin
    }
    
    $subject = "Leave Application " . ucfirst($leave_status);
    $message = "Dear " . $user['full_name'] . ",\n\n";
    $message .= "Your leave application has been " . $leave_status . ".\n";
    if ($remarks) {
        $message .= "Remarks: " . $remarks . "\n";
    }
    $message .= "\nPlease login to the HRMS portal for more details.\n\n";
    $message .= "Best regards,\nNexGen HRMS";
    
    return sendEmailNotification($user['email'], $subject, $message);
}

/**
 * Send task assignment notification email
 * @param array $user - User data
 * @param string $task_title - Task title
 * @param string $due_date - Due date
 * @return bool
 */
function sendTaskAssignmentEmail($user, $task_title, $due_date) {
    if (!isNotificationEnabled('task')) {
        return true; // Notification disabled by admin
    }
    
    $subject = "New Task Assigned: " . $task_title;
    $message = "Dear " . $user['full_name'] . ",\n\n";
    $message .= "You have been assigned a new task: " . $task_title . "\n";
    $message .= "Due Date: " . $due_date . "\n\n";
    $message .= "Please login to the HRMS portal to view details and update progress.\n\n";
    $message .= "Best regards,\nNexGen HRMS";
    
    return sendEmailNotification($user['email'], $subject, $message);
}
/**
 * Check if the application is running in a local environment
 * @return bool
 */
function isLocalEnvironment() {
    $local_hosts = ['127.0.0.1', '::1', 'localhost'];
    return (isset($_SERVER['REMOTE_ADDR']) && in_array($_SERVER['REMOTE_ADDR'], $local_hosts)) 
        || (isset($_SERVER['SERVER_NAME']) && in_array($_SERVER['SERVER_NAME'], $local_hosts));
}
/**
 * Notify a user that they have been added to a project
 * @param int $project_id
 * @param int $user_id
 * @return bool
 */
function notifyProjectAssignment($project_id, $user_id) {
    if (!isNotificationEnabled('notify_project')) return false;

    $conn = getDBConnection();
    
    // Get project name
    $proj_sql = "SELECT project_name FROM projects WHERE id = ?";
    $proj_stmt = $conn->prepare($proj_sql);
    $proj_stmt->bind_param("i", $project_id);
    $proj_stmt->execute();
    $proj_res = $proj_stmt->get_result();
    $proj_data = $proj_res->fetch_assoc();
    
    // Get member info
    $user_sql = "SELECT full_name, email FROM users WHERE id = ?";
    $user_stmt = $conn->prepare($user_sql);
    $user_stmt->bind_param("i", $user_id);
    $user_stmt->execute();
    $user_res = $user_stmt->get_result();
    $user_data = $user_res->fetch_assoc();
    
    if (!$user_data || !$proj_data) return false;

    $subject = "You've been added to a new project: " . $proj_data['project_name'];
    $message = "
        <div style='font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; padding: 20px; border: 1px solid #ddd; border-radius: 10px;'>
            <h2 style='color: #0d6efd;'>Project Assignment</h2>
            <p>Hello <strong>" . htmlspecialchars($user_data['full_name']) . "</strong>,</p>
            <p>You have been added as a team member to the project: <strong>" . htmlspecialchars($proj_data['project_name']) . "</strong>.</p>
            <p>You can now view the project details, team members, and your assigned tasks in the NexGen HRMS Project module.</p>
            <div style='margin-top: 30px; padding: 15px; background: #f8f9fa; border-radius: 5px; border-left: 4px solid #0d6efd;'>
                <p style='margin: 0; font-style: italic;'>\"Working together to achieve excellence.\"</p>
            </div>
            <p style='margin-top: 30px;'>Best regards,<br><strong>NexGen HRMS Team</strong></p>
        </div>
    ";
    
    return sendEmailNotification($user_data['email'], $subject, $message);
}

/**
 * Send notification when user account is created
 * @param array $user_data - User data (email, full_name, username, role)
 * @param string $temporary_password - Temporary password
 * @return bool Success status
 */
function notifyUserAccountCreated($user_data, $temporary_password = 'Nexgen@123') {
    if (!isNotificationEnabled('email_notifications')) return false;
    
    $subject = "Welcome to NexGen HRMS - Your Account Has Been Created";
    $message = "
        <div style='font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; padding: 20px; border: 1px solid #ddd; border-radius: 10px;'>
            <h2 style='color: #28a745;'>Welcome to NexGen HRMS!</h2>
            <p>Hello <strong>" . htmlspecialchars($user_data['full_name']) . "</strong>,</p>
            <p>Your account has been created in the NexGen HRMS system.</p>
            
            <div style='background: #f8f9fa; padding: 15px; border-radius: 5px; margin: 20px 0;'>
                <h4 style='margin-top: 0;'>Login Credentials:</h4>
                <p><strong>Username:</strong> " . htmlspecialchars($user_data['username']) . "</p>
                <p><strong>Temporary Password:</strong> <code style='background: #e9ecef; padding: 2px 6px; border-radius: 3px;'>" . htmlspecialchars($temporary_password) . "</code></p>
                <p><strong>Role:</strong> " . htmlspecialchars(ucfirst(str_replace('_', ' ', $user_data['role']))) . "</p>
            </div>
            
            <p style='color: #dc3545; font-weight: bold;'>⚠️ Please change your password after your first login!</p>
            
            <p>You can access the system at: <a href='" . htmlspecialchars(getEnv('APP_URL', 'http://localhost/nexgen_hrms_late')) . "'>NexGen HRMS Login</a></p>
            
            <div style='margin-top: 30px; padding: 15px; background: #f8f9fa; border-radius: 5px; border-left: 4px solid #28a745;'>
                <p style='margin: 0; font-style: italic;'>\"Empowering your workforce with modern HR management.\"</p>
            </div>
            
            <p style='margin-top: 30px;'>Best regards,<br><strong>NexGen HRMS Team</strong></p>
        </div>
    ";
    
    return sendEmailNotification($user_data['email'], $subject, $message);
}

/**
 * Send notification when user status changes
 * @param array $user_data - User data (email, full_name, username)
 * @param string $new_status - New status (active, suspended, inactive)
 * @param string $changed_by - Name of admin who made the change
 * @return bool Success status
 */
function notifyUserStatusChanged($user_data, $new_status, $changed_by = 'Admin') {
    if (!isNotificationEnabled('email_notifications')) return false;
    
    $status_messages = [
        'active' => ['Your Account Has Been Reactivated', 'Your account has been reactivated. You can now access all system features.'],
        'suspended' => ['Account Suspended', 'Your account has been temporarily suspended. Please contact HR or Admin for more information.'],
        'inactive' => ['Account Deactivated', 'Your account has been deactivated. Please contact HR if you believe this is an error.']
    ];
    
    list($subject, $main_message) = $status_messages[$new_status] ?? ['Account Status Changed', 'Your account status has been updated.'];
    
    $message = "
        <div style='font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; padding: 20px; border: 1px solid #ddd; border-radius: 10px;'>
            <h2 style='color: " . ($new_status === 'active' ? '#28a745' : '#dc3545') . ";'>" . htmlspecialchars($subject) . "</h2>
            <p>Hello <strong>" . htmlspecialchars($user_data['full_name']) . "</strong>,</p>
            <p>" . $main_message . "</p>
            
            <div style='background: #f8f9fa; padding: 15px; border-radius: 5px; margin: 20px 0;'>
                <p><strong>New Status:</strong> <span style='color: " . ($new_status === 'active' ? '#28a745' : '#dc3545') . "; font-weight: bold;'>" . ucfirst($new_status) . "</span></p>
                <p><strong>Changed By:</strong> " . htmlspecialchars($changed_by) . "</p>
                <p><strong>Date:</strong> " . date('F j, Y g:i a') . "</p>
            </div>
            
            <p style='margin-top: 30px;'>Best regards,<br><strong>NexGen HRMS Team</strong></p>
        </div>
    ";
    
    return sendEmailNotification($user_data['email'], $subject, $message);
}

/**
 * Send notification when user role is changed
 * @param array $user_data - User data (email, full_name, username)
 * @param string $old_role - Previous role
 * @param string $new_role - New role
 * @param string $changed_by - Name of admin who made the change
 * @return bool Success status
 */
function notifyUserRoleChanged($user_data, $old_role, $new_role, $changed_by = 'Admin') {
    if (!isNotificationEnabled('email_notifications')) return false;
    
    $subject = "Your Role Has Been Updated - NexGen HRMS";
    $message = "
        <div style='font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; padding: 20px; border: 1px solid #ddd; border-radius: 10px;'>
            <h2 style='color: #0d6efd;'>Role Update Notification</h2>
            <p>Hello <strong>" . htmlspecialchars($user_data['full_name']) . "</strong>,</p>
            <p>Your role in the NexGen HRMS system has been updated.</p>
            
            <div style='background: #f8f9fa; padding: 15px; border-radius: 5px; margin: 20px 0;'>
                <p><strong>Previous Role:</strong> " . htmlspecialchars(ucfirst(str_replace('_', ' ', $old_role))) . "</p>
                <p><strong>New Role:</strong> <span style='color: #0d6efd; font-weight: bold;'>" . htmlspecialchars(ucfirst(str_replace('_', ' ', $new_role))) . "</span></p>
                <p><strong>Changed By:</strong> " . htmlspecialchars($changed_by) . "</p>
                <p><strong>Date:</strong> " . date('F j, Y g:i a') . "</p>
            </div>
            
            <p>Your permissions and access levels have been updated accordingly. Please log in to explore your new capabilities.</p>
            
            <p style='margin-top: 30px;'>Best regards,<br><strong>NexGen HRMS Team</strong></p>
        </div>
    ";
    
    return sendEmailNotification($user_data['email'], $subject, $message);
}

/**
 * Send notification to all users when announcement is created
 * @param int $announcement_id - Announcement ID
 * @param string $target_audience - Target audience (all, employees, etc.)
 * @return int Number of notifications sent
 */
function notifyAnnouncementCreated($announcement_id, $target_audience = 'all') {
    $conn = getDBConnection();
    
    // Get announcement details
    $ann_sql = "SELECT * FROM announcements WHERE id = ?";
    $ann_stmt = $conn->prepare($ann_sql);
    $ann_stmt->bind_param("i", $announcement_id);
    $ann_stmt->execute();
    $announcement = $ann_stmt->get_result()->fetch_assoc();
    
    if (!$announcement) return 0;
    
    // Get target users
    $user_sql = "SELECT id, email, full_name, role FROM users WHERE status = 'active'";
    
    if ($target_audience !== 'all') {
        $role_map = [
            'employees' => 'employee',
            'project_leaders' => 'project_leader',
            'hr' => 'hr',
            'admin' => 'admin'
        ];
        $target_role = $role_map[$target_audience] ?? null;
        if ($target_role) {
            $user_sql .= " AND role = '$target_role'";
        }
    }
    
    $user_sql .= " ORDER BY id";
    $users = $conn->query($user_sql);
    
    $count = 0;
    while ($user = $users->fetch_assoc()) {
        // Create in-app notification
        createNotification(
            $user['id'],
            '📢 ' . $announcement['title'],
            substr(strip_tags($announcement['content']), 0, 200) . '...',
            $announcement['priority'] === 'urgent' ? 'danger' : 'info',
            'announcement',
            'modules/admin/announcements.php'
        );
        
        // Send email if enabled
        if (isNotificationEnabled('notify_announcement')) {
            $subject = "📢 " . $announcement['title'];
            $message = "
                <div style='font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; padding: 20px; border: 1px solid #ddd; border-radius: 10px;'>
                    <h2 style='color: #0d6efd;'>" . htmlspecialchars($announcement['title']) . "</h2>
                    <p>Hello <strong>" . htmlspecialchars($user['full_name']) . "</strong>,</p>
                    <div style='background: #f8f9fa; padding: 15px; border-radius: 5px; margin: 20px 0;'>
                        " . nl2br(htmlspecialchars($announcement['content'])) . "
                    </div>
                    <p style='margin-top: 30px;'>Best regards,<br><strong>NexGen HRMS Team</strong></p>
                </div>
            ";
            sendEmailNotification($user['email'], $subject, $message);
            $count++;
        }
    }
    
    $conn->close();
    return $count;
}

/**
 * Log admin activity for audit purposes
 * @param string $action - Action type (USER_CREATED, USER_UPDATED, etc.)
 * @param string $description - Human-readable description
 * @param int|null $target_user_id - Affected user ID
 * @param array $details - Additional details (old_values, new_values, etc.)
 * @return int Log ID
 */
function logAdminActivity($action, $description, $target_user_id = null, $details = []) {
    $conn = getDBConnection();
    
    $admin_id = $_SESSION['user_id'] ?? null;
    $admin_name = $_SESSION['full_name'] ?? 'Unknown';
    $ip_address = $_SERVER['REMOTE_ADDR'] ?? null;
    
    $details_json = !empty($details) ? json_encode($details) : null;
    
    $sql = "INSERT INTO activity_logs (user_id, action, description, table_name, record_id, old_values, new_values, ip_address, user_agent)
            VALUES (?, ?, ?, 'users', ?, ?, ?, ?, ?)";
    
    $stmt = $conn->prepare($sql);
    $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? null;
    $stmt->bind_param("isssiss", $admin_id, $action, $description, $target_user_id, $details_json, $details_json, $ip_address, $user_agent);
    
    if ($stmt->execute()) {
        $log_id = $conn->insert_id;
        $conn->close();
        return $log_id;
    }
    
    $conn->close();
    return 0;
}
?>
