<?php
require_once '../../includes/auth.php';
require_once '../../includes/functions.php';

// Check login status first
Auth::requireLogin();

// Only admin can access settings
if (!Auth::hasRole('admin')) {
    setFlash('danger', 'Access denied. Admin only.');
    header('Location: ' . Auth::getBasePath() . '/dashboard.php');
    exit();
}

$page_title = 'System Settings';
$conn = getDBConnection();

// Handle settings update
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $setting_key = $_POST['setting_key'];
    $setting_value = $_POST['setting_value'];
    
    // In a real system, you'd have a settings table
    // For now, we'll just show a success message
    setFlash('success', 'Settings updated successfully.');
    header('Location: ' . Auth::getBasePath() . '/modules/admin/settings.php');
    exit();
}

// Default settings (in real system, fetch from database)
$settings = [
    'company_name' => 'NexGen Solutions',
    'company_email' => 'info@nexgensolutions.com',
    'company_phone' => '+1 (555) 123-4567',
    'company_address' => '123 Tech Street, San Francisco, CA 94107',
    'leave_annual_days' => '15',
    'leave_sick_days' => '10',
    'leave_casual_days' => '7',
    'working_hours_start' => '09:00',
    'working_hours_end' => '18:00',
    'payroll_day' => '7',
    'tax_percentage' => '10',
    'overtime_rate_multiplier' => '1.5',
    'currency' => 'USD',
    'date_format' => 'Y-m-d',
    'timezone' => 'America/Los_Angeles',
    'email_notifications' => '1',
    'sms_notifications' => '0',
    'maintenance_mode' => '0'
];
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
                                        <button type="submit" class="btn btn-primary">Save Changes</button>
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
                                        <button type="submit" class="btn btn-primary">Save Changes</button>
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
                                        <button type="submit" class="btn btn-primary">Save Changes</button>
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
                                <input type="hidden" name="setting_key" value="email">
                                
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">SMTP Host</label>
                                        <input type="text" class="form-control" 
                                               placeholder="smtp.gmail.com">
                                    </div>
                                    
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">SMTP Port</label>
                                        <input type="number" class="form-control" 
                                               placeholder="587">
                                    </div>
                                    
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">SMTP Username</label>
                                        <input type="email" class="form-control" 
                                               placeholder="noreply@nexgensolutions.com">
                                    </div>
                                    
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">SMTP Password</label>
                                        <input type="password" class="form-control" 
                                               placeholder="••••••••">
                                    </div>
                                    
                                    <div class="col-md-12 mb-3">
                                        <h6>Enable Notifications For:</h6>
                                        <div class="row">
                                            <div class="col-md-4">
                                                <div class="form-check">
                                                    <input class="form-check-input" type="checkbox" 
                                                           id="notify_leave" checked>
                                                    <label class="form-check-label" for="notify_leave">
                                                        Leave Applications
                                                    </label>
                                                </div>
                                            </div>
                                            <div class="col-md-4">
                                                <div class="form-check">
                                                    <input class="form-check-input" type="checkbox" 
                                                           id="notify_task" checked>
                                                    <label class="form-check-label" for="notify_task">
                                                        Task Assignments
                                                    </label>
                                                </div>
                                            </div>
                                            <div class="col-md-4">
                                                <div class="form-check">
                                                    <input class="form-check-input" type="checkbox" 
                                                           id="notify_payroll" checked>
                                                    <label class="form-check-label" for="notify_payroll">
                                                        Payroll Processing
                                                    </label>
                                                </div>
                                            </div>
                                            <div class="col-md-4">
                                                <div class="form-check">
                                                    <input class="form-check-input" type="checkbox" 
                                                           id="notify_inquiry" checked>
                                                    <label class="form-check-label" for="notify_inquiry">
                                                        New Inquiries
                                                    </label>
                                                </div>
                                            </div>
                                            <div class="col-md-4">
                                                <div class="form-check">
                                                    <input class="form-check-input" type="checkbox" 
                                                           id="notify_announcement">
                                                    <label class="form-check-label" for="notify_announcement">
                                                        Company Announcements
                                                    </label>
                                                </div>
                                            </div>
                                            <div class="col-md-4">
                                                <div class="form-check">
                                                    <input class="form-check-input" type="checkbox" 
                                                           id="notify_birthday">
                                                    <label class="form-check-label" for="notify_birthday">
                                                        Employee Birthdays
                                                    </label>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <div class="col-md-12">
                                        <button type="submit" class="btn btn-primary">Save Changes</button>
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
                <div class="mb-3">
                    <label class="form-label">Test Email Address</label>
                    <input type="email" class="form-control" placeholder="Enter email to send test">
                </div>
                <div class="alert alert-info">
                    <i class="bi bi-info-circle"></i> 
                    A test email will be sent to verify SMTP configuration.
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary">Send Test Email</button>
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
    });
</script>

<?php require_once '../../includes/footer.php'; ?>