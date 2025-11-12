<?php
/**
 * Listings Map API
 * Returns listings with coordinates for map display
 */

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Set content type to JSON
header('Content-Type: application/json');

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
    
    // Build query with distance calculation if coordinates provided
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
        // Check if latitude and longitude are valid numbers
        // Try different possible field names
        $latVal = null;
        $lngVal = null;
        
        if (isset($listing['latitude']) && $listing['latitude'] !== null) {
            $latVal = floatval($listing['latitude']);
        } elseif (isset($listing['lat']) && $listing['lat'] !== null) {
            $latVal = floatval($listing['lat']);
        }
        
        if (isset($listing['longitude']) && $listing['longitude'] !== null) {
            $lngVal = floatval($listing['longitude']);
        } elseif (isset($listing['lng']) && $listing['lng'] !== null) {
            $lngVal = floatval($listing['lng']);
        }
        
        // If coordinates are missing or invalid, try to auto-geocode
        $needsGeocoding = false;
        if ($latVal === null || $lngVal === null || 
            !is_numeric($latVal) || !is_numeric($lngVal) ||
            $latVal < -90 || $latVal > 90 || 
            $lngVal < -180 || $lngVal > 180 ||
            abs($latVal) <= 0.0001 || abs($lngVal) <= 0.0001) {
            $needsGeocoding = true;
        }
        
        // Auto-geocode if needed (only once per listing to avoid rate limits)
        if ($needsGeocoding) {
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
                    $latVal = floatval($updatedListing['latitude']);
                    $lngVal = floatval($updatedListing['longitude']);
                    $needsGeocoding = false;
                }
            }
        }
        
        // Validate coordinates are within valid ranges
        if (!$needsGeocoding && 
            $latVal !== null && $lngVal !== null && 
            is_numeric($latVal) && is_numeric($lngVal) &&
            $latVal >= -90 && $latVal <= 90 && 
            $lngVal >= -180 && $lngVal <= 180 &&
            abs($latVal) > 0.0001 && abs($lngVal) > 0.0001) {
            
            $mapListings[] = [
                'id' => $listing['id'],
                'title' => $listing['title'] ?? 'Untitled',
                'description' => mb_substr($listing['description'] ?? '', 0, 100),
                'city' => $listing['city'] ?? '',
                'address' => $listing['complete_address'] ?? '',
                'lat' => $latVal,
                'lng' => $lngVal,
                'price' => $listing['min_rent'] ? '₹' . number_format($listing['min_rent']) . ($listing['max_rent'] && $listing['max_rent'] != $listing['min_rent'] ? ' - ₹' . number_format($listing['max_rent']) : '') : 'Price on request',
                'image' => !empty($listing['cover_image']) ? app_url($listing['cover_image']) : null,
                'url' => app_url('listings/' . $listing['id']),
                'distance' => isset($listing['distance']) ? round($listing['distance'], 2) : null
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
    
    jsonSuccess('Listings loaded', [
        'listings' => $mapListings,
        'center' => [
            'lat' => $centerLat,
            'lng' => $centerLng
        ],
        'count' => count($mapListings)
    ]);
    
} catch (Exception $e) {
    jsonError('Error loading listings', [], 500);
}

