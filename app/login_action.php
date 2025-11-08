<?php
/**
 * Unified Login Action
 * Handles both user and admin login authentication
 */

// Load required files first (before using any functions)
require __DIR__ . '/config.php';
require __DIR__ . '/functions.php';

// Check if remember me is requested (before starting session)
$remember = isset($_POST['remember']) && $_POST['remember'] == '1';

// Set session lifetime before starting session
if ($remember) {
    ini_set('session.gc_maxlifetime', 30 * 24 * 60 * 60); // 30 days
}

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Set content type to JSON
header('Content-Type: application/json');

// Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonError('Invalid request method', [], 405);
}

// Initialize response
$errors = [];
$email = trim($_POST['email'] ?? '');
$password = $_POST['password'] ?? '';
// $remember already set above before session_start()
$isAdminLogin = isset($_POST['admin_login']) && $_POST['admin_login'] == '1';
$redirect = $_POST['redirect'] ?? null;

// Validation
if (empty($email)) {
    $errors['email'] = 'Email is required';
} elseif (!isValidEmail($email)) {
    $errors['email'] = 'Please enter a valid email address';
}

if (empty($password)) {
    $errors['password'] = 'Password is required';
}

// If validation errors, return them
if (!empty($errors)) {
    jsonError('Please fix the errors below', $errors, 400);
}

try {
    // Get database connection
    $db = db();
    
    // Find user by email
    $user = $db->fetchOne(
        "SELECT id, name, email, password_hash, role, referral_code, google_id 
         FROM users 
         WHERE email = ? 
         LIMIT 1",
        [$email]
    );
    
    // Check if user exists
    if (!$user) {
        jsonError('Invalid email or password', ['email' => 'Invalid email or password'], 401);
    }
    
    // Check if user has password (not Google-only account)
    if (empty($user['password_hash'])) {
        jsonError('This account uses Google login. Please sign in with Google.', [], 401);
    }
    
    // Verify password
    if (!password_verify($password, $user['password_hash'])) {
        // Log failed login attempt (for security monitoring)
        error_log("Failed login attempt for email: " . $email);
        jsonError('Invalid email or password', ['email' => 'Invalid email or password'], 401);
    }
    
    // If admin login, verify user is admin
    if ($isAdminLogin && $user['role'] !== 'admin') {
        jsonError('Access denied. Admin privileges required.', [], 403);
    }
    
    // Regenerate session ID for security (prevent session fixation)
    session_regenerate_id(true);
    
    // Handle "Remember Me" - set session cookie lifetime after regenerating session ID
    if ($remember) {
        // Set session cookie to expire in 30 days (2592000 seconds)
        $cookieParams = session_get_cookie_params();
        setcookie(
            session_name(),
            session_id(),
            time() + (30 * 24 * 60 * 60), // 30 days
            $cookieParams['path'],
            $cookieParams['domain'],
            $cookieParams['secure'],
            $cookieParams['httponly']
        );
    }
    
    // Set session variables
    $_SESSION['user_id'] = $user['id'];
    $_SESSION['user_name'] = $user['name'];
    $_SESSION['user_email'] = $user['email'];
    $_SESSION['user_role'] = $user['role'];
    $_SESSION['referral_code'] = $user['referral_code'] ?? '';
    $_SESSION['logged_in'] = true;
    $_SESSION['login_time'] = time();
    $_SESSION['remember_me'] = $remember;
    
    // Determine redirect URL
    if ($redirect) {
        $redirectUrl = $redirect;
    } elseif ($isAdminLogin) {
        $redirectUrl = app_url('admin');
    } else {
        $redirectUrl = app_url('profile');
    }
    
    // Log successful login
    error_log("Successful login: User ID {$user['id']} ({$user['email']}) - Role: {$user['role']}");
    
    // Return success response
    jsonSuccess('Login successful! Redirecting...', [
        'redirect' => $redirectUrl,
        'user' => [
            'id' => $user['id'],
            'name' => $user['name'],
            'email' => $user['email'],
            'role' => $user['role']
        ]
    ]);
    
} catch (PDOException $e) {
    error_log("Database error in login_action.php: " . $e->getMessage());
    jsonError('A database error occurred. Please try again later.', [], 500);
} catch (Exception $e) {
    error_log("Error in login_action.php: " . $e->getMessage());
    jsonError('An unexpected error occurred. Please try again later.', [], 500);
}

