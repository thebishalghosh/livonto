<?php
/**
 * Common Helper Functions
 * Utility functions used throughout the application
 */

/**
 * Get database instance (shorthand)
 * @return Database
 */
function getDB() {
    return db();
}

/**
 * Sanitize string input
 * @param string $input
 * @return string
 */
function sanitize($input) {
    return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
}

/**
 * Validate email
 * @param string $email
 * @return bool
 */
function isValidEmail($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

/**
 * Generate random referral code
 * @param int $length
 * @return string
 */
function generateReferralCode($length = 8) {
    $characters = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
    $code = '';
    for ($i = 0; $i < $length; $i++) {
        $code .= $characters[rand(0, strlen($characters) - 1)];
    }
    return $code;
}

/**
 * Check if user is logged in
 * @return bool
 */
function isLoggedIn() {
    return !empty($_SESSION['user_id']);
}

/**
 * Check if user is admin
 * @return bool
 */
function isAdmin() {
    return !empty($_SESSION['user_id']) && $_SESSION['user_role'] === 'admin';
}

/**
 * Require user to be logged in (redirect if not)
 */
function requireLogin() {
    if (!isLoggedIn()) {
        header('Location: ' . app_url('login'));
        exit;
    }
}

/**
 * Require user to be admin (redirect if not)
 */
function requireAdmin() {
    if (!isAdmin()) {
        header('Location: ' . app_url('admin/login'));
        exit;
    }
}

/**
 * Check if owner is logged in
 * @return bool
 */
function isOwnerLoggedIn() {
    return !empty($_SESSION['owner_logged_in']) && $_SESSION['owner_logged_in'] === true;
}

/**
 * Require owner to be logged in (redirect if not)
 */
function requireOwnerLogin() {
    if (!isOwnerLoggedIn()) {
        header('Location: ' . app_url('owner/login'));
        exit;
    }
}

/**
 * Get current owner listing ID
 * @return int|null
 */
function getCurrentOwnerListingId() {
    return $_SESSION['owner_listing_id'] ?? null;
}

/**
 * Get number of beds per room based on room type
 * @param string $roomType
 * @return int
 */
function getBedsPerRoom($roomType) {
    $roomType = strtolower(trim($roomType));
    switch ($roomType) {
        case 'single sharing':
            return 1;
        case 'double sharing':
            return 2;
        case 'triple sharing':
            return 3;
        default:
            return 1; // Default to 1 bed if unknown
    }
}

/**
 * Calculate total beds for a room configuration
 * @param int $totalRooms
 * @param string $roomType
 * @return int
 */
function calculateTotalBeds($totalRooms, $roomType) {
    return $totalRooms * getBedsPerRoom($roomType);
}

/**
 * Calculate available beds for a room configuration
 * @param int $totalRooms
 * @param string $roomType
 * @param int $bookedBeds
 * @return int
 */
function calculateAvailableBeds($totalRooms, $roomType, $bookedBeds) {
    $totalBeds = calculateTotalBeds($totalRooms, $roomType);
    return max(0, $totalBeds - $bookedBeds);
}

/**
 * Get real-time available beds for a room configuration
 * This calculates based on total_beds - booked_beds, ensuring consistency across all interfaces
 * @param int $roomConfigId
 * @return int Available beds count
 */
function getAvailableBedsForRoomConfig($roomConfigId) {
    try {
        $db = db();
        
        // Get room configuration
        $roomConfig = $db->fetchOne(
            "SELECT total_rooms, room_type FROM room_configurations WHERE id = ?",
            [$roomConfigId]
        );
        
        if (!$roomConfig) {
            return 0;
        }
        
        // Count actual booked beds (only confirmed bookings affect availability)
        $bookedBeds = (int)$db->fetchValue(
            "SELECT COUNT(*) FROM bookings 
             WHERE room_config_id = ? AND status = 'confirmed'",
            [$roomConfigId]
        );
        
        // Calculate total beds and available beds
        $totalBeds = calculateTotalBeds($roomConfig['total_rooms'], $roomConfig['room_type']);
        $availableBeds = max(0, $totalBeds - $bookedBeds);
        
        return $availableBeds;
    } catch (Exception $e) {
        error_log("Error getting available beds for room_config_id {$roomConfigId}: " . $e->getMessage());
        return 0;
    }
}

/**
 * Recalculate and update available beds for a room configuration based on actual bookings
 * @param int $roomConfigId
 * @return bool
 */
function recalculateAvailableBeds($roomConfigId) {
    try {
        $availableBeds = getAvailableBedsForRoomConfig($roomConfigId);
        
        // Update available_rooms (which represents available beds)
        db()->execute(
            "UPDATE room_configurations SET available_rooms = ? WHERE id = ?",
            [$availableBeds, $roomConfigId]
        );
        
        return true;
    } catch (Exception $e) {
        error_log("Error recalculating available beds for room_config_id {$roomConfigId}: " . $e->getMessage());
        return false;
    }
}

/**
 * Recalculate available beds for all room configurations in a listing
 * @param int $listingId
 * @return void
 */
function recalculateListingAvailability($listingId) {
    try {
        $db = db();
        $roomConfigs = $db->fetchAll(
            "SELECT id FROM room_configurations WHERE listing_id = ?",
            [$listingId]
        );
        
        foreach ($roomConfigs as $config) {
            recalculateAvailableBeds($config['id']);
        }
    } catch (Exception $e) {
        error_log("Error recalculating listing availability for listing_id {$listingId}: " . $e->getMessage());
    }
}

/**
 * Get current user ID
 * @return int|null
 */
function getCurrentUserId() {
    return $_SESSION['user_id'] ?? null;
}

/**
 * Get current user data
 * @return array|null
 */
function getCurrentUser() {
    if (!isLoggedIn()) {
        return null;
    }
    
    try {
        return db()->fetchOne(
            "SELECT id, name, email, role, referral_code, profile_image FROM users WHERE id = ?",
            [getCurrentUserId()]
        );
    } catch (Exception $e) {
        error_log("Error fetching current user: " . $e->getMessage());
        return null;
    }
}

/**
 * Format currency (Indian Rupees)
 * @param float $amount
 * @return string
 */
function formatCurrency($amount) {
    return '₹' . number_format($amount, 2);
}

/**
 * Format date for display
 * @param string $date
 * @param string $format
 * @return string
 */
function formatDate($date, $format = 'd M Y') {
    if (empty($date)) return '';
    return date($format, strtotime($date));
}

/**
 * Get time ago string (e.g., "2 hours ago")
 * @param string $datetime
 * @return string
 */
function timeAgo($datetime) {
    if (empty($datetime)) return '';
    
    $timestamp = strtotime($datetime);
    $diff = time() - $timestamp;
    
    if ($diff < 60) return 'just now';
    if ($diff < 3600) return floor($diff / 60) . ' minutes ago';
    if ($diff < 86400) return floor($diff / 3600) . ' hours ago';
    if ($diff < 2592000) return floor($diff / 86400) . ' days ago';
    if ($diff < 31536000) return floor($diff / 2592000) . ' months ago';
    return floor($diff / 31536000) . ' years ago';
}

/**
 * Redirect with message
 * @param string $url
 * @param string $message
 * @param string $type (success, error, warning, info)
 */
function redirect($url, $message = null, $type = 'success') {
    if ($message) {
        $_SESSION['flash_message'] = $message;
        $_SESSION['flash_type'] = $type;
    }
    header('Location: ' . $url);
    exit;
}

/**
 * Get and clear flash message
 * @return array|null [message, type]
 */
function getFlashMessage() {
    if (isset($_SESSION['flash_message'])) {
        $message = $_SESSION['flash_message'];
        $type = $_SESSION['flash_type'] ?? 'info';
        unset($_SESSION['flash_message'], $_SESSION['flash_type']);
        return ['message' => $message, 'type' => $type];
    }
    return null;
}

/**
 * JSON response helper
 * @param mixed $data
 * @param int $statusCode
 */
function jsonResponse($data, $statusCode = 200) {
    // Clean any output buffers
    while (ob_get_level()) {
        ob_end_clean();
    }
    
    if (!headers_sent()) {
        http_response_code($statusCode);
        header('Content-Type: application/json; charset=utf-8');
    }
    
    $json = @json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_NUMERIC_CHECK);
    if ($json === false || $json === null) {
        echo '{"status":"error","message":"JSON encoding failed"}';
    } else {
        echo $json;
    }
    exit;
}

