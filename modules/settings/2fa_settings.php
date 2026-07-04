<?php
require_once 'includes/header.php';
Auth::requireLogin();

$page_title = 'Two-Factor Authentication';
$conn = getDBConnection();
$user = Auth::getCurrentUser();
$user_id = $user['id'];

// Check if 2FA is enabled
$is_2fa_enabled = Auth::is2FAEnabled($user_id);
$message = '';
$message_type = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $action = $_POST['action'] ?? '';
    
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $message = 'Invalid security token.';
        $message_type = 'danger';
    } else {
        if ($action === 'enable_2fa') {
            $result = Auth::enable2FA($user_id);
            if ($result['success']) {
                $message = $result['message'] . ' You will now need to enter a verification code when logging in.';
                $message_type = 'success';
            } else {
                $message = $result['message'];
                $message_type = 'danger';
            }
        } elseif ($action === 'disable_2fa') {
            $result = Auth::disable2FA($user_id);
            if ($result['success']) {
                $message = $result['message'];
                $message_type = 'success';
            } else {
                $message = $result['message'];
                $message_type = 'danger';
            }
        } elseif ($action === 'send_test_code') {
            // Send a test OTP code
            $otp_manager = new OTPManager();
            $otp_result = $otp_manager->generateAndStoreOTP(
                $user['email'],
                OTPManager::PURPOSE_LOGIN_2FA,
                $user_id
            );
            
            if ($otp_result['success']) {
                $otp_manager->sendOTPEmail(
                    $user['email'],
                    $otp_result['otp_code'],
                    OTPManager::PURPOSE_LOGIN_2FA,
                    ['name' => $user['full_name']]
                );
                $message = 'Test verification code sent to ' . htmlspecialchars($user['email']);
                $message_type = 'success';
            } else {
                $message = $otp_result['message'];
                $message_type = 'danger';
            }
        }
    }
}
?>

<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0"><i class="bi bi-shield-lock me-2"></i>Two-Factor Authentication</h5>
                </div>
                <div class="card-body">
                    <?php if ($message): ?>
                    <div class="alert alert-<?php echo $message_type; ?> alert-dismissible fade show" role="alert">
                        <?php echo $message; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                    <?php endif; ?>
                    
                    <div class="row">
                        <div class="col-md-8">
                            <h6 class="mb-3">About Two-Factor Authentication</h6>
                            <p class="text-muted">
                                Two-factor authentication (2FA) adds an extra layer of security to your account.
                                When enabled, you'll need to enter a verification code from your email in addition
                                to your password when logging in.
                            </p>
                            
                            <div class="alert alert-info">
                                <i class="bi bi-info-circle me-2"></i>
                                <strong>Current Status:</strong> 
                                <?php if ($is_2fa_enabled): ?>
                                    <span class="badge bg-success">2FA is Enabled</span>
                                <?php else: ?>
                                    <span class="badge bg-secondary">2FA is Disabled</span>
                                <?php endif; ?>
                            </div>
                            
                            <div class="card bg-light mb-3">
                                <div class="card-body">
                                    <h6><i class="bi bi-shield-check me-2"></i>Benefits of 2FA</h6>
                                    <ul class="mb-0">
                                        <li>Protects your account even if your password is compromised</li>
                                        <li>Prevents unauthorized access from unknown devices</li>
                                        <li>Receive instant notifications of login attempts</li>
                                        <li>Recommended for Admin and HR accounts</li>
                                    </ul>
                                </div>
                            </div>
                        </div>
                        
                        <div class="col-md-4">
                            <div class="card">
                                <div class="card-header bg-white">
                                    <h6 class="mb-0">2FA Settings</h6>
                                </div>
                                <div class="card-body">
                                    <?php if ($is_2fa_enabled): ?>
                                        <!-- 2FA is enabled - show disable option -->
                                        <form method="POST" action="">
                                            <?php echo csrfField(); ?>
                                            <input type="hidden" name="action" value="disable_2fa">
                                            
                                            <div class="d-grid gap-2">
                                                <button type="submit" class="btn btn-outline-danger" 
                                                        onclick="return confirm('Are you sure you want to disable 2FA? This will make your account less secure.')">
                                                    <i class="bi bi-shield-x me-2"></i>Disable 2FA
                                                </button>
                                                
                                                <button type="submit" class="btn btn-outline-primary" name="action" value="send_test_code">
                                                    <i class="bi bi-envelope me-2"></i>Send Test Code
                                                </button>
                                            </div>
                                        </form>
                                    <?php else: ?>
                                        <!-- 2FA is disabled - show enable option -->
                                        <form method="POST" action="">
                                            <?php echo csrfField(); ?>
                                            <input type="hidden" name="action" value="enable_2fa">
                                            
                                            <div class="mb-3">
                                                <p class="small text-muted mb-2">
                                                    Verification codes will be sent to:
                                                </p>
                                                <p class="small mb-0">
                                                    <i class="bi bi-envelope me-1"></i>
                                                    <strong><?php echo htmlspecialchars($user['email']); ?></strong>
                                                </p>
                                            </div>
                                            
                                            <div class="d-grid">
                                                <button type="submit" class="btn btn-primary" 
                                                        onclick="return confirm('Enabling 2FA will require you to enter a verification code when logging in. Continue?')">
                                                    <i class="bi bi-shield-check me-2"></i>Enable 2FA
                                                </button>
                                            </div>
                                        </form>
                                    <?php endif; ?>
                                </div>
                            </div>
                            
                            <div class="card mt-3">
                                <div class="card-header bg-white">
                                    <h6 class="mb-0"><i class="bi bi-key me-2"></i>Security Tips</h6>
                                </div>
                                <div class="card-body small">
                                    <ul class="mb-0">
                                        <li>Never share your verification codes</li>
                                        <li>Keep your email account secure</li>
                                        <li>Use a strong password</li>
                                        <li>Log out from shared computers</li>
                                    </ul>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>
