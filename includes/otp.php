<?php
// Prevent direct access to this file
if (!defined('APP_ENTRY_POINT')) {
    http_response_code(403);
    exit('Direct access not allowed');
}

/**
 * OTP (One-Time Password) Management Class
 * 
 * Handles OTP generation, verification, and security for:
 * - Password reset verification
 * - Email verification during registration
 * - Two-factor authentication (2FA)
 * - Sensitive action confirmation
 * 
 * @package NexGen_HRMS
 * @author NexGen HRMS Team
 * @version 1.0.0
 */
class OTPManager {
    private $conn;
    private $otp_length;
    private $otp_expiry_minutes;
    private $max_attempts;
    
    // OTP Configuration - Rate limiting
    // Production: 3 requests per 5 minutes
    // Development: 10 requests per 2 minutes (for testing)
    private const RATE_LIMIT_MINUTES = 2;
    private const RATE_LIMIT_MAX_REQUESTS = 10;
    
    // OTP Purposes
    const PURPOSE_PASSWORD_RESET = 'password_reset';
    const PURPOSE_EMAIL_VERIFICATION = 'email_verification';
    const PURPOSE_LOGIN_2FA = 'login_2fa';
    const PURPOSE_SENSITIVE_ACTION = 'sensitive_action';
    
    public function __construct() {
        $this->conn = getDBConnection();
        
        // Load configurable OTP settings from database
        $this->otp_length = intval(getSettingValue('otp_length', 6, $this->conn));
        $this->otp_expiry_minutes = intval(getSettingValue('otp_expiry_minutes', 10, $this->conn));
        $this->max_attempts = intval(getSettingValue('otp_max_attempts', 3, $this->conn));
    }
    
    /**
     * Get OTP length setting
     */
    public function getOTPLength() {
        return $this->otp_length;
    }
    
    /**
     * Get OTP expiry minutes setting
     */
    public function getOTPExpiryMinutes() {
        return $this->otp_expiry_minutes;
    }
    
    /**
     * Get max OTP attempts setting
     */
    public function getMaxAttempts() {
        return $this->max_attempts;
    }
    
    /**
     * Generate a random OTP code
     * 
     * @param int $length Length of the OTP code
     * @return string Generated OTP code
     */
    public function generateOTPCode($length = null) {
        if ($length === null) {
            $length = $this->otp_length;
        }
        $otp = '';
        for ($i = 0; $i < $length; $i++) {
            $otp .= random_int(0, 9);
        }
        return $otp;
    }
    
    /**
     * Generate and store OTP for a specific purpose
     * 
     * @param string $email Email address to send OTP to
     * @param string $purpose Purpose of the OTP
     * @param int|null $user_id User ID (if applicable)
     * @param int $expiryMinutes OTP expiry time in minutes (null uses default from settings)
     * @return array ['success' => bool, 'otp_id' => int|null, 'message' => string]
     */
    public function generateAndStoreOTP($email, $purpose, $user_id = null, $expiryMinutes = null) {
        if ($expiryMinutes === null) {
            $expiryMinutes = $this->otp_expiry_minutes;
        }
        try {
            // Check rate limiting
            $rateLimitCheck = $this->checkRateLimit($email, $purpose);
            if (!$rateLimitCheck['allowed']) {
                return [
                    'success' => false,
                    'otp_id' => null,
                    'message' => $rateLimitCheck['message'],
                    'retry_after' => $rateLimitCheck['retry_after'] ?? null
                ];
            }
            
            // Invalidate any existing unused OTPs for the same email and purpose
            $this->invalidateExistingOTPs($email, $purpose, $user_id);
            
            // Generate OTP
            $otp_code = $this->generateOTPCode();
            $expires_at = date('Y-m-d H:i:s', strtotime("+{$expiryMinutes} minutes"));
            $ip_address = $_SERVER['REMOTE_ADDR'] ?? null;
            
            // Store OTP in database
            $sql = "INSERT INTO otp_codes (user_id, email, otp_code, purpose, expires_at, max_attempts, ip_address)
                    VALUES (?, ?, ?, ?, ?, ?, ?)";
            
            $stmt = $this->conn->prepare($sql);
            $max_attempts = $this->max_attempts;
            $stmt->bind_param("issssii", $user_id, $email, $otp_code, $purpose, $expires_at, $max_attempts, $ip_address);
            
            if ($stmt->execute()) {
                $otp_id = $this->conn->insert_id;
                
                // Log the OTP generation (but not the actual code)
                error_log("OTP generated for {$email} - Purpose: {$purpose} - OTP ID: {$otp_id}");
                
                return [
                    'success' => true,
                    'otp_id' => $otp_id,
                    'message' => 'OTP generated successfully',
                    'otp_code' => $otp_code, // Return only for email sending, not to client
                    'expires_at' => $expires_at
                ];
            } else {
                error_log("Failed to store OTP: " . $this->conn->error);
                return [
                    'success' => false,
                    'otp_id' => null,
                    'message' => 'Failed to generate OTP. Please try again.'
                ];
            }
        } catch (Exception $e) {
            error_log("OTP generation error: " . $e->getMessage());
            return [
                'success' => false,
                'otp_id' => null,
                'message' => 'An error occurred. Please try again.'
            ];
        }
    }
    