/**
 * JSON error response
 * @param string $message
 * @param array $errors
 * @param int $statusCode
 */
function jsonError($message, $errors = [], $statusCode = 400) {
    jsonResponse([
        'status' => 'error',
        'message' => $message,
        'errors' => $errors
    ], $statusCode);
}

/**
 * JSON success response
 * @param string $message
 * @param mixed $data
 * @param int $statusCode
 */
function jsonSuccess($message, $data = null, $statusCode = 200) {
    $response = [
        'status' => 'success',
        'message' => $message
    ];
    if ($data !== null) {
        $response['data'] = $data;
    }
    jsonResponse($response, $statusCode);
}

/**
 * Get site setting value
 * @param string $key
 * @param string $default
 * @return string
 */
function getSetting($key, $default = '') {
    static $settingsCache = null;
    
    // Load settings once per request
    if ($settingsCache === null) {
        try {
            $db = db();
            $settingsRows = $db->fetchAll("SELECT setting_key, setting_value FROM site_settings");
            $settingsCache = [];
            foreach ($settingsRows as $row) {
                $value = $row['setting_value'];
                // Try to decode JSON, otherwise use as string
                $decoded = json_decode($value, true);
                $settingsCache[$row['setting_key']] = ($decoded !== null && json_last_error() === JSON_ERROR_NONE) ? $decoded : $value;
            }
        } catch (Exception $e) {
            error_log("Error loading settings: " . $e->getMessage());
            $settingsCache = [];
        }
    }
    
    return $settingsCache[$key] ?? $default;
}

