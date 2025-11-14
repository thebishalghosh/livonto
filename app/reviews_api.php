<?php
/**
 * Reviews API Endpoint
 * Handles AJAX requests for reviews: add, edit, delete, and fetch
 */

// Prevent any output before headers
ob_start();

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Set JSON header early
header('Content-Type: application/json');

// Suppress any warnings/notices that might break JSON
error_reporting(E_ALL);
ini_set('display_errors', 0);

require __DIR__ . '/config.php';
require __DIR__ . '/functions.php';

// Clear any output buffer
ob_clean();

// Get request method
$method = $_SERVER['REQUEST_METHOD'];

// Handle GET request - fetch reviews
if ($method === 'GET') {
    $listingId = intval($_GET['listing_id'] ?? 0);
    $page = max(1, intval($_GET['page'] ?? 1));
    $perPage = intval($_GET['per_page'] ?? 10); // Default 10 reviews per page
    $perPage = min(max(1, $perPage), 50); // Limit between 1 and 50
    $offset = ($page - 1) * $perPage;
    
    if ($listingId <= 0) {
        ob_end_clean();
        jsonError('Invalid listing ID', [], 400);
        exit;
    }
    
    try {
        $db = db();
        
        // Get total count of reviews
        $totalReviews = (int)$db->fetchValue(
            "SELECT COUNT(*) FROM reviews WHERE listing_id = ?",
            [$listingId]
        ) ?: 0;
        
        // Get paginated reviews for this listing
        $reviews = $db->fetchAll(
            "SELECT r.*, u.name as user_name, u.profile_image
             FROM reviews r
             LEFT JOIN users u ON r.user_id = u.id
             WHERE r.listing_id = ?
             ORDER BY r.created_at DESC
             LIMIT ? OFFSET ?",
            [$listingId, $perPage, $offset]
        );
        
        // Get current user ID if logged in
        $currentUserId = getCurrentUserId();
        
        // Add is_owner flag to each review
        foreach ($reviews as &$review) {
            $review['is_owner'] = ($currentUserId && $review['user_id'] == $currentUserId);
        }
        unset($review);
        
        $totalPages = ceil($totalReviews / $perPage);
        $hasMore = $page < $totalPages;
        
        ob_end_clean();
        jsonSuccess('Reviews fetched successfully', [
            'reviews' => $reviews,
            'current_user_id' => $currentUserId,
            'pagination' => [
                'current_page' => $page,
                'per_page' => $perPage,
                'total_reviews' => $totalReviews,
                'total_pages' => $totalPages,
                'has_more' => $hasMore
            ]
        ]);
        exit;
    } catch (Exception $e) {
        error_log("Error fetching reviews: " . $e->getMessage());
        ob_end_clean();
        jsonError('Failed to fetch reviews', [], 500);
        exit;
    }
}

