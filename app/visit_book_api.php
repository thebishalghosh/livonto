<?php
/**
 * Visit Booking API Endpoint
 * Handles AJAX form submissions for visit bookings
 */

header('Content-Type: application/json');

// Start session BEFORE any output
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require __DIR__ . '/config.php';
require __DIR__ . '/functions.php';

// Check if request is AJAX
$isAjax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && 
          strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';

// Require login
if (!isLoggedIn()) {
    jsonError('Please login to book a visit', [], 401);
}

// Only accept POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonError('Invalid request method', [], 405);
}

// Get and validate input
$listingId = intval($_POST['listing_id'] ?? 0);
$date = trim($_POST['date'] ?? '');
$time = trim($_POST['time'] ?? '');
$message = trim($_POST['message'] ?? '');

// Validation
$errors = [];

if ($listingId <= 0) {
    $errors['listing_id'] = 'Invalid listing ID';
}

if (empty($date)) {
    $errors['date'] = 'Preferred date is required';
} elseif (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
    $errors['date'] = 'Invalid date format';
} elseif (strtotime($date) < strtotime('today')) {
    $errors['date'] = 'Date cannot be in the past';
}

if (empty($time)) {
    $errors['time'] = 'Preferred time is required';
} elseif (!preg_match('/^\d{2}:\d{2}$/', $time)) {
    $errors['time'] = 'Invalid time format';
}

if (!empty($errors)) {
    jsonError('Please fill all required fields correctly', $errors, 400);
}

// Note: visit_bookings table should be created via schema.sql
// If you need to migrate from old structure, run the migration script separately

// Verify listing exists and is active
$listing = getListingById($listingId, true);
if (!$listing) {
    jsonError('Listing not found or is not available for visits', [], 404);
}

// Get user ID
$userId = getCurrentUserId();

// Check for duplicate booking (same user, same listing, same date/time)
try {
    $db = db();
    $existing = $db->fetchOne(
        "SELECT id FROM visit_bookings 
         WHERE listing_id = ? AND user_id = ? AND preferred_date = ? AND preferred_time = ? 
         LIMIT 1",
        [$listingId, $userId, $date, $time]
    );
    
    if ($existing) {
        jsonError('You have already submitted a visit request for this date and time', [], 409);
    }
} catch (Exception $e) {
    error_log("Error checking duplicate visit booking: " . $e->getMessage());
}

// Insert visit booking
try {
    $db = db();
    
    $db->execute(
        "INSERT INTO visit_bookings (listing_id, user_id, preferred_date, preferred_time, message)
         VALUES (?, ?, ?, ?, ?)",
        [
            $listingId, 
            $userId,
            $date, 
            $time, 
            $message ?: null
        ]
    );
    
    $visitBookingId = $db->lastInsertId();
    
    // Send admin notification about new visit booking with full PG details
    try {
        require_once __DIR__ . '/email_helper.php';
        $user = $db->fetchOne("SELECT name, email FROM users WHERE id = ?", [$userId]);
        $listingDetails = $db->fetchOne(
            "SELECT l.title,
                    loc.complete_address,
                    loc.city,
                    loc.pin_code,
                    loc.google_maps_link
             FROM listings l
             LEFT JOIN listing_locations loc ON l.id = loc.listing_id
             WHERE l.id = ?",
            [$listingId]
        );
        $baseUrl = app_url('');
        sendAdminNotification(
            "New Visit Booking Request - Visit #{$visitBookingId}",
            "New Visit Booking Request",
            "A new visit booking request has been submitted.",
            [
                'Visit ID' => '#' . $visitBookingId,
                'User Name' => $user['name'] ?? 'Unknown',
                'User Email' => $user['email'] ?? 'N/A',
                'Property' => $listingDetails['title'] ?? 'Unknown',
                'Address' => $listingDetails['complete_address'] ?? 'N/A',
                'City / PIN' => trim(($listingDetails['city'] ?? '') .
                    (!empty($listingDetails['pin_code']) ? ' - ' . $listingDetails['pin_code'] : '')) ?: 'N/A',
                'Google Maps' => $listingDetails['google_maps_link'] ?? 'N/A',
                'Preferred Date' => date('F d, Y', strtotime($date)),
                'Preferred Time' => date('h:i A', strtotime($time)),
                'Status' => 'Pending'
            ],
            $baseUrl . 'admin/visit-bookings',
            'View Visit Booking'
        );
    } catch (Exception $e) {
        error_log("Failed to send admin notification for new visit booking: " . $e->getMessage());
    }
    
    jsonSuccess('Your visit request has been submitted successfully. We\'ll contact you soon.', [
        'booking_id' => $visitBookingId
    ]);
    
} catch (Exception $e) {
    error_log("Visit booking error: " . $e->getMessage());
    jsonError('Error submitting request. Please try again.', [], 500);
}

