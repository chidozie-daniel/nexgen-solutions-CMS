<?php
// Define entry point constant
define('APP_ENTRY_POINT', true);

require_once '../../includes/auth.php';
require_once '../../includes/functions.php';

// Check login status first
Auth::requireLogin();

$page_title = 'Projects';
$conn = getDBConnection();
$user_id = $_SESSION['user_id'];
$role = $_SESSION['role'];

// Base query parts
$columns = "p.*, 
            u.full_name as leader_name,
            COUNT(DISTINCT t.id) as total_tasks,
            SUM(CASE WHEN t.status = 'completed' THEN 1 ELSE 0 END) as completed_tasks,
            COUNT(DISTINCT pm.user_id) as team_members";

$joins = "LEFT JOIN users u ON p.project_leader = u.id
          LEFT JOIN tasks t ON p.id = t.project_id
          LEFT JOIN project_members pm ON p.id = pm.project_id";

$group_by = "GROUP BY p.id";
$order_by = "ORDER BY p.created_at DESC";

// Role-based filtering
if ($role == 'admin' || $role == 'hr') {
    $sql = "SELECT $columns 
            FROM projects p 
            $joins 
            $group_by 
            $order_by";
    $stmt = $conn->prepare($sql);
} elseif ($role == 'project_leader') {
    $sql = "SELECT $columns 
            FROM projects p 
            $joins 
            WHERE p.project_leader = ? 
            $group_by 
            $order_by";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $user_id);
} else {
    // Regular employee - get projects they're a member of
    // We need a subquery or a specific join logic for the membership check to avoid messing up the main joins
    // Easier to filter in WHERE clause using IN
    $sql = "SELECT $columns 
            FROM projects p 
            $joins 
            WHERE p.id IN (SELECT project_id FROM project_members WHERE user_id = ?) 
            $group_by 
            $order_by";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $user_id);
}

