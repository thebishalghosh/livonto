<?php
/**
 * Google OAuth Callback Handler
 * Handles Google OAuth2 authentication and creates/logs in user
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

// Get Google client ID from config
$config = require __DIR__ . '/config.php';
$googleClientId = is_array($config) ? ($config['google_client_id'] ?? '') : '';

if (empty($googleClientId)) {
    jsonError('Google OAuth is not configured. Please contact administrator.', [], 500);
}

/**
 * Download and cache Google profile image locally
 * @param string $googleImageUrl The Google profile image URL
 * @param int $userId The user ID
 * @return string|null Local path if successful, null otherwise
 */
function downloadGoogleProfileImage($googleImageUrl, $userId) {
    if (empty($googleImageUrl) || empty($userId)) {
        return null;
    }
    
    try {
        // Create upload directory if it doesn't exist
        $uploadDir = __DIR__ . '/../storage/uploads/profiles/';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }
        
        // Use cURL for better control and error handling
        $ch = curl_init($googleImageUrl);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS => 5,
            CURLOPT_TIMEOUT => 10,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_USERAGENT => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
        ]);
        
        $imageData = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $contentType = curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
        $error = curl_error($ch);
        curl_close($ch);
        
        // Check for errors
        if ($imageData === false || !empty($error) || $httpCode !== 200) {
            if ($httpCode === 429) {
                // Rate limited - will retry on next login
            }
            return null;
        }
        
        // Validate that we actually got image data
        if (empty($imageData) || strlen($imageData) < 100) {
            return null;
        }
        
        // Determine file extension from content type or URL
        $ext = 'jpg'; // Default
        if ($contentType) {
            if (strpos($contentType, 'png') !== false) {
                $ext = 'png';
            } elseif (strpos($contentType, 'gif') !== false) {
                $ext = 'gif';
            } elseif (strpos($contentType, 'webp') !== false) {
                $ext = 'webp';
            } elseif (strpos($contentType, 'jpeg') !== false || strpos($contentType, 'jpg') !== false) {
                $ext = 'jpg';
            }
        } elseif (strpos($googleImageUrl, '.png') !== false) {
            $ext = 'png';
        } elseif (strpos($googleImageUrl, '.gif') !== false) {
            $ext = 'gif';
        } elseif (strpos($googleImageUrl, '.webp') !== false) {
            $ext = 'webp';
        }
        
        // Generate unique filename
        $uniqueId = uniqid('', true);
        $filename = 'google_profile_' . $userId . '_' . time() . '_' . $uniqueId . '.' . $ext;
        $uploadPath = $uploadDir . $filename;
        
        // Save the image
        if (file_put_contents($uploadPath, $imageData) !== false) {
            // Verify the file was created and has content
            if (file_exists($uploadPath) && filesize($uploadPath) > 0) {
                return 'storage/uploads/profiles/' . $filename;
            }
        }
        
        return null;
    } catch (Exception $e) {
        return null;
    }
}

// Get user info from POST (from OAuth2 flow)
$googleId = $_POST['google_id'] ?? '';
$email = trim($_POST['email'] ?? '');
$name = trim($_POST['name'] ?? '');
$picture = trim($_POST['picture'] ?? ''); // Trim to remove any whitespace

// Get referral code if provided (for registration)
$referralCode = trim($_POST['referral_code'] ?? '');

// Validation
if (empty($googleId) || empty($email)) {
    jsonError('Invalid Google account data', [], 400);
}

if (empty($name)) {
    $name = $email; // Fallback to email if name not provided
}

