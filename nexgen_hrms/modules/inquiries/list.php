<?php
require_once '../../includes/auth.php';
require_once '../../includes/functions.php';

// Check login status first
Auth::requireLogin();

// Only HR and Admin can access inquiries
if (!Auth::hasRole('hr') && !Auth::hasRole('admin')) {
    setFlash('danger', 'You do not have permission to view inquiries.');
    header('Location: ' . Auth::getBasePath() . '/dashboard.php');
    exit();
}

$page_title = 'Inquiries Management';
$conn = getDBConnection();

// Update inquiry status (POST only)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_status') {
    $inquiry_id = (int)($_POST['inquiry_id'] ?? 0);
    $status = htmlspecialchars($_POST['status'] ?? '', ENT_QUOTES, 'UTF-8');
    $user_id = $_SESSION['user_id'];
    
    // Validate status values
    $valid_statuses = ['new', 'contacted', 'converted', 'closed'];
    if (!in_array($status, $valid_statuses)) {
        setFlash('danger', 'Invalid status value.');
    } else {
        $sql = "UPDATE inquiries SET status = ?, assigned_to = ? WHERE id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("sii", $status, $user_id, $inquiry_id);
        
        if ($stmt->execute()) {
            setFlash('success', 'Inquiry status updated successfully.');
        } else {
            setFlash('danger', 'Failed to update inquiry status.');
        }
    }
    header('Location: ' . Auth::getBasePath() . '/modules/inquiries/list.php');
    exit();
}

// Get inquiries with filters
$status_filter = htmlspecialchars($_GET['status'] ?? 'all', ENT_QUOTES, 'UTF-8');
$assigned_filter = htmlspecialchars($_GET['assigned'] ?? 'all', ENT_QUOTES, 'UTF-8');

// Build query with prepared statements
$sql = "SELECT i.*, u.full_name as assigned_to_name 
        FROM inquiries i 
        LEFT JOIN users u ON i.assigned_to = u.id";
        
$where = [];
$params = [];
$types = '';

if ($status_filter != 'all') {
    $where[] = "i.status = ?";
    $params[] = $status_filter;
    $types .= 's';
}

if ($assigned_filter != 'all') {
    if ($assigned_filter == 'me') {
        $where[] = "i.assigned_to = ?";
        $params[] = $_SESSION['user_id'];
        $types .= 'i';
    } elseif ($assigned_filter == 'unassigned') {
        $where[] = "i.assigned_to IS NULL";
    }
}

if (!empty($where)) {
    $sql .= " WHERE " . implode(' AND ', $where);
}

$sql .= " ORDER BY i.created_at DESC";

if (!empty($params)) {
    $stmt = $conn->prepare($sql);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $result = $stmt->get_result();
} else {
    $result = $conn->query($sql);
}

// Get stats with prepared statement
$stats_sql = "SELECT 
    COUNT(*) as total,
    SUM(CASE WHEN status = 'new' THEN 1 ELSE 0 END) as new,
    SUM(CASE WHEN status = 'contacted' THEN 1 ELSE 0 END) as contacted,
    SUM(CASE WHEN status = 'converted' THEN 1 ELSE 0 END) as converted,
    SUM(CASE WHEN assigned_to IS NULL THEN 1 ELSE 0 END) as unassigned
    FROM inquiries";
$stats_result = $conn->query($stats_sql);
$stats = $stats_result->fetch_assoc();

