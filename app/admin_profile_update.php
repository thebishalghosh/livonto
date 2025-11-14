<?php
/**
 * Admin Profile Update Handler
 * Handles AJAX requests for updating admin profile information
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header('Content-Type: application/json');

require __DIR__ . '/config.php';
require __DIR__ . '/functions.php';

// Check if user is logged in and is admin
if (empty($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
    jsonError('Unauthorized', [], 401);
    exit;
}

// Check if this is a POST request
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonError('Invalid request method', [], 405);
    exit;
}

// Check action
$action = $_POST['action'] ?? '';

if ($action !== 'update_profile') {
    jsonError('Invalid action', [], 400);
    exit;
}

$userId = $_SESSION['user_id'];
$errors = [];

// Get and validate input
$name = trim($_POST['name'] ?? '');
$email = trim($_POST['email'] ?? '');
$phone = trim($_POST['phone'] ?? '');

// Remove any non-digit characters from phone for validation
$phoneDigits = preg_replace('/[^0-9]/', '', $phone);

// Validation
if (empty($name)) {
    $errors[] = 'Name is required';
}

if (empty($email)) {
    $errors[] = 'Email is required';
} elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $errors[] = 'Invalid email format';
}

if (!empty($phone) && strlen($phoneDigits) !== 10) {
    $errors[] = 'Phone number must be 10 digits';
}

// If there are errors, return them
if (!empty($errors)) {
    jsonError(implode(', ', $errors), [], 400);
    exit;
}

try {
    $db = db();
    
    // Check if email is already taken by another user
    $existingUser = $db->fetchOne(
        "SELECT id FROM users WHERE email = ? AND id != ? AND role = 'admin'",
        [$email, $userId]
    );
    
    if ($existingUser) {
        jsonError('Email is already taken by another admin', [], 400);
        exit;
    }
    
    // Update admin profile
    // Use phoneDigits if phone was provided, otherwise null
    $phoneValue = !empty($phone) ? $phoneDigits : null;
    
    $db->execute(
        "UPDATE users 
         SET name = ?, email = ?, phone = ?, updated_at = CURRENT_TIMESTAMP 
         WHERE id = ? AND role = 'admin'",
        [$name, $email, $phoneValue, $userId]
    );
    
    // Update session data
    $_SESSION['user_name'] = $name;
    $_SESSION['user_email'] = $email;
    
    jsonSuccess('Profile updated successfully');
    
} catch (Exception $e) {
    error_log("Error updating admin profile: " . $e->getMessage());
    jsonError('Failed to update profile. Please try again.', [], 500);
}

