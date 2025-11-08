<?php
/**
 * Admin Delete Listing Handler
 * Handles listing deletion including file cleanup
 */

// Start session and load config/functions BEFORE handling POST
if (session_status() === PHP_SESSION_NONE) session_start();
require __DIR__ . '/../app/config.php';
require __DIR__ . '/../app/functions.php';

// Ensure admin is logged in
if (empty($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
    header('Location: ' . app_url('admin/login'));
    exit;
}

// Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $_SESSION['flash_message'] = 'Invalid request method';
    $_SESSION['flash_type'] = 'danger';
    header('Location: ' . app_url('admin/listings'));
    exit;
}

// Get listing ID
$listingId = intval($_POST['id'] ?? 0);
$confirmDelete = isset($_POST['confirm_delete']) && $_POST['confirm_delete'] == '1';

if (!$listingId || !$confirmDelete) {
    $_SESSION['flash_message'] = 'Invalid request';
    $_SESSION['flash_type'] = 'danger';
    header('Location: ' . app_url('admin/listings'));
    exit;
}

try {
    $db = db();
    
    // Get listing details including cover image path
    $listing = $db->fetchOne("SELECT id, title, cover_image FROM listings WHERE id = ?", [$listingId]);
    
    if (!$listing) {
        $_SESSION['flash_message'] = 'Listing not found';
        $_SESSION['flash_type'] = 'danger';
        header('Location: ' . app_url('admin/listings'));
        exit;
    }
    
    // Delete the cover image file if it exists
    if (!empty($listing['cover_image'])) {
        $imagePath = $listing['cover_image'];
        
        // If it's a relative path, build full path
        if (strpos($imagePath, 'http') !== 0 && strpos($imagePath, '//') !== 0) {
            // Remove base URL if present
            $baseUrl = app_url('');
            $imagePath = str_replace($baseUrl . '/', '', $imagePath);
            $imagePath = ltrim($imagePath, '/');
            
            // Build full file system path
            $fullImagePath = __DIR__ . '/../' . $imagePath;
        } else {
            // If it's a full URL, we can't delete it (external image)
            $fullImagePath = null;
        }
        
        // Delete the file if it exists and is in our uploads directory
        if ($fullImagePath && file_exists($fullImagePath)) {
            // Security check: ensure the file is in the uploads directory
            $realPath = realpath($fullImagePath);
            $uploadsDir = realpath(__DIR__ . '/../storage/uploads/listings/');
            
            if ($uploadsDir && $realPath && strpos($realPath, $uploadsDir) === 0) {
                // File is in the uploads directory, safe to delete
                if (@unlink($fullImagePath)) {
                    error_log("Deleted listing image: {$fullImagePath}");
                } else {
                    error_log("Warning: Failed to delete listing image: {$fullImagePath}");
                }
            } else {
                error_log("Warning: Attempted to delete file outside uploads directory: {$fullImagePath}");
            }
        }
    }
    
    // Delete the listing from database (cascade will handle related records)
    $db->execute("DELETE FROM listings WHERE id = ?", [$listingId]);
    
    error_log("Admin deleted listing: ID {$listingId} ({$listing['title']}) by Admin ID {$_SESSION['user_id']}");
    
    $_SESSION['flash_message'] = 'Listing deleted successfully';
    $_SESSION['flash_type'] = 'success';
    
} catch (PDOException $e) {
    error_log("Database error deleting listing: " . $e->getMessage());
    $_SESSION['flash_message'] = 'Error deleting listing: ' . (getenv('APP_DEBUG') === 'true' ? $e->getMessage() : 'Database error occurred');
    $_SESSION['flash_type'] = 'danger';
} catch (Exception $e) {
    error_log("Error deleting listing: " . $e->getMessage());
    $_SESSION['flash_message'] = 'Error deleting listing: ' . $e->getMessage();
    $_SESSION['flash_type'] = 'danger';
}

// Redirect back to listings page
header('Location: ' . app_url('admin/listings'));
exit;

