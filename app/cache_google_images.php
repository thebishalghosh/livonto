<?php
/**
 * Cache Google Profile Images Script
 * Downloads and caches all Google profile images that are still stored as URLs
 */

require __DIR__ . '/config.php';
require __DIR__ . '/functions.php';

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

try {
    $db = db();
    
    // Find all users with Google IDs who still have Google URLs as profile images
    $users = $db->fetchAll(
        "SELECT id, name, email, profile_image, google_id 
         FROM users 
         WHERE google_id IS NOT NULL 
         AND profile_image IS NOT NULL 
         AND (profile_image LIKE 'http://%' OR profile_image LIKE 'https://%')"
    );
    
    echo "Found " . count($users) . " users with Google profile image URLs to cache.\n\n";
    
    $successCount = 0;
    $failCount = 0;
    
    foreach ($users as $user) {
        echo "Processing user {$user['id']} ({$user['name']})... ";
        
        // Add a small delay to avoid rate limiting
        usleep(500000); // 0.5 second delay between requests
        
        $localPath = downloadGoogleProfileImage($user['profile_image'], $user['id']);
        
        if ($localPath) {
            // Update database with local path
            $db->execute(
                "UPDATE users SET profile_image = ? WHERE id = ?",
                [$localPath, $user['id']]
            );
            echo "✓ Cached successfully\n";
            $successCount++;
        } else {
            echo "✗ Failed to cache (may be rate limited, try again later)\n";
            $failCount++;
        }
    }
    
    echo "\n";
    echo "Summary:\n";
    echo "  Successfully cached: {$successCount}\n";
    echo "  Failed: {$failCount}\n";
    echo "\nDone!\n";
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    exit(1);
}

