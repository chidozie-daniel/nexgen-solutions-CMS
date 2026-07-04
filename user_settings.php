<?php
// Define entry point constant
define('APP_ENTRY_POINT', true);

require_once 'includes/auth.php';
require_once 'includes/functions.php';

// Check login status first
Auth::requireLogin();

$user = Auth::getCurrentUser();
$conn = getDBConnection();
$user_id = $user['id'];

// Handle password change
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['change_password'])) {
    // Verify CSRF token
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        setFlash('danger', 'Invalid security token. Please try again.');
        header('Location: user_settings.php');
        exit();
    }
    
    $current_password = $_POST['current_password'] ?? '';
    $new_password = $_POST['new_password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    
    // Validation
    if (empty($current_password) || empty($new_password) || empty($confirm_password)) {
        setFlash('danger', 'All fields are required.');
    } elseif ($new_password !== $confirm_password) {
        setFlash('danger', 'New passwords do not match.');
    } elseif (strlen($new_password) < 6 || strlen($new_password) > 128) {
        setFlash('danger', 'New password must be between 6 and 128 characters long.');
    } elseif ($new_password === $current_password) {
        setFlash('danger', 'New password must be different from the current password.');
    } else {
        // Verify current password
        $sql = "SELECT password FROM users WHERE id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $pwd_row = $result->fetch_assoc();
        
        if (password_verify($current_password, $pwd_row['password'])) {
            // Update to new password
            $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
            $update_sql = "UPDATE users SET password = ?, updated_at = NOW() WHERE id = ?";
            $update_stmt = $conn->prepare($update_sql);
            $update_stmt->bind_param("si", $hashed_password, $user_id);
            
            if ($update_stmt->execute()) {
                setFlash('success', 'Password updated successfully!');
                header('Location: user_settings.php');
                exit();
            } else {
                setFlash('danger', 'Error updating password: ' . $conn->error);
            }
        } else {
            setFlash('danger', 'Current password is incorrect.');
        }
    }
}

$page_title = 'User Settings';
require_once 'includes/header.php';
?>

<div class="container-fluid">
    <div class="module-hero">
        <div class="container">
            <div>
                <h1 class="mb-2">User Settings</h1>
                <p class="mb-0">Manage your account security and preferences</p>
            </div>
        </div>
    </div>
    
    <div class="row justify-content-center">
        <div class="col-md-6">
            <!-- Account Security Card -->
            <div class="card shadow-sm mb-4">
                <div class="card-header bg-white py-3">
                    <h5 class="mb-0 text-primary"><i class="bi bi-shield-lock me-2"></i>Change Password</h5>
                </div>
                <div class="card-body p-4">
                    <form method="POST" action="">
                        <?php echo csrfField(); ?>
                        <div class="mb-3">
                            <label class="form-label fw-bold">Current Password</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="bi bi-key"></i></span>
                                <input type="password" name="current_password" class="form-control" data-required="true">
                            </div>
                        </div>
                        
                        <hr class="my-4">
                        
                        <div class="mb-3">
                            <label class="form-label fw-bold">New Password</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="bi bi-lock"></i></span>
                                <input type="password" name="new_password" class="form-control" data-required="true">
                            </div>
                            <small class="text-muted">Minimum 6 characters</small>
                        </div>
                        
                        <div class="mb-4">
                            <label class="form-label fw-bold">Confirm New Password</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="bi bi-lock-check"></i></span>
                                <input type="password" name="confirm_password" class="form-control" data-required="true">
                            </div>
                        </div>
                        
                        <div class="d-grid">
                            <button type="submit" name="change_password" class="btn btn-primary">
                                <i class="bi bi-shield-check me-2"></i> Update Password
                            </button>
                        </div>
                    </form>
                </div>
            </div>
            
        </div>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>
