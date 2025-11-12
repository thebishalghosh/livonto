<?php
/**
 * Owner Reset Password Page
 * Allows owners to reset their password using a token
 */

session_start();
require __DIR__ . '/../app/config.php';
require __DIR__ . '/../app/functions.php';

// Redirect if already logged in as owner
if (isset($_SESSION['owner_logged_in']) && $_SESSION['owner_logged_in'] === true) {
    header('Location: ' . app_url('owner/dashboard'));
    exit;
}

$pageTitle = "Owner Reset Password";
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
        
        // Check if token exists (without expiration check) to provide better error messages
        $tokenCheck = $db->fetchOne(
            "SELECT id, owner_name, owner_email, owner_password_reset_token, owner_password_reset_expires 
             FROM listings 
             WHERE owner_password_reset_token = ? 
             LIMIT 1",
            [$token]
        );
        
        // Find listing with valid token (with expiration check)
        // Use UTC_TIMESTAMP() to match the UTC timezone used when storing
        $listing = $db->fetchOne(
            "SELECT id, owner_name, owner_email, owner_password_reset_token, owner_password_reset_expires 
             FROM listings 
             WHERE owner_password_reset_token = ? 
             AND owner_password_reset_expires > UTC_TIMESTAMP() 
             LIMIT 1",
            [$token]
        );
        
        if (!$listing) {
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
                    $listingCheck = $db->fetchOne(
                        "SELECT id FROM listings 
                         WHERE owner_password_reset_token = ? 
                         AND owner_password_reset_expires > UTC_TIMESTAMP() 
                         LIMIT 1",
                        [$postToken]
                    );
                    
                    if (!$listingCheck || $listingCheck['id'] != $listing['id']) {
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
                                "UPDATE listings 
                                 SET owner_password_hash = ?, 
                                     owner_password_reset_token = NULL, 
                                     owner_password_reset_expires = NULL 
                                 WHERE id = ? AND owner_password_reset_token = ?",
                                [$passwordHash, $listing['id'], $postToken]
                            );
                            
                            $success = 'Password reset successfully! You can now login with your new password.';
                            $token = ''; // Clear token to prevent reuse
                            $listing = null; // Clear listing to prevent form display
                        }
                    }
                }
            }
        }
    } catch (Exception $e) {
        $error = 'An error occurred. Please try again later.';
    }
}

// Get baseUrl for assets
$baseUrl = app_url('');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= htmlspecialchars($pageTitle) ?></title>
    
    <!-- Bootstrap 5 -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    
    <!-- Website Styles -->
    <?php 
    $cssBasePath = ($baseUrl === '' || $baseUrl === '/') ? '/public/assets/css/' : ($baseUrl . '/public/assets/css/');
    if (substr($cssBasePath, 0, 1) !== '/') {
        $cssBasePath = '/' . ltrim($cssBasePath, '/');
    }
    ?>
    <link rel="stylesheet" href="<?= htmlspecialchars($cssBasePath . 'styles.css') ?>">
    
    <style>
        :root {
            --primary: #8b6bd1;
            --primary-700: #6f55b2;
        }
        
        body {
            background: #f8f9fa;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 2rem 1rem;
        }
        
        .login-wrapper {
            width: 100%;
            max-width: 450px;
        }
        
        .login-card {
            background: white;
            border-radius: 12px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.08);
            border: 1px solid rgba(139, 107, 209, 0.1);
            overflow: hidden;
        }
        
        .login-header {
            background: linear-gradient(90deg, var(--primary) 0%, var(--primary-700) 100%);
            color: white;
            padding: 2rem;
            text-align: center;
        }
        
        .login-header img {
            filter: brightness(0) invert(1);
            margin-bottom: 1rem;
        }
        
        .login-header h2 {
            color: white;
            font-size: 1.5rem;
            font-weight: 600;
            margin-bottom: 0.5rem;
        }
        
        .login-header p {
            color: rgba(255,255,255,0.9);
            margin: 0;
            font-size: 0.9rem;
        }
        
        .login-body {
            padding: 2rem;
        }
        
        .form-label {
            font-weight: 500;
            color: #333;
            margin-bottom: 0.5rem;
            font-size: 0.9rem;
        }
        
        .form-control {
            border-radius: 8px;
            border: 1.5px solid #e0e0e0;
            padding: 0.75rem 1rem;
            transition: all 0.2s ease;
        }
        
        .form-control:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 0.2rem rgba(139, 107, 209, 0.15);
        }
        
        .btn-primary {
            background: linear-gradient(90deg, var(--primary) 0%, var(--primary-700) 100%);
            border: none;
            border-radius: 8px;
            padding: 0.75rem 1.5rem;
            font-weight: 500;
            transition: all 0.2s ease;
        }
        
        .btn-primary:hover {
            background: linear-gradient(90deg, var(--primary-700) 0%, var(--primary) 100%);
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(139, 107, 209, 0.3);
        }
        
        .alert {
            border-radius: 8px;
            border: none;
        }
        
        .text-muted a {
            color: var(--primary);
            text-decoration: none;
        }
        
        .text-muted a:hover {
            color: var(--primary-700);
            text-decoration: underline;
        }
    </style>
</head>
<body>
    <div class="login-wrapper">
        <div class="card login-card">
            <div class="login-header">
                <?php 
                $logoPath = ($baseUrl === '' || $baseUrl === '/') ? '/public/assets/images/logo-white-removebg.png' : ($baseUrl . '/public/assets/images/logo-white-removebg.png');
                if (substr($logoPath, 0, 1) !== '/') {
                    $logoPath = '/' . ltrim($logoPath, '/');
                }
                ?>
                <img src="<?= htmlspecialchars($logoPath) ?>" 
                     alt="Livonto" 
                     style="max-height: 60px; width: auto;">
                <h2>Reset Password</h2>
                <p>Enter your new password below.</p>
            </div>
            
            <div class="login-body">
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
                        <a href="<?= htmlspecialchars(app_url('owner/login')) ?>" class="btn btn-primary">
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
                        <a href="<?= htmlspecialchars(app_url('owner/login')) ?>" class="text-decoration-none">
                            <i class="bi bi-arrow-left me-1"></i>Back to Login
                        </a>
                    </div>
                <?php else: ?>
                    <div class="text-center mt-4">
                        <a href="<?= htmlspecialchars(app_url('owner/forgot-password')) ?>" class="btn btn-primary">
                            Request New Reset Link
                        </a>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>

