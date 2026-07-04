<?php
define('APP_ENTRY_POINT', true);

require_once '../../includes/auth.php';
require_once '../../includes/functions.php';

Auth::requireRole('admin');

$page_title = 'Backup & Restore';
require_once '../../includes/header.php';

$base_url = Auth::getBasePath();
$flash = getFlash();
?>
<div class="container-fluid">
    <?php if ($flash): ?>
        <div class="alert alert-<?php echo htmlspecialchars($flash['type']); ?> alert-dismissible fade show" role="alert">
            <?php echo $flash['message']; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <div class="row mb-4">
        <div class="col">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h4 class="mb-1">Backup & Restore</h4>
                    <p class="text-muted mb-0">Download a full database backup or restore from a SQL file.</p>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-4">
        <div class="col-lg-6">
            <div class="card h-100">
                <div class="card-header">
                    <h6 class="mb-0"><i class="bi bi-download me-2"></i>Database Backup</h6>
                </div>
                <div class="card-body">
                    <p class="text-muted small mb-3">
                        This generates a SQL file containing schema + data for the current database.
                        Store it somewhere safe.
                    </p>

                    <form method="POST" action="backup_download.php">
                        <?php echo csrfField(); ?>
                        <div class="form-check mb-2">
                            <input class="form-check-input" type="checkbox" name="include_data" id="includeData" checked>
                            <label class="form-check-label" for="includeData">Include table data (recommended)</label>
                        </div>
                        <div class="form-check mb-3">
                            <input class="form-check-input" type="checkbox" name="drop_tables" id="dropTables">
                            <label class="form-check-label" for="dropTables">Include DROP TABLE statements</label>
                        </div>
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-cloud-arrow-down me-2"></i>Download Backup (.sql)
                        </button>
                    </form>
                </div>
            </div>
        </div>

        <div class="col-lg-6">
            <div class="card h-100">
                <div class="card-header">
                    <h6 class="mb-0"><i class="bi bi-upload me-2"></i>Database Restore</h6>
                </div>
                <div class="card-body">
                    <div class="alert alert-warning">
                        <strong>Warning:</strong> Restoring will execute SQL statements from the uploaded file.
                        Only upload trusted backups.
                    </div>

                    <form method="POST" action="backup_upload.php" enctype="multipart/form-data">
                        <?php echo csrfField(); ?>
                        <div class="mb-3">
                            <label class="form-label">SQL File</label>
                            <input type="file" class="form-control" name="sql_file" accept=".sql,text/sql" required>
                            <div class="form-text">Max recommended size: 20MB</div>
                        </div>
                        <div class="form-check mb-3">
                            <input class="form-check-input" type="checkbox" name="confirm_restore" id="confirmRestore" required>
                            <label class="form-check-label" for="confirmRestore">
                                I understand this will modify the database
                            </label>
                        </div>
                        <button type="submit" class="btn btn-danger">
                            <i class="bi bi-arrow-repeat me-2"></i>Restore Database
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once '../../includes/footer.php'; ?>

