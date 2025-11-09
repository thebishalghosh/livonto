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
    http_response_code($statusCode);
    header('Content-Type: application/json');
    echo json_encode($data);
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
 * Get listing by ID
 * @param int $listingId
 * @param bool $requireActive - If false, returns listing regardless of status
 * @return array|null
 */
function getListingById($listingId, $requireActive = true) {
    try {
        $whereClause = $requireActive ? "WHERE l.id = ? AND l.status = 'active'" : "WHERE l.id = ?";
        return db()->fetchOne(
            "SELECT l.id, l.title, l.description, l.status, loc.city, loc.pin_code
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

