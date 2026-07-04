<?php
// Define entry point constant
define('APP_ENTRY_POINT', true);

require_once '../../includes/auth.php';
require_once '../../includes/functions.php';

// Check login status and require admin or HR role
Auth::requireRole(['admin', 'hr']);

$page_title = 'Manage Announcements';
$conn = getDBConnection();
$user_id = $_SESSION['user_id'];

// Handle create/update announcement
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_announcement'])) {
    // Verify CSRF token
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        setFlash('danger', 'Invalid security token. Please try again.');
        header('Location: announcements.php');
        exit();
    }
    
    $title = sanitizeText($_POST['title'] ?? '', 255);
    $content = sanitizeText($_POST['content'] ?? '', 2000, true);
    $priority = $_POST['priority'] ?? 'medium';
    $target_audience = $_POST['target_audience'] ?? 'all';
    $expires_at = !empty($_POST['expires_at']) ? $_POST['expires_at'] . ' 23:59:59' : null;
    $announcement_id = !empty($_POST['announcement_id']) ? (int)$_POST['announcement_id'] : null;
    
    $allowed_priorities = ['low', 'medium', 'high', 'urgent'];
    $allowed_audiences = ['all', 'employees', 'project_leaders', 'hr', 'admin'];
    
    if (empty($title) || empty($content)) {
        setFlash('danger', 'Title and content are required.');
    } elseif (!in_array($priority, $allowed_priorities, true)) {
        setFlash('danger', 'Invalid priority selected.');
    } elseif (!in_array($target_audience, $allowed_audiences, true)) {
        setFlash('danger', 'Invalid target audience selected.');
    } else {
        if ($announcement_id) {
            $sql = "UPDATE announcements SET title = ?, content = ?, priority = ?, target_audience = ?, expires_at = ?, updated_at = NOW() WHERE id = ?";
            $stmt = dbPrepare($conn, $sql, 'announcements update');
            if ($stmt) {
                $stmt->bind_param("sssssi", $title, $content, $priority, $target_audience, $expires_at, $announcement_id);
                if (dbExecute($stmt, 'announcements update')) {
                    setFlash('success', 'Announcement updated successfully!');
                } else {
                    setFlash('danger', 'Could not update the announcement. Please try again.');
                }
                $stmt->close();
            } else {
                setFlash('danger', 'Could not update the announcement. Please try again.');
            }
        } else {
            $sql = "INSERT INTO announcements (title, content, priority, target_audience, created_by, expires_at) VALUES (?, ?, ?, ?, ?, ?)";
            $stmt = dbPrepare($conn, $sql, 'announcements insert');
            if ($stmt) {
                $stmt->bind_param("ssssis", $title, $content, $priority, $target_audience, $user_id, $expires_at);
                if (dbExecute($stmt, 'announcements insert')) {
                    $announcement_id = $stmt->insert_id;
                    notifyAnnouncementCreated($announcement_id, $target_audience);
                    setFlash('success', 'Announcement created successfully!');
                } else {
                    setFlash('danger', 'Could not create the announcement. Please try again.');
                }
                $stmt->close();
            } else {
                setFlash('danger', 'Could not create the announcement. Please try again.');
            }
        }
        header('Location: announcements.php');
        exit();
    }
}

// Handle delete
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete']) && is_numeric($_POST['delete'])) {
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        setFlash('danger', 'Invalid security token.');
        header('Location: announcements.php');
        exit();
    }
    $delete_id = (int)$_POST['delete'];
    $sql = "DELETE FROM announcements WHERE id = ?";
    $stmt = dbPrepare($conn, $sql, 'announcements delete');
    if ($stmt) {
        $stmt->bind_param("i", $delete_id);
        if (dbExecute($stmt, 'announcements delete')) {
            setFlash('success', 'Announcement deleted successfully!');
        } else {
            setFlash('danger', 'Could not delete the announcement. Please try again.');
        }
        $stmt->close();
    } else {
        setFlash('danger', 'Could not delete the announcement. Please try again.');
    }
    header('Location: announcements.php');
    exit();
}

