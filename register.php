<?php
require_once 'includes/header.php';
Auth::requireLogin();

// Only admin and HR can register new users
if (!Auth::hasRole('admin') && !Auth::hasRole('hr')) {
    setFlash('danger', 'You do not have permission to register users.');
    header('Location: ' . Auth::getBasePath() . '/dashboard.php');
    exit();
}

$page_title = 'Register New User';
$conn = getDBConnection();

// Generate employee ID
function generateEmployeeID($department) {
    $prefix = strtoupper(substr($department, 0, 3));
    $random = str_pad(rand(1, 999), 3, '0', STR_PAD_LEFT);
    return $prefix . $random . date('y');
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        setFlash('danger', 'Invalid security token. Please try again.');
        header('Location: ' . Auth::getBasePath() . '/register.php');
        exit();
    }
    $full_name = sanitizeText($_POST['full_name'] ?? '', 120);
    $email = trim($_POST['email'] ?? '');
    $username = sanitizeText($_POST['username'] ?? '', 30);
    $role = $_POST['role'] ?? '';
    $department = sanitizeText($_POST['department'] ?? '', 60);
    $position = sanitizeText($_POST['position'] ?? '', 80);
    $salary = $_POST['salary'] ?? null;
    $hire_date = $_POST['hire_date'] ?? '';

    $errors = [];

    if ($full_name === '' || strlen($full_name) < 2) {
        $errors[] = 'Full name is required.';
    }
    if (!isValidEmail($email)) {
        $errors[] = 'A valid email address is required.';
    }
    if (!isValidUsername($username)) {
        $errors[] = 'Username must be 3-30 characters (letters, numbers, dot, underscore, dash).';
    }

    $allowed_roles = ['employee', 'project_leader', 'hr', 'admin'];
    if (!in_array($role, $allowed_roles, true)) {
        $errors[] = 'Invalid role selected.';
    }

    $allowed_departments = ['IT', 'HR', 'Finance', 'Marketing', 'Sales', 'Operations', 'Development', 'Support'];
    if (!in_array($department, $allowed_departments, true)) {
        $errors[] = 'Invalid department selected.';
    }

    if ($position === '') {
        $errors[] = 'Position is required.';
    }

    if ($salary !== null && $salary !== '') {
        if (!isNonNegativeNumber($salary)) {
            $errors[] = 'Salary must be a non-negative number.';
        } else {
            $salary = (float)$salary;
        }
    } else {
        $salary = 0;
    }

    if ($hire_date !== '' && !isValidDate($hire_date)) {
        $errors[] = 'Invalid hire date.';
    }
    
    // Generate password (can be changed by user later)
    $password = 'Welcome@123';
    $hashed_password = password_hash($password, PASSWORD_DEFAULT);
    
    // Generate employee ID
    $employee_id = generateEmployeeID($department);
    
    if (!empty($errors)) {
        setFlash('danger', implode('<br>', $errors));
    } else {
        // Check if username or email exists
        $check_sql = "SELECT id FROM users WHERE username = ? OR email = ?";
        $check_stmt = dbPrepare($conn, $check_sql, 'register duplicate check');
        if (!$check_stmt) {
            setFlash('danger', 'Could not verify account details. Please try again.');
        } else {
        $check_stmt->bind_param("ss", $username, $email);
        if (!dbExecute($check_stmt, 'register duplicate check')) {
            $check_stmt->close();
            setFlash('danger', 'Could not verify account details. Please try again.');
        } else {
        $check_result = $check_stmt->get_result();
        
        if ($check_result->num_rows > 0) {
            $check_stmt->close();
            setFlash('danger', 'Username or email already exists.');
        } else {
            $sql = "INSERT INTO users (employee_id, username, email, password, full_name, role, department, position, salary, hire_date) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
            $stmt = dbPrepare($conn, $sql, 'register insert');
            if (!$stmt) {
                $check_stmt->close();
                setFlash('danger', 'Error registering user.');
            } else {
                $stmt->bind_param("ssssssssds", $employee_id, $username, $email, $hashed_password, $full_name, $role, $department, $position, $salary, $hire_date);
                if (dbExecute($stmt, 'register insert')) {
                    setFlash('success', "User registered successfully! Employee ID: " . htmlspecialchars($employee_id));
                    $stmt->close();
                    $check_stmt->close();
                    header('Location: ' . Auth::getBasePath() . '/modules/admin/users.php');
                    exit();
                }
                setFlash('danger', 'Error registering user.');
                $stmt->close();
                $check_stmt->close();
            }
        }
    }
    }
    }
}
?>
<div class="container-fluid">
    <div class="row justify-content-center">
        <div class="col-md-8">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">Register New Employee</h5>
                </div>
                <div class="card-body">
                    <form method="POST" action="">
                        <?php echo csrfField(); ?>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Full Name *</label>
                                <input type="text" class="form-control" name="full_name" data-required="true">
                            </div>
                            
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Email Address *</label>
                                <input type="email" class="form-control" name="email" data-required="true">
                            </div>
                            
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Username *</label>
                                <input type="text" class="form-control" name="username" data-required="true">
                            </div>
                            
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Role *</label>
                                <select class="form-select" name="role" data-required="true">
                                    <option value="employee">Employee</option>
                                    <option value="project_leader">Project Leader</option>
                                    <option value="hr">HR Staff</option>
                                    <option value="admin">Administrator</option>
                                </select>
                            </div>
                            
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Department *</label>
                                <select class="form-select" name="department" data-required="true">
                                    <option value="">Select Department</option>
                                    <option value="IT">Information Technology</option>
                                    <option value="HR">Human Resources</option>
                                    <option value="Finance">Finance</option>
                                    <option value="Marketing">Marketing</option>
                                    <option value="Sales">Sales</option>
                                    <option value="Operations">Operations</option>
                                    <option value="Development">Development</option>
                                    <option value="Support">Support</option>
                                </select>
                            </div>
                            
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Position *</label>
                                <input type="text" class="form-control" name="position" data-required="true" 
                                       placeholder="e.g., Software Engineer">
                            </div>
                            
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Salary ($)</label>
                                <input type="number" step="0.01" class="form-control" name="salary" 
                                       placeholder="Monthly salary">
                            </div>
                            
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Hire Date</label>
                                <input type="date" class="form-control" name="hire_date" 
                                       value="<?php echo date('Y-m-d'); ?>">
                            </div>
                            
                            <div class="col-md-12">
                                <div class="alert alert-info">
                                    <i class="bi bi-info-circle"></i>
                                    A temporary password will be generated and can be changed by the user on first login.
                                </div>
                            </div>
                            
                            <div class="col-md-12">
                                <button type="submit" class="btn btn-primary">Register User</button>
                                <a href="<?php echo $base_url; ?>/modules/admin/users.php" class="btn btn-outline-secondary">Cancel</a>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>
