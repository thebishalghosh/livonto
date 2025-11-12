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
            // Relative URL - make it absolute
            $redirectUrl = app_url(ltrim($redirect, '/'));
        }
        header('Location: ' . $redirectUrl);
    } else {
        header('Location: ' . app_url('profile'));
    }
    exit;
}

require __DIR__ . '/../app/includes/header.php';
?>

<div class="container my-5">
    <div class="row justify-content-center">
        <div class="col-md-6 col-lg-5">
            <div class="card shadow">
                <div class="card-body p-4">
                    <h2 class="card-title text-center mb-4">Login</h2>
                    
                    <div id="loginAlert"></div>
                    
                    <form id="loginForm" method="POST" action="<?= app_url('login-action') ?>">
                        <input type="hidden" name="redirect" value="<?= htmlspecialchars($redirect) ?>">
                        
                        <div class="mb-3">
                            <label for="loginEmail" class="form-label">Email</label>
                            <input type="email" class="form-control" id="loginEmail" name="email" required>
                            <div class="invalid-feedback" id="loginEmailError"></div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="loginPassword" class="form-label">Password</label>
                            <input type="password" class="form-control" id="loginPassword" name="password" required>
                            <div class="invalid-feedback" id="loginPasswordError"></div>
                        </div>
                        
                        <div class="mb-3 form-check">
                            <input type="checkbox" class="form-check-input" id="remember" name="remember" value="1">
                            <label class="form-check-label" for="remember">Remember me</label>
                        </div>
                        
                        <div class="mb-3">
                            <a href="<?= app_url('forgot-password') ?>" class="text-decoration-none">Forgot password?</a>
                        </div>
                        
                        <button type="submit" class="btn btn-primary w-100 mb-3" id="loginSubmit">
                            <span id="loginSpinner" class="spinner-border spinner-border-sm me-2 d-none" role="status" aria-hidden="true"></span>
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
// Login form AJAX submission
(function(){
    const loginForm = document.getElementById('loginForm');
    const loginSubmit = document.getElementById('loginSubmit');
    const loginSpinner = document.getElementById('loginSpinner');
    const loginAlert = document.getElementById('loginAlert');
    
    function showAlert(message, type = 'danger') {
        loginAlert.innerHTML = '<div class="alert alert-' + type + ' alert-dismissible fade show" role="alert">' +
            message +
            '<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>';
    }
    
    function clearErrors() {
        document.getElementById('loginEmail').classList.remove('is-invalid');
        document.getElementById('loginPassword').classList.remove('is-invalid');
        document.getElementById('loginEmailError').textContent = '';
        document.getElementById('loginPasswordError').textContent = '';
    }
    
    if (loginForm) {
        loginForm.addEventListener('submit', async function(e){
            e.preventDefault();
            clearErrors();
            
            if (loginSubmit) loginSubmit.disabled = true;
            if (loginSpinner) loginSpinner.classList.remove('d-none');
            
            const formData = new FormData(loginForm);
            
            try {
                const response = await fetch('<?= app_url('login-action') ?>', {
                    method: 'POST',
                    body: formData,
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest'
                    }
                });
                
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
                        if (data.errors.email) {
                            document.getElementById('loginEmail').classList.add('is-invalid');
                            document.getElementById('loginEmailError').textContent = data.errors.email;
                        }
                        if (data.errors.password) {
                            document.getElementById('loginPassword').classList.add('is-invalid');
                            document.getElementById('loginPasswordError').textContent = data.errors.password;
                        }
                    }
                    showAlert(data.message || 'Login failed. Please check your credentials.');
                }
            } catch (err) {
                console.error('Login error:', err);
                showAlert('Server error. Please try again later.');
            } finally {
                if (loginSubmit) loginSubmit.disabled = false;
                if (loginSpinner) loginSpinner.classList.add('d-none');
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
                                            console.error('Google auth error:', err);
                                            showAlert('Failed to sign in with Google. Please try again.');
                                        });
                                    })
                                    .catch(err => {
                                        console.error('Google OAuth error:', err);
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

