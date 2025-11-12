<?php
/**
 * Owner Change Password API
 * Handles password change requests from authenticated owners
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

// Check if owner is logged in
if (!isOwnerLoggedIn()) {
    jsonError('You must be logged in to change your password', [], 401);
}

$listingId = getCurrentOwnerListingId();
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
    
    // Get listing with password hash
    $listing = $db->fetchOne(
        "SELECT id, owner_password_hash FROM listings WHERE id = ? LIMIT 1",
        [$listingId]
    );
    
    if (!$listing) {
        jsonError('Listing not found', [], 404);
    }
    
    // Check if listing has a password
    if (empty($listing['owner_password_hash'])) {
        jsonError('No password set for this account. Please contact admin.', [], 400);
    }
    
    // Verify current password
    if (!password_verify($currentPassword, $listing['owner_password_hash'])) {
        jsonError('Current password is incorrect', ['current_password' => 'Current password is incorrect'], 400);
    }
    
    // Check if new password is same as current
    if (password_verify($newPassword, $listing['owner_password_hash'])) {
        jsonError('New password must be different from your current password', ['new_password' => 'New password must be different from your current password'], 400);
    }
    
    // Hash new password
    $newPasswordHash = password_hash($newPassword, PASSWORD_DEFAULT);
    
    // Update password
    $db->execute(
        "UPDATE listings SET owner_password_hash = ? WHERE id = ?",
        [$newPasswordHash, $listingId]
    );
    
    // Return success
    jsonSuccess('Password changed successfully!', []);
    
} catch (PDOException $e) {
    jsonError('A database error occurred. Please try again later.', [], 500);
} catch (Exception $e) {
    jsonError('An unexpected error occurred. Please try again later.', [], 500);
}

