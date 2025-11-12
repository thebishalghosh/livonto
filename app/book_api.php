<?php
/**
 * Booking API
 * Handles AJAX requests for KYC submission and booking creation
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

// Check if user is logged in
if (!isLoggedIn()) {
    ob_end_clean();
    jsonError('Please login to continue', [], 401);
    exit;
}

$userId = getCurrentUserId();
$action = $_POST['action'] ?? '';

// Handle KYC submission
if ($action === 'submit_kyc') {
    $errors = [];
    
    $documentType = trim($_POST['document_type'] ?? '');
    $documentNumber = trim($_POST['document_number'] ?? '');
    
    // Validation
    if (empty($documentType)) {
        $errors['document_type'] = 'Document type is required';
    }
    
    if (empty($documentNumber)) {
        $errors['document_number'] = 'Document number is required';
    }
    
    // Handle file uploads
    $documentFront = null;
    $documentBack = null;
    
    // Upload front document
    if (isset($_FILES['document_front'])) {
        $file = $_FILES['document_front'];
        
        if ($file['error'] !== UPLOAD_ERR_OK) {
            $uploadErrors = [
                UPLOAD_ERR_INI_SIZE => 'File exceeds upload_max_filesize',
                UPLOAD_ERR_FORM_SIZE => 'File exceeds MAX_FILE_SIZE',
                UPLOAD_ERR_PARTIAL => 'File was only partially uploaded',
                UPLOAD_ERR_NO_FILE => 'No file was uploaded',
                UPLOAD_ERR_NO_TMP_DIR => 'Missing temporary folder',
                UPLOAD_ERR_CANT_WRITE => 'Failed to write file to disk',
                UPLOAD_ERR_EXTENSION => 'File upload stopped by extension'
            ];
            $errors['document_front'] = $uploadErrors[$file['error']] ?? 'Upload error: ' . $file['error'];
        } else {
            $allowedTypes = ['image/jpeg', 'image/png', 'image/jpg', 'application/pdf'];
            $maxSize = 5 * 1024 * 1024; // 5MB
            
            if (!in_array($file['type'], $allowedTypes)) {
                $errors['document_front'] = 'Invalid file type. Only JPG, PNG, PDF allowed.';
            } elseif ($file['size'] > $maxSize) {
                $errors['document_front'] = 'File size must be less than 5MB.';
            } else {
                $uploadDir = __DIR__ . '/../storage/uploads/kyc/';
                if (!is_dir($uploadDir)) {
                    if (!mkdir($uploadDir, 0755, true)) {
                        $errors['document_front'] = 'Failed to create upload directory.';
                    }
                }
                
                if (empty($errors['document_front'])) {
                    $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
                    $filename = 'kyc_' . $userId . '_' . time() . '_front.' . $extension;
                    $filepath = $uploadDir . $filename;
                    
                    if (move_uploaded_file($file['tmp_name'], $filepath)) {
                        $documentFront = 'storage/uploads/kyc/' . $filename;
                    } else {
                        $errors['document_front'] = 'Failed to upload file. Please check directory permissions.';
                    }
                }
            }
        }
    } else {
        $errors['document_front'] = 'Front document is required';
    }
    
    // Upload back document (optional)
    if (isset($_FILES['document_back']) && $_FILES['document_back']['error'] === UPLOAD_ERR_OK) {
        $file = $_FILES['document_back'];
        $allowedTypes = ['image/jpeg', 'image/png', 'image/jpg', 'application/pdf'];
        $maxSize = 5 * 1024 * 1024; // 5MB
        
        if (!in_array($file['type'], $allowedTypes)) {
            $errors['document_back'] = 'Invalid file type. Only JPG, PNG, PDF allowed.';
        } elseif ($file['size'] > $maxSize) {
            $errors['document_back'] = 'File size must be less than 5MB.';
        } else {
            $uploadDir = __DIR__ . '/../storage/uploads/kyc/';
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0755, true);
            }
            
            $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
            $filename = 'kyc_' . $userId . '_' . time() . '_back.' . $extension;
            $filepath = $uploadDir . $filename;
            
            if (move_uploaded_file($file['tmp_name'], $filepath)) {
                $documentBack = 'storage/uploads/kyc/' . $filename;
            }
        }
    }
    
    if (!empty($errors)) {
        jsonError('Validation failed', $errors, 400);
        exit;
    }
    
    try {
        $db = db();
        
        // Check which columns exist in the table
        $columns = $db->fetchAll("DESCRIBE user_kyc");
        $columnNames = array_column($columns, 'Field');
        
        // Map to actual column names if they exist
        $hasDocumentType = in_array('document_type', $columnNames);
        $hasDocType = in_array('doc_type', $columnNames);
        $hasDocumentNumber = in_array('document_number', $columnNames);
        $hasDocNumber = in_array('doc_number', $columnNames);
        $hasDocumentFront = in_array('document_front', $columnNames);
        $hasDocImage = in_array('doc_image', $columnNames);
        $hasDocumentBack = in_array('document_back', $columnNames);
        
        // Use the correct column names based on what exists
        // If both old and new columns exist, use new ones but also populate old ones for compatibility
        // Auto-verify KYC (no admin verification needed)
        if ($hasDocumentType && $hasDocumentNumber && $hasDocumentFront) {
            // New schema structure exists - use it
            if ($hasDocImage) {
                // Both old and new columns exist - populate both
                $db->execute(
                    "INSERT INTO user_kyc (user_id, document_type, document_number, document_front, document_back, doc_type, doc_number, doc_image, status, verified_at)
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'verified', NOW())",
                    [$userId, $documentType, $documentNumber, $documentFront, $documentBack, $documentType, $documentNumber, $documentFront]
                );
            } else {
                // Only new columns exist
                $db->execute(
                    "INSERT INTO user_kyc (user_id, document_type, document_number, document_front, document_back, status, verified_at)
                     VALUES (?, ?, ?, ?, ?, 'verified', NOW())",
                    [$userId, $documentType, $documentNumber, $documentFront, $documentBack]
                );
            }
        } elseif ($hasDocType && $hasDocNumber && $hasDocImage) {
            // Only old schema structure exists - use it
            $db->execute(
                "INSERT INTO user_kyc (user_id, doc_type, doc_number, doc_image, status, verified_at)
                 VALUES (?, ?, ?, ?, 'verified', NOW())",
                [$userId, $documentType, $documentNumber, $documentFront]
            );
        } else {
            throw new Exception("KYC table structure is incompatible. Please check the database schema.");
        }
        
        // Get the inserted KYC ID
        $kycId = $db->lastInsertId();
        
        jsonSuccess('KYC submitted successfully! You can now proceed with booking.', [
            'redirect' => app_url('book?id=' . intval($_POST['listing_id'] ?? 0)),
            'kyc_id' => $kycId
        ]);
        
    } catch (Exception $e) {
        error_log("Error submitting KYC: " . $e->getMessage());
        jsonError('Failed to submit KYC. Please try again.', [], 500);
    }
    exit;
}

// Handle booking submission
if ($action === 'submit_booking') {
    $errors = [];
    
    $listingId = intval($_POST['listing_id'] ?? 0);
    $roomConfigId = intval($_POST['room_config_id'] ?? 0);
    $bookingStartDate = trim($_POST['booking_start_date'] ?? '');
    $durationMonths = intval($_POST['duration_months'] ?? 0);
    $specialRequests = trim($_POST['special_requests'] ?? '');
    $agreedToTnc = isset($_POST['agreed_to_tnc']) && $_POST['agreed_to_tnc'] === 'on';
    $kycId = intval($_POST['kyc_id'] ?? 0);
    
    // Validation
    if ($listingId <= 0) {
        $errors['listing_id'] = 'Invalid listing';
    }
    
    if ($roomConfigId <= 0) {
        $errors['room_config_id'] = 'Please select a room type';
    }
    
    if (empty($bookingStartDate)) {
        $errors['booking_start_date'] = 'Please select a month for booking';
    } else {
        // Convert selected date to 1st of that month
        $selectedDate = new DateTime($bookingStartDate);
        $bookingStartDate = $selectedDate->format('Y-m-01'); // Set to 1st of the month
        
        // Check if the selected month is before the current month
        // Allow current month and future months by comparing year-month strings
        $selectedYearMonth = $selectedDate->format('Y-m');
        $currentYearMonth = date('Y-m');
        
        if ($selectedYearMonth < $currentYearMonth) {
            $errors['booking_start_date'] = 'Selected month cannot be in the past';
        }
    }
    
    if ($durationMonths < 1 || $durationMonths > 12) {
        $errors['duration_months'] = 'Duration must be between 1 and 12 months';
    }
    
    if (!$agreedToTnc) {
        $errors['agreed_to_tnc'] = 'You must agree to the terms and conditions';
    }
    
    // Verify KYC exists (no need to check status - auto-verified on submission)
    if ($kycId <= 0) {
        // Try to get the latest KYC for this user
        try {
            $db = db();
            $kyc = $db->fetchOne(
                "SELECT id FROM user_kyc WHERE user_id = ? ORDER BY id DESC LIMIT 1",
                [$userId]
            );
            
            if ($kyc) {
                $kycId = $kyc['id'];
            } else {
                $errors['kyc'] = 'KYC submission is required. Please submit KYC first.';
            }
        } catch (Exception $e) {
            $errors['kyc'] = 'Unable to verify KYC status';
        }
    } else {
        // Verify the KYC belongs to this user
        try {
            $db = db();
            $kyc = $db->fetchOne(
                "SELECT id FROM user_kyc WHERE id = ? AND user_id = ?",
                [$kycId, $userId]
            );
            
            if (!$kyc) {
                $errors['kyc'] = 'Invalid KYC record';
            }
        } catch (Exception $e) {
            $errors['kyc'] = 'Unable to verify KYC status';
        }
    }
    
    if (!empty($errors)) {
        jsonError('Validation failed', $errors, 400);
        exit;
    }
    
    try {
        $db = db();
        
        // Get listing to get security deposit
        $listing = $db->fetchOne(
            "SELECT security_deposit_amount FROM listings WHERE id = ?",
            [$listingId]
        );
        
        if (!$listing) {
            jsonError('Invalid listing', [], 400);
            exit;
        }
        
        // Get room configuration to check availability (bed-based)
        $roomConfig = $db->fetchOne(
            "SELECT rent_per_month, total_rooms, room_type FROM room_configurations WHERE id = ? AND listing_id = ?",
            [$roomConfigId, $listingId]
        );
        
        if (!$roomConfig) {
            jsonError('Invalid room configuration', [], 400);
            exit;
        }
        
        // Calculate total beds for this room configuration
        $bedsPerRoom = getBedsPerRoom($roomConfig['room_type']);
        $totalBeds = calculateTotalBeds($roomConfig['total_rooms'], $roomConfig['room_type']);
        
        // Check bed availability for all months in the duration
        $startDate = new DateTime($bookingStartDate);
        for ($i = 0; $i < $durationMonths; $i++) {
            $checkDate = clone $startDate;
            $checkDate->modify("+{$i} months");
            $checkMonth = $checkDate->format('Y-m'); // Format to match DATE_FORMAT output
            
            // Count booked beds for this room config for this month (each booking = 1 bed)
            $bookedBeds = $db->fetchOne(
                "SELECT COUNT(*) as count 
                 FROM bookings 
                 WHERE room_config_id = ? 
                 AND DATE_FORMAT(booking_start_date, '%Y-%m') = ?
                 AND status IN ('pending', 'confirmed')",
                [$roomConfigId, $checkMonth]
            );
            
            $bookedBeds = (int)($bookedBeds['count'] ?? 0);
            $availableBedsForMonth = max(0, $totalBeds - $bookedBeds);
            
            // Check if beds are available for this month
            if ($bookedBeds >= $totalBeds) {
                $monthName = $checkDate->format('F Y');
                jsonError("No beds available for this configuration in {$monthName}. All beds are already booked.", [], 400);
                exit;
            }
        }
        
        // Parse security deposit amount
        $securityDepositAmount = 0;
        $depositStr = trim($listing['security_deposit_amount'] ?? '');
        if (!empty($depositStr) && strtolower($depositStr) !== 'no deposit') {
            // Extract numeric value from string like "₹10,000" or "10000" or "₹ 10,000"
            $depositStr = preg_replace('/[₹,\s]/', '', $depositStr);
            $securityDepositAmount = floatval($depositStr);
        }
        
        if ($securityDepositAmount <= 0) {
            jsonError('Security deposit is required for this listing. Please contact support.', [], 400);
            exit;
        }
        
        // Add duration_months column if it doesn't exist (for backward compatibility)
        try {
            $db->execute("ALTER TABLE bookings ADD COLUMN duration_months INT DEFAULT 1 AFTER booking_start_date");
        } catch (Exception $e) {
            // Column might already exist, ignore
        }
        
        // Create booking with duration
        $db->execute(
            "INSERT INTO bookings (user_id, listing_id, room_config_id, booking_start_date, duration_months,
                                  total_amount, kyc_id, agreed_to_tnc, tnc_accepted_at, special_requests, status)
             VALUES (?, ?, ?, ?, ?, ?, ?, TRUE, NOW(), ?, 'pending')",
            [$userId, $listingId, $roomConfigId, $bookingStartDate, $durationMonths, $securityDepositAmount, $kycId, $specialRequests]
        );
        
        $bookingId = $db->lastInsertId();
        
        // Recalculate availability immediately after creating booking (even if pending)
        // This ensures availability is updated in real-time
        recalculateAvailableBeds($roomConfigId);
        
        // Create payment record with security deposit amount
        $db->execute(
            "INSERT INTO payments (booking_id, amount, provider, status)
             VALUES (?, ?, 'razorpay', 'initiated')",
            [$bookingId, $securityDepositAmount]
        );
        
        jsonSuccess('Booking created successfully', [
            'booking_id' => $bookingId,
            'amount' => $securityDepositAmount,
            'redirect' => app_url('payment?booking_id=' . $bookingId)
        ]);
        
    } catch (Exception $e) {
        jsonError('Failed to create booking. Please try again.', [], 500);
    }
    exit;
}

// Handle availability check for specific month and duration
if ($action === 'check_availability') {
    $listingId = intval($_POST['listing_id'] ?? 0);
    $bookingStartDate = trim($_POST['booking_start_date'] ?? '');
    $durationMonths = intval($_POST['duration_months'] ?? 1);
    
    if ($listingId <= 0) {
        ob_end_clean();
        jsonError('Invalid listing ID', [], 400);
        exit;
    }
    
    if (empty($bookingStartDate)) {
        ob_end_clean();
        jsonError('Booking start date is required', [], 400);
        exit;
    }
    
    if ($durationMonths < 1 || $durationMonths > 12) {
        ob_end_clean();
        jsonError('Duration must be between 1 and 12 months', [], 400);
        exit;
    }
    
    // Convert selected date to 1st of that month
    $selectedDate = new DateTime($bookingStartDate);
    $bookingStartDate = $selectedDate->format('Y-m-01');
    
    try {
        $db = db();
        
        // Get all room configurations for this listing
        $roomConfigs = $db->fetchAll(
            "SELECT id, room_type, rent_per_month, total_rooms 
             FROM room_configurations 
             WHERE listing_id = ?",
            [$listingId]
        );
        
        $availability = [];
        
        foreach ($roomConfigs as $room) {
            // Calculate bed-based availability
            $bedsPerRoom = getBedsPerRoom($room['room_type']);
            $totalBeds = calculateTotalBeds($room['total_rooms'], $room['room_type']);
            
            // Check bed availability for all months in the duration
            $isAvailable = true;
            $minAvailableBeds = $totalBeds; // Start with total beds, find minimum across all months
            
            $startDate = new DateTime($bookingStartDate);
            for ($i = 0; $i < $durationMonths; $i++) {
                $checkDate = clone $startDate;
                $checkDate->modify("+{$i} months");
                $checkMonth = $checkDate->format('Y-m'); // Format to match DATE_FORMAT output
                
                // Count booked beds for this room config for this month (each booking = 1 bed)
                $bookedBeds = $db->fetchOne(
                    "SELECT COUNT(*) as count 
                     FROM bookings 
                     WHERE room_config_id = ? 
                     AND DATE_FORMAT(booking_start_date, '%Y-%m') = ?
                     AND status IN ('pending', 'confirmed')",
                    [$room['id'], $checkMonth]
                );
                
                $bookedBeds = (int)($bookedBeds['count'] ?? 0);
                $availableBedsForMonth = max(0, $totalBeds - $bookedBeds);
                
                // Track minimum available beds across all months (worst case availability)
                $minAvailableBeds = min($minAvailableBeds, $availableBedsForMonth);
                
                // If any month is fully booked (all beds), the room is not available
                if ($bookedBeds >= $totalBeds) {
                    $isAvailable = false;
                    break;
                }
            }
            
            $availableBeds = $isAvailable ? $minAvailableBeds : 0;
            
            $availability[] = [
                'id' => $room['id'],
                'room_type' => $room['room_type'],
                'rent_per_month' => $room['rent_per_month'],
                'total_rooms' => $room['total_rooms'],
                'total_beds' => $totalBeds,
                'beds_per_room' => $bedsPerRoom,
                'booked_beds' => $maxBookedBeds,
                'available_beds' => $availableBeds,
                'available_count' => $availableBeds, // Keep for backward compatibility
                'is_available' => $isAvailable
            ];
        }
        
        ob_end_clean();
        jsonSuccess('Availability checked', ['rooms' => $availability]);
        exit;
        
    } catch (Exception $e) {
        ob_end_clean();
        jsonError('Failed to check availability', [], 500);
        exit;
    }
}

jsonError('Invalid action', [], 400);

