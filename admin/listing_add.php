<?php
/**
 * Admin Add Listing Page
 * Form to create new PG listings with all details
 */

$pageTitle = "Add New Listing";
require __DIR__ . '/../app/includes/admin_header.php';
require __DIR__ . '/../app/functions.php';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $errors = [];
    
    // Get form data
    $title = trim($_POST['title'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $ownerId = !empty($_POST['owner_id']) ? intval($_POST['owner_id']) : null;
    $availableFor = $_POST['available_for'] ?? 'both';
    $genderAllowed = $_POST['gender_allowed'] ?? 'unisex';
    $preferredTenants = $_POST['preferred_tenants'] ?? 'anyone';
    $securityDeposit = trim($_POST['security_deposit_amount'] ?? 'No Deposit');
    $noticePeriod = intval($_POST['notice_period'] ?? 0);
    // Handle cover image upload
    $coverImage = null;
    if (isset($_FILES['cover_image']) && $_FILES['cover_image']['error'] === UPLOAD_ERR_OK) {
        $file = $_FILES['cover_image'];
        $allowedTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/webp'];
        $maxSize = 5 * 1024 * 1024; // 5MB
        
        // Validate file type
        if (!in_array($file['type'], $allowedTypes)) {
            $errors[] = 'Cover image must be a JPEG, PNG, GIF, or WebP image';
        }
        
        // Validate file size
        if ($file['size'] > $maxSize) {
            $errors[] = 'Cover image must be less than 5MB';
        }
        
        // Validate file extension
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        $allowedExts = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
        if (!in_array($ext, $allowedExts)) {
            $errors[] = 'Invalid image file extension';
        }
    }
    $status = $_POST['status'] ?? 'draft';
    
    // Determine status from submit button
    if (isset($_POST['save_active'])) {
        $status = 'active';
    } elseif (isset($_POST['save_draft'])) {
        $status = 'draft';
    }
    
    // Location data
    $completeAddress = trim($_POST['complete_address'] ?? '');
    $city = trim($_POST['city'] ?? '');
    $pinCode = trim($_POST['pin_code'] ?? '');
    $googleMapsLink = trim($_POST['google_maps_link'] ?? '');
    $latitude = !empty($_POST['latitude']) ? floatval($_POST['latitude']) : null;
    $longitude = !empty($_POST['longitude']) ? floatval($_POST['longitude']) : null;
    $nearbyLandmarks = trim($_POST['nearby_landmarks'] ?? '');
    
    // Additional info
    $electricityCharges = $_POST['electricity_charges'] ?? null;
    $foodAvailability = $_POST['food_availability'] ?? null;
    $gateClosingTime = !empty($_POST['gate_closing_time']) ? $_POST['gate_closing_time'] : null;
    $totalBeds = intval($_POST['total_beds'] ?? 0);
    
    // Room configurations
    $roomConfigs = $_POST['room_configs'] ?? [];
    
    // Amenities and rules
    $amenities = $_POST['amenities'] ?? [];
    $houseRules = $_POST['house_rules'] ?? [];
    
    // Validation
    if (empty($title)) $errors[] = 'Title is required';
    if (empty($description)) $errors[] = 'Description is required';
    if (empty($completeAddress)) $errors[] = 'Complete address is required';
    if (empty($city)) $errors[] = 'City is required';
    
    // Validate room configurations
    if (empty($roomConfigs)) {
        $errors[] = 'At least one room configuration is required';
    } else {
        foreach ($roomConfigs as $index => $config) {
            if (empty($config['room_type']) || empty($config['rent_per_month']) || 
                empty($config['total_rooms']) || !isset($config['available_rooms'])) {
                $errors[] = "Room configuration #" . ($index + 1) . " is incomplete";
            }
            if (isset($config['available_rooms']) && intval($config['available_rooms']) > intval($config['total_rooms'])) {
                $errors[] = "Available rooms cannot exceed total rooms in configuration #" . ($index + 1);
            }
        }
    }
    
    // If no errors, save listing
    if (empty($errors)) {
        try {
            $db = db();
            $db->beginTransaction();
            
            // Handle file upload before inserting listing
            $coverImagePath = null;
            if (isset($_FILES['cover_image']) && $_FILES['cover_image']['error'] === UPLOAD_ERR_OK) {
                $file = $_FILES['cover_image'];
                $uploadDir = __DIR__ . '/../storage/uploads/listings/';
                
                // Create directory if it doesn't exist
                if (!is_dir($uploadDir)) {
                    mkdir($uploadDir, 0755, true);
                }
                
                // Generate unique filename
                $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
                $filename = 'listing_' . time() . '_' . uniqid() . '.' . $ext;
                $uploadPath = $uploadDir . $filename;
                
                // Move uploaded file
                if (move_uploaded_file($file['tmp_name'], $uploadPath)) {
                    // Store relative path from project root
                    $coverImagePath = 'storage/uploads/listings/' . $filename;
                } else {
                    throw new Exception('Failed to upload cover image');
                }
            }
            
            // 1. Insert main listing
            $db->execute(
                "INSERT INTO listings (owner_id, title, description, available_for, preferred_tenants, 
                 security_deposit_amount, notice_period, gender_allowed, cover_image, status)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)",
                [$ownerId, $title, $description, $availableFor, $preferredTenants, 
                 $securityDeposit, $noticePeriod, $genderAllowed, $coverImagePath, $status]
            );
            
            $listingId = $db->lastInsertId();
            
            // 2. Insert location
            $landmarksJson = null;
            if (!empty($nearbyLandmarks)) {
                $landmarksArray = array_map('trim', explode(',', $nearbyLandmarks));
                $landmarksJson = json_encode($landmarksArray);
            }
            
            $db->execute(
                "INSERT INTO listing_locations (listing_id, complete_address, city, pin_code, 
                 google_maps_link, latitude, longitude, nearby_landmarks)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?)",
                [$listingId, $completeAddress, $city, $pinCode ?: null, 
                 $googleMapsLink ?: null, $latitude, $longitude, $landmarksJson]
            );
            
            // 3. Insert room configurations
            foreach ($roomConfigs as $config) {
                $db->execute(
                    "INSERT INTO room_configurations (listing_id, room_type, rent_per_month, total_rooms, available_rooms)
                     VALUES (?, ?, ?, ?, ?)",
                    [
                        $listingId,
                        $config['room_type'],
                        floatval($config['rent_per_month']),
                        intval($config['total_rooms']),
                        intval($config['available_rooms'])
                    ]
                );
            }
            
            // 4. Insert additional info
            if ($electricityCharges || $foodAvailability || $gateClosingTime || $totalBeds > 0) {
                $db->execute(
                    "INSERT INTO listing_additional_info (listing_id, electricity_charges, food_availability, 
                     gate_closing_time, total_beds)
                     VALUES (?, ?, ?, ?, ?)",
                    [$listingId, $electricityCharges, $foodAvailability, $gateClosingTime, $totalBeds]
                );
            }
            
            // 5. Insert amenities (many-to-many)
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
                            // Ignore duplicate entries
                            if ($e->getCode() != 23000) throw $e;
                        }
                    }
                }
            }
            
            // 6. Insert house rules (many-to-many)
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
                            // Ignore duplicate entries
                            if ($e->getCode() != 23000) throw $e;
                        }
                    }
                }
            }
            
            // 7. Log admin action
            try {
                $db->execute(
                    "INSERT INTO admin_actions (admin_id, action, target_type, target_id)
                     VALUES (?, ?, 'listing', ?)",
                    [$_SESSION['user_id'], 'Created listing: ' . $title, 'listing', $listingId]
                );
            } catch (Exception $e) {
                // Log action failure but don't fail the whole operation
                error_log("Failed to log admin action: " . $e->getMessage());
            }
            
            $db->commit();
            
            error_log("Admin created listing: ID {$listingId} ({$title}) by Admin ID {$_SESSION['user_id']}");
            
            $_SESSION['flash_message'] = 'Listing created successfully!';
            $_SESSION['flash_type'] = 'success';
            header('Location: ' . app_url('admin/listings'));
            exit;
            
        } catch (PDOException $e) {
            if ($db->inTransaction()) {
                $db->rollback();
            }
            error_log("Database error in add listing: " . $e->getMessage());
            $errors[] = 'Error creating listing: ' . (getenv('APP_DEBUG') === 'true' ? $e->getMessage() : 'Please try again');
        } catch (Exception $e) {
            if ($db->inTransaction()) {
                $db->rollback();
            }
            error_log("Error in add listing: " . $e->getMessage());
            $errors[] = 'Error creating listing. Please try again.';
        }
    }
    
    // If errors, show them
    if (!empty($errors)) {
        $_SESSION['flash_message'] = implode('<br>', $errors);
        $_SESSION['flash_type'] = 'danger';
    }
}

