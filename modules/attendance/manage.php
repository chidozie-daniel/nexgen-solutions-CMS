<?php
define('APP_ENTRY_POINT', true);
require_once '../../includes/auth.php';
require_once '../../includes/functions.php';

Auth::requireRole(['admin', 'hr']);

$page_title = 'Attendance Management';
$conn = getDBConnection();

$date_from = $_GET['date_from'] ?? '';
$date_to = $_GET['date_to'] ?? '';

$where = [];
$params = [];
$types = '';

if ($date_from !== '' && isValidDate($date_from, 'Y-m-d')) {
    $where[] = "a.date >= ?";
    $params[] = $date_from;
    $types .= 's';
}

if ($date_to !== '' && isValidDate($date_to, 'Y-m-d')) {
    $where[] = "a.date <= ?";
    $params[] = $date_to;
    $types .= 's';
}

$sql = "SELECT a.*, u.full_name, u.employee_id, u.department
        FROM attendance a
        JOIN users u ON a.user_id = u.id";

if (!empty($where)) {
    $sql .= " WHERE " . implode(' AND ', $where);
}

$sql .= " ORDER BY a.date DESC, u.full_name ASC LIMIT 200";

$result = null;
$query_error = null;
try {
    if (!empty($params)) {
        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            throw new Exception($conn->error);
        }
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $result = $stmt->get_result();
    } else {
        $result = $conn->query($sql);
    }
} catch (Throwable $e) {
    $query_error = $e->getMessage();
}

require_once '../../includes/header.php';
?>

<div class="container-fluid">
    <div class="module-hero">
        <div class="container">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h1 class="mb-2">Attendance</h1>
                    <p class="mb-0">Import and review attendance records</p>
                </div>
                <a class="btn btn-outline-light" href="<?php echo $base_url; ?>/modules/admin/download_csv_template.php?type=attendance">
                    <i class="bi bi-download"></i> Download CSV Template
                </a>
            </div>
        </div>
    </div>

    <div class="row g-4">
        <div class="col-lg-4">
            <div class="card">
                <div class="card-header bg-white">
                    <h5 class="mb-0"><i class="bi bi-cloud-arrow-up me-2"></i>Bulk Import</h5>
                </div>
                <div class="card-body">
                    <form method="POST" action="<?php echo $base_url; ?>/modules/attendance/import_attendance.php" enctype="multipart/form-data">
                        <?php echo csrfField(); ?>

                        <div class="mb-3">
                            <label class="form-label fw-bold">CSV File</label>
                            <input type="file" name="csv_file" class="form-control" accept=".csv" required>
                            <small class="text-muted d-block mt-1">Max 10MB. Columns: employee_id,date,check_in,check_out,status,notes</small>
                        </div>

                        <div class="form-check mb-3">
                            <input class="form-check-input" type="checkbox" name="overwrite_existing" id="overwrite_existing" value="1">
                            <label class="form-check-label" for="overwrite_existing">
                                Overwrite existing records (same employee + date)
                            </label>
                        </div>

                        <div class="d-grid gap-2">
                            <button type="submit" class="btn btn-primary">
                                <i class="bi bi-upload me-2"></i>Import Attendance
                            </button>
                            <a class="btn btn-outline-secondary" href="<?php echo $base_url; ?>/modules/attendance/manage.php">
                                Reset
                            </a>
                        </div>
                    </form>
                </div>
            </div>

            <div class="card mt-4">
                <div class="card-header bg-white">
                    <h6 class="mb-0"><i class="bi bi-funnel me-2"></i>Filters</h6>
                </div>
                <div class="card-body">
                    <form method="GET" action="">
                        <div class="mb-3">
                            <label class="form-label">Date From</label>
                            <input type="date" class="form-control" name="date_from" value="<?php echo htmlspecialchars($date_from); ?>">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Date To</label>
                            <input type="date" class="form-control" name="date_to" value="<?php echo htmlspecialchars($date_to); ?>">
                        </div>
                        <div class="d-grid">
                            <button type="submit" class="btn btn-outline-primary">Apply</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <div class="col-lg-8">
            <div class="card">
                <div class="card-header bg-white d-flex justify-content-between align-items-center">
                    <h5 class="mb-0"><i class="bi bi-table me-2"></i>Recent Records</h5>
                    <span class="text-muted small">Showing up to 200 rows</span>
                </div>
                <div class="card-body p-0">
                    <?php if ($query_error): ?>
                        <div class="alert alert-warning m-3">
                            Attendance table not available or query failed: <?php echo htmlspecialchars($query_error); ?>
                        </div>
                    <?php elseif ($result && $result->num_rows > 0): ?>
                        <div class="table-responsive">
                            <table class="table table-hover mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th>Date</th>
                                        <th>Employee</th>
                                        <th>Department</th>
                                        <th>Status</th>
                                        <th>Check In</th>
                                        <th>Check Out</th>
                                        <th class="text-end">Hours</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php while ($row = $result->fetch_assoc()): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($row['date']); ?></td>
                                            <td>
                                                <div class="fw-bold"><?php echo htmlspecialchars($row['full_name']); ?></div>
                                                <div class="text-muted small"><?php echo htmlspecialchars($row['employee_id']); ?></div>
                                            </td>
                                            <td><?php echo htmlspecialchars($row['department'] ?? ''); ?></td>
                                            <td><?php echo getStatusBadge($row['status']); ?></td>
                                            <td><?php echo htmlspecialchars($row['check_in'] ?? ''); ?></td>
                                            <td><?php echo htmlspecialchars($row['check_out'] ?? ''); ?></td>
                                            <td class="text-end"><?php echo $row['working_hours'] !== null ? number_format((float)$row['working_hours'], 2) : ''; ?></td>
                                        </tr>
                                    <?php endwhile; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <div class="text-center py-5">
                            <i class="bi bi-calendar2-check h1 text-muted d-block mb-2"></i>
                            <div class="text-muted">No attendance records found for the selected filters.</div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once '../../includes/footer.php'; ?>

