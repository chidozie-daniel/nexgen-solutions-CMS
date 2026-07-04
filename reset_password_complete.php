<?php
// Define entry point constant for public pages
define('APP_ENTRY_POINT', true);

require_once 'includes/auth.php';
require_once 'includes/functions.php';

// Redirect if already logged in
if (Auth::isLoggedIn()) {
    Auth::redirectBasedOnRole();
}

$page_title = 'Set New Password';
$error = '';
$success = '';

// Check if OTP was verified
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

$otp_verified = $_SESSION['otp_verified'] ?? false;
$otp_user_id = $_SESSION['otp_verified_user_id'] ?? null;

// Also check for reset_email from the OTP verification flow
$reset_email = $_SESSION['reset_email'] ?? null;
$reset_user_id = $_SESSION['reset_user_id'] ?? null;

// Use the reset_user_id if otp_verified_user_id is not set
if (!$otp_user_id && $reset_user_id) {
    $otp_user_id = $reset_user_id;
}

// Redirect if OTP not verified
if (!$otp_verified || !$otp_user_id) {
    header('Location: reset_password.php');
    exit();
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid security token. Please refresh the page and try again.';
    } else {
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';

    if (empty($password)) {
        $error = 'Please enter a new password.';
    } elseif (strlen($password) < 8) {
        $error = 'Password must be at least 8 characters long.';
    } elseif (!preg_match('/[A-Z]/', $password)) {
        $error = 'Password must contain at least one uppercase letter.';
    } elseif (!preg_match('/[a-z]/', $password)) {
        $error = 'Password must contain at least one lowercase letter.';
    } elseif (!preg_match('/[0-9]/', $password)) {
        $error = 'Password must contain at least one number.';
    } elseif (!preg_match('/[!@#$%^&*(),.?":{}|<>]/', $password)) {
        $error = 'Password must contain at least one special character.';
    } elseif ($password !== $confirm_password) {
        $error = 'Passwords do not match.';
    } else {
        // Update password
        $conn = getDBConnection();
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
        
        $sql = "UPDATE users SET password = ?, password_reset_token = NULL, password_reset_expires = NULL WHERE id = ?";
        $stmt = dbPrepare($conn, $sql, 'reset_password_complete');
        if (!$stmt) {
            $error = 'Failed to reset password. Please try again.';
        } else {
        $stmt->bind_param("si", $hashed_password, $otp_user_id);

        if (dbExecute($stmt, 'reset_password_complete')) {
            // Clear session variables
            unset($_SESSION['otp_verified']);
            unset($_SESSION['otp_verified_user_id']);
            unset($_SESSION['reset_email']);
            unset($_SESSION['reset_user_id']);
            
            // Log activity
            logActivity(
                'PASSWORD_RESET',
                'User reset their password via OTP verification',
                'users',
                $otp_user_id,
                null,
                ['password' => '***']
            );
            
            $success = 'Your password has been reset successfully! Redirecting to login...';
            
            // Redirect to login after 3 seconds
            header('Refresh: 3; URL=login.php');
        } else {
            $error = 'Failed to reset password. Please try again.';
        }
        $stmt->close();
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

        .password-requirements {
            background: rgba(13, 110, 253, 0.05);
            border-left: 3px solid #0d6efd;
            padding: 12px 15px;
            border-radius: 8px;
            font-size: 0.85rem;
            color: #475569;
        }

        .password-requirements ul {
            margin: 8px 0 0 0;
            padding-left: 20px;
        }

        .password-requirements li {
            margin: 4px 0;
        }

        .requirement-met {
            color: #10b981;
        }

        .requirement-not-met {
            color: #64748b;
        }

        .password-toggle {
            cursor: pointer;
            z-index: 10;
            background: transparent !important;
            border-left: none !important;
        }

        #password.form-control, #confirm_password.form-control {
            border-right: none !important;
        }

        .password-toggle:hover i {
            color: #0d6efd !important;
        }
    </style>
</head>
<body>
    <div class="reset-card">
        <div class="reset-logo">
            <div class="logo-icon-container">
                <i class="bi bi-shield-lock"></i>
            </div>
            <h3>Set New Password</h3>
            <p class="text-secondary small">Create a strong password for your account</p>
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
        <div class="step-indicator">
            <div class="step completed">1</div>
            <div class="step completed">2</div>
            <div class="step active">3</div>
        </div>

        <form method="POST" action="" class="needs-validation" novalidate id="passwordForm">
            <?php echo csrfField(); ?>
            <div class="mb-3">
                <label for="password" class="form-label">New Password</label>
                <div class="input-group has-validation">
                    <span class="input-group-text bg-white border-end-0">
                        <i class="bi bi-lock text-secondary"></i>
                    </span>
                    <input type="password" class="form-control border-start-0" id="password" name="password" required
                           placeholder="Enter new password">
                    <span class="input-group-text bg-white password-toggle" id="togglePassword">
                        <i class="bi bi-eye text-secondary"></i>
                    </span>
                    <div class="invalid-feedback">
                        Please enter a new password.
                    </div>
                </div>
            </div>

            <div class="mb-3">
                <label for="confirm_password" class="form-label">Confirm Password</label>
                <div class="input-group has-validation">
                    <span class="input-group-text bg-white border-end-0">
                        <i class="bi bi-lock-fill text-secondary"></i>
                    </span>
                    <input type="password" class="form-control border-start-0" id="confirm_password" name="confirm_password" required
                           placeholder="Confirm new password">
                    <span class="input-group-text bg-white password-toggle" id="toggleConfirmPassword">
                        <i class="bi bi-eye text-secondary"></i>
                    </span>
                    <div class="invalid-feedback">
                        Please confirm your password.
                    </div>
                </div>
            </div>

            <div class="password-requirements mb-3">
                <strong>Password Requirements:</strong>
                <ul>
                    <li id="req-length"><span class="requirement-not-met">●</span> At least 8 characters</li>
                    <li id="req-uppercase"><span class="requirement-not-met">●</span> One uppercase letter</li>
                    <li id="req-lowercase"><span class="requirement-not-met">●</span> One lowercase letter</li>
                    <li id="req-number"><span class="requirement-not-met">●</span> One number</li>
                    <li id="req-special"><span class="requirement-not-met">●</span> One special character (!@#$%^&*...)</li>
                    <li id="req-match"><span class="requirement-not-met">●</span> Passwords match</li>
                </ul>
            </div>

            <button type="submit" class="btn btn-primary" id="submitBtn" <?php echo $success ? 'disabled' : ''; ?>>
                <span class="spinner-border spinner-border-sm d-none me-2" role="status" aria-hidden="true"></span>
                <span class="btn-text"><?php echo $success ? 'Password Reset Complete' : 'Reset Password'; ?></span>
            </button>
        </form>

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
            const form = document.getElementById('passwordForm')
            const submitBtn = document.getElementById('submitBtn')
            const spinner = submitBtn.querySelector('.spinner-border')
            const btnText = submitBtn.querySelector('.btn-text')

            form.addEventListener('submit', event => {
                if (!form.checkValidity()) {
                    event.preventDefault()
                    event.stopPropagation()
                } else {
                    // Check if passwords match
                    const password = document.getElementById('password').value;
                    const confirm_password = document.getElementById('confirm_password').value;
                    
                    if (password !== confirm_password) {
                        event.preventDefault()
                        event.stopPropagation()
                        document.getElementById('confirm_password').classList.add('is-invalid')
                    } else {
                        submitBtn.disabled = true
                        spinner.classList.remove('d-none')
                        btnText.textContent = 'Resetting Password...'
                    }
                }

                form.classList.add('was-validated')
            }, false)
        })()

        // Password requirements checker
        const passwordInput = document.getElementById('password');
        const confirmPasswordInput = document.getElementById('confirm_password');

        function checkRequirements(password, confirmPassword = '') {
            // Length
            updateRequirement('req-length', password.length >= 8);
            
            // Uppercase
            updateRequirement('req-uppercase', /[A-Z]/.test(password));
            
            // Lowercase
            updateRequirement('req-lowercase', /[a-z]/.test(password));
            
            // Number
            updateRequirement('req-number', /[0-9]/.test(password));
            
            // Special character
            updateRequirement('req-special', /[!@#$%^&*(),.?":{}|<>]/.test(password));
            
            // Match
            if (confirmPassword) {
                updateRequirement('req-match', password === confirmPassword && password.length > 0);
            }
        }

        function updateRequirement(id, met) {
            const element = document.getElementById(id);
            if (element) {
                const span = element.querySelector('span');
                if (met) {
                    span.className = 'requirement-met';
                    span.textContent = '✓';
                } else {
                    span.className = 'requirement-not-met';
                    span.textContent = '●';
                }
            }
        }

        passwordInput.addEventListener('input', function() {
            checkRequirements(this.value, confirmPasswordInput.value);
        });

        confirmPasswordInput.addEventListener('input', function() {
            checkRequirements(passwordInput.value, this.value);
        });

        // Password toggle logic
        const togglePassword = document.getElementById('togglePassword');
        const toggleConfirmPassword = document.getElementById('toggleConfirmPassword');
        const passwordField = document.getElementById('password');
        const confirmField = document.getElementById('confirm_password');

        togglePassword.addEventListener('click', function() {
            const type = passwordField.getAttribute('type') === 'password' ? 'text' : 'password';
            passwordField.setAttribute('type', type);

            const icon = this.querySelector('i');
            icon.classList.toggle('bi-eye');
            icon.classList.toggle('bi-eye-slash');
        });

        toggleConfirmPassword.addEventListener('click', function() {
            const type = confirmField.getAttribute('type') === 'password' ? 'text' : 'password';
            confirmField.setAttribute('type', type);

            const icon = this.querySelector('i');
            icon.classList.toggle('bi-eye');
            icon.classList.toggle('bi-eye-slash');
        });
    </script>
</body>
</html>
