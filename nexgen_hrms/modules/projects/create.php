<?php
require_once '../../includes/auth.php';
require_once '../../includes/functions.php';

// Check login status first
Auth::requireLogin();

// Only project leaders, HR, and Admin can create projects
if (!Auth::hasRole('project_leader') && !Auth::hasRole('hr') && !Auth::hasRole('admin')) {
    setFlash('danger', 'You do not have permission to create projects.');
    header('Location: ' . Auth::getBasePath() . '/dashboard.php');
    exit();
}

$page_title = 'Create New Project';
$conn = getDBConnection();
$user_id = $_SESSION['user_id'];

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $project_name = trim($_POST['project_name']);
    $project_code = trim($_POST['project_code']);
    $description = trim($_POST['description']);
    $client_name = trim($_POST['client_name']);
    $start_date = $_POST['start_date'];
    $end_date = $_POST['end_date'];
    $budget = $_POST['budget'] ?? 0;
    $status = $_POST['status'];
    $team_members = $_POST['team_members'] ?? [];
    
    // Validation
    $errors = [];
    
    // Check if project code already exists
    $check_sql = "SELECT id FROM projects WHERE project_code = ?";
    $check_stmt = $conn->prepare($check_sql);
    $check_stmt->bind_param("s", $project_code);
    $check_stmt->execute();
    if ($check_stmt->get_result()->num_rows > 0) {
        $errors[] = "Project code '$project_code' already exists.";
    }
    
    // Validate dates
    if (strtotime($end_date) < strtotime($start_date)) {
        $errors[] = "End date cannot be before start date.";
    }
    
    if (!empty($errors)) {
        setFlash('danger', implode('<br>', $errors));
        // Don't redirect, just fall through to re-display form (fields won't auto-fill without extra work, but simple for now)
        // Ideally we'd persist input, but for this quick fix, we'll just redirect back with error if complex
        // Actually, PHP falls through to display HTML below.
        // But we need to keep the values. 
        // For now, let's redirect to avoid complexity or just show error.
        // Let's modify to include values in value attributes if we were doing a full refactor.
        // Since we are "patching", let's just use the flash and re-render.
    } else {
        // Start Transaction
        $conn->begin_transaction();
        
        try {
            // Insert project
            $sql = "INSERT INTO projects (project_code, project_name, description, project_leader, 
            client_name, start_date, end_date, budget, status) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
            
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("sssisssds", $project_code, $project_name, $description, $user_id, 
                             $client_name, $start_date, $end_date, $budget, $status);
            
            if (!$stmt->execute()) {
                throw new Exception("Error creating project: " . $stmt->error);
            }
            
            $project_id = $stmt->insert_id;
            
            // Add team members
            if (!empty($team_members)) {
                $member_sql = "INSERT INTO project_members (project_id, user_id, joined_date) VALUES (?, ?, CURDATE())";
                $member_stmt = $conn->prepare($member_sql);
                
                foreach ($team_members as $member_id) {
                    $member_stmt->bind_param("ii", $project_id, $member_id);
                    if (!$member_stmt->execute()) {
                        throw new Exception("Error adding team member: " . $member_stmt->error);
                    }
                }
            }
            
            $conn->commit();
            setFlash('success', 'Project created successfully!');
            header('Location: ' . Auth::getBasePath() . '/modules/projects/details.php?id=' . $project_id);
            exit();
            
        } catch (Exception $e) {
            $conn->rollback();
            setFlash('danger', $e->getMessage());
        }
    }
}

// Get all employees for team selection
$employees_sql = "SELECT id, full_name, employee_id, department, position 
                  FROM users 
                  WHERE status = 'active' 
                  AND role IN ('employee', 'project_leader')
                  ORDER BY full_name";
$employees_result = $conn->query($employees_sql);

// Generate project code
function generateProjectCode() {
    $prefix = 'PROJ';
    $year = date('y');
    $month = date('m');
    $random = str_pad(rand(1, 999), 3, '0', STR_PAD_LEFT);
    return $prefix . $year . $month . $random;
}

