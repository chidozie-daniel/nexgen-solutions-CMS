<?php
// Define entry point constant
define('APP_ENTRY_POINT', true);

require_once '../../includes/auth.php';
require_once '../../includes/functions.php';

// Check login status and require project leader, HR, or admin role
Auth::requireRole(['project_leader', 'hr', 'admin']);

$page_title = 'Assign Task';
$conn = getDBConnection();
$user_id = $_SESSION['user_id'];

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Verify CSRF token
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        setFlash('danger', 'Invalid security token. Please try again.');
        header('Location: assign.php');
        exit();
    }
    $title = sanitizeText($_POST['title'] ?? '', 120);
    $description = sanitizeText($_POST['description'] ?? '', 2000, true);
    $assigned_to = $_POST['assigned_to'] ?? '';
    $project_id = $_POST['project_id'] ?: null; // Handle empty string as NULL
    $priority = $_POST['priority'] ?? '';
    $due_date = $_POST['due_date'] ?? '';
    
    // Validation
    $errors = [];
    if ($title === '') {
        $errors[] = 'Task title is required.';
    }
    if ($description === '' || strlen($description) < 5) {
        $errors[] = 'Task description is required.';
    }
    if (!is_numeric($assigned_to) || (int)$assigned_to <= 0) {
        $errors[] = 'Please select an employee to assign.';
    }
    if ($project_id !== null && $project_id !== '' && (!is_numeric($project_id) || (int)$project_id <= 0)) {
        $errors[] = 'Invalid project selected.';
    }
    $allowed_priority = ['low', 'medium', 'high', 'critical'];
    if (!in_array($priority, $allowed_priority, true)) {
        $errors[] = 'Invalid priority selected.';
    }
    if (!isValidDate($due_date)) {
        $errors[] = 'Please provide a valid due date.';
    } elseif (strtotime($due_date) < strtotime(date('Y-m-d'))) {
        $errors[] = 'Due date cannot be in the past.';
    }

    if (!empty($errors)) {
        setFlash('danger', implode('<br>', $errors));
        header('Location: ' . Auth::getBasePath() . '/modules/tasks/assign.php');
        exit();
    }
    
    $sql = "INSERT INTO tasks (title, description, project_id, assigned_to, assigned_by, priority, due_date, status) 
            VALUES (?, ?, ?, ?, ?, ?, ?, 'pending')";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ssiiiss", $title, $description, $project_id, $assigned_to, $user_id, $priority, $due_date);
    
    if ($stmt->execute()) {
        $task_id = $stmt->insert_id;
        
        // Log activity
        logActivity('TASK_ASSIGN', 'Task assigned: ' . $title, 'tasks', $task_id, null, [
            'assigned_to' => $assigned_to,
            'priority' => $priority,
            'due_date' => $due_date
        ]);
        
        // Get employee data for notification
        $emp_sql = "SELECT full_name, email FROM users WHERE id = ?";
        $emp_stmt = $conn->prepare($emp_sql);
        $emp_stmt->bind_param("i", $assigned_to);
        $emp_stmt->execute();
        $employee = $emp_stmt->get_result()->fetch_assoc();
        
        // Get project name if assigned
        $project_name = '';
        if ($project_id) {
            $proj_sql = "SELECT project_name FROM projects WHERE id = ?";
            $proj_stmt = $conn->prepare($proj_sql);
            $proj_stmt->bind_param("i", $project_id);
            $proj_stmt->execute();
            $proj_result = $proj_stmt->get_result();
            if ($proj_result->num_rows > 0) {
                $project_name = $proj_result->fetch_assoc()['project_name'];
            }
        }
        
        // Notify employee
        createNotification($assigned_to,
            'New Task Assigned: ' . substr($title, 0, 50),
            'You have been assigned a new task: "' . $title . '"' . ($project_name ? ' for project "' . $project_name . '"' : '') . '. Priority: ' . ucfirst($priority) . '. Due: ' . formatDate($due_date, 'M d, Y'),
            'info',
            'task',
            '/modules/tasks/view.php?id=' . $task_id,
            $user_id
        );
        
        // Send email notification
        sendTaskAssignmentEmail($employee, $title, formatDate($due_date, 'F d, Y'));
        
        setFlash('success', 'Task assigned successfully! ' . $employee['full_name'] . ' has been notified.');
        header('Location: ' . Auth::getBasePath() . '/modules/tasks/my_tasks.php');
        exit();
    } else {
        setFlash('danger', 'Error assigning task: ' . $stmt->error);
    }
}

// Get employees and projects for dropdowns
// Show role-appropriate users based on who is assigning
$creator_role = $_SESSION['role'];
if ($creator_role === 'admin' || $creator_role === 'hr') {
    // Admin/HR can assign to anyone (Project Leaders and Employees)
    $employees = getEmployees(true, ['project_leader', 'employee']);
} else if ($creator_role === 'project_leader') {
    // Project Leaders typically assign to Employees
    $employees = getEmployees(true, ['employee']);
} else {
    // Fallback: all active users
    $employees = getEmployees();
}
$projects = getProjects();

