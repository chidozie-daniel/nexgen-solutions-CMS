<?php
// Prevent direct access to this file
if (!defined('APP_ENTRY_POINT') && php_sapi_name() !== 'cli') {
    http_response_code(403);
    exit('Direct access not allowed');
}

$base_dir = dirname(dirname(__FILE__));
require_once $base_dir . '/config/database.php';
require_once $base_dir . '/includes/otp.php';

class Auth {
    private $conn;
    private $otp_manager;

    public function __construct() {
        $this->conn = getDBConnection();
        $this->otp_manager = new OTPManager();
    }
    
    private static function strContains($haystack, $needle) {
        if ($needle === '') return true;
        return strpos((string)$haystack, (string)$needle) !== false;
    }
    
    private static function strStartsWith($haystack, $needle) {
        $haystack = (string)$haystack;
        $needle = (string)$needle;
        if ($needle === '') return true;
        return strncmp($haystack, $needle, strlen($needle)) === 0;
    }
    
    public static function getBasePath() {
        $script = $_SERVER['SCRIPT_NAME'] ?? '';
        $parts = array_values(array_filter(explode('/', $script), 'strlen'));

        if (count($parts) === 0) {
            return '';
        }

        // If the first segment looks like a file (e.g. dashboard.php), assume app is hosted at domain root
        if (self::strContains($parts[0], '.php')) {
            return '';
        }

        return '/' . $parts[0];
    }
    