$page_title = 'Inquiries Management';
require_once '../../includes/header.php';
?>
<div class="container-fluid">
    <div class="row mb-4">
        <div class="col">
            <div class="d-flex justify-content-between align-items-center">
                <h4 class="mb-0">Website Inquiries</h4>
                <div class="d-flex gap-2">
                    <a href="manage.php" class="btn btn-primary">
                        <i class="bi bi-plus-circle"></i> Add New Inquiry
                    </a>
                </div>
            </div>
            <p class="text-muted mb-0">Manage inquiries from the public website</p>
        </div>
    </div>
    
    <!-- Stats Cards -->
    <div class="row mb-4">
        <div class="col-md-2 col-6 mb-3">
            <div class="card">
                <div class="card-body text-center py-3">
                    <h3 class="mb-1"><?php echo $stats['total']; ?></h3>
                    <p class="text-muted mb-0 small">Total Inquiries</p>
                </div>
            </div>
        </div>
        
        <div class="col-md-2 col-6 mb-3">
            <div class="card border-primary">
                <div class="card-body text-center py-3">
                    <h3 class="mb-1 text-primary"><?php echo $stats['new']; ?></h3>
                    <p class="text-muted mb-0 small">New</p>
                </div>
            </div>
        </div>
        
        <div class="col-md-2 col-6 mb-3">
            <div class="card border-warning">
                <div class="card-body text-center py-3">
                    <h3 class="mb-1 text-warning"><?php echo $stats['contacted']; ?></h3>
                    <p class="text-muted mb-0 small">Contacted</p>
                </div>
            </div>
        </div>
        
        <div class="col-md-2 col-6 mb-3">
            <div class="card border-success">
                <div class="card-body text-center py-3">
                    <h3 class="mb-1 text-success"><?php echo $stats['converted']; ?></h3>
                    <p class="text-muted mb-0 small">Converted</p>
                </div>
            </div>
        </div>
        
        <div class="col-md-2 col-6 mb-3">
            <div class="card border-danger">
                <div class="card-body text-center py-3">
                    <h3 class="mb-1 text-danger"><?php echo $stats['unassigned']; ?></h3>
                    <p class="text-muted mb-0 small">Unassigned</p>
                </div>
            </div>
        </div>
        
        <div class="col-md-2 col-6 mb-3">
            <div class="card bg-light">
                <div class="card-body text-center py-3">
                    <h3 class="mb-1">
                        <?php 
                        $conversion_rate = $stats['total'] > 0 ? round(($stats['converted'] / $stats['total']) * 100, 1) : 0;
                        echo $conversion_rate;
                        ?>%
                    </h3>
                    <p class="text-muted mb-0 small">Conversion Rate</p>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Filters -->
    <div class="row mb-4">
        <div class="col">
            <div class="card">
                <div class="card-body">
                    <form method="GET" class="row g-3">
                        <div class="col-md-3">
                            <label class="form-label">Status</label>
                            <select name="status" class="form-select" onchange="this.form.submit()">
                                <option value="all" <?php echo $status_filter == 'all' ? 'selected' : ''; ?>>All Status</option>
                                <option value="new" <?php echo $status_filter == 'new' ? 'selected' : ''; ?>>New</option>
                                <option value="contacted" <?php echo $status_filter == 'contacted' ? 'selected' : ''; ?>>Contacted</option>
                                <option value="converted" <?php echo $status_filter == 'converted' ? 'selected' : ''; ?>>Converted</option>
                                <option value="closed" <?php echo $status_filter == 'closed' ? 'selected' : ''; ?>>Closed</option>
                            </select>
                        </div>
                        
                        <div class="col-md-3">
                            <label class="form-label">Assigned To</label>
                            <select name="assigned" class="form-select" onchange="this.form.submit()">
                                <option value="all" <?php echo $assigned_filter == 'all' ? 'selected' : ''; ?>>All Assignments</option>
                                <option value="me" <?php echo $assigned_filter == 'me' ? 'selected' : ''; ?>>Assigned to Me</option>
                                <option value="unassigned" <?php echo $assigned_filter == 'unassigned' ? 'selected' : ''; ?>>Unassigned</option>
                            </select>
                        </div>
                        
                        <div class="col-md-3">
                            <label class="form-label">Date From</label>
                            <input type="date" class="form-control" name="date_from">
                        </div>
                        
                        <div class="col-md-3">
                            <label class="form-label">Date To</label>
                            <input type="date" class="form-control" name="date_to">
                        </div>
                        
                        <div class="col-md-12">
                            <button type="submit" class="btn btn-primary">Apply Filters</button>
                            <a href="list.php" class="btn btn-outline-secondary">Reset</a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Inquiries Table -->
    <div class="row">
        <div class="col">
            <div class="card">
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Name</th>
                                    <th>Contact</th>
                                    <th>Service</th>
                                    <th>Message</th>
                                    <th>Status</th>
                                    <th>Assigned To</th>
                                    <th>Received</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if ($result->num_rows > 0): ?>
                                    <?php while ($row = $result->fetch_assoc()): 
                                        $status_badges = [
                                            'new' => 'primary',
                                            'contacted' => 'warning',
                                            'converted' => 'success',
                                            'closed' => 'secondary'
                                        ];
                                        $status_color = $status_badges[$row['status']] ?? 'secondary';
                                    ?>
                                    <tr class="<?php echo $row['status'] == 'new' ? 'table-primary' : ''; ?>">
                                        <td>#INQ-<?php echo str_pad($row['id'], 5, '0', STR_PAD_LEFT); ?></td>
                                        <td>
                                            <strong><?php echo htmlspecialchars($row['name']); ?></strong>
                                        </td>
                                        <td>
                                            <div><?php echo htmlspecialchars($row['email']); ?></div>
                                            <?php if ($row['phone']): ?>
                                            <div class="small text-muted"><?php echo $row['phone']; ?></div>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <span class="badge bg-info"><?php echo $row['service']; ?></span>
                                        </td>
                                        <td>
                                            <small class="text-muted" title="<?php echo htmlspecialchars($row['message'] ?? ''); ?>">
                                                <?php echo htmlspecialchars(substr($row['message'] ?? '', 0, 50)); ?>
                                                <?php echo strlen($row['message'] ?? '') > 50 ? '...' : ''; ?>
                                            </small>
                                        </td>
                                        <td>
                                            <span class="badge bg-<?php echo $status_color; ?>">
                                                <?php echo htmlspecialchars(ucfirst($row['status'])); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <?php if ($row['assigned_to_name']): ?>
                                            <span class="small"><?php echo htmlspecialchars($row['assigned_to_name']); ?></span>
                                            <?php else: ?>
                                            <span class="badge bg-danger">Unassigned</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <small><?php echo formatDate($row['created_at'], 'M d'); ?></small><br>
                                            <small class="text-muted"><?php echo date('h:i A', strtotime($row['created_at'])); ?></small>
                                        </td>
                                        <td>
                                            <div class="btn-group">
                                                <a href="view.php?id=<?php echo $row['id']; ?>" 
                                                   class="btn btn-sm btn-outline-primary" title="View">
                                                    <i class="bi bi-eye"></i>
                                                </a>
                                                
                                                <button type="button" class="btn btn-sm btn-outline-success dropdown-toggle" 
                                                        data-bs-toggle="dropdown" title="Update Status">
                                                    <i class="bi bi-check-circle"></i>
                                                </button>
                                                <ul class="dropdown-menu">
                                                    <li>
                                                        <form method="POST" class="m-0" style="display:inline;">
                                                            <input type="hidden" name="action" value="update_status">
                                                            <input type="hidden" name="inquiry_id" value="<?php echo $row['id']; ?>">
                                                            <input type="hidden" name="status" value="new">
                                                            <button type="submit" class="dropdown-item">Mark as New</button>
                                                        </form>
                                                    </li>
                                                    <li>
                                                        <form method="POST" class="m-0" style="display:inline;">
                                                            <input type="hidden" name="action" value="update_status">
                                                            <input type="hidden" name="inquiry_id" value="<?php echo $row['id']; ?>">
                                                            <input type="hidden" name="status" value="contacted">
                                                            <button type="submit" class="dropdown-item">Mark as Contacted</button>
                                                        </form>
                                                    </li>
                                                    <li>
                                                        <form method="POST" class="m-0" style="display:inline;">
                                                            <input type="hidden" name="action" value="update_status">
                                                            <input type="hidden" name="inquiry_id" value="<?php echo $row['id']; ?>">
                                                            <input type="hidden" name="status" value="converted">
                                                            <button type="submit" class="dropdown-item">Mark as Converted</button>
                                                        </form>
                                                    </li>
                                                    <li>
                                                        <form method="POST" class="m-0" style="display:inline;">
                                                            <input type="hidden" name="action" value="update_status">
                                                            <input type="hidden" name="inquiry_id" value="<?php echo $row['id']; ?>">
                                                            <input type="hidden" name="status" value="closed">
                                                            <button type="submit" class="dropdown-item">Mark as Closed</button>
                                                        </form>
                                                    </li>
                                                    <li><hr class="dropdown-divider"></li>
                                                </ul>
                                                
                                                <a href="mailto:<?php echo urlencode($row['email']); ?>?subject=Re: Inquiry about <?php echo urlencode($row['service']); ?>" 
                                                   class="btn btn-sm btn-outline-info" title="Reply via Email">
                                                    <i class="bi bi-envelope"></i>
                                                </a>
                                            </div>
                                        </td>
                                    </tr>
                                    <?php endwhile; ?>
                                <?php else: ?>
                                <tr>
                                    <td colspan="9" class="text-center py-4">
                                        <i class="bi bi-inbox h1 text-muted d-block mb-3"></i>
                                        <p class="text-muted">No inquiries found.</p>
                                        <a href="<?php echo $base_url; ?>/index.php" target="_blank" class="btn btn-primary">View Website</a>
                                    </td>
                                </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once '../../includes/footer.php'; ?>