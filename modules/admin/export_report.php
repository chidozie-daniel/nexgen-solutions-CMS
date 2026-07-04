<?php
define('APP_ENTRY_POINT', true);

require_once '../../includes/auth.php';
require_once '../../includes/functions.php';

Auth::requireRole(['admin', 'hr']);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('Method not allowed');
}

if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
    http_response_code(403);
    exit('Invalid security token');
}

$conn = getDBConnection();

$report_type = $_POST['report'] ?? 'overview';
$start_date = $_POST['start_date'] ?? date('Y-m-01');
$end_date = $_POST['end_date'] ?? date('Y-m-t');
$department = $_POST['department'] ?? 'all';
$format = strtolower(trim((string)($_POST['format'] ?? 'csv')));

if (!isValidDate($start_date, 'Y-m-d') || !isValidDate($end_date, 'Y-m-d')) {
    http_response_code(400);
    exit('Invalid date range');
}

$allowed_formats = ['csv', 'xls', 'pdf'];
if (!in_array($format, $allowed_formats, true)) $format = 'csv';

$safe_report = preg_replace('/[^a-z0-9_]/i', '', (string)$report_type);
$ts = date('Ymd_His');

// Collect "sections" as arrays to support multiple export types
$sections = [];
$meta_lines = [
    ['NexGen HRMS Report Export'],
    ['Report', $report_type],
    ['Start Date', $start_date],
    ['End Date', $end_date],
    ['Department', $department],
    ['Generated At', date('Y-m-d H:i:s')],
];

$dept_filter_sql = '';
$dept_param = null;
if ($department !== 'all' && $department !== '') {
    $dept_filter_sql = " AND u.department = ? ";
    $dept_param = $department;
}

