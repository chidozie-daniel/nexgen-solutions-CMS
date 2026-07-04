<?php
// Define entry point constant for public pages
define('APP_ENTRY_POINT', true);

require_once 'includes/auth.php';
require_once 'includes/functions.php';

// Redirect if already logged in
if (Auth::isLoggedIn()) {
    Auth::redirectBasedOnRole();
}

$page_title = 'Register';
$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid security token. Please refresh the page and try again.';
    } else {
    $full_name = trim($_POST['full_name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    $position = trim($_POST['position'] ?? '');
    
    $errors = [];
    
    // Validate full name
    if (empty($full_name) || strlen($full_name) < 2) {
        $errors[] = 'Full name is required.';
    }
    
    // Validate email
    if (empty($email)) {
        $errors[] = 'Email address is required.';
    } elseif (!isValidEmail($email)) {
        $errors[] = 'A valid email address is required.';
    }
    
    // Validate username
    if (empty($username)) {
        $errors[] = 'Username is required.';
    } elseif (!isValidUsername($username)) {
        $errors[] = 'Username must be 3-30 characters (letters, numbers, dot, underscore, dash).';
    }
    
    // Validate password
    if (empty($password)) {
        $errors[] = 'Password is required.';
    } elseif (strlen($password) < 8) {
        $errors[] = 'Password must be at least 8 characters long.';
    } elseif (!preg_match('/[A-Z]/', $password)) {
        $errors[] = 'Password must contain at least one uppercase letter.';
    } elseif (!preg_match('/[a-z]/', $password)) {
        $errors[] = 'Password must contain at least one lowercase letter.';
    } elseif (!preg_match('/[0-9]/', $password)) {
        $errors[] = 'Password must contain at least one number.';
    }
    
    // Validate password confirmation
    if ($password !== $confirm_password) {
        $errors[] = 'Passwords do not match.';
    }
    
    // Validate position
    if (empty($position)) {
        $errors[] = 'Position is required.';
    }
    
    if (empty($errors)) {
        $conn = getDBConnection();
        $check_sql = "SELECT id FROM users WHERE username = ? OR email = ?";
        $check_stmt = dbPrepare($conn, $check_sql, 'public_register duplicate check');
        if (!$check_stmt) {
            $errors[] = 'Could not verify registration. Please try again.';
        } else {
            $check_stmt->bind_param("ss", $username, $email);
            if (!dbExecute($check_stmt, 'public_register duplicate check')) {
                $check_stmt->close();
                $errors[] = 'Could not verify registration. Please try again.';
            } else {
                $check_result = $check_stmt->get_result();
                if ($check_result->num_rows > 0) {
                    $check_stmt->close();
                    $errors[] = 'Username or email already exists.';
                } else {
                    if (session_status() == PHP_SESSION_NONE) {
                        session_start();
                    }

                    $_SESSION['pending_email'] = $email;
                    $_SESSION['pending_registration'] = [
                        'full_name' => $full_name,
                        'username' => $username,
                        'password' => $password,
                        'position' => $position,
                        'role' => 'employee',
                        'department' => 'General',
                        'salary' => null,
                        'hire_date' => date('Y-m-d')
                    ];

                    $check_stmt->close();
                    header('Location: verify_email.php');
                    exit();
                }
            }
        }
    }
    
    if (!empty($errors)) {
        $error = implode('<br>', $errors);
    }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register - NexGen HRMS</title>
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

        .register-card {
            background: var(--glass-bg);
            backdrop-filter: blur(12px);
            -webkit-backdrop-filter: blur(12px);
            border: 1px solid var(--glass-border);
            border-radius: 20px;
            padding: 40px;
            width: 100%;
            max-width: 500px;
            max-height: 90vh;
            overflow-y: auto;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.15);
            animation: fadeInScale 0.6s cubic-bezier(0.4, 0, 0.2, 1);
        }

        @keyframes fadeInScale {
            from { opacity: 0; transform: scale(0.95); }
            to { opacity: 1; transform: scale(1); }
        }

        .register-logo {
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

        .register-logo h3 {
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

        .btn-register {
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

        .btn-register:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(13, 110, 253, 0.3);
        }

        .btn-register:disabled {
            opacity: 0.6;
            cursor: not-allowed;
        }

        .register-footer {
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

        .requirements-list {
            font-size: 0.8rem;
            color: #64748b;
            margin-top: 5px;
        }

        .requirements-list li {
            margin-bottom: 2px;
        }
    </style>
</head>
<body>
    <div class="register-card">
        <div class="register-logo">
            <div class="logo-icon-container">
                <i class="bi bi-person-plus"></i>
            </div>
            <h3>Create Account</h3>
            <p class="text-secondary small">Join NexGen HRMS</p>
        </div>

        <?php if ($error): ?>
        <div class="alert alert-danger d-flex align-items-center" role="alert">
            <i class="bi bi-exclamation-triangle-fill me-2"></i>
            <div><?php echo $error; ?></div>
        </div>
        <?php endif; ?>

        <form method="POST" action="" class="needs-validation" novalidate id="registerForm">
            <?php echo csrfField(); ?>
            <div class="mb-3">
                <label for="full_name" class="form-label">Full Name</label>
                <div class="input-group has-validation">
                    <span class="input-group-text bg-white border-end-0">
                        <i class="bi bi-person text-secondary"></i>
                    </span>
                    <input type="text" class="form-control border-start-0" id="full_name" name="full_name" required
                           placeholder="Enter your full name">
                    <div class="invalid-feedback">
                        Please enter your full name.
                    </div>
                </div>
            </div>

            <div class="mb-3">
                <label for="email" class="form-label">Email Address</label>
                <div class="input-group has-validation">
                    <span class="input-group-text bg-white border-end-0">
                        <i class="bi bi-envelope text-secondary"></i>
                    </span>
                    <input type="email" class="form-control border-start-0" id="email" name="email" required
                           placeholder="Enter your email">
                    <div class="invalid-feedback">
                        Please enter a valid email address.
                    </div>
                </div>
            </div>

            <div class="mb-3">
                <label for="username" class="form-label">Username</label>
                <div class="input-group has-validation">
                    <span class="input-group-text bg-white border-end-0">
                        <i class="bi bi-at text-secondary"></i>
                    </span>
                    <input type="text" class="form-control border-start-0" id="username" name="username" required
                           placeholder="Choose a username" minlength="3" maxlength="30">
                    <div class="invalid-feedback">
                        Username must be 3-30 characters.
                    </div>
                </div>
            </div>

            <div class="mb-3">
                <label for="position" class="form-label">Position</label>
                <div class="input-group has-validation">
                    <span class="input-group-text bg-white border-end-0">
                        <i class="bi bi-briefcase text-secondary"></i>
                    </span>
                    <input type="text" class="form-control border-start-0" id="position" name="position" required
                           placeholder="e.g., Software Engineer">
                    <div class="invalid-feedback">
                        Please enter your position.
                    </div>
                </div>
            </div>

            <div class="mb-3">
                <label for="password" class="form-label">Password</label>
                <div class="input-group has-validation">
                    <span class="input-group-text bg-white border-end-0">
                        <i class="bi bi-lock text-secondary"></i>
                    </span>
                    <input type="password" class="form-control border-start-0" id="password" name="password" required
                           placeholder="Create a password">
                    <span class="input-group-text bg-white password-toggle" id="togglePassword">
                        <i class="bi bi-eye text-secondary"></i>
                    </span>
                    <div class="invalid-feedback">
                        Please enter a password.
                    </div>
                </div>
                <ul class="requirements-list">
                    <li>• At least 8 characters</li>
                    <li>• One uppercase, lowercase, and number</li>
                </ul>
            </div>

            <div class="mb-4">
                <label for="confirm_password" class="form-label">Confirm Password</label>
                <div class="input-group has-validation">
                    <span class="input-group-text bg-white border-end-0">
                        <i class="bi bi-lock-fill text-secondary"></i>
                    </span>
                    <input type="password" class="form-control border-start-0" id="confirm_password" name="confirm_password" required
                           placeholder="Confirm your password">
                    <span class="input-group-text bg-white password-toggle" id="toggleConfirmPassword">
                        <i class="bi bi-eye text-secondary"></i>
                    </span>
                    <div class="invalid-feedback">
                        Please confirm your password.
                    </div>
                </div>
            </div>

            <button type="submit" class="btn btn-register" id="submitBtn">
                <span class="spinner-border spinner-border-sm d-none me-2" role="status" aria-hidden="true"></span>
                <span class="btn-text">Create Account</span>
            </button>
        </form>

        <div class="register-footer">
            <p class="text-muted mb-2 small">
                Already have an account? 
                <a href="login.php" class="text-decoration-none fw-bold">Login here</a>
            </p>
            <a href="index.php" class="back-login">
                <i class="bi bi-arrow-left me-1"></i> Back to Homepage
            </a>
            <p class="text-muted mt-3 mb-0" style="font-size: 0.75rem;">
                &copy; <?php echo date('Y'); ?> NexGen Solutions. All rights reserved.
            </p>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        (() => {
            'use strict'
            const form = document.getElementById('registerForm')
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
                        btnText.textContent = 'Creating Account...'
                    }
                }

                form.classList.add('was-validated')
            }, false)
        })()

        // Password toggle logic
        const togglePassword = document.getElementById('togglePassword')
        const toggleConfirmPassword = document.getElementById('toggleConfirmPassword')
        const passwordField = document.getElementById('password')
        const confirmField = document.getElementById('confirm_password')

        togglePassword.addEventListener('click', function() {
            const type = passwordField.getAttribute('type') === 'password' ? 'text' : 'password'
            passwordField.setAttribute('type', type)

            const icon = this.querySelector('i')
            icon.classList.toggle('bi-eye')
            icon.classList.toggle('bi-eye-slash')
        })

        toggleConfirmPassword.addEventListener('click', function() {
            const type = confirmField.getAttribute('type') === 'password' ? 'text' : 'password'
            confirmField.setAttribute('type', type)

            const icon = this.querySelector('i')
            icon.classList.toggle('bi-eye')
            icon.classList.toggle('bi-eye-slash')
        })
    </script>
</body>
</html>
