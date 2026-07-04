<?php
// Define entry point constant for public pages
define('APP_ENTRY_POINT', true);

require_once 'includes/auth.php';

// Redirect if already logged in
if (Auth::isLoggedIn()) {
    Auth::redirectBasedOnRole();
}

$error = '';
$posted_username = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = trim($_POST['password'] ?? '');
    $posted_username = $username;

    // Server-side validation
    if (empty($username) || empty($password)) {
        $error = 'Please fill in all fields.';
    } else {
        $auth = new Auth();
        $login_result = $auth->login($username, $password);
        
        if ($login_result === true) {
            // Login successful without 2FA
            $redirect = $_GET['redirect'] ?? '';
            // PHP 7/8 compatible safe-redirect (avoid open redirect to external sites)
            if (is_string($redirect) && $redirect !== '' && strpos($redirect, '/') === 0 && strpos($redirect, '//') === false) {
                header('Location: ' . $redirect);
                exit();
            }
            Auth::redirectBasedOnRole();
        } elseif ($login_result === '2fa_required') {
            // 2FA verification required
            header('Location: verify_2fa.php');
            exit();
        } else {
            $error = 'Invalid username or password!';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - NexGen HRM</title>
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

        .login-card {
            background: var(--glass-bg);
            backdrop-filter: blur(12px);
            -webkit-backdrop-filter: blur(12px);
            border: 1px solid var(--glass-border);
            border-radius: 20px;
            padding: 40px;
            width: 100%;
            max-width: 420px;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.15);
            animation: fadeInScale 0.6s cubic-bezier(0.4, 0, 0.2, 1);
        }

        @keyframes fadeInScale {
            from { opacity: 0; transform: scale(0.95); }
            to { opacity: 1; transform: scale(1); }
        }

        .login-logo {
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

        .login-logo h3 {
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

        .btn-login {
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

        .btn-login:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(13, 110, 253, 0.3);
        }

        .btn-login:active {
            transform: translateY(0);
        }

        .login-footer {
            text-align: center;
            margin-top: 30px;
            padding-top: 20px;
            border-top: 1px solid rgba(0, 0, 0, 0.05);
        }

        .back-home {
            color: #64748b;
            text-decoration: none;
            font-weight: 500;
            font-size: 0.9rem;
            transition: color 0.2s;
        }

        .back-home:hover {
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
        
        #password.form-control {
            border-right: none !important;
        }

        .password-toggle:hover i {
            color: #0d6efd !important;
        }
    </style>
</head>
<body>
    <div class="login-card">
        <div class="login-logo">
            <div class="logo-icon-container">
                <i class="bi bi-building"></i>
            </div>
            <h3>NexGen HRM</h3>
            <p class="text-secondary small">Empowering Enterprise HR Solutions</p>
        </div>
        
        <?php if ($error): ?>
        <div class="alert alert-danger d-flex align-items-center" role="alert">
            <i class="bi bi-exclamation-triangle-fill me-2"></i>
            <div><?php echo $error; ?></div>
        </div>
        <?php endif; ?>
        
        <form method="POST" action="" class="needs-validation" novalidate id="loginForm">
            <div class="mb-3">
                <label for="username" class="form-label">Username</label>
                <div class="input-group has-validation">
                    <span class="input-group-text bg-white border-end-0">
                        <i class="bi bi-person text-secondary"></i>
                    </span>
                    <input type="text" class="form-control border-start-0" id="username" name="username" required 
                           placeholder="Enter your username" value="<?php echo htmlspecialchars($posted_username); ?>">
                    <div class="invalid-feedback">
                        Please enter your username or email.
                    </div>
                </div>
            </div>
            
            <div class="mb-4">
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <label for="password" class="form-label mb-0">Password</label>
                    <a href="reset_password.php" class="text-decoration-none small" style="font-size: 0.85rem;">
                        Forgot Password?
                    </a>
                </div>
                <div class="input-group has-validation">
                    <span class="input-group-text bg-white border-end-0">
                        <i class="bi bi-lock text-secondary"></i>
                    </span>
                    <input type="password" class="form-control border-start-0" id="password" name="password" required
                           placeholder="Enter your password">
                    <span class="input-group-text bg-white password-toggle" id="togglePassword">
                        <i class="bi bi-eye text-secondary"></i>
                    </span>
                    <div class="invalid-feedback">
                        Please enter your password.
                    </div>
                </div>
            </div>

            <button type="submit" class="btn btn-login" id="submitBtn">
                <span class="spinner-border spinner-border-sm d-none me-2" role="status" aria-hidden="true"></span>
                <span class="btn-text">Sign In</span>
            </button>
        </form>
        
        <div class="login-footer">
            <a href="index.php" class="back-home">
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
            const form = document.getElementById('loginForm')
            const submitBtn = document.getElementById('submitBtn')
            const spinner = submitBtn.querySelector('.spinner-border')
            const btnText = submitBtn.querySelector('.btn-text')

            form.addEventListener('submit', event => {
                if (!form.checkValidity()) {
                    event.preventDefault()
                    event.stopPropagation()
                } else {
                    // Show loading state
                    submitBtn.disabled = true
                    spinner.classList.remove('d-none')
                    btnText.textContent = 'Authenticating...'
                }

                form.classList.add('was-validated')
            }, false)

            // Add input listeners to remove validation state on change
            const inputs = form.querySelectorAll('input')
            inputs.forEach(input => {
                input.addEventListener('input', () => {
                    if (input.checkValidity()) {
                        input.classList.remove('is-invalid')
                    }
                })
            })

            // Password toggle logic
            const togglePassword = document.getElementById('togglePassword')
            const passwordInput = document.getElementById('password')
            const toggleIcon = togglePassword.querySelector('i')

            togglePassword.addEventListener('click', () => {
                const type = passwordInput.getAttribute('type') === 'password' ? 'text' : 'password'
                passwordInput.setAttribute('type', type)
                
                // Toggle icon
                toggleIcon.classList.toggle('bi-eye')
                toggleIcon.classList.toggle('bi-eye-slash')
            })
        })()
    </script>
</body>
</html>