// Handle toggle active status
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['toggle']) && is_numeric($_POST['toggle'])) {
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        setFlash('danger', 'Invalid security token.');
        header('Location: announcements.php');
        exit();
    }
    $toggle_id = (int)$_POST['toggle'];
    $sql = "UPDATE announcements SET is_active = NOT is_active WHERE id = ?";
    $stmt = dbPrepare($conn, $sql, 'announcements toggle');
    if ($stmt) {
        $stmt->bind_param("i", $toggle_id);
        if (dbExecute($stmt, 'announcements toggle')) {
            setFlash('success', 'Announcement status updated!');
        } else {
            setFlash('danger', 'Could not update announcement status. Please try again.');
        }
        $stmt->close();
    } else {
        setFlash('danger', 'Could not update announcement status. Please try again.');
    }
    header('Location: announcements.php');
    exit();
}

// Get all announcements
$announcements_sql = "SELECT a.*, u.full_name as creator_name 
                      FROM announcements a 
                      LEFT JOIN users u ON a.created_by = u.id 
                      ORDER BY a.created_at DESC";
$announcements_result = dbQuery($conn, $announcements_sql, 'announcements list');
$announcement_rows = [];
if ($announcements_result instanceof mysqli_result) {
    while ($row = $announcements_result->fetch_assoc()) {
        $announcement_rows[] = $row;
    }
    $announcements_result->free();
}

// Get announcement for editing
$edit_announcement = null;
if (isset($_GET['edit']) && is_numeric($_GET['edit'])) {
    $edit_id = (int)$_GET['edit'];
    $edit_sql = "SELECT * FROM announcements WHERE id = ?";
    $edit_stmt = dbPrepare($conn, $edit_sql, 'announcements edit load');
    if ($edit_stmt) {
        $edit_stmt->bind_param("i", $edit_id);
        if (dbExecute($edit_stmt, 'announcements edit load')) {
            $edit_announcement = $edit_stmt->get_result()->fetch_assoc();
        }
        $edit_stmt->close();
    }
}

require_once '../../includes/header.php';
?>

