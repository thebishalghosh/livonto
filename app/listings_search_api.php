<?php
/**
 * Listings Search API
 * Returns listings based on search query (for displaying in listings section)
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header('Content-Type: application/json');

require __DIR__ . '/config.php';
require __DIR__ . '/functions.php';

// Get search parameters
$city = trim($_GET['city'] ?? '');
$query = trim($_GET['q'] ?? '');

// If city and query are the same (from single search input), use it for both
if (empty($city) && !empty($query)) {
    $city = $query;
} elseif (empty($query) && !empty($city)) {
    $query = $city;
}

$limit = isset($_GET['limit']) ? intval($_GET['limit']) : 50;

try {
    $db = db();
    
    // Build WHERE clause
    $where = ['l.status = ?'];
    $params = ['active'];
    
    if (!empty($city)) {
        // Search by city (case-insensitive)
        $where[] = '(LOWER(TRIM(loc.city)) = LOWER(TRIM(?)) OR LOWER(loc.city) LIKE LOWER(?) OR loc.city LIKE ?)';
        $cityTrimmed = trim($city);
        $cityParam = "%{$cityTrimmed}%";
        $params[] = $cityTrimmed;
        $params[] = $cityParam;
        $params[] = $cityParam;
    }
    
    if (!empty($query)) {
        // Search in title and description
        $where[] = '(l.title LIKE ? OR l.description LIKE ?)';
        $queryParam = "%{$query}%";
        $params[] = $queryParam;
        $params[] = $queryParam;
    }
    
    $whereClause = 'WHERE ' . implode(' AND ', $where);
    
    // Get listings with all necessary data
    $sql = "SELECT l.id, l.title, l.description, l.cover_image, l.available_for, l.gender_allowed,
                   loc.city, loc.pin_code,
                   (SELECT MIN(rent_per_month) FROM room_configurations WHERE listing_id = l.id) as min_rent,
                   (SELECT MAX(rent_per_month) FROM room_configurations WHERE listing_id = l.id) as max_rent,
                   (SELECT AVG(rating) FROM reviews WHERE listing_id = l.id) as avg_rating,
                   (SELECT COUNT(*) FROM reviews WHERE listing_id = l.id) as reviews_count
            FROM listings l
            LEFT JOIN listing_locations loc ON l.id = loc.listing_id
            {$whereClause}
            ORDER BY l.created_at DESC
            LIMIT ?";
    
    $params[] = $limit;
    
    $listings = $db->fetchAll($sql, $params);
    
    // Format listings for response
    $baseUrl = app_url('');
    $formattedListings = [];
    foreach ($listings as $listing) {
        // Fetch all images for this listing
        $listingImages = $db->fetchAll(
            "SELECT image_path, image_order, is_cover 
             FROM listing_images 
             WHERE listing_id = ? 
             ORDER BY is_cover DESC, image_order ASC",
            [$listing['id']]
        );
        
        // Build image URLs
        $images = [];
        foreach ($listingImages as $img) {
            $imagePath = trim($img['image_path']);
            if (empty($imagePath)) continue;
            
            if (strpos($imagePath, 'http') === 0 || strpos($imagePath, '//') === 0) {
                $images[] = $imagePath;
            } else {
                $images[] = rtrim($baseUrl, '/') . '/' . ltrim($imagePath, '/');
            }
        }
        
        // Fallback to cover_image if no images in listing_images table
        if (empty($images) && !empty($listing['cover_image'])) {
            $imagePath = $listing['cover_image'];
            if (strpos($imagePath, 'http') !== 0 && strpos($imagePath, '//') !== 0) {
                $images[] = rtrim($baseUrl, '/') . '/' . ltrim($imagePath, '/');
            } else {
                $images[] = $imagePath;
            }
        }
        
        $formattedListings[] = [
            'id' => $listing['id'],
            'title' => $listing['title'] ?? 'Untitled',
            'description' => $listing['description'] ?? '',
            'cover_image' => $listing['cover_image'] ?? null,
            'images' => $images, // Add images array
            'available_for' => $listing['available_for'] ?? 'both',
            'gender_allowed' => $listing['gender_allowed'] ?? 'unisex',
            'city' => $listing['city'] ?? '',
            'pin_code' => $listing['pin_code'] ?? '',
            'min_rent' => $listing['min_rent'] ?? null,
            'max_rent' => $listing['max_rent'] ?? null,
            'avg_rating' => $listing['avg_rating'] ?? null,
            'reviews_count' => (int)($listing['reviews_count'] ?? 0)
        ];
    }
    
    jsonSuccess('Listings found', [
        'listings' => $formattedListings,
        'count' => count($formattedListings)
    ]);
    
} catch (Exception $e) {
    error_log("Error in listings_search_api.php: " . $e->getMessage());
    jsonError('Error loading listings', [], 500);
}