    // Login function
    public function login($username, $password) {
        $sql = "SELECT * FROM users WHERE username = ? OR email = ?";
        $stmt = $this->conn->prepare($sql);
        $stmt->bind_param("ss", $username, $username);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result && $result->num_rows == 1) {
            $user = $result->fetch_assoc();

            // Verify password
            if (password_verify($password, $user['password'])) {
                // Check if 2FA is enabled
                $two_factor_enabled = $user['two_factor_enabled'] ?? 0;
                
                if ($two_factor_enabled) {
                    // Generate and send 2FA OTP
                    $otp_result = $this->otp_manager->generateAndStoreOTP(
                        $user['email'],
                        OTPManager::PURPOSE_LOGIN_2FA,
                        $user['id']
                    );
                    
                    if ($otp_result['success']) {
                        // Send OTP email
                        $this->otp_manager->sendOTPEmail(
                            $user['email'],
                            $otp_result['otp_code'],
                            OTPManager::PURPOSE_LOGIN_2FA,
                            ['name' => $user['full_name']]
                        );
                        
                        // Store user ID in session for 2FA verification
                        if (session_status() == PHP_SESSION_NONE) {
                            session_start();
                        }
                        $_SESSION['pending_user_id'] = $user['id'];
                        $_SESSION['pending_username'] = $user['username'];
                        
                        // Return special status for 2FA
                        return '2fa_required';
                    }
                }
                
                // Update last login
                $update_sql = "UPDATE users SET last_login = NOW() WHERE id = ?";
                $update_stmt = $this->conn->prepare($update_sql);
                $update_stmt->bind_param("i", $user['id']);
                $update_stmt->execute();

                // Start session if not already started
                if (session_status() == PHP_SESSION_NONE) {
                    session_start();
                }

                // Set ALL session variables properly
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['full_name'] = $user['full_name'];
                $_SESSION['role'] = $user['role'];
                $_SESSION['employee_id'] = $user['employee_id'];
                $_SESSION['department'] = $user['department'];
                $_SESSION['position'] = $user['position'];
                $_SESSION['profile_image'] = $user['profile_image'] ?? 'default.jpg';

                return true;
            }
        }
        return false;
    }
    
    // Check if user is logged in
    public static function isLoggedIn() {
        if (session_status() == PHP_SESSION_NONE) {
            session_start();
        }
        return isset($_SESSION['user_id']);
    }
    
    // Check user role
    public static function hasRole($role) {
        if (!self::isLoggedIn()) return false;
        return $_SESSION['role'] == $role;
    }

    // Require specific role(s) - access control for pages
    public static function requireRole($allowedRoles) {
        self::requireLogin(); // First ensure logged in
        
        if (!is_array($allowedRoles)) {
            $allowedRoles = [$allowedRoles];
        }
        
        if (!self::isLoggedIn()) {
            return false;
        }
        
        $userRole = $_SESSION['role'] ?? '';
        
        if (!in_array($userRole, $allowedRoles, true)) {
            // Set flash message for access denied
            if (session_status() == PHP_SESSION_NONE) {
                session_start();
            }
            $_SESSION['flash'] = [
                'type' => 'danger',
                'message' => 'Access denied. You do not have permission to access this page.'
            ];
            
            // Redirect to dashboard
            $base = self::getBasePath();
            header('Location: ' . $base . '/dashboard.php');
            exit();
        }
        
        return true;
    }

    // Redirect if not logged in
    public static function requireLogin() {
        if (!self::isLoggedIn()) {
            $base = self::getBasePath();
            header('Location: ' . $base . '/login.php?redirect=' . urlencode($_SERVER['REQUEST_URI']));
            exit();
        }
    }
    
    // Redirect based on role
    public static function redirectBasedOnRole() {
        if (self::isLoggedIn()) {
            $base = self::getBasePath();
            $role = $_SESSION['role'];
            switch($role) {
                case 'admin':
                    header('Location: ' . $base . '/dashboard.php');
                    break;
                case 'hr':
                    header('Location: ' . $base . '/dashboard.php');
                    break;
                case 'project_leader':
                    header('Location: ' . $base . '/dashboard.php');
                    break;
                default:
                    header('Location: ' . $base . '/dashboard.php');
            }
            exit();
        }
    }
    
    // Get current user info
    public static function getCurrentUser() {
        if (!self::isLoggedIn()) return null;
        
        $conn = getDBConnection();
        $user_id = $_SESSION['user_id'];
        $sql = "SELECT * FROM users WHERE id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        return $result->fetch_assoc();
    }
    
    // Logout function
    public static function logout() {
        if (session_status() == PHP_SESSION_NONE) {
            session_start();
        }
        session_destroy();
        $base = self::getBasePath();
        header('Location: ' . $base . '/login.php');
        exit();
    }
    
    // ==========================================
    // 2FA Management Methods
    // ==========================================
    
    /**
     * Verify 2FA OTP code
     * @param string $otp_code OTP code to verify
     * @return array ['success' => bool, 'message' => string]
     */
    public function verify2FA($otp_code) {
        if (session_status() == PHP_SESSION_NONE) {
            session_start();
        }
        
        $pending_user_id = $_SESSION['pending_user_id'] ?? null;
        
        if (!$pending_user_id) {
            return [
                'success' => false,
                'message' => 'Session expired. Please login again.'
            ];
        }
        
        // Get user email
        $sql = "SELECT email, full_name FROM users WHERE id = ?";
        $stmt = $this->conn->prepare($sql);
        $stmt->bind_param("i", $pending_user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows !== 1) {
            return [
                'success' => false,
                'message' => 'User not found.'
            ];
        }
        
        $user = $result->fetch_assoc();
        
        // Verify OTP
        $verification = $this->otp_manager->verifyOTP(
            $user['email'],
            $otp_code,
            OTPManager::PURPOSE_LOGIN_2FA
        );
        
        if ($verification['success']) {
            // Complete login - set session variables
            $full_user_data = $this->getCompleteUserData($pending_user_id);
            
            // Update last login
            $update_sql = "UPDATE users SET last_login = NOW() WHERE id = ?";
            $update_stmt = $this->conn->prepare($update_sql);
            $update_stmt->bind_param("i", $pending_user_id);
            $update_stmt->execute();
            
            // Set session variables
            $_SESSION['user_id'] = $full_user_data['id'];
            $_SESSION['username'] = $full_user_data['username'];
            $_SESSION['full_name'] = $full_user_data['full_name'];
            $_SESSION['role'] = $full_user_data['role'];
            $_SESSION['employee_id'] = $full_user_data['employee_id'];
            $_SESSION['department'] = $full_user_data['department'];
            $_SESSION['position'] = $full_user_data['position'];
            $_SESSION['profile_image'] = $full_user_data['profile_image'] ?? 'default.jpg';
            
            // Clear pending session variables
            unset($_SESSION['pending_user_id']);
            unset($_SESSION['pending_username']);
            
            // Log activity
            logActivity(
                'USER_LOGIN_2FA',
                'User logged in with 2FA verification',
                'users',
                $pending_user_id,
                null,
                ['ip_address' => $_SERVER['REMOTE_ADDR'] ?? null]
            );
            
            return [
                'success' => true,
                'message' => 'Login successful!'
            ];
        }
        
        return $verification;
    }
    
    /**
     * Get complete user data by ID
     */
    private function getCompleteUserData($user_id) {
        $sql = "SELECT * FROM users WHERE id = ?";
        $stmt = $this->conn->prepare($sql);
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        return $result->fetch_assoc();
    }
    
    /**
     * Enable 2FA for a user
     * @param int $user_id User ID
     * @return array ['success' => bool, 'message' => string]
     */
    public static function enable2FA($user_id) {
        $conn = getDBConnection();
        $sql = "UPDATE users SET two_factor_enabled = 1 WHERE id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $user_id);
        
        if ($stmt->execute()) {
            logActivity(
                '2FA_ENABLED',
                'User enabled two-factor authentication',
                'users',
                $user_id
            );
            
            return [
                'success' => true,
                'message' => '2FA enabled successfully.'
            ];
        }
        
        return [
            'success' => false,
            'message' => 'Failed to enable 2FA.'
        ];
    }
    
    /**
     * Disable 2FA for a user
     * @param int $user_id User ID
     * @return array ['success' => bool, 'message' => string]
     */
    public static function disable2FA($user_id) {
        $conn = getDBConnection();
        $sql = "UPDATE users SET two_factor_enabled = 0 WHERE id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $user_id);
        
        if ($stmt->execute()) {
            logActivity(
                '2FA_DISABLED',
                'User disabled two-factor authentication',
                'users',
                $user_id
            );
            
            return [
                'success' => true,
                'message' => '2FA disabled successfully.'
            ];
        }
        
        return [
            'success' => false,
            'message' => 'Failed to disable 2FA.'
        ];
    }
    
    /**
     * Check if user has 2FA enabled
     * @param int $user_id User ID
     * @return bool
     */
    public static function is2FAEnabled($user_id) {
        $conn = getDBConnection();
        $sql = "SELECT two_factor_enabled FROM users WHERE id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 1) {
            $user = $result->fetch_assoc();
            return (bool)($user['two_factor_enabled'] ?? 0);
        }
        
        return false;
    }
}
?>