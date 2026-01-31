<?php
require_once '../../includes/auth.php';
require_once '../../includes/functions.php';

// Check login status first
Auth::requireLogin();

// Only HR and Admin can view inquiry details
if (!Auth::hasRole('hr') && !Auth::hasRole('admin')) {
    setFlash('danger', 'You do not have permission to view inquiries.');
    header('Location: ' . Auth::getBasePath() . '/dashboard.php');
    exit();
}

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    setFlash('danger', 'Invalid inquiry ID.');
    header('Location: ' . Auth::getBasePath() . '/modules/inquiries/list.php');
    exit();
}

$inquiry_id = $_GET['id'];
$conn = getDBConnection();

// Get inquiry details
$sql = "SELECT i.*, u.full_name as assigned_to_name 
        FROM inquiries i 
        LEFT JOIN users u ON i.assigned_to = u.id 
        WHERE i.id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $inquiry_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows == 0) {
    setFlash('danger', 'Inquiry not found.');
    header('Location: ' . Auth::getBasePath() . '/modules/inquiries/list.php');
    exit();
}

$inquiry = $result->fetch_assoc();

// Update inquiry status if requested
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $status = $_POST['status'];
    $notes = $_POST['notes'] ?? '';
    $assigned_to = $_POST['assigned_to'] ?? null;
    
    $update_sql = "UPDATE inquiries SET status = ?, notes = ?, assigned_to = ? WHERE id = ?";
    $update_stmt = $conn->prepare($update_sql);
    $update_stmt->bind_param("ssii", $status, $notes, $assigned_to, $inquiry_id);
    
    if ($update_stmt->execute()) {
        setFlash('success', 'Inquiry updated successfully.');
        header('Location: ' . Auth::getBasePath() . '/modules/inquiries/view.php?id=' . $inquiry_id);
        exit();
    }
}

// Get all HR users for assignment
$hr_sql = "SELECT id, full_name, email FROM users WHERE role IN ('hr', 'admin') AND status = 'active' ORDER BY full_name";
$hr_result = $conn->query($hr_sql);