try {
    // Get database connection
    $db = db();
    
    // Check if user exists with this Google ID
    $user = $db->fetchOne(
        "SELECT id, name, email, role, referral_code, profile_image 
         FROM users 
         WHERE google_id = ? 
         LIMIT 1",
        [$googleId]
    );
    
    // If user doesn't exist, check if email exists (account linking)
    if (!$user) {
        $existingUser = $db->fetchOne(
            "SELECT id, name, email, role, referral_code, google_id, password_hash 
             FROM users 
             WHERE email = ? 
             LIMIT 1",
            [$email]
        );
        
        if ($existingUser) {
            // Email exists - link Google account if no password (or allow linking)
            if (empty($existingUser['google_id'])) {
                // Link Google account to existing account
                // Try to download and cache Google profile image
                $profileImageValue = $existingUser['profile_image'] ?? null;
                if (!empty($picture) && trim($picture) !== '') {
                    // Only download if user doesn't have a profile image or if current one is a Google URL
                    if (empty($profileImageValue) || strpos($profileImageValue, 'http://') === 0 || strpos($profileImageValue, 'https://') === 0) {
                        // Add a small delay to avoid rate limiting
                        usleep(500000); // 0.5 second delay
                        
                        $localImagePath = downloadGoogleProfileImage($picture, $existingUser['id']);
                        $profileImageValue = $localImagePath ?: $picture; // Use local path if downloaded, otherwise use Google URL
                    }
                }
                
                $db->execute(
                    "UPDATE users SET google_id = ?, profile_image = ? WHERE id = ?",
                    [$googleId, $profileImageValue, $existingUser['id']]
                );
                
                $user = [
                    'id' => $existingUser['id'],
                    'name' => $existingUser['name'],
                    'email' => $existingUser['email'],
                    'role' => $existingUser['role'],
                    'referral_code' => $existingUser['referral_code'],
                    'profile_image' => $profileImageValue ?: ($existingUser['profile_image'] ?? null)
                ];
            } else {
                // Google account already linked to different user
                jsonError('This Google account is already linked to another account', [], 409);
            }
        } else {
            // New user - create account
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
            
            // Ensure referral code is unique
            $maxAttempts = 10;
            $attempts = 0;
            while ($attempts < $maxAttempts) {
                $existingCode = $db->fetchValue(
                    "SELECT id FROM users WHERE referral_code = ? LIMIT 1",
                    [$newReferralCode]
                );
                
                if (!$existingCode) {
                    break;
                }
                
                $newReferralCode = generateReferralCode(8);
                $attempts++;
            }
            
            // Begin transaction
            $db->beginTransaction();
            
            try {
                // Insert new user
                // Try to download and cache Google profile image
                $profileImageValue = null;
                if (!empty($picture) && trim($picture) !== '') {
                    // We'll download after getting the user ID
                    $profileImageValue = $picture; // Temporary: will update after getting userId
                }
                
                $db->execute(
                    "INSERT INTO users (name, email, google_id, referral_code, referred_by, role, profile_image) 
                     VALUES (?, ?, ?, ?, ?, 'user', ?)",
                    [$name, $email, $googleId, $newReferralCode, $referredBy, $profileImageValue]
                );
                
                $userId = $db->lastInsertId();
                
                // Now try to download and cache the Google profile image
                if (!empty($picture) && trim($picture) !== '') {
                    // Add a small delay to avoid rate limiting
                    usleep(500000); // 0.5 second delay
                    
                    $localImagePath = downloadGoogleProfileImage($picture, $userId);
                    if ($localImagePath) {
                        // Update with local path
                        $db->execute(
                            "UPDATE users SET profile_image = ? WHERE id = ?",
                            [$localImagePath, $userId]
                        );
                        $profileImageValue = $localImagePath;
                    } else {
                        // If download failed, keep the Google URL as fallback
                        // It will be cached on next login or can be cached manually
                        $profileImageValue = $picture;
                    }
                }
                
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
                
                // Fetch created user
                $user = $db->fetchOne(
                    "SELECT id, name, email, role, referral_code, profile_image 
                     FROM users 
                     WHERE id = ? 
                     LIMIT 1",
                    [$userId]
                );
                
            } catch (Exception $e) {
                $db->rollback();
                throw $e;
            }
        }
    } else {
        // Update profile image if provided and different from current
        // Also update if user doesn't have a profile image yet
        if (!empty($picture) && trim($picture) !== '') {
            $currentImage = $user['profile_image'] ?? '';
            // Only update if current image is empty or if it's a Google URL that might be different
            if (empty($currentImage) || trim($currentImage) === '' || 
                (strpos($currentImage, 'http://') === 0 || strpos($currentImage, 'https://') === 0)) {
                // Try to download and cache the Google profile image
                // Add a small delay to avoid rate limiting
                usleep(500000); // 0.5 second delay
                
                $localImagePath = downloadGoogleProfileImage($picture, $user['id']);
                if ($localImagePath) {
                    // Successfully cached - use local path
                    $profileImageValue = $localImagePath;
                } else {
                    // Download failed - keep Google URL as fallback
                    $profileImageValue = $picture;
                }
                
                $db->execute(
                    "UPDATE users SET profile_image = ? WHERE id = ?",
                    [$profileImageValue, $user['id']]
                );
                $user['profile_image'] = $profileImageValue;
            }
        }
    }
    
    // Regenerate session ID for security
    session_regenerate_id(true);
    
    // Set session variables
    $_SESSION['user_id'] = $user['id'];
    $_SESSION['user_name'] = $user['name'];
    $_SESSION['user_email'] = $user['email'];
    $_SESSION['user_role'] = $user['role'];
    $_SESSION['referral_code'] = $user['referral_code'];
    $_SESSION['logged_in'] = true;
    $_SESSION['login_time'] = time();
    
    // Set profile image in session (handle both local and external URLs)
    // Always fetch fresh from database to ensure we have the latest value after any updates
    $finalProfileImage = $db->fetchValue("SELECT profile_image FROM users WHERE id = ?", [$user['id']]);
    
    if (!empty($finalProfileImage) && trim($finalProfileImage) !== '') {
        // If it's an external URL (starts with http:// or https://), use as is
        // Otherwise, it's a local path, use app_url
        if (strpos($finalProfileImage, 'http://') === 0 || strpos($finalProfileImage, 'https://') === 0) {
            // Still an external URL (download might have failed) - use as is
            $profileImageUrl = $finalProfileImage;
        } else {
            // Local path - use app_url
            $profileImageUrl = app_url($finalProfileImage);
        }
        $_SESSION['user_profile_image'] = $profileImageUrl;
    } else {
        $_SESSION['user_profile_image'] = null;
    }
    
    // Get redirect URL from request (if provided)
    $redirect = null;
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        // Check FormData first (from modal login)
        $redirect = $_POST['redirect'] ?? null;
        // If not in POST, check JSON input (for API calls)
        if (empty($redirect)) {
            $input = json_decode(file_get_contents('php://input'), true);
            $redirect = $input['redirect'] ?? null;
        }
    } else {
        $redirect = $_GET['redirect'] ?? null;
    }
    
    // Determine redirect URL
    if ($redirect) {
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
    } else {
        $redirectUrl = app_url('profile');
    }
    
    // Return success response
    jsonSuccess('Login successful!', [
        'redirect' => $redirectUrl,
        'user' => [
            'id' => $user['id'],
            'name' => $user['name'],
            'email' => $user['email'],
            'referral_code' => $user['referral_code']
        ]
    ]);
    
} catch (PDOException $e) {
    error_log("Database error in Google auth: " . $e->getMessage());
    jsonError('Database error occurred. Please try again.', [], 500);
} catch (Exception $e) {
    error_log("Error in Google auth: " . $e->getMessage());
    jsonError($e->getMessage(), [], 500);
}
