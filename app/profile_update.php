<?php
/**
 * Profile Update Handler
 * Handles user profile updates
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
    jsonError('You must be logged in to update your profile', [], 401);
}

$userId = getCurrentUserId();

// Initialize response
$errors = [];
$name = trim($_POST['name'] ?? '');
$phone = trim($_POST['phone'] ?? '');
$gender = $_POST['gender'] ?? null;
$address = trim($_POST['address'] ?? '');
$city = trim($_POST['city'] ?? '');
$state = trim($_POST['state'] ?? '');
$pincode = trim($_POST['pincode'] ?? '');

// Validation
if (empty($name)) {
    $errors['name'] = 'Name is required';
} elseif (strlen($name) < 2) {
    $errors['name'] = 'Name must be at least 2 characters';
} elseif (strlen($name) > 150) {
    $errors['name'] = 'Name must not exceed 150 characters';
}

if (!empty($gender) && !in_array($gender, ['male', 'female', 'other'])) {
    $errors['gender'] = 'Invalid gender selection';
}

// If validation errors, return them
if (!empty($errors)) {
    jsonError('Please fix the errors below', $errors, 400);
}

try {
    // Get database connection
    $db = db();
    
    // Update user profile
    $db->execute(
        "UPDATE users 
         SET name = ?, phone = ?, gender = ?, address = ?, city = ?, state = ?, pincode = ?, updated_at = NOW()
         WHERE id = ?",
        [
            $name,
            $phone ?: null,
            $gender ?: null,
            $address ?: null,
            $city ?: null,
            $state ?: null,
            $pincode ?: null,
            $userId
        ]
    );
    
    // Update session data
    $_SESSION['user_name'] = $name;
    
    // Log successful update
    error_log("User updated profile: User ID {$userId}");
    
    // Return success response
    jsonSuccess('Profile updated successfully!', [
        'user' => [
            'id' => $userId,
            'name' => $name
        ]
    ]);
    
} catch (PDOException $e) {
    error_log("Database error in profile_update.php: " . $e->getMessage());
    jsonError('A database error occurred. Please try again later.', [], 500);
} catch (Exception $e) {
    error_log("Error in profile_update.php: " . $e->getMessage());
    jsonError('An unexpected error occurred. Please try again later.', [], 500);
}

