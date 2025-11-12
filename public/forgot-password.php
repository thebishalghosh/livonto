<?php
/**
 * Forgot Password Page
 * Allows users to request password reset
 */

session_start();
require __DIR__ . '/../app/config.php';
require __DIR__ . '/../app/functions.php';

$pageTitle = "Forgot Password";
$baseUrl = app_url('');
$error = '';
$success = '';
$email = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    
    if (empty($email)) {
        $error = 'Please enter your email address';
    } elseif (!isValidEmail($email)) {
        $error = 'Please enter a valid email address';
    } else {
        try {
            $db = db();
            
            // Check if user exists
            $user = $db->fetchOne(
                "SELECT id, name, email, password_hash, google_id FROM users WHERE email = ? LIMIT 1",
                [$email]
            );
            
            if (!$user) {
                // Don't reveal if email exists (security best practice)
                $success = 'If an account exists with this email, a password reset link has been sent.';
            } elseif (!empty($user['google_id']) && empty($user['password_hash'])) {
                // Google-only account
                $error = 'This account uses Google login. Please sign in with Google instead.';
            } else {
                // Generate reset token
                $resetToken = bin2hex(random_bytes(32));
                // Use UTC timezone for expiration to avoid timezone issues
                $resetExpires = gmdate('Y-m-d H:i:s', strtotime('+1 hour'));
                
                // Save token to database
                $db->execute(
                    "UPDATE users SET password_reset_token = ?, password_reset_expires = ? WHERE id = ?",
                    [$resetToken, $resetExpires, $user['id']]
                );
                
                // Generate full absolute URL for email
                $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https://' : 'http://';
                $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
                $basePath = app_url('');
                $resetPath = app_url("reset-password?token={$resetToken}");
                
                // Build full absolute URL
                if (strpos($resetPath, 'http://') === 0 || strpos($resetPath, 'https://') === 0) {
                    $resetLink = $resetPath;
                } else {
                    $resetLink = $protocol . $host . $resetPath;
                }
                
                $siteName = function_exists('getSetting') ? getSetting('site_name', 'Livonto') : 'Livonto';
                
                $emailSubject = "Password Reset Request - {$siteName}";
                $emailBody = "
                <!DOCTYPE html>
                <html>
                <head>
                    <meta charset='UTF-8'>
                    <meta name='viewport' content='width=device-width, initial-scale=1.0'>
                    <style>
                        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; margin: 0; padding: 0; }
                        .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                        .header { background: linear-gradient(90deg, #8b6bd1 0%, #6f55b2 100%); color: white; padding: 30px; text-align: center; border-radius: 8px 8px 0 0; }
                        .content { background: #f8f9fa; padding: 30px; border-radius: 0 0 8px 8px; }
                        .button { 
                            display: inline-block; 
                            background: #8b6bd1 !important; 
                            background: linear-gradient(90deg, #8b6bd1 0%, #6f55b2 100%) !important; 
                            color: white !important; 
                            padding: 12px 30px; 
                            text-decoration: none !important; 
                            border-radius: 6px; 
                            margin: 20px 0; 
                            font-weight: bold;
                            border: none;
                        }
                        .button:hover {
                            background: #6f55b2 !important;
                        }
                        .footer { text-align: center; margin-top: 20px; color: #666; font-size: 12px; }
                    </style>
                </head>
                <body>
                    <div class='container'>
                        <div class='header'>
                            <h2>Password Reset Request</h2>
                        </div>
                        <div class='content'>
                            <p>Hello " . htmlspecialchars($user['name']) . ",</p>
                            <p>We received a request to reset your password. Click the button below to reset it:</p>
                            <p style='text-align: center;'>
                                <a href='" . htmlspecialchars($resetLink) . "' class='button' style='background: #8b6bd1 !important; color: white !important; text-decoration: none !important; display: inline-block; padding: 12px 30px; border-radius: 6px; font-weight: bold;'>Reset Password</a>
                            </p>
                            <p>Or copy and paste this link into your browser:</p>
                            <p style='word-break: break-all; color: #8b6bd1;'>" . htmlspecialchars($resetLink) . "</p>
                            <p><strong>This link will expire in 1 hour.</strong></p>
                            <p>If you didn't request this, please ignore this email. Your password will remain unchanged.</p>
                        </div>
                        <div class='footer'>
                            <p>&copy; " . date('Y') . " {$siteName}. All rights reserved.</p>
                        </div>
                    </div>
                </body>
                </html>
                ";
                
                require_once __DIR__ . '/../app/email_helper.php';
                $emailSent = sendEmail($user['email'], $emailSubject, $emailBody);
                
                if ($emailSent) {
                    $success = 'If an account exists with this email, a password reset link has been sent. Please check your inbox.';
                } else {
                    $error = 'Failed to send email. Please try again later.';
                }
            }
        } catch (Exception $e) {
            error_log("Error in forgot password: " . $e->getMessage());
            $error = 'An error occurred. Please try again later.';
        }
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
                        <h2 class="mb-2" style="color: var(--primary-700);">Forgot Password?</h2>
                        <p class="text-muted">Enter your email address and we'll send you a link to reset your password.</p>
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
                    <?php endif; ?>
                    
                    <form method="POST" action="">
                        <div class="mb-3">
                            <label for="email" class="form-label">Email Address</label>
                            <input type="email" 
                                   class="form-control" 
                                   id="email" 
                                   name="email" 
                                   value="<?= htmlspecialchars($email) ?>" 
                                   required 
                                   autofocus>
                        </div>
                        
                        <button type="submit" class="btn btn-primary w-100 mb-3">
                            <i class="bi bi-envelope me-2"></i>Send Reset Link
                        </button>
                    </form>
                    
                    <div class="text-center mt-4">
                        <a href="<?= htmlspecialchars(app_url('index')) ?>" class="text-decoration-none">
                            <i class="bi bi-arrow-left me-1"></i>Back to Login
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require __DIR__ . '/../app/includes/footer.php'; ?>

