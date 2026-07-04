<?php
define('APP_ENTRY_POINT', true);
require_once '../../includes/auth.php';
require_once '../../includes/functions.php';

Auth::requireLogin();

if (!Auth::hasRole('hr') && !Auth::hasRole('admin')) {
    setFlash('danger', 'You do not have permission to manage inquiries.');
    header('Location: ' . Auth::getBasePath() . '/dashboard.php');
    exit();
}

$conn = getDBConnection();
$page_title = 'Add New Inquiry';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_inquiry'])) {
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        setFlash('danger', 'Invalid security token. Please try again.');
        header('Location: manage.php');
        exit();
    }

    $name = sanitizeText($_POST['name'] ?? '', 100);
    $email = trim($_POST['email'] ?? '');
    $phone = sanitizeText($_POST['phone'] ?? '', 20);
    $service = sanitizeText($_POST['service'] ?? '', 100);
    $message = sanitizeText($_POST['message'] ?? '', 4000, true);
    $notes = sanitizeText($_POST['notes'] ?? '', 4000, true);

    $errors = [];
    if ($name === '') $errors[] = 'Name is required.';
    if (!isValidEmail($email)) $errors[] = 'Valid email is required.';
    if ($service === '') $errors[] = 'Service is required.';
    if ($message === '' || strlen($message) < 5) $errors[] = 'Message is required (min 5 characters).';

    if (!empty($errors)) {
        setFlash('danger', implode('<br>', $errors));
    } else {
        $sql = "INSERT INTO inquiries (name, email, phone, service, message, status, assigned_to, notes)
                VALUES (?, ?, ?, ?, ?, 'new', ?, ?)";
        $stmt = $conn->prepare($sql);
        $assigned_to = (int)($_SESSION['user_id'] ?? 0);
        $stmt->bind_param("sssssis", $name, $email, $phone, $service, $message, $assigned_to, $notes);
        if ($stmt->execute()) {
            setFlash('success', 'Inquiry created successfully.');
            header('Location: ' . Auth::getBasePath() . '/modules/inquiries/view.php?id=' . $conn->insert_id);
            exit();
        }
        setFlash('danger', 'Failed to create inquiry.');
    }
}

require_once '../../includes/header.php';
?>

<div class="container-fluid">
    <div class="row mb-4">
        <div class="col">
            <div class="d-flex justify-content-between align-items-center">
                <h4 class="mb-0">Add New Inquiry</h4>
                <a href="<?php echo $base_url; ?>/modules/inquiries/list.php" class="btn btn-outline-secondary">
                    <i class="bi bi-arrow-left"></i> Back to List
                </a>
            </div>
            <p class="text-muted mb-0">Create an inquiry manually (phone call, walk-in, referral)</p>
        </div>
    </div>

    <div class="row justify-content-center">
        <div class="col-lg-8">
            <div class="card shadow-sm">
                <div class="card-body p-4">
                    <form method="POST" action="">
                        <?php echo csrfField(); ?>

                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label fw-bold">Name</label>
                                <input type="text" name="name" class="form-control" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-bold">Email</label>
                                <input type="email" name="email" class="form-control" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-bold">Phone (optional)</label>
                                <input type="text" name="phone" class="form-control">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-bold">Service</label>
                                <input type="text" name="service" class="form-control" required>
                            </div>
                            <div class="col-12">
                                <label class="form-label fw-bold">Message</label>
                                <textarea name="message" class="form-control" rows="5" required></textarea>
                            </div>
                            <div class="col-12">
                                <label class="form-label fw-bold">Notes <span class="text-muted">(Internal - Not visible to customer)</span></label>
                                <textarea name="notes" class="form-control" rows="3" placeholder="Add any internal notes or context..."></textarea>
                            </div>
                        </div>

                        <div class="d-flex gap-2 mt-4">
                            <button type="submit" name="create_inquiry" class="btn btn-primary">
                                <i class="bi bi-save me-1"></i> Create Inquiry
                            </button>
                            <a href="<?php echo $base_url; ?>/modules/inquiries/list.php" class="btn btn-outline-secondary">
                                Cancel
                            </a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once '../../includes/footer.php'; ?>

