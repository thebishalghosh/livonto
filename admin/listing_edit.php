<?php
/**
 * Admin Edit Listing Page
 * Edit existing PG listings with image management
 */

// Start session and load config/functions BEFORE handling POST
if (session_status() === PHP_SESSION_NONE) session_start();
require __DIR__ . '/../app/config.php';
require __DIR__ . '/../app/functions.php';

// Ensure admin is logged in
if (empty($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
    header('Location: ' . app_url('admin/login'));
    exit;
}

// Get listing ID
$listingId = intval($_GET['id'] ?? 0);

if (!$listingId) {
    $_SESSION['flash_message'] = 'Invalid listing ID';
    $_SESSION['flash_type'] = 'danger';
    header('Location: ' . app_url('admin/listings'));
    exit;
}

// Handle form submission for updating listing
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_listing') {
    $errors = [];

    // Get form data
    $title = trim($_POST['title'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $ownerName = trim($_POST['owner_name'] ?? '');
    $ownerEmail = trim($_POST['owner_email'] ?? '');
    $ownerPassword = $_POST['owner_password'] ?? '';
    $availableFor = $_POST['available_for'] ?? 'both';
    $genderAllowed = $_POST['gender_allowed'] ?? 'unisex';
    $preferredTenants = $_POST['preferred_tenants'] ?? 'anyone';
    $securityDeposit = trim($_POST['security_deposit_amount'] ?? 'No Deposit');
    $noticePeriod = intval($_POST['notice_period'] ?? 0);
    $status = $_POST['status'] ?? 'draft';

    // Location data
    $completeAddress = trim($_POST['complete_address'] ?? '');
    $city = trim($_POST['city'] ?? '');
    $pinCode = trim($_POST['pin_code'] ?? '');
    $googleMapsLink = trim($_POST['google_maps_link'] ?? '');
    $latitude = !empty($_POST['latitude']) ? floatval($_POST['latitude']) : null;
    $longitude = !empty($_POST['longitude']) ? floatval($_POST['longitude']) : null;
    $nearbyLandmarks = trim($_POST['nearby_landmarks'] ?? '');

    // Additional info - validate ENUM values
    $electricityCharges = !empty($_POST['electricity_charges']) ? $_POST['electricity_charges'] : null;
    // Validate electricity_charges against allowed ENUM values
    $allowedElectricityCharges = ['included', 'as per usage', 'as per usage of AC', 'separate meter'];
    if ($electricityCharges !== null && !in_array($electricityCharges, $allowedElectricityCharges)) {
        $electricityCharges = null;
    }

    $foodAvailability = !empty($_POST['food_availability']) ? $_POST['food_availability'] : null;
    // Validate food_availability against allowed ENUM values
    $allowedFoodAvailability = ['vegetarian', 'non-vegetarian', 'both', 'not available'];
    if ($foodAvailability !== null && !in_array($foodAvailability, $allowedFoodAvailability)) {
        $foodAvailability = null;
    }

    $gateClosingTime = !empty($_POST['gate_closing_time']) ? $_POST['gate_closing_time'] : null;

    // Room configurations
    $roomConfigs = isset($_POST['room_configs']) && is_array($_POST['room_configs']) ? $_POST['room_configs'] : [];

    // Amenities and rules
    $amenities = $_POST['amenities'] ?? [];
    $houseRules = $_POST['house_rules'] ?? [];

    // Handle new image uploads
    $newUploadedImages = [];
    $allowedTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/webp'];
    $maxSize = 5 * 1024 * 1024; // 5MB
    $allowedExts = ['jpg', 'jpeg', 'png', 'gif', 'webp'];

    if (isset($_FILES['new_images']) && is_array($_FILES['new_images']['name'])) {
        $fileCount = count($_FILES['new_images']['name']);

        // Check total images (existing + new) don't exceed 16
        $db = db();
        $existingCount = $db->fetchValue("SELECT COUNT(*) FROM listing_images WHERE listing_id = ?", [$listingId]);
        if ($existingCount + $fileCount > 16) {
            $errors[] = 'Total images cannot exceed 16. You can upload ' . (16 - $existingCount) . ' more image(s).';
        }

        for ($i = 0; $i < $fileCount; $i++) {
            if ($_FILES['new_images']['error'][$i] === UPLOAD_ERR_OK) {
                $file = [
                    'name' => $_FILES['new_images']['name'][$i],
                    'type' => $_FILES['new_images']['type'][$i],
                    'tmp_name' => $_FILES['new_images']['tmp_name'][$i],
                    'size' => $_FILES['new_images']['size'][$i],
                    'error' => $_FILES['new_images']['error'][$i]
                ];

                if (!in_array($file['type'], $allowedTypes)) {
                    $errors[] = "Image '{$file['name']}' must be a JPEG, PNG, GIF, or WebP image";
                    continue;
                }

                if ($file['size'] > $maxSize) {
                    $errors[] = "Image '{$file['name']}' must be less than 5MB";
                    continue;
                }

                $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
                if (!in_array($ext, $allowedExts)) {
                    $errors[] = "Invalid file extension for '{$file['name']}'";
                    continue;
                }

                $newUploadedImages[] = $file;
            }
        }
    }

    // Validate required fields
    if (empty($title)) {
        $errors[] = 'Title is required';
    }
    if (empty($description)) {
        $errors[] = 'Description is required';
    }
    if (empty($completeAddress)) {
        $errors[] = 'Complete address is required';
    }
    if (empty($city)) {
        $errors[] = 'City is required';
    }

    // Validate room configurations
    if (empty($roomConfigs)) {
        $errors[] = 'At least one room configuration is required';
    } else {
        foreach ($roomConfigs as $index => $config) {
            if (empty($config['room_type']) || empty($config['rent_per_month']) ||
                empty($config['total_rooms'])) {
                $errors[] = "Room configuration #" . ($index + 1) . " is incomplete";
            }

            // Calculate total beds based on room type
            $bedsPerRoom = 1;
            if ($config['room_type'] === 'double sharing') {
                $bedsPerRoom = 2;
            } elseif ($config['room_type'] === 'triple sharing') {
                $bedsPerRoom = 3;
            } elseif ($config['room_type'] === '4 sharing') {
                $bedsPerRoom = 4;
            }
            $totalBeds = intval($config['total_rooms']) * $bedsPerRoom;

            // Only validate available beds if manual override is ON
            if (!empty($config['is_manual_availability'])) {
                $availableBeds = intval($config['available_rooms'] ?? 0);
                // Validate: available beds cannot exceed total beds
                if ($availableBeds > $totalBeds) {
                    $errors[] = "Available beds cannot exceed total beds in configuration #" . ($index + 1) . " (Total: {$totalBeds} beds, Available: {$availableBeds} beds)";
                }
            }
        }
    }

    // Validate Owner Password Logic (MOVED OUTSIDE TRANSACTION)
    $ownerPasswordHash = null;
    if (empty($errors)) {
        $db = db();
        if (!empty($ownerEmail) && !empty($ownerPassword)) {
            // New password provided - hash it
            $ownerPasswordHash = password_hash($ownerPassword, PASSWORD_DEFAULT);
        } elseif (!empty($ownerEmail) && empty($ownerPassword)) {
            // Email provided but no password

            // 1. Check if THIS listing already has a password
            $existingListing = $db->fetchOne("SELECT owner_password_hash FROM listings WHERE id = ?", [$listingId]);

            if (!empty($existingListing['owner_password_hash'])) {
                // Keep existing password hash for this listing
                $ownerPasswordHash = $existingListing['owner_password_hash'];
            } else {
                // 2. Check if ANY OTHER listing has this email and a password
                // This handles the "One Owner, Multiple PGs" scenario
                $otherListing = $db->fetchOne(
                    "SELECT owner_password_hash FROM listings WHERE owner_email = ? AND owner_password_hash IS NOT NULL LIMIT 1",
                    [$ownerEmail]
                );

                if (!empty($otherListing['owner_password_hash'])) {
                    // Found existing owner password from another listing - reuse it
                    $ownerPasswordHash = $otherListing['owner_password_hash'];
                } else {
                    // Brand new owner email, no password anywhere
                    $errors[] = 'Password is required when setting owner email for the first time';
                }
            }
        }
    }

    if (empty($errors)) {
        try {
            $db = db();
            $db->beginTransaction();

            // Update main listing
            if ($ownerPasswordHash !== null) {
                // Update with password
                $db->execute(
                    "UPDATE listings SET title = ?, description = ?, owner_name = ?, owner_email = ?, owner_password_hash = ?,
                     available_for = ?, gender_allowed = ?, preferred_tenants = ?,
                     security_deposit_amount = ?, notice_period = ?, status = ?, updated_at = CURRENT_TIMESTAMP
                     WHERE id = ?",
                    [$title, $description, $ownerName, $ownerEmail, $ownerPasswordHash, $availableFor, $genderAllowed, $preferredTenants,
                     $securityDeposit, $noticePeriod, $status, $listingId]
                );
            } else {
                // Update without password (only email if provided, or remove email)
                $db->execute(
                    "UPDATE listings SET title = ?, description = ?, owner_name = ?, owner_email = ?,
                     available_for = ?, gender_allowed = ?, preferred_tenants = ?,
                     security_deposit_amount = ?, notice_period = ?, status = ?, updated_at = CURRENT_TIMESTAMP
                     WHERE id = ?",
                    [$title, $description, $ownerName, !empty($ownerEmail) ? $ownerEmail : null, $availableFor, $genderAllowed, $preferredTenants,
                     $securityDeposit, $noticePeriod, $status, $listingId]
                );
            }

            // Update location
            $landmarksJson = null;
            if (!empty($nearbyLandmarks)) {
                $landmarksArray = array_map('trim', explode(',', $nearbyLandmarks));
                $landmarksJson = json_encode($landmarksArray);
            }

            // Check if location exists, update or insert
            $existingLocation = $db->fetchOne(
                "SELECT id FROM listing_locations WHERE listing_id = ?",
                [$listingId]
            );

            if ($existingLocation) {
                $db->execute(
                    "UPDATE listing_locations SET complete_address = ?, city = ?, pin_code = ?,
                     google_maps_link = ?, latitude = ?, longitude = ?, nearby_landmarks = ?
                     WHERE listing_id = ?",
                    [$completeAddress, $city, $pinCode ?: null, $googleMapsLink ?: null,
                     $latitude, $longitude, $landmarksJson, $listingId]
                );
            } else {
                $db->execute(
                    "INSERT INTO listing_locations (listing_id, complete_address, city, pin_code,
                     google_maps_link, latitude, longitude, nearby_landmarks)
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?)",
                    [$listingId, $completeAddress, $city, $pinCode ?: null,
                     $googleMapsLink ?: null, $latitude, $longitude, $landmarksJson]
                );
            }

            // Update additional info
            $existingAdditionalInfo = $db->fetchOne(
                "SELECT id FROM listing_additional_info WHERE listing_id = ?",
                [$listingId]
            );

            // Calculate total beds for additional info
            $calculatedTotalBeds = 0;
            foreach ($roomConfigs as $config) {
                $bedsPerRoom = 1;
                if ($config['room_type'] === 'double sharing') $bedsPerRoom = 2;
                elseif ($config['room_type'] === 'triple sharing') $bedsPerRoom = 3;
                elseif ($config['room_type'] === '4 sharing') $bedsPerRoom = 4;
                $calculatedTotalBeds += (intval($config['total_rooms']) * $bedsPerRoom);
            }

            if ($existingAdditionalInfo) {
                $db->execute(
                    "UPDATE listing_additional_info SET electricity_charges = ?, food_availability = ?,
                     gate_closing_time = ?, total_beds = ?
                     WHERE listing_id = ?",
                    [$electricityCharges, $foodAvailability, $gateClosingTime, $calculatedTotalBeds, $listingId]
                );
            } else {
                $db->execute(
                    "INSERT INTO listing_additional_info (listing_id, electricity_charges, food_availability,
                     gate_closing_time, total_beds)
                     VALUES (?, ?, ?, ?, ?)",
                    [$listingId, $electricityCharges, $foodAvailability, $gateClosingTime, $calculatedTotalBeds]
                );
            }

            // SMART SYNC for Room Configurations
            // 1. Fetch existing IDs
            $existingRoomIds = $db->fetchAll(
                "SELECT id, total_rooms, available_rooms FROM room_configurations WHERE listing_id = ?",
                [$listingId]
            );
            $existingIdsMap = array_column($existingRoomIds, null, 'id'); // Map ID => Row Data

            // 2. Process submitted rooms (Update or Insert)
            foreach ($roomConfigs as $config) {
                $isManual = isset($config['is_manual_availability']) ? 1 : 0;
                $configId = isset($config['id']) ? intval($config['id']) : 0;

                if ($configId > 0 && isset($existingIdsMap[$configId])) {
                    // UPDATE existing room
                    $existingRow = $existingIdsMap[$configId];
                    $newTotalRooms = intval($config['total_rooms']);

                    // Determine available_rooms value
                    // If manual override is ON, use the submitted value
                    // If manual override is OFF, keep existing value (will be recalculated if needed)
                    $newAvailableRooms = $existingRow['available_rooms'];
                    if ($isManual) {
                        $newAvailableRooms = intval($config['available_rooms']);
                    }

                    $db->execute(
                        "UPDATE room_configurations
                         SET room_type = ?, rent_per_month = ?, total_rooms = ?, available_rooms = ?, is_manual_availability = ?
                         WHERE id = ? AND listing_id = ?",
                        [
                            $config['room_type'],
                            floatval($config['rent_per_month']),
                            $newTotalRooms,
                            $newAvailableRooms,
                            $isManual,
                            $configId,
                            $listingId
                        ]
                    );

                    // Remove from deletion map
                    unset($existingIdsMap[$configId]);

                    // Recalculate availability ONLY if:
                    // 1. Not in manual mode
                    // 2. Total rooms changed (capacity changed)
                    if (!$isManual && $newTotalRooms !== intval($existingRow['total_rooms'])) {
                        recalculateAvailableBeds($configId);
                    }

                } else {
                    // INSERT new room
                    $db->execute(
                        "INSERT INTO room_configurations (listing_id, room_type, rent_per_month, total_rooms, available_rooms, is_manual_availability)
                         VALUES (?, ?, ?, ?, ?, ?)",
                        [
                            $listingId,
                            $config['room_type'],
                            floatval($config['rent_per_month']),
                            intval($config['total_rooms']),
                            intval($config['available_rooms']), // Initial value, will be recalculated if not manual
                            $isManual
                        ]
                    );

                    $newRoomConfigId = $db->lastInsertId();
                    if ($newRoomConfigId && !$isManual) {
                        recalculateAvailableBeds($newRoomConfigId);
                    }
                }
            }

            // 3. Cleanup: Delete removed rooms ONLY if they have no bookings
            if (!empty($existingIdsMap)) {
                foreach ($existingIdsMap as $idToDelete => $row) {
                    // Check for bookings
                    $bookingCount = (int)$db->fetchValue(
                        "SELECT COUNT(*) FROM bookings WHERE room_config_id = ?",
                        [$idToDelete]
                    );

                    if ($bookingCount === 0) {
                        // Safe to delete - scoped to listing_id for extra safety
                        $db->execute("DELETE FROM room_configurations WHERE id = ? AND listing_id = ?", [$idToDelete, $listingId]);
                    } else {
                        // Has bookings - DO NOT DELETE.
                        // Optionally log this or handle it (e.g., mark as inactive if you had an 'active' column)
                        // For now, we just skip deletion to preserve data integrity.
                    }
                }
            }

            // Update amenities (delete existing and insert new)
            $db->execute("DELETE FROM listing_amenities WHERE listing_id = ?", [$listingId]);
            if (!empty($amenities)) {
                foreach ($amenities as $amenityId) {
                    $amenityId = intval($amenityId);
                    if ($amenityId > 0) {
                        try {
                            $db->execute(
                                "INSERT INTO listing_amenities (listing_id, amenity_id) VALUES (?, ?)",
                                [$listingId, $amenityId]
                            );
                        } catch (PDOException $e) {
                            if ($e->getCode() != 23000) throw $e;
                        }
                    }
                }
            }

            // Update house rules (delete existing and insert new)
            $db->execute("DELETE FROM listing_rules WHERE listing_id = ?", [$listingId]);
            if (!empty($houseRules)) {
                foreach ($houseRules as $ruleId) {
                    $ruleId = intval($ruleId);
                    if ($ruleId > 0) {
                        try {
                            $db->execute(
                                "INSERT INTO listing_rules (listing_id, rule_id) VALUES (?, ?)",
                                [$listingId, $ruleId]
                            );
                        } catch (PDOException $e) {
                            if ($e->getCode() != 23000) throw $e;
                        }
                    }
                }
            }

            // Upload new images
            if (!empty($newUploadedImages)) {
                $uploadDir = __DIR__ . '/../storage/uploads/listings/';
                if (!is_dir($uploadDir)) {
                    mkdir($uploadDir, 0755, true);
                }

                // Get current max order
                $maxOrder = (int)$db->fetchValue(
                    "SELECT COALESCE(MAX(image_order), -1) FROM listing_images WHERE listing_id = ?",
                    [$listingId]
                );

                foreach ($newUploadedImages as $index => $file) {
                    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
                    $uniqueId = uniqid('', true);
                    $filename = 'listing_' . time() . '_' . $uniqueId . '_' . ($maxOrder + $index + 1) . '.' . $ext;
                    $uploadPath = $uploadDir . $filename;

                    if (move_uploaded_file($file['tmp_name'], $uploadPath) && file_exists($uploadPath)) {
                        $imagePath = 'storage/uploads/listings/' . $filename;
                        $isCover = ($maxOrder === -1 && $index === 0) ? 1 : 0; // First image if no images exist

                        $db->execute(
                            "INSERT INTO listing_images (listing_id, image_path, image_order, is_cover)
                             VALUES (?, ?, ?, ?)",
                            [$listingId, $imagePath, $maxOrder + $index + 1, $isCover]
                        );

                        if ($isCover) {
                            $db->execute(
                                "UPDATE listings SET cover_image = ? WHERE id = ?",
                                [$imagePath, $listingId]
                            );
                        }
                    }
                }
            }

            $db->commit();

            $_SESSION['flash_message'] = 'Listing updated successfully!';
            $_SESSION['flash_type'] = 'success';
            header('Location: ' . app_url('admin/listings/view?id=' . $listingId));
            exit;

        } catch (PDOException $e) {
            if ($db->inTransaction()) {
                $db->rollback();
            }
            error_log("Database error updating listing: " . $e->getMessage());
            $errors[] = 'Database error: ' . (getenv('APP_DEBUG') === 'true' ? $e->getMessage() : 'Please try again');
        } catch (Exception $e) {
            if (isset($db) && $db->inTransaction()) {
                $db->rollback();
            }
            error_log("Error updating listing: " . $e->getMessage());
            $errors[] = 'Error updating listing: ' . $e->getMessage();
        }
    }

    if (!empty($errors)) {
        $_SESSION['flash_message'] = implode('<br>', $errors);
        $_SESSION['flash_type'] = 'danger';
        // Don't redirect - stay on edit page to show errors
    } else {
        // If no errors but didn't redirect, something went wrong
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $_SESSION['flash_message'] = 'Update completed but no redirect occurred. Please check the listing.';
            $_SESSION['flash_type'] = 'warning';
        }
    }
}

