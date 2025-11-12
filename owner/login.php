<?php
/**
 * Owner Login Page
 * Allows property owners to login and manage their listings
 */

session_start();
require __DIR__ . '/../app/config.php';
require __DIR__ . '/../app/functions.php';

// Redirect if already logged in as owner
if (isset($_SESSION['owner_logged_in']) && $_SESSION['owner_logged_in'] === true) {
    header('Location: ' . app_url('owner/dashboard'));
    exit;
}

$pageTitle = "Owner Login";
$error = '';
$success = '';

// Handle login form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    
    if (empty($email) || empty($password)) {
        $error = 'Please enter both email and password';
    } else {
        try {
            $db = db();
            
            // Find listing by owner email
            $listing = $db->fetchOne(
                "SELECT id, owner_name, owner_email, owner_password_hash 
                 FROM listings 
                 WHERE owner_email = ? 
                 LIMIT 1",
                [$email]
            );
            
            if (!$listing) {
                $error = 'Invalid email or password';
            } elseif (empty($listing['owner_password_hash'])) {
                $error = 'No password set for this account. Please contact admin.';
            } elseif (!password_verify($password, $listing['owner_password_hash'])) {
                $error = 'Invalid email or password';
            } else {
                // Login successful
                session_regenerate_id(true);
                
                $_SESSION['owner_logged_in'] = true;
                $_SESSION['owner_listing_id'] = $listing['id'];
                $_SESSION['owner_name'] = $listing['owner_name'];
                $_SESSION['owner_email'] = $listing['owner_email'];
                $_SESSION['owner_login_time'] = time();
                
                header('Location: ' . app_url('owner/dashboard'));
                exit;
            }
        } catch (Exception $e) {
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
                // Ensure logo path is always absolute
                $logoPath = ($baseUrl === '' || $baseUrl === '/') ? '/public/assets/images/logo-white-removebg.png' : ($baseUrl . '/public/assets/images/logo-white-removebg.png');
                if (substr($logoPath, 0, 1) !== '/') {
                    $logoPath = '/' . ltrim($logoPath, '/');
                }
                ?>
                <img src="<?= htmlspecialchars($logoPath) ?>" 
                     alt="Livonto" 
                     style="max-height: 60px; width: auto;">
                <h2>Owner Login</h2>
                <p>Sign in to manage your property listings</p>
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
                        <input type="email" class="form-control" id="email" name="email" 
                               value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="password" class="form-label">Password</label>
                        <input type="password" class="form-control" id="password" name="password" required>
                    </div>
                    
                    <button type="submit" class="btn btn-primary w-100 mb-3">
                        <i class="bi bi-box-arrow-in-right me-2"></i>Sign In
                    </button>
                </form>
                
                <div class="text-center mt-4">
                    <small class="text-muted d-block mb-2">
                        Need help? <a href="<?= app_url('contact') ?>">Contact Support</a>
                    </small>
                    <small class="text-muted">
                        <a href="<?= app_url('') ?>">
                            <i class="bi bi-arrow-left me-1"></i>Back to Website
                        </a>
                    </small>
                </div>
            </div>
        </div>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>