if ($stmt) {
    $stmt->execute();
    $result = $stmt->get_result();
} else {
    // Fallback if preparation failed
    $result = null;
    die("Database error: " . $conn->error);
}
$page_title = 'Projects';
require_once '../../includes/header.php';
?>
<div class="container-fluid">
    <div class="module-hero">
        <div class="container">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h1 class="mb-2">Projects</h1>
                    <p class="mb-0">Manage and track your projects effectively</p>
                </div>
                <?php if ($role == 'project_leader' || $role == 'hr' || $role == 'admin'): ?>
                <a href="<?php echo $base_url; ?>/modules/projects/create.php" class="btn btn-warning fw-bold text-dark">
                    <i class="bi bi-plus-circle"></i> Create Project
                </a>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <!-- Projects Grid -->
    <div class="container-fluid">
        <div class="row">
            <?php 
            if (!$result) {
                echo '<div class="col-12"><div class="alert alert-danger">No result from database query.</div></div>';
            }
            ?>
            <?php if ($result && $result->num_rows > 0): ?>
                <?php while ($row = $result->fetch_assoc()): 
                    // Calculate progress
                    $total_tasks = $row['total_tasks'];
                    $completed_tasks = $row['completed_tasks'] ?? 0; // Handle NULL sum
                    $progress = $total_tasks > 0 ? round(($completed_tasks / $total_tasks) * 100) : 0;
                    
                    // Status badge color
                    $status_colors = [
                        'planning' => 'secondary',
                        'active' => 'primary',
                        'on_hold' => 'warning',
                        'completed' => 'success',
                        'cancelled' => 'danger'
                    ];
                    $status_color = $status_colors[$row['status']] ?? 'secondary';
                    
                    // Days remaining
                    $days_left = '';
                    if ($row['end_date'] && $row['status'] == 'active') {
                        $today = new DateTime();
                        $end_date = new DateTime($row['end_date']);
                        $interval = $today->diff($end_date);
                        $days_left = $interval->days;
                        
                        if ($interval->invert) {
                            $days_left = -$days_left;
                        }
                    }
                ?>
                <div class="col-md-4 mb-4">
                    <div class="project-card">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <div style="overflow: hidden;">
                                <h6 class="mb-0 text-truncate" title="<?php echo htmlspecialchars($row['project_name']); ?>">
                                    <?php echo htmlspecialchars($row['project_name']); ?>
                                </h6>
                                <small class="text-muted"><?php echo $row['project_code']; ?></small>
                            </div>
                            <span class="badge bg-<?php echo $status_color; ?> flex-shrink-0 ms-2">
                                <?php echo ucfirst(str_replace('_', ' ', $row['status'])); ?>
                            </span>
                        </div>
                        
                        <div class="card-body">
                            <p class="card-text text-muted small">
                                <?php echo htmlspecialchars(substr($row['description'], 0, 100)); ?>
                                <?php if (strlen($row['description']) > 100): ?>...<?php endif; ?>
                            </p>
                            
                            <div class="mb-3">
                                <div class="d-flex justify-content-between small mb-1">
                                    <span>Progress</span>
                                    <span><?php echo $progress; ?>%</span>
                                </div>
                                <div class="progress" style="height: 6px;">
                                    <div class="progress-bar 
                                        <?php 
                                        if ($progress >= 80) echo 'bg-success';
                                        elseif ($progress >= 50) echo 'bg-primary';
                                        else echo 'bg-warning';
                                        ?>" 
                                        style="width: <?php echo $progress; ?>%">
                                    </div>
                                </div>
                            </div>
                            
                            <div class="row text-center small mb-3">
                                <div class="col-4">
                                    <div class="fw-bold"><?php echo $total_tasks; ?></div>
                                    <div class="text-muted">Tasks</div>
                                </div>
                                <div class="col-4">
                                    <div class="fw-bold"><?php echo $completed_tasks; ?></div>
                                    <div class="text-muted">Completed</div>
                                </div>
                                <div class="col-4">
                                    <div class="fw-bold"><?php echo $row['team_members']; ?></div>
                                    <div class="text-muted">Members</div>
                                </div>
                            </div>
                            
                            <div class="small text-muted">
                                <div class="d-flex justify-content-between mb-1">
                                    <span>Project Leader:</span>
                                    <span><?php echo htmlspecialchars($row['leader_name']); ?></span>
                                </div>
                                <div class="d-flex justify-content-between mb-1">
                                    <span>Start Date:</span>
                                    <span><?php echo formatDate($row['start_date']); ?></span>
                                </div>
                                <div class="d-flex justify-content-between">
                                    <span>End Date:</span>
                                    <span class="<?php echo (is_numeric($days_left) && $days_left < 0) ? 'text-danger fw-bold' : ''; ?>">
                                        <?php echo formatDate($row['end_date']); ?>
                                        <?php if (is_numeric($days_left)): ?>
                                            <?php if ($days_left > 0): ?>
                                            <small class="text-muted">(<?php echo $days_left; ?> days left)</small>
                                            <?php elseif ($days_left < 0): ?>
                                            <small class="text-danger">(<?php echo abs($days_left); ?> days overdue)</small>
                                            <?php endif; ?>
                                        <?php endif; ?>
                                    </span>
                                </div>
                            </div>
                        </div>
                        
                        <div class="card-footer bg-transparent">
                            <div class="d-flex justify-content-between align-items-center">
                                <a href="details.php?id=<?php echo $row['id']; ?>" class="btn btn-sm btn-outline-primary">
                                    <i class="bi bi-eye"></i> Details
                                </a>
                                
                                <div>
                                    <?php if ($role == 'admin' || $role == 'hr' || ($role == 'project_leader' && $row['project_leader'] == $user_id)): ?>
                                        <form action="delete.php" method="POST" style="display: inline-block;" onsubmit="return confirm('Are you sure you want to delete this project? This cannot be undone.');">
                                            <?php echo csrfField(); ?>
                                            <input type="hidden" name="project_id" value="<?php echo $row['id']; ?>">
                                            <button type="submit" class="btn btn-sm btn-outline-danger" title="Delete Project">
                                                <i class="bi bi-trash"></i>
                                            </button>
                                        </form>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endwhile; ?>
            <?php else: ?>
            <div class="col-12">
                <div class="card">
                    <div class="card-body text-center py-5">
                        <i class="bi bi-folder-x h1 text-muted d-block mb-3"></i>
                        <?php if ($role == 'project_leader' || $role == 'hr' || $role == 'admin'): ?>
                            <p class="text-muted">No projects found. Create your first project to get started.</p>
                            <a href="create.php" class="btn btn-primary mt-2">
                                <i class="bi bi-plus-circle me-1"></i> Create Project
                            </a>
                        <?php else: ?>
                            <p class="text-muted">You are not currently assigned to any projects.</p>
                            <p class="small text-muted mb-0">New projects will appear here once you are added to a team by your project leader or HR.</p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<style>
.project-card {
    background: white;
    border-radius: 12px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.08);
    transition: all 0.3s ease;
    height: 100%;
    border: 1px solid rgba(0,0,0,0.05);
}

.project-card:hover {
    transform: translateY(-4px);
    box-shadow: 0 8px 20px rgba(0,0,0,0.12);
}

.project-card .card-header {
    background: linear-gradient(135deg, rgba(13, 110, 253, 0.05) 0%, rgba(13, 110, 253, 0.02) 100%);
    border-bottom: 1px solid rgba(0,0,0,0.05);
    padding: 1rem 1.25rem;
    font-weight: 600;
}

.project-card .card-body {
    padding: 1.25rem;
}

.project-card .card-footer {
    border-top: 1px solid rgba(0,0,0,0.05);
    padding: 1rem 1.25rem;
    background: rgba(0,0,0,0.02);
}
</style>

<?php require_once '../../includes/footer.php'; ?>