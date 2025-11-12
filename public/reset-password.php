<?php
/**
 * Reset Password Page
 * Allows users to reset their password using a token
 */

session_start();
require __DIR__ . '/../app/config.php';
require __DIR__ . '/../app/functions.php';

$pageTitle = "Reset Password";
$baseUrl = app_url('');
$error = '';
$success = '';
// Get token from GET or POST (POST is used when form is submitted)
$token = trim($_GET['token'] ?? $_POST['token'] ?? '');

// Check if token is provided
if (empty($token)) {
    $error = 'Invalid or missing reset token.';
} else {
    try {
        $db = db();
        
        // First, check if token exists (without expiration check) for debugging
        $tokenCheck = $db->fetchOne(
            "SELECT id, email, password_reset_token, password_reset_expires 
             FROM users 
             WHERE password_reset_token = ? 
             LIMIT 1",
            [$token]
        );
        
        // Find user with valid token (with expiration check)
        // Use UTC_TIMESTAMP() to match the UTC timezone used when storing
        $user = $db->fetchOne(
            "SELECT id, email, password_reset_token, password_reset_expires 
             FROM users 
             WHERE password_reset_token = ? 
             AND password_reset_expires > UTC_TIMESTAMP() 
             LIMIT 1",
            [$token]
        );
        
        // Log token validation attempt for debugging (remove in production)
        if ($tokenCheck) {
            error_log("Token validation: Token exists for user {$tokenCheck['id']}, expires at {$tokenCheck['password_reset_expires']}, current UTC: " . gmdate('Y-m-d H:i:s'));
        } else {
            error_log("Token validation: Token not found in database: " . substr($token, 0, 20) . "...");
        }
        
        if (!$user) {
            if ($tokenCheck) {
                // Token exists but expired
                $error = 'This reset link has expired. Please request a new password reset link.';
            } else {
                // Token doesn't exist
                $error = 'Invalid reset token. Please request a new password reset link.';
            }
        } else {
            // Handle password reset form submission
            if ($_SERVER['REQUEST_METHOD'] === 'POST') {
                // Re-validate token on POST to prevent token reuse
                $postToken = trim($_POST['token'] ?? '');
                if (empty($postToken) || $postToken !== $token) {
                    $error = 'Invalid token. Please request a new password reset link.';
                } else {
                    // Re-verify token is still valid (use UTC_TIMESTAMP to match storage)
                    $userCheck = $db->fetchOne(
                        "SELECT id FROM users 
                         WHERE password_reset_token = ? 
                         AND password_reset_expires > UTC_TIMESTAMP() 
                         LIMIT 1",
                        [$postToken]
                    );
                    
                    if (!$userCheck || $userCheck['id'] != $user['id']) {
                        $error = 'This reset link has expired. Please request a new password reset link.';
                    } else {
                        $newPassword = $_POST['password'] ?? '';
                        $confirmPassword = $_POST['confirm_password'] ?? '';
                        
                        if (empty($newPassword)) {
                            $error = 'Please enter a new password';
                        } elseif (strlen($newPassword) < 8) {
                            $error = 'Password must be at least 8 characters long';
                        } elseif ($newPassword !== $confirmPassword) {
                            $error = 'Passwords do not match';
                        } else {
                            // Update password and clear reset token
                            $passwordHash = password_hash($newPassword, PASSWORD_DEFAULT);
                            $db->execute(
                                "UPDATE users 
                                 SET password_hash = ?, 
                                     password_reset_token = NULL, 
                                     password_reset_expires = NULL 
                                 WHERE id = ? AND password_reset_token = ?",
                                [$passwordHash, $user['id'], $postToken]
                            );
                            
                            $success = 'Password reset successfully! You can now login with your new password.';
                            $token = ''; // Clear token to prevent reuse
                            $user = null; // Clear user to prevent form display
                        }
                    }
                }
            }
        }
    } catch (Exception $e) {
        error_log("Error in reset password: " . $e->getMessage());
        $error = 'An error occurred. Please try again later.';
    }
}

require __DIR__ . '/../app/includes/header.php';
?>

<div class="container-xxl py-5">
    <div class="row justify-content-center">
        <div class="col-md-6 col-lg-5">
            <div class="card shadow-sm">
                <div class="card-body p-4">
                    <div class="text-center mb-4">
                        <h2 class="mb-2" style="color: var(--primary-700);">Reset Password</h2>
                        <p class="text-muted">Enter your new password below.</p>
                    </div>
                    
                    <?php if ($error): ?>
                        <div class="alert alert-danger alert-dismissible fade show" role="alert">
                            <?= htmlspecialchars($error) ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    <?php endif; ?>
                    
                    <?php if ($success): ?>
                        <div class="alert alert-success alert-dismissible fade show" role="alert">
                            <?= htmlspecialchars($success) ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                        <div class="text-center mt-4">
                            <a href="<?= htmlspecialchars(app_url('login')) ?>" class="btn btn-primary">
                                <i class="bi bi-box-arrow-in-right me-2"></i>Go to Login
                            </a>
                        </div>
                    <?php elseif (!empty($token) && !$error): ?>
                        <form method="POST" action="<?= htmlspecialchars($_SERVER['REQUEST_URI']) ?>">
                            <input type="hidden" name="token" value="<?= htmlspecialchars($token) ?>">
                            <div class="mb-3">
                                <label for="password" class="form-label">New Password</label>
                                <input type="password" 
                                       class="form-control" 
                                       id="password" 
                                       name="password" 
                                       required 
                                       minlength="8"
                                       autofocus>
                                <small class="text-muted">Must be at least 8 characters long</small>
                            </div>
                            
                            <div class="mb-3">
                                <label for="confirm_password" class="form-label">Confirm New Password</label>
                                <input type="password" 
                                       class="form-control" 
                                       id="confirm_password" 
                                       name="confirm_password" 
                                       required 
                                       minlength="8">
                            </div>
                            
                            <button type="submit" class="btn btn-primary w-100 mb-3">
                                <i class="bi bi-key me-2"></i>Reset Password
                            </button>
                        </form>
                        
                        <div class="text-center mt-4">
                            <a href="<?= htmlspecialchars(app_url('login')) ?>" class="text-decoration-none">
                                <i class="bi bi-arrow-left me-1"></i>Back to Login
                            </a>
                        </div>
                    <?php else: ?>
                        <div class="text-center mt-4">
                            <a href="<?= htmlspecialchars(app_url('forgot-password')) ?>" class="btn btn-primary">
                                Request New Reset Link
                            </a>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require __DIR__ . '/../app/includes/footer.php'; ?>

