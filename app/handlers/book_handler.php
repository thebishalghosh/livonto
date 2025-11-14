<?php
/**
 * Booking Handler
 * Handles all backend logic for the booking page
 */

// Start session BEFORE any output
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require __DIR__ . '/../config.php';
require __DIR__ . '/../functions.php';

// Initialize variables
$pageTitle = "Book Now";
$error = null;
$listing = null;
$userData = [];
$listingId = intval($_GET['id'] ?? 0);
$kycStatus = null;
$roomConfigs = [];
$step = 'kyc'; // 'kyc' or 'booking'

// Require login - redirect if not logged in
if (!isLoggedIn()) {
    header('Location: ' . app_url('login') . '?redirect=' . urlencode(app_url('book?id=' . $listingId)));
    exit;
}

$userId = getCurrentUserId();

// Get user data
try {
    $userData = db()->fetchOne(
        "SELECT id, name, email, phone, gender, dob, address, city, state, pincode FROM users WHERE id = ?",
        [$userId]
    ) ?: [];
} catch (Exception $e) {
    error_log("Error fetching user data for booking: " . $e->getMessage());
    header('Location: ' . app_url('login'));
    exit;
}

// Get listing if ID is provided
if ($listingId > 0) {
    $listing = getListingById($listingId, true);
    
    if (!$listing) {
        $error = 'Listing not found or is not available.';
    } else {
        // Get room configurations and calculate real-time availability
        try {
            $roomConfigs = db()->fetchAll(
                "SELECT id, room_type, rent_per_month, total_rooms, available_rooms 
                 FROM room_configurations 
                 WHERE listing_id = ?
                 ORDER BY rent_per_month ASC",
                [$listingId]
            );
            
            // Calculate bed availability for each room config using unified calculation
            foreach ($roomConfigs as &$room) {
                $room['beds_per_room'] = getBedsPerRoom($room['room_type']);
                $room['total_beds'] = calculateTotalBeds($room['total_rooms'], $room['room_type']);
                
                // Count actual booked beds (only confirmed bookings affect availability)
                $bookedBeds = (int)db()->fetchValue(
                    "SELECT COUNT(*) FROM bookings 
                     WHERE room_config_id = ? AND status = 'confirmed'",
                    [$room['id']]
                );
                
                // Use unified calculation: total_beds - booked_beds (ensures consistency)
                $room['available_beds'] = calculateAvailableBeds($room['total_rooms'], $room['room_type'], $bookedBeds);
                $room['booked_beds'] = $bookedBeds;
            }
            unset($room);
            
            // Filter to only show rooms with available beds
            $roomConfigs = array_filter($roomConfigs, function($room) {
                return $room['available_beds'] > 0;
            });
        } catch (Exception $e) {
            error_log("Error fetching room configs: " . $e->getMessage());
            $roomConfigs = [];
        }
        
        // Get listing images
        try {
            $listingImages = db()->fetchAll(
                "SELECT id, image_path, image_order, is_cover 
                 FROM listing_images 
                 WHERE listing_id = ? 
                 ORDER BY is_cover DESC, image_order ASC",
                [$listingId]
            );
            
            $imageUrls = [];
            foreach ($listingImages as $img) {
                $imgPath = trim($img['image_path']);
                if (empty($imgPath)) continue;
                
                if (strpos($imgPath, 'http') === 0 || strpos($imgPath, '//') === 0) {
                    $imageUrls[] = $imgPath;
                } else {
                    $imageUrls[] = app_url($imgPath);
                }
            }
            
            if (empty($imageUrls) && !empty($listing['cover_image'])) {
                $coverImagePath = $listing['cover_image'];
                if (strpos($coverImagePath, 'http') === 0 || strpos($coverImagePath, '//') === 0) {
                    $imageUrls[] = $coverImagePath;
                } else {
                    $imageUrls[] = app_url($coverImagePath);
                }
            }
            
            $listing['images'] = $imageUrls;
        } catch (Exception $e) {
            error_log("Error fetching listing images: " . $e->getMessage());
            $listing['images'] = [];
        }
    }
} else {
    $error = 'No listing ID provided.';
}

// Check KYC status
try {
    $db = db();
    
    // Check which columns exist
    $columns = $db->fetchAll("DESCRIBE user_kyc");
    $columnNames = array_column($columns, 'Field');
    
    // Build SELECT query based on available columns
    $selectFields = ['id', 'status'];
    if (in_array('document_type', $columnNames)) {
        $selectFields[] = 'document_type';
    } elseif (in_array('doc_type', $columnNames)) {
        $selectFields[] = 'doc_type as document_type';
    }
    
    $orderBy = 'id';
    if (in_array('created_at', $columnNames)) {
        $orderBy = 'created_at';
    } elseif (in_array('submitted_at', $columnNames)) {
        $orderBy = 'submitted_at';
    }
    
    if (in_array('verified_at', $columnNames)) {
        $selectFields[] = 'verified_at';
    }
    if (in_array('rejection_reason', $columnNames)) {
        $selectFields[] = 'rejection_reason';
    }
    
    $kycStatus = $db->fetchOne(
        "SELECT " . implode(', ', $selectFields) . "
         FROM user_kyc
         WHERE user_id = ?
         ORDER BY $orderBy DESC
         LIMIT 1",
        [$userId]
    );
    
    // Determine current step
    // If KYC exists (submitted), allow booking (no verification needed)
    if ($kycStatus) {
        $step = 'booking';
    } else {
        $step = 'kyc';
    }
} catch (Exception $e) {
    error_log("Error fetching KYC status: " . $e->getMessage());
    $step = 'kyc';
}