/**
 * Get listing by ID
 * @param int $listingId
 * @param bool $requireActive - If false, returns listing regardless of status
 * @return array|null
 */
function getListingById($listingId, $requireActive = true) {
    try {
        $whereClause = $requireActive ? "WHERE l.id = ? AND l.status = 'active'" : "WHERE l.id = ?";
        return db()->fetchOne(
            "SELECT l.id, l.title, l.description, l.status, l.security_deposit_amount, loc.city, loc.pin_code
             FROM listings l
             LEFT JOIN listing_locations loc ON l.id = loc.listing_id
             {$whereClause}",
            [$listingId]
        );
    } catch (Exception $e) {
        error_log("Error fetching listing: " . $e->getMessage());
        return null;
    }
}

/**
 * Geocode address to coordinates using OpenStreetMap Nominatim API
 * @param string $address - Full address or city name
 * @param string $city - City name (optional, for better accuracy)
 * @return array|null - Returns ['lat' => float, 'lng' => float] or null on failure
 */
function geocodeAddress($address, $city = '') {
    if (empty($address) && empty($city)) {
        return null;
    }
    
    // Build search query
    $searchQuery = trim($address);
    if (!empty($city) && strpos(strtolower($searchQuery), strtolower($city)) === false) {
        $searchQuery .= ', ' . trim($city);
    }
    if (strpos(strtolower($searchQuery), 'india') === false) {
        $searchQuery .= ', India';
    }
    
    $encodedQuery = urlencode($searchQuery);
    $url = "https://nominatim.openstreetmap.org/search?format=json&q={$encodedQuery}&limit=1&addressdetails=1";
    
    // Use cURL with proper headers (required by Nominatim)
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_USERAGENT => 'Livonto PG Platform',
        CURLOPT_TIMEOUT => 5,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_SSL_VERIFYPEER => true
    ]);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($httpCode !== 200 || empty($response)) {
        return null;
    }
    
    $data = json_decode($response, true);
    
    if (!empty($data) && is_array($data) && isset($data[0]['lat']) && isset($data[0]['lon'])) {
        return [
            'lat' => (float)$data[0]['lat'],
            'lng' => (float)$data[0]['lon']
        ];
    }
    
    return null;
}

/**
 * Auto-geocode listing location if coordinates are missing
 * @param int $listingId
 * @return bool - Returns true if coordinates were successfully added
 */
function autoGeocodeListing($listingId) {
    try {
        $db = db();
        
        // Get listing location data
        $location = $db->fetchOne(
            "SELECT complete_address, city, latitude, longitude 
             FROM listing_locations 
             WHERE listing_id = ?",
            [$listingId]
        );
        
        if (!$location) {
            return false;
        }
        
        // Skip if coordinates already exist
        if (!empty($location['latitude']) && !empty($location['longitude']) &&
            abs((float)$location['latitude']) > 0.0001 && abs((float)$location['longitude']) > 0.0001) {
            return false;
        }
        
        // Build address for geocoding
        $address = trim($location['complete_address'] ?? '');
        $city = trim($location['city'] ?? '');
        
        if (empty($address) && empty($city)) {
            return false;
        }
        
        // Geocode the address
        $coords = geocodeAddress($address, $city);
        
        if ($coords && isset($coords['lat']) && isset($coords['lng'])) {
            // Update coordinates in database
            $db->execute(
                "UPDATE listing_locations 
                 SET latitude = ?, longitude = ? 
                 WHERE listing_id = ?",
                [$coords['lat'], $coords['lng'], $listingId]
            );
            return true;
        }
        
        return false;
    } catch (Exception $e) {
        return false;
    }
}

