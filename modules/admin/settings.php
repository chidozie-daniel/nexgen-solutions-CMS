<?php
// Define entry point constant
define('APP_ENTRY_POINT', true);

require_once '../../includes/auth.php';
require_once '../../includes/functions.php';

// Check login status and require admin role
Auth::requireRole('admin');

$page_title = 'System Settings';
$conn = getDBConnection();

// Handle settings update
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['save_settings'])) {
    // Verify CSRF token
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        setFlash('danger', 'Invalid security token. Please try again.');
        header('Location: settings.php');
        exit();
    }
    
    // Process all posted settings
    $settings_to_save = $_POST;
    unset($settings_to_save['save_settings'], $settings_to_save['csrf_token'], $settings_to_save['setting_key']);

    // List of checkbox-based settings that should be '0' if not present in POST
    $checkbox_keys = [
        'notify_leave', 'notify_task', 'notify_payroll', 'notify_inquiry', 
        'notify_announcement', 'notify_birthday', 'notify_project', 'carry_forward', 
        'sick_leave_certificate', 'advance_leave_application',
        'maintenance_mode', 'two_factor_auth', 'ip_restriction', 'login_attempts'
    ];

    // Merge checkboxes into settings (set to 0 if not checked)
    foreach ($checkbox_keys as $check_key) {
        $settings_to_save[$check_key] = isset($_POST[$check_key]) ? '1' : '0';
    }

    $settings_save_ok = true;
    foreach ($settings_to_save as $key => $value) {
        // Basic validation for keys
        if (!preg_match('/^[a-z0-9_]+$/i', $key)) continue;

        // Skip fields that are not settings
        if ($key === 'test_email') continue;

        // Validation for specific types
        $numeric_keys = ['leave_annual_days', 'leave_sick_days', 'leave_casual_days', 'payroll_day', 'tax_percentage', 'overtime_rate_multiplier', 'smtp_port', 'otp_length', 'otp_expiry_minutes', 'otp_max_attempts', 'progress_color_low_threshold', 'progress_color_mid_threshold'];
        $email_keys = ['company_email', 'email_from_address', 'smtp_user', 'smtp_from_email'];

        if (in_array($key, $numeric_keys, true)) {
            $value = is_numeric($value) ? (string)$value : '0';
        } elseif (in_array($key, $email_keys, true)) {
            $value = trim($value);
        } else {
            $value = sanitizeText($value, 1000, true);
        }

        $stmt = dbPrepare($conn, "INSERT INTO settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = ?", 'settings save:' . $key);
        if (!$stmt) {
            $settings_save_ok = false;
            continue;
        }
        $stmt->bind_param("sss", $key, $value, $value);
        if (!dbExecute($stmt, 'settings save:' . $key)) {
            $settings_save_ok = false;
        }
        $stmt->close();
    }

    if ($settings_save_ok) {
        setFlash('success', 'Settings updated successfully.');
    } else {
        setFlash('danger', 'Some settings could not be saved. Please try again.');
    }
    header('Location: ' . Auth::getBasePath() . '/modules/admin/settings.php');
    exit();
}

