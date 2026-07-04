<?php
// Define entry point constant
define('APP_ENTRY_POINT', true);

require_once '../includes/auth.php';
require_once '../includes/functions.php';

// Check login status
Auth::requireLogin();

$user = Auth::getCurrentUser();
$user_id = $user['id'];
$conn = getDBConnection();

// Handle mark as read
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['mark_read']) && is_numeric($_POST['mark_read'])) {
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        setFlash('danger', 'Invalid security token.');
        header('Location: notifications.php');
        exit();
    }
    markNotificationAsRead((int)$_POST['mark_read'], $user_id);
    header('Location: notifications.php');
    exit();
}

// Handle mark all as read
if (isset($_POST['mark_all_read'])) {
    markAllNotificationsAsRead($user_id);
    setFlash('success', 'All notifications marked as read.');
    header('Location: notifications.php');
    exit();
}

// Handle delete notification
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete']) && is_numeric($_POST['delete'])) {
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        setFlash('danger', 'Invalid security token.');
        header('Location: notifications.php');
        exit();
    }
    $notif_id = (int)$_POST['delete'];
    $del_sql = "DELETE FROM notifications WHERE id = ? AND user_id = ?";
    $del_stmt = $conn->prepare($del_sql);
    $del_stmt->bind_param("ii", $notif_id, $user_id);
    $del_stmt->execute();
    setFlash('success', 'Notification deleted.');
    header('Location: notifications.php');
    exit();
}

// Get filter
$filter = $_GET['filter'] ?? 'all'; // all, unread, read

// Get notifications
$notif_sql = "SELECT n.*, u.full_name as creator_name 
              FROM notifications n 
              LEFT JOIN users u ON n.created_by = u.id 
              WHERE n.user_id = ?";

if ($filter == 'unread') {
    $notif_sql .= " AND n.is_read = 0";
} elseif ($filter == 'read') {
    $notif_sql .= " AND n.is_read = 1";
}

$notif_sql .= " ORDER BY n.created_at DESC LIMIT 50";

$notif_stmt = $conn->prepare($notif_sql);
$notif_stmt->bind_param("i", $user_id);
$notif_stmt->execute();
$notifications = $notif_stmt->get_result();

// Get counts
$unread_count = getUnreadNotificationCount($user_id);

$page_title = 'Notifications';
$base_url = Auth::getBasePath();
require_once '../includes/header.php';
?>

