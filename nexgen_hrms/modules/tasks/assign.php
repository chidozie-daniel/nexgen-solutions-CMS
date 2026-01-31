<?php
require_once '../../includes/auth.php';
require_once '../../includes/functions.php';

// Check login status first
Auth::requireLogin();

// Only project leaders, HR, and admin can assign tasks
if (!Auth::hasRole('project_leader') && !Auth::hasRole('hr') && !Auth::hasRole('admin')) {
    setFlash('danger', 'You do not have permission to assign tasks.');
    header('Location: ' . Auth::getBasePath() . '/dashboard.php');
    exit();
}

$page_title = 'Assign Task';
$conn = getDBConnection();
$user_id = $_SESSION['user_id'];

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $title = trim($_POST['title']);
    $description = trim($_POST['description']);
    $assigned_to = $_POST['assigned_to'];
    $project_id = $_POST['project_id'] ?: null; // Handle empty string as NULL
    $priority = $_POST['priority'];
    $due_date = $_POST['due_date'];
    
    // Validation
    if (strtotime($due_date) < strtotime(date('Y-m-d'))) {
        setFlash('danger', 'Due date cannot be in the past.');
        // In a full implementation, we'd pass old input back. For now, redirect.
        // Or better, just drop through to show form? 
        // Showing form is better but we lost the data without extensive refactor.
        // Redirecting is safe for this patch.
         header('Location: ' . Auth::getBasePath() . '/modules/tasks/assign.php');
         exit();
    }
    
    $sql = "INSERT INTO tasks (title, description, project_id, assigned_to, assigned_by, priority, due_date, status) 
            VALUES (?, ?, ?, ?, ?, ?, ?, 'pending')";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ssiiiss", $title, $description, $project_id, $assigned_to, $user_id, $priority, $due_date);
    
    if ($stmt->execute()) {
        $task_id = $stmt->insert_id;
        
        // Notify user (Optional: Implement notification system later)
        
        setFlash('success', 'Task assigned successfully!');
        header('Location: ' . Auth::getBasePath() . '/modules/tasks/my_tasks.php');
        exit();
    } else {
        setFlash('danger', 'Error assigning task: ' . $stmt->error);
    }
}

// Get employees and projects for dropdowns
$employees = getEmployees();
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
                        <div class="row">
                            <div class="col-md-12 mb-3">
                                <label for="title" class="form-label">Task Title</label>
                                <input type="text" class="form-control" id="title" name="title" required 
                                       placeholder="Enter task title">
                            </div>
                            
                            <div class="col-md-12 mb-3">
                                <label for="description" class="form-label">Task Description</label>
                                <textarea class="form-control" id="description" name="description" rows="4" required 
                                          placeholder="Describe the task in detail..."></textarea>
                            </div>
                            
                            <div class="col-md-6 mb-3">
                                <label for="assigned_to" class="form-label">Assign To</label>
                                <select class="form-select" id="assigned_to" name="assigned_to" required>
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
                                <select class="form-select" id="project_id" name="project_id">
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
                                <select class="form-select" id="priority" name="priority" required>
                                    <option value="low">Low</option>
                                    <option value="medium" selected>Medium</option>
                                    <option value="high">High</option>
                                    <option value="critical">Critical</option>
                                </select>
                            </div>
                            
                            <div class="col-md-4 mb-3">
                                <label for="due_date" class="form-label">Due Date</label>
                                <input type="date" class="form-control" id="due_date" name="due_date" required 
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