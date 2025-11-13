<?php
/**
 * Listings Search API
 * Returns listings based on search query (for displaying in listings section)
 */

// Start output buffering to prevent any output before headers
ob_start();

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Set headers for JSON response
header('Content-Type: application/json; charset=utf-8');

// Suppress errors from being displayed (they'll be logged)
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

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
    
    // Build WHERE clause - use OR logic for more flexible search
    $where = ['l.status = ?'];
    $params = ['active'];
    $hasCityFilter = false;
    $hasQueryFilter = false;
    
    // Build flexible search conditions
    $searchConditions = [];
    
    if (!empty($city)) {
        $hasCityFilter = true;
        $cityTrimmed = trim($city);
        $cityParam = "%{$cityTrimmed}%";
        
        // Search by city (exact match, partial match, or in address)
        $searchConditions[] = '(LOWER(TRIM(loc.city)) = LOWER(TRIM(?)) OR LOWER(loc.city) LIKE LOWER(?) OR loc.city LIKE ? OR LOWER(loc.complete_address) LIKE LOWER(?))';
        $params[] = $cityTrimmed;
        $params[] = $cityParam;
        $params[] = $cityParam;
        $params[] = $cityParam;
    }
    
    if (!empty($query)) {
        $hasQueryFilter = true;
        $queryParam = "%{$query}%";
        
        // Search in title, description, city, or address
        $searchConditions[] = '(l.title LIKE ? OR l.description LIKE ? OR LOWER(loc.city) LIKE LOWER(?) OR LOWER(loc.complete_address) LIKE LOWER(?))';
        $params[] = $queryParam;
        $params[] = $queryParam;
        $params[] = $queryParam;
        $params[] = $queryParam;
    }
    
    // Combine search conditions with OR if both exist, otherwise use AND
    if (!empty($searchConditions)) {
        if (count($searchConditions) > 1) {
            // If both city and query exist, use OR to find listings matching either
            $where[] = '(' . implode(' OR ', $searchConditions) . ')';
        } else {
            $where[] = $searchConditions[0];
        }
    }
    
    $whereClause = 'WHERE ' . implode(' AND ', $where);
    
    // Get listings with all necessary data
    // Use INNER JOIN to ensure listings have locations
    $sql = "SELECT l.id, l.title, l.description, l.cover_image, l.available_for, l.gender_allowed,
                   COALESCE(loc.city, '') as city, 
                   COALESCE(loc.pin_code, '') as pin_code,
                   COALESCE((SELECT MIN(rent_per_month) FROM room_configurations WHERE listing_id = l.id), 0) as min_rent,
                   COALESCE((SELECT MAX(rent_per_month) FROM room_configurations WHERE listing_id = l.id), 0) as max_rent,
                   COALESCE((SELECT AVG(rating) FROM reviews WHERE listing_id = l.id), 0) as avg_rating,
                   COALESCE((SELECT COUNT(*) FROM reviews WHERE listing_id = l.id), 0) as reviews_count
            FROM listings l
            INNER JOIN listing_locations loc ON l.id = loc.listing_id
            {$whereClause}
            ORDER BY l.created_at DESC
            LIMIT ?";
    
    $params[] = $limit;
    
    $listings = $db->fetchAll($sql, $params);
    
    // If no listings found, try progressively more relaxed searches
    if (empty($listings)) {
        $cityTrimmed = !empty($city) ? trim($city) : '';
        $queryTrimmed = !empty($query) ? trim($query) : '';
        
        // Strategy 1: Try very relaxed city/address search (any partial match)
        if (!empty($cityTrimmed)) {
            $cityParam = "%{$cityTrimmed}%";
            $relaxedSql = "SELECT l.id, l.title, l.description, l.cover_image, l.available_for, l.gender_allowed,
                               COALESCE(loc.city, '') as city, 
                               COALESCE(loc.pin_code, '') as pin_code,
                               COALESCE((SELECT MIN(rent_per_month) FROM room_configurations WHERE listing_id = l.id), 0) as min_rent,
                               COALESCE((SELECT MAX(rent_per_month) FROM room_configurations WHERE listing_id = l.id), 0) as max_rent,
                               COALESCE((SELECT AVG(rating) FROM reviews WHERE listing_id = l.id), 0) as avg_rating,
                               COALESCE((SELECT COUNT(*) FROM reviews WHERE listing_id = l.id), 0) as reviews_count
                        FROM listings l
                        INNER JOIN listing_locations loc ON l.id = loc.listing_id
                        WHERE l.status = ? 
                        AND (LOWER(loc.city) LIKE LOWER(?) 
                             OR LOWER(loc.complete_address) LIKE LOWER(?)
                             OR LOWER(loc.pin_code) LIKE LOWER(?))
                        ORDER BY l.created_at DESC
                        LIMIT ?";
            
            $relaxedListings = $db->fetchAll($relaxedSql, ['active', $cityParam, $cityParam, $cityParam, $limit]);
            
            if (!empty($relaxedListings)) {
                $listings = $relaxedListings;
            }
        }
        
        // Strategy 2: If still no results and we have a search term, try searching in all fields
        if (empty($listings) && (!empty($cityTrimmed) || !empty($queryTrimmed))) {
            $searchTerm = !empty($cityTrimmed) ? $cityTrimmed : $queryTrimmed;
            $searchParam = "%{$searchTerm}%";
            
            $veryRelaxedSql = "SELECT l.id, l.title, l.description, l.cover_image, l.available_for, l.gender_allowed,
                                   COALESCE(loc.city, '') as city, 
                                   COALESCE(loc.pin_code, '') as pin_code,
                                   COALESCE((SELECT MIN(rent_per_month) FROM room_configurations WHERE listing_id = l.id), 0) as min_rent,
                                   COALESCE((SELECT MAX(rent_per_month) FROM room_configurations WHERE listing_id = l.id), 0) as max_rent,
                                   COALESCE((SELECT AVG(rating) FROM reviews WHERE listing_id = l.id), 0) as avg_rating,
                                   COALESCE((SELECT COUNT(*) FROM reviews WHERE listing_id = l.id), 0) as reviews_count
                            FROM listings l
                            INNER JOIN listing_locations loc ON l.id = loc.listing_id
                            WHERE l.status = ? 
                            AND (LOWER(l.title) LIKE LOWER(?)
                                 OR LOWER(l.description) LIKE LOWER(?)
                                 OR LOWER(loc.city) LIKE LOWER(?)
                                 OR LOWER(loc.complete_address) LIKE LOWER(?))
                            ORDER BY l.created_at DESC
                            LIMIT ?";
            
            $veryRelaxedListings = $db->fetchAll($veryRelaxedSql, ['active', $searchParam, $searchParam, $searchParam, $searchParam, $limit]);
            
            if (!empty($veryRelaxedListings)) {
                $listings = $veryRelaxedListings;
            }
        }
        
        // Strategy 3: If still no results, show all active listings (fallback)
        // This ensures users always see something, even if search doesn't match
        if (empty($listings)) {
            $fallbackSql = "SELECT l.id, l.title, l.description, l.cover_image, l.available_for, l.gender_allowed,
                               COALESCE(loc.city, '') as city, 
                               COALESCE(loc.pin_code, '') as pin_code,
                               COALESCE((SELECT MIN(rent_per_month) FROM room_configurations WHERE listing_id = l.id), 0) as min_rent,
                               COALESCE((SELECT MAX(rent_per_month) FROM room_configurations WHERE listing_id = l.id), 0) as max_rent,
                               COALESCE((SELECT AVG(rating) FROM reviews WHERE listing_id = l.id), 0) as avg_rating,
                               COALESCE((SELECT COUNT(*) FROM reviews WHERE listing_id = l.id), 0) as reviews_count
                        FROM listings l
                        INNER JOIN listing_locations loc ON l.id = loc.listing_id
                        WHERE l.status = ?
                        ORDER BY l.created_at DESC
                        LIMIT ?";
            
            $listings = $db->fetchAll($fallbackSql, ['active', $limit]);
        }
    }
    
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
    
    // Clean output buffer before sending JSON
    ob_end_clean();
    
    jsonSuccess('Listings found', [
        'listings' => $formattedListings,
        'count' => count($formattedListings)
    ]);
    
} catch (Exception $e) {
    // Clean output buffer
    while (ob_get_level()) {
        ob_end_clean();
    }
    
    error_log("Error in listings_search_api.php: " . $e->getMessage());
    
    jsonError('Error loading listings', [], 500);
} catch (Error $e) {
    // Clean output buffer
    while (ob_get_level()) {
        ob_end_clean();
    }
    
    error_log("Fatal error in listings_search_api.php: " . $e->getMessage());
    
    jsonError('Error loading listings', [], 500);
}

