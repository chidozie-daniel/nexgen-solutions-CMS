<?php
/**
 * Display Import Results
 * Shows detailed results of bulk import operations
 */

define('APP_ENTRY_POINT', true);
require_once '../../includes/auth.php';
require_once '../../includes/functions.php';

Auth::requireRole('admin');

$import_type = $_GET['type'] ?? 'users';
$results = $_SESSION[$import_type . '_import_results'] ?? null;

// Clear results after displaying
unset($_SESSION[$import_type . '_import_results']);

$page_title = 'Import Results';
require_once '../../includes/header.php';
?>

<div class="container-fluid">
    <div class="row mb-4">
        <div class="col">
            <h4 class="mb-0">
                <i class="bi bi-upload me-2"></i>
                <?php echo ucfirst($import_type); ?> Import Results
            </h4>
            <p class="text-muted mb-0">Detailed import report</p>
        </div>
        <div class="col-auto">
            <a href="<?php echo Auth::getBasePath(); ?>/modules/admin/users.php" class="btn btn-outline-secondary">
                <i class="bi bi-arrow-left"></i> Back to Users
            </a>
        </div>
    </div>

    <?php if (!$results): ?>
    <div class="alert alert-warning">
        <i class="bi bi-exclamation-triangle me-2"></i>
        No import results found. Please import a file first.
    </div>
    <?php else: ?>

    <!-- Summary Cards -->
    <div class="row mb-4">
        <div class="col-md-3">
            <div class="card border-0 shadow-sm">
                <div class="card-body text-center">
                    <h2 class="mb-0 text-primary"><?php echo $results['total_rows']; ?></h2>
                    <p class="text-muted mb-0">Total Rows</p>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-0 shadow-sm">
                <div class="card-body text-center">
                    <h2 class="mb-0 text-success"><?php echo count($results['success']); ?></h2>
                    <p class="text-muted mb-0">Successful</h2>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-0 shadow-sm">
                <div class="card-body text-center">
                    <h2 class="mb-0 text-danger"><?php echo count($results['errors']); ?></h2>
                    <p class="text-muted mb-0">Errors</p>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-0 shadow-sm">
                <div class="card-body text-center">
                    <h6 class="mb-0 text-muted"><?php echo $results['timestamp']; ?></h6>
                    <p class="text-muted mb-0 small">Import Time</p>
                </div>
            </div>
        </div>
    </div>

    <!-- Success Records -->
    <?php if (!empty($results['success'])): ?>
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-header bg-success bg-opacity-10 border-0">
            <h5 class="mb-0 text-success">
                <i class="bi bi-check-circle me-2"></i>
                Successfully Imported (<?php echo count($results['success']); ?>)
            </h5>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead class="bg-light">
                        <tr>
                            <th>Row</th>
                            <?php if ($import_type === 'users'): ?>
                            <th>Username</th>
                            <th>Full Name</th>
                            <th>Email</th>
                            <th>Role</th>
                            <?php elseif ($import_type === 'attendance'): ?>
                            <th>Employee ID</th>
                            <th>Name</th>
                            <th>Date</th>
                            <th>Status</th>
                            <th>Action</th>
                            <?php elseif ($import_type === 'tasks'): ?>
                            <th>Task Title</th>
                            <th>Assigned To</th>
                            <th>Due Date</th>
                            <?php endif; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($results['success'] as $record): ?>
                        <tr>
                            <td>#<?php echo $record['row']; ?></td>
                            <?php if ($import_type === 'users'): ?>
                            <td><?php echo htmlspecialchars($record['username']); ?></td>
                            <td><?php echo htmlspecialchars($record['full_name']); ?></td>
                            <td><?php echo htmlspecialchars($record['email']); ?></td>
                            <td><span class="badge bg-info"><?php echo htmlspecialchars($record['role']); ?></span></td>
                            <?php elseif ($import_type === 'attendance'): ?>
                            <td><?php echo htmlspecialchars($record['employee_id']); ?></td>
                            <td><?php echo htmlspecialchars($record['full_name']); ?></td>
                            <td><?php echo htmlspecialchars($record['date']); ?></td>
                            <td><span class="badge bg-success"><?php echo htmlspecialchars($record['status']); ?></span></td>
                            <td><span class="badge bg-primary"><?php echo ucfirst($record['action']); ?></span></td>
                            <?php elseif ($import_type === 'tasks'): ?>
                            <td><?php echo htmlspecialchars($record['title']); ?></td>
                            <td><?php echo htmlspecialchars($record['assigned_to']); ?></td>
                            <td><?php echo htmlspecialchars($record['due_date']); ?></td>
                            <?php endif; ?>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- Error Records -->
    <?php if (!empty($results['errors'])): ?>
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-header bg-danger bg-opacity-10 border-0">
            <h5 class="mb-0 text-danger">
                <i class="bi bi-x-circle me-2"></i>
                Errors (<?php echo count($results['errors']); ?>)
            </h5>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead class="bg-light">
                        <tr>
                            <th>Row</th>
                            <th>Identifier</th>
                            <th>Errors</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($results['errors'] as $error): ?>
                        <tr>
                            <td class="text-danger fw-bold">#<?php echo $error['row']; ?></td>
                            <td>
                                <?php 
                                if (isset($error['username'])) echo htmlspecialchars($error['username']);
                                elseif (isset($error['employee_id'])) echo htmlspecialchars($error['employee_id']) . ' (' . htmlspecialchars($error['date']) . ')';
                                elseif (isset($error['title'])) echo htmlspecialchars($error['title']);
                                ?>
                            </td>
                            <td>
                                <ul class="mb-0 text-danger">
                                    <?php foreach ($error['errors'] as $err): ?>
                                    <li><?php echo htmlspecialchars($err); ?></li>
                                    <?php endforeach; ?>
                                </ul>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- Actions -->
    <div class="row">
        <div class="col">
            <div class="card border-0 shadow-sm">
                <div class="card-body">
                    <h6 class="mb-3">Next Steps:</h6>
                    <div class="d-flex gap-2 flex-wrap">
                        <?php if ($import_type === 'users'): ?>
                        <a href="<?php echo Auth::getBasePath(); ?>/modules/admin/users.php" class="btn btn-primary">
                            <i class="bi bi-people"></i> View Users
                        </a>
                        <a href="<?php echo Auth::getBasePath(); ?>/modules/admin/download_csv_template.php?type=users" class="btn btn-outline-secondary">
                            <i class="bi bi-download"></i> Download Template Again
                        </a>
                        <?php elseif ($import_type === 'attendance'): ?>
                        <a href="<?php echo Auth::getBasePath(); ?>/modules/attendance/manage.php" class="btn btn-primary">
                            <i class="bi bi-calendar-check"></i> View Attendance
                        </a>
                        <?php elseif ($import_type === 'tasks'): ?>
                        <a href="<?php echo Auth::getBasePath(); ?>/modules/projects/details.php?id=<?php echo $results['project_id']; ?>" class="btn btn-primary">
                            <i class="bi bi-folder"></i> View Project
                        </a>
                        <?php endif; ?>
                        <button onclick="window.print()" class="btn btn-outline-secondary">
                            <i class="bi bi-printer"></i> Print Report
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <?php endif; ?>
</div>

<?php require_once '../../includes/footer.php'; ?>
