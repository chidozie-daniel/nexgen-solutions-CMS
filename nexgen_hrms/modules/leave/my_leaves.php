<?php
require_once '../../includes/auth.php';
require_once '../../includes/functions.php';

// Check login status first
Auth::requireLogin();

$page_title = 'My Leave Applications';
$conn = getDBConnection();
$user_id = $_SESSION['user_id'];

// Cancel leave request
if (isset($_GET['cancel']) && is_numeric($_GET['cancel'])) {
    $leave_id = $_GET['cancel'];
    
    // Check if user owns this leave and it's pending
    $check_sql = "SELECT id FROM leaves WHERE id = ? AND user_id = ? AND status = 'pending'";
    $check_stmt = $conn->prepare($check_sql);
    $check_stmt->bind_param("ii", $leave_id, $user_id);
    $check_stmt->execute();
    $check_result = $check_stmt->get_result();
    
    if ($check_result->num_rows == 1) {
        $update_sql = "UPDATE leaves SET status = 'cancelled' WHERE id = ?";
        $update_stmt = $conn->prepare($update_sql);
        $update_stmt->bind_param("i", $leave_id);
        if ($update_stmt->execute()) {
            setFlash('success', 'Leave request cancelled successfully.');
        }
    }
    header('Location: ' . Auth::getBasePath() . '/modules/leave/my_leaves.php');
    exit();
}

