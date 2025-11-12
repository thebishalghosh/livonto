<?php
/**
 * User Login Page
 * Handles user login with redirect support
 */

session_start();
require __DIR__ . '/../app/config.php';
require_once __DIR__ . '/../app/functions.php';

$pageTitle = "Login";
$baseUrl = app_url('');

// Get redirect URL from query parameter
$redirect = $_GET['redirect'] ?? '';

// If already logged in, redirect to profile or the redirect URL
if (isLoggedIn()) {
    if (!empty($redirect)) {
        // Validate redirect URL to prevent open redirects
        $redirectUrl = filter_var($redirect, FILTER_SANITIZE_URL);
        // Only allow relative URLs or same domain
        if (strpos($redirectUrl, 'http://') === 0 || strpos($redirectUrl, 'https://') === 0) {
            // Check if it's the same domain
            $parsedRedirect = parse_url($redirectUrl);
            $parsedCurrent = parse_url(app_url(''));
            if ($parsedRedirect['host'] !== $parsedCurrent['host']) {
                $redirectUrl = app_url('profile');
            }
        } else {
            // Relative URL - decode and validate
            $redirectUrl = urldecode($redirect);
            // Remove leading slash and validate it's a valid route
            $cleanPath = ltrim($redirectUrl, '/');
            $pathParts = explode('?', $cleanPath);
            $routePath = $pathParts[0];
            
            // Validate route exists (basic check for common routes)
            $validRoutes = ['book', 'profile', 'listings', 'index', 'about', 'contact', 'visit-book', 'payment', 'invoice'];
            $isValidRoute = in_array($routePath, $validRoutes) || strpos($routePath, 'listings/') === 0;
            
            if ($isValidRoute) {
                $redirectUrl = app_url($cleanPath);
            } else {
                // Invalid route - redirect to profile instead
                $redirectUrl = app_url('profile');
            }
        }
        header('Location: ' . $redirectUrl);
    } else {
        header('Location: ' . app_url('profile'));
    }
    exit;
}

// Don't include the container-xxl from header since this is a standalone page
$skipHeaderContainer = true;
require __DIR__ . '/../app/includes/header.php';
?>