switch ($report_type) {
    case 'overview': {
        $rows = [];
        $rows[] = ['Department', 'Active Employees'];

        $dept_sql = "SELECT u.department, COUNT(*) as count
                     FROM users u
                     WHERE u.status = 'active' AND u.department IS NOT NULL" . ($department !== 'all' ? " AND u.department = ?" : "") . "
                     GROUP BY u.department
                     ORDER BY count DESC";
        if ($department !== 'all') {
            $stmt = $conn->prepare($dept_sql);
            $stmt->bind_param("s", $department);
            $stmt->execute();
            $res = $stmt->get_result();
        } else {
            $res = $conn->query($dept_sql);
        }
        if ($res) {
            while ($row = $res->fetch_assoc()) {
                $rows[] = [$row['department'], $row['count']];
            }
        }
        $sections[] = ['title' => 'Department Distribution', 'rows' => $rows];

        $rows = [];
        $rows[] = ['Total Leaves', 'Approved', 'Pending', 'Rejected', 'Total Days'];
        $leave_sql = "SELECT
            COUNT(*) as total_leaves,
            SUM(CASE WHEN status = 'approved' THEN 1 ELSE 0 END) as approved,
            SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending,
            SUM(CASE WHEN status = 'rejected' THEN 1 ELSE 0 END) as rejected,
            SUM(duration_days) as total_days
            FROM leaves l
            JOIN users u ON l.user_id = u.id
            WHERE l.start_date BETWEEN ? AND ? $dept_filter_sql";
        $stmt = $conn->prepare($leave_sql);
        if ($dept_param !== null) {
            $stmt->bind_param("sss", $start_date, $end_date, $dept_param);
        } else {
            $stmt->bind_param("ss", $start_date, $end_date);
        }
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $rows[] = [$row['total_leaves'] ?? 0, $row['approved'] ?? 0, $row['pending'] ?? 0, $row['rejected'] ?? 0, $row['total_days'] ?? 0];
        $sections[] = ['title' => 'Leave Summary', 'rows' => $rows];

        $rows = [];
        $rows[] = ['Total Tasks', 'Completed', 'In Progress', 'Average Progress'];
        $task_sql = "SELECT
            COUNT(*) as total_tasks,
            SUM(CASE WHEN t.status = 'completed' THEN 1 ELSE 0 END) as completed,
            SUM(CASE WHEN t.status = 'in_progress' THEN 1 ELSE 0 END) as in_progress,
            AVG(t.progress) as avg_progress
            FROM tasks t
            JOIN users u ON t.assigned_to = u.id
            WHERE t.created_at BETWEEN ? AND ? $dept_filter_sql";
        $stmt = $conn->prepare($task_sql);
        if ($dept_param !== null) {
            $stmt->bind_param("sss", $start_date . " 00:00:00", $end_date . " 23:59:59", $dept_param);
        } else {
            $stmt->bind_param("ss", $start_date . " 00:00:00", $end_date . " 23:59:59");
        }
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $rows[] = [$row['total_tasks'] ?? 0, $row['completed'] ?? 0, $row['in_progress'] ?? 0, round((float)($row['avg_progress'] ?? 0), 2)];
        $sections[] = ['title' => 'Task Summary', 'rows' => $rows];

        $rows = [];
        $rows[] = ['Salary Records', 'Total Amount', 'Average Salary', 'Paid Count'];
        $pay_sql = "SELECT
            COUNT(*) as total_salaries,
            SUM(s.net_salary) as total_amount,
            AVG(s.net_salary) as avg_salary,
            SUM(CASE WHEN s.status = 'paid' THEN 1 ELSE 0 END) as paid
            FROM salaries s
            JOIN users u ON s.user_id = u.id
            WHERE s.month BETWEEN DATE_FORMAT(?, '%Y-%m') AND DATE_FORMAT(?, '%Y-%m') $dept_filter_sql";
        $stmt = $conn->prepare($pay_sql);
        if ($dept_param !== null) {
            $stmt->bind_param("sss", $start_date, $end_date, $dept_param);
        } else {
            $stmt->bind_param("ss", $start_date, $end_date);
        }
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $rows[] = [$row['total_salaries'] ?? 0, $row['total_amount'] ?? 0, $row['avg_salary'] ?? 0, $row['paid'] ?? 0];
        $sections[] = ['title' => 'Payroll Summary', 'rows' => $rows];
        break;
    }

    case 'leave': {
        $rows = [];
        $rows[] = ['Employee', 'Department', 'Leave Type', 'Start Date', 'End Date', 'Days', 'Status'];
        $sql = "SELECT u.full_name, u.department, l.leave_type, l.start_date, l.end_date, l.duration_days, l.status
                FROM leaves l
                JOIN users u ON l.user_id = u.id
                WHERE l.start_date BETWEEN ? AND ? $dept_filter_sql
                ORDER BY l.start_date DESC";
        $stmt = $conn->prepare($sql);
        if ($dept_param !== null) {
            $stmt->bind_param("sss", $start_date, $end_date, $dept_param);
        } else {
            $stmt->bind_param("ss", $start_date, $end_date);
        }
        $stmt->execute();
        $res = $stmt->get_result();
        while ($r = $res->fetch_assoc()) {
            $rows[] = [$r['full_name'], $r['department'], $r['leave_type'], $r['start_date'], $r['end_date'], $r['duration_days'], $r['status']];
        }
        $sections[] = ['title' => 'Leave Analysis', 'rows' => $rows];
        break;
    }

    case 'payroll':
    case 'financial': {
        $rows = [];
        $rows[] = ['Month', 'Employees', 'Total Net', 'Average Net', 'Paid'];
        $sql = "SELECT s.month,
                       COUNT(*) as employees,
                       SUM(s.net_salary) as total_net,
                       AVG(s.net_salary) as avg_net,
                       SUM(CASE WHEN s.status='paid' THEN 1 ELSE 0 END) as paid
                FROM salaries s
                JOIN users u ON s.user_id = u.id
                WHERE s.month BETWEEN DATE_FORMAT(?, '%Y-%m') AND DATE_FORMAT(?, '%Y-%m') $dept_filter_sql
                GROUP BY s.month
                ORDER BY s.month DESC";
        $stmt = $conn->prepare($sql);
        if ($dept_param !== null) {
            $stmt->bind_param("sss", $start_date, $end_date, $dept_param);
        } else {
            $stmt->bind_param("ss", $start_date, $end_date);
        }
        $stmt->execute();
        $res = $stmt->get_result();
        while ($r = $res->fetch_assoc()) {
            $rows[] = [$r['month'], $r['employees'], $r['total_net'], $r['avg_net'], $r['paid']];
        }
        $sections[] = ['title' => 'Payroll / Financial', 'rows' => $rows];
        break;
    }

    case 'attendance': {
        $rows = [];
        $rows[] = ['Employee', 'Department', 'Days', 'Present', 'Absent', 'Late', 'Remote', 'Total Hours'];
        $sql = "SELECT u.full_name, u.department,
                       COUNT(a.id) as days,
                       SUM(CASE WHEN a.status='present' THEN 1 ELSE 0 END) as present,
                       SUM(CASE WHEN a.status='absent' THEN 1 ELSE 0 END) as absent,
                       SUM(CASE WHEN a.status='late' THEN 1 ELSE 0 END) as late,
                       SUM(CASE WHEN a.status='remote' THEN 1 ELSE 0 END) as remote,
                       SUM(COALESCE(a.working_hours,0)) as total_hours
                FROM attendance a
                JOIN users u ON a.user_id = u.id
                WHERE a.date BETWEEN ? AND ? $dept_filter_sql
                GROUP BY u.id
                ORDER BY u.full_name";
        $stmt = $conn->prepare($sql);
        if ($dept_param !== null) {
            $stmt->bind_param("sss", $start_date, $end_date, $dept_param);
        } else {
            $stmt->bind_param("ss", $start_date, $end_date);
        }
        $stmt->execute();
        $res = $stmt->get_result();
        while ($r = $res->fetch_assoc()) {
            $rows[] = [$r['full_name'], $r['department'], $r['days'], $r['present'], $r['absent'], $r['late'], $r['remote'], $r['total_hours']];
        }
        $sections[] = ['title' => 'Attendance Report', 'rows' => $rows];
        break;
    }

    case 'productivity': {
        $rows = [];
        $rows[] = ['Employee', 'Department', 'Tasks Total', 'Completed', 'Avg Progress', 'Logged Hours'];
        $sql = "SELECT u.full_name, u.department,
                       COUNT(t.id) as tasks_total,
                       SUM(CASE WHEN t.status='completed' THEN 1 ELSE 0 END) as completed,
                       AVG(t.progress) as avg_progress,
                       COALESCE(SUM(lt.hours_spent),0) as logged_hours
                FROM users u
                LEFT JOIN tasks t ON t.assigned_to = u.id AND t.created_at BETWEEN ? AND ?
                LEFT JOIN task_time_logs lt ON lt.user_id = u.id AND lt.created_at BETWEEN ? AND ?
                WHERE u.status='active' " . ($department !== 'all' ? " AND u.department = ? " : "") . "
                GROUP BY u.id
                ORDER BY u.full_name";
        $stmt = $conn->prepare($sql);
        if ($department !== 'all') {
            $stmt->bind_param("sssss", $start_date . " 00:00:00", $end_date . " 23:59:59", $start_date . " 00:00:00", $end_date . " 23:59:59", $department);
        } else {
            $stmt->bind_param("ssss", $start_date . " 00:00:00", $end_date . " 23:59:59", $start_date . " 00:00:00", $end_date . " 23:59:59");
        }
        $stmt->execute();
        $res = $stmt->get_result();
        while ($r = $res->fetch_assoc()) {
            $rows[] = [$r['full_name'], $r['department'], $r['tasks_total'], $r['completed'], round((float)($r['avg_progress'] ?? 0), 2), $r['logged_hours']];
        }
        $sections[] = ['title' => 'Employee Productivity', 'rows' => $rows];
        break;
    }

    default:
        $sections[] = ['title' => 'Unsupported report type', 'rows' => [['Message'], ['Unsupported report type']]];
        break;
}