// Fetch settings from database
$settings = [];
$res = dbQuery($conn, "SELECT setting_key, setting_value FROM settings", 'settings load');
if ($res instanceof mysqli_result) {
    while ($row = $res->fetch_assoc()) {
        $settings[$row['setting_key']] = $row['setting_value'];
    }
    $res->free();
}
$page_title = 'System Settings';
require_once '../../includes/header.php';
?>
<div class="container-fluid">
    <div class="row mb-4">
        <div class="col">
            <h4 class="mb-0">System Settings</h4>
            <p class="text-muted mb-0">Configure system preferences and default values</p>
        </div>
    </div>
    
    <div class="row">
        <!-- Settings Navigation -->
        <div class="col-md-3 mb-4">
            <div class="card">
                <div class="card-body p-0">
                    <div class="list-group list-group-flush">
                        <a href="#general" class="list-group-item list-group-item-action active" 
                           data-bs-toggle="list">
                            <i class="bi bi-gear me-2"></i>General Settings
                        </a>
                        <a href="#leave" class="list-group-item list-group-item-action" 
                           data-bs-toggle="list">
                            <i class="bi bi-calendar me-2"></i>Leave Settings
                        </a>
                        <a href="#payroll" class="list-group-item list-group-item-action" 
                           data-bs-toggle="list">
                            <i class="bi bi-cash-coin me-2"></i>Payroll Settings
                        </a>
                        <a href="#email" class="list-group-item list-group-item-action" 
                           data-bs-toggle="list">
                            <i class="bi bi-envelope me-2"></i>Email Settings
                        </a>
                        <a href="#security" class="list-group-item list-group-item-action" 
                           data-bs-toggle="list">
                            <i class="bi bi-shield-lock me-2"></i>Security
                        </a>
                        <a href="#projects" class="list-group-item list-group-item-action" 
                           data-bs-toggle="list">
                            <i class="bi bi-briefcase me-2"></i>Project Settings
                        </a>
                        <a href="#otp" class="list-group-item list-group-item-action" 
                           data-bs-toggle="list">
                            <i class="bi bi-shield-check me-2"></i>OTP Settings
                        </a>
                        <a href="#backup" class="list-group-item list-group-item-action" 
                           data-bs-toggle="list">
                            <i class="bi bi-cloud-arrow-down me-2"></i>Backup & Restore
                        </a>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Settings Content -->
        <div class="col-md-9">
            <div class="tab-content">
                <!-- General Settings -->
                <div class="tab-pane fade show active" id="general">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="mb-0">General Settings</h5>
                        </div>
                        <div class="card-body">
                            <form method="POST" action="">
                                <?php echo csrfField(); ?>
                                <input type="hidden" name="setting_key" value="general">

                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">Company Name</label>
                                        <input type="text" class="form-control" 
                                               name="company_name" 
                                               value="<?php echo $settings['company_name']; ?>">
                                    </div>
                                    
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">Company Email</label>
                                        <input type="email" class="form-control" 
                                               name="company_email" 
                                               value="<?php echo $settings['company_email']; ?>">
                                    </div>
                                    
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">Company Phone</label>
                                        <input type="text" class="form-control" 
                                               name="company_phone" 
                                               value="<?php echo $settings['company_phone']; ?>">
                                    </div>
                                    
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">Company Address</label>
                                        <textarea class="form-control" name="company_address" 
                                                  rows="2"><?php echo $settings['company_address']; ?></textarea>
                                    </div>
                                    
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">Default Currency</label>
                                        <select class="form-select" name="currency">
                                            <option value="USD" <?php echo $settings['currency'] == 'USD' ? 'selected' : ''; ?>>USD ($)</option>
                                            <option value="EUR" <?php echo $settings['currency'] == 'EUR' ? 'selected' : ''; ?>>EUR (€)</option>
                                            <option value="GBP" <?php echo $settings['currency'] == 'GBP' ? 'selected' : ''; ?>>GBP (£)</option>
                                            <option value="INR" <?php echo $settings['currency'] == 'INR' ? 'selected' : ''; ?>>INR (₹)</option>
                                        </select>
                                    </div>
                                    
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">Time Zone</label>
                                        <select class="form-select" name="timezone">
                                            <option value="America/Los_Angeles" <?php echo $settings['timezone'] == 'America/Los_Angeles' ? 'selected' : ''; ?>>Pacific Time (PT)</option>
                                            <option value="America/New_York" <?php echo $settings['timezone'] == 'America/New_York' ? 'selected' : ''; ?>>Eastern Time (ET)</option>
                                            <option value="Europe/London" <?php echo $settings['timezone'] == 'Europe/London' ? 'selected' : ''; ?>>London (GMT)</option>
                                            <option value="Asia/Kolkata" <?php echo $settings['timezone'] == 'Asia/Kolkata' ? 'selected' : ''; ?>>India (IST)</option>
                                        </select>
                                    </div>
                                    
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">Date Format</label>
                                        <select class="form-select" name="date_format">
                                            <option value="Y-m-d" <?php echo $settings['date_format'] == 'Y-m-d' ? 'selected' : ''; ?>>YYYY-MM-DD</option>
                                            <option value="d/m/Y" <?php echo $settings['date_format'] == 'd/m/Y' ? 'selected' : ''; ?>>DD/MM/YYYY</option>
                                            <option value="m/d/Y" <?php echo $settings['date_format'] == 'm/d/Y' ? 'selected' : ''; ?>>MM/DD/YYYY</option>
                                            <option value="d M, Y" <?php echo $settings['date_format'] == 'd M, Y' ? 'selected' : ''; ?>>DD Mon, YYYY</option>
                                        </select>
                                    </div>
                                    
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">Working Hours</label>
                                        <div class="input-group">
                                            <input type="time" class="form-control" 
                                                   name="working_hours_start" 
                                                   value="<?php echo $settings['working_hours_start']; ?>">
                                            <span class="input-group-text">to</span>
                                            <input type="time" class="form-control" 
                                                   name="working_hours_end" 
                                                   value="<?php echo $settings['working_hours_end']; ?>">
                                        </div>
                                    </div>
                                    
                                    <div class="col-md-12">
                                        <button type="submit" name="save_settings" class="btn btn-primary">Save Changes</button>
                                        <button type="reset" class="btn btn-outline-secondary">Reset</button>
                                    </div>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
                
                <!-- Leave Settings -->
                <div class="tab-pane fade" id="leave">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="mb-0">Leave Settings</h5>
                        </div>
                        <div class="card-body">
                            <form method="POST" action="">
                                <?php echo csrfField(); ?>
                                <input type="hidden" name="setting_key" value="leave">

                                <div class="row">
                                    <div class="col-md-4 mb-3">
                                        <label class="form-label">Annual Leave Days</label>
                                        <input type="number" class="form-control" 
                                               name="leave_annual_days" 
                                               value="<?php echo $settings['leave_annual_days']; ?>">
                                        <small class="text-muted">Days per year</small>
                                    </div>
                                    
                                    <div class="col-md-4 mb-3">
                                        <label class="form-label">Sick Leave Days</label>
                                        <input type="number" class="form-control" 
                                               name="leave_sick_days" 
                                               value="<?php echo $settings['leave_sick_days']; ?>">
                                        <small class="text-muted">Days per year</small>
                                    </div>
                                    
                                    <div class="col-md-4 mb-3">
                                        <label class="form-label">Casual Leave Days</label>
                                        <input type="number" class="form-control" 
                                               name="leave_casual_days" 
                                               value="<?php echo $settings['leave_casual_days']; ?>">
                                        <small class="text-muted">Days per year</small>
                                    </div>
                                    
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">Maternity Leave</label>
                                        <input type="number" class="form-control" value="90" disabled>
                                        <small class="text-muted">Days (as per law)</small>
                                    </div>
                                    
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">Paternity Leave</label>
                                        <input type="number" class="form-control" value="7" disabled>
                                        <small class="text-muted">Days (as per law)</small>
                                    </div>
                                    
                                    <div class="col-md-12">
                                        <div class="form-check mb-3">
                                            <input class="form-check-input" type="checkbox" 
                                                   id="carry_forward" checked>
                                            <label class="form-check-label" for="carry_forward">
                                                Allow annual leave carry forward to next year
                                            </label>
                                        </div>
                                        
                                        <div class="form-check mb-3">
                                            <input class="form-check-input" type="checkbox" 
                                                   id="sick_leave_certificate">
                                            <label class="form-check-label" for="sick_leave_certificate">
                                                Require medical certificate for sick leave > 3 days
                                            </label>
                                        </div>
                                        
                                        <div class="form-check mb-3">
                                            <input class="form-check-input" type="checkbox" 
                                                   id="advance_leave_application" checked>
                                            <label class="form-check-label" for="advance_leave_application">
                                                Require leave applications at least 3 days in advance
                                            </label>
                                        </div>
                                    </div>
                                    
                                    <div class="col-md-12">
                                        <button type="submit" name="save_settings" class="btn btn-primary">Save Changes</button>
                                    </div>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
                
                <!-- Payroll Settings -->
                <div class="tab-pane fade" id="payroll">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="mb-0">Payroll Settings</h5>
                        </div>
                        <div class="card-body">
                            <form method="POST" action="">
                                <?php echo csrfField(); ?>
                                <input type="hidden" name="setting_key" value="payroll">

                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">Payroll Day</label>
                                        <select class="form-select" name="payroll_day">
                                            <?php for ($i = 1; $i <= 28; $i++): ?>
                                            <option value="<?php echo $i; ?>" 
                                                <?php echo $settings['payroll_day'] == $i ? 'selected' : ''; ?>>
                                                <?php echo $i; ?><?php echo $i == 1 ? 'st' : ($i == 2 ? 'nd' : ($i == 3 ? 'rd' : 'th')); ?> of month
                                            </option>
                                            <?php endfor; ?>
                                        </select>
                                        <small class="text-muted">Day of month when salaries are paid</small>
                                    </div>
                                    
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">Tax Percentage</label>
                                        <div class="input-group">
                                            <input type="number" class="form-control" 
                                                   name="tax_percentage" 
                                                   value="<?php echo $settings['tax_percentage']; ?>">
                                            <span class="input-group-text">%</span>
                                        </div>
                                        <small class="text-muted">Default income tax rate</small>
                                    </div>
                                    
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">Overtime Rate Multiplier</label>
                                        <div class="input-group">
                                            <input type="number" step="0.1" class="form-control" 
                                                   name="overtime_rate_multiplier" 
                                                   value="<?php echo $settings['overtime_rate_multiplier']; ?>">
                                            <span class="input-group-text">×</span>
                                        </div>
                                        <small class="text-muted">Overtime rate = hourly rate × multiplier</small>
                                    </div>
                                    
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">Working Hours per Month</label>
                                        <input type="number" class="form-control" value="160" disabled>
                                        <small class="text-muted">Used for hourly rate calculation</small>
                                    </div>
                                    
                                    <div class="col-md-12 mb-3">
                                        <label class="form-label">Payment Methods</label>
                                        <div class="row">
                                            <div class="col-md-3">
                                                <div class="form-check">
                                                    <input class="form-check-input" type="checkbox" 
                                                           id="bank_transfer" checked disabled>
                                                    <label class="form-check-label" for="bank_transfer">
                                                        Bank Transfer
                                                    </label>
                                                </div>
                                            </div>
                                            <div class="col-md-3">
                                                <div class="form-check">
                                                    <input class="form-check-input" type="checkbox" 
                                                           id="check_payment">
                                                    <label class="form-check-label" for="check_payment">
                                                        Check
                                                    </label>
                                                </div>
                                            </div>
                                            <div class="col-md-3">
                                                <div class="form-check">
                                                    <input class="form-check-input" type="checkbox" 
                                                           id="cash_payment">
                                                    <label class="form-check-label" for="cash_payment">
                                                        Cash
                                                    </label>
                                                </div>
                                            </div>
                                            <div class="col-md-3">
                                                <div class="form-check">
                                                    <input class="form-check-input" type="checkbox" 
                                                           id="online_payment">
                                                    <label class="form-check-label" for="online_payment">
                                                        Online Payment
                                                    </label>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <div class="col-md-12">
                                        <button type="submit" name="save_settings" class="btn btn-primary">Save Changes</button>
                                    </div>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
                
                <!-- Email Settings -->
                <div class="tab-pane fade" id="email">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="mb-0">Email & Notification Settings</h5>
                        </div>
                        <div class="card-body">
                            <form method="POST" action="">
                                <?php echo csrfField(); ?>
                                <input type="hidden" name="setting_key" value="email">

                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">SMTP Host</label>
                                        <input type="text" class="form-control" name="smtp_host" 
                                               value="<?php echo $settings['smtp_host'] ?? ''; ?>" 
                                               placeholder="smtp.gmail.com">
                                    </div>
                                    
                                    <div class="col-md-4 mb-3">
                                        <label class="form-label">SMTP Port</label>
                                        <input type="number" class="form-control" name="smtp_port" 
                                               value="<?php echo $settings['smtp_port'] ?? '587'; ?>" 
                                               placeholder="587">
                                    </div>

                                    <div class="col-md-2 mb-3">
                                        <label class="form-label">Enable SSL/TLS</label>
                                        <select class="form-select" name="smtp_secure">
                                            <option value="tls" <?php echo ($settings['smtp_secure'] ?? 'tls') == 'tls' ? 'selected' : ''; ?>>STARTTLS</option>
                                            <option value="ssl" <?php echo ($settings['smtp_secure'] ?? '') == 'ssl' ? 'selected' : ''; ?>>SSL</option>
                                            <option value="none" <?php echo ($settings['smtp_secure'] ?? '') == 'none' ? 'selected' : ''; ?>>None</option>
                                        </select>
                                    </div>
                                    
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">SMTP Username</label>
                                        <input type="email" class="form-control" name="smtp_user" 
                                               value="<?php echo $settings['smtp_user'] ?? ''; ?>" 
                                               placeholder="your_email@gmail.com">
                                    </div>
                                    
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">SMTP Password</label>
                                        <input type="password" class="form-control" name="smtp_pass" 
                                               value="<?php echo $settings['smtp_pass'] ?? ''; ?>" 
                                               placeholder="••••••••">
                                        <small class="text-muted">Use Gmail App Password if using Gmail</small>
                                    </div>

                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">From Email Address</label>
                                        <input type="email" class="form-control" name="smtp_from_email" 
                                               value="<?php echo $settings['smtp_from_email'] ?? ''; ?>" 
                                               placeholder="noreply@yourdomain.com">
                                    </div>

                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">From Name</label>
                                        <input type="text" class="form-control" name="smtp_from_name" 
                                               value="<?php echo $settings['smtp_from_name'] ?? 'NexGen HRMS'; ?>" 
                                               placeholder="NexGen HRMS">
                                    </div>
                                    
                                    <div class="col-md-12 mb-3">
                                        <h6>Enable Notifications For:</h6>
                                        <div class="row">
                                            <div class="col-md-4">
                                                <div class="form-check">
                                                    <input class="form-check-input" type="checkbox" 
                                                           name="notify_leave" id="notify_leave" 
                                                           <?php echo ($settings['notify_leave'] ?? '1') == '1' ? 'checked' : ''; ?>>
                                                    <label class="form-check-label" for="notify_leave">
                                                        Leave Applications
                                                    </label>
                                                </div>
                                            </div>
                                            <div class="col-md-4">
                                                <div class="form-check">
                                                    <input class="form-check-input" type="checkbox" 
                                                           name="notify_task" id="notify_task"
                                                           <?php echo ($settings['notify_task'] ?? '1') == '1' ? 'checked' : ''; ?>>
                                                    <label class="form-check-label" for="notify_task">
                                                        Task Assignments
                                                    </label>
                                                </div>
                                            </div>
                                            <div class="col-md-4">
                                                <div class="form-check">
                                                    <input class="form-check-input" type="checkbox" 
                                                           name="notify_payroll" id="notify_payroll"
                                                           <?php echo ($settings['notify_payroll'] ?? '1') == '1' ? 'checked' : ''; ?>>
                                                    <label class="form-check-label" for="notify_payroll">
                                                        Payroll Processing
                                                    </label>
                                                </div>
                                            </div>
                                            <div class="col-md-4">
                                                <div class="form-check">
                                                    <input class="form-check-input" type="checkbox" 
                                                           name="notify_inquiry" id="notify_inquiry"
                                                           <?php echo ($settings['notify_inquiry'] ?? '1') == '1' ? 'checked' : ''; ?>>
                                                    <label class="form-check-label" for="notify_inquiry">
                                                        New Inquiries
                                                    </label>
                                                </div>
                                            </div>
                                            <div class="col-md-4">
                                                <div class="form-check">
                                                    <input class="form-check-input" type="checkbox" 
                                                           name="notify_announcement" id="notify_announcement"
                                                           <?php echo ($settings['notify_announcement'] ?? '0') == '1' ? 'checked' : ''; ?>>
                                                    <label class="form-check-label" for="notify_announcement">
                                                        Company Announcements
                                                    </label>
                                                </div>
                                            </div>
                                            <div class="col-md-4">
                                                <div class="form-check">
                                                    <input class="form-check-input" type="checkbox" 
                                                           name="notify_birthday" id="notify_birthday"
                                                           <?php echo ($settings['notify_birthday'] ?? '0') == '1' ? 'checked' : ''; ?>>
                                                    <label class="form-check-label" for="notify_birthday">
                                                        Employee Birthdays
                                                    </label>
                                                </div>
                                            </div>
                                            <div class="col-md-4">
                                                <div class="form-check">
                                                    <input class="form-check-input" type="checkbox" 
                                                           name="notify_project" id="notify_project"
                                                           <?php echo ($settings['notify_project'] ?? '1') == '1' ? 'checked' : ''; ?>>
                                                    <label class="form-check-label" for="notify_project">
                                                        Project Assignments
                                                    </label>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <div class="col-md-12">
                                        <button type="submit" name="save_settings" class="btn btn-primary">Save Changes</button>
                                        <button type="button" class="btn btn-outline-info" 
                                                data-bs-toggle="modal" data-bs-target="#testEmailModal">
                                            Test Email Configuration
                                        </button>
                                    </div>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
                
                <!-- Security Settings -->
                <div class="tab-pane fade" id="security">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="mb-0">Security Settings</h5>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Session Timeout</label>
                                    <select class="form-select">
                                        <option value="15">15 minutes</option>
                                        <option value="30" selected>30 minutes</option>
                                        <option value="60">1 hour</option>
                                        <option value="120">2 hours</option>
                                        <option value="480">8 hours</option>
                                    </select>
                                </div>
                                
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Password Policy</label>
                                    <select class="form-select">
                                        <option value="low">Low (6 characters minimum)</option>
                                        <option value="medium" selected>Medium (8 characters, mixed case)</option>
                                        <option value="high">High (10 characters, mixed case + numbers)</option>
                                        <option value="strict">Strict (12 characters, mixed case + numbers + symbols)</option>
                                    </select>
                                </div>
                                
                                <div class="col-md-12 mb-3">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" 
                                               id="two_factor_auth">
                                        <label class="form-check-label" for="two_factor_auth">
                                            Enable Two-Factor Authentication for Admin Users
                                        </label>
                                    </div>
                                </div>
                                
                                <div class="col-md-12 mb-3">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" 
                                               id="ip_restriction">
                                        <label class="form-check-label" for="ip_restriction">
                                            Restrict access to specific IP addresses
                                        </label>
                                    </div>
                                </div>
                                
                                <div class="col-md-12 mb-3">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" 
                                               id="login_attempts" checked>
                                        <label class="form-check-label" for="login_attempts">
                                            Lock account after 5 failed login attempts
                                        </label>
                                    </div>
                                </div>
                                
                                <div class="col-md-12 mb-3">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" 
                                               id="maintenance_mode" 
                                               <?php echo $settings['maintenance_mode'] ? 'checked' : ''; ?>>
                                        <label class="form-check-label" for="maintenance_mode">
                                            Enable Maintenance Mode
                                        </label>
                                    </div>
                                </div>
                                
                                <div class="col-md-12">
                                    <button class="btn btn-primary">Save Security Settings</button>
                                    <button class="btn btn-outline-warning" 
                                            data-bs-toggle="modal" data-bs-target="#auditLogModal">
                                        View Audit Log
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Project Settings -->
                <div class="tab-pane fade" id="projects">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="mb-0">Project Settings</h5>
                        </div>
                        <div class="card-body">
                            <form method="POST" action="">
                                <?php echo csrfField(); ?>
                                <input type="hidden" name="setting_key" value="projects">

                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">Project Code Prefix</label>
                                        <input type="text" class="form-control" 
                                               name="project_code_prefix" 
                                               value="<?php echo htmlspecialchars($settings['project_code_prefix'] ?? 'PROJ'); ?>"
                                               maxlength="10"
                                               placeholder="e.g., PROJ, PRJ, P">
                                        <small class="text-muted">Used when auto-generating project codes (e.g., PROJ260330001)</small>
                                    </div>
                                    
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">Progress Color - Low Threshold</label>
                                        <input type="number" class="form-control" 
                                               name="progress_color_low_threshold" 
                                               value="<?php echo htmlspecialchars($settings['progress_color_low_threshold'] ?? '50'); ?>"
                                               min="0" max="100" step="1">
                                        <small class="text-muted">% - Progress below this shows as red</small>
                                    </div>
                                    
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">Progress Color - Mid Threshold</label>
                                        <input type="number" class="form-control" 
                                               name="progress_color_mid_threshold" 
                                               value="<?php echo htmlspecialchars($settings['progress_color_mid_threshold'] ?? '80'); ?>"
                                               min="0" max="100" step="1">
                                        <small class="text-muted">% - Progress between low and mid shows as yellow, above shows as green</small>
                                    </div>

                                    <div class="col-md-12 mb-3">
                                        <div class="alert alert-info">
                                            <h6><i class="bi bi-info-circle me-2"></i>Progress Color Legend:</h6>
                                            <ul class="mb-0">
                                                <li><span class="badge bg-danger">Red</span> - Progress &lt; <span id="low_threshold_display"><?php echo $settings['progress_color_low_threshold'] ?? '50'; ?></span>%</li>
                                                <li><span class="badge bg-warning text-dark">Yellow</span> - Progress <span id="low_threshold_display2"><?php echo $settings['progress_color_low_threshold'] ?? '50'; ?></span>% to <span id="mid_threshold_display"><?php echo $settings['progress_color_mid_threshold'] ?? '80'; ?></span>%</li>
                                                <li><span class="badge bg-success">Green</span> - Progress &gt; <span id="mid_threshold_display2"><?php echo $settings['progress_color_mid_threshold'] ?? '80'; ?></span>%</li>
                                            </ul>
                                        </div>
                                    </div>
                                    
                                    <div class="col-md-12">
                                        <button type="submit" name="save_settings" class="btn btn-primary">Save Changes</button>
                                        <button type="reset" class="btn btn-outline-secondary">Reset</button>
                                    </div>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
                
                <!-- OTP Settings -->
                <div class="tab-pane fade" id="otp">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="mb-0">OTP (One-Time Password) Settings</h5>
                        </div>
                        <div class="card-body">
                            <form method="POST" action="">
                                <?php echo csrfField(); ?>
                                <input type="hidden" name="setting_key" value="otp">

                                <div class="row">
                                    <div class="col-md-4 mb-3">
                                        <label class="form-label">OTP Code Length</label>
                                        <input type="number" class="form-control" 
                                               name="otp_length" 
                                               value="<?php echo htmlspecialchars($settings['otp_length'] ?? '6'); ?>"
                                               min="4" max="10" step="1">
                                        <small class="text-muted">Number of digits in OTP code</small>
                                    </div>
                                    
                                    <div class="col-md-4 mb-3">
                                        <label class="form-label">OTP Expiry Time</label>
                                        <input type="number" class="form-control" 
                                               name="otp_expiry_minutes" 
                                               value="<?php echo htmlspecialchars($settings['otp_expiry_minutes'] ?? '10'); ?>"
                                               min="1" max="60" step="1">
                                        <small class="text-muted">Minutes until OTP expires</small>
                                    </div>
                                    
                                    <div class="col-md-4 mb-3">
                                        <label class="form-label">Max Verification Attempts</label>
                                        <input type="number" class="form-control" 
                                               name="otp_max_attempts" 
                                               value="<?php echo htmlspecialchars($settings['otp_max_attempts'] ?? '3'); ?>"
                                               min="1" max="10" step="1">
                                        <small class="text-muted">Attempts allowed before blocking</small>
                                    </div>

                                    <div class="col-md-12 mb-3">
                                        <div class="alert alert-warning">
                                            <h6><i class="bi bi-exclamation-triangle me-2"></i>OTP Settings Guide:</h6>
                                            <ul class="mb-0">
                                                <li>Used for: Password reset, email verification, 2FA login</li>
                                                <li>Rate limiting: 3 requests per 5 minutes (non-configurable)</li>
                                                <li>Recommended: 6 digits, 10 minutes expiry, 3 attempts max</li>
                                            </ul>
                                        </div>
                                    </div>
                                    
                                    <div class="col-md-12">
                                        <button type="submit" name="save_settings" class="btn btn-primary">Save Changes</button>
                                        <button type="reset" class="btn btn-outline-secondary">Reset</button>
                                    </div>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
                
                <!-- Backup & Restore -->
                <div class="tab-pane fade" id="backup">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="mb-0">Backup & Restore</h5>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-6 mb-4">
                                    <div class="card">
                                        <div class="card-body text-center">
                                            <i class="bi bi-cloud-arrow-up h1 text-primary mb-3"></i>
                                            <h5>Backup Database</h5>
                                            <p class="text-muted small mb-3">
                                                Create a backup of the entire database
                                            </p>
                                            <button class="btn btn-primary">
                                                <i class="bi bi-download"></i> Create Backup
                                            </button>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="col-md-6 mb-4">
                                    <div class="card">
                                        <div class="card-body text-center">
                                            <i class="bi bi-cloud-arrow-down h1 text-success mb-3"></i>
                                            <h5>Restore Database</h5>
                                            <p class="text-muted small mb-3">
                                                Restore database from backup file
                                            </p>
                                            <div class="mb-3">
                                                <input type="file" class="form-control" accept=".sql,.backup">
                                            </div>
                                            <button class="btn btn-success">
                                                <i class="bi bi-upload"></i> Restore Backup
                                            </button>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="col-md-12">
                                    <div class="card">
                                        <div class="card-header">
                                            <h6 class="mb-0">Recent Backups</h6>
                                        </div>
                                        <div class="card-body">
                                            <div class="table-responsive">
                                                <table class="table table-sm">
                                                    <thead>
                                                        <tr>
                                                            <th>Backup Date</th>
                                                            <th>File Size</th>
                                                            <th>Type</th>
                                                            <th>Status</th>
                                                            <th>Actions</th>
                                                        </tr>
                                                    </thead>
                                                    <tbody>
                                                        <tr>
                                                            <td>Today, 02:30 AM</td>
                                                            <td>45.2 MB</td>
                                                            <td>Auto Backup</td>
                                                            <td><span class="badge bg-success">Complete</span></td>
                                                            <td>
                                                                <button class="btn btn-sm btn-outline-primary">
                                                                    <i class="bi bi-download"></i>
                                                                </button>
                                                            </td>
                                                        </tr>
                                                        <tr>
                                                            <td>Yesterday, 02:30 AM</td>
                                                            <td>44.8 MB</td>
                                                            <td>Auto Backup</td>
                                                            <td><span class="badge bg-success">Complete</span></td>
                                                            <td>
                                                                <button class="btn btn-sm btn-outline-primary">
                                                                    <i class="bi bi-download"></i>
                                                                </button>
                                                            </td>
                                                        </tr>
                                                    </tbody>
                                                </table>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Test Email Modal -->
