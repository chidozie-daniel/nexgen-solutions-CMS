<?php
$base_dir = dirname(dirname(__FILE__));
require_once $base_dir . '/config/database.php';

class Auth {
    private $conn;
    
    public function __construct() {
        $this->conn = getDBConnection();
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
}
?>