function csv_escape_cell($v) {
    if ($v === null) return '';
    return (string)$v;
}

if ($format === 'csv') {
    $filename = "report_{$safe_report}_{$ts}.csv";
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');

    $out = fopen('php://output', 'w');
    foreach ($meta_lines as $line) fputcsv($out, $line);
    fputcsv($out, []);
    foreach ($sections as $s) {
        fputcsv($out, [$s['title']]);
        foreach ($s['rows'] as $r) fputcsv($out, array_map('csv_escape_cell', $r));
        fputcsv($out, []);
    }
    fclose($out);
    exit();
}

if ($format === 'xls') {
    $filename = "report_{$safe_report}_{$ts}.xls";
    header('Content-Type: application/vnd.ms-excel; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');

    $html = '<html><head><meta charset="utf-8"></head><body>';
    $html .= '<h2>NexGen HRMS Report Export</h2>';
    $html .= '<table border="1" cellpadding="5" cellspacing="0">';
    foreach ($meta_lines as $line) {
        $html .= '<tr><td colspan="6"><strong>' . htmlspecialchars($line[0] ?? '') . '</strong></td></tr>';
        if (count($line) > 1) {
            $html .= '<tr><td><strong>' . htmlspecialchars((string)$line[0]) . '</strong></td><td>' . htmlspecialchars((string)$line[1]) . '</td></tr>';
        }
    }
    $html .= '</table><br>';
    foreach ($sections as $s) {
        $html .= '<h3>' . htmlspecialchars($s['title']) . '</h3>';
        $html .= '<table border="1" cellpadding="5" cellspacing="0">';
        foreach ($s['rows'] as $ri => $row) {
            $html .= '<tr>';
            foreach ($row as $cell) {
                $tag = $ri === 0 ? 'th' : 'td';
                $html .= "<{$tag}>" . htmlspecialchars((string)$cell) . "</{$tag}>";
            }
            $html .= '</tr>';
        }
        $html .= '</table><br>';
    }
    $html .= '</body></html>';
    echo $html;
    exit();
}