<div class="modal fade" id="testEmailModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Test Email Configuration</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" id="settings_ajax_csrf" value="<?php echo htmlspecialchars(generateCSRFToken(), ENT_QUOTES, 'UTF-8'); ?>">
                <div class="mb-3">
                    <label class="form-label">Test Email Address</label>
                    <input type="email" id="test_email_input" class="form-control" 
                           placeholder="Enter email to send test" 
                           value="<?php echo $settings['smtp_from_email'] ?? ''; ?>">
                </div>
                <div id="test_email_result" class="alert d-none"></div>
                <div class="alert alert-info">
                    <i class="bi bi-info-circle"></i> 
                    A test email will be sent to verify your <strong>currently saved</strong> SMTP configuration.
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" id="send_test_email_btn" class="btn btn-primary">
                    <span class="spinner-border spinner-border-sm d-none" role="status" aria-hidden="true"></span>
                    Send Test Email
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Audit Log Modal -->
<div class="modal fade" id="auditLogModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">System Audit Log</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="table-responsive">
                    <table class="table table-sm">
                        <thead>
                            <tr>
                                <th>Timestamp</th>
                                <th>User</th>
                                <th>Action</th>
                                <th>IP Address</th>
                                <th>Details</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td>Today, 10:30 AM</td>
                                <td>Admin User</td>
                                <td>Login</td>
                                <td>192.168.1.100</td>
                                <td>Successful login</td>
                            </tr>
                            <tr>
                                <td>Today, 09:15 AM</td>
                                <td>HR User</td>
                                <td>Leave Approval</td>
                                <td>192.168.1.101</td>
                                <td>Approved leave LV-00123</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                <button type="button" class="btn btn-primary">Export Log</button>
            </div>
        </div>
    </div>