<div class="container-fluid">
    <div class="module-hero">
        <div class="container">
            <div>
                <h1 class="mb-2">Manage Announcements</h1>
                <p class="mb-0">Create and manage company-wide announcements</p>
            </div>
        </div>
    </div>

    <div class="row">
        <!-- Create/Edit Form -->
        <div class="col-lg-4 mb-4">
            <div class="card shadow-sm">
                <div class="card-header bg-white py-3">
                    <h5 class="mb-0 text-primary">
                        <i class="bi bi-megaphone me-2"></i>
                        <?php echo $edit_announcement ? 'Edit Announcement' : 'New Announcement'; ?>
                    </h5>
                </div>
                <div class="card-body">
                    <form method="POST" action="">
                        <?php echo csrfField(); ?>
                        <input type="hidden" name="announcement_id" value="<?php echo $edit_announcement['id'] ?? ''; ?>">
                        
                        <div class="mb-3">
                            <label class="form-label fw-bold">Title</label>
                            <input type="text" name="title" class="form-control" 
                                   value="<?php echo htmlspecialchars($edit_announcement['title'] ?? ''); ?>" required>
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-bold">Content</label>
                            <textarea name="content" class="form-control" rows="5" required><?php echo htmlspecialchars($edit_announcement['content'] ?? ''); ?></textarea>
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-bold">Priority</label>
                            <select name="priority" class="form-select">
                                <option value="low" <?php echo ($edit_announcement['priority'] ?? '') == 'low' ? 'selected' : ''; ?>>Low</option>
                                <option value="medium" <?php echo ($edit_announcement['priority'] ?? '') == 'medium' ? 'selected' : ''; ?>>Medium</option>
                                <option value="high" <?php echo ($edit_announcement['priority'] ?? '') == 'high' ? 'selected' : ''; ?>>High</option>
                                <option value="urgent" <?php echo ($edit_announcement['priority'] ?? '') == 'urgent' ? 'selected' : ''; ?>>Urgent</option>
                            </select>
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-bold">Target Audience</label>
                            <select name="target_audience" class="form-select">
                                <option value="all" <?php echo ($edit_announcement['target_audience'] ?? '') == 'all' ? 'selected' : ''; ?>>All Users</option>
                                <option value="employees" <?php echo ($edit_announcement['target_audience'] ?? '') == 'employees' ? 'selected' : ''; ?>>Employees Only</option>
                                <option value="project_leaders" <?php echo ($edit_announcement['target_audience'] ?? '') == 'project_leaders' ? 'selected' : ''; ?>>Project Leaders</option>
                                <option value="hr" <?php echo ($edit_announcement['target_audience'] ?? '') == 'hr' ? 'selected' : ''; ?>>HR Only</option>
                                <option value="admin" <?php echo ($edit_announcement['target_audience'] ?? '') == 'admin' ? 'selected' : ''; ?>>Admin Only</option>
                            </select>
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-bold">Expires On (Optional)</label>
                            <input type="date" name="expires_at" class="form-control" 
                                   value="<?php echo $edit_announcement && $edit_announcement['expires_at'] ? substr($edit_announcement['expires_at'], 0, 10) : ''; ?>">
                            <small class="text-muted">Leave empty for no expiration</small>
                        </div>

                        <div class="d-grid gap-2">
                            <button type="submit" name="save_announcement" class="btn btn-primary">
                                <i class="bi bi-save me-2"></i><?php echo $edit_announcement ? 'Update' : 'Create'; ?> Announcement
                            </button>
                            <?php if ($edit_announcement): ?>
                            <a href="announcements.php" class="btn btn-outline-secondary">Cancel</a>
                            <?php endif; ?>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- Announcements List -->
        <div class="col-lg-8">
            <div class="card shadow-sm">
                <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                    <h5 class="mb-0 text-primary"><i class="bi bi-list-ul me-2"></i>All Announcements</h5>
                    <span class="badge bg-primary"><?php echo count($announcement_rows); ?> Total</span>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead class="bg-light">
                                <tr>
                                    <th width="50">Status</th>
                                    <th>Title</th>
                                    <th>Priority</th>
                                    <th>Audience</th>
                                    <th>Creator</th>
                                    <th>Expires</th>
                                    <th width="150">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (count($announcement_rows) > 0): ?>
                                    <?php foreach ($announcement_rows as $ann): ?>
                                    <tr>
                                        <td>
                                            <?php if ($ann['is_active']): ?>
                                                <span class="badge bg-success">Active</span>
                                            <?php else: ?>
                                                <span class="badge bg-secondary">Inactive</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <strong><?php echo htmlspecialchars($ann['title']); ?></strong>
                                            <br>
                                            <small class="text-muted"><?php echo substr(htmlspecialchars($ann['content']), 0, 60); ?>...</small>
                                        </td>
                                        <td>
                                            <?php
                                            $priority_colors = [
                                                'low' => 'secondary',
                                                'medium' => 'info',
                                                'high' => 'warning text-dark',
                                                'urgent' => 'danger'
                                            ];
                                            ?>
                                            <span class="badge bg-<?php echo $priority_colors[$ann['priority']] ?? 'secondary'; ?>">
                                                <?php echo ucfirst($ann['priority']); ?>
                                            </span>
                                        </td>
                                        <td><?php echo ucfirst(str_replace('_', ' ', $ann['target_audience'])); ?></td>
                                        <td><?php echo htmlspecialchars($ann['creator_name'] ?? 'System'); ?></td>
                                        <td>
                                            <?php if ($ann['expires_at']): ?>
                                                <small><?php echo formatDate($ann['expires_at'], 'M d, Y'); ?></small>
                                            <?php else: ?>
                                                <span class="text-muted">Never</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <div class="btn-group btn-group-sm">
                                                <form method="POST" class="d-inline">
                                                    <?php echo csrfField(); ?>
                                                    <input type="hidden" name="toggle" value="<?php echo $ann['id']; ?>">
                                                    <button type="submit" class="btn btn-outline-secondary" title="Toggle Status">
                                                        <i class="bi bi-<?php echo $ann['is_active'] ? 'pause' : 'play'; ?>"></i>
                                                    </button>
                                                </form>
                                                <a href="?edit=<?php echo $ann['id']; ?>" class="btn btn-outline-primary" title="Edit">
                                                    <i class="bi bi-pencil"></i>
                                                </a>
                                                <form method="POST" class="d-inline" onsubmit="return confirm('Delete this announcement?')">
                                                    <?php echo csrfField(); ?>
                                                    <input type="hidden" name="delete" value="<?php echo $ann['id']; ?>">
                                                    <button type="submit" class="btn btn-outline-danger" title="Delete">
                                                        <i class="bi bi-trash"></i>
                                                    </button>
                                                </form>
                                            </div>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="7" class="text-center py-5">
                                            <i class="bi bi-inbox display-4 text-muted mb-3 d-block"></i>
                                            <p class="text-muted">No announcements found. Create one to get started!</p>
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