$page_title = 'Inquiry: ' . $inquiry['name'];
require_once '../../includes/header.php';
?>
<div class="container-fluid">
    <!-- Inquiry Header -->
    <div class="row mb-4">
        <div class="col">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h4 class="mb-1">Inquiry Details</h4>
                    <p class="text-muted mb-0">
                        From: <?php echo htmlspecialchars($inquiry['name']); ?> | 
                        Received: <?php echo formatDate($inquiry['created_at']); ?>
                    </p>
                </div>
                <div class="btn-group">
                    <a href="list.php" class="btn btn-outline-secondary">
                        <i class="bi bi-arrow-left"></i> Back to List
                    </a>
                    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#replyModal">
                        <i class="bi bi-reply"></i> Reply
                    </button>
                </div>
            </div>
        </div>
    </div>
    
    <div class="row">
        <!-- Inquiry Details -->
        <div class="col-md-8">
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="mb-0">Inquiry Information</h5>
                </div>
                <div class="card-body">
                    <div class="row mb-4">
                        <div class="col-md-6">
                            <table class="table table-sm">
                                <tr>
                                    <th width="40%">Inquiry ID:</th>
                                    <td>#INQ-<?php echo str_pad($inquiry['id'], 5, '0', STR_PAD_LEFT); ?></td>
                                </tr>
                                <tr>
                                    <th>Status:</th>
                                    <td>
                                        <?php 
                                        $status_badges = [
                                            'new' => 'primary',
                                            'contacted' => 'warning',
                                            'converted' => 'success',
                                            'closed' => 'secondary'
                                        ];
                                        $status_color = $status_badges[$inquiry['status']] ?? 'secondary';
                                        ?>
                                        <span class="badge bg-<?php echo $status_color; ?>">
                                            <?php echo ucfirst($inquiry['status']); ?>
                                        </span>
                                    </td>
                                </tr>
                                <tr>
                                    <th>Service:</th>
                                    <td>
                                        <span class="badge bg-info"><?php echo $inquiry['service']; ?></span>
                                    </td>
                                </tr>
                                <tr>
                                    <th>Assigned To:</th>
                                    <td>
                                        <?php if ($inquiry['assigned_to_name']): ?>
                                        <?php echo $inquiry['assigned_to_name']; ?>
                                        <?php else: ?>
                                        <span class="badge bg-danger">Unassigned</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            </table>
                        </div>
                        <div class="col-md-6">
                            <table class="table table-sm">
                                <tr>
                                    <th width="40%">Name:</th>
                                    <td><?php echo htmlspecialchars($inquiry['name']); ?></td>
                                </tr>
                                <tr>
                                    <th>Email:</th>
                                    <td>
                                        <a href="mailto:<?php echo htmlspecialchars($inquiry['email']); ?>">
                                            <?php echo htmlspecialchars($inquiry['email']); ?>
                                        </a>
                                    </td>
                                </tr>
                                <tr>
                                    <th>Phone:</th>
                                    <td><?php echo htmlspecialchars($inquiry['phone'] ?: 'Not provided'); ?></td>
                                </tr>
                                <tr>
                                    <th>Received:</th>
                                    <td><?php echo formatDate($inquiry['created_at'], 'F d, Y h:i A'); ?></td>
                                </tr>
                            </table>
                        </div>
                    </div>
                    
                    <div class="mb-4">
                        <h6>Message</h6>
                        <div class="card bg-light">
                            <div class="card-body">
                                <?php echo nl2br(htmlspecialchars($inquiry['message'])); ?>
                            </div>
                        </div>
                    </div>
                    
                    <?php if ($inquiry['notes']): ?>
                    <div class="mb-3">
                        <h6>Internal Notes</h6>
                        <div class="card">
                            <div class="card-body">
                                <?php echo nl2br(htmlspecialchars($inquiry['notes'])); ?>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>
                    
                    <!-- Quick Actions -->
                    <div class="d-flex flex-wrap gap-2">
                        <a href="mailto:<?php echo htmlspecialchars($inquiry['email']); ?>?subject=Re: Inquiry about <?php echo urlencode($inquiry['service'] ?? ''); ?>" 
                           class="btn btn-outline-primary">
                            <i class="bi bi-envelope"></i> Reply via Email
                        </a>
                        
                        <button class="btn btn-outline-info" data-bs-toggle="modal" data-bs-target="#callModal">
                            <i class="bi bi-telephone"></i> Call Client
                        </button>
                        
                        <button class="btn btn-outline-success" data-bs-toggle="modal" data-bs-target="#convertModal">
                            <i class="bi bi-check-circle"></i> Mark as Converted
                        </button>
                        
                        <button class="btn btn-outline-danger" data-bs-toggle="modal" data-bs-target="#closeModal">
                            <i class="bi bi-x-circle"></i> Close Inquiry
                        </button>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Update Form -->
        <div class="col-md-4">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">Update Inquiry</h5>
                </div>
                <div class="card-body">
                    <form method="POST" action="">
                        <div class="mb-3">
                            <label class="form-label">Status</label>
                            <select class="form-select" name="status" required>
                                <option value="new" <?php echo $inquiry['status'] == 'new' ? 'selected' : ''; ?>>New</option>
                                <option value="contacted" <?php echo $inquiry['status'] == 'contacted' ? 'selected' : ''; ?>>Contacted</option>
                                <option value="converted" <?php echo $inquiry['status'] == 'converted' ? 'selected' : ''; ?>>Converted</option>
                                <option value="closed" <?php echo $inquiry['status'] == 'closed' ? 'selected' : ''; ?>>Closed</option>
                            </select>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Assign To</label>
                            <select class="form-select" name="assigned_to">
                                <option value="">-- Unassigned --</option>
                                <?php while ($hr = $hr_result->fetch_assoc()): ?>
                                <option value="<?php echo $hr['id']; ?>" 
                                    <?php echo $inquiry['assigned_to'] == $hr['id'] ? 'selected' : ''; ?>>
                                    <?php echo $hr['full_name']; ?>
                                </option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Internal Notes</label>
                            <textarea class="form-control" name="notes" rows="4" 
                                      placeholder="Add internal notes about this inquiry..."><?php echo htmlspecialchars($inquiry['notes']); ?></textarea>
                        </div>
                        
                        <div class="d-grid">
                            <button type="submit" class="btn btn-primary">
                                <i class="bi bi-save"></i> Save Changes
                            </button>
                        </div>
                    </form>
                </div>
            </div>
            
            <!-- Activity Log -->
            <div class="card mt-4">
                <div class="card-header">
                    <h6 class="mb-0">Activity Log</h6>
                </div>
                <div class="card-body">
                    <div class="timeline small">
                        <div class="timeline-item mb-3">
                            <div class="timeline-marker"></div>
                            <div class="timeline-content">
                                <div class="text-muted">Today, 10:30 AM</div>
                                <div>Status changed to <span class="badge bg-primary">New</span></div>
                            </div>
                        </div>
                        <div class="timeline-item mb-3">
                            <div class="timeline-marker"></div>
                            <div class="timeline-content">
                                <div class="text-muted">Yesterday, 14:15 PM</div>
                                <div>Inquiry received via website form</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Reply Modal -->
<div class="modal fade" id="replyModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form method="POST" action="send_reply.php">
                <input type="hidden" name="inquiry_id" value="<?php echo $inquiry_id; ?>">
                <input type="hidden" name="to_email" value="<?php echo htmlspecialchars($inquiry['email']); ?>">
                <div class="modal-header">
                    <h5 class="modal-title">Reply to Inquiry</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label class="form-label">To</label>
                            <input type="text" class="form-control" value="<?php echo htmlspecialchars($inquiry['name']); ?>" readonly>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Email</label>
                            <input type="email" class="form-control" value="<?php echo htmlspecialchars($inquiry['email']); ?>" readonly>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Subject</label>
                        <input type="text" class="form-control" name="subject" 
                               value="Re: Inquiry about <?php echo $inquiry['service']; ?>" required>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Message</label>
                        <textarea class="form-control" name="message" rows="8" required>
Dear <?php echo htmlspecialchars($inquiry['name']); ?>,