// Handle POST request - add, update, or delete review
if ($method === 'POST') {
    // Check if user is logged in
    if (!isLoggedIn()) {
        ob_end_clean();
        jsonError('Please login to submit a review', [], 401);
        exit;
    }
    
    $userId = getCurrentUserId();
    $action = trim($_POST['action'] ?? '');
    
    // Validate action
    if (!in_array($action, ['add', 'update', 'delete'])) {
        ob_end_clean();
        jsonError('Invalid action', [], 400);
        exit;
    }
    
    try {
        $db = db();
        
        // Handle delete action
        if ($action === 'delete') {
            $reviewId = intval($_POST['review_id'] ?? 0);
            
            if ($reviewId <= 0) {
                ob_end_clean();
                jsonError('Invalid review ID', [], 400);
                exit;
            }
            
            // Verify review belongs to user
            $review = $db->fetchOne(
                "SELECT id, listing_id FROM reviews WHERE id = ? AND user_id = ?",
                [$reviewId, $userId]
            );
            
            if (!$review) {
                ob_end_clean();
                jsonError('Review not found or you do not have permission to delete it', [], 404);
                exit;
            }
            
            // Delete review
            $db->execute(
                "DELETE FROM reviews WHERE id = ? AND user_id = ?",
                [$reviewId, $userId]
            );
            
            ob_end_clean();
            jsonSuccess('Review deleted successfully', ['review_id' => $reviewId]);
            exit;
        }
        
        // Handle add and update actions
        $listingId = intval($_POST['listing_id'] ?? 0);
        $rating = intval($_POST['rating'] ?? 0);
        $comment = trim($_POST['comment'] ?? '');
        
        // Validation
        $errors = [];
        
        if ($listingId <= 0) {
            $errors['listing_id'] = 'Invalid listing ID';
        }
        
        if ($rating < 1 || $rating > 5) {
            $errors['rating'] = 'Rating must be between 1 and 5';
        }
        
        if (empty($comment)) {
            $errors['comment'] = 'Comment is required';
        } elseif (strlen($comment) > 1000) {
            $errors['comment'] = 'Comment must be less than 1000 characters';
        }
        
        if (!empty($errors)) {
            ob_end_clean();
            jsonError('Please fill all required fields correctly', $errors, 400);
            exit;
        }
        
        // Verify listing exists and is active
        $listing = $db->fetchOne(
            "SELECT id FROM listings WHERE id = ? AND status = 'active'",
            [$listingId]
        );
        
        if (!$listing) {
            ob_end_clean();
            jsonError('Listing not found or is not available', [], 404);
            exit;
        }
        
        // Handle add action
        if ($action === 'add') {
            // Check if user already has a review for this listing
            $existingReview = $db->fetchOne(
                "SELECT id FROM reviews WHERE user_id = ? AND listing_id = ?",
                [$userId, $listingId]
            );
            
            if ($existingReview) {
                ob_end_clean();
                jsonError('You have already submitted a review for this listing. Please edit your existing review instead.', [], 409);
                exit;
            }
            
            // Insert new review
            $db->execute(
                "INSERT INTO reviews (user_id, listing_id, rating, comment)
                 VALUES (?, ?, ?, ?)",
                [$userId, $listingId, $rating, $comment]
            );
            
            $reviewId = $db->lastInsertId();
            
            // Get the created review with user info
            $newReview = $db->fetchOne(
                "SELECT r.*, u.name as user_name, u.profile_image
                 FROM reviews r
                 LEFT JOIN users u ON r.user_id = u.id
                 WHERE r.id = ?",
                [$reviewId]
            );
            
            if ($newReview) {
                $newReview['is_owner'] = true;
            }
            
            ob_end_clean();
            jsonSuccess('Review submitted successfully', ['review' => $newReview]);
            exit;
        }
        
        // Handle update action
        if ($action === 'update') {
            $reviewId = intval($_POST['review_id'] ?? 0);
            
            if ($reviewId <= 0) {
                ob_end_clean();
                jsonError('Invalid review ID', [], 400);
                exit;
            }
            
            // Verify review belongs to user
            $existingReview = $db->fetchOne(
                "SELECT id FROM reviews WHERE id = ? AND user_id = ?",
                [$reviewId, $userId]
            );
            
            if (!$existingReview) {
                ob_end_clean();
                jsonError('Review not found or you do not have permission to edit it', [], 404);
                exit;
            }
            
            // Update review
            $db->execute(
                "UPDATE reviews SET rating = ?, comment = ? WHERE id = ? AND user_id = ?",
                [$rating, $comment, $reviewId, $userId]
            );
            
            // Get the updated review with user info
            $updatedReview = $db->fetchOne(
                "SELECT r.*, u.name as user_name, u.profile_image
                 FROM reviews r
                 LEFT JOIN users u ON r.user_id = u.id
                 WHERE r.id = ?",
                [$reviewId]
            );
            
            if ($updatedReview) {
                $updatedReview['is_owner'] = true;
            }
            
            ob_end_clean();
            jsonSuccess('Review updated successfully', ['review' => $updatedReview]);
            exit;
        }
    } catch (Exception $e) {
        error_log("Error processing review: " . $e->getMessage());
        ob_end_clean();
        jsonError('An error occurred while processing your request', [], 500);
        exit;
    }
}

// If we get here, method not allowed
ob_end_clean();
jsonError('Method not allowed', [], 405);
exit;