require_once '../../includes/header.php';
?>
<div class="container-fluid">
    <div class="row">
        <div class="col-lg-8">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">Assign New Task</h5>
                </div>
                <div class="card-body">
                    <form method="POST" action="">
                        <?php echo csrfField(); ?>
                        <div class="row">
                            <div class="col-md-12 mb-3">
                                <label for="title" class="form-label">Task Title</label>
                                <input type="text" class="form-control" id="title" name="title" data-required="true" 
                                       placeholder="Enter task title">
                            </div>
                            
                            <div class="col-md-12 mb-3">
                                <label for="description" class="form-label">Task Description</label>
                                <textarea class="form-control" id="description" name="description" rows="4" data-required="true" 
                                          placeholder="Describe the task in detail..."></textarea>
                            </div>
                            
                            <div class="col-md-6 mb-3">
                                <label for="assigned_to" class="form-label">Assign To</label>
                                <select class="form-select" id="assigned_to" name="assigned_to" data-required="true">
                                    <option value="">Select Employee</option>
                                    <?php foreach ($employees as $emp): ?>
                                    <option value="<?php echo $emp['id']; ?>">
                                        <?php echo htmlspecialchars($emp['full_name']); ?> (<?php echo htmlspecialchars($emp['employee_id'] ?? 'N/A'); ?>) - <?php echo htmlspecialchars($emp['department'] ?? 'N/A'); ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <div class="col-md-6 mb-3">
                                <label for="project_id" class="form-label">Project (Optional)</label>
                                <select class="form-select" id="project_id" name="project_id" data-optional="true">
                                    <option value="">Select Project</option>
                                    <?php foreach ($projects as $project): ?>
                                    <option value="<?php echo $project['id']; ?>">
                                        <?php echo htmlspecialchars($project['project_code'] ?? 'N/A'); ?> - <?php echo htmlspecialchars($project['project_name']); ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <div class="col-md-4 mb-3">
                                <label for="priority" class="form-label">Priority</label>
                                <select class="form-select" id="priority" name="priority" data-required="true">
                                    <option value="low">Low</option>
                                    <option value="medium" selected>Medium</option>
                                    <option value="high">High</option>
                                    <option value="critical">Critical</option>
                                </select>
                            </div>
                            
                            <div class="col-md-4 mb-3">
                                <label for="due_date" class="form-label">Due Date</label>
                                <input type="date" class="form-control" id="due_date" name="due_date" data-required="true" 
                                       min="<?php echo date('Y-m-d'); ?>">
                            </div>
                            
                            <div class="col-md-4 mb-3">
                                <label class="form-label">Estimated Duration</label>
                                <select class="form-select" id="duration" name="duration">
                                    <option value="1">1 Day</option>
                                    <option value="3">3 Days</option>
                                    <option value="7">1 Week</option>
                                    <option value="14">2 Weeks</option>
                                    <option value="30">1 Month</option>
                                </select>
                            </div>
                            
                            <div class="col-md-12">
                                <button type="submit" class="btn btn-primary">Assign Task</button>
                                <a href="<?php echo $base_url; ?>/modules/tasks/my_tasks.php" class="btn btn-outline-secondary">Cancel</a>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        
        <div class="col-lg-4">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">Task Assignment Guidelines</h5>
                </div>
                <div class="card-body">
                    <div class="alert alert-info">
                        <h6><i class="bi bi-info-circle"></i> Best Practices</h6>
                        <ul class="small mb-0">
                            <li>Be clear and specific about task requirements</li>
                            <li>Set realistic deadlines</li>
                            <li>Consider team member's current workload</li>
                            <li>Assign tasks based on skills and expertise</li>
                            <li>Provide necessary resources and support</li>
                        </ul>
                    </div>
                    
                    <div class="mt-3">
                        <h6>Priority Definitions</h6>
                        <div class="list-group small">
                            <div class="list-group-item">
                                <span class="badge bg-success me-2">Low</span>
                                Routine tasks, no immediate deadline
                            </div>
                            <div class="list-group-item">
                                <span class="badge bg-primary me-2">Medium</span>
                                Standard priority, complete within timeframe
                            </div>
                            <div class="list-group-item">
                                <span class="badge bg-warning text-dark me-2">High</span>
                                Important, needs attention soon
                            </div>
                            <div class="list-group-item">
                                <span class="badge bg-danger me-2">Critical</span>
                                Urgent, drop everything else
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    // Set default due date based on duration
    document.getElementById('duration').addEventListener('change', function() {
        const duration = parseInt(this.value);
        const dueDate = document.getElementById('due_date');
        
        if (duration) {
            const today = new Date();
            today.setDate(today.getDate() + duration);
            const formattedDate = today.toISOString().split('T')[0];
            dueDate.value = formattedDate;
        }
    });
</script>

<?php require_once '../../includes/footer.php'; ?>
