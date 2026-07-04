<?php
// Define entry point constant
define('APP_ENTRY_POINT', true);

require_once '../../includes/auth.php';
require_once '../../includes/functions.php';

// Check login status and require project leader, HR, or admin role
Auth::requireRole(['project_leader', 'hr', 'admin']);

$page_title = 'Create New Project';
$conn = getDBConnection();
$user_id = $_SESSION['user_id'];
$field_errors = [];
$form_data = [
    'project_name' => '',
    'project_code' => generateProjectCode(),
    'description' => '',
    'client_name' => '',
    'start_date' => date('Y-m-d'),
    'end_date' => '',
    'budget' => '',
    'status' => 'planning',
    'team_members' => []
];

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Verify CSRF token
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        setFlash('danger', 'Invalid security token. Please try again.');
        header('Location: ' . Auth::getBasePath() . '/modules/projects/create.php');
        exit();
    }

    $project_name = sanitizeText($_POST['project_name'] ?? '', 120);
    $project_code = sanitizeText($_POST['project_code'] ?? '', 30);
    $description = sanitizeText($_POST['description'] ?? '', 2000, true);
    $client_name = sanitizeText($_POST['client_name'] ?? '', 120);
    $start_date = $_POST['start_date'] ?? '';
    $end_date = $_POST['end_date'] ?? '';
    $budget = trim((string)($_POST['budget'] ?? ''));
    $status = $_POST['status'] ?? '';
    $team_members = [];
    if (!empty($_POST['team_members']) && is_array($_POST['team_members'])) {
        foreach ($_POST['team_members'] as $member_id) {
            if (is_numeric($member_id) && (int)$member_id > 0) {
                $team_members[] = (int)$member_id;
            }
        }
    }

    $form_data = [
        'project_name' => $project_name,
        'project_code' => $project_code,
        'description' => $description,
        'client_name' => $client_name,
        'start_date' => $start_date,
        'end_date' => $end_date,
        'budget' => $budget,
        'status' => $status,
        'team_members' => array_map('strval', $team_members)
    ];
    
    // Validation
    if ($project_name === '') {
        $field_errors['project_name'] = 'Project name is required.';
    }
    if ($project_code === '' || !preg_match('/^[A-Za-z0-9_-]{3,30}$/', $project_code)) {
        $field_errors['project_code'] = 'Project code must be 3-30 characters (letters, numbers, dash, underscore).';
    }
    if ($description === '' || strlen($description) < 10) {
        $field_errors['description'] = 'Project description must be at least 10 characters.';
    }
    if (!isValidDate($start_date)) {
        $field_errors['start_date'] = 'Please provide a valid start date.';
    }
    if (!isValidDate($end_date)) {
        $field_errors['end_date'] = 'Please provide a valid end date.';
    }
    if (!isset($field_errors['start_date']) && !isset($field_errors['end_date']) && strtotime($end_date) < strtotime($start_date)) {
        $field_errors['end_date'] = 'End date cannot be before start date.';
    }
    $allowed_status = ['planning', 'active', 'on_hold'];
    if (!in_array($status, $allowed_status, true)) {
        $field_errors['status'] = 'Invalid status selected.';
    }
    if ($budget !== '' && !isNonNegativeNumber($budget)) {
        $field_errors['budget'] = 'Budget must be a non-negative number.';
    } else {
        $budget = ($budget === '') ? 0 : (float)$budget;
    }
    
    // Check if project code already exists
    if (empty($field_errors)) {
        $check_sql = "SELECT id FROM projects WHERE project_code = ?";
        $check_stmt = $conn->prepare($check_sql);
        $check_stmt->bind_param("s", $project_code);
        $check_stmt->execute();
        if ($check_stmt->get_result()->num_rows > 0) {
            $field_errors['project_code'] = "Project code '$project_code' already exists.";
        }
    }
    
    if (!empty($field_errors)) {
        setFlash('danger', 'Please correct the highlighted fields and try again.');
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
            
            // Add team members and send notifications
            $member_sql = "INSERT INTO project_members (project_id, user_id, joined_date, role) VALUES (?, ?, CURDATE(), ?)";
            $member_stmt = $conn->prepare($member_sql);
            
            // 1. Add Project Leader as 'lead'
            $leader_role = 'lead';
            $member_stmt->bind_param("iis", $project_id, $user_id, $leader_role);
            if (!$member_stmt->execute()) {
                throw new Exception("Error adding project leader to members: " . $member_stmt->error);
            }
            // Optional: Notify leader (usually not needed as they are the one creating it, but for audit/record)
            // notifyProjectAssignment($project_id, $user_id);

            // 2. Add other team members
            if (!empty($team_members)) {
                $member_role = 'member';
                foreach ($team_members as $member_id) {
                    // Skip if accidentally selected leader again (form might allow it)
                    if ($member_id == $user_id) continue;
                    
                    $member_stmt->bind_param("iis", $project_id, $member_id, $member_role);
                    if (!$member_stmt->execute()) {
                        throw new Exception("Error adding team member: " . $member_stmt->error);
                    }
                    
                    // Send Notification to member
                    notifyProjectAssignment($project_id, $member_id);
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

// Get appropriate team members based on creator's role
// HR/Admin should select Project Leaders and Employees as team members
// Project Leaders should select Employees as team members
$creator_role = $_SESSION['role'];

if ($creator_role === 'admin' || $creator_role === 'hr') {
    // For HR/Admin: Show Project Leaders and Employees (most common team members)
    $employees_sql = "SELECT id, full_name, employee_id, department, position, role
                      FROM users
                      WHERE status = 'active'
                      AND id != ?
                      AND role IN ('project_leader', 'employee')
                      ORDER BY role DESC, full_name";
} else {
    // For Project Leaders: Show Employees only
    $employees_sql = "SELECT id, full_name, employee_id, department, position, role
                      FROM users
                      WHERE status = 'active'
                      AND id != ?
                      AND role = 'employee'
                      ORDER BY full_name";
}

$employees_stmt = $conn->prepare($employees_sql);
$employees_stmt->bind_param("i", $user_id);
$employees_stmt->execute();
$employees_result = $employees_stmt->get_result();

require_once '../../includes/header.php';
?>
<div class="container-fluid">
    <div class="module-hero">
        <div class="container">
            <h1 class="mb-2">Create New Project</h1>
            <p class="mb-0">
                <?php 
                if ($creator_role === 'admin' || $creator_role === 'hr') {
                    echo 'Initialize a new project and select team members (Project Leaders & Employees)';
                } else {
                    echo 'Initialize a new project, set yourself as leader, and add team members';
                }
                ?>
            </p>
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
                        <?php echo csrfField(); ?>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Project Name *</label>
                                <input type="text" class="form-control <?php echo isset($field_errors['project_name']) ? 'is-invalid' : ''; ?>" name="project_name" data-required="true" data-maxlength="120"
                                       data-msg-required="Project name is required." value="<?php echo htmlspecialchars($form_data['project_name']); ?>" placeholder="Enter project name">
                                <div class="invalid-feedback <?php echo isset($field_errors['project_name']) ? 'd-block' : ''; ?>"><?php echo $field_errors['project_name'] ?? ''; ?></div>
                            </div>
                            
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Project Code *</label>
                                <div class="input-group">
                                    <input type="text" class="form-control <?php echo isset($field_errors['project_code']) ? 'is-invalid' : ''; ?>" name="project_code" data-required="true" data-pattern="^[A-Za-z0-9_-]{3,30}$"
                                           data-msg-pattern="Project code must be 3-30 characters (letters, numbers, dash, underscore)." value="<?php echo htmlspecialchars($form_data['project_code']); ?>">
                                    <button type="button" class="btn btn-outline-secondary" 
                                            onclick="generateCode()">
                                        <i class="bi bi-arrow-clockwise"></i>
                                    </button>
                                </div>
                                <div class="invalid-feedback <?php echo isset($field_errors['project_code']) ? 'd-block' : ''; ?>"><?php echo $field_errors['project_code'] ?? ''; ?></div>
                                <small class="text-muted">Unique identifier for the project</small>
                            </div>
                            
                            <div class="col-md-12 mb-3">
                                <label class="form-label">Description *</label>
                                <textarea class="form-control <?php echo isset($field_errors['description']) ? 'is-invalid' : ''; ?>" name="description" rows="4" data-required="true" data-minlength="10"
                                          data-msg-required="Project description is required." data-msg-minlength="Project description must be at least 10 characters."
                                          placeholder="Describe the project objectives, scope, and deliverables..."><?php echo htmlspecialchars($form_data['description']); ?></textarea>
                                <div class="invalid-feedback <?php echo isset($field_errors['description']) ? 'd-block' : ''; ?>"><?php echo $field_errors['description'] ?? ''; ?></div>
                            </div>
                            
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Client Name</label>
                                <input type="text" class="form-control" name="client_name" data-maxlength="120"
                                       value="<?php echo htmlspecialchars($form_data['client_name']); ?>" placeholder="Enter client name">
                            </div>
                            
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Budget ($)</label>
                                <input type="number" step="0.01" min="0" class="form-control <?php echo isset($field_errors['budget']) ? 'is-invalid' : ''; ?>" name="budget" data-min="0" data-type="number"
                                       data-msg-min="Budget must be a non-negative number." value="<?php echo htmlspecialchars((string)$form_data['budget']); ?>" placeholder="Enter project budget">
                                <div class="invalid-feedback <?php echo isset($field_errors['budget']) ? 'd-block' : ''; ?>"><?php echo $field_errors['budget'] ?? ''; ?></div>
                            </div>
                            
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Start Date *</label>
                                <input type="date" class="form-control <?php echo isset($field_errors['start_date']) ? 'is-invalid' : ''; ?>" name="start_date" data-required="true" data-msg-required="Start date is required."
                                       value="<?php echo htmlspecialchars($form_data['start_date']); ?>">
                                <div class="invalid-feedback <?php echo isset($field_errors['start_date']) ? 'd-block' : ''; ?>"><?php echo $field_errors['start_date'] ?? ''; ?></div>
                            </div>
                            
                            <div class="col-md-6 mb-3">
                                <label class="form-label">End Date *</label>
                                <input type="date" class="form-control <?php echo isset($field_errors['end_date']) ? 'is-invalid' : ''; ?>" name="end_date" data-required="true" data-msg-required="End date is required."
                                       min="<?php echo date('Y-m-d'); ?>" value="<?php echo htmlspecialchars($form_data['end_date']); ?>">
                                <div class="invalid-feedback <?php echo isset($field_errors['end_date']) ? 'd-block' : ''; ?>"><?php echo $field_errors['end_date'] ?? ''; ?></div>
                            </div>
                            
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Status *</label>
                                <select class="form-select <?php echo isset($field_errors['status']) ? 'is-invalid' : ''; ?>" name="status" data-required="true" data-msg-required="Status is required.">
                                    <option value="planning" <?php echo $form_data['status'] === 'planning' ? 'selected' : ''; ?>>Planning</option>
                                    <option value="active" <?php echo $form_data['status'] === 'active' ? 'selected' : ''; ?>>Active</option>
                                    <option value="on_hold" <?php echo $form_data['status'] === 'on_hold' ? 'selected' : ''; ?>>On Hold</option>
                                </select>
                                <div class="invalid-feedback <?php echo isset($field_errors['status']) ? 'd-block' : ''; ?>"><?php echo $field_errors['status'] ?? ''; ?></div>
                            </div>
                            
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Project Leader</label>
                                <?php if ($creator_role === 'admin' || $creator_role === 'hr'): ?>
                                <input type="text" class="form-control"
                                       value="<?php echo $_SESSION['full_name']; ?>"
                                       disabled>
                                <small class="text-muted">You are creating this project. Select Project Leaders as team members to lead it.</small>
                                <?php else: ?>
                                <input type="text" class="form-control"
                                       value="<?php echo $_SESSION['full_name']; ?>"
                                       disabled>
                                <small class="text-muted">You are the project leader</small>
                                <?php endif; ?>
                            </div>

                            <div class="col-md-12 mb-3">
                                <label class="form-label d-flex justify-content-between align-items-center">
                                    <span>Team Members</span>
                                    <div>
                                        <button type="button" class="btn btn-sm btn-outline-primary me-1" onclick="toggleTeamMembers(true)">
                                            <i class="bi bi-check-square"></i> Select All
                                        </button>
                                        <button type="button" class="btn btn-sm btn-outline-secondary" onclick="toggleTeamMembers(false)">
                                            <i class="bi bi-square"></i> None
                                        </button>
                                    </div>
                                </label>
                                <div class="card">
                                    <div class="card-body" style="max-height: 300px; overflow-y: auto;">
                                        <?php
                                        $current_role = '';
                                        // Only show role labels for roles that will appear
                                        $role_labels = [
                                            'project_leader' => 'Project Leaders',
                                            'employee' => 'Employees'
                                        ];
                                        ?>
                                        <?php while ($employee = $employees_result->fetch_assoc()): ?>
                                            <?php
                                            if ($employee['role'] !== $current_role) {
                                                if ($current_role !== '') {
                                                    echo '</div></div>';
                                                }
                                                $current_role = $employee['role'];
                                            ?>
                                            <div class="mb-3">
                                                <h6 class="small text-muted text-uppercase"><?php echo $role_labels[$current_role] ?? ucfirst($current_role); ?></h6>
                                                <div class="row">
                                            <?php } ?>
                                            <div class="col-md-6 mb-2">
                                                <div class="form-check">
                                                    <input class="form-check-input" type="checkbox"
                                                           name="team_members[]"
                                                           value="<?php echo $employee['id']; ?>"
                                                           <?php echo in_array((string)$employee['id'], $form_data['team_members'], true) ? 'checked' : ''; ?>
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
                                        <?php if ($current_role !== '') { echo '</div></div>'; } ?>
                                    </div>
                                </div>
                                <small class="text-muted">
                                    <i class="bi bi-info-circle"></i> 
                                    <?php if ($creator_role === 'admin' || $creator_role === 'hr'): ?>
                                        Select Project Leaders to lead this project and Employees to work on it.
                                    <?php else: ?>
                                        Select Employees to add to this project. They will receive an email notification upon being added.
                                    <?php endif; ?>
                                </small>
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

    // Toggle all team member checkboxes
    function toggleTeamMembers(selectAll) {
        const checkboxes = document.querySelectorAll('input[name="team_members[]"]');
        checkboxes.forEach(cb => {
            cb.checked = selectAll;
        });
    }

    // Set minimum end date based on start date
    document.querySelector('input[name="start_date"]').addEventListener('change', function() {
        const endDate = document.querySelector('input[name="end_date"]');
        endDate.min = this.value;
        if (endDate.value && endDate.value < this.value) {
            endDate.value = this.value;
        }
        validateProjectDates();
    });

    document.querySelector('input[name="end_date"]').addEventListener('change', validateProjectDates);

    function validateProjectDates() {
        const startDate = document.querySelector('input[name="start_date"]');
        const endDate = document.querySelector('input[name="end_date"]');
        const feedback = endDate.nextElementSibling;

        if (startDate.value && endDate.value && endDate.value < startDate.value) {
            endDate.classList.add('is-invalid');
            if (feedback && feedback.classList.contains('invalid-feedback')) {
                feedback.textContent = 'End date cannot be before start date.';
                feedback.style.display = 'block';
            }
            return false;
        }

        if (feedback && feedback.classList.contains('invalid-feedback') && feedback.textContent === 'End date cannot be before start date.') {
            feedback.textContent = '';
            feedback.style.display = 'none';
        }
        return true;
    }

    document.querySelector('form').addEventListener('submit', function(event) {
        if (!validateProjectDates()) {
            event.preventDefault();
            event.stopPropagation();
        }
    });
</script>

<?php require_once '../../includes/footer.php'; ?>
