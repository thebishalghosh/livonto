<?php
/**
 * User Registration Action
 * Handles new user registration with referral system support
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

// Initialize response
$errors = [];
$name = trim($_POST['name'] ?? '');
$email = trim($_POST['email'] ?? '');
$password = $_POST['password'] ?? '';
$referralCode = trim($_POST['referral_code'] ?? '');

// Validation
if (empty($name)) {
    $errors['name'] = 'Full name is required';
} elseif (strlen($name) < 2) {
    $errors['name'] = 'Name must be at least 2 characters';
} elseif (strlen($name) > 150) {
    $errors['name'] = 'Name must not exceed 150 characters';
}

if (empty($email)) {
    $errors['email'] = 'Email is required';
} elseif (!isValidEmail($email)) {
    $errors['email'] = 'Please enter a valid email address';
}

if (empty($password)) {
    $errors['password'] = 'Password is required';
} elseif (strlen($password) < 6) {
    $errors['password'] = 'Password must be at least 6 characters';
} elseif (strlen($password) > 72) {
    $errors['password'] = 'Password must not exceed 72 characters';
}

// If validation errors, return them
if (!empty($errors)) {
    jsonError('Please fix the errors below', $errors, 400);
}

try {
    // Get database connection
    $db = db();
    
    // Check if email already exists
    $existingUser = $db->fetchOne(
        "SELECT id FROM users WHERE email = ? LIMIT 1",
        [$email]
    );
    
    if ($existingUser) {
        jsonError('This email is already registered. Please use a different email or login.', ['email' => 'Email already exists'], 409);
    }
    
    // Validate referral code if provided
    $referredBy = null;
    if (!empty($referralCode)) {
        $referrer = $db->fetchOne(
            "SELECT id FROM users WHERE referral_code = ? LIMIT 1",
            [$referralCode]
        );
        
        if (!$referrer) {
            jsonError('Invalid referral code', ['referral_code' => 'The referral code you entered is invalid'], 400);
        }
        
        $referredBy = $referrer['id'];
    }
    
    // Generate unique referral code for new user
    $newReferralCode = generateReferralCode(8);
    
    // Ensure referral code is unique (retry if collision)
    $maxAttempts = 10;
    $attempts = 0;
    while ($attempts < $maxAttempts) {
        $existingCode = $db->fetchValue(
            "SELECT id FROM users WHERE referral_code = ? LIMIT 1",
            [$newReferralCode]
        );
        
        if (!$existingCode) {
            break; // Code is unique
        }
        
        $newReferralCode = generateReferralCode(8);
        $attempts++;
    }
    
    if ($attempts >= $maxAttempts) {
        error_log("Warning: Failed to generate unique referral code after {$maxAttempts} attempts");
        // Continue anyway - database UNIQUE constraint will catch it
    }
    
    // Hash password
    $passwordHash = password_hash($password, PASSWORD_DEFAULT);
    
    if ($passwordHash === false) {
        error_log("Error: Failed to hash password for email: " . $email);
        jsonError('An error occurred during registration. Please try again.', [], 500);
    }
    
    // Begin transaction
    $db->beginTransaction();
    
    try {
        // Insert new user
        $db->execute(
            "INSERT INTO users (name, email, password_hash, referral_code, referred_by, role) 
             VALUES (?, ?, ?, ?, ?, 'user')",
            [$name, $email, $passwordHash, $newReferralCode, $referredBy]
        );
        
        $userId = $db->lastInsertId();
        
        // If user was referred, create referral record
        if ($referredBy) {
            $db->execute(
                "INSERT INTO referrals (referrer_id, referred_id, code, status) 
                 VALUES (?, ?, ?, 'pending')",
                [$referredBy, $userId, $referralCode]
            );
        }
        
        // Commit transaction
        $db->commit();
        
        // Log successful registration
        error_log("New user registered: User ID {$userId} ({$email})" . ($referredBy ? " - Referred by: {$referredBy}" : ""));
        
        // Send admin notification about new user registration
        try {
            require_once __DIR__ . '/email_helper.php';
            $baseUrl = app_url('');
            sendAdminNotification(
                "New User Registration - {$name}",
                "New User Registered",
                "A new user has registered on the platform.",
                [
                    'User Name' => $name,
                    'Email' => $email,
                    'Registration Date' => date('F d, Y, h:i A'),
                    'Referred By' => $referredBy ? 'Yes (Code: ' . $referralCode . ')' : 'No'
                ],
                $baseUrl . 'admin/users/view?id=' . $userId,
                'View User Profile'
            );
        } catch (Exception $e) {
            error_log("Failed to send admin notification for new user: " . $e->getMessage());
        }
        
        // Auto-login the user after registration
        session_regenerate_id(true);
        
        $_SESSION['user_id'] = $userId;
        $_SESSION['user_name'] = $name;
        $_SESSION['user_email'] = $email;
        $_SESSION['user_role'] = 'user';
        $_SESSION['referral_code'] = $newReferralCode;
        $_SESSION['logged_in'] = true;
        $_SESSION['login_time'] = time();
        
        // Return success response
        jsonSuccess('Registration successful! Welcome to Livonto!', [
            'redirect' => app_url('profile'),
            'user' => [
                'id' => $userId,
                'name' => $name,
                'email' => $email,
                'referral_code' => $newReferralCode
            ]
        ]);
        
    } catch (Exception $e) {
        // Rollback transaction on error
        $db->rollback();
        throw $e;
    }
    
} catch (PDOException $e) {
    error_log("Database error in register_action.php: " . $e->getMessage());
    
    // Check for duplicate entry error
    if ($e->getCode() == 23000) { // SQLSTATE 23000 = Integrity constraint violation
        if (strpos($e->getMessage(), 'email') !== false) {
            jsonError('This email is already registered. Please use a different email or login.', ['email' => 'Email already exists'], 409);
        } elseif (strpos($e->getMessage(), 'referral_code') !== false) {
            // Retry with new referral code (shouldn't happen, but handle it)
            jsonError('Registration failed due to a system error. Please try again.', [], 500);
        }
    }
    
    jsonError('A database error occurred. Please try again later.', [], 500);
} catch (Exception $e) {
    error_log("Error in register_action.php: " . $e->getMessage());
    jsonError('An unexpected error occurred. Please try again later.', [], 500);
}

