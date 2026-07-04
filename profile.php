<?php
// Define entry point constant
define('APP_ENTRY_POINT', true);

require_once 'includes/auth.php';
require_once 'includes/functions.php';

// Check login status first
Auth::requireLogin();

$user = Auth::getCurrentUser();
$conn = getDBConnection();
$user_id = $user['id'];

// Handle profile update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
    // Verify CSRF token
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        setFlash('danger', 'Invalid security token. Please try again.');
        header('Location: profile.php');
        exit();
    }
    
    $full_name = sanitizeText($_POST['full_name'] ?? '', 120);
    $email = trim($_POST['email'] ?? '');
    
    // Validation
    $errors = [];
    if (empty($full_name) || strlen($full_name) < 2) {
        $errors[] = 'Full name is required.';
    }
    if (!isValidEmail($email)) {
        $errors[] = 'A valid email address is required.';
    }
    // Ensure email uniqueness
    if (empty($errors)) {
        $email_check_sql = "SELECT id FROM users WHERE email = ? AND id != ?";
        $email_check_stmt = $conn->prepare($email_check_sql);
        $email_check_stmt->bind_param("si", $email, $user_id);
        $email_check_stmt->execute();
        if ($email_check_stmt->get_result()->num_rows > 0) {
            $errors[] = 'Email address is already in use.';
        }
    }

    if (!empty($errors)) {
        setFlash('danger', implode('<br>', $errors));
    } else {
        $profile_image = $user['profile_image'];
        
        // Handle image upload
        if (isset($_FILES['profile_image']) && $_FILES['profile_image']['error'] === UPLOAD_ERR_OK) {
            $file_tmp = $_FILES['profile_image']['tmp_name'];
            $file_name = $_FILES['profile_image']['name'];
            $file_size = $_FILES['profile_image']['size'];
            $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
            
            $allowed_exts = ['jpg', 'jpeg', 'png', 'gif'];
            $max_size = 2 * 1024 * 1024; // 2MB
            
            if (!in_array($file_ext, $allowed_exts)) {
                setFlash('danger', 'Invalid file type. Only JPG, PNG, and GIF allowed.');
            } elseif ($file_size > $max_size) {
                setFlash('danger', 'File size too large. Maximum size is 2MB.');
            } else {
                $new_file_name = 'user_' . $user_id . '_' . time() . '.' . $file_ext;
                $upload_path = 'uploads/profile_images/' . $new_file_name;
                
                if (move_uploaded_file($file_tmp, $upload_path)) {
                    // Delete old image if not default
                    if ($user['profile_image'] !== 'default.jpg' && file_exists('uploads/profile_images/' . $user['profile_image'])) {
                        unlink('uploads/profile_images/' . $user['profile_image']);
                    }
                    $profile_image = $new_file_name;
                } else {
                    setFlash('danger', 'Failed to upload image.');
                }
            }
        }
        
        // Update database
        $sql = "UPDATE users SET full_name = ?, email = ?, profile_image = ?, updated_at = NOW() WHERE id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("sssi", $full_name, $email, $profile_image, $user_id);
        
        if ($stmt->execute()) {
            setFlash('success', 'Profile updated successfully!');
            // Update session data if needed (some data is cached in session in Auth class)
            $_SESSION['full_name'] = $full_name;
            $_SESSION['profile_image'] = $profile_image;
            header('Location: profile.php');
            exit();
        } else {
            setFlash('danger', 'Error updating profile: ' . $conn->error);
        }
    }
}

$page_title = 'My Profile';
require_once 'includes/header.php';
?>

<div class="container-fluid">
    <div class="module-hero">
        <div class="container">
            <div>
                <h1 class="mb-2">My Profile</h1>
                <p class="mb-0">Manage your personal information and profile picture</p>
            </div>
        </div>
    </div>
    
    <div class="row justify-content-center">
        <div class="col-md-8">
            <div class="card shadow-sm">
                <div class="card-body p-4">
                    <form method="POST" action="" enctype="multipart/form-data">
                        <?php echo csrfField(); ?>
                        <div class="row mb-4 align-items-center">
                            <div class="col-auto">
                                <div class="profile-preview mb-3">
                                    <img src="uploads/profile_images/<?php echo $user['profile_image']; ?>" 
                                         alt="Profile Picture" class="rounded-circle shadow" 
                                         style="width: 120px; height: 120px; object-fit: cover; border: 4px solid #fff;">
                                </div>
                            </div>
                            <div class="col">
                                <label class="form-label fw-bold">Profile Picture</label>
                                <input type="file" name="profile_image" class="form-control" accept="image/*">
                                <small class="text-muted d-block mt-1">Recommended: Square image, max 2MB (JPG, PNG, GIF)</small>
                            </div>
                        </div>
                        
                        <hr class="my-4">
                        
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label fw-bold">Full Name</label>
                                <input type="text" name="full_name" class="form-control" 
                                       value="<?php echo htmlspecialchars($user['full_name']); ?>" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-bold">Email Address</label>
                                <input type="email" name="email" class="form-control" 
                                       value="<?php echo htmlspecialchars($user['email']); ?>" required>
                            </div>
                            
                            <div class="col-md-6">
                                <label class="form-label fw-bold text-muted">Username</label>
                                <input type="text" class="form-control bg-light" value="<?php echo htmlspecialchars($user['username']); ?>" readonly>
                                <small class="text-muted">Username cannot be changed</small>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-bold text-muted">Employee ID</label>
                                <input type="text" class="form-control bg-light" value="<?php echo htmlspecialchars($user['employee_id']); ?>" readonly>
                            </div>
                            
                            <div class="col-md-6">
                                <label class="form-label fw-bold text-muted">Department</label>
                                <input type="text" class="form-control bg-light" value="<?php echo htmlspecialchars($user['department'] ?? 'N/A'); ?>" readonly>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-bold text-muted">Position</label>
                                <input type="text" class="form-control bg-light" value="<?php echo htmlspecialchars($user['position'] ?? 'N/A'); ?>" readonly>
                            </div>
                            
                            <div class="col-md-6">
                                <label class="form-label fw-bold text-muted">Role</label>
                                <div class="mt-1">
                                    <?php echo getRoleBadge($user['role']); ?>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-bold text-muted">Hire Date</label>
                                <input type="text" class="form-control bg-light" value="<?php echo formatDate($user['hire_date'], 'F d, Y'); ?>" readonly>
                            </div>
                        </div>
                        
                        <div class="mt-4 pt-3 border-top d-flex justify-content-between">
                            <p class="small text-muted mb-0 aligns-self-center">Last updated: <?php echo formatDate($user['updated_at'] ?? $user['created_at'], 'M d, Y H:i'); ?></p>
                            <button type="submit" name="update_profile" class="btn btn-primary px-4">
                                <i class="bi bi-save me-2"></i> Save Changes
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>
