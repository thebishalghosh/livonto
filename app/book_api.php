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
        
        // Get room configuration to check availability (bed-based) and rent
        $roomConfig = $db->fetchOne(
            "SELECT rent_per_month, total_rooms, room_type, is_manual_availability, available_rooms FROM room_configurations WHERE id = ? AND listing_id = ?",
            [$roomConfigId, $listingId]
        );
        
        if (!$roomConfig) {
            jsonError('Invalid room configuration', [], 400);
            exit;
        }

        // Check availability
        // If manual override is ON, check the stored available_rooms
        if (!empty($roomConfig['is_manual_availability'])) {
            if ($roomConfig['available_rooms'] <= 0) {
                jsonError("No beds available for this configuration (Manual Override).", [], 400);
                exit;
            }
        } else {
            // Standard calculation logic
            $bedsPerRoom = getBedsPerRoom($roomConfig['room_type']);
            $totalBeds = calculateTotalBeds($roomConfig['total_rooms'], $roomConfig['room_type']);

            // Check bed availability for all months in the duration
            $startDate = new DateTime($bookingStartDate);
            for ($i = 0; $i < $durationMonths; $i++) {
                $checkDate = clone $startDate;
                $checkDate->modify("+{$i} months");
                $checkMonth = $checkDate->format('Y-m'); // Format to match DATE_FORMAT output

                // Count booked beds for this room config for this month (each booking = 1 bed)
                // Only confirmed bookings affect availability
                $bookedBeds = $db->fetchOne(
                    "SELECT COUNT(*) as count
                     FROM bookings
                     WHERE room_config_id = ?
                     AND DATE_FORMAT(booking_start_date, '%Y-%m') = ?
                     AND status = 'confirmed'",
                    [$roomConfigId, $checkMonth]
                );

                $bookedBeds = (int)($bookedBeds['count'] ?? 0);

                // Check if beds are available for this month
                if ($bookedBeds >= $totalBeds) {
                    $monthName = $checkDate->format('F Y');
                    jsonError("No beds available for this configuration in {$monthName}. All beds are already booked.", [], 400);
                    exit;
                }
            }
        }
        
        // Calculate Security Deposit
        $securityDepositAmount = 0;
        $depositStr = trim($listing['security_deposit_amount'] ?? '');

        // Check if deposit is a number of months (1-6)
        if (is_numeric($depositStr) && intval($depositStr) >= 1 && intval($depositStr) <= 6) {
            // Dynamic calculation: Rent * Months
            $months = intval($depositStr);
            $rentPerMonth = floatval($roomConfig['rent_per_month']);
            $securityDepositAmount = $rentPerMonth * $months;
        } else {
            // Fallback to fixed amount parsing
            if (!empty($depositStr) && strtolower($depositStr) !== 'no deposit') {
                // Extract numeric value from string like "₹10,000" or "10000"
                $depositStr = preg_replace('/[₹,\s]/', '', $depositStr);
                $securityDepositAmount = floatval($depositStr);
            }
        }
        
        if ($securityDepositAmount <= 0) {
            // Allow 0 deposit if explicitly set to "No Deposit" or 0 months, but warn if it seems like an error
            // For now, we'll assume 0 is valid if configured that way, or default to 1 month rent if invalid
            if (strtolower(trim($listing['security_deposit_amount'] ?? '')) !== 'no deposit') {
                 // Fallback: 1 month rent
                 $securityDepositAmount = floatval($roomConfig['rent_per_month']);
            }
        }
        
        // Calculate GST if enabled
        $gstEnabled = function_exists('getSetting') && getSetting('gst_enabled', '0') == '1';
        $gstPercentage = $gstEnabled ? floatval(getSetting('gst_percentage', '18')) : 0;
        $gstAmount = 0;
        $totalAmountWithGst = $securityDepositAmount;
        
        if ($gstEnabled && $gstPercentage > 0) {
            $gstAmount = ($securityDepositAmount * $gstPercentage) / 100;
            $totalAmountWithGst = $securityDepositAmount + $gstAmount;
        }
        
        // Add duration_months column if it doesn't exist (for backward compatibility)
        try {
            $db->execute("ALTER TABLE bookings ADD COLUMN duration_months INT DEFAULT 1 AFTER booking_start_date");
        } catch (Exception $e) {
            // Column might already exist, ignore
        }
        
        // Add GST columns if they don't exist
        try {
            $db->execute("ALTER TABLE bookings ADD COLUMN gst_amount DECIMAL(10,2) DEFAULT 0 AFTER total_amount");
        } catch (Exception $e) {
            // Column might already exist, ignore
        }
        
        try {
            $db->execute("ALTER TABLE payments ADD COLUMN gst_amount DECIMAL(10,2) DEFAULT 0 AFTER amount");
        } catch (Exception $e) {
            // Column might already exist, ignore
        }
        
        // Create booking with duration and GST
        $db->execute(
            "INSERT INTO bookings (user_id, listing_id, room_config_id, booking_start_date, duration_months,
                                  total_amount, gst_amount, kyc_id, agreed_to_tnc, tnc_accepted_at, special_requests, status)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, TRUE, NOW(), ?, 'pending')",
            [$userId, $listingId, $roomConfigId, $bookingStartDate, $durationMonths, $securityDepositAmount, $gstAmount, $kycId, $specialRequests]
        );
        
        $bookingId = $db->lastInsertId();
        
        // Note: Availability is NOT decreased for pending bookings
        // Availability will only decrease when booking status changes to 'confirmed'
        // This ensures pending bookings don't affect availability until payment is confirmed
        
        // Send admin notification about new booking with full PG details
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
                "New Booking Request - Booking #{$bookingId}",
                "New Booking Request",
                "A new booking request has been submitted and is pending payment confirmation.",
                [
                    'Booking ID' => '#' . $bookingId,
                    'User Name' => $user['name'] ?? 'Unknown',
                    'User Email' => $user['email'] ?? 'N/A',
                    'Property' => $listingDetails['title'] ?? 'Unknown',
                    'Address' => $listingDetails['complete_address'] ?? 'N/A',
                    'City / PIN' => trim(($listingDetails['city'] ?? '') .
                        (!empty($listingDetails['pin_code']) ? ' - ' . $listingDetails['pin_code'] : '')) ?: 'N/A',
                    'Google Maps' => $listingDetails['google_maps_link'] ?? 'N/A',
                    'Start Date' => date('F d, Y', strtotime($bookingStartDate)),
                    'Duration' => $durationMonths . ' month(s)',
                    'Total Amount' => '₹' . number_format($totalAmountWithGst, 2),
                    'Status' => 'Pending Payment'
                ],
                $baseUrl . 'admin/bookings',
                'View Booking'
            );
        } catch (Exception $e) {
            error_log("Failed to send admin notification for new booking: " . $e->getMessage());
        }
        
        // Create payment record with security deposit amount and GST
        $db->execute(
            "INSERT INTO payments (booking_id, amount, gst_amount, provider, status)
             VALUES (?, ?, ?, 'razorpay', 'initiated')",
            [$bookingId, $securityDepositAmount, $gstAmount]
        );
        
        jsonSuccess('Booking created successfully', [
            'booking_id' => $bookingId,
            'amount' => $totalAmountWithGst,
            'base_amount' => $securityDepositAmount,
            'gst_amount' => $gstAmount,
            'gst_percentage' => $gstPercentage,
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
            "SELECT id, room_type, rent_per_month, total_rooms, available_rooms, is_manual_availability
             FROM room_configurations 
             WHERE listing_id = ?",
            [$listingId]
        );
        
        $availability = [];
        
        foreach ($roomConfigs as $room) {
            // Check if manual override is enabled
            if (!empty($room['is_manual_availability'])) {
                // Use the stored manual value directly
                $availableBeds = (int)$room['available_rooms'];
                
                // For manual override, we assume this availability applies to all requested months
                // We don't calculate based on bookings because the admin has overridden it
                $isAvailable = $availableBeds > 0;
                
                // Calculate total beds just for display purposes
                $bedsPerRoom = getBedsPerRoom($room['room_type']);
                $totalBeds = calculateTotalBeds($room['total_rooms'], $room['room_type']);
                
                // We don't really know "booked beds" in manual mode without calculation,
                // but we can approximate it as Total - Available
                $maxBookedBeds = max(0, $totalBeds - $availableBeds);
                
            } else {
                // Standard calculation logic (Auto mode)

                // Calculate bed-based availability using unified calculation
                $bedsPerRoom = getBedsPerRoom($room['room_type']);
                $totalBeds = calculateTotalBeds($room['total_rooms'], $room['room_type']);

                // Check bed availability for all months in the duration
                // Calculate real-time available beds for each month
                $minAvailableBeds = null;
                $maxBookedBeds = 0;

                $startDate = new DateTime($bookingStartDate);
                for ($i = 0; $i < $durationMonths; $i++) {
                    $checkDate = clone $startDate;
                    $checkDate->modify("+{$i} months");
                    $checkMonth = $checkDate->format('Y-m'); // Format to match DATE_FORMAT output

                    // Count booked beds for this room config for this month (each booking = 1 bed)
                    // IMPORTANT: Only confirmed bookings should block availability.
                    $bookedBedsForMonth = $db->fetchOne(
                        "SELECT COUNT(*) as count
                         FROM bookings
                         WHERE room_config_id = ?
                         AND DATE_FORMAT(booking_start_date, '%Y-%m') = ?
                         AND status = 'confirmed'",
                        [$room['id'], $checkMonth]
                    );

                    $bookedBedsForMonth = (int)($bookedBedsForMonth['count'] ?? 0);
                    $maxBookedBeds = max($maxBookedBeds, $bookedBedsForMonth);

                    // Use unified calculation: total_beds - booked_beds (ensures consistency)
                    $availableBedsForMonth = calculateAvailableBeds($room['total_rooms'], $room['room_type'], $bookedBedsForMonth);

                    // Track minimum available beds across all months
                    if ($minAvailableBeds === null || $availableBedsForMonth < $minAvailableBeds) {
                        $minAvailableBeds = $availableBedsForMonth;
                    }
                }

                // Final available beds is the minimum across all months
                $availableBeds = $minAvailableBeds !== null ? max(0, $minAvailableBeds) : 0;
                $isAvailable = $availableBeds > 0;
            }

            $availability[] = [
                'id' => $room['id'],
                'room_type' => $room['room_type'],
                'rent_per_month' => $room['rent_per_month'],
                'total_rooms' => $room['total_rooms'],
                'total_beds' => isset($totalBeds) ? $totalBeds : 0,
                'beds_per_room' => isset($bedsPerRoom) ? $bedsPerRoom : 1,
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
