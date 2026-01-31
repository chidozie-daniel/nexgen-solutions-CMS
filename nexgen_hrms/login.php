<?php
require_once 'includes/auth.php';

// Redirect if already logged in
if (Auth::isLoggedIn()) {
    Auth::redirectBasedOnRole();
}

$error = '';
$posted_username = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';
    $posted_username = $username;
    
    $auth = new Auth();
    if ($auth->login($username, $password)) {
        $redirect = $_GET['redirect'] ?? '';
        // PHP 7/8 compatible safe-redirect (avoid open redirect to external sites)
        if (is_string($redirect) && $redirect !== '' && strpos($redirect, '/') === 0 && strpos($redirect, '//') === false) {
            header('Location: ' . $redirect);
            exit();
        }
        Auth::redirectBasedOnRole();
    } else {
        $error = 'Invalid username or password!';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - NexGen HRMS</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <style>
        body {
            background: linear-gradient(135deg, #00c6ff 0%, #0072ff 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .login-card {
            background: white;
            border-radius: 10px;
            padding: 30px;
            width: 100%;
            max-width: 400px;
            box-shadow: 0 15px 35px rgba(0,0,0,0.2);
        }
        
        .login-logo {
            text-align: center;
            margin-bottom: 20px;
        }
        
        .login-logo h3 {
            color: #0d6efd;
            font-weight: 700;
        }
        
        .form-control {
            padding: 10px;
            border-radius: 8px;
            margin-bottom: 15px;
        }
        
        .btn-login {
            background: #0d6efd;
            color: white;
            padding: 12px;
            border-radius: 8px;
            width: 100%;
            font-weight: 600;
            border: none;
            transition: all 0.3s;
        }
        
        .btn-login:hover {
            background: #0b5ed7;
            transform: translateY(-2px);
        }
        
        .login-footer {
            text-align: center;
            margin-top: 20px;
            color: #6c757d;
        }
    </style>
</head>
<body>
    <div class="login-card">
        <div class="login-logo">
            <h3><i class="bi bi-building"></i> NexGen HRMS</h3>
            <p class="text-muted">Employee Management System</p>
        </div>
        
        <?php if ($error): ?>
        <div class="alert alert-danger"><?php echo $error; ?></div>
        <?php endif; ?>
        
        <form method="POST" action="">
            <div class="mb-3">
                <label for="username" class="form-label">Username</label>
                <input type="text" class="form-control" id="username" name="username" required 
                       placeholder="Enter username" value="<?php echo htmlspecialchars($posted_username ?? ''); ?>">
            </div>
            
            <div class="mb-3">
                <label for="password" class="form-label">Password</label>
                <input type="password" class="form-control" id="password" name="password" required 
                       placeholder="Enter password">
            </div>
            
            <button type="submit" class="btn btn-login w-100">Sign In</button>
        </form>
        
        <div class="login-footer">
            <p class="mb-2">
                <strong>Quick Login:</strong>
            </p>
            
            <div class="row g-2">
                <div class="col-6">
                    <form method="POST" action="">
                        <input type="hidden" name="username" value="admin">
                        <input type="hidden" name="password" value="Admin@123">
                        <button type="submit" class="btn btn-danger btn-sm w-100" title="Admin / Admin@123">Admin</button>
                    </form>
                </div>
                
                <div class="col-6">
                    <form method="POST" action="">
                        <input type="hidden" name="username" value="hrmanager">
                        <input type="hidden" name="password" value="hr123">
                        <button type="submit" class="btn btn-warning btn-sm w-100" style="color: #000;" title="HR Manager / hr123">HR Manager</button>
                    </form>
                </div>

                <div class="col-6">
                    <form method="POST" action="">
                        <input type="hidden" name="username" value="projlead">
                        <input type="hidden" name="password" value="pl123">
                        <button type="submit" class="btn btn-primary btn-sm w-100" title="Project Leader / pl123">Proj. Leader</button>
                    </form>
                </div>

                <div class="col-6">
                    <form method="POST" action="">
                        <input type="hidden" name="username" value="employee">
                        <input type="hidden" name="password" value="Employee@123">
                        <button type="submit" class="btn btn-secondary btn-sm w-100" title="Employee / Employee@123">Employee</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>