// Load listing data
try {
    $db = db();

    $listing = $db->fetchOne(
        "SELECT l.*,
                loc.complete_address, loc.city, loc.pin_code, loc.google_maps_link,
                loc.latitude, loc.longitude, loc.nearby_landmarks,
                add_info.electricity_charges, add_info.food_availability,
                add_info.gate_closing_time, add_info.total_beds
         FROM listings l
         LEFT JOIN listing_locations loc ON l.id = loc.listing_id
         LEFT JOIN listing_additional_info add_info ON l.id = add_info.listing_id
         WHERE l.id = ?",
        [$listingId]
    );

    if (!$listing) {
        $_SESSION['flash_message'] = 'Listing not found';
        $_SESSION['flash_type'] = 'danger';
        header('Location: ' . app_url('admin/listings'));
        exit;
    }

    // Get all listing images
    $listingImages = $db->fetchAll(
        "SELECT id, image_path, image_order, is_cover
         FROM listing_images
         WHERE listing_id = ?
         ORDER BY is_cover DESC, image_order ASC",
        [$listingId]
    );

    // Build image URLs
    $baseUrl = app_url('');
    $allImageUrls = [];

    foreach ($listingImages as $img) {
        $imagePath = trim($img['image_path']);
        if (empty($imagePath)) continue;

        if (strpos($imagePath, 'http') === 0 || strpos($imagePath, '//') === 0) {
            $fullUrl = $imagePath;
        } else {
            // Use app_url() for consistent path handling
            $fullUrl = app_url($imagePath);
            $imagePath = ltrim($imagePath, '/'); // Keep for localPath check below
        }

        $localPath = __DIR__ . '/../' . $imagePath;
        if (file_exists($localPath) || strpos($imagePath, 'http') === 0) {
            $allImageUrls[] = [
                'url' => $fullUrl,
                'path' => $imagePath,
                'is_cover' => (bool)$img['is_cover'],
                'order' => (int)$img['image_order'],
                'id' => (int)$img['id']
            ];
        }
    }

    // Get all amenities and house rules for checkboxes
    $allAmenities = $db->fetchAll("SELECT id, name FROM amenities ORDER BY name");
    $allHouseRules = $db->fetchAll("SELECT id, name FROM house_rules ORDER BY name");

    // Get selected amenities and rules
    $selectedAmenities = $db->fetchAll(
        "SELECT amenity_id FROM listing_amenities WHERE listing_id = ?",
        [$listingId]
    );
    $selectedAmenityIds = array_column($selectedAmenities, 'amenity_id');

    $selectedRules = $db->fetchAll(
        "SELECT rule_id FROM listing_rules WHERE listing_id = ?",
        [$listingId]
    );
    $selectedRuleIds = array_column($selectedRules, 'rule_id');

    // Get room configurations
    $existingRoomConfigs = $db->fetchAll(
        "SELECT * FROM room_configurations WHERE listing_id = ? ORDER BY rent_per_month ASC",
        [$listingId]
    );

    // Recalculate available_rooms for each configuration using unified calculation
    foreach ($existingRoomConfigs as &$config) {
        // Only recalculate if manual override is NOT enabled
        if (empty($config['is_manual_availability'])) {
            // Count booked beds (confirmed and pending bookings)
            $bookedBeds = (int)$db->fetchValue(
                "SELECT COUNT(*) FROM bookings
                 WHERE room_config_id = ? AND status IN ('pending', 'confirmed')",
                [$config['id']]
            );

            // Use unified calculation: total_beds - booked_beds (ensures consistency)
            $availableBeds = calculateAvailableBeds($config['total_rooms'], $config['room_type'], $bookedBeds);

            // Update the config array with recalculated value
            $config['available_rooms'] = $availableBeds;
        }
    }
    unset($config);

    // Parse nearby landmarks
    $nearbyLandmarksArray = [];
    if (!empty($listing['nearby_landmarks'])) {
        $landmarks = json_decode($listing['nearby_landmarks'], true);
        if (is_array($landmarks)) {
            $nearbyLandmarksArray = $landmarks;
        }
    }
    $nearbyLandmarksString = implode(', ', $nearbyLandmarksArray);

} catch (Exception $e) {
    error_log("Error loading listing: " . $e->getMessage());
    $_SESSION['flash_message'] = 'Error loading listing data';
    $_SESSION['flash_type'] = 'danger';
    header('Location: ' . app_url('admin/listings'));
    exit;
}

