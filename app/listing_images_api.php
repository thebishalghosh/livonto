<?php
/**
 * API endpoint for listing image management
 * Handles: delete, set cover, reorder images
 */

header('Content-Type: application/json');

// Start session and load config/functions
if (session_status() === PHP_SESSION_NONE) session_start();
require __DIR__ . '/config.php';
require __DIR__ . '/functions.php';

// Ensure admin is logged in
if (empty($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
    exit;
}

// Get action and parameters
$action = $_POST['action'] ?? $_GET['action'] ?? '';
$imageId = intval($_POST['image_id'] ?? $_GET['image_id'] ?? 0);
$listingId = intval($_POST['listing_id'] ?? $_GET['listing_id'] ?? 0);

if (empty($action) || $imageId <= 0 || $listingId <= 0) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Invalid parameters']);
    exit;
}

try {
    $db = db();
    
    // Verify image belongs to listing
    $image = $db->fetchOne(
        "SELECT id, listing_id, image_path, is_cover, image_order 
         FROM listing_images 
         WHERE id = ? AND listing_id = ?",
        [$imageId, $listingId]
    );
    
    if (!$image) {
        http_response_code(404);
        echo json_encode(['status' => 'error', 'message' => 'Image not found']);
        exit;
    }
    
    switch ($action) {
        case 'delete':
            // Delete image file
            $imagePath = trim($image['image_path']);
            if (!empty($imagePath) && strpos($imagePath, 'http') !== 0) {
                $fullPath = __DIR__ . '/../' . ltrim($imagePath, '/');
                if (file_exists($fullPath)) {
                    @unlink($fullPath);
                }
            }
            
            // Delete from database
            $db->execute(
                "DELETE FROM listing_images WHERE id = ? AND listing_id = ?",
                [$imageId, $listingId]
            );
            
            // If this was the cover image, set the first remaining image as cover
            if ($image['is_cover']) {
                $firstImage = $db->fetchOne(
                    "SELECT id FROM listing_images 
                     WHERE listing_id = ? 
                     ORDER BY image_order ASC 
                     LIMIT 1",
                    [$listingId]
                );
                
                if ($firstImage) {
                    $db->execute(
                        "UPDATE listing_images SET is_cover = 1 WHERE id = ?",
                        [$firstImage['id']]
                    );
                }
            }
            
            // Reorder remaining images
            $remainingImages = $db->fetchAll(
                "SELECT id FROM listing_images 
                 WHERE listing_id = ? 
                 ORDER BY image_order ASC",
                [$listingId]
            );
            
            foreach ($remainingImages as $index => $img) {
                $db->execute(
                    "UPDATE listing_images SET image_order = ? WHERE id = ?",
                    [$index, $img['id']]
                );
            }
            
            echo json_encode([
                'status' => 'success',
                'message' => 'Image deleted successfully'
            ]);
            break;
            
        case 'set_cover':
            // Remove cover flag from all images in this listing
            $db->execute(
                "UPDATE listing_images SET is_cover = 0 WHERE listing_id = ?",
                [$listingId]
            );
            
            // Set this image as cover
            $db->execute(
                "UPDATE listing_images SET is_cover = 1 WHERE id = ?",
                [$imageId]
            );
            
            // Also update listings table cover_image
            $db->execute(
                "UPDATE listings SET cover_image = ? WHERE id = ?",
                [$image['image_path'], $listingId]
            );
            
            echo json_encode([
                'status' => 'success',
                'message' => 'Cover image updated successfully'
            ]);
            break;
            
        case 'reorder':
            // Get new order from POST
            $newOrder = intval($_POST['new_order'] ?? $image['image_order']);
            
            // Get all images for this listing in current order
            $allImages = $db->fetchAll(
                "SELECT id, image_order FROM listing_images 
                 WHERE listing_id = ? 
                 ORDER BY image_order ASC",
                [$listingId]
            );
            
            $oldOrder = (int)$image['image_order'];
            
            // Validate new order
            if ($newOrder < 0 || $newOrder >= count($allImages)) {
                http_response_code(400);
                echo json_encode(['status' => 'error', 'message' => 'Invalid order position']);
                exit;
            }
            
            // If order hasn't changed, do nothing
            if ($newOrder === $oldOrder) {
                echo json_encode([
                    'status' => 'success',
                    'message' => 'Image order unchanged'
                ]);
                break;
            }
            
            // Reorder all images: remove the dragged image, then reinsert at new position
            // First, get all image IDs in order
            $imageIds = array_column($allImages, 'id');
            
            // Remove the dragged image from its current position
            $draggedIndex = array_search($imageId, $imageIds);
            if ($draggedIndex !== false) {
                unset($imageIds[$draggedIndex]);
                $imageIds = array_values($imageIds); // Reindex array
                
                // Insert at new position
                array_splice($imageIds, $newOrder, 0, $imageId);
            }
            
            // Update all image orders based on new positions
            foreach ($imageIds as $index => $id) {
                $db->execute(
                    "UPDATE listing_images SET image_order = ? WHERE id = ?",
                    [$index, $id]
                );
            }
            
            echo json_encode([
                'status' => 'success',
                'message' => 'Image order updated successfully'
            ]);
            break;
            
        default:
            http_response_code(400);
            echo json_encode(['status' => 'error', 'message' => 'Invalid action']);
            break;
    }
    
} catch (Exception $e) {
    error_log("Error in listing_images_api.php: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => 'An error occurred: ' . $e->getMessage()
    ]);
}

