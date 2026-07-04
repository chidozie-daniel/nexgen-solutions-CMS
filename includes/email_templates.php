<?php
/**
 * Email Templates for NexGen HRMS
 * 
 * This file contains email template functions for transactional emails.
 * All templates return HTML formatted emails with consistent branding.
 */

// Prevent direct access
if (!defined('APP_ENTRY_POINT') && php_sapi_name() !== 'cli') {
    http_response_code(403);
    exit('Direct access not allowed');
}

/**
 * Get the base URL for the application
 */
function getAppBaseUrl() {
    $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $script = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME']));
    $script = rtrim($script, '/');
    if ($script === '/' || $script === '\\') {
        $script = '';
    }
    return $protocol . '://' . $host . $script;
}

/**
 * Get company branding and information
 */
function getCompanyInfo() {
    return [
        'name' => getSetting('company_name', 'NexGen Solutions'),
        'email' => getSetting('company_email', 'info@nexgensolutions.com'),
        'phone' => getSetting('company_phone', '+1 (555) 123-4567'),
        'address' => getSetting('company_address', '123 Tech Street, San Francisco, CA 94107'),
        'url' => getAppBaseUrl()
    ];
}

/**
 * Wrap content in the standard email template
 * 
 * @param string $content The email body content
 * @param string $subject Email subject for the header
 * @return string Complete HTML email
 */