$pageTitle = "Edit Listing";
require __DIR__ . '/../app/includes/admin_header.php';

$flashMessage = getFlashMessage();
?>

<!-- Page Header -->
<div class="admin-page-header mb-4">
    <div class="d-flex justify-content-between align-items-center">
        <div>
            <h1 class="admin-page-title">Edit Listing</h1>
            <p class="admin-page-subtitle text-muted"><?= htmlspecialchars($listing['title']) ?></p>
        </div>
        <div>
            <a href="<?= htmlspecialchars(app_url('admin/listings/view?id=' . $listingId)) ?>" class="btn btn-secondary">
                <i class="bi bi-arrow-left me-2"></i>Back to View
            </a>
        </div>
    </div>
</div>

<!-- Flash Message -->
<?php if ($flashMessage): ?>
    <div class="alert alert-<?= htmlspecialchars($flashMessage['type']) ?> alert-dismissible fade show" role="alert">
        <?= $flashMessage['message'] ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<!-- Edit Listing Form -->
<form method="POST" enctype="multipart/form-data" id="editListingForm">
    <input type="hidden" name="action" value="update_listing">

    <!-- Basic Information -->
    <div class="admin-card mb-4">
        <div class="admin-card-header">
            <h5 class="admin-card-title">
                <i class="bi bi-info-circle me-2"></i>Basic Information
            </h5>
        </div>
        <div class="admin-card-body">
            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label">Title <span class="text-danger">*</span></label>
                    <input type="text" class="form-control" name="title"
                           value="<?= htmlspecialchars($listing['title']) ?>" required>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Owner Name</label>
                    <input type="text" class="form-control" name="owner_name"
                           value="<?= htmlspecialchars($listing['owner_name'] ?? '') ?>">
                </div>
                <div class="col-md-6">
                    <label class="form-label">Owner Email</label>
                    <input type="email" class="form-control" name="owner_email"
                           placeholder="owner@example.com"
                           value="<?= htmlspecialchars($listing['owner_email'] ?? '') ?>">
                    <small class="form-text text-muted">Allow owner to login and manage availability</small>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Owner Password</label>
                    <input type="password" class="form-control" name="owner_password"
                           placeholder="Leave empty to keep existing password">
                    <small class="form-text text-muted">Only enter if you want to change the password</small>
                </div>
                <div class="col-12">
                    <label class="form-label">Description <span class="text-danger">*</span></label>
                    <textarea class="form-control" name="description" rows="5" required><?= htmlspecialchars($listing['description']) ?></textarea>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Available For</label>
                    <select class="form-control filter-select" name="available_for">
                        <option value="both" <?= $listing['available_for'] === 'both' ? 'selected' : '' ?>>Both</option>
                        <option value="boys" <?= $listing['available_for'] === 'boys' ? 'selected' : '' ?>>Boys</option>
                        <option value="girls" <?= $listing['available_for'] === 'girls' ? 'selected' : '' ?>>Girls</option>
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Gender Allowed</label>
                    <select class="form-control filter-select" name="gender_allowed">
                        <option value="unisex" <?= $listing['gender_allowed'] === 'unisex' ? 'selected' : '' ?>>Unisex</option>
                        <option value="male" <?= $listing['gender_allowed'] === 'male' ? 'selected' : '' ?>>Male</option>
                        <option value="female" <?= $listing['gender_allowed'] === 'female' ? 'selected' : '' ?>>Female</option>
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Preferred Tenants</label>
                    <select class="form-control filter-select" name="preferred_tenants">
                        <option value="anyone" <?= ($listing['preferred_tenants'] ?? 'anyone') === 'anyone' ? 'selected' : '' ?>>Anyone</option>
                        <option value="students" <?= ($listing['preferred_tenants'] ?? '') === 'students' ? 'selected' : '' ?>>Students</option>
                        <option value="working professionals" <?= ($listing['preferred_tenants'] ?? '') === 'working professionals' ? 'selected' : '' ?>>Working Professionals</option>
                        <option value="family" <?= ($listing['preferred_tenants'] ?? '') === 'family' ? 'selected' : '' ?>>Family</option>
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Security Deposit (Months)</label>
                    <select class="form-control filter-select" name="security_deposit_amount">
                        <option value="No Deposit" <?= ($listing['security_deposit_amount'] ?? '') === 'No Deposit' ? 'selected' : '' ?>>No Deposit</option>
                        <option value="1" <?= ($listing['security_deposit_amount'] ?? '') == '1' ? 'selected' : '' ?>>1 Month Rent</option>
                        <option value="2" <?= ($listing['security_deposit_amount'] ?? '') == '2' ? 'selected' : '' ?>>2 Months Rent</option>
                        <option value="3" <?= ($listing['security_deposit_amount'] ?? '') == '3' ? 'selected' : '' ?>>3 Months Rent</option>
                        <option value="4" <?= ($listing['security_deposit_amount'] ?? '') == '4' ? 'selected' : '' ?>>4 Months Rent</option>
                        <option value="5" <?= ($listing['security_deposit_amount'] ?? '') == '5' ? 'selected' : '' ?>>5 Months Rent</option>
                        <option value="6" <?= ($listing['security_deposit_amount'] ?? '') == '6' ? 'selected' : '' ?>>6 Months Rent</option>
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Notice Period (days)</label>
                    <input type="number" class="form-control" name="notice_period"
                           min="0" value="<?= intval($listing['notice_period'] ?? 0) ?>">
                </div>
                <div class="col-md-4">
                    <label class="form-label">Status</label>
                    <select class="form-control filter-select" name="status">
                        <option value="draft" <?= $listing['status'] === 'draft' ? 'selected' : '' ?>>Draft</option>
                        <option value="active" <?= $listing['status'] === 'active' ? 'selected' : '' ?>>Active</option>
                        <option value="inactive" <?= $listing['status'] === 'inactive' ? 'selected' : '' ?>>Inactive</option>
                    </select>
                </div>
            </div>
        </div>
    </div>

    <!-- Location Information -->
    <div class="admin-card mb-4">
        <div class="admin-card-header">
            <h5 class="admin-card-title">
                <i class="bi bi-geo-alt me-2"></i>Location Information
            </h5>
        </div>
        <div class="admin-card-body">
            <div class="row g-3">
                <div class="col-12">
                    <label class="form-label">Complete Address <span class="text-danger">*</span></label>
                    <textarea class="form-control" name="complete_address" rows="3" required><?= htmlspecialchars($listing['complete_address'] ?? '') ?></textarea>
                </div>
                <div class="col-md-4">
                    <label class="form-label">City <span class="text-danger">*</span></label>
                    <input type="text" class="form-control" name="city" required
                           value="<?= htmlspecialchars($listing['city'] ?? '') ?>"
                           placeholder="e.g., Kolkata">
                </div>
                <div class="col-md-4">
                    <label class="form-label">Pin Code</label>
                    <input type="text" class="form-control" name="pin_code"
                           value="<?= htmlspecialchars($listing['pin_code'] ?? '') ?>"
                           placeholder="e.g., 700001" maxlength="10">
                </div>
                <div class="col-md-4">
                    <label class="form-label">Google Maps Link</label>
                    <input type="url" class="form-control" name="google_maps_link"
                           value="<?= htmlspecialchars($listing['google_maps_link'] ?? '') ?>"
                           placeholder="https://maps.google.com/...">
                </div>
                <div class="col-md-6">
                    <label class="form-label">Latitude</label>
                    <input type="number" step="any" class="form-control" name="latitude"
                           value="<?= $listing['latitude'] ?? '' ?>"
                           placeholder="e.g., 22.5726">
                </div>
                <div class="col-md-6">
                    <label class="form-label">Longitude</label>
                    <input type="number" step="any" class="form-control" name="longitude"
                           value="<?= $listing['longitude'] ?? '' ?>"
                           placeholder="e.g., 88.3639">
                </div>
                <div class="col-12">
                    <label class="form-label">Nearby Landmarks</label>
                    <input type="text" class="form-control" name="nearby_landmarks"
                           value="<?= htmlspecialchars($nearbyLandmarksString) ?>"
                           placeholder="Comma separated: IIT, Metro Station, Mall">
                    <small class="text-muted">Enter landmarks separated by commas</small>
                </div>
            </div>
        </div>
    </div>

    <!-- Room Configurations -->
    <div class="admin-card mb-4">
        <div class="admin-card-header">
            <h5 class="admin-card-title">
                <i class="bi bi-door-open me-2"></i>Room Configurations
            </h5>
        </div>
        <div class="admin-card-body">
            <div id="roomConfigs">
                <?php if (!empty($existingRoomConfigs)): ?>
                    <?php foreach ($existingRoomConfigs as $index => $config): ?>
                        <div class="row g-3 mb-3 border rounded p-3 room-config-item">
                            <div class="col-md-3">
                                <label class="form-label">Room Type <span class="text-danger">*</span></label>
                                <select class="form-control filter-select" name="room_configs[<?= $index ?>][room_type]" required>
                                    <option value="single sharing" <?= $config['room_type'] === 'single sharing' ? 'selected' : '' ?>>Single Sharing</option>
                                    <option value="double sharing" <?= $config['room_type'] === 'double sharing' ? 'selected' : '' ?>>Double Sharing</option>
                                    <option value="triple sharing" <?= $config['room_type'] === 'triple sharing' ? 'selected' : '' ?>>Triple Sharing</option>
                                    <option value="4 sharing" <?= $config['room_type'] === '4 sharing' ? 'selected' : '' ?>>4 Sharing</option>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Rent per Month (₹) <span class="text-danger">*</span></label>
                                <input type="number" step="0.01" class="form-control" name="room_configs[<?= $index ?>][rent_per_month]"
                                       required min="0" value="<?= floatval($config['rent_per_month']) ?>">
                            </div>
                            <div class="col-md-2">
                                <label class="form-label">Total Rooms <span class="text-danger">*</span></label>
                                <input type="number" class="form-control" name="room_configs[<?= $index ?>][total_rooms]"
                                       required min="1" value="<?= intval($config['total_rooms']) ?>">
                            </div>
                            <div class="col-md-2">
                                <label class="form-label">Available Beds <span class="text-danger">*</span></label>
                                <input type="number" class="form-control" name="room_configs[<?= $index ?>][available_rooms]"
                                       required min="0" value="<?= intval($config['available_rooms']) ?>">
                                <div class="form-check mt-1">
                                    <input class="form-check-input" type="checkbox"
                                           name="room_configs[<?= $index ?>][is_manual_availability]"
                                           value="1"
                                           id="manual_<?= $index ?>"
                                           <?= !empty($config['is_manual_availability']) ? 'checked' : '' ?>>
                                    <label class="form-check-label small text-muted" for="manual_<?= $index ?>">
                                        Manual Override
                                    </label>
                                </div>
                                <!-- Hidden ID field for Smart Sync -->
                                <input type="hidden" name="room_configs[<?= $index ?>][id]" value="<?= $config['id'] ?>">
                            </div>
                            <div class="col-md-2 d-flex align-items-end">
                                <button type="button" class="btn btn-outline-danger w-100" onclick="removeRoomConfig(this)">
                                    <i class="bi bi-trash"></i> Remove
                                </button>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <!-- If no existing configs, add one by default -->
                    <script>
                    document.addEventListener('DOMContentLoaded', function() {
                        addRoomConfig();
                    });
                    </script>
                <?php endif; ?>
            </div>
            <button type="button" class="btn btn-outline-primary" onclick="addRoomConfig()">
                <i class="bi bi-plus-circle me-2"></i>Add Room Type
            </button>
        </div>
    </div>

    <!-- Additional Information -->
    <div class="admin-card mb-4">
        <div class="admin-card-header">
            <h5 class="admin-card-title">
                <i class="bi bi-info-square me-2"></i>Additional Information
            </h5>
        </div>
        <div class="admin-card-body">
            <div class="row g-3">
                <div class="col-md-4">
                    <label class="form-label">Electricity Charges</label>
                    <select class="form-control filter-select" name="electricity_charges">
                        <option value="">Select</option>
                        <option value="included" <?= ($listing['electricity_charges'] ?? '') === 'included' ? 'selected' : '' ?>>Included</option>
                        <option value="as per usage" <?= ($listing['electricity_charges'] ?? '') === 'as per usage' ? 'selected' : '' ?>>As per usage</option>
                        <option value="as per usage of AC" <?= ($listing['electricity_charges'] ?? '') === 'as per usage of AC' ? 'selected' : '' ?>>As per usage of AC</option>
                        <option value="separate meter" <?= ($listing['electricity_charges'] ?? '') === 'separate meter' ? 'selected' : '' ?>>Separate meter</option>
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Food Availability</label>
                    <select class="form-control filter-select" name="food_availability">
                        <option value="">Select</option>
                        <option value="vegetarian" <?= ($listing['food_availability'] ?? '') === 'vegetarian' ? 'selected' : '' ?>>Vegetarian</option>
                        <option value="non-vegetarian" <?= ($listing['food_availability'] ?? '') === 'non-vegetarian' ? 'selected' : '' ?>>Non-vegetarian</option>
                        <option value="both" <?= ($listing['food_availability'] ?? '') === 'both' ? 'selected' : '' ?>>Both</option>
                        <option value="not available" <?= ($listing['food_availability'] ?? '') === 'not available' ? 'selected' : '' ?>>Not available</option>
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Gate Closing Time</label>
                    <input type="time" class="form-control" name="gate_closing_time"
                           value="<?= htmlspecialchars($listing['gate_closing_time'] ?? '') ?>">
                </div>
                <!-- Total Beds field removed (auto-calculated) -->
            </div>
        </div>
    </div>

    <!-- Amenities -->
    <div class="admin-card mb-4">
        <div class="admin-card-header">
            <h5 class="admin-card-title">
                <i class="bi bi-star me-2"></i>Amenities
            </h5>
        </div>
        <div class="admin-card-body">
            <?php if (empty($allAmenities)): ?>
                <p class="text-muted">No amenities available. <a href="<?= app_url('admin/amenities') ?>">Add amenities first</a></p>
            <?php else: ?>
                <div class="row g-2">
                    <?php foreach ($allAmenities as $amenity): ?>
                        <div class="col-md-3 col-6">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox"
                                       name="amenities[]"
                                       value="<?= $amenity['id'] ?>"
                                       id="amenity_<?= $amenity['id'] ?>"
                                       <?= in_array($amenity['id'], $selectedAmenityIds) ? 'checked' : '' ?>>
                                <label class="form-check-label" for="amenity_<?= $amenity['id'] ?>">
                                    <?= htmlspecialchars($amenity['name']) ?>
                                </label>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- House Rules -->
    <div class="admin-card mb-4">
        <div class="admin-card-header">
            <h5 class="admin-card-title">
                <i class="bi bi-shield-check me-2"></i>House Rules
            </h5>
        </div>
        <div class="admin-card-body">
            <?php if (empty($allHouseRules)): ?>
                <p class="text-muted">No house rules available. <a href="<?= app_url('admin/house-rules') ?>">Add house rules first</a></p>
            <?php else: ?>
                <div class="row g-2">
                    <?php foreach ($allHouseRules as $rule): ?>
                        <div class="col-md-3 col-6">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox"
                                       name="house_rules[]"
                                       value="<?= $rule['id'] ?>"
                                       id="rule_<?= $rule['id'] ?>"
                                       <?= in_array($rule['id'], $selectedRuleIds) ? 'checked' : '' ?>>
                                <label class="form-check-label" for="rule_<?= $rule['id'] ?>">
                                    <?= htmlspecialchars($rule['name']) ?>
                                </label>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Image Management -->
    <div class="admin-card mb-4">
        <div class="admin-card-header">
            <h5 class="admin-card-title">
                <i class="bi bi-images me-2"></i>Images Management
                <span class="badge bg-secondary ms-2"><?= count($allImageUrls) ?> / 16 images</span>
            </h5>
        </div>
        <div class="admin-card-body">
            <!-- Existing Images -->
            <?php if (!empty($allImageUrls)): ?>
                <div class="mb-4">
                    <label class="form-label mb-3">Current Images (Drag to reorder)</label>
                    <div id="imagesContainer" class="row g-3">
                        <?php foreach ($allImageUrls as $index => $img): ?>
                            <div class="col-md-3 col-sm-4 col-6 image-item"
                                 data-image-id="<?= $img['id'] ?>"
                                 data-order="<?= $img['order'] ?>"
                                 style="cursor: move;">
                                <div class="position-relative border rounded p-2 bg-light">
                                    <div class="position-relative">
                                        <img src="<?= htmlspecialchars($img['url']) ?>?v=<?= $img['id'] ?>&t=<?= time() ?>"
                                             alt="Image <?= $index + 1 ?>"
                                             class="img-fluid rounded"
                                             style="height: 150px; width: 100%; object-fit: cover;">
                                        <?php if ($img['is_cover']): ?>
                                            <span class="badge bg-primary position-absolute top-0 start-0 m-1">
                                                <i class="bi bi-star-fill me-1"></i>Cover
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                    <div class="mt-2 d-flex gap-1 flex-wrap">
                                        <?php if (!$img['is_cover']): ?>
                                            <button type="button"
                                                    class="btn btn-sm btn-outline-primary flex-fill set-cover-btn"
                                                    data-image-id="<?= $img['id'] ?>"
                                                    data-listing-id="<?= $listingId ?>">
                                                <i class="bi bi-star me-1"></i>Set Cover
                                            </button>
                                        <?php endif; ?>
                                        <button type="button"
                                                class="btn btn-sm btn-outline-danger flex-fill delete-image-btn"
                                                data-image-id="<?= $img['id'] ?>"
                                                data-listing-id="<?= $listingId ?>">
                                            <i class="bi bi-trash me-1"></i>Delete
                                        </button>
                                    </div>
                                    <div class="text-center mt-1">
                                        <small class="text-muted">Order: <?= $img['order'] + 1 ?></small>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php else: ?>
                <div class="alert alert-info">
                    <i class="bi bi-info-circle me-2"></i>No images uploaded yet. Upload images below.
                </div>
            <?php endif; ?>

            <!-- Upload New Images -->
            <?php if (count($allImageUrls) < 16): ?>
                <div class="mt-4">
                    <label class="form-label">Upload New Images</label>
                    <input type="file"
                           class="form-control"
                           name="new_images[]"
                           id="newImagesInput"
                           accept="image/jpeg,image/jpg,image/png,image/gif,image/webp"
                           multiple>
                    <small class="text-muted">
                        You can upload up to <?= 16 - count($allImageUrls) ?> more image(s).
                        JPEG, PNG, GIF, or WebP (max 5MB each).
                    </small>
                </div>
            <?php else: ?>
                <div class="alert alert-warning">
                    <i class="bi bi-exclamation-triangle me-2"></i>Maximum 16 images allowed. Delete an image to upload a new one.
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Form Actions -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <a href="<?= htmlspecialchars(app_url('admin/listings/view?id=' . $listingId)) ?>" class="btn btn-secondary">
            <i class="bi bi-x me-2"></i>Cancel
        </a>
        <button type="submit" class="btn btn-primary">
            <i class="bi bi-check-lg me-2"></i>Save Changes
        </button>
    </div>
