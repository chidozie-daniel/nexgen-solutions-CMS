<?php
/**
 * Email Configuration for NexGen HRMS
 * 
 * This file contains the email sending configuration.
 * Loads SMTP settings from environment variables and database settings with fallbacks.
 */

// Load environment variables
$root_dir = dirname(dirname(__FILE__));
if (file_exists($root_dir . '/config/env.php')) {
    require_once $root_dir . '/config/env.php';
}

// Load Composer autoloader if it exists
if (file_exists(__DIR__ . '/../vendor/autoload.php')) {
    require_once __DIR__ . '/../vendor/autoload.php';
}

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

// Prevent direct access
if (!defined('APP_ENTRY_POINT') && php_sapi_name() !== 'cli') {
    http_response_code(403);
    exit('Direct access not allowed');
}

/**
 * Email Sending Configuration
 * 
 * These are now loaded from environment variables with fallbacks to database settings.
 * Define defaults here for development/testing.
 */
define('ENABLE_REAL_EMAIL', getEnv('ENABLE_REAL_EMAIL', true));
define('SMTP_HOST', getEnv('SMTP_HOST', 'smtp.gmail.com'));
define('SMTP_PORT', getEnv('SMTP_PORT', 587));
define('SMTP_USERNAME', getEnv('SMTP_USERNAME', ''));
define('SMTP_PASSWORD', getEnv('SMTP_PASSWORD', ''));
define('SMTP_FROM_EMAIL', getEnv('SMTP_FROM_EMAIL', ''));
define('SMTP_FROM_NAME', getEnv('SMTP_FROM_NAME', 'NexGen HRMS'));
define('SMTP_SECURE', getEnv('SMTP_SECURE', 'tls'));

/**
 * Send email notification
 * 
 * @param string $to Recipient email
 * @param string $subject Email subject
 * @param string $message Email body (plain text or HTML)
 * @param string|null $from From email address
 * @param bool|null $isHtml Whether the message is HTML (auto-detect if not specified)
 * @return bool Success status
 */
