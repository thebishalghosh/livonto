<?php
/**
 * Owner Forgot Password Page
 * Allows owners to request password reset
 */

session_start();
require __DIR__ . '/../app/config.php';
require __DIR__ . '/../app/functions.php';

// Redirect if already logged in as owner
if (isset($_SESSION['owner_logged_in']) && $_SESSION['owner_logged_in'] === true) {
    header('Location: ' . app_url('owner/dashboard'));
    exit;
}

$pageTitle = "Owner Forgot Password";
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
            
            // Check if owner exists
            $listing = $db->fetchOne(
                "SELECT id, owner_name, owner_email, owner_password_hash FROM listings WHERE owner_email = ? LIMIT 1",
                [$email]
            );
            
            if (!$listing) {
                // Don't reveal if email exists (security best practice)
                $success = 'If an account exists with this email, a password reset link has been sent.';
            } elseif (empty($listing['owner_password_hash'])) {
                // No password set
                $error = 'No password set for this account. Please contact admin.';
            } else {
                // Generate reset token
                $resetToken = bin2hex(random_bytes(32));
                // Use UTC timezone for expiration to avoid timezone issues
                $resetExpires = gmdate('Y-m-d H:i:s', strtotime('+1 hour'));
                
                // Save token to database
                $db->execute(
                    "UPDATE listings SET owner_password_reset_token = ?, owner_password_reset_expires = ? WHERE id = ?",
                    [$resetToken, $resetExpires, $listing['id']]
                );
                
                // Generate full absolute URL for email
                $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https://' : 'http://';
                $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
                $resetPath = app_url("owner/reset-password?token={$resetToken}");
                
                // Build full absolute URL
                if (strpos($resetPath, 'http://') === 0 || strpos($resetPath, 'https://') === 0) {
                    $resetLink = $resetPath;
                } else {
                    $resetLink = $protocol . $host . $resetPath;
                }
                
                $siteName = function_exists('getSetting') ? getSetting('site_name', 'Livonto') : 'Livonto';
                
                $emailSubject = "Owner Password Reset Request - {$siteName}";
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
                            <h2>Owner Password Reset Request</h2>
                        </div>
                        <div class='content'>
                            <p>Hello " . htmlspecialchars($listing['owner_name']) . ",</p>
                            <p>We received a request to reset your owner account password. Click the button below to reset it:</p>
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
                $emailSent = sendEmail($listing['owner_email'], $emailSubject, $emailBody);
                
                if ($emailSent) {
                    $success = 'If an account exists with this email, a password reset link has been sent. Please check your inbox.';
                } else {
                    $error = 'Failed to send email. Please try again later.';
                }
            }
        } catch (Exception $e) {
            error_log("Error in owner forgot password: " . $e->getMessage());
            $error = 'An error occurred. Please try again later.';
        }
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
                <h2>Forgot Password?</h2>
                <p>Enter your email address and we'll send you a link to reset your password.</p>
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
                    <a href="<?= htmlspecialchars(app_url('owner/login')) ?>" class="text-decoration-none">
                        <i class="bi bi-arrow-left me-1"></i>Back to Login
                    </a>
                </div>
            </div>
        </div>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>