</form>

<script>
const baseUrl = '<?= htmlspecialchars($baseUrl) ?>';
const listingId = <?= $listingId ?>;

// Room configurations management
let roomConfigCount = <?= count($existingRoomConfigs) > 0 ? count($existingRoomConfigs) - 1 : -1 ?>; // Start from last index (or -1 if empty)

function addRoomConfig() {
    roomConfigCount++;
    const container = document.getElementById('roomConfigs');
    const div = document.createElement('div');
    div.className = 'row g-3 mb-3 border rounded p-3 room-config-item';
    div.innerHTML = `
        <div class="col-md-3">
            <label class="form-label">Room Type <span class="text-danger">*</span></label>
            <select class="form-control filter-select" name="room_configs[${roomConfigCount}][room_type]" required>
                <option value="single sharing">Single Sharing</option>
                <option value="double sharing">Double Sharing</option>
                <option value="triple sharing">Triple Sharing</option>
                <option value="4 sharing">4 Sharing</option>
            </select>
        </div>
        <div class="col-md-3">
            <label class="form-label">Rent per Month (₹) <span class="text-danger">*</span></label>
            <input type="number" step="0.01" class="form-control" name="room_configs[${roomConfigCount}][rent_per_month]" required min="0">
        </div>
        <div class="col-md-2">
            <label class="form-label">Total Rooms <span class="text-danger">*</span></label>
            <input type="number" class="form-control" name="room_configs[${roomConfigCount}][total_rooms]" required min="1" value="1">
        </div>
        <div class="col-md-2">
            <label class="form-label">Available Beds <span class="text-danger">*</span></label>
            <input type="number" class="form-control" name="room_configs[${roomConfigCount}][available_rooms]" required min="0" value="1">
            <div class="form-check mt-1">
                <input class="form-check-input" type="checkbox"
                       name="room_configs[${roomConfigCount}][is_manual_availability]"
                       value="1"
                       id="manual_${roomConfigCount}">
                <label class="form-check-label small text-muted" for="manual_${roomConfigCount}">
                    Manual Override
                </label>
            </div>
            <!-- Hidden ID field for new items (empty) -->
            <input type="hidden" name="room_configs[${roomConfigCount}][id]" value="">
        </div>
        <div class="col-md-2 d-flex align-items-end">
            <button type="button" class="btn btn-outline-danger w-100" onclick="removeRoomConfig(this)">
                <i class="bi bi-trash"></i> Remove
            </button>
        </div>
    `;
    container.appendChild(div);
}