    /**
     * Verify OTP code
     * 
     * @param string $email Email address
     * @param string $otp_code OTP code to verify
     * @param string $purpose Purpose of the OTP
     * @return array ['success' => bool, 'message' => string, 'data' => array|null]
     */
    public function verifyOTP($email, $otp_code, $purpose) {
        try {
            // Get the most recent unused OTP for this email and purpose
            $sql = "SELECT * FROM otp_codes 
                    WHERE email = ? AND otp_code = ? AND purpose = ? 
                    AND is_used = 0 AND expires_at > NOW()
                    ORDER BY created_at DESC LIMIT 1";
            
            $stmt = $this->conn->prepare($sql);
            $stmt->bind_param("sss", $email, $otp_code, $purpose);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows === 0) {
                // Check if OTP exists but is expired
                $this->checkExpiredOrUsedOTP($email, $otp_code, $purpose);
                
                return [
                    'success' => false,
                    'message' => 'Invalid or expired OTP code.',
                    'data' => null
                ];
            }
            
            $otp_record = $result->fetch_assoc();
            
            // Check attempt limit
            if ($otp_record['attempts'] >= $otp_record['max_attempts']) {
                return [
                    'success' => false,
                    'message' => 'Maximum verification attempts exceeded. Please request a new OTP.',
                    'data' => null
                ];
            }
            
            // Increment attempt counter
            $this->incrementOTPAttempts($otp_record['id']);
            
            // Mark OTP as used
            $this->markOTPAsUsed($otp_record['id']);
            
            return [
                'success' => true,
                'message' => 'OTP verified successfully.',
                'data' => [
                    'user_id' => $otp_record['user_id'],
                    'email' => $otp_record['email'],
                    'purpose' => $otp_record['purpose']
                ]
            ];
        } catch (Exception $e) {
            error_log("OTP verification error: " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'An error occurred during verification.',
                'data' => null
            ];
        }
    }
    
    /**
     * Resend OTP (generate a new one)
     * 
     * @param string $email Email address
     * @param string $purpose Purpose of the OTP
     * @param int|null $user_id User ID (if applicable)
     * @return array ['success' => bool, 'message' => string, 'otp_code' => string|null]
     */
    public function resendOTP($email, $purpose, $user_id = null) {
        // Invalidate previous OTPs
        $this->invalidateExistingOTPs($email, $purpose, $user_id);
        
        // Generate new OTP
        return $this->generateAndStoreOTP($email, $purpose, $user_id);
    }
    
    /**
     * Check rate limiting for OTP requests
     * 
     * @param string $email Email address
     * @param string $purpose Purpose of the OTP
     * @return array ['allowed' => bool, 'message' => string, 'retry_after' => int|null]
     */
    private function checkRateLimit($email, $purpose) {
        $rate_limit_minutes = self::RATE_LIMIT_MINUTES;
        $sql = "SELECT COUNT(*) as count FROM otp_codes 
                WHERE email = ? AND purpose = ? 
                AND created_at > DATE_SUB(NOW(), INTERVAL {$rate_limit_minutes} MINUTE)";
        
        $stmt = $this->conn->prepare($sql);
        $stmt->bind_param("ss", $email, $purpose);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        
        $count = (int)($row['count'] ?? 0);
        
        if ($count >= self::RATE_LIMIT_MAX_REQUESTS) {
            return [
                'allowed' => false,
                'message' => 'Too many OTP requests. Please wait ' . self::RATE_LIMIT_MINUTES . ' minutes before trying again.',
                'retry_after' => self::RATE_LIMIT_MINUTES * 60 // in seconds
            ];
        }
        
        return [
            'allowed' => true,
            'message' => 'Rate limit check passed'
        ];
    }
    
    /**
     * Invalidate existing unused OTPs for the same email and purpose
     * 
     * @param string $email Email address
     * @param string $purpose Purpose of the OTP
     * @param int|null $user_id User ID (if applicable)
     */
    private function invalidateExistingOTPs($email, $purpose, $user_id = null) {
        $sql = "UPDATE otp_codes SET is_used = 1 
                WHERE email = ? AND purpose = ? AND is_used = 0";
        
        $params = [$email, $purpose];
        $types = "ss";
        
        if ($user_id !== null) {
            $sql .= " OR (user_id = ? AND purpose = ? AND is_used = 0)";
            $params[] = $user_id;
            $params[] = $purpose;
            $types .= "is";
        }
        
        $stmt = $this->conn->prepare($sql);
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
    }
    
    /**
     * Check if OTP is expired or already used
     * 
     * @param string $email Email address
     * @param string $otp_code OTP code
     * @param string $purpose Purpose
     * @return string Status message
     */
    private function checkExpiredOrUsedOTP($email, $otp_code, $purpose) {
        $sql = "SELECT * FROM otp_codes 
                WHERE email = ? AND otp_code = ? AND purpose = ? 
                ORDER BY created_at DESC LIMIT 1";
        
        $stmt = $this->conn->prepare($sql);
        $stmt->bind_param("sss", $email, $otp_code, $purpose);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 0) {
            return 'OTP not found';
        }
        
        $otp_record = $result->fetch_assoc();
        
        if ($otp_record['is_used'] == 1) {
            return 'OTP has already been used';
        }
        
        if (strtotime($otp_record['expires_at']) < time()) {
            return 'OTP has expired';
        }
        
        return 'Unknown error';
    }
    
    /**
     * Increment OTP attempt counter
     * 
     * @param int $otp_id OTP record ID
     */
    private function incrementOTPAttempts($otp_id) {
        $sql = "UPDATE otp_codes SET attempts = attempts + 1 WHERE id = ?";
        $stmt = $this->conn->prepare($sql);
        $stmt->bind_param("i", $otp_id);
        $stmt->execute();
    }
    
    /**
     * Mark OTP as used
     * 
     * @param int $otp_id OTP record ID
     */
    private function markOTPAsUsed($otp_id) {
        $sql = "UPDATE otp_codes 
                SET is_used = 1, used_at = NOW() 
                WHERE id = ?";
        $stmt = $this->conn->prepare($sql);
        $stmt->bind_param("i", $otp_id);
        $stmt->execute();
    }
    
    /**
     * Get OTP record by ID
     * 
     * @param int $otp_id OTP record ID
     * @return array|null OTP record or null if not found
     */
    public function getOTPById($otp_id) {
        $sql = "SELECT * FROM otp_codes WHERE id = ?";
        $stmt = $this->conn->prepare($sql);
        $stmt->bind_param("i", $otp_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 1) {
            return $result->fetch_assoc();
        }
        
        return null;
    }
    
    /**
     * Clean up expired OTPs (can be run periodically)
     * 
     * @return int Number of deleted records
     */
    public function cleanupExpiredOTPs() {
        $sql = "DELETE FROM otp_codes WHERE expires_at < NOW()";
        $result = $this->conn->query($sql);
        
        return $this->conn->affected_rows;
    }
    
    /**
     * Get the latest active OTP code for an email and purpose
     * (Mainly for development/debugging)
     * 
     * @param string $email Email address
     * @param string $purpose Purpose
     * @return string|null OTP code or null if not found
     */
    public function getLatestOTPCode($email, $purpose) {
        $sql = "SELECT otp_code FROM otp_codes 
                WHERE email = ? AND purpose = ? 
                AND is_used = 0 AND expires_at > NOW()
                ORDER BY created_at DESC LIMIT 1";
        
        $stmt = $this->conn->prepare($sql);
        $stmt->bind_param("ss", $email, $purpose);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 1) {
            $row = $result->fetch_assoc();
            return $row['otp_code'];
        }
        
        return null;
    }

    /**
     * Send OTP via email
     * 
     * @param string $email Recipient email
     * @param string $otp_code OTP code
     * @param string $purpose Purpose of the OTP
     * @param array $user_data User data (name, etc.)
     * @param int $expiryMinutes OTP expiry time in minutes (null uses default from settings)
     * @return bool Success status
     */
    public function sendOTPEmail($email, $otp_code, $purpose, $user_data = [], $expiryMinutes = null) {
        if ($expiryMinutes === null) {
            $expiryMinutes = $this->otp_expiry_minutes;
        }
        $subject = '';
        $message = '';
        
        switch ($purpose) {
            case self::PURPOSE_PASSWORD_RESET:
                $subject = 'Password Reset OTP - NexGen HRMS';
                $message = $this->getPasswordResetEmailTemplate($otp_code, $user_data, $expiryMinutes);
                break;
                
            case self::PURPOSE_EMAIL_VERIFICATION:
                $subject = 'Email Verification OTP - NexGen HRMS';
                $message = $this->getEmailVerificationTemplate($otp_code, $user_data, $expiryMinutes);
                break;
                
            case self::PURPOSE_LOGIN_2FA:
                $subject = 'Login Verification Code - NexGen HRMS';
                $message = $this->get2FAEmailTemplate($otp_code, $user_data, $expiryMinutes);
                break;
                
            case self::PURPOSE_SENSITIVE_ACTION:
                $subject = 'Security Verification Code - NexGen HRMS';
                $message = $this->getSensitiveActionEmailTemplate($otp_code, $user_data, $expiryMinutes);
                break;
                
            default:
                $subject = 'Your OTP Code - NexGen HRMS';
                $message = $this->getGenericOTPEmailTemplate($otp_code, $user_data, $expiryMinutes);
        }
        
        // Use the existing email notification function
        return sendEmailNotification($email, $subject, $message);
    }
    
    /**
     * Email template for password reset
     */
    private function getPasswordResetEmailTemplate($otp_code, $user_data, $expiryMinutes) {
        $name = $user_data['name'] ?? 'User';
        
        return "Dear {$name},\n\n" .
               "You have requested to reset your password for your NexGen HRMS account.\n\n" .
               "Your One-Time Password (OTP) is: {$otp_code}\n\n" .
               "This code will expire in {$expiryMinutes} minutes.\n\n" .
               "If you did not request this password reset, please ignore this email or contact support if you have concerns.\n\n" .
               "For security reasons, do not share this code with anyone.\n\n" .
               "Best regards,\n" .
               "NexGen HRMS Team\n" .
               "© " . date('Y') . " NexGen Solutions. All rights reserved.";
    }
    
    /**
     * Email template for email verification
     */
    private function getEmailVerificationTemplate($otp_code, $user_data, $expiryMinutes) {
        $name = $user_data['name'] ?? 'User';
        
        return "Dear {$name},\n\n" .
               "Welcome to NexGen HRMS! Please verify your email address using the following One-Time Password (OTP):\n\n" .
               "Your verification code: {$otp_code}\n\n" .
               "This code will expire in {$expiryMinutes} minutes.\n\n" .
               "If you did not create an account with us, please ignore this email.\n\n" .
               "Best regards,\n" .
               "NexGen HRMS Team\n" .
               "© " . date('Y') . " NexGen Solutions. All rights reserved.";
    }
    
    /**
     * Email template for 2FA login
     */
    private function get2FAEmailTemplate($otp_code, $user_data, $expiryMinutes) {
        $name = $user_data['name'] ?? 'User';
        
        return "Dear {$name},\n\n" .
               "A login attempt was made to your NexGen HRMS account. Please use the following verification code to complete your login:\n\n" .
               "Your verification code: {$otp_code}\n\n" .
               "This code will expire in {$expiryMinutes} minutes.\n\n" .
               "If you did not attempt to login, please contact support immediately as your account security may be compromised.\n\n" .
               "Best regards,\n" .
               "NexGen HRMS Team\n" .
               "© " . date('Y') . " NexGen Solutions. All rights reserved.";
    }
    
    /**
     * Email template for sensitive actions
     */
    private function getSensitiveActionEmailTemplate($otp_code, $user_data, $expiryMinutes) {
        $name = $user_data['name'] ?? 'User';
        
        return "Dear {$name},\n\n" .
               "A sensitive action is being performed on your NexGen HRMS account. Please use the following verification code:\n\n" .
               "Your verification code: {$otp_code}\n\n" .
               "This code will expire in {$expiryMinutes} minutes.\n\n" .
               "If you did not initiate this action, please contact support immediately.\n\n" .
               "Best regards,\n" .
               "NexGen HRMS Team\n" .
               "© " . date('Y') . " NexGen Solutions. All rights reserved.";
    }
    
    /**
     * Generic OTP email template
     */
    private function getGenericOTPEmailTemplate($otp_code, $user_data, $expiryMinutes) {
        $name = $user_data['name'] ?? 'User';
        
        return "Dear {$name},\n\n" .
               "Your NexGen HRMS verification code is: {$otp_code}\n\n" .
               "This code will expire in {$expiryMinutes} minutes.\n\n" .
               "For security reasons, do not share this code with anyone.\n\n" .
               "Best regards,\n" .
               "NexGen HRMS Team\n" .
               "© " . date('Y') . " NexGen Solutions. All rights reserved.";
    }
}
?>
