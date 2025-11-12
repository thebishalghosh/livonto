<?php
/**
 * Change Password API
 * Handles password change requests from authenticated users
 */

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

// Load required files
require __DIR__ . '/config.php';
require __DIR__ . '/functions.php';

// Check if user is logged in
if (!isLoggedIn()) {
    jsonError('You must be logged in to change your password', [], 401);
}

$userId = getCurrentUserId();
$currentPassword = $_POST['current_password'] ?? '';
$newPassword = $_POST['new_password'] ?? '';
$confirmPassword = $_POST['confirm_new_password'] ?? '';

// Validation
$errors = [];

if (empty($currentPassword)) {
    $errors['current_password'] = 'Current password is required';
}

if (empty($newPassword)) {
    $errors['new_password'] = 'New password is required';
} elseif (strlen($newPassword) < 8) {
    $errors['new_password'] = 'Password must be at least 8 characters long';
}

if (empty($confirmPassword)) {
    $errors['confirm_new_password'] = 'Please confirm your new password';
} elseif ($newPassword !== $confirmPassword) {
    $errors['confirm_new_password'] = 'Passwords do not match';
}

if (!empty($errors)) {
    jsonError('Please fix the errors below', $errors, 400);
}

try {
    $db = db();
    
    // Get user with password hash
    $user = $db->fetchOne(
        "SELECT id, password_hash, google_id FROM users WHERE id = ? LIMIT 1",
        [$userId]
    );
    
    if (!$user) {
        jsonError('User not found', [], 404);
    }
    
    // Check if user has a password (not Google-only account)
    if (empty($user['password_hash'])) {
        jsonError('This account uses Google login. Please update your password in your Google account settings.', [], 400);
    }
    
    // Verify current password
    if (!password_verify($currentPassword, $user['password_hash'])) {
        jsonError('Current password is incorrect', ['current_password' => 'Current password is incorrect'], 400);
    }
    
    // Check if new password is same as current
    if (password_verify($newPassword, $user['password_hash'])) {
        jsonError('New password must be different from your current password', ['new_password' => 'New password must be different from your current password'], 400);
    }
    
    // Hash new password
    $newPasswordHash = password_hash($newPassword, PASSWORD_DEFAULT);
    
    // Update password
    $db->execute(
        "UPDATE users SET password_hash = ? WHERE id = ?",
        [$newPasswordHash, $userId]
    );
    
    // Log password change
    error_log("Password changed for user ID: {$userId}");
    
    // Return success
    jsonSuccess('Password changed successfully!', []);
    
} catch (PDOException $e) {
    error_log("Database error in change_password_api.php: " . $e->getMessage());
    jsonError('A database error occurred. Please try again later.', [], 500);
} catch (Exception $e) {
    error_log("Error in change_password_api.php: " . $e->getMessage());
    jsonError('An unexpected error occurred. Please try again later.', [], 500);
}

