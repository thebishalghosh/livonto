<?php
/**
 * Visit Booking Handler
 * Handles all backend logic for the visit booking page
 */

// Start session BEFORE any output
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require __DIR__ . '/../config.php';
require __DIR__ . '/../functions.php';

// Initialize variables
$pageTitle = "Book a Visit";
$success = false;
$error = null;
$listing = null;
$userData = [];
$listingId = intval($_GET['id'] ?? 0);

// Require login - redirect if not logged in
if (!isLoggedIn()) {
    header('Location: ' . app_url('login') . '?redirect=' . urlencode(app_url('visit-book?id=' . $listingId)));
    exit;
}

// Get user data (user is logged in, so fetch it)
try {
    $userData = db()->fetchOne(
        "SELECT name, email, phone, gender, dob, address, city, state, pincode FROM users WHERE id = ?",
        [getCurrentUserId()]
    ) ?: [];
} catch (Exception $e) {
    error_log("Error fetching user data for visit booking: " . $e->getMessage());
    // If we can't get user data, redirect to login
    header('Location: ' . app_url('login'));
    exit;
}

// Get listing if ID is provided
if ($listingId > 0) {
    // First try with active status requirement
    $listing = getListingById($listingId, true);
    
    if (!$listing) {
        // Try without status requirement to see if listing exists
        $listing = getListingById($listingId, false);
        
        if ($listing) {
            // Listing exists but is not active
            if ($listing['status'] !== 'active') {
                $error = 'This listing is currently not available for visits.';
                $listing = null; // Don't show the form
            }
        } else {
            $error = 'Listing not found.';
        }
    }
} else {
    $error = 'No listing ID provided.';
}

// Note: visit_bookings table should be created via schema.sql
// If you need to migrate from old structure, run the migration script separately

// Handle form submission (fallback for non-AJAX - redirects to prevent resubmission)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $listingId > 0) {
    // Check if this is an AJAX request - if so, let the API handle it
    $isAjax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && 
              strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
    
    if (!$isAjax) {
        // Non-AJAX POST - redirect to API endpoint or use PRG pattern
        // For now, redirect to prevent resubmission
        header('Location: ' . app_url('visit-book?id=' . $listingId) . '&submitted=1');
        exit;
    }
}