try {
    $db = db();
    
    // Get all users for owner selection
    $owners = $db->fetchAll("SELECT id, name, email FROM users ORDER BY name");
    
    // Get all amenities
    $amenities = $db->fetchAll("SELECT id, name FROM amenities ORDER BY name");
    
    // Get all house rules
    $houseRules = $db->fetchAll("SELECT id, name FROM house_rules ORDER BY name");
    
} catch (Exception $e) {
    error_log("Error loading data for add listing: " . $e->getMessage());
    $owners = [];
    $amenities = [];
    $houseRules = [];
}

// Get flash message
$flashMessage = getFlashMessage();
?>

<!-- Page Header -->
<div class="admin-page-header mb-4">
    <div class="d-flex justify-content-between align-items-center">
        <div>
            <h1 class="admin-page-title">Add New PG Listing</h1>
            <p class="admin-page-subtitle text-muted">Create a new property listing</p>
        </div>
        <div>
            <a href="<?= htmlspecialchars(app_url('admin/listings')) ?>" class="btn btn-secondary">
                <i class="bi bi-arrow-left me-2"></i>Back to Listings
            </a>
        </div>
    </div>
</div>

<!-- Flash Message -->
<?php if ($flashMessage): ?>
    <div class="alert alert-<?= htmlspecialchars($flashMessage['type']) ?> alert-dismissible fade show" role="alert">
        <?= htmlspecialchars($flashMessage['message']) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<!-- Add Listing Form -->
