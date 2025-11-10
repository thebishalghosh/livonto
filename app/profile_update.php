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
$removeProfileImage = isset($_POST['remove_profile_image']) && $_POST['remove_profile_image'] === '1';

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
    
    // Handle profile image upload
    $profileImagePath = null;
    $allowedTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/webp'];
    $maxSize = 2 * 1024 * 1024; // 2MB
    $allowedExts = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
    
    // Get current profile image to delete old one if needed
    $currentProfileImage = $db->fetchValue("SELECT profile_image FROM users WHERE id = ?", [$userId]);
    
    // Handle image removal
    if ($removeProfileImage) {
        if ($currentProfileImage && strpos($currentProfileImage, 'http') !== 0) {
            // Only delete if it's a local file (not a Google profile image)
            $oldImagePath = __DIR__ . '/../' . $currentProfileImage;
            if (file_exists($oldImagePath)) {
                @unlink($oldImagePath);
            }
        }
        $profileImagePath = null;
    }
    // Handle new image upload
    elseif (isset($_FILES['profile_image']) && $_FILES['profile_image']['error'] === UPLOAD_ERR_OK) {
        $file = $_FILES['profile_image'];
        
        // Validate file type
        if (!in_array($file['type'], $allowedTypes)) {
            $errors['profile_image'] = 'Image must be a JPEG, PNG, GIF, or WebP file';
        }
        
        // Validate file size
        if ($file['size'] > $maxSize) {
            $errors['profile_image'] = 'Image size must be less than 2MB';
        }
        
        // Validate file extension
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if (!in_array($ext, $allowedExts)) {
            $errors['profile_image'] = 'Invalid file extension';
        }
        
        // If no errors, proceed with upload
        if (empty($errors['profile_image'])) {
            $uploadDir = __DIR__ . '/../storage/uploads/profiles/';
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0755, true);
            }
            
            // Generate unique filename
            $uniqueId = uniqid('', true);
            $filename = 'profile_' . $userId . '_' . time() . '_' . $uniqueId . '.' . $ext;
            $uploadPath = $uploadDir . $filename;
            
            // Delete old image if exists (and it's a local file)
            if ($currentProfileImage && strpos($currentProfileImage, 'http') !== 0) {
                $oldImagePath = __DIR__ . '/../' . $currentProfileImage;
                if (file_exists($oldImagePath)) {
                    @unlink($oldImagePath);
                }
            }
            
            // Move uploaded file
            if (move_uploaded_file($file['tmp_name'], $uploadPath)) {
                $profileImagePath = 'storage/uploads/profiles/' . $filename;
            } else {
                $errors['profile_image'] = 'Failed to upload image. Please try again.';
            }
        }
    }
    
    // If there are image-related errors, return them
    if (!empty($errors)) {
        jsonError('Please fix the errors below', $errors, 400);
    }
    
    // Build update query
    $updateFields = [
        'name = ?',
        'phone = ?',
        'gender = ?',
        'address = ?',
        'city = ?',
        'state = ?',
        'pincode = ?',
        'updated_at = NOW()'
    ];
    $updateParams = [
        $name,
        $phone ?: null,
        $gender ?: null,
        $address ?: null,
        $city ?: null,
        $state ?: null,
        $pincode ?: null
    ];
    
    // Add profile image to update if provided
    if ($profileImagePath !== null || $removeProfileImage) {
        $updateFields[] = 'profile_image = ?';
        $updateParams[] = $profileImagePath;
    }
    
    $updateParams[] = $userId; // Add userId at the end for WHERE clause
    
    // Update user profile
    $db->execute(
        "UPDATE users 
         SET " . implode(', ', $updateFields) . "
         WHERE id = ?",
        $updateParams
    );
    
    // Update session data
    $_SESSION['user_name'] = $name;
    // Update profile image in session if changed
    if ($profileImagePath !== null || $removeProfileImage) {
        $_SESSION['user_profile_image'] = $profileImagePath;
    }
    
    // Log successful update
    error_log("User updated profile: User ID {$userId}" . ($profileImagePath ? " (with new profile image)" : ""));
    
    // Return success response
    jsonSuccess('Profile updated successfully!', [
        'user' => [
            'id' => $userId,
            'name' => $name,
            'profile_image' => $profileImagePath
        ]
    ]);
    
} catch (PDOException $e) {
    error_log("Database error in profile_update.php: " . $e->getMessage());
    jsonError('A database error occurred. Please try again later.', [], 500);
} catch (Exception $e) {
    error_log("Error in profile_update.php: " . $e->getMessage());
    jsonError('An unexpected error occurred. Please try again later.', [], 500);
}

