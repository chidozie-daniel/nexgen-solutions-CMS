<?php
// Use absolute path relative to the root of nexgen_hrms
$base_dir = dirname(dirname(__FILE__));
require_once $base_dir . '/config/database.php';

// Redirect function
function redirect($url) {
    header("Location: $url");
    exit();
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

// Get all employees for dropdown
function getEmployees($exclude_self = true) {
    $conn = getDBConnection();
    $sql = "SELECT id, employee_id, full_name, department FROM users WHERE status = 'active'";
    
    if ($exclude_self && isset($_SESSION['user_id'])) {
        $sql .= " AND id != ? ORDER BY full_name";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $_SESSION['user_id']);
        $stmt->execute();
        $result = $stmt->get_result();
    } else {
        $sql .= " ORDER BY full_name";
        $result = $conn->query($sql);
    }
    
    $employees = [];
    while ($row = $result->fetch_assoc()) {
        $employees[] = $row;
    }
    
    return $employees;
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
        COUNT(*) as used_days 
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
    
    // Default leave allocations
    $allocations = [
        'annual' => 15,
        'sick' => 10,
        'casual' => 7
    ];
    
    return ($allocations[$type] ?? 0) - $used;
}
?>