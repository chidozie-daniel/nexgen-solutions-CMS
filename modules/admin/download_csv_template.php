<?php
/**
 * Download CSV Templates for Bulk Upload
 */

define('APP_ENTRY_POINT', true);
require_once '../../includes/auth.php';
require_once '../../includes/functions.php';

Auth::requireLogin();

$template = $_GET['type'] ?? '';

$templates = [
    'users' => [
        'filename' => 'user_import_template.csv',
        'headers' => ['username', 'email', 'full_name', 'role', 'department', 'position', 'salary', 'employee_id', 'hire_date', 'phone'],
        'sample_data' => [
            ['johndoe', 'john@example.com', 'John Doe', 'employee', 'IT', 'Developer', '5000', 'EMP20260001', '2026-01-15', '1234567890'],
            ['janesmith', 'jane@example.com', 'Jane Smith', 'project_leader', 'Marketing', 'Manager', '7000', 'EMP20260002', '2026-02-01', '0987654321'],
        ],
        'notes' => [
            'username: 3-30 characters (letters, numbers, underscore, dash, dot)',
            'email: Valid email format',
            'role: employee, project_leader, hr, or admin',
            'salary: Optional, numeric value',
            'hire_date: Format YYYY-MM-DD',
            'Default password will be: Nexgen@123'
        ]
    ],
    'attendance' => [
        'filename' => 'attendance_import_template.csv',
        'headers' => ['employee_id', 'date', 'check_in', 'check_out', 'status', 'notes'],
        'sample_data' => [
            ['EMP20260001', '2026-03-30', '09:00:00', '18:00:00', 'present', ''],
            ['EMP20260002', '2026-03-30', '09:15:00', '18:30:00', 'late', 'Traffic delay'],
            ['EMP20260003', '2026-03-30', '', '', 'remote', 'Working from home'],
        ],
        'notes' => [
            'employee_id: Must match existing employee ID',
            'date: Format YYYY-MM-DD',
            'check_in/check_out: Format HH:MM:SS (24-hour)',
            'status: present, absent, late, half_day, or remote',
            'Existing records will be skipped unless overwrite is enabled'
        ]
    ],
    'tasks' => [
        'filename' => 'task_import_template.csv',
        'headers' => ['title', 'description', 'assigned_to_email', 'priority', 'due_date', 'status', 'progress'],
        'sample_data' => [
            ['Design Homepage', 'Create mockup for company website homepage', 'designer@example.com', 'high', '2026-04-15', 'pending', '0'],
            ['API Development', 'Build REST API endpoints for user management', 'developer@example.com', 'critical', '2026-04-20', 'in_progress', '25'],
            ['Write Documentation', 'Document all API endpoints and usage', 'writer@example.com', 'medium', '2026-04-25', 'pending', '0'],
        ],
        'notes' => [
            'title: Minimum 5 characters',
            'description: Detailed task description',
            'assigned_to_email: Must match existing user email',
            'priority: low, medium, high, or critical',
            'due_date: Format YYYY-MM-DD',
            'status: pending, in_progress, review, completed, or cancelled',
            'progress: 0-100'
        ]
    ],
    'payroll' => [
        'filename' => 'payroll_inputs_template.csv',
        'headers' => ['employee_id', 'full_name', 'overtime_hours', 'bonus', 'deductions', 'notes'],
        'sample_data' => [
            ['EMP20260001', 'John Doe', '5', '100', '0', 'Overtime for month-end release'],
            ['EMP20260002', 'Jane Smith', '0', '250', '50', 'Performance bonus, equipment deduction'],
        ],
        'notes' => [
            'employee_id: Must match existing employee ID in the system',
            'overtime_hours/bonus/deductions: Non-negative numbers',
            'notes: Optional, for HR reference',
            'Upload from Payroll > Submit Inputs'
        ]
    ]
];

if (!isset($templates[$template])) {
    setFlash('danger', 'Invalid template type.');
    header('Location: ' . Auth::getBasePath() . '/dashboard.php');
    exit();
}

$template_data = $templates[$template];

// Set headers for CSV download
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $template_data['filename'] . '"');

$output = fopen('php://output', 'w');

// Add notes as comments at the top
foreach ($template_data['notes'] as $note) {
    fputcsv($output, ['# ' . $note]);
}

// Add empty line
fputcsv($output, ['']);

// Add headers
fputcsv($output, $template_data['headers']);

// Add sample data
foreach ($template_data['sample_data'] as $row) {
    fputcsv($output, $row);
}

fclose($output);
exit();
?>
