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
    $full_name = $_POST['full_name'];
    $email = $_POST['email'];
    $username = $_POST['username'];
    $role = $_POST['role'];
    $department = $_POST['department'];
    $position = $_POST['position'];
    $salary = $_POST['salary'] ?? 0;
    $hire_date = $_POST['hire_date'];
    
    // Generate password (can be changed by user later)
    $password = 'Welcome@123';
    $hashed_password = password_hash($password, PASSWORD_DEFAULT);
    
    // Generate employee ID
    $employee_id = generateEmployeeID($department);
    
    // Check if username or email exists
    $check_sql = "SELECT id FROM users WHERE username = ? OR email = ?";
    $check_stmt = $conn->prepare($check_sql);
    $check_stmt->bind_param("ss", $username, $email);
    $check_stmt->execute();
    $check_result = $check_stmt->get_result();
    
    if ($check_result->num_rows > 0) {
        setFlash('danger', 'Username or email already exists.');
    } else {
        // Insert new user
        $sql = "INSERT INTO users (employee_id, username, email, password, full_name, role, department, position, salary, hire_date) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ssssssssds", $employee_id, $username, $email, $hashed_password, $full_name, $role, $department, $position, $salary, $hire_date);
        
        if ($stmt->execute()) {
            // Send welcome email (in production)
            /*
            $subject = "Welcome to NexGen Solutions!";
            $message = "Hello $full_name,\n\n";
            $message .= "Your account has been created.\n";
            $message .= "Employee ID: $employee_id\n";
            $message .= "Username: $username\n";
            $message .= "Temporary Password: $password\n\n";
            $message .= "Please login at: " . $_SERVER['HTTP_HOST'] . "/login.php\n\n";
            $message .= "Best regards,\nNexGen Solutions HR Team";
            
            mail($email, $subject, $message);
            */
            
            setFlash('success', "User registered successfully! Employee ID: " . htmlspecialchars($employee_id));
            header('Location: ' . Auth::getBasePath() . '/modules/admin/users.php');
            exit();
        } else {
            setFlash('danger', 'Error registering user.');
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
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Full Name *</label>
                                <input type="text" class="form-control" name="full_name" required>
                            </div>
                            
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Email Address *</label>
                                <input type="email" class="form-control" name="email" required>
                            </div>
                            
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Username *</label>
                                <input type="text" class="form-control" name="username" required>
                            </div>
                            
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Role *</label>
                                <select class="form-select" name="role" required>
                                    <option value="employee">Employee</option>
                                    <option value="project_leader">Project Leader</option>
                                    <option value="hr">HR Staff</option>
                                    <option value="admin">Administrator</option>
                                </select>
                            </div>
                            
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Department *</label>
                                <select class="form-select" name="department" required>
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
                                <input type="text" class="form-control" name="position" required 
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