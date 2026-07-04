<?php
define('APP_ENTRY_POINT', true);
require_once '../../includes/auth.php';
require_once '../../includes/functions.php';

Auth::requireLogin();

if (!Auth::hasRole('hr') && !Auth::hasRole('admin')) {
    setFlash('danger', 'You do not have permission to manage newsletter subscribers.');
    header('Location: ' . Auth::getBasePath() . '/dashboard.php');
    exit();
}

$conn = getDBConnection();
$page_title = 'Newsletter Subscribers';

// Handle subscriber deletion
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_subscriber'])) {
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        setFlash('danger', 'Invalid security token.');
        header('Location: list.php');
        exit();
    }

    $subscriber_id = (int)$_POST['subscriber_id'];
    $sql = "DELETE FROM newsletter_subscribers WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $subscriber_id);
    
    if ($stmt->execute()) {
        setFlash('success', 'Subscriber removed successfully.');
    } else {
        setFlash('danger', 'Failed to remove subscriber.');
    }
    header('Location: list.php');
    exit();
}

// Handle status update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_status'])) {
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        setFlash('danger', 'Invalid security token.');
        header('Location: list.php');
        exit();
    }

    $subscriber_id = (int)$_POST['subscriber_id'];
    $new_status = $_POST['status'];
    
    $sql = "UPDATE newsletter_subscribers SET status = ?, unsubscribed_at = CASE WHEN ? = 'unsubscribed' THEN NOW() ELSE unsubscribed_at END WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ssi", $new_status, $new_status, $subscriber_id);
    
    if ($stmt->execute()) {
        setFlash('success', 'Subscriber status updated.');
    } else {
        setFlash('danger', 'Failed to update status.');
    }
    header('Location: list.php');
    exit();
}

// Get filter parameters
$status_filter = $_GET['status'] ?? '';
$source_filter = $_GET['source'] ?? '';
$search = $_GET['search'] ?? '';

// Build query with filters
$where_clauses = [];
$params = [];
$types = '';

if ($status_filter) {
    $where_clauses[] = "ns.status = ?";
    $params[] = $status_filter;
    $types .= 's';
}

if ($source_filter) {
    $where_clauses[] = "ns.source = ?";
    $params[] = $source_filter;
    $types .= 's';
}

if ($search) {
    $where_clauses[] = "(ns.email LIKE ? OR ns.name LIKE ?)";
    $search_param = "%$search%";
    $params[] = $search_param;
    $params[] = $search_param;
    $types .= 'ss';
}

$where_sql = !empty($where_clauses) ? 'WHERE ' . implode(' AND ', $where_clauses) : '';

// Get subscribers
$sql = "SELECT ns.*, 
        (SELECT COUNT(*) FROM inquiries i WHERE i.email = ns.email) as inquiry_count
        FROM newsletter_subscribers ns 
        $where_sql 
        ORDER BY ns.subscribed_at DESC";

$stmt = $conn->prepare($sql);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$result = $stmt->get_result();
$subscribers = [];
while ($row = $result->fetch_assoc()) {
    $subscribers[] = $row;
}

// Get statistics
$stats_sql = "SELECT 
    COUNT(*) as total,
    SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) as active_count,
    SUM(CASE WHEN status = 'unsubscribed' THEN 1 ELSE 0 END) as unsubscribed_count,
    SUM(CASE WHEN status = 'bounced' THEN 1 ELSE 0 END) as bounced_count,
    SUM(CASE WHEN source = 'contact_form' THEN 1 ELSE 0 END) as from_contact_form,
    SUM(CASE WHEN source = 'manual_entry' THEN 1 ELSE 0 END) as manual_entry
    FROM newsletter_subscribers";
$stats_result = $conn->query($stats_sql);
$stats = $stats_result->fetch_assoc();

require_once '../../includes/header.php';
?>