function wrapEmailTemplate($content, $subject) {
    $company = getCompanyInfo();
    $base_url = $company['url'];
    
    return '<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>' . htmlspecialchars($subject) . '</title>
    <style>
        body {
            font-family: Arial, Helvetica, sans-serif;
            line-height: 1.6;
            color: #333333;
            margin: 0;
            padding: 0;
            background-color: #f4f4f4;
        }
        .email-container {
            max-width: 600px;
            margin: 20px auto;
            background-color: #ffffff;
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .email-header {
            background: linear-gradient(135deg, #0d6efd 0%, #0056b3 100%);
            color: #ffffff;
            padding: 30px;
            text-align: center;
        }
        .email-header h1 {
            margin: 0;
            font-size: 24px;
            font-weight: 600;
        }
        .email-body {
            padding: 30px;
            background-color: #ffffff;
        }
        .email-footer {
            background-color: #f8f9fa;
            padding: 20px 30px;
            border-top: 1px solid #dee2e6;
            text-align: center;
            font-size: 12px;
            color: #6c757d;
        }
        .email-footer p {
            margin: 5px 0;
        }
        .button {
            display: inline-block;
            padding: 12px 30px;
            background-color: #0d6efd;
            color: #ffffff !important;
            text-decoration: none;
            border-radius: 5px;
            font-weight: 600;
            margin: 15px 0;
        }
        .button:hover {
            background-color: #0056b3;
        }
        .info-box {
            background-color: #e7f3ff;
            border-left: 4px solid #0d6efd;
            padding: 15px;
            margin: 20px 0;
            border-radius: 4px;
        }
        .success-box {
            background-color: #d4edda;
            border-left: 4px solid #28a745;
            padding: 15px;
            margin: 20px 0;
            border-radius: 4px;
        }
        table.info-table {
            width: 100%;
            border-collapse: collapse;
            margin: 15px 0;
        }
        table.info-table td {
            padding: 10px;
            border-bottom: 1px solid #dee2e6;
        }
        table.info-table td:first-child {
            font-weight: 600;
            width: 35%;
            color: #495057;
        }
        @media only screen and (max-width: 600px) {
            .email-container {
                width: 100% !important;
                margin: 10px auto !important;
            }
            .email-body {
                padding: 20px !important;
            }
        }
    </style>
</head>
<body>
    <div class="email-container">
        <div class="email-header">
            <h1>' . htmlspecialchars($company['name']) . '</h1>
        </div>
        <div class="email-body">
            ' . $content . '
        </div>
        <div class="email-footer">
            <p><strong>' . htmlspecialchars($company['name']) . '</strong></p>
            <p>' . htmlspecialchars($company['address']) . '</p>
            <p>Phone: ' . htmlspecialchars($company['phone']) . ' | Email: ' . htmlspecialchars($company['email']) . '</p>
            <p style="margin-top: 15px; font-size: 11px;">This is an automated message. Please do not reply directly to this email.</p>
            <p style="font-size: 11px;">&copy; ' . date('Y') . ' ' . htmlspecialchars($company['name']) . '. All rights reserved.</p>
        </div>
    </div>
</body>
</html>';
}

/**
 * Send inquiry confirmation email to the visitor
 * 
 * @param string $name Visitor name
 * @param string $email Visitor email
 * @param string $service Service inquired about
 * @param string $message Original message
 * @param int $inquiry_id Inquiry ID for reference
 * @return bool Success status
 */
function sendInquiryConfirmationEmail($name, $email, $service, $message, $inquiry_id = null) {
    $company = getCompanyInfo();
    $subject = 'Thank you for contacting ' . $company['name'];
    
    $content = '
        <h2 style="color: #0d6efd; margin-top: 0;">We\'ve Received Your Message</h2>
        <p>Dear ' . htmlspecialchars($name) . ',</p>
        
        <div class="success-box">
            <p style="margin: 0;">
                <i class="bi bi-check-circle" style="color: #28a745; font-size: 20px;">✓</i>
                <strong>Thank you for reaching out!</strong> Your inquiry has been successfully received and our team will review it promptly.
            </p>
        </div>
        
        <p>Here\'s a summary of your message:</p>
        
        <table class="info-table">
            <tr>
                <td>Service:</td>
                <td>' . htmlspecialchars($service) . '</td>
            </tr>
            <tr>
                <td>Reference ID:</td>
                <td>' . ($inquiry_id ? '#' . $inquiry_id : 'Pending') . '</td>
            </tr>
            <tr>
                <td>Date Submitted:</td>
                <td>' . date('F d, Y \a\t g:i A') . '</td>
            </tr>
        </table>
        
        <div class="info-box">
            <p style="margin: 0;">
                <strong>What happens next?</strong><br>
                • Our team will review your inquiry within 1 business day<br>
                • A representative will contact you via email or phone<br>
                • We\'ll provide detailed information about your requested service
            </p>
        </div>
        
        <p>If you have any urgent questions in the meantime, please don\'t hesitate to contact us:</p>
        
        <table class="info-table">
            <tr>
                <td>Phone:</td>
                <td>' . htmlspecialchars($company['phone']) . '</td>
            </tr>
            <tr>
                <td>Email:</td>
                <td>' . htmlspecialchars($company['email']) . '</td>
            </tr>
        </table>
        
        <p style="margin-top: 25px;">We appreciate your interest in our services and look forward to assisting you.</p>
        
        <p style="margin-top: 25px;">
            Best regards,<br>
            <strong>The ' . htmlspecialchars($company['name']) . ' Team</strong>
        </p>
    ';
    
    $email_body = wrapEmailTemplate($content, $subject);
    
    return sendEmailNotification($email, $subject, $email_body, null, true);
}

/**
 * Send newsletter welcome email to new subscriber
 * 
 * @param string $email Subscriber email
 * @param string $name Subscriber name (optional)
 * @return bool Success status
 */
function sendNewsletterWelcomeEmail($email, $name = '') {
    $company = getCompanyInfo();
    $display_name = $name ? $name : 'Subscriber';
    $subject = 'Welcome to ' . $company['name'] . ' Newsletter!';
    
    $content = '
        <h2 style="color: #0d6efd; margin-top: 0;">Welcome to Our Community! 🎉</h2>
        <p>Dear ' . htmlspecialchars($display_name) . ',</p>
        
        <div class="success-box">
            <p style="margin: 0;">
                <strong>You\'re in!</strong> Thank you for subscribing to our newsletter. You\'ll now receive exclusive tech insights, industry updates, and company news.
            </p>
        </div>
        
        <h3 style="color: #495057;">What You Can Expect:</h3>
        <ul style="line-height: 2;">
            <li>📊 Monthly tech insights and industry trends</li>
            <li>🚀 Product updates and new service announcements</li>
            <li>💡 Expert tips and best practices</li>
            <li>📅 Invitations to webinars and events</li>
        </ul>
        
        <div class="info-box">
            <p style="margin: 0;">
                <strong>Want to customize your preferences?</strong><br>
                You can update your subscription preferences or unsubscribe at any time by contacting us.
            </p>
        </div>
        
        <p>In the meantime, feel free to explore our services:</p>
        
        <ul style="line-height: 2;">
            <li>IT Consulting</li>
            <li>Cloud Services</li>
            <li>Cybersecurity</li>
            <li>Software Development</li>
            <li>Data Analytics</li>
            <li>Digital Transformation</li>
        </ul>
        
        <p style="margin-top: 25px;">
            <a href="' . htmlspecialchars($company['url']) . '" class="button">Visit Our Website</a>
        </p>
        
        <p style="margin-top: 25px;">
            Welcome aboard!<br>
            <strong>The ' . htmlspecialchars($company['name']) . ' Team</strong>
        </p>
    ';
    
    $email_body = wrapEmailTemplate($content, $subject);
    
    return sendEmailNotification($email, $subject, $email_body, null, true);
}

/**
 * Update subscriber email count after sending
 * 
 * @param string $email Subscriber email
 * @return bool Success status
 */
function updateSubscriberEmailCount($email) {
    try {
        $conn = getDBConnection();
        $sql = "UPDATE newsletter_subscribers 
                SET total_emails_sent = total_emails_sent + 1, 
                    last_email_sent = NOW() 
                WHERE email = ? AND status = 'active'";
        $stmt = $conn->prepare($sql);
        if ($stmt) {
            $stmt->bind_param("s", $email);
            $result = $stmt->execute();
            $stmt->close();
            return $result;
        }
        return false;
    } catch (Exception $e) {
        error_log("[WARN] Could not update subscriber count: " . $e->getMessage());
        return false;
    }
}
?>
