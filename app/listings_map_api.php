<?php
/**
 * Listings Map API
 * Returns listings with coordinates for map display
 */

// Start output buffering to prevent any output before headers
ob_start();

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Set headers for CORS and JSON response
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Suppress errors from being displayed (they'll be logged)
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

// Load required files
require __DIR__ . '/config.php';
require __DIR__ . '/functions.php';

// Get search parameters
$city = trim($_GET['city'] ?? '');
$query = trim($_GET['q'] ?? '');

// If city and query are the same (from single search input), use it for both
if (empty($city) && !empty($query)) {
    $city = $query; // Use query as city for geocoding
} elseif (empty($query) && !empty($city)) {
    $query = $city; // Use city as query for text search
}

$lat = isset($_GET['lat']) ? floatval($_GET['lat']) : null;
$lng = isset($_GET['lng']) ? floatval($_GET['lng']) : null;
$radius = isset($_GET['radius']) ? floatval($_GET['radius']) : 50; // Default 50km radius to show more nearby listings

try {
    $db = db();
    
    // Build WHERE clause - require active status
    // Note: We'll validate coordinates in PHP to handle edge cases (0, empty strings, etc.)
    $where = [
        'l.status = ?'
    ];
    $params = ['active'];
    
    // Note: We'll validate coordinates in PHP instead of SQL to avoid issues with DECIMAL comparisons
    
    if (!empty($city)) {
        // Make city search case-insensitive and more flexible
        // Try exact match first, then partial match
        $where[] = '(LOWER(TRIM(loc.city)) = LOWER(TRIM(?)) OR LOWER(loc.city) LIKE LOWER(?) OR loc.city LIKE ?)';
        $cityTrimmed = trim($city);
        $cityParam = "%{$cityTrimmed}%";
        $params[] = $cityTrimmed;
        $params[] = $cityParam;
        $params[] = $cityParam;
    }
    
    if (!empty($query)) {
        $where[] = '(l.title LIKE ? OR l.description LIKE ?)';
        $params[] = "%{$query}%";
        $params[] = "%{$query}%";
    }
    
    $whereClause = 'WHERE ' . implode(' AND ', $where);
    
    // Ensure we have at least one listing location to avoid empty results
    // Use INNER JOIN to only get listings with valid locations
    
    // Build query with distance calculation if coordinates provided
    // Ensure coordinates are properly cast to DECIMAL for consistent numeric handling
    if ($lat !== null && $lng !== null) {
        $sql = "SELECT l.id, l.title, l.description, l.cover_image,
                       loc.city, loc.complete_address, 
                       CAST(loc.latitude AS DECIMAL(10,7)) as latitude,
                       CAST(loc.longitude AS DECIMAL(10,7)) as longitude,
                       (SELECT MIN(rent_per_month) FROM room_configurations WHERE listing_id = l.id) as min_rent,
                       (SELECT MAX(rent_per_month) FROM room_configurations WHERE listing_id = l.id) as max_rent,
                       (6371 * acos(
                           cos(radians(?)) * 
                           cos(radians(CAST(loc.latitude AS DECIMAL(10,7)))) * 
                           cos(radians(CAST(loc.longitude AS DECIMAL(10,7))) - radians(?)) + 
                           sin(radians(?)) * 
                           sin(radians(CAST(loc.latitude AS DECIMAL(10,7))))
                       )) AS distance
                FROM listings l
                INNER JOIN listing_locations loc ON l.id = loc.listing_id
                {$whereClause}
                HAVING distance <= ?
                ORDER BY distance ASC
                LIMIT 50";
        
        $params = array_merge([$lat, $lng, $lat], $params, [$radius]);
    } else {
        $sql = "SELECT l.id, l.title, l.description, l.cover_image,
                       loc.city, loc.complete_address,
                       CAST(loc.latitude AS DECIMAL(10,7)) as latitude,
                       CAST(loc.longitude AS DECIMAL(10,7)) as longitude,
                       (SELECT MIN(rent_per_month) FROM room_configurations WHERE listing_id = l.id) as min_rent,
                       (SELECT MAX(rent_per_month) FROM room_configurations WHERE listing_id = l.id) as max_rent,
                       0 AS distance
                FROM listings l
                INNER JOIN listing_locations loc ON l.id = loc.listing_id
                {$whereClause}
                ORDER BY l.created_at DESC
                LIMIT 50";
    }
    
    $listings = $db->fetchAll($sql, $params);
    
    // Format listings for map
    $mapListings = [];
    foreach ($listings as $listing) {
        // Extract coordinates - handle both string and numeric formats
        // Force conversion to float to ensure proper numeric type
        $latVal = null;
        $lngVal = null;
        
        // Try latitude field (handle both 'latitude' and 'lat' keys)
        if (isset($listing['latitude'])) {
            $latRaw = $listing['latitude'];
            if ($latRaw !== null && $latRaw !== '' && $latRaw !== '0') {
                $latVal = (float)$latRaw;
            }
        } elseif (isset($listing['lat'])) {
            $latRaw = $listing['lat'];
            if ($latRaw !== null && $latRaw !== '' && $latRaw !== '0') {
                $latVal = (float)$latRaw;
            }
        }
        
        // Try longitude field (handle both 'longitude' and 'lng' keys)
        if (isset($listing['longitude'])) {
            $lngRaw = $listing['longitude'];
            if ($lngRaw !== null && $lngRaw !== '' && $lngRaw !== '0') {
                $lngVal = (float)$lngRaw;
            }
        } elseif (isset($listing['lng'])) {
            $lngRaw = $listing['lng'];
            if ($lngRaw !== null && $lngRaw !== '' && $lngRaw !== '0') {
                $lngVal = (float)$lngRaw;
            }
        }
        
        // Validate coordinates are valid numbers and within ranges
        $hasValidCoords = false;
        if ($latVal !== null && $lngVal !== null && 
            is_finite($latVal) && is_finite($lngVal) &&
            $latVal >= -90 && $latVal <= 90 && 
            $lngVal >= -180 && $lngVal <= 180 &&
            abs($latVal) > 0.0001 && abs($lngVal) > 0.0001) {
            $hasValidCoords = true;
        }
        
        // If coordinates are missing or invalid, try to auto-geocode
        if (!$hasValidCoords) {
            $geocoded = autoGeocodeListing($listing['id']);
            if ($geocoded) {
                // Re-fetch the listing with updated coordinates
                $updatedListing = $db->fetchOne(
                    "SELECT CAST(loc.latitude AS DECIMAL(10,7)) as latitude,
                            CAST(loc.longitude AS DECIMAL(10,7)) as longitude
                     FROM listing_locations loc
                     WHERE loc.listing_id = ?",
                    [$listing['id']]
                );
                
                if ($updatedListing && 
                    isset($updatedListing['latitude']) && 
                    isset($updatedListing['longitude'])) {
                    $latVal = (float)$updatedListing['latitude'];
                    $lngVal = (float)$updatedListing['longitude'];
                    
                    // Re-validate after geocoding
                    if ($latVal >= -90 && $latVal <= 90 && 
                        $lngVal >= -180 && $lngVal <= 180 &&
                        abs($latVal) > 0.0001 && abs($lngVal) > 0.0001) {
                        $hasValidCoords = true;
                    }
                }
            }
        }
        
        // Add to map listings if coordinates are valid
        if ($hasValidCoords) {
            $mapListings[] = [
                'id' => (int)$listing['id'],
                'title' => $listing['title'] ?? 'Untitled',
                'description' => mb_substr($listing['description'] ?? '', 0, 100),
                'city' => $listing['city'] ?? '',
                'address' => $listing['complete_address'] ?? '',
                'lat' => $latVal,  // Already validated as float
                'lng' => $lngVal,  // Already validated as float
                'price' => $listing['min_rent'] ? '₹' . number_format($listing['min_rent']) . ($listing['max_rent'] && $listing['max_rent'] != $listing['min_rent'] ? ' - ₹' . number_format($listing['max_rent']) : '') : 'Price on request',
                'image' => !empty($listing['cover_image']) ? app_url($listing['cover_image']) : null,
                'url' => app_url('listings/' . $listing['id']),
                'distance' => isset($listing['distance']) && is_numeric($listing['distance']) ? round((float)$listing['distance'], 2) : null
            ];
        }
    }
    
    // Calculate center point
    $centerLat = null;
    $centerLng = null;
    if (!empty($mapListings)) {
        if ($lat !== null && $lng !== null) {
            // Use search location as center
            $centerLat = $lat;
            $centerLng = $lng;
        } else {
            // Calculate center from listings
            $sumLat = 0;
            $sumLng = 0;
            foreach ($mapListings as $listing) {
                $sumLat += $listing['lat'];
                $sumLng += $listing['lng'];
            }
            $centerLat = $sumLat / count($mapListings);
            $centerLng = $sumLng / count($mapListings);
        }
    } else {
        // Default to India center if no listings
        $centerLat = 20.5937;
        $centerLng = 78.9629;
    }
    
    // Clean output buffer before sending JSON
    ob_end_clean();
    
    jsonSuccess('Listings loaded', [
        'listings' => $mapListings,
        'center' => [
            'lat' => $centerLat,
            'lng' => $centerLng
        ],
        'count' => count($mapListings)
    ]);
    
} catch (Exception $e) {
    // Clean output buffer
    while (ob_get_level()) {
        ob_end_clean();
    }
    
    error_log("Error in listings_map_api.php: " . $e->getMessage());
    
    jsonError('Error loading listings', [], 500);
} catch (Error $e) {
    // Clean output buffer
    while (ob_get_level()) {
        ob_end_clean();
    }
    
    error_log("Fatal error in listings_map_api.php: " . $e->getMessage());
    
    jsonError('Error loading listings', [], 500);
}