// PDF
require_once dirname(__DIR__, 2) . '/vendor/autoload.php';
if (!class_exists(\Dompdf\Dompdf::class)) {
    http_response_code(500);
    exit('PDF export not available (dompdf missing)');
}

$filename = "report_{$safe_report}_{$ts}.pdf";
header('Content-Type: application/pdf');
header('Content-Disposition: attachment; filename="' . $filename . '"');

$html = '<html><head><meta charset="utf-8"><style>
body { font-family: DejaVu Sans, sans-serif; font-size: 12px; }
h2 { margin: 0 0 8px 0; }
h3 { margin: 14px 0 6px 0; }
table { width: 100%; border-collapse: collapse; }
th, td { border: 1px solid #333; padding: 6px; }
th { background: #f2f2f2; }
.meta td { border: none; padding: 2px 0; }
</style></head><body>';
$html .= '<h2>NexGen HRMS Report Export</h2>';
$html .= '<table class="meta">';
foreach ($meta_lines as $line) {
    if (count($line) === 1) {
        $html .= '<tr><td><strong>' . htmlspecialchars((string)$line[0]) . '</strong></td></tr>';
    } else {
        $html .= '<tr><td><strong>' . htmlspecialchars((string)$line[0]) . ':</strong> ' . htmlspecialchars((string)$line[1]) . '</td></tr>';
    }
}
$html .= '</table>';

foreach ($sections as $s) {
    $html .= '<h3>' . htmlspecialchars($s['title']) . '</h3>';
    $html .= '<table><tbody>';
    foreach ($s['rows'] as $ri => $row) {
        $html .= '<tr>';
        foreach ($row as $cell) {
            $tag = $ri === 0 ? 'th' : 'td';
            $html .= "<{$tag}>" . htmlspecialchars((string)$cell) . "</{$tag}>";
        }
        $html .= '</tr>';
    }
    $html .= '</tbody></table>';
}

$html .= '</body></html>';

$dompdf = new \Dompdf\Dompdf(['isRemoteEnabled' => false]);
$dompdf->loadHtml($html, 'UTF-8');
$dompdf->setPaper('A4', 'portrait');
$dompdf->render();
echo $dompdf->output();
exit();