</div>

<script>
    // Tab switching
    document.addEventListener('DOMContentLoaded', function() {
        const triggerTabList = document.querySelectorAll('[data-bs-toggle="list"]');
        triggerTabList.forEach(triggerEl => {
            triggerEl.addEventListener('click', function() {
                // Remove active class from all tabs
                triggerTabList.forEach(el => {
                    el.classList.remove('active');
                });
                // Add active class to clicked tab
                this.classList.add('active');
            });
        });
        
        // Show warning for maintenance mode
        const maintenanceCheckbox = document.getElementById('maintenance_mode');
        if (maintenanceCheckbox) {
            maintenanceCheckbox.addEventListener('change', function() {
                if (this.checked) {
                    if (!confirm('Enabling maintenance mode will log out all users. Continue?')) {
                        this.checked = false;
                    }
                }
            });
        }

        const sendTestBtn = document.getElementById('send_test_email_btn');
        const testEmailInput = document.getElementById('test_email_input');
        const testResult = document.getElementById('test_email_result');
        const ajaxCsrfInput = document.getElementById('settings_ajax_csrf');

        if (sendTestBtn) {
            sendTestBtn.addEventListener('click', function() {
                const email = testEmailInput.value;
                if (!email) {
                    alert('Please enter an email address.');
                    return;
                }

                sendTestBtn.disabled = true;
                sendTestBtn.querySelector('.spinner-border').classList.remove('d-none');
                testResult.classList.add('d-none');

                const formData = new FormData();
                formData.append('email', email);
                if (ajaxCsrfInput) {
                    formData.append('csrf_token', ajaxCsrfInput.value);
                }

                fetch('test_email_ajax.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    testResult.classList.remove('d-none', 'alert-success', 'alert-danger');
                    testResult.classList.add(data.success ? 'alert-success' : 'alert-danger');
                    testResult.textContent = data.message;
                })
                .catch(error => {
                    testResult.classList.remove('d-none', 'alert-success');
                    testResult.classList.add('alert-danger');
                    testResult.textContent = 'An error occurred. Please check the console.';
                    console.error('Error:', error);
                })
                .finally(() => {
                    sendTestBtn.disabled = false;
                    sendTestBtn.querySelector('.spinner-border').classList.add('d-none');
                });
            });
        }
    });
</script>

<?php require_once '../../includes/footer.php'; ?>