<div class="container my-5" style="min-height: 60vh; padding-top: 2rem; padding-bottom: 2rem;">
    <div class="row justify-content-center">
        <div class="col-md-6 col-lg-5">
            <div class="card shadow">
                <div class="card-body p-4">
                    <h2 class="card-title text-center mb-4">Login</h2>
                    
                    <div id="loginPageAlert"></div>
                    
                    <form id="loginPageForm" method="POST" action="<?= app_url('login-action') ?>">
                        <input type="hidden" name="redirect" value="<?= htmlspecialchars($redirect) ?>">
                        
                        <div class="mb-3">
                            <label for="loginPageEmail" class="form-label">Email</label>
                            <input type="email" class="form-control" id="loginPageEmail" name="email" required>
                            <div class="invalid-feedback" id="loginPageEmailError"></div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="loginPagePassword" class="form-label">Password</label>
                            <input type="password" class="form-control" id="loginPagePassword" name="password" required>
                            <div class="invalid-feedback" id="loginPagePasswordError"></div>
                        </div>
                        
                        <div class="mb-3 form-check">
                            <input type="checkbox" class="form-check-input" id="loginPageRemember" name="remember" value="1">
                            <label class="form-check-label" for="loginPageRemember">Remember me</label>
                        </div>
                        
                        <div class="mb-3">
                            <a href="<?= app_url('forgot-password') ?>" class="text-decoration-none">Forgot password?</a>
                        </div>
                        
                        <button type="submit" class="btn btn-primary w-100 mb-3" id="loginPageSubmit">
                            <span id="loginPageSpinner" class="spinner-border spinner-border-sm me-2 d-none" role="status" aria-hidden="true"></span>
                            <i class="bi bi-box-arrow-in-right me-2"></i>Sign In
                        </button>
                        
                        <div class="text-center">
                            <p class="mb-0">Don't have an account? <a href="<?= app_url('register') ?>" class="text-decoration-none">Sign up</a></p>
                        </div>
                    </form>
                    
                    <?php
                    // Google Sign-In button if configured
                    $config = require __DIR__ . '/../app/config.php';
                    $googleClientId = is_array($config) ? ($config['google_client_id'] ?? '') : '';
                    if ($googleClientId):
                    ?>
                    <div class="mt-4">
                        <div class="text-center mb-3">
                            <span class="text-muted">or</span>
                        </div>
                        <button type="button" class="btn btn-outline-secondary w-100" id="googleSignInBtn">
                            <i class="bi bi-google me-2"></i>Sign in with Google
                        </button>
                    </div>
                    <?php endif; ?>
                    
                    <div class="text-center mt-3">
                        <a href="<?= app_url('index') ?>" class="text-decoration-none">
                            <i class="bi bi-arrow-left me-1"></i>Back to Home
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// Login form AJAX submission (standalone page - different IDs to avoid conflicts with modal)
(function(){
    const loginPageForm = document.getElementById('loginPageForm');
    const loginPageSubmit = document.getElementById('loginPageSubmit');
    const loginPageSpinner = document.getElementById('loginPageSpinner');
    const loginPageAlert = document.getElementById('loginPageAlert');
    
    function showAlert(message, type = 'danger') {
        if (loginPageAlert) {
            loginPageAlert.innerHTML = '<div class="alert alert-' + type + ' alert-dismissible fade show" role="alert">' +
                message +
                '<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>';
        }
    }
    
    function clearErrors() {
        const emailEl = document.getElementById('loginPageEmail');
        const passwordEl = document.getElementById('loginPagePassword');
        if (emailEl) emailEl.classList.remove('is-invalid');
        if (passwordEl) passwordEl.classList.remove('is-invalid');
        const emailError = document.getElementById('loginPageEmailError');
        const passwordError = document.getElementById('loginPagePasswordError');
        if (emailError) emailError.textContent = '';
        if (passwordError) passwordError.textContent = '';
    }
    
    if (loginPageForm) {
        loginPageForm.addEventListener('submit', async function(e){
            e.preventDefault();
            clearErrors();
            
            if (loginPageSubmit) loginPageSubmit.disabled = true;
            if (loginPageSpinner) loginPageSpinner.classList.remove('d-none');
            
            const formData = new FormData(loginPageForm);
            
            try {
                const response = await fetch('<?= app_url('login-action') ?>', {
                    method: 'POST',
                    body: formData,
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest'
                    }
                });
                
                if (!response.ok) {
                    throw new Error('Network response was not ok');
                }
                
                const data = await response.json();
                
                if (data.status === 'ok' || data.status === 'success') {
                    showAlert(data.message || 'Login successful! Redirecting...', 'success');
                    
                    setTimeout(() => {
                        if (data.data && data.data.redirect) {
                            window.location.href = data.data.redirect;
                        } else {
                            window.location.href = '<?= app_url('profile') ?>';
                        }
                    }, 500);
                } else {
                    if (data.errors) {
                        const emailEl = document.getElementById('loginPageEmail');
                        const passwordEl = document.getElementById('loginPagePassword');
                        if (data.errors.email && emailEl) {
                            emailEl.classList.add('is-invalid');
                            const emailError = document.getElementById('loginPageEmailError');
                            if (emailError) emailError.textContent = data.errors.email;
                        }
                        if (data.errors.password && passwordEl) {
                            passwordEl.classList.add('is-invalid');
                            const passwordError = document.getElementById('loginPagePasswordError');
                            if (passwordError) passwordError.textContent = data.errors.password;
                        }
                    }
                    showAlert(data.message || 'Login failed. Please check your credentials.');
                }
            } catch (err) {
                showAlert('Server error. Please try again later.');
            } finally {
                if (loginPageSubmit) loginPageSubmit.disabled = false;
                if (loginPageSpinner) loginPageSpinner.classList.add('d-none');
            }
        });
    }
    
    // Google Sign-In functionality
    <?php if ($googleClientId): ?>
    const googleClientId = '<?= htmlspecialchars($googleClientId) ?>';
    const redirectUrl = '<?= htmlspecialchars($redirect) ?>';
    
    // Load Google Identity Services
    (function() {
        const script = document.createElement('script');
        script.src = 'https://accounts.google.com/gsi/client';
        script.async = true;
        script.defer = true;
        document.head.appendChild(script);
    })();
    
    // Initialize Google Sign-In when library loads
    window.addEventListener('load', function() {
        if (typeof google !== 'undefined' && google.accounts) {
            const googleSignInBtn = document.getElementById('googleSignInBtn');
            
            if (googleSignInBtn) {
                googleSignInBtn.addEventListener('click', function() {
                    const client = google.accounts.oauth2.initTokenClient({
                        client_id: googleClientId,
                        scope: 'openid email profile',
                        callback: function(response) {
                            if (response.access_token) {
                                fetch('https://www.googleapis.com/oauth2/v2/userinfo?access_token=' + response.access_token)
                                    .then(res => res.json())
                                    .then(userInfo => {
                                        // Send to server for verification and login
                                        fetch('<?= app_url('google-auth-callback') ?>', {
                                            method: 'POST',
                                            headers: {
                                                'Content-Type': 'application/json',
                                                'X-Requested-With': 'XMLHttpRequest'
                                            },
                                            body: JSON.stringify({
                                                userInfo: userInfo,
                                                redirect: redirectUrl
                                            })
                                        })
                                        .then(res => res.json())
                                        .then(data => {
                                            if (data.status === 'ok' || data.status === 'success') {
                                                if (data.data && data.data.redirect) {
                                                    window.location.href = data.data.redirect;
                                                } else {
                                                    window.location.href = '<?= app_url('profile') ?>';
                                                }
                                            } else {
                                                showAlert(data.message || 'Failed to sign in with Google.');
                                            }
                                        })
                                        .catch(err => {
                                            showAlert('Failed to sign in with Google. Please try again.');
                                        });
                                    })
                                    .catch(err => {
                                        showAlert('Failed to sign in with Google. Please try again.');
                                    });
                            }
                        }
                    });
                    client.requestAccessToken();
                });
            }
        }
    });
    <?php endif; ?>
})();
</script>

<?php require __DIR__ . '/../app/includes/footer.php'; ?>