<div class="container-fluid">
    <div class="module-hero">
        <div class="container">
            <div>
                <h1 class="mb-2">
                    <i class="bi bi-bell me-2"></i>Notifications
                </h1>
                <p class="mb-0">Stay updated with your latest activities and alerts</p>
            </div>
        </div>
    </div>

    <div class="row mb-4">
        <div class="col-md-8">
            <div class="btn-group" role="group">
                <a href="notifications.php?filter=all" class="btn btn-<?php echo $filter == 'all' ? 'primary' : 'outline-primary'; ?>">
                    All (<?php echo $notifications->num_rows; ?>)
                </a>
                <a href="notifications.php?filter=unread" class="btn btn-<?php echo $filter == 'unread' ? 'warning' : 'outline-warning'; ?>">
                    Unread (<?php echo $unread_count; ?>)
                </a>
                <a href="notifications.php?filter=read" class="btn btn-<?php echo $filter == 'read' ? 'secondary' : 'outline-secondary'; ?>">
                    Read
                </a>
            </div>
        </div>
        <div class="col-md-4 text-end">
            <?php if ($unread_count > 0): ?>
            <form method="POST" class="d-inline">
                <button type="submit" name="mark_all_read" class="btn btn-outline-success btn-sm">
                    <i class="bi bi-check-all me-1"></i> Mark All Read
                </button>
            </form>
            <?php endif; ?>
        </div>
    </div>

    <div class="row">
        <div class="col-lg-12">
            <?php if ($notifications->num_rows > 0): ?>
            <div class="list-group shadow-sm">
                <?php while ($notif = $notifications->fetch_assoc()): ?>
                <div class="list-group-item list-group-item-action p-0 <?php echo !$notif['is_read'] ? 'bg-light' : ''; ?>" 
                     style="border-left: 4px solid <?php 
                         echo $notif['type'] == 'success' ? '#198754' : 
                              ($notif['type'] == 'danger' ? '#dc3545' : 
                              ($notif['type'] == 'warning' ? '#ffc107' : '#0d6efd')); 
                     ?>;">
                    <div class="p-3">
                        <div class="d-flex w-100 justify-content-between align-items-start">
                            <div class="flex-grow-1">
                                <div class="d-flex align-items-center gap-2 mb-1">
                                    <h6 class="mb-0 fw-bold <?php echo !$notif['is_read'] ? 'text-dark' : 'text-secondary'; ?>">
                                        <?php if (!$notif['is_read']): ?>
                                            <i class="bi bi-circle-fill text-primary" style="font-size: 6px;"></i>
                                        <?php endif; ?>
                                        <?php echo htmlspecialchars($notif['title']); ?>
                                    </h6>
                                    <span class="badge bg-<?php 
                                        echo $notif['type'] == 'success' ? 'success' : 
                                             ($notif['type'] == 'danger' ? 'danger' : 
                                             ($notif['type'] == 'warning' ? 'warning text-dark' : 'info')); 
                                    ?> rounded-pill" style="font-size: 0.65rem;">
                                        <?php echo ucfirst($notif['category']); ?>
                                    </span>
                                    <?php if (!$notif['is_read']): ?>
                                    <span class="badge bg-primary rounded-pill" style="font-size: 0.65rem;">NEW</span>
                                    <?php endif; ?>
                                </div>
                                <p class="mb-2 text-secondary small"><?php echo nl2br(htmlspecialchars($notif['message'])); ?></p>
                                <div class="d-flex justify-content-between align-items-center">
                                    <small class="text-muted">
                                        <i class="bi bi-clock me-1"></i><?php echo formatDate($notif['created_at'], 'M d, Y h:i A'); ?>
                                        <?php if ($notif['creator_name']): ?>
                                            • From: <?php echo htmlspecialchars($notif['creator_name']); ?>
                                        <?php endif; ?>
                                    </small>
                                    <div class="btn-group btn-group-sm">
                                        <?php if ($notif['action_url']): ?>
                                        <?php 
                                            $final_action_url = $notif['action_url'];
                                            if (strpos($final_action_url, '/') === 0 && strpos($final_action_url, (string)$base_url) !== 0) {
                                                $final_action_url = $base_url . $final_action_url;
                                            }
                                        ?>
                                        <a href="<?php echo htmlspecialchars($final_action_url); ?>" 
                                           class="btn btn-outline-primary btn-sm" 
                                           onclick="markAsRead(<?php echo $notif['id']; ?>)">
                                            <i class="bi bi-box-arrow-up-right me-1"></i>View
                                        </a>
                                        <?php endif; ?>
                                        <?php if (!$notif['is_read']): ?>
                                        <form method="POST" class="d-inline">
                                            <?php echo csrfField(); ?>
                                            <input type="hidden" name="mark_read" value="<?php echo $notif['id']; ?>">
                                            <button type="submit" class="btn btn-outline-success btn-sm">
                                                <i class="bi bi-check"></i>
                                            </button>
                                        </form>
                                        <?php endif; ?>
                                        <form method="POST" class="d-inline" onsubmit="return confirm('Delete this notification?')">
                                            <?php echo csrfField(); ?>
                                            <input type="hidden" name="delete" value="<?php echo $notif['id']; ?>">
                                            <button type="submit" class="btn btn-outline-danger btn-sm">
                                                <i class="bi bi-trash"></i>
                                            </button>
                                        </form>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endwhile; ?>
            </div>
            <?php else: ?>
            <div class="card shadow-sm">
                <div class="card-body text-center py-5">
                    <i class="bi bi-bell-slash display-1 text-muted mb-3 d-block"></i>
                    <h5 class="text-muted">
                        <?php if ($filter == 'unread'): ?>
                            No unread notifications
                        <?php elseif ($filter == 'read'): ?>
                            No read notifications
                        <?php else: ?>
                            No notifications yet
                        <?php endif; ?>
                    </h5>
                    <p class="text-muted">
                        <?php if ($filter == 'unread'): ?>
                            You're all caught up! Check back later for updates.
                        <?php else: ?>
                            Notifications will appear here as you receive updates.
                        <?php endif; ?>
                    </p>
                    <?php if ($filter == 'unread'): ?>
                    <a href="notifications.php?filter=all" class="btn btn-outline-primary mt-2">
                        View All Notifications
                    </a>
                    <?php endif; ?>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
function markAsRead(id) {
    const formData = new FormData();
    formData.append('mark_read', id);
    formData.append('csrf_token', '<?php echo $_SESSION['csrf_token'] ?? ''; ?>');
    
    fetch('notifications.php', {
        method: 'POST',
        body: formData
    }).then(response => {
        // Navigation handles page leave
    });
}
</script>

<style>
.list-group-item {
    transition: all 0.2s ease;
}
.list-group-item:hover {
    background-color: #f8fafc !important;
}
</style>

<?php require_once '../includes/footer.php'; ?>