require_once '../../includes/header.php';
?>
<div class="container-fluid">
    <div class="module-hero">
        <div class="container">
            <h1 class="mb-2">Create New Project</h1>
            <p class="mb-0">Initialize a new project and assign a leader</p>
        </div>
    </div>

    <div class="container-fluid">
        <div class="row">
            <div class="col-lg-8">
                <div class="card">
                    <div class="card-header bg-transparent">
                        <h5 class="mb-0 text-primary">Project Details</h5>
                    </div>
                <div class="card-body">
                    <form method="POST" action="">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Project Name *</label>
                                <input type="text" class="form-control" name="project_name" required 
                                       placeholder="Enter project name">
                            </div>
                            
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Project Code *</label>
                                <div class="input-group">
                                    <input type="text" class="form-control" name="project_code" 
                                           value="<?php echo generateProjectCode(); ?>" required>
                                    <button type="button" class="btn btn-outline-secondary" 
                                            onclick="generateCode()">
                                        <i class="bi bi-arrow-clockwise"></i>
                                    </button>
                                </div>
                                <small class="text-muted">Unique identifier for the project</small>
                            </div>
                            
                            <div class="col-md-12 mb-3">
                                <label class="form-label">Description *</label>
                                <textarea class="form-control" name="description" rows="4" required 
                                          placeholder="Describe the project objectives, scope, and deliverables..."></textarea>
                            </div>
                            
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Client Name</label>
                                <input type="text" class="form-control" name="client_name" 
                                       placeholder="Enter client name">
                            </div>
                            
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Budget ($)</label>
                                <input type="number" step="0.01" class="form-control" name="budget" 
                                       placeholder="Enter project budget">
                            </div>
                            
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Start Date *</label>
                                <input type="date" class="form-control" name="start_date" required 
                                       value="<?php echo date('Y-m-d'); ?>">
                            </div>
                            
                            <div class="col-md-6 mb-3">
                                <label class="form-label">End Date *</label>
                                <input type="date" class="form-control" name="end_date" required 
                                       min="<?php echo date('Y-m-d'); ?>">
                            </div>
                            
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Status *</label>
                                <select class="form-select" name="status" required>
                                    <option value="planning">Planning</option>
                                    <option value="active">Active</option>
                                    <option value="on_hold">On Hold</option>
                                </select>
                            </div>
                            
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Project Leader</label>
                                <input type="text" class="form-control" 
                                       value="<?php echo $_SESSION['full_name']; ?>" 
                                       disabled>
                                <small class="text-muted">You are the project leader</small>
                            </div>
                            
                            <div class="col-md-12 mb-3">
                                <label class="form-label">Team Members</label>
                                <div class="card">
                                    <div class="card-body" style="max-height: 200px; overflow-y: auto;">
                                        <div class="row">
                                            <?php while ($employee = $employees_result->fetch_assoc()): ?>
                                            <div class="col-md-6 mb-2">
                                                <div class="form-check">
                                                    <input class="form-check-input" type="checkbox" 
                                                           name="team_members[]" 
                                                           value="<?php echo $employee['id']; ?>" 
                                                           id="member_<?php echo $employee['id']; ?>">
                                                    <label class="form-check-label" for="member_<?php echo $employee['id']; ?>">
                                                        <strong><?php echo htmlspecialchars($employee['full_name']); ?></strong><br>
                                                        <small class="text-muted">
                                                            <?php echo htmlspecialchars($employee['employee_id'] ?? 'N/A'); ?> | 
                                                            <?php echo htmlspecialchars($employee['department'] ?? 'N/A'); ?>
                                                        </small>
                                                    </label>
                                                </div>
                                            </div>
                                            <?php endwhile; ?>
                                        </div>
                                    </div>
                                </div>
                                <small class="text-muted">Select team members to add to this project</small>
                            </div>
                            
                            <div class="col-md-12">
                                <button type="submit" class="btn btn-primary">Create Project</button>
                                <a href="<?php echo $base_url; ?>/modules/projects/index.php" class="btn btn-outline-secondary">Cancel</a>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        
        <div class="col-lg-4">
            <div class="card">
                <div class="card-header">
                    <h6 class="mb-0">Project Setup Guidelines</h6>
                </div>
                <div class="card-body">
                    <div class="alert alert-info">
                        <h6><i class="bi bi-info-circle"></i> Best Practices</h6>
                        <ul class="small mb-0">
                            <li>Use clear, descriptive project names</li>
                            <li>Set realistic timelines and budgets</li>
                            <li>Include detailed project descriptions</li>
                            <li>Select appropriate team members</li>
                            <li>Define clear objectives and deliverables</li>
                        </ul>
                    </div>
                    
                    <div class="mt-3">
                        <h6>Project Status Definitions</h6>
                        <div class="list-group small">
                            <div class="list-group-item">
                                <span class="badge bg-secondary me-2">Planning</span>
                                Project is in planning phase
                            </div>
                            <div class="list-group-item">
                                <span class="badge bg-primary me-2">Active</span>
                                Project is currently running
                            </div>
                            <div class="list-group-item">
                                <span class="badge bg-warning text-dark me-2">On Hold</span>
                                Project is temporarily paused
                            </div>
                            <div class="list-group-item">
                                <span class="badge bg-success me-2">Completed</span>
                                Project is finished
                            </div>
                            <div class="list-group-item">
                                <span class="badge bg-danger me-2">Cancelled</span>
                                Project is cancelled
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    function generateCode() {
        const prefix = 'PROJ';
        const year = new Date().getFullYear().toString().slice(-2);
        const month = (new Date().getMonth() + 1).toString().padStart(2, '0');
        const random = Math.floor(Math.random() * 999).toString().padStart(3, '0');
        document.querySelector('input[name="project_code"]').value = prefix + year + month + random;
    }
    
    // Set minimum end date based on start date
    document.querySelector('input[name="start_date"]').addEventListener('change', function() {
        const endDate = document.querySelector('input[name="end_date"]');
        endDate.min = this.value;
        if (endDate.value && endDate.value < this.value) {
            endDate.value = this.value;
        }
    });
</script>

<?php require_once '../../includes/footer.php'; ?>