<div class="container-fluid">
    <div class="row mb-4">
        <div class="col">
            <div class="d-flex justify-content-between align-items-center">
                <h4 class="mb-0"><i class="bi bi-envelope-paper me-2"></i>Newsletter Subscribers</h4>
                <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addSubscriberModal">
                    <i class="bi bi-plus-circle me-1"></i> Add Subscriber
                </button>
            </div>
            <p class="text-muted mb-0">Manage newsletter subscription list</p>
        </div>
    </div>

    <!-- Statistics Cards -->
    <div class="row mb-4">
        <div class="col-md-2">
            <div class="card bg-primary text-white">
                <div class="card-body">
                    <h5 class="card-title"><?php echo $stats['total']; ?></h5>
                    <p class="card-text mb-0">Total Subscribers</p>
                </div>
            </div>
        </div>
        <div class="col-md-2">
            <div class="card bg-success text-white">
                <div class="card-body">
                    <h5 class="card-title"><?php echo $stats['active_count']; ?></h5>
                    <p class="card-text mb-0">Active</p>
                </div>
            </div>
        </div>
        <div class="col-md-2">
            <div class="card bg-secondary text-white">
                <div class="card-body">
                    <h5 class="card-title"><?php echo $stats['unsubscribed_count']; ?></h5>
                    <p class="card-text mb-0">Unsubscribed</p>
                </div>
            </div>
        </div>
        <div class="col-md-2">
            <div class="card bg-danger text-white">
                <div class="card-body">
                    <h5 class="card-title"><?php echo $stats['bounced_count']; ?></h5>
                    <p class="card-text mb-0">Bounced</p>
                </div>
            </div>
        </div>
        <div class="col-md-2">
            <div class="card bg-info text-white">
                <div class="card-body">
                    <h5 class="card-title"><?php echo $stats['from_contact_form']; ?></h5>
                    <p class="card-text mb-0">From Contact Form</p>
                </div>
            </div>
        </div>
        <div class="col-md-2">
            <div class="card bg-warning text-dark">
                <div class="card-body">
                    <h5 class="card-title"><?php echo $stats['manual_entry']; ?></h5>
                    <p class="card-text mb-0">Manual Entries</p>
                </div>
            </div>
        </div>
    </div>

    <!-- Filters -->
    <div class="card mb-4">
        <div class="card-body">
            <form method="GET" action="" class="row g-3">
                <div class="col-md-3">
                    <input type="text" name="search" class="form-control" placeholder="Search email or name..." value="<?php echo htmlspecialchars($search); ?>">
                </div>
                <div class="col-md-2">
                    <select name="status" class="form-select">
                        <option value="">All Status</option>
                        <option value="active" <?php echo $status_filter === 'active' ? 'selected' : ''; ?>>Active</option>
                        <option value="unsubscribed" <?php echo $status_filter === 'unsubscribed' ? 'selected' : ''; ?>>Unsubscribed</option>
                        <option value="bounced" <?php echo $status_filter === 'bounced' ? 'selected' : ''; ?>>Bounced</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <select name="source" class="form-select">
                        <option value="">All Sources</option>
                        <option value="contact_form" <?php echo $source_filter === 'contact_form' ? 'selected' : ''; ?>>Contact Form</option>
                        <option value="manual_entry" <?php echo $source_filter === 'manual_entry' ? 'selected' : ''; ?>>Manual Entry</option>
                        <option value="import" <?php echo $source_filter === 'import' ? 'selected' : ''; ?>>Import</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <button type="submit" class="btn btn-primary me-2"><i class="bi bi-funnel me-1"></i> Filter</button>
                    <a href="list.php" class="btn btn-outline-secondary"><i class="bi bi-x-circle me-1"></i> Clear</a>
                </div>
                <div class="col-md-2 text-end">
                    <a href="export.php" class="btn btn-success"><i class="bi bi-download me-1"></i> Export</a>
                </div>
            </form>
        </div>
    </div>

    <!-- Subscribers Table -->
    <div class="card">
        <div class="card-body">
            <?php if (empty($subscribers)): ?>
                <div class="text-center py-5">
                    <i class="bi bi-inbox display-1 text-muted"></i>
                    <p class="text-muted mt-3">No subscribers found</p>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>Email</th>
                                <th>Name</th>
                                <th>Phone</th>
                                <th>Source</th>
                                <th>Status</th>
                                <th>Subscribed</th>
                                <th>Emails Sent</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($subscribers as $sub): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($sub['email']); ?></td>
                                <td><?php echo htmlspecialchars($sub['name'] ?? 'N/A'); ?></td>
                                <td><?php echo htmlspecialchars($sub['phone'] ?? 'N/A'); ?></td>
                                <td>
                                    <?php 
                                    $source_badges = [
                                        'contact_form' => '<span class="badge bg-info">Contact Form</span>',
                                        'manual_entry' => '<span class="badge bg-warning text-dark">Manual</span>',
                                        'import' => '<span class="badge bg-secondary">Import</span>',
                                        'other' => '<span class="badge bg-light text-dark">Other</span>'
                                    ];
                                    echo $source_badges[$sub['source']] ?? $sub['source']; 
                                    ?>
                                </td>
                                <td>
                                    <?php 
                                    $status_badges = [
                                        'active' => '<span class="badge bg-success">Active</span>',
                                        'unsubscribed' => '<span class="badge bg-secondary">Unsubscribed</span>',
                                        'bounced' => '<span class="badge bg-danger">Bounced</span>'
                                    ];
                                    echo $status_badges[$sub['status']] ?? $sub['status']; 
                                    ?>
                                </td>
                                <td><?php echo date('M d, Y', strtotime($sub['subscribed_at'])); ?></td>
                                <td><?php echo $sub['total_emails_sent']; ?></td>
                                <td>
                                    <div class="btn-group btn-group-sm">
                                        <button class="btn btn-outline-primary" onclick="updateStatus(<?php echo $sub['id']; ?>, '<?php echo $sub['status']; ?>')" title="Update Status">
                                            <i class="bi bi-pencil"></i>
                                        </button>
                                        <?php if ($sub['inquiry_count'] > 0): ?>
                                        <a href="../inquiries/list.php?email=<?php echo urlencode($sub['email']); ?>" class="btn btn-outline-info" title="View Inquiries">
                                            <i class="bi bi-chat-left-text"></i> <?php echo $sub['inquiry_count']; ?>
                                        </a>
                                        <?php endif; ?>
                                        <form method="POST" action="" class="d-inline" onsubmit="return confirm('Are you sure you want to remove this subscriber?');">
                                            <?php echo csrfField(); ?>
                                            <input type="hidden" name="subscriber_id" value="<?php echo $sub['id']; ?>">
                                            <button type="submit" name="delete_subscriber" class="btn btn-outline-danger" title="Remove">
                                                <i class="bi bi-trash"></i>
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Add Subscriber Modal -->
<div class="modal fade" id="addSubscriberModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" action="add.php">
                <?php echo csrfField(); ?>
                <div class="modal-header">
                    <h5 class="modal-title">Add Newsletter Subscriber</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label fw-bold">Email <span class="text-danger">*</span></label>
                        <input type="email" name="email" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold">Name</label>
                        <input type="text" name="name" class="form-control">
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold">Phone</label>
                        <input type="text" name="phone" class="form-control">
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold">Notes</label>
                        <textarea name="notes" class="form-control" rows="3"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="add_subscriber" class="btn btn-primary">
                        <i class="bi bi-plus-circle me-1"></i> Add Subscriber
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Status Update Modal -->
<div class="modal fade" id="statusModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" action="">
                <?php echo csrfField(); ?>
                <div class="modal-header">
                    <h5 class="modal-title">Update Subscriber Status</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="subscriber_id" id="status_subscriber_id">
                    <div class="mb-3">
                        <label class="form-label fw-bold">Status</label>
                        <select name="status" id="status_select" class="form-select" required>
                            <option value="active">Active</option>
                            <option value="unsubscribed">Unsubscribed</option>
                            <option value="bounced">Bounced</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="update_status" class="btn btn-primary">
                        <i class="bi bi-check-circle me-1"></i> Update Status
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function updateStatus(subscriberId, currentStatus) {
    document.getElementById('status_subscriber_id').value = subscriberId;
    document.getElementById('status_select').value = currentStatus;
    new bootstrap.Modal(document.getElementById('statusModal')).show();
}
</script>

<?php require_once '../../includes/footer.php'; ?>
