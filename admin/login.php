<?php
// admin/login.php
session_start();

require __DIR__ . '/../app/config.php';

// Redirect if already logged in as admin
if (!empty($_SESSION['user_id']) && $_SESSION['user_role'] === 'admin') {
    header('Location: ' . app_url('admin'));
    exit;
}
// Get baseUrl - ensure it's the actual value, not app_url('') which returns '/' for empty
global $baseUrl;
if (!isset($baseUrl)) {
    $baseUrl = getenv('LIVONTO_BASE_URL') ?: '';
    if (empty($baseUrl)) {
        // Auto-detect baseUrl
        $scriptName = $_SERVER['SCRIPT_NAME'] ?? '';
        $requestUri = $_SERVER['REQUEST_URI'] ?? '';
        $documentRoot = $_SERVER['DOCUMENT_ROOT'] ?? '';
        $projectRoot = dirname(__DIR__);
        
        $scriptDir = dirname($scriptName);
        
        // Check REQUEST_URI
        if (preg_match('#^/([^/]+)/#', $requestUri, $matches)) {
            $potentialBase = '/' . $matches[1];
            if (is_dir($documentRoot . $potentialBase) && file_exists($documentRoot . $potentialBase . '/index.php')) {
                $baseUrl = $potentialBase;
            }
        }
        
        // Calculate from project root
        if (empty($baseUrl)) {
            $relativePath = str_replace($documentRoot, '', $projectRoot);
            $relativePath = str_replace('\\', '/', $relativePath);
            $relativePath = trim($relativePath, '/');
            $baseUrl = !empty($relativePath) ? '/' . $relativePath : '';
        }
        
        // From SCRIPT_NAME
        if (empty($baseUrl) || $baseUrl === '/') {
            if ($scriptDir === '/' || $scriptDir === '\\' || $scriptDir === '.') {
                $baseUrl = '';
            } else {
                $baseUrl = rtrim($scriptDir, '/');
            }
        }
        
        // Fallback
        if (empty($baseUrl) && strpos($requestUri, '/Livonto/') !== false) {
            $baseUrl = '/Livonto';
        }
    }
    $baseUrl = rtrim($baseUrl, '/');
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Admin Login - Livonto</title>
    
    <!-- Favicon -->
    <?php 
    // Ensure favicon paths are always absolute
    $faviconPath = ($baseUrl === '' || $baseUrl === '/') ? '/public/assets/images/favicon.ico' : ($baseUrl . '/public/assets/images/favicon.ico');
    if (substr($faviconPath, 0, 1) !== '/') {
        $faviconPath = '/' . ltrim($faviconPath, '/');
    }
    ?>
    <link rel="icon" type="image/x-icon" href="<?= htmlspecialchars($faviconPath) ?>">
    <link rel="shortcut icon" type="image/x-icon" href="<?= htmlspecialchars($faviconPath) ?>">
    
    <!-- Bootstrap 5 -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    
    <!-- Admin Styles -->
    <?php 
    // Ensure admin CSS path is always absolute (start with /)
    $adminCssPath = ($baseUrl === '' || $baseUrl === '/') ? '/admin/assets/css/admin.css' : ($baseUrl . '/admin/assets/css/admin.css');
    if (substr($adminCssPath, 0, 1) !== '/') {
        $adminCssPath = '/' . ltrim($adminCssPath, '/');
    }
    ?>
    <link rel="stylesheet" href="<?= htmlspecialchars($adminCssPath) ?>">
</head>
<body class="admin-login-page">
    <div class="login-container">
        <div class="login-card">
            <!-- Logo Section -->
            <div class="login-logo">
                <?php 
                // Ensure logo path is always absolute
                $logoPath = ($baseUrl === '' || $baseUrl === '/') ? '/public/assets/images/logo-removebg.png' : ($baseUrl . '/public/assets/images/logo-removebg.png');
                if (substr($logoPath, 0, 1) !== '/') {
                    $logoPath = '/' . ltrim($logoPath, '/');
                }
                ?>
                <img src="<?= htmlspecialchars($logoPath) ?>" 
                     alt="Livonto" 
                     class="logo-img">
            </div>

            <!-- Login Form -->
            <?php 
            // Ensure form action URL is correct
            $loginActionUrl = ($baseUrl === '' || $baseUrl === '/') ? '/app/login_action.php' : ($baseUrl . '/app/login_action.php');
            if (substr($loginActionUrl, 0, 1) !== '/') {
                $loginActionUrl = '/' . ltrim($loginActionUrl, '/');
            }
            ?>
            <form id="adminLoginForm" method="POST" action="<?= htmlspecialchars($loginActionUrl) ?>" novalidate>
                <div id="loginAlert" class="mb-3"></div>

                <div class="form-group mb-3">
                    <label for="email" class="form-label">
                        <i class="bi bi-envelope me-2"></i>Email Address
                    </label>
                    <input type="email" 
                           class="form-control form-control-lg" 
                           id="email" 
                           name="email" 
                           placeholder="admin@livonto.com" 
                           required 
                           autocomplete="username"
                           autofocus>
                    <div class="invalid-feedback" id="emailFeedback"></div>
                </div>

                <div class="form-group mb-4">
                    <label for="password" class="form-label">
                        <i class="bi bi-lock me-2"></i>Password
                    </label>
                    <div class="password-input-wrapper">
                        <input type="password" 
                               class="form-control form-control-lg" 
                               id="password" 
                               name="password" 
                               placeholder="Enter your password" 
                               required 
                               autocomplete="current-password">
                        <button type="button" 
                                class="password-toggle" 
                                id="togglePassword" 
                                aria-label="Toggle password visibility">
                            <i class="bi bi-eye" id="toggleIcon"></i>
                        </button>
                    </div>
                    <div class="invalid-feedback" id="passwordFeedback"></div>
                </div>

                <div class="form-group mb-4">
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" id="remember" name="remember" value="1">
                        <label class="form-check-label" for="remember">
                            Remember me
                        </label>
                    </div>
                </div>

                <input type="hidden" name="admin_login" value="1">
                <input type="hidden" name="redirect" value="<?= htmlspecialchars(app_url('admin')) ?>">

                <button type="submit" class="btn btn-primary btn-lg w-100 mb-3" id="loginSubmit">
                    <span id="loginSpinner" class="spinner-border spinner-border-sm me-2 d-none" role="status" aria-hidden="true"></span>
                    <i class="bi bi-box-arrow-in-right me-2"></i>Sign In
                </button>

                <div class="text-center">
                    <?php 
                    // Ensure back link URL is correct
                    $backLinkUrl = ($baseUrl === '' || $baseUrl === '/') ? '/' : ($baseUrl . '/');
                    if (substr($backLinkUrl, 0, 1) !== '/') {
                        $backLinkUrl = '/' . ltrim($backLinkUrl, '/');
                    }
                    ?>
                    <a href="<?= htmlspecialchars($backLinkUrl) ?>" class="back-link">
                        <i class="bi bi-arrow-left me-1"></i>Back to Website
                    </a>
                </div>
            </form>
        </div>

        <!-- Decorative Elements -->
        <div class="login-decoration">
            <div class="decoration-circle circle-1"></div>
            <div class="decoration-circle circle-2"></div>
            <div class="decoration-circle circle-3"></div>
        </div>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        // Password toggle
        (function() {
            const toggleBtn = document.getElementById('togglePassword');
            const passwordInput = document.getElementById('password');
            const toggleIcon = document.getElementById('toggleIcon');
            
            if (toggleBtn && passwordInput) {
                toggleBtn.addEventListener('click', function() {
                    const type = passwordInput.getAttribute('type') === 'password' ? 'text' : 'password';
                    passwordInput.setAttribute('type', type);
                    
                    if (toggleIcon) {
                        toggleIcon.classList.toggle('bi-eye');
                        toggleIcon.classList.toggle('bi-eye-slash');
                    }
                });
            }
        })();

        // Form validation and AJAX submission
        (function() {
            const form = document.getElementById('adminLoginForm');
            const submitBtn = document.getElementById('loginSubmit');
            const spinner = document.getElementById('loginSpinner');
            const alertContainer = document.getElementById('loginAlert');

            function showAlert(type, message) {
                if (!alertContainer) return;
                alertContainer.innerHTML = 
                    '<div class="alert alert-' + type + ' alert-dismissible fade show" role="alert">' +
                    message +
                    '<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>' +
                    '</div>';
            }

            function clearValidation() {
                form.querySelectorAll('.is-invalid').forEach(el => el.classList.remove('is-invalid'));
                form.querySelectorAll('.invalid-feedback').forEach(el => el.textContent = '');
                if (alertContainer) alertContainer.innerHTML = '';
            }

            if (form) {
                form.addEventListener('submit', async function(e) {
                    e.preventDefault();
                    clearValidation();

                    // Disable submit button
                    if (submitBtn) submitBtn.disabled = true;
                    if (spinner) spinner.classList.remove('d-none');

                    const formData = new FormData(form);

                    try {
                        const response = await fetch(form.action, {
                            method: 'POST',
                            body: formData,
                            credentials: 'include',
                            headers: {
                                'Accept': 'application/json'
                            }
                        });

                        // Check if response is OK
                        if (!response.ok) {
                            const errorText = await response.text();
                            try {
                                const errorData = JSON.parse(errorText);
                                showAlert('danger', errorData.message || 'An error occurred. Please try again.');
                            } catch (e) {
                                showAlert('danger', 'Server error. Please try again.');
                            }
                            if (submitBtn) submitBtn.disabled = false;
                            if (spinner) spinner.classList.add('d-none');
                            return;
                        }

                        const data = await response.json();

                        if (data.status === 'ok' || data.status === 'success') {
                            showAlert('success', data.message || 'Login successful! Redirecting...');
                            setTimeout(() => {
                                window.location.href = data.redirect || '<?= htmlspecialchars(app_url('admin')) ?>';
                            }, 1000);
                        } else {
                            // Show errors
                            if (data.errors) {
                                Object.keys(data.errors).forEach(field => {
                                    const input = document.getElementById(field);
                                    const feedback = document.getElementById(field + 'Feedback');
                                    if (input) input.classList.add('is-invalid');
                                    if (feedback) feedback.textContent = data.errors[field];
                                });
                            }
                            showAlert('danger', data.message || 'Invalid credentials. Please try again.');
                            
                            // Re-enable submit button
                            if (submitBtn) submitBtn.disabled = false;
                            if (spinner) spinner.classList.add('d-none');
                        }
                    } catch (error) {
                        showAlert('danger', 'Network error. Please check your connection and try again.');
                        if (submitBtn) submitBtn.disabled = false;
                        if (spinner) spinner.classList.add('d-none');
                    }
                });
            }
        })();
    </script>
</body>
</html>