function sendEmailNotification($to, $subject, $message, $from = null, $isHtml = null) {
    // Auto-detect HTML if not specified
    if ($isHtml === null) {
        $isHtml = stripos(trim($message), '<') === 0;
    }
    // Log the email attempt
    error_log("[" . date('Y-m-d H:i:s') . "] Email attempt to: $to | Subject: $subject");
    
    // Check if real email sending is enabled
    $enable_real_email = getEnv('ENABLE_REAL_EMAIL', false);
    
    if (!$enable_real_email) {
        // Development mode: Log full email content
        error_log("========================================");
        error_log("[DEV MODE] Email sending is DISABLED");
        error_log("  To: $to");
        error_log("  Subject: $subject");
        error_log("  Format: " . ($isHtml ? 'HTML' : 'Plain Text'));
        error_log("  Message:");
        error_log(str_repeat("-", 40));
        error_log(substr($message, 0, 500)); // Log first 500 chars
        error_log(str_repeat("-", 40));
        error_log("========================================");
        return true; 
    }
    
    // Get database connection for fallback settings
    $conn = null;
    if (function_exists('getDBConnection')) {
        try {
            $conn = getDBConnection();
        } catch (Exception $e) {
            $conn = null;
        }
    }
    
    // Load SMTP settings from environment variables (priority 1), then database (priority 2), then defaults (priority 3)
    $smtp_host = getEnv('SMTP_HOST', null);
    if (!$smtp_host && function_exists('getSettingValue') && $conn) {
        $smtp_host = getSettingValue('smtp_host', 'smtp.gmail.com', $conn);
    }
    if (!$smtp_host) $smtp_host = 'smtp.gmail.com';
    
    $smtp_port = getEnv('SMTP_PORT', null);
    if (!$smtp_port && function_exists('getSettingValue') && $conn) {
        $smtp_port = getSettingValue('smtp_port', '587', $conn);
    }
    if (!$smtp_port) $smtp_port = 587;
    $smtp_port = (int)$smtp_port;
    
    $smtp_user = getEnv('SMTP_USERNAME', null);
    if (!$smtp_user && function_exists('getSettingValue') && $conn) {
        $smtp_user = getSettingValue('smtp_user', '', $conn);
    }
    if (!$smtp_user) $smtp_user = '';
    
    $smtp_pass = getEnv('SMTP_PASSWORD', null);
    if (!$smtp_pass && function_exists('getSettingValue') && $conn) {
        $smtp_pass = getSettingValue('smtp_pass', '', $conn);
    }
    if (!$smtp_pass) $smtp_pass = '';
    
    $smtp_from = getEnv('SMTP_FROM_EMAIL', null);
    if (!$smtp_from && function_exists('getSettingValue') && $conn) {
        $smtp_from = getSettingValue('smtp_from_email', '', $conn);
    }
    if (!$smtp_from) $smtp_from = $smtp_user;
    
    $smtp_name = getEnv('SMTP_FROM_NAME', null);
    if (!$smtp_name && function_exists('getSettingValue') && $conn) {
        $smtp_name = getSettingValue('smtp_from_name', 'NexGen HRMS', $conn);
    }
    if (!$smtp_name) $smtp_name = 'NexGen HRMS';
    
    $smtp_secure = getEnv('SMTP_SECURE', null);
    if (!$smtp_secure && function_exists('getSettingValue') && $conn) {
        $smtp_secure = getSettingValue('smtp_secure', 'tls', $conn);
    }
    if (!$smtp_secure) $smtp_secure = 'tls';
    
    // Close connection if we opened it
    if ($conn) {
        $conn->close();
    }
    
    // Production mode: Try to send real email using PHPMailer
    if (class_exists('PHPMailer\PHPMailer\PHPMailer')) {
        $mail = new PHPMailer(true);
        try {
            // Validate SMTP credentials
            if (empty($smtp_user) || empty($smtp_pass)) {
                error_log("[FAIL] SMTP credentials are not configured (empty SMTP_USERNAME or SMTP_PASSWORD)");
                logEmailFailure($to, $subject, 'SMTP credentials not configured');
                return false;
            }

            $mail->isSMTP();
            $mail->Host       = $smtp_host;
            $mail->SMTPAuth   = true;
            $mail->Username   = $smtp_user;
            $mail->Password   = $smtp_pass;
            
            // Security Protocol
            if ($smtp_secure === 'ssl') {
                $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
            } elseif ($smtp_secure === 'tls') {
                $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            } else {
                $mail->SMTPAuth = false;
                $mail->SMTPSecure = '';
            }
            
            $mail->Port = $smtp_port;

            // SSL Certificate Bypass (Necessary for local XAMPP setups)
            $mail->SMTPOptions = array(
                'ssl' => array(
                    'verify_peer' => false,
                    'verify_peer_name' => false,
                    'allow_self_signed' => true
                )
            );

            // Timeouts and Encoding
            $mail->Timeout = 30;
            $mail->CharSet = 'UTF-8';

            // Recipients
            $mail->setFrom($smtp_from, $smtp_name);
            $mail->addAddress($to);
            $mail->addReplyTo($smtp_from, $smtp_name);

            // Content
            $mail->isHTML($isHtml);
            $mail->Subject = $subject;
            $mail->Body    = $message;

            $mail->send();
            error_log("[OK] Email sent via PHPMailer to: $to");
            logEmailSuccess($to, $subject);
            return true;
        } catch (Exception $e) {
            error_log("[FAIL] PHPMailer Error: {$mail->ErrorInfo}");
            logEmailFailure($to, $subject, $mail->ErrorInfo);
            return false;
        }
    } else {
        // Fallback to PHP mail() function
        $headers = array(
            'From: ' . ($from ?? $smtp_from),
            'Reply-To: ' . ($from ?? $smtp_from),
            'X-Mailer: PHP/' . phpversion(),
        );
        
        if ($isHtml) {
            $headers[] = 'Content-Type: text/html; charset=UTF-8';
        } else {
            $headers[] = 'Content-Type: text/plain; charset=UTF-8';
        }
        
        if (@mail($to, $subject, $message, implode("\r\n", $headers))) {
            error_log("[OK] Email sent via mail() to: $to");
            logEmailSuccess($to, $subject);
            return true;
        }
    }
    
    error_log("[FAIL] Could not send email to: $to");
    logEmailFailure($to, $subject, 'Unknown error');
    return false;
}

/**
 * Log email failure to database for audit purposes
 *
 * @param string $to Recipient email
 * @param string $subject Email subject
 * @param string $reason Failure reason
 */
function logEmailFailure($to, $subject, $reason) {
    try {
        if (function_exists('getDBConnection')) {
            $conn = getDBConnection();
            $sql = "INSERT INTO email_logs (recipient, subject, status, error_message, sent_at)
                    VALUES (?, ?, 'failed', ?, NOW())";
            $stmt = $conn->prepare($sql);
            if ($stmt) {
                $stmt->bind_param("sss", $to, $subject, $reason);
                $stmt->execute();
                $stmt->close();
            }
            $conn->close();
        }
    } catch (Exception $e) {
        // Silently fail - don't interrupt email sending
        error_log("[WARN] Could not log email failure: " . $e->getMessage());
    }
}

/**
 * Log successful email to database for audit purposes
 *
 * @param string $to Recipient email
 * @param string $subject Email subject
 */
function logEmailSuccess($to, $subject) {
    try {
        if (function_exists('getDBConnection')) {
            $conn = getDBConnection();
            $sql = "INSERT INTO email_logs (recipient, subject, status, sent_at)
                    VALUES (?, ?, 'success', NOW())";
            $stmt = $conn->prepare($sql);
            if ($stmt) {
                $stmt->bind_param("ss", $to, $subject);
                $stmt->execute();
                $stmt->close();
            }
            $conn->close();
        }
    } catch (Exception $e) {
        // Silently fail
        error_log("[WARN] Could not log email success: " . $e->getMessage());
    }
}
?>