function removeRoomConfig(btn) {
    btn.closest('.room-config-item').remove();
}

// Delete image
document.querySelectorAll('.delete-image-btn').forEach(btn => {
    btn.addEventListener('click', async function() {
        const imageId = this.dataset.imageId;
        const listingId = this.dataset.listingId;

        if (!confirm('Are you sure you want to delete this image? This action cannot be undone.')) {
            return;
        }

        const formData = new FormData();
        formData.append('action', 'delete');
        formData.append('image_id', imageId);
        formData.append('listing_id', listingId);

        try {
            // Construct absolute URL
            let apiUrl;
            if (baseUrl && (baseUrl.startsWith('http://') || baseUrl.startsWith('https://'))) {
                apiUrl = baseUrl.replace(/\/+$/, '') + '/listing-images-api';
            } else {
                const protocol = window.location.protocol;
                const host = window.location.host;
                const basePath = baseUrl ? baseUrl.replace(/\/+$/, '') : '';
                apiUrl = protocol + '//' + host + basePath + '/listing-images-api';
            }

            const response = await fetch(apiUrl, {
                method: 'POST',
                body: formData
            });

            if (!response.ok) {
                const errorText = await response.text();
                throw new Error(`HTTP error! status: ${response.status}, response: ${errorText}`);
            }

            const data = await response.json();

            if (data.status === 'success') {
                // Remove image from DOM
                this.closest('.image-item').remove();
                // Reload page to refresh image count
                setTimeout(() => location.reload(), 500);
            } else {
                alert('Error: ' + (data.message || 'Failed to delete image'));
            }
        } catch (error) {
            alert('An error occurred while deleting the image: ' + error.message);
        }
    });
});

