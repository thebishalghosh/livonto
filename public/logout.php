<?php
/**
 * Logout Handler
 * Destroys session and redirects to home page
 */

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Load config before destroying session
require __DIR__ . '/../app/config.php';

// Check if user was admin before destroying session
$wasAdmin = !empty($_SESSION['user_role']) && $_SESSION['user_role'] === 'admin';

// Log logout (if user was logged in)
if (!empty($_SESSION['user_id'])) {
    $userEmail = $_SESSION['user_email'] ?? 'unknown';
    error_log("User logout: User ID {$_SESSION['user_id']} ({$userEmail})");
}

// Destroy all session data
$_SESSION = [];

// Delete the session cookie
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(
        session_name(),
        '',
        time() - 42000,
        $params["path"],
        $params["domain"],
        $params["secure"],
        $params["httponly"]
    );
}

// Destroy the session
session_destroy();

// Redirect based on user role - admin goes to admin login, others to home
if ($wasAdmin) {
    header('Location: ' . app_url('admin/login'));
} else {
    header('Location: ' . app_url('index'));
}
exit;