Thank you for your inquiry about our <?php echo $inquiry['service']; ?> services.

<?php if ($inquiry['status'] == 'new'): ?>
We have received your inquiry and one of our representatives will contact you within 24 hours to discuss your requirements.
<?php elseif ($inquiry['status'] == 'contacted'): ?>
Following up on our previous conversation regarding your inquiry about <?php echo $inquiry['service']; ?>.
<?php endif; ?>

Please feel free to contact us if you have any further questions.

Best regards,
NexGen Solutions Team
                        </textarea>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="send_copy" id="sendCopy" checked>
                                <label class="form-check-label" for="sendCopy">
                                    Send copy to myself
                                </label>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="mark_contacted" id="markContacted" checked>
                                <label class="form-check-label" for="markContacted">
                                    Mark inquiry as contacted
                                </label>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Send Reply</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Call Modal -->
<div class="modal fade" id="callModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Call Client</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="alert alert-info">
                    <i class="bi bi-info-circle"></i> 
                    Call details and notes will be logged in the inquiry history.
                </div>
                
                <div class="mb-3">
                    <label class="form-label">Client</label>
                    <input type="text" class="form-control" value="<?php echo htmlspecialchars($inquiry['name']); ?>" readonly>
                </div>
                
                <div class="mb-3">
                    <label class="form-label">Phone Number</label>
                    <div class="input-group">
                        <input type="tel" class="form-control" value="<?php echo $inquiry['phone']; ?>" readonly>
                        <a href="tel:<?php echo $inquiry['phone']; ?>" class="btn btn-success">
                            <i class="bi bi-telephone"></i> Call Now
                        </a>
                    </div>
                </div>
                
                <div class="mb-3">
                    <label class="form-label">Call Notes</label>
                    <textarea class="form-control" rows="4" placeholder="Enter call notes..."></textarea>
                </div>
                
                <div class="mb-3">
                    <label class="form-label">Next Follow-up Date</label>
                    <input type="date" class="form-control" value="<?php echo date('Y-m-d', strtotime('+2 days')); ?>">
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary">Save Call Log</button>
            </div>
        </div>
    </div>
</div>

<!-- Convert Modal -->
<div class="modal fade" id="convertModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" action="convert.php">
                <input type="hidden" name="inquiry_id" value="<?php echo $inquiry_id; ?>">
                <div class="modal-header">
                    <h5 class="modal-title">Convert Inquiry to Client</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="alert alert-success">
                        <i class="bi bi-check-circle"></i> 
                        This will mark the inquiry as converted and create a new client record.
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Client Type</label>
                        <select class="form-select" name="client_type" required>
                            <option value="">Select type...</option>
                            <option value="corporate">Corporate Client</option>
                            <option value="individual">Individual Client</option>
                            <option value="government">Government Organization</option>
                            <option value="non_profit">Non-Profit Organization</option>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Assigned Sales Rep</label>
                        <select class="form-select" name="sales_rep">
                            <option value="">-- None --</option>
                            <?php
                            $sales_sql = "SELECT id, full_name FROM users WHERE department = 'Sales' AND status = 'active'";
                            $sales_result = $conn->query($sales_sql);
                            while ($sales = $sales_result->fetch_assoc()):
                            ?>
                            <option value="<?php echo $sales['id']; ?>"><?php echo $sales['full_name']; ?></option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Estimated Value ($)</label>
                        <input type="number" step="0.01" class="form-control" name="estimated_value" 
                               placeholder="Enter estimated contract value">
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Notes</label>
                        <textarea class="form-control" name="notes" rows="3" 
                                  placeholder="Additional notes about this conversion..."></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-success">Convert to Client</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Close Modal -->
<div class="modal fade" id="closeModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" action="close.php">
                <input type="hidden" name="inquiry_id" value="<?php echo $inquiry_id; ?>">
                <div class="modal-header">
                    <h5 class="modal-title">Close Inquiry</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="alert alert-warning">
                        <i class="bi bi-exclamation-triangle"></i> 
                        Please provide a reason for closing this inquiry.
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Close Reason</label>
                        <select class="form-select" name="close_reason" required>
                            <option value="">Select reason...</option>
                            <option value="not_interested">Client not interested</option>
                            <option value="duplicate">Duplicate inquiry</option>
                            <option value="spam">Spam/Invalid inquiry</option>
                            <option value="no_response">No response from client</option>
                            <option value="out_of_scope">Out of service scope</option>
                            <option value="other">Other</option>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Additional Notes</label>
                        <textarea class="form-control" name="notes" rows="3" 
                                  placeholder="Additional details about closing this inquiry..."></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-danger">Close Inquiry</button>
                </div>
            </form>
        </div>
    </div>
</div>

<style>
    .timeline {
        position: relative;
        padding-left: 30px;
    }
    
    .timeline-item {
        position: relative;
        padding-bottom: 10px;
    }
    
    .timeline-marker {
        position: absolute;
        left: -30px;
        top: 5px;
        width: 10px;
        height: 10px;
        border-radius: 50%;
        background-color: #6c757d;
        border: 2px solid white;
    }
</style>

<?php require_once '../../includes/footer.php'; ?>