/**
 * Render pagination HTML for admin pages
 * @param int $currentPage Current page number
 * @param int $totalPages Total number of pages
 * @param int $totalItems Total number of items
 * @param int $perPage Items per page
 * @param int $offset Current offset
 * @param string $baseUrl Base URL for pagination links (optional, uses current URL if not provided)
 * @param string $ariaLabel Aria label for pagination (optional)
 * @param bool $showInfo Show "Showing X to Y of Z" info (default: true)
 * @return string HTML for pagination
 */
function renderAdminPagination($currentPage, $totalPages, $totalItems, $perPage, $offset, $baseUrl = null, $ariaLabel = 'Page navigation', $showInfo = true) {
    if ($totalPages <= 1) {
        return '';
    }
    
    // Calculate page range (show 2 pages before and after current)
    $startPage = max(1, $currentPage - 2);
    $endPage = min($totalPages, $currentPage + 2);
    
    // Expand range if near start or end
    if ($startPage === 1 && $endPage < min(5, $totalPages)) {
        $endPage = min(5, $totalPages);
    }
    if ($endPage === $totalPages && $startPage > max(1, $totalPages - 4)) {
        $startPage = max(1, $totalPages - 4);
    }
    
    $html = '<div class="admin-card-body border-top">';
    
    if ($showInfo) {
        $html .= '<div class="d-flex justify-content-between align-items-center flex-wrap gap-3 mb-3">';
        $html .= '<div class="text-muted small">';
        $html .= 'Showing ' . number_format($offset + 1) . ' to ' . number_format(min($offset + $perPage, $totalItems)) . ' of ' . number_format($totalItems) . ' items';
        $html .= '</div>';
        $html .= '</div>';
    }
    
    $html .= '<nav aria-label="' . htmlspecialchars($ariaLabel) . '">';
    $html .= '<ul class="pagination pagination-sm justify-content-center mb-0">';
    
    // Helper function to build URL with page parameter
    $buildPageUrl = function($pageNum) use ($baseUrl) {
        if ($baseUrl !== null) {
            // If baseUrl provided, append page parameter
            $separator = strpos($baseUrl, '?') === false ? '?' : '&';
            return $baseUrl . $separator . 'page=' . $pageNum;
        } else {
            // Use current GET parameters
            $params = $_GET;
            $params['page'] = $pageNum;
            return '?' . http_build_query($params);
        }
    };
    
    // Previous button
    if ($currentPage > 1) {
        $html .= '<li class="page-item">';
        $html .= '<a class="page-link" href="' . htmlspecialchars($buildPageUrl($currentPage - 1)) . '">';
        $html .= '<i class="bi bi-chevron-left"></i>';
        $html .= '</a>';
        $html .= '</li>';
    }
    
    // First page if not in range
    if ($startPage > 1) {
        $html .= '<li class="page-item">';
        $html .= '<a class="page-link" href="' . htmlspecialchars($buildPageUrl(1)) . '">1</a>';
        $html .= '</li>';
        if ($startPage > 2) {
            $html .= '<li class="page-item disabled"><span class="page-link">...</span></li>';
        }
    }
    
    // Page numbers
    for ($i = $startPage; $i <= $endPage; $i++) {
        $html .= '<li class="page-item ' . ($i === $currentPage ? 'active' : '') . '">';
        $html .= '<a class="page-link" href="' . htmlspecialchars($buildPageUrl($i)) . '">' . $i . '</a>';
        $html .= '</li>';
    }
    
    // Last page if not in range
    if ($endPage < $totalPages) {
        if ($endPage < $totalPages - 1) {
            $html .= '<li class="page-item disabled"><span class="page-link">...</span></li>';
        }
        $html .= '<li class="page-item">';
        $html .= '<a class="page-link" href="' . htmlspecialchars($buildPageUrl($totalPages)) . '">' . $totalPages . '</a>';
        $html .= '</li>';
    }
    
    // Next button
    if ($currentPage < $totalPages) {
        $html .= '<li class="page-item">';
        $html .= '<a class="page-link" href="' . htmlspecialchars($buildPageUrl($currentPage + 1)) . '">';
        $html .= '<i class="bi bi-chevron-right"></i>';
        $html .= '</a>';
        $html .= '</li>';
    }
    
    $html .= '</ul>';
    $html .= '</nav>';
    $html .= '</div>';
    
    return $html;
}