// Set cover image
document.querySelectorAll('.set-cover-btn').forEach(btn => {
    btn.addEventListener('click', async function(e) {
        e.preventDefault();
        e.stopPropagation();

        const imageId = this.dataset.imageId;
        const listingId = this.dataset.listingId;

        if (!imageId || !listingId) {
            alert('Error: Missing image or listing ID');
            return;
        }

        // Disable button during request
        const originalText = this.innerHTML;
        this.disabled = true;
        this.innerHTML = '<i class="bi bi-hourglass-split me-1"></i>Setting...';

        const formData = new FormData();
        formData.append('action', 'set_cover');
        formData.append('image_id', imageId);
        formData.append('listing_id', listingId);

        try {
            // Construct absolute URL
            let apiUrl;
            if (baseUrl && (baseUrl.startsWith('http://') || baseUrl.startsWith('https://'))) {
                // baseUrl is already absolute
                apiUrl = baseUrl.replace(/\/+$/, '') + '/listing-images-api';
            } else {
                // Construct full URL from current location
                const protocol = window.location.protocol;
                const host = window.location.host;
                const basePath = baseUrl ? baseUrl.replace(/\/+$/, '') : '';
                apiUrl = protocol + '//' + host + basePath + '/listing-images-api';
            }
            const response = await fetch(apiUrl, {
                method: 'POST',
                body: formData
            });

            if (!response.ok) {
                const errorText = await response.text();
                throw new Error(`HTTP error! status: ${response.status}, response: ${errorText}`);
            }

            const data = await response.json();

            if (data.status === 'success') {
                // Reload page to show updated cover badge
                location.reload();
            } else {
                alert('Error: ' + (data.message || 'Failed to set cover image'));
                this.disabled = false;
                this.innerHTML = originalText;
            }
        } catch (error) {
            alert('An error occurred while setting cover image: ' + error.message);
            this.disabled = false;
            this.innerHTML = originalText;
        }
    });
});