<form id="addListingForm" method="POST" enctype="multipart/form-data">
    
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
                    <input type="text" class="form-control" name="title" required 
                           placeholder="e.g., Cozy PG near IIT">
                </div>
                <div class="col-md-6">
                    <label class="form-label">Owner</label>
                    <select class="form-select" name="owner_id">
                        <option value="">Select Owner (Optional)</option>
                        <?php foreach ($owners as $owner): ?>
                            <option value="<?= $owner['id'] ?>">
                                <?= htmlspecialchars($owner['name']) ?> (<?= htmlspecialchars($owner['email']) ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-12">
                    <label class="form-label">Description <span class="text-danger">*</span></label>
                    <textarea class="form-control" name="description" rows="5" required 
                              placeholder="Describe the PG, facilities, nearby areas, etc."></textarea>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Available For <span class="text-danger">*</span></label>
                    <select class="form-select" name="available_for" required>
                        <option value="both">Both</option>
                        <option value="boys">Boys</option>
                        <option value="girls">Girls</option>
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Gender Allowed <span class="text-danger">*</span></label>
                    <select class="form-select" name="gender_allowed" required>
                        <option value="unisex">Unisex</option>
                        <option value="male">Male</option>
                        <option value="female">Female</option>
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Preferred Tenants</label>
                    <select class="form-select" name="preferred_tenants">
                        <option value="anyone">Anyone</option>
                        <option value="students">Students</option>
                        <option value="working professionals">Working Professionals</option>
                        <option value="family">Family</option>
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Security Deposit</label>
                    <input type="text" class="form-control" name="security_deposit_amount" 
                           placeholder="e.g., ₹10,000 or No Deposit" value="No Deposit">
                </div>
                <div class="col-md-4">
                    <label class="form-label">Notice Period (days)</label>
                    <input type="number" class="form-control" name="notice_period" min="0" value="0">
                </div>
                <div class="col-md-4">
                    <label class="form-label">Cover Image</label>
                    <input type="file" class="form-control" name="cover_image" 
                           accept="image/jpeg,image/jpg,image/png,image/gif,image/webp"
                           id="coverImageInput">
                    <small class="text-muted">JPEG, PNG, GIF, or WebP (max 5MB)</small>
                    <div id="coverImagePreview" class="mt-2" style="display: none;">
                        <img id="previewImg" src="" alt="Preview" class="img-thumbnail" style="max-width: 200px; max-height: 200px;">
                    </div>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Status <span class="text-danger">*</span></label>
                    <select class="form-select" name="status" required>
                        <option value="draft">Draft</option>
                        <option value="active">Active</option>
                        <option value="inactive">Inactive</option>
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
                    <textarea class="form-control" name="complete_address" rows="3" required 
                              placeholder="Full address with street, area, etc."></textarea>
                </div>
                <div class="col-md-4">
                    <label class="form-label">City <span class="text-danger">*</span></label>
                    <input type="text" class="form-control" name="city" required 
                           placeholder="e.g., Kolkata">
                </div>
                <div class="col-md-4">
                    <label class="form-label">Pin Code</label>
                    <input type="text" class="form-control" name="pin_code" 
                           placeholder="e.g., 700001" maxlength="10">
                </div>
                <div class="col-md-4">
                    <label class="form-label">Google Maps Link</label>
                    <input type="url" class="form-control" name="google_maps_link" 
                           placeholder="https://maps.google.com/...">
                </div>
                <div class="col-md-6">
                    <label class="form-label">Latitude</label>
                    <input type="number" step="any" class="form-control" name="latitude" 
                           placeholder="e.g., 22.5726">
                </div>
                <div class="col-md-6">
                    <label class="form-label">Longitude</label>
                    <input type="number" step="any" class="form-control" name="longitude" 
                           placeholder="e.g., 88.3639">
                </div>
                <div class="col-12">
                    <label class="form-label">Nearby Landmarks</label>
                    <input type="text" class="form-control" name="nearby_landmarks" 
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
                <!-- Room config will be added dynamically -->
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
                    <select class="form-select" name="electricity_charges">
                        <option value="">Select</option>
                        <option value="included">Included</option>
                        <option value="as per usage">As per usage</option>
                        <option value="as per usage of AC">As per usage of AC</option>
                        <option value="separate meter">Separate meter</option>
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Food Availability</label>
                    <select class="form-select" name="food_availability">
                        <option value="">Select</option>
                        <option value="vegetarian">Vegetarian</option>
                        <option value="non-vegetarian">Non-vegetarian</option>
                        <option value="both">Both</option>
                        <option value="not available">Not available</option>
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Gate Closing Time</label>
                    <input type="time" class="form-control" name="gate_closing_time">
                </div>
                <div class="col-md-4">
                    <label class="form-label">Total Beds</label>
                    <input type="number" class="form-control" name="total_beds" min="0" value="0">
                </div>
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
            <?php if (empty($amenities)): ?>
                <p class="text-muted">No amenities available. <a href="#">Add amenities first</a></p>
            <?php else: ?>
                <div class="row g-2">
                    <?php foreach ($amenities as $amenity): ?>
                        <div class="col-md-3 col-6">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" 
                                       name="amenities[]" 
                                       value="<?= $amenity['id'] ?>" 
                                       id="amenity_<?= $amenity['id'] ?>">
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
            <?php if (empty($houseRules)): ?>
                <p class="text-muted">No house rules available. <a href="#">Add house rules first</a></p>
            <?php else: ?>
                <div class="row g-2">
                    <?php foreach ($houseRules as $rule): ?>
                        <div class="col-md-3 col-6">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" 
                                       name="house_rules[]" 
                                       value="<?= $rule['id'] ?>" 
                                       id="rule_<?= $rule['id'] ?>">
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

    <!-- Form Actions -->
    <div class="d-flex justify-content-between">
        <a href="<?= htmlspecialchars(app_url('admin/listings')) ?>" class="btn btn-secondary">
            <i class="bi bi-x-circle me-2"></i>Cancel
        </a>
        <div>
            <button type="submit" name="save_draft" value="1" class="btn btn-outline-primary">
                <i class="bi bi-save me-2"></i>Save as Draft
            </button>
            <button type="submit" name="save_active" value="1" class="btn btn-success">
                <i class="bi bi-check-circle me-2"></i>Save & Activate
            </button>
        </div>
    </div>
</form>

<script>
let roomConfigCount = 0;

function addRoomConfig() {
    roomConfigCount++;
    const container = document.getElementById('roomConfigs');
    const div = document.createElement('div');
    div.className = 'row g-3 mb-3 border rounded p-3 room-config-item';
    div.innerHTML = `
        <div class="col-md-3">
            <label class="form-label">Room Type <span class="text-danger">*</span></label>
            <select class="form-select" name="room_configs[${roomConfigCount}][room_type]" required>
                <option value="single sharing">Single Sharing</option>
                <option value="double sharing">Double Sharing</option>
                <option value="triple sharing">Triple Sharing</option>
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
            <label class="form-label">Available Rooms <span class="text-danger">*</span></label>
            <input type="number" class="form-control" name="room_configs[${roomConfigCount}][available_rooms]" required min="0" value="1">
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

// Add one room config by default
document.addEventListener('DOMContentLoaded', function() {
    addRoomConfig();
    
    // Cover image preview
    const coverImageInput = document.getElementById('coverImageInput');
    const previewDiv = document.getElementById('coverImagePreview');
    const previewImg = document.getElementById('previewImg');
    
    if (coverImageInput && previewDiv && previewImg) {
        coverImageInput.addEventListener('change', function(e) {
            const file = e.target.files[0];
            if (file) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    previewImg.src = e.target.result;
                    previewDiv.style.display = 'block';
                };
                reader.readAsDataURL(file);
            } else {
                previewDiv.style.display = 'none';
            }
        });
    }
});
</script>

<?php require __DIR__ . '/../app/includes/admin_footer.php'; ?>