// Get user's leave applications
$sql = "SELECT * FROM leaves WHERE user_id = ? ORDER BY created_at DESC";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
require_once '../../includes/header.php';
?>
<div class="container-fluid">
    <div class="row mb-4">
        <div class="col">
            <div class="d-flex justify-content-between align-items-center">
                <h4 class="mb-0">My Leave Applications</h4>
                <a href="<?php echo $base_url; ?>/modules/leave/apply.php" class="btn btn-primary">
                    <i class="bi bi-plus-circle"></i> Apply for Leave
                </a>
            </div>
        </div>
    </div>
    
    <!-- Leave Summary -->
    <div class="row mb-4">
        <?php
        // Get leave summary
        $current_year = date('Y');
        $summary_sql = "SELECT 
            leave_type,
            COUNT(*) as total_applications,
            SUM(CASE WHEN status = 'approved' THEN duration_days ELSE 0 END) as approved_days,
            SUM(CASE WHEN status = 'pending' THEN duration_days ELSE 0 END) as pending_days
            FROM leaves 
            WHERE user_id = ? 
            AND YEAR(start_date) = ?
            GROUP BY leave_type";
        
        $summary_stmt = $conn->prepare($summary_sql);
        $summary_stmt->bind_param("ii", $user_id, $current_year);
        $summary_stmt->execute();
        $summary_result = $summary_stmt->get_result();
        ?>
        
        <div class="col-md-12">
            <div class="card">
                <div class="card-header">
                    <h6 class="mb-0">Annual Leave Summary (<?php echo $current_year; ?>)</h6>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-sm table-bordered">
                            <thead class="table-light">
                                <tr>
                                    <th>Leave Type</th>
                                    <th>Total Applications</th>
                                    <th>Approved Days</th>
                                    <th>Pending Days</th>
                                    <th>Available Balance</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                $types = ['annual', 'sick', 'casual'];
                                foreach ($types as $type):
                                    $balance = getLeaveBalance($user_id, $type);
                                    
                                    // Find this type in summary
                                    $type_data = null;
                                    if ($summary_result->num_rows > 0) {
                                        $summary_result->data_seek(0);
                                        while ($row = $summary_result->fetch_assoc()) {
                                            if ($row['leave_type'] == $type) {
                                                $type_data = $row;
                                                break;
                                            }
                                        }
                                    }
                                ?>
                                <tr>
                                    <td><?php echo ucfirst($type); ?> Leave</td>
                                    <td><?php echo $type_data['total_applications'] ?? 0; ?></td>
                                    <td><?php echo $type_data['approved_days'] ?? 0; ?></td>
                                    <td><?php echo $type_data['pending_days'] ?? 0; ?></td>
                                    <td>
                                        <span class="badge bg-<?php echo $balance > 0 ? 'success' : 'danger'; ?>">
                                            <?php echo $balance; ?> days
                                        </span>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Leave Applications Table -->
    <div class="row">
        <div class="col">
            <div class="card">
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-hover" id="leavesTable">
                            <thead>
                                <tr>
                                    <th>Leave ID</th>
                                    <th>Type</th>
                                    <th>Period</th>
                                    <th>Duration</th>
                                    <th>Reason</th>
                                    <th>Status</th>
                                    <th>Applied On</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if ($result->num_rows > 0): ?>
                                    <?php while ($row = $result->fetch_assoc()): ?>
                                    <tr>
                                        <td>#LV-<?php echo str_pad($row['id'], 5, '0', STR_PAD_LEFT); ?></td>
                                        <td><?php echo ucfirst($row['leave_type']); ?></td>
                                        <td>
                                            <?php echo formatDate($row['start_date']); ?><br>
                                            <small>to <?php echo formatDate($row['end_date']); ?></small>
                                        </td>
                                        <td><?php echo $row['duration_days']; ?> days</td>
                                        <td>
                                            <small class="text-muted" title="<?php echo htmlspecialchars($row['reason']); ?>">
                                                <?php echo substr($row['reason'], 0, 50); ?>
                                                <?php echo strlen($row['reason']) > 50 ? '...' : ''; ?>
                                            </small>
                                        </td>
                                        <td><?php echo getStatusBadge($row['status']); ?></td>
                                        <td><?php echo formatDate($row['created_at'], 'M d, Y'); ?></td>
                                        <td>
                                            <button type="button" class="btn btn-sm btn-outline-primary" 
                                                    data-bs-toggle="modal" data-bs-target="#viewModal<?php echo $row['id']; ?>">
                                                <i class="bi bi-eye"></i>
                                            </button>
                                            
                                            <?php if ($row['status'] == 'pending'): ?>
                                            <a href="<?php echo $base_url; ?>/modules/leave/my_leaves.php?cancel=<?php echo $row['id']; ?>" 
                                               class="btn btn-sm btn-outline-danger"
                                               onclick="return confirm('Are you sure you want to cancel this leave request?')">
                                                <i class="bi bi-x-circle"></i>
                                            </a>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                    
                                    <!-- View Modal -->
                                    <div class="modal fade" id="viewModal<?php echo $row['id']; ?>" tabindex="-1">
                                        <div class="modal-dialog">
                                            <div class="modal-content">
                                                <div class="modal-header">
                                                    <h5 class="modal-title">Leave Details #LV-<?php echo str_pad($row['id'], 5, '0', STR_PAD_LEFT); ?></h5>
                                                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                                </div>
                                                <div class="modal-body">
                                                    <div class="row mb-3">
                                                        <div class="col-md-6">
                                                            <strong>Leave Type:</strong><br>
                                                            <?php echo ucfirst($row['leave_type']); ?>
                                                        </div>
                                                        <div class="col-md-6">
                                                            <strong>Status:</strong><br>
                                                            <?php echo getStatusBadge($row['status']); ?>
                                                        </div>
                                                    </div>
                                                    
                                                    <div class="row mb-3">
                                                        <div class="col-md-6">
                                                            <strong>Start Date:</strong><br>
                                                            <?php echo formatDate($row['start_date'], 'F d, Y'); ?>
                                                        </div>
                                                        <div class="col-md-6">
                                                            <strong>End Date:</strong><br>
                                                            <?php echo formatDate($row['end_date'], 'F d, Y'); ?>
                                                        </div>
                                                    </div>
                                                    
                                                    <div class="row mb-3">
                                                        <div class="col-md-6">
                                                            <strong>Duration:</strong><br>
                                                            <?php echo $row['duration_days']; ?> days
                                                        </div>
                                                        <div class="col-md-6">
                                                            <strong>Applied On:</strong><br>
                                                            <?php echo formatDate($row['created_at'], 'F d, Y h:i A'); ?>
                                                        </div>
                                                    </div>
                                                    
                                                    <div class="mb-3">
                                                        <strong>Reason:</strong><br>
                                                        <p class="border rounded p-2 bg-light"><?php echo nl2br(htmlspecialchars($row['reason'])); ?></p>
                                                    </div>
                                                    
                                                    <?php if ($row['hr_remarks']): ?>
                                                    <div class="mb-3">
                                                        <strong>HR Remarks:</strong><br>
                                                        <p class="border rounded p-2 bg-light"><?php echo nl2br(htmlspecialchars($row['hr_remarks'])); ?></p>
                                                    </div>
                                                    <?php endif; ?>
                                                </div>
                                                <div class="modal-footer">
                                                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <?php endwhile; ?>
                                <?php else: ?>
                                <tr>
                                    <td colspan="8" class="text-center py-4">
                                        <i class="bi bi-calendar-x h1 text-muted d-block mb-3"></i>
                                        <p class="text-muted">No leave applications found.</p>
                                        <a href="<?php echo $base_url; ?>/modules/leave/apply.php" class="btn btn-primary">Apply for Leave</a>
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

<script>
    // Initialize DataTable if needed
    document.addEventListener('DOMContentLoaded', function() {
        // You can add DataTable initialization here if needed
        // const table = new DataTable('#leavesTable');
    });
</script>

<?php require_once '../../includes/footer.php'; ?>