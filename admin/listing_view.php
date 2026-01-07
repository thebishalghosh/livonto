<?php
/**
 * Admin View Listing Page
 * Display detailed information about a specific listing with tabbed navigation
 */

// Start session and load config/functions
if (session_status() === PHP_SESSION_NONE) session_start();
require __DIR__ . '/../app/config.php';
require __DIR__ . '/../app/functions.php';

// Ensure admin is logged in
if (empty($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
    header('Location: ' . app_url('admin/login'));
    exit;
}

// Get listing ID and active tab
$listingId = intval($_GET['id'] ?? 0);
$activeTab = $_GET['tab'] ?? 'photos';

if (!$listingId) {
    $_SESSION['flash_message'] = 'Invalid listing ID';
    $_SESSION['flash_type'] = 'danger';
    header('Location: ' . app_url('admin/listings'));
    exit;
}

try {
    $db = db();
    
    // Get main listing data
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
    
    // Get room configurations
    $roomConfigs = $db->fetchAll(
        "SELECT * FROM room_configurations WHERE listing_id = ? ORDER BY rent_per_month ASC",
        [$listingId]
    );
    
    // Get amenities
    $amenities = $db->fetchAll(
        "SELECT a.id, a.name 
         FROM amenities a
         INNER JOIN listing_amenities la ON a.id = la.amenity_id
         WHERE la.listing_id = ?
         ORDER BY a.name",
        [$listingId]
    );
    
    // Get house rules
    $houseRules = $db->fetchAll(
        "SELECT hr.id, hr.name 
         FROM house_rules hr
         INNER JOIN listing_rules lr ON hr.id = lr.rule_id
         WHERE lr.listing_id = ?
         ORDER BY hr.name",
        [$listingId]
    );
    
    // Get statistics
    $stats = [
        'bookings_count' => (int)$db->fetchValue("SELECT COUNT(*) FROM bookings WHERE listing_id = ?", [$listingId]) ?: 0,
        'confirmed_bookings' => (int)$db->fetchValue("SELECT COUNT(*) FROM bookings WHERE listing_id = ? AND status = 'confirmed'", [$listingId]) ?: 0,
        'visits_count' => (int)$db->fetchValue("SELECT COUNT(*) FROM visit_bookings WHERE listing_id = ?", [$listingId]) ?: 0,
        'reviews_count' => (int)$db->fetchValue("SELECT COUNT(*) FROM reviews WHERE listing_id = ?", [$listingId]) ?: 0,
        'avg_rating' => (float)$db->fetchValue("SELECT AVG(rating) FROM reviews WHERE listing_id = ?", [$listingId]) ?: 0,
    ];
    
    // Get recent bookings
    $recentBookings = $db->fetchAll(
        "SELECT b.*, u.name as user_name, u.email as user_email
         FROM bookings b
         LEFT JOIN users u ON b.user_id = u.id
         WHERE b.listing_id = ?
         ORDER BY b.created_at DESC
         LIMIT 10",
        [$listingId]
    );
    
    // Get recent reviews
    $recentReviews = $db->fetchAll(
        "SELECT r.*, u.name as user_name
         FROM reviews r
         LEFT JOIN users u ON r.user_id = u.id
         WHERE r.listing_id = ?
         ORDER BY r.created_at DESC
         LIMIT 10",
        [$listingId]
    );
    
    // Parse nearby landmarks if JSON
    $nearbyLandmarks = [];
    if (!empty($listing['nearby_landmarks'])) {
        $landmarks = json_decode($listing['nearby_landmarks'], true);
        if (is_array($landmarks)) {
            $nearbyLandmarks = $landmarks;
        }
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
    $coverImageUrl = null;
    $allImageUrls = [];
    
    // Process images from listing_images table
    foreach ($listingImages as $img) {
        $imagePath = trim($img['image_path']);
        if (empty($imagePath)) {
            continue; // Skip empty image paths
        }
        
        // Build full URL
        if (strpos($imagePath, 'http') === 0 || strpos($imagePath, '//') === 0) {
            $fullUrl = $imagePath;
            $localPath = null; // Can't verify external URLs
        } else {
            // Use app_url() for consistent path handling
            $fullUrl = app_url($imagePath);
            // Check if file exists locally (need original path for file check)
            $imagePathForFile = ltrim($imagePath, '/');
            $localPath = __DIR__ . '/../' . $imagePathForFile;
        }
        
        // Only add image if file exists (or is external URL)
        // For local files, verify they actually exist before adding
        $fileExists = true;
        if ($localPath !== null) {
            $fileExists = file_exists($localPath);
            if (!$fileExists) {
                // Log missing file and skip this image
                error_log("Image file not found: {$localPath} for listing_id: {$listingId}, image_id: {$img['id']}");
                continue; // Skip this image entirely - don't add to array
            }
        }
        
        // Only add if file exists (or is external URL)
        if ($localPath === null || $fileExists) {
            $allImageUrls[] = [
                'url' => $fullUrl,
                'path' => $imagePath,
                'is_cover' => (bool)$img['is_cover'],
                'order' => (int)$img['image_order'],
                'id' => (int)$img['id'],
                'exists' => true // We only add if it exists
            ];
            
            // Set cover image (first cover image found)
            if ($img['is_cover'] && !$coverImageUrl) {
                $coverImageUrl = $fullUrl;
            }
        }
    }
    
    // Fallback to cover_image from listings table if no images in listing_images table
    if (empty($allImageUrls) && !empty($listing['cover_image'])) {
        $imagePath = trim($listing['cover_image']);
        if (!empty($imagePath)) {
            if (strpos($imagePath, 'http') === 0 || strpos($imagePath, '//') === 0) {
                $coverImageUrl = $imagePath;
            } else {
                // Use app_url() for consistent path handling
                $coverImageUrl = app_url($imagePath);
                $imagePath = ltrim($imagePath, '/'); // Keep for array below
            }
            $allImageUrls[] = [
                'url' => $coverImageUrl,
                'path' => $imagePath,
                'is_cover' => true,
                'order' => 0,
                'id' => 0 // Fallback ID
            ];
        }
    }
    
    // If still no cover image, use first image
    if (!$coverImageUrl && !empty($allImageUrls)) {
        $coverImageUrl = $allImageUrls[0]['url'];
        $allImageUrls[0]['is_cover'] = true;
        if (!isset($allImageUrls[0]['id'])) {
            $allImageUrls[0]['id'] = 0;
        }
    }
    
    // Calculate bed-based totals using unified calculation
    $totalRooms = 0;
    $totalBeds = 0;
    $totalAvailableBeds = 0;
    foreach ($roomConfigs as &$config) {
        $totalRooms += (int)$config['total_rooms'];
        $bedsPerRoom = getBedsPerRoom($config['room_type']);
        $configBeds = calculateTotalBeds($config['total_rooms'], $config['room_type']);
        $totalBeds += $configBeds;
        
        // Count actual booked beds for display
        $bookedBeds = (int)$db->fetchValue(
            "SELECT COUNT(*) FROM bookings 
             WHERE room_config_id = ? AND status IN ('pending', 'confirmed')",
            [$config['id']]
        );
        
        // Use unified calculation: total_beds - booked_beds (ensures consistency)
        $availableBeds = calculateAvailableBeds($config['total_rooms'], $config['room_type'], $bookedBeds);
        $totalAvailableBeds += $availableBeds;
        
        $config['beds_per_room'] = $bedsPerRoom;
        $config['total_beds'] = $configBeds;
        $config['available_beds'] = $availableBeds;
        $config['booked_beds'] = $bookedBeds;
    }
    unset($config);
    
} catch (Exception $e) {
    error_log("Error loading listing view: " . $e->getMessage());
    $_SESSION['flash_message'] = 'Error loading listing details';
    $_SESSION['flash_type'] = 'danger';
    header('Location: ' . app_url('admin/listings'));
    exit;
}

$pageTitle = "View Listing: " . htmlspecialchars($listing['title']);
require __DIR__ . '/../app/includes/admin_header.php';

$flashMessage = getFlashMessage();
$baseUrl = app_url('');
?>

<!-- Page Header -->
<div class="admin-page-header mb-3">
    <div class="d-flex justify-content-between align-items-center">
        <div>
            <h1 class="admin-page-title">View Listing</h1>
            <p class="admin-page-subtitle text-muted"><?= htmlspecialchars($listing['title']) ?></p>
        </div>
        <div class="d-flex gap-2">
            <a href="<?= htmlspecialchars(app_url('admin/listings')) ?>" class="btn btn-secondary">
                <i class="bi bi-arrow-left me-2"></i>Back
            </a>
            <a href="<?= htmlspecialchars(app_url('admin/listings/edit?id=' . $listingId)) ?>" class="btn btn-primary">
                <i class="bi bi-pencil me-2"></i>Edit Listing
            </a>
        </div>
    </div>
</div>

<!-- Flash Message -->
<?php if ($flashMessage): ?>
    <div class="alert alert-<?= htmlspecialchars($flashMessage['type']) ?> alert-dismissible fade show mb-3" role="alert">
        <?= htmlspecialchars($flashMessage['message']) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<!-- Main Content with Sidebar Navigation -->
<div class="row g-3 align-items-stretch">
    <!-- Left Sidebar Navigation -->
    <div class="col-lg-3 listing-view-sidebar">
        <div class="admin-card h-100">
            <div class="admin-card-body p-0 d-flex flex-column">
                <nav class="nav flex-column flex-grow-1">
                    <a class="nav-link <?= $activeTab === 'photos' ? 'active' : '' ?>" 
                       href="<?= htmlspecialchars(app_url('admin/listings/view?id=' . $listingId . '&tab=photos')) ?>">
                        <i class="bi bi-image me-2"></i>Photos
                    </a>
                    <a class="nav-link <?= $activeTab === 'details' ? 'active' : '' ?>" 
                       href="<?= htmlspecialchars(app_url('admin/listings/view?id=' . $listingId . '&tab=details')) ?>">
                        <i class="bi bi-info-circle me-2"></i>Details
                    </a>
                    <a class="nav-link <?= $activeTab === 'location' ? 'active' : '' ?>" 
                       href="<?= htmlspecialchars(app_url('admin/listings/view?id=' . $listingId . '&tab=location')) ?>">
                        <i class="bi bi-geo-alt me-2"></i>Location
                    </a>
                    <a class="nav-link <?= $activeTab === 'amenities' ? 'active' : '' ?>" 
                       href="<?= htmlspecialchars(app_url('admin/listings/view?id=' . $listingId . '&tab=amenities')) ?>">
                        <i class="bi bi-star me-2"></i>Amenities
                    </a>
                    <a class="nav-link <?= $activeTab === 'pricing' ? 'active' : '' ?>" 
                       href="<?= htmlspecialchars(app_url('admin/listings/view?id=' . $listingId . '&tab=pricing')) ?>">
                        <i class="bi bi-currency-rupee me-2"></i>Pricing
                    </a>
                    <a class="nav-link <?= $activeTab === 'bookings' ? 'active' : '' ?>" 
                       href="<?= htmlspecialchars(app_url('admin/listings/view?id=' . $listingId . '&tab=bookings')) ?>">
                        <i class="bi bi-calendar-check me-2"></i>Bookings
                    </a>
                    <a class="nav-link <?= $activeTab === 'reviews' ? 'active' : '' ?>" 
                       href="<?= htmlspecialchars(app_url('admin/listings/view?id=' . $listingId . '&tab=reviews')) ?>">
                        <i class="bi bi-chat-dots me-2"></i>Reviews
                    </a>
                    <a class="nav-link <?= $activeTab === 'statistics' ? 'active' : '' ?>" 
                       href="<?= htmlspecialchars(app_url('admin/listings/view?id=' . $listingId . '&tab=statistics')) ?>">
                        <i class="bi bi-graph-up me-2"></i>Statistics
                    </a>
                </nav>
            </div>
        </div>
    </div>

    <!-- Main Content Area -->
    <div class="col-lg-9">
        <?php if ($activeTab === 'photos'): ?>
            <!-- Photos Tab -->
            <div class="admin-card">
                <div class="admin-card-header">
                    <h5 class="admin-card-title">
                        <i class="bi bi-image me-2"></i>Photos
                        <?php if (!empty($allImageUrls)): ?>
                            <span class="badge bg-secondary ms-2"><?= count($allImageUrls) ?> image<?= count($allImageUrls) !== 1 ? 's' : '' ?></span>
                        <?php endif; ?>
                    </h5>
                </div>
                <div class="admin-card-body">
                    <?php if (!empty($allImageUrls)): ?>
                        <!-- All Images Gallery -->
                        <div class="row g-3">
                            <?php 
                            $coverShown = false;
                            foreach ($allImageUrls as $index => $img): 
                                // Show cover image larger only once, at the top
                                if ($img['is_cover'] && !$coverShown): 
                                    $coverShown = true;
                                    $cacheBuster = '?v=' . $img['id'] . '&t=' . time();
                                    $imgUrl = htmlspecialchars($img['url']) . (strpos($img['url'], '?') !== false ? '&' : '?') . 'v=' . $img['id'] . '&t=' . time();
                            ?>
                                <div class="col-md-6 col-sm-12">
                                    <div class="position-relative">
                                        <div class="position-relative" style="border-radius: 8px; overflow: hidden; box-shadow: 0 2px 8px rgba(0,0,0,0.1);">
                                            <img src="<?= $imgUrl ?>" 
                                                 alt="Cover Image - <?= htmlspecialchars($img['path']) ?>" 
                                                 class="img-fluid w-100" 
                                                 style="height: 400px; object-fit: cover; cursor: pointer; transition: transform 0.2s; display: block;"
                                                 onmouseover="this.style.transform='scale(1.02)'"
                                                 onmouseout="this.style.transform='scale(1)'"
                                                 onclick="openImageModal('<?= htmlspecialchars($img['url']) ?>')"
                                                 onerror="this.style.display='none'; this.parentElement.style.display='none';">
                                            <span class="badge bg-primary position-absolute" 
                                                  style="top: 12px; left: 12px; z-index: 10; box-shadow: 0 2px 4px rgba(0,0,0,0.2);">
                                                <i class="bi bi-star-fill me-1"></i>Cover
                                            </span>
                                        </div>
                                        <div class="text-center mt-2">
                                            <small class="text-muted"><?= htmlspecialchars(basename($img['path'])) ?></small>
                                        </div>
                                    </div>
                                </div>
                            <?php 
                                elseif (!$img['is_cover']): 
                                    // Show non-cover images as thumbnails
                                    $imgUrl = htmlspecialchars($img['url']) . (strpos($img['url'], '?') !== false ? '&' : '?') . 'v=' . $img['id'] . '&t=' . time();
                            ?>
                                <div class="col-md-3 col-sm-4 col-6">
                                    <div class="position-relative">
                                        <div class="position-relative" style="border-radius: 8px; overflow: hidden; box-shadow: 0 2px 8px rgba(0,0,0,0.1);">
                                            <img src="<?= $imgUrl ?>" 
                                                 alt="Image <?= $index + 1 ?> - <?= htmlspecialchars($img['path']) ?>" 
                                                 class="img-fluid w-100" 
                                                 style="height: 200px; object-fit: cover; cursor: pointer; transition: transform 0.2s; display: block;"
                                                 onmouseover="this.style.transform='scale(1.05)'"
                                                 onmouseout="this.style.transform='scale(1)'"
                                                 onclick="openImageModal('<?= htmlspecialchars($img['url']) ?>')"
                                                 onerror="this.style.display='none'; this.parentElement.parentElement.style.display='none';">
                                        </div>
                                        <div class="text-center mt-1">
                                            <small class="text-muted d-block">Image <?= $index + 1 ?></small>
                                            <small class="text-muted" style="font-size: 0.7rem; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; display: block;" title="<?= htmlspecialchars($img['path']) ?>">
                                                <?= htmlspecialchars(basename($img['path'])) ?>
                                            </small>
                                        </div>
                                    </div>
                                </div>
                            <?php 
                                endif;
                            endforeach; 
                            ?>
                        </div>
                    <?php else: ?>
                        <div class="text-center py-5">
                            <i class="bi bi-image fs-1 text-muted d-block mb-3"></i>
                            <p class="text-muted">No images uploaded</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Image Modal -->
            <div class="modal fade" id="imageModal" tabindex="-1" aria-hidden="true">
                <div class="modal-dialog modal-dialog-centered modal-lg">
                    <div class="modal-content bg-transparent border-0">
                        <button type="button" class="btn-close btn-close-white position-absolute top-0 end-0 m-3" 
                                data-bs-dismiss="modal" aria-label="Close" style="z-index: 1055;"></button>
                        <img id="modalImage" src="" alt="Full size" class="img-fluid rounded">
                    </div>
                </div>
            </div>
            
            <script>
            function openImageModal(imageUrl) {
                const modal = new bootstrap.Modal(document.getElementById('imageModal'));
                document.getElementById('modalImage').src = imageUrl;
                modal.show();
            }
            </script>

        <?php elseif ($activeTab === 'details'): ?>
            <!-- Details Tab -->
            <div class="admin-card">
                <div class="admin-card-header">
                    <h5 class="admin-card-title">
                        <i class="bi bi-info-circle me-2"></i>Listing Details
                    </h5>
                    <a href="<?= htmlspecialchars(app_url('admin/listings/edit?id=' . $listingId)) ?>" class="btn btn-sm btn-success">
                        <i class="bi bi-pencil me-1"></i>Edit
                    </a>
                </div>
                <div class="admin-card-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label text-muted small mb-1">Listing ID</label>
                            <div class="fw-semibold">#<?= htmlspecialchars($listingId) ?></div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label text-muted small mb-1">Status</label>
                            <div>
                                <span class="badge bg-<?= $listing['status'] === 'active' ? 'success' : ($listing['status'] === 'draft' ? 'warning' : 'danger') ?>">
                                    <?= ucfirst($listing['status']) ?>
                                </span>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label text-muted small mb-1">Title</label>
                            <div class="fw-semibold"><?= htmlspecialchars($listing['title'] ?: 'N/A') ?></div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label text-muted small mb-1">Owner Name</label>
                            <div><?= htmlspecialchars($listing['owner_name'] ?: 'N/A') ?></div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label text-muted small mb-1">Owner Email</label>
                            <div>
                                <?php if (!empty($listing['owner_email'])): ?>
                                    <a href="mailto:<?= htmlspecialchars($listing['owner_email']) ?>" class="text-decoration-none">
                                        <?= htmlspecialchars($listing['owner_email']) ?>
                                    </a>
                                    <?php if (!empty($listing['owner_password_hash'])): ?>
                                        <span class="badge bg-success ms-2" title="Owner can login">
                                            <i class="bi bi-check-circle"></i> Active
                                        </span>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <span class="text-muted">N/A</span>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="col-12">
                            <label class="form-label text-muted small mb-1">Description</label>
                            <div><?= nl2br(htmlspecialchars($listing['description'] ?: 'N/A')) ?></div>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label text-muted small mb-1">Available For</label>
                            <div><?= ucfirst($listing['available_for'] ?: 'N/A') ?></div>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label text-muted small mb-1">Gender Allowed</label>
                            <div><?= ucfirst($listing['gender_allowed'] ?: 'N/A') ?></div>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label text-muted small mb-1">Preferred Tenants</label>
                            <div><?= ucfirst($listing['preferred_tenants'] ?: 'N/A') ?></div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label text-muted small mb-1">Security Deposit</label>
                            <div><?= htmlspecialchars($listing['security_deposit_amount'] ?: 'N/A') ?></div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label text-muted small mb-1">Notice Period</label>
                            <div><?= $listing['notice_period'] ? $listing['notice_period'] . ' days' : 'N/A' ?></div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label text-muted small mb-1">Total Rooms / Beds</label>
                            <div><?= $totalRooms > 0 ? ($totalRooms . ' rooms / ' . $totalBeds . ' beds') : 'N/A' ?></div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label text-muted small mb-1">Available Beds</label>
                            <div><?= $totalAvailableBeds > 0 ? $totalAvailableBeds : 'N/A' ?></div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label text-muted small mb-1">Total Beds</label>
                            <div><?= $listing['total_beds'] ? $listing['total_beds'] : 'N/A' ?></div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label text-muted small mb-1">Created At</label>
                            <div><?= formatDate($listing['created_at'], 'd M Y, h:i A') ?></div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Property Details -->
            <div class="admin-card mt-2">
                <div class="admin-card-header">
                    <h5 class="admin-card-title">
                        <i class="bi bi-list-check me-2"></i>Property Details
                    </h5>
                    <a href="<?= htmlspecialchars(app_url('admin/listings/edit?id=' . $listingId)) ?>" class="btn btn-sm btn-success">
                        <i class="bi bi-pencil me-1"></i>Edit
                    </a>
                </div>
                <div class="admin-card-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label text-muted small mb-1">Electricity Charges</label>
                            <div><?= $listing['electricity_charges'] ? ucfirst(htmlspecialchars($listing['electricity_charges'])) : 'N/A' ?></div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label text-muted small mb-1">Food Availability</label>
                            <div><?= $listing['food_availability'] ? ucfirst(htmlspecialchars($listing['food_availability'])) : 'N/A' ?></div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label text-muted small mb-1">Gate Closing Time</label>
                            <div><?= $listing['gate_closing_time'] ? htmlspecialchars($listing['gate_closing_time']) : 'N/A' ?></div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label text-muted small mb-1">Last Updated</label>
                            <div><?= formatDate($listing['updated_at'], 'd M Y, h:i A') ?></div>
                        </div>
                    </div>
                </div>
            </div>

        <?php elseif ($activeTab === 'location'): ?>
            <!-- Location Tab -->
            <div class="admin-card">
                <div class="admin-card-header">
                    <h5 class="admin-card-title">
                        <i class="bi bi-geo-alt me-2"></i>Location Details
                    </h5>
                    <a href="<?= htmlspecialchars(app_url('admin/listings/edit?id=' . $listingId)) ?>" class="btn btn-sm btn-success">
                        <i class="bi bi-pencil me-1"></i>Edit
                    </a>
                </div>
                <div class="admin-card-body">
                    <div class="row g-3">
                        <div class="col-12">
                            <label class="form-label text-muted small mb-1">Complete Address</label>
                            <div><?= nl2br(htmlspecialchars($listing['complete_address'] ?: 'N/A')) ?></div>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label text-muted small mb-1">City</label>
                            <div><?= htmlspecialchars($listing['city'] ?: 'N/A') ?></div>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label text-muted small mb-1">Pin Code</label>
                            <div><?= htmlspecialchars($listing['pin_code'] ?: 'N/A') ?></div>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label text-muted small mb-1">Coordinates</label>
                            <div>
                                <?php if ($listing['latitude'] && $listing['longitude']): ?>
                                    <?= htmlspecialchars($listing['latitude']) ?>, <?= htmlspecialchars($listing['longitude']) ?>
                                <?php else: ?>
                                    N/A
                                <?php endif; ?>
                            </div>
                        </div>
                        <?php if (!empty($listing['google_maps_link'])): ?>
                            <div class="col-12">
                                <label class="form-label text-muted small mb-1">Google Maps Link</label>
                                <div>
                                    <a href="<?= htmlspecialchars($listing['google_maps_link']) ?>" target="_blank" class="btn btn-sm btn-outline-primary">
                                        <i class="bi bi-map me-1"></i>View on Google Maps
                                    </a>
                                </div>
                            </div>
                        <?php endif; ?>
                        <?php if (!empty($nearbyLandmarks)): ?>
                            <div class="col-12">
                                <label class="form-label text-muted small mb-1">Nearby Landmarks</label>
                                <div>
                                    <?php foreach ($nearbyLandmarks as $landmark): ?>
                                        <span class="badge bg-secondary me-1 mb-1"><?= htmlspecialchars($landmark) ?></span>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

        <?php elseif ($activeTab === 'amenities'): ?>
            <!-- Amenities Tab -->
            <div class="admin-card">
                <div class="admin-card-header">
                    <h5 class="admin-card-title">
                        <i class="bi bi-star me-2"></i>Amenities
                    </h5>
                    <a href="<?= htmlspecialchars(app_url('admin/listings/edit?id=' . $listingId)) ?>" class="btn btn-sm btn-success">
                        <i class="bi bi-plus me-1"></i>Add Amenity
                    </a>
                </div>
                <div class="admin-card-body">
                    <?php if (empty($amenities)): ?>
                        <div class="text-center py-4">
                            <i class="bi bi-star fs-1 text-muted d-block mb-2"></i>
                            <p class="text-muted mb-0">No amenities added</p>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Amenity Name</th>
                                        <th>Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($amenities as $amenity): ?>
                                        <tr>
                                            <td><?= htmlspecialchars($amenity['name']) ?></td>
                                            <td>
                                                <a href="<?= htmlspecialchars(app_url('admin/listings/edit?id=' . $listingId)) ?>" class="btn btn-sm btn-outline-primary">
                                                    <i class="bi bi-pencil"></i> Edit
                                                </a>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- House Rules -->
            <div class="admin-card mt-3">
                <div class="admin-card-header">
                    <h5 class="admin-card-title">
                        <i class="bi bi-shield-check me-2"></i>House Rules
                    </h5>
                    <a href="<?= htmlspecialchars(app_url('admin/listings/edit?id=' . $listingId)) ?>" class="btn btn-sm btn-success">
                        <i class="bi bi-plus me-1"></i>Add Rule
                    </a>
                </div>
                <div class="admin-card-body">
                    <?php if (empty($houseRules)): ?>
                        <div class="text-center py-4">
                            <i class="bi bi-shield-check fs-1 text-muted d-block mb-2"></i>
                            <p class="text-muted mb-0">No house rules added</p>
                        </div>
                    <?php else: ?>
                        <ul class="list-unstyled mb-0">
                            <?php foreach ($houseRules as $rule): ?>
                                <li class="mb-2 pb-2 border-bottom">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <span><i class="bi bi-check-circle text-success me-2"></i><?= htmlspecialchars($rule['name']) ?></span>
                                        <a href="<?= htmlspecialchars(app_url('admin/listings/edit?id=' . $listingId)) ?>" class="btn btn-sm btn-outline-primary">
                                            <i class="bi bi-pencil"></i> Edit
                                        </a>
                                    </div>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                </div>
            </div>

        <?php elseif ($activeTab === 'pricing'): ?>
            <!-- Pricing Tab -->
            <div class="admin-card">
                <div class="admin-card-header">
                    <h5 class="admin-card-title">
                        <i class="bi bi-currency-rupee me-2"></i>Pricing Details
                    </h5>
                    <a href="<?= htmlspecialchars(app_url('admin/listings/edit?id=' . $listingId)) ?>" class="btn btn-sm btn-success">
                        <i class="bi bi-pencil me-1"></i>Edit
                    </a>
                </div>
                <div class="admin-card-body">
                    <div class="row g-3 mb-4">
                        <div class="col-md-6">
                            <label class="form-label text-muted small mb-1">Security Deposit</label>
                            <div class="fw-semibold"><?= htmlspecialchars($listing['security_deposit_amount'] ?: 'N/A') ?></div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label text-muted small mb-1">Notice Period</label>
                            <div><?= $listing['notice_period'] ? $listing['notice_period'] . ' days' : 'N/A' ?></div>
                        </div>
                    </div>
                    
                    <?php if (!empty($roomConfigs)): ?>
                        <h6 class="mb-3">Room Configurations</h6>
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Room Type</th>
                                        <th>Rent/Month</th>
                                        <th>Total</th>
                                        <th>Available Beds</th>
                                        <th>Occupied</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($roomConfigs as $config): ?>
                                        <tr>
                                            <td>
                                                <?= ucfirst(htmlspecialchars($config['room_type'])) ?>
                                                <br><small class="text-muted"><?= $config['beds_per_room'] ?> bed<?= $config['beds_per_room'] > 1 ? 's' : '' ?> per room</small>
                                            </td>
                                            <td class="fw-semibold">₹<?= number_format($config['rent_per_month'], 2) ?></td>
                                            <td>
                                                <?= $config['total_rooms'] ?> room<?= $config['total_rooms'] != 1 ? 's' : '' ?>
                                                <br><small class="text-muted"><?= $config['total_beds'] ?> beds</small>
                                            </td>
                                            <td>
                                                <?= $config['available_beds'] ?> bed<?= $config['available_beds'] != 1 ? 's' : '' ?>
                                            </td>
                                            <td>
                                                <?php 
                                                $bookedBeds = (int)($config['booked_beds'] ?? 0);
                                                $totalBeds = (int)($config['total_beds'] ?? 0);
                                                $percentage = $totalBeds > 0 ? ($bookedBeds / $totalBeds) * 100 : 0;
                                                ?>
                                                <span class="badge bg-<?= $percentage >= 80 ? 'danger' : ($percentage >= 50 ? 'warning' : 'success') ?>">
                                                    <?= $bookedBeds ?>/<?= $totalBeds ?> beds (<?= number_format($percentage, 1) ?>%)
                                                </span>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <div class="text-center py-4">
                            <i class="bi bi-currency-rupee fs-1 text-muted d-block mb-2"></i>
                            <p class="text-muted mb-0">No room configurations added</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

        <?php elseif ($activeTab === 'bookings'): ?>
            <!-- Bookings Tab -->
            <div class="admin-card">
                <div class="admin-card-header">
                    <h5 class="admin-card-title">
                        <i class="bi bi-calendar-check me-2"></i>Bookings
                    </h5>
                </div>
                <div class="admin-card-body">
                    <?php if (empty($recentBookings)): ?>
                        <div class="text-center py-4">
                            <i class="bi bi-calendar-x fs-1 text-muted d-block mb-2"></i>
                            <p class="text-muted mb-0">No bookings found</p>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>User</th>
                                        <th>Start Date</th>
                                        <th>End Date</th>
                                        <th>Amount</th>
                                        <th>Status</th>
                                        <th>Date</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($recentBookings as $booking): ?>
                                        <tr>
                                            <td>
                                                <div><?= htmlspecialchars($booking['user_name'] ?: 'Unknown') ?></div>
                                                <small class="text-muted"><?= htmlspecialchars($booking['user_email'] ?: '') ?></small>
                                            </td>
                                            <?php
                                            $startDate = $booking['booking_start_date'] ?? null;
                                            $duration = intval($booking['duration_months'] ?? 1);
                                            $endDate = null;
                                            if ($startDate) {
                                                $start = new DateTime($startDate);
                                                $end = clone $start;
                                                $end->modify('+' . $duration . ' month');
                                                $endDate = $end->format('Y-m-d');
                                            }
                                            ?>
                                            <td><?= $startDate ? formatDate($startDate, 'd M Y') : 'N/A' ?></td>
                                            <td><?= $endDate ? formatDate($endDate, 'd M Y') : 'N/A' ?></td>
                                            <td class="fw-semibold">₹<?= number_format($booking['total_amount'], 2) ?></td>
                                            <td>
                                                <span class="badge bg-<?= $booking['status'] === 'confirmed' ? 'success' : ($booking['status'] === 'pending' ? 'warning' : 'secondary') ?>">
                                                    <?= ucfirst($booking['status']) ?>
                                                </span>
                                            </td>
                                            <td><?= formatDate($booking['created_at'], 'd M Y') ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

        <?php elseif ($activeTab === 'reviews'): ?>
            <!-- Reviews Tab -->
            <div class="admin-card">
                <div class="admin-card-header">
                    <h5 class="admin-card-title">
                        <i class="bi bi-chat-dots me-2"></i>Reviews
                    </h5>
                </div>
                <div class="admin-card-body">
                    <?php if (empty($recentReviews)): ?>
                        <div class="text-center py-4">
                            <i class="bi bi-chat-dots fs-1 text-muted d-block mb-2"></i>
                            <p class="text-muted mb-0">No reviews yet</p>
                        </div>
                    <?php else: ?>
                        <div class="list-group">
                            <?php foreach ($recentReviews as $review): ?>
                                <div class="list-group-item">
                                    <div class="d-flex justify-content-between align-items-start mb-2">
                                        <div>
                                            <div class="fw-semibold"><?= htmlspecialchars($review['user_name'] ?: 'Anonymous') ?></div>
                                            <small class="text-muted"><?= formatDate($review['created_at'], 'd M Y') ?></small>
                                        </div>
                                        <span class="text-warning">
                                            <?php for ($i = 1; $i <= 5; $i++): ?>
                                                <i class="bi bi-star<?= $i <= $review['rating'] ? '-fill' : '' ?>"></i>
                                            <?php endfor; ?>
                                        </span>
                                    </div>
                                    <?php if (!empty($review['comment'])): ?>
                                        <p class="mb-0"><?= nl2br(htmlspecialchars($review['comment'])) ?></p>
                                    <?php endif; ?>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

        <?php elseif ($activeTab === 'statistics'): ?>
            <!-- Statistics Tab -->
            <div class="row g-3">
                <div class="col-md-3">
                    <div class="admin-card">
                        <div class="admin-card-body text-center">
                            <div class="fs-3 fw-bold text-primary"><?= $stats['bookings_count'] ?></div>
                            <div class="small text-muted">Total Bookings</div>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="admin-card">
                        <div class="admin-card-body text-center">
                            <div class="fs-3 fw-bold text-success"><?= $stats['confirmed_bookings'] ?></div>
                            <div class="small text-muted">Confirmed</div>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="admin-card">
                        <div class="admin-card-body text-center">
                            <div class="fs-3 fw-bold text-info"><?= $stats['visits_count'] ?></div>
                            <div class="small text-muted">Visits</div>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="admin-card">
                        <div class="admin-card-body text-center">
                            <div class="fs-3 fw-bold text-warning"><?= $stats['reviews_count'] ?></div>
                            <div class="small text-muted">Reviews</div>
                        </div>
                    </div>
                </div>
                <?php if ($stats['avg_rating'] > 0): ?>
                    <div class="col-12">
                        <div class="admin-card">
                            <div class="admin-card-body text-center">
                                <div class="fs-4 fw-bold text-warning mb-2">
                                    <?= number_format($stats['avg_rating'], 1) ?>
                                    <span class="text-warning ms-2">
                                        <?php for ($i = 1; $i <= 5; $i++): ?>
                                            <i class="bi bi-star<?= $i <= round($stats['avg_rating']) ? '-fill' : '' ?>"></i>
                                        <?php endfor; ?>
                                    </span>
                                </div>
                                <div class="small text-muted">Average Rating</div>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<style>
/* Custom styles for listing view sidebar */
.listing-view-sidebar {
    display: flex;
    flex-direction: column;
}

.listing-view-sidebar .admin-card {
    display: flex;
    flex-direction: column;
    height: auto;
    min-height: auto;
}

.listing-view-sidebar .admin-card-body {
    display: flex;
    flex-direction: column;
    padding: 0 !important;
    flex: 0 1 auto;
}

.listing-view-sidebar .nav {
    padding: 0.5rem 0;
    flex: 0 1 auto;
}

.listing-view-sidebar .nav-link {
    padding: 0.875rem 1.25rem;
    color: var(--admin-text);
    border-left: 3px solid transparent;
    transition: all 0.2s ease;
    border-radius: 0;
    display: flex;
    align-items: center;
    text-decoration: none;
    margin: 0.125rem 0;
}

.listing-view-sidebar .nav-link:hover {
    background: var(--admin-bg);
    border-left-color: var(--admin-primary);
    color: var(--admin-primary);
}

.listing-view-sidebar .nav-link.active {
    background: var(--admin-accent);
    border-left-color: var(--admin-primary);
    color: var(--admin-primary);
    font-weight: 600;
}

.listing-view-sidebar .nav-link i {
    width: 20px;
    text-align: center;
}

/* Main content area styles - reduce padding and spacing */
.admin-listing-view .admin-card {
    margin-bottom: 1rem;
}

.admin-listing-view .admin-card-body {
    padding: 1rem !important;
}

.admin-listing-view .admin-card-header {
    padding: 1rem 1.5rem !important;
}

.admin-listing-view .form-label {
    margin-bottom: 0.25rem !important;
}

.admin-listing-view .mb-2 {
    margin-bottom: 0.5rem !important;
}

.admin-listing-view .row.g-3 {
    margin-bottom: 0;
}

.admin-listing-view .row.g-3 > * {
    margin-bottom: 0.5rem;
}

/* Remove excessive spacing in details tab */
.admin-listing-view .admin-card-body .row:last-child {
    margin-bottom: 0;
}

.admin-listing-view .admin-card-body .row:last-child > *:last-child {
    margin-bottom: 0;
}
</style>

<?php require __DIR__ . '/../app/includes/admin_footer.php'; ?>