// Drag and drop reordering
let draggedElement = null;
let draggedFromIndex = null;

// Prevent buttons from interfering with drag
document.querySelectorAll('.image-item button').forEach(btn => {
    btn.addEventListener('mousedown', function(e) {
        e.stopPropagation();
    });
});

// Make container droppable
const imagesContainer = document.getElementById('imagesContainer');
if (imagesContainer) {
    imagesContainer.addEventListener('dragover', function(e) {
        e.preventDefault();
        e.stopPropagation();

        if (!draggedElement) return;

        // Find which item we're over
        const imageItems = Array.from(document.querySelectorAll('.image-item'));
        const afterElement = imageItems.find(item => {
            const rect = item.getBoundingClientRect();
            return e.clientY < rect.top + rect.height / 2;
        });

        if (afterElement && afterElement !== draggedElement) {
            imagesContainer.insertBefore(draggedElement, afterElement);
        } else if (!afterElement && draggedElement !== imageItems[imageItems.length - 1]) {
            imagesContainer.appendChild(draggedElement);
        }
    });

    imagesContainer.addEventListener('drop', async function(e) {
        e.preventDefault();
        e.stopPropagation();

        if (!draggedElement) return;

        // Get all items in their current DOM order
        const allItems = Array.from(document.querySelectorAll('.image-item'));
        const newOrder = allItems.indexOf(draggedElement);
        const imageId = draggedElement.dataset.imageId;

        // Only proceed if order actually changed
        if (newOrder === draggedFromIndex) {
            draggedElement.style.opacity = '1';
            draggedElement = null;
            draggedFromIndex = null;
            return; // No change needed
        }

        const formData = new FormData();
        formData.append('action', 'reorder');
        formData.append('image_id', imageId);
        formData.append('listing_id', listingId);
        formData.append('new_order', newOrder);

        // Show loading state
        draggedElement.style.opacity = '0.7';

        try {
            // Construct absolute URL
            let apiUrl;
            if (baseUrl && (baseUrl.startsWith('http://') || baseUrl.startsWith('https://'))) {
                apiUrl = baseUrl.replace(/\/+$/, '') + '/listing-images-api';
            } else {
                const protocol = window.location.protocol;
                const host = window.location.host;
                const basePath = baseUrl ? baseUrl.replace(/\/+$/, '') : '';
                apiUrl = protocol + '//' + host + basePath + '/listing-images-api';
            }

            const response = await fetch(apiUrl, {
                method: 'POST',
                body: formData
            });

            if (!response.ok) {
                const errorText = await response.text();
                throw new Error(`HTTP error! status: ${response.status}, response: ${errorText}`);
            }

            const data = await response.json();

            if (data.status === 'success') {
                // Update order display and data attributes
                document.querySelectorAll('.image-item').forEach((item, index) => {
                    item.dataset.order = index;
                    const orderText = item.querySelector('small');
                    if (orderText) {
                        orderText.textContent = 'Order: ' + (index + 1);
                    }
                });

                // Reload page to refresh from database
                setTimeout(() => location.reload(), 300);
            } else {
                alert('Error: ' + (data.message || 'Failed to reorder images'));
                location.reload();
            }
        } catch (error) {
            alert('An error occurred while reordering images: ' + error.message);
            location.reload();
        } finally {
            if (draggedElement) {
                draggedElement.style.opacity = '1';
            }
            draggedElement = null;
            draggedFromIndex = null;
        }
    });
}

// Make each image item draggable
document.querySelectorAll('.image-item').forEach(item => {
    item.setAttribute('draggable', 'true');

    item.addEventListener('dragstart', function(e) {
        // Don't drag if clicking on a button
        if (e.target.tagName === 'BUTTON' || e.target.closest('button')) {
            e.preventDefault();
            return false;
        }

        draggedElement = this;
        draggedFromIndex = Array.from(document.querySelectorAll('.image-item')).indexOf(this);
        this.style.opacity = '0.5';
        this.style.cursor = 'grabbing';

        // Set drag data
        e.dataTransfer.effectAllowed = 'move';
        e.dataTransfer.setData('text/html', this.outerHTML);
    });

    item.addEventListener('dragend', function(e) {
        if (this === draggedElement) {
            this.style.opacity = '1';
            this.style.cursor = 'move';
        }
    });

    // Prevent default drag behavior on buttons
    item.querySelectorAll('button').forEach(btn => {
        btn.setAttribute('draggable', 'false');
    });
});
</script>

<?php require __DIR__ . '/../app/includes/admin_footer.php'; ?>
