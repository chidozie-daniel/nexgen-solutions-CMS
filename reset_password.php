<?php
// Define entry point constant for public pages
define('APP_ENTRY_POINT', true);

require_once 'includes/auth.php';
require_once 'includes/functions.php';
require_once 'includes/otp.php';

// Redirect if already logged in
if (Auth::isLoggedIn()) {
    Auth::redirectBasedOnRole();
}

$page_title = 'Reset Password';
$error = '';
$success = '';
$email_sent = false;
$verification_step = false;
$posted_email = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Verify CSRF token
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid security token. Please try again.';
    } else {
        $action = $_POST['action'] ?? '';
        
        if ($action === 'request_reset') {
            // Step 1: Request password reset OTP
            $email = trim($_POST['email'] ?? '');
            $posted_email = $email;
            
            if (empty($email)) {
                $error = 'Please enter your email address.';
            } elseif (!isValidEmail($email)) {
                $error = 'Please enter a valid email address.';
            } else {
                // Check if email exists in database
                $conn = getDBConnection();
                $sql = "SELECT id, full_name, email FROM users WHERE email = ?";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("s", $email);
                $stmt->execute();
                $result = $stmt->get_result();
                
                if ($result->num_rows === 0) {
                    // For security, don't reveal if email exists or not
                    $success = 'If an account exists with this email, you will receive a password reset code shortly.';
                    $email_sent = true;
                    $verification_step = true; // Still show the form to stay consistent
                    // Set a dummy reset email in session so the UI doesn't crash
                    if (session_status() == PHP_SESSION_NONE) session_start();
                    $_SESSION['reset_email'] = $email;
                } else {
                    $user = $result->fetch_assoc();
                    
                    // Generate OTP
                    $otp_manager = new OTPManager();
                    $otp_result = $otp_manager->generateAndStoreOTP(
                        $email,
                        OTPManager::PURPOSE_PASSWORD_RESET,
                        $user['id']
                    );
                    
                    if ($otp_result['success']) {
                        // Send OTP email
                        $otp_manager->sendOTPEmail(
                            $email,
                            $otp_result['otp_code'],
                            OTPManager::PURPOSE_PASSWORD_RESET,
                            ['name' => $user['full_name']]
                        );
                        
                        $success = '✓ A password reset code has been sent to your email.<br><small class="text-muted">Check your inbox and spam folder.</small>';
                        $email_sent = true;
                        $verification_step = true;
                        
                        // Store details in session
                        if (session_status() == PHP_SESSION_NONE) session_start();
                        $_SESSION['reset_email'] = $email;
                        $_SESSION['reset_user_id'] = $user['id'];
                    } else {
                        $error = $otp_result['message'];
                    }
                }
            }
            
        } elseif ($action === 'verify_otp') {
            // Step 2: Verify OTP code
            if (session_status() == PHP_SESSION_NONE) {
                session_start();
            }
            
            $email = $_SESSION['reset_email'] ?? '';
            $user_id = $_SESSION['reset_user_id'] ?? null;
            $otp_code = trim($_POST['otp_code'] ?? '');
            
            if (empty($email)) {
                $error = 'Session expired. Please request a new password reset code.';
                $email_sent = false;
                $verification_step = false;
            } elseif (empty($otp_code)) {
                $error = 'Please enter the OTP code.';
            } else {
                $otp_manager = new OTPManager();
                $verification = $otp_manager->verifyOTP(
                    $email,
                    $otp_code,
                    OTPManager::PURPOSE_PASSWORD_RESET
                );
                
                if ($verification['success']) {
                    // OTP verified successfully, proceed to password reset
                    if (session_status() == PHP_SESSION_NONE) session_start();
                    $_SESSION['otp_verified'] = true;
                    $_SESSION['otp_verified_user_id'] = $user_id;
                    
                    // Clear any output buffers to ensure redirect works
                    if (ob_get_level()) ob_end_clean();
                    
                    header('Location: reset_password_complete.php');
                    exit();
                } else {
                    $error = $verification['message'];
                    
                    // Check if should allow resend
                    if (strpos($error, 'expired') !== false || strpos($error, 'Maximum') !== false) {
                        $email_sent = false;
                        $verification_step = false;
                        unset($_SESSION['reset_email']);
                        unset($_SESSION['reset_user_id']);
                    }
                }
            }
            
        } elseif ($action === 'resend_otp') {
            // Resend OTP
            if (session_status() == PHP_SESSION_NONE) {
                session_start();
            }
            
            $email = $_SESSION['reset_email'] ?? '';
            $user_id = $_SESSION['reset_user_id'] ?? null;
            
            if (!empty($email)) {
                $otp_manager = new OTPManager();
                $otp_result = $otp_manager->resendOTP(
                    $email,
                    OTPManager::PURPOSE_PASSWORD_RESET,
                    $user_id
                );
                
                if ($otp_result['success']) {
                    // Send OTP email
                    $conn = getDBConnection();
                    $sql = "SELECT full_name FROM users WHERE id = ?";
                    $stmt = $conn->prepare($sql);
                    $stmt->bind_param("i", $user_id);
                    $stmt->execute();
                    $result = $stmt->get_result();
                    $user = $result->fetch_assoc();
                    
                    $otp_manager->sendOTPEmail(
                        $email,
                        $otp_result['otp_code'],
                        OTPManager::PURPOSE_PASSWORD_RESET,
                        ['name' => ($user['full_name'] ?? 'User')]
                    );
                    
                    $success = '✓ A <strong>new</strong> password reset code has been sent.<br><small class="text-muted">Please use the new code, the old one is no longer valid.</small>';
                } else {
                    $error = $otp_result['message'];
                    
                    if (isset($otp_result['retry_after'])) {
                        $error .= ' Please wait ' . ceil($otp_result['retry_after'] / 60) . ' minutes.';
                    }
                }
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?> - NexGen HRMS</title>
    <!-- Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&family=Outfit:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <!-- Bootstrap 5 -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">

    <style>
        :root {
            --primary-gradient: linear-gradient(135deg, #0d6efd 0%, #0a58ca 100%);
            --glass-bg: rgba(255, 255, 255, 0.85);
            --glass-border: rgba(255, 255, 255, 0.4);
        }

        body {
            font-family: 'Inter', sans-serif;
            background-color: #f8fafc;
            background-image:
                radial-gradient(at 0% 0%, rgba(13, 110, 253, 0.08) 0px, transparent 50%),
                radial-gradient(at 100% 0%, rgba(10, 88, 202, 0.08) 0px, transparent 50%),
                url("https://www.transparenttextures.com/patterns/cubes.png");
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }

        h1, h2, h3, h4, h5, h6 {
            font-family: 'Outfit', sans-serif;
            font-weight: 700;
        }

        .reset-card {
            background: var(--glass-bg);
            backdrop-filter: blur(12px);
            -webkit-backdrop-filter: blur(12px);
            border: 1px solid var(--glass-border);
            border-radius: 20px;
            padding: 40px;
            width: 100%;
            max-width: 480px;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.15);
            animation: fadeInScale 0.6s cubic-bezier(0.4, 0, 0.2, 1);
        }

        @keyframes fadeInScale {
            from { opacity: 0; transform: scale(0.95); }
            to { opacity: 1; transform: scale(1); }
        }

        .reset-logo {
            text-align: center;
            margin-bottom: 35px;
        }

        .logo-icon-container {
            width: 60px;
            height: 60px;
            background: var(--primary-gradient);
            border-radius: 15px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 15px;
            box-shadow: 0 10px 15px -3px rgba(13, 110, 253, 0.3);
        }

        .logo-icon-container i {
            font-size: 28px;
            color: white;
        }

        .reset-logo h3 {
            background: var(--primary-gradient);
            -webkit-background-clip: text;
            background-clip: text;
            -webkit-text-fill-color: transparent;
            font-weight: 800;
            font-size: 1.75rem;
            margin-bottom: 5px;
        }

        .form-label {
            font-weight: 600;
            color: #475569;
            margin-bottom: 8px;
            font-size: 0.9rem;
        }

        .form-control {
            padding: 12px 16px;
            border-radius: 12px;
            border: 1px solid #e2e8f0;
            background: rgba(255, 255, 255, 0.8);
            transition: all 0.3s;
        }

        .form-control:focus {
            box-shadow: 0 0 0 4px rgba(13, 110, 253, 0.1);
            border-color: #0d6efd;
            background: white;
        }

        .btn-primary {
            background: var(--primary-gradient);
            color: white;
            padding: 14px;
            border-radius: 12px;
            width: 100%;
            font-weight: 700;
            border: none;
            transition: all 0.3s;
            margin-top: 10px;
            box-shadow: 0 4px 12px rgba(13, 110, 253, 0.2);
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(13, 110, 253, 0.3);
        }

        .btn-primary:disabled {
            opacity: 0.6;
            cursor: not-allowed;
        }

        .reset-footer {
            text-align: center;
            margin-top: 30px;
            padding-top: 20px;
            border-top: 1px solid rgba(0, 0, 0, 0.05);
        }

        .back-login {
            color: #64748b;
            text-decoration: none;
            font-weight: 500;
            font-size: 0.9rem;
            transition: color 0.2s;
        }

        .back-login:hover {
            color: #0d6efd;
        }

        .alert {
            border-radius: 12px;
            border: none;
            font-weight: 500;
            font-size: 0.9rem;
        }

        .step-indicator {
            display: flex;
            justify-content: center;
            margin-bottom: 30px;
        }

        .step {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: #e2e8f0;
            color: #64748b;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            margin: 0 10px;
            position: relative;
        }

        .step.active {
            background: var(--primary-gradient);
            color: white;
        }

        .step.completed {
            background: #10b981;
            color: white;
        }

        .step:not(:last-child)::after {
            content: '';
            position: absolute;
            top: 50%;
            left: 100%;
            width: 40px;
            height: 2px;
            background: #e2e8f0;
            transform: translateY(-50%);
            z-index: -1;
        }

        .step.completed:not(:last-child)::after {
            background: #10b981;
        }

        .otp-input {
            text-align: center;
            font-size: 1.5rem;
            letter-spacing: 10px;
            font-weight: 700;
        }

        .resend-link {
            color: #0d6efd;
            text-decoration: none;
            font-weight: 600;
        }

        .resend-link:hover {
            text-decoration: underline;
        }

        .resend-link.disabled {
            color: #64748b;
            pointer-events: none;
        }

        .security-note {
            background: rgba(13, 110, 253, 0.05);
            border-left: 3px solid #0d6efd;
            padding: 12px 15px;
            border-radius: 8px;
            font-size: 0.85rem;
            color: #475569;
        }
    </style>
</head>
<body>
    <div class="reset-card">
        <div class="reset-logo">
            <div class="logo-icon-container">
                <i class="bi bi-key"></i>
            </div>
            <h3>Reset Password</h3>
            <p class="text-secondary small">Secure password recovery with OTP verification</p>
        </div>

        <?php if ($error): ?>
        <div class="alert alert-danger d-flex align-items-center" role="alert">
            <i class="bi bi-exclamation-triangle-fill me-2"></i>
            <div><?php echo $error; ?></div>
        </div>
        <?php endif; ?>

        <?php if ($success): ?>
        <div class="alert alert-success d-flex align-items-center" role="alert">
            <i class="bi bi-check-circle-fill me-2"></i>
            <div><?php echo $success; ?></div>
        </div>
        <?php endif; ?>

        <!-- Step Indicator -->
        <?php if ($email_sent): ?>
        <div class="step-indicator">
            <div class="step completed">1</div>
            <div class="step <?php echo $verification_step ? 'active' : 'completed'; ?>">2</div>
            <div class="step <?php echo $verification_step ? '' : 'completed'; ?>">3</div>
        </div>
        <?php endif; ?>

        <!-- Step 1: Request OTP -->
        <?php if (!$email_sent): ?>
        <form method="POST" action="" class="needs-validation" novalidate id="resetForm">
            <?php echo csrfField(); ?>
            <input type="hidden" name="action" value="request_reset">
            
            <div class="mb-4">
                <label for="email" class="form-label">Email Address</label>
                <div class="input-group has-validation">
                    <span class="input-group-text bg-white border-end-0">
                        <i class="bi bi-envelope text-secondary"></i>
                    </span>
                    <input type="email" class="form-control border-start-0" id="email" name="email" required
                           placeholder="Enter your email address" value="<?php echo htmlspecialchars($posted_email); ?>">
                    <div class="invalid-feedback">
                        Please enter a valid email address.
                    </div>
                </div>
            </div>

            <div class="security-note mb-3">
                <i class="bi bi-shield-check me-1"></i>
                We'll send a verification code to your email to reset your password.
            </div>

            <button type="submit" class="btn btn-primary" id="submitBtn">
                <span class="spinner-border spinner-border-sm d-none me-2" role="status" aria-hidden="true"></span>
                <span class="btn-text">Send Verification Code</span>
            </button>
        </form>
        
        <!-- Step 2: Verify OTP -->
        <?php elseif ($verification_step): ?>
        <form method="POST" action="" class="needs-validation" novalidate id="verifyForm">
            <?php echo csrfField(); ?>
            <input type="hidden" name="action" value="verify_otp">
            
            <div class="mb-4">
                <label for="otp_code" class="form-label">Enter Verification Code</label>
                <p class="text-muted small mb-2">
                    We've sent a 6-digit code to <strong><?php echo htmlspecialchars($email_sent ? $posted_email : $_SESSION['reset_email'] ?? ''); ?></strong>
                </p>

                <?php 
                // Development Hint: Show OTP in UI if on localhost
                if (isLocalEnvironment()): 
                    $otp_mgr = new OTPManager();
                    $debug_otp = $otp_mgr->getLatestOTPCode($_SESSION['reset_email'] ?? '', OTPManager::PURPOSE_PASSWORD_RESET);
                    if ($debug_otp): 
                ?>
                    <div class="alert alert-warning py-2 small mb-3">
                        <i class="bi bi-bug-fill me-2"></i>
                        <strong>Development Hint:</strong> Your OTP code is <code><?php echo $debug_otp; ?></code>
                    </div>
                <?php endif; endif; ?>
                <input type="text" class="form-control otp-input" id="otp_code" name="otp_code" required
                       placeholder="000000" maxlength="6" pattern="[0-9]{6}" inputmode="numeric">
                <div class="invalid-feedback">
                    Please enter the 6-digit verification code.
                </div>
            </div>

            <div class="text-center mb-3">
                <p class="text-muted small mb-0">
                    Didn't receive the code? 
                    <a href="#" class="resend-link" id="resendLink" onclick="resendOTP(event)">Resend</a>
                </p>
                <p class="text-muted small" id="resendTimer"></p>
            </div>

            <button type="submit" class="btn btn-primary" id="verifyBtn">
                <span class="spinner-border spinner-border-sm d-none me-2" role="status" aria-hidden="true"></span>
                <span class="btn-text">Verify Code</span>
            </button>
            
            <form method="POST" action="" id="resendForm">
                <input type="hidden" name="action" value="resend_otp">
            </form>
        </form>

        <?php else: ?>
        <!-- Email sent successfully, showing success message -->
        <div class="text-center">
            <div class="mb-4">
                <div class="logo-icon-container mx-auto mb-3">
                    <i class="bi bi-envelope-check"></i>
                </div>
                <h5 class="mb-2">Check Your Email</h5>
                <p class="text-muted">
                    We've sent a verification code to<br>
                    <strong><?php echo htmlspecialchars($posted_email ?: $_SESSION['reset_email'] ?? 'your email'); ?></strong>
                </p>
            </div>
            <button type="button" class="btn btn-primary" onclick="showVerificationForm()">
                <i class="bi bi-shield-check me-2"></i>I Received the Code
            </button>
        </div>

        <script>
        function showVerificationForm() {
            document.getElementById('verifyForm').classList.remove('d-none');
            document.querySelector('.text-center').classList.add('d-none');
        }
        </script>
        <?php endif; ?>

        <div class="reset-footer">
            <a href="login.php" class="back-login">
                <i class="bi bi-arrow-left me-1"></i> Back to Login
            </a>
            <p class="text-muted mt-3 mb-0" style="font-size: 0.75rem;">
                &copy; <?php echo date('Y'); ?> NexGen Solutions. All rights reserved.
            </p>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Form validation
        (() => {
            'use strict'
            const forms = document.querySelectorAll('form.needs-validation')
            
            forms.forEach(form => {
                form.addEventListener('submit', event => {
                    if (!form.checkValidity()) {
                        event.preventDefault()
                        event.stopPropagation()
                    } else {
                        const submitBtn = form.querySelector('button[type="submit"]')
                        if (submitBtn) {
                            submitBtn.disabled = true
                            const spinner = submitBtn.querySelector('.spinner-border')
                            const btnText = submitBtn.querySelector('.btn-text')
                            if (spinner && btnText) {
                                spinner.classList.remove('d-none')
                                btnText.textContent = 'Processing...'
                            }
                        }
                    }

                    form.classList.add('was-validated')
                }, false)
            })
        })()

        // Resend OTP with timer
        function resendOTP(event) {
            event.preventDefault();
            const resendLink = document.getElementById('resendLink');
            const resendTimer = document.getElementById('resendTimer');
            
            if (resendLink.classList.contains('disabled')) {
                return;
            }
            
            // Disable the link
            resendLink.classList.add('disabled');
            resendLink.textContent = 'Resending...';
            
            // Submit the resend form
            document.getElementById('resendForm').submit();
        }

        // Auto-format OTP input
        const otpInput = document.getElementById('otp_code');
        if (otpInput) {
            otpInput.addEventListener('input', function(e) {
                // Remove non-numeric characters
                this.value = this.value.replace(/[^0-9]/g, '');
                
                // Limit to 6 digits
                if (this.value.length > 6) {
                    this.value = this.value.slice(0, 6);
                }
            });
            
            // Auto-submit when 6 digits entered
            otpInput.addEventListener('keyup', function(e) {
                if (this.value.length === 6) {
                    // Optional: Auto-submit the form
                    // document.getElementById('verifyForm').submit();
                }
            });
        }
    </script>
</body>
</html>
