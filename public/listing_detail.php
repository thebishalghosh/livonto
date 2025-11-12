<?php
/**
 * Listing Detail Page
 * Shows complete information about a specific PG listing
 */

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require __DIR__ . '/../app/config.php';
require __DIR__ . '/../app/functions.php';

// Get listing ID from URL
$listingId = intval($_GET['id'] ?? 0);

if (!$listingId) {
    header('Location: ' . app_url('listings'));
    exit;
}

try {
    $db = db();
    
    // Get main listing data (only active listings)
    $listing = $db->fetchOne(
        "SELECT l.*, 
                loc.complete_address, loc.city, loc.pin_code, loc.google_maps_link, 
                loc.latitude, loc.longitude, loc.nearby_landmarks,
                add_info.electricity_charges, add_info.food_availability, 
                add_info.gate_closing_time, add_info.total_beds,
                (SELECT AVG(rating) FROM reviews WHERE listing_id = l.id) as avg_rating,
                (SELECT COUNT(*) FROM reviews WHERE listing_id = l.id) as reviews_count
         FROM listings l
         LEFT JOIN listing_locations loc ON l.id = loc.listing_id
         LEFT JOIN listing_additional_info add_info ON l.id = add_info.listing_id
         WHERE l.id = ? AND l.status = 'active'",
        [$listingId]
    );
    
    if (!$listing) {
        header('Location: ' . app_url('listings'));
        exit;
    }
    
    // Set page title with listing name
    $pageTitle = htmlspecialchars($listing['title']) . ' - PG Details';
    require __DIR__ . '/../app/includes/header.php';
    $baseUrl = app_url('');
    
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
    
    // Get all listing images
    $listingImages = $db->fetchAll(
        "SELECT id, image_path, image_order, is_cover 
         FROM listing_images 
         WHERE listing_id = ? 
         ORDER BY is_cover DESC, image_order ASC",
        [$listingId]
    );
    
    // If no images, use cover_image as fallback
    if (empty($listingImages) && !empty($listing['cover_image'])) {
        $listingImages = [
            [
                'image_path' => $listing['cover_image'],
                'is_cover' => 1
            ]
        ];
    }
    
    // Build image URLs
    $imageUrls = [];
    foreach ($listingImages as $img) {
        $imgPath = $img['image_path'];
        if (strpos($imgPath, 'http') === 0) {
            $imageUrls[] = $imgPath;
        } else {
            $imageUrls[] = app_url($imgPath);
        }
    }
    
    // Parse nearby landmarks if JSON
    $nearbyLandmarks = [];
    if (!empty($listing['nearby_landmarks'])) {
        $landmarks = json_decode($listing['nearby_landmarks'], true);
        if (is_array($landmarks)) {
            $nearbyLandmarks = $landmarks;
        }
    }
    
    // Format price range
    $minRent = null;
    $maxRent = null;
    if (!empty($roomConfigs)) {
        $rents = array_column($roomConfigs, 'rent_per_month');
        $minRent = min($rents);
        $maxRent = max($rents);
    }
    
    $priceText = '';
    if ($minRent) {
        if ($minRent == $maxRent) {
            $priceText = '₹' . number_format($minRent) . '/month';
        } else {
            $priceText = '₹' . number_format($minRent) . ' - ₹' . number_format($maxRent) . '/month';
        }
    }
    
    // Format location
    $locationParts = [];
    if (!empty($listing['city'])) {
        $locationParts[] = $listing['city'];
    }
    if (!empty($listing['pin_code'])) {
        $locationParts[] = $listing['pin_code'];
    }
    $locationText = implode(', ', $locationParts);
    
    // Format available for
    $availableForText = '';
    if (!empty($listing['available_for']) && $listing['available_for'] !== 'both') {
        $availableForText = ucfirst($listing['available_for']);
    } elseif (!empty($listing['gender_allowed'])) {
        $availableForText = ucfirst($listing['gender_allowed']);
    }
    
    // Get reviews for display
    $reviews = $db->fetchAll(
        "SELECT r.*, u.name as user_name, u.profile_image
         FROM reviews r
         LEFT JOIN users u ON r.user_id = u.id
         WHERE r.listing_id = ?
         ORDER BY r.created_at DESC
         LIMIT 5",
        [$listingId]
    );
    
} catch (Exception $e) {
    error_log("Error loading listing: " . $e->getMessage());
    header('Location: ' . app_url('listings'));
    exit;
}
?>

<!-- Breadcrumb -->
<nav aria-label="breadcrumb" class="mb-4">
    <ol class="breadcrumb">
        <li class="breadcrumb-item"><a href="<?= htmlspecialchars(app_url('')) ?>" class="text-decoration-none">Home</a></li>
        <li class="breadcrumb-item"><a href="<?= htmlspecialchars(app_url('listings')) ?>" class="text-decoration-none">Listings</a></li>
        <li class="breadcrumb-item active" aria-current="page"><?= htmlspecialchars($listing['title']) ?></li>
    </ol>
</nav>

<div class="row g-4">
    <!-- Main Content -->
    <div class="col-lg-8">
        <!-- Image Gallery -->
        <?php if (!empty($imageUrls)): ?>
            <div class="card pg mb-4">
                <div class="card-body p-0">
                    <div id="listingImageGallery" class="carousel slide" data-bs-ride="carousel">
                        <div class="carousel-inner">
                            <?php foreach ($imageUrls as $index => $imgUrl): ?>
                                <div class="carousel-item <?= $index === 0 ? 'active' : '' ?>">
                                    <img src="<?= htmlspecialchars($imgUrl) ?>" 
                                         class="d-block w-100 listing-detail-gallery"
                                         alt="<?= htmlspecialchars($listing['title']) ?> - Image <?= $index + 1 ?>"
                                         onerror="this.src='data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iODAwIiBoZWlnaHQ9IjUwMCIgeG1sbnM9Imh0dHA6Ly93d3cudzMub3JnLzIwMDAvc3ZnIj48cmVjdCB3aWR0aD0iMTAwJSIgaGVpZ2h0PSIxMDAlIiBmaWxsPSIjZGRkIi8+PHRleHQgeD0iNTAlIiB5PSI1MCUiIGZvbnQtZmFtaWx5PSJBcmlhbCwgc2Fucy1zZXJpZiIgZm9udC1zaXplPSIyNCIgZmlsbD0iIzk5OSIgdGV4dC1hbmNob3I9Im1pZGRsZSIgZHk9Ii4zZW0iPk5vIEltYWdlPC90ZXh0Pjwvc3ZnPg=='">
                                </div>
                            <?php endforeach; ?>
                        </div>
                        <?php if (count($imageUrls) > 1): ?>
                            <button class="carousel-control-prev" type="button" data-bs-target="#listingImageGallery" data-bs-slide="prev">
                                <span class="carousel-control-prev-icon" aria-hidden="true"></span>
                                <span class="visually-hidden">Previous</span>
                            </button>
                            <button class="carousel-control-next" type="button" data-bs-target="#listingImageGallery" data-bs-slide="next">
                                <span class="carousel-control-next-icon" aria-hidden="true"></span>
                                <span class="visually-hidden">Next</span>
                            </button>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <!-- Title and Basic Info -->
        <div class="card pg mb-4">
            <div class="card-body">
                <h1 class="display-6 mb-3"><?= htmlspecialchars($listing['title']) ?></h1>
                
                <div class="d-flex flex-wrap gap-3 mb-4">
                    <?php if (!empty($locationText)): ?>
                        <div class="d-flex align-items-center text-muted">
                            <i class="bi bi-geo-alt-fill me-2 icon-primary"></i>
                            <span><?= htmlspecialchars($locationText) ?></span>
                        </div>
                    <?php endif; ?>
                    
                    <?php if (!empty($availableForText)): ?>
                        <div class="d-flex align-items-center text-muted">
                            <i class="bi bi-people-fill me-2 icon-primary"></i>
                            <span><?= htmlspecialchars($availableForText) ?></span>
                        </div>
                    <?php endif; ?>
                    
                    <?php if (!empty($listing['avg_rating'])): ?>
                        <div class="d-flex align-items-center">
                            <i class="bi bi-star-fill text-warning me-1"></i>
                            <span class="fw-semibold"><?= number_format($listing['avg_rating'], 1) ?></span>
                            <?php if ($listing['reviews_count'] > 0): ?>
                                <span class="text-muted ms-1">(<?= $listing['reviews_count'] ?> reviews)</span>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                </div>
                
                <?php if (!empty($priceText)): ?>
                    <div class="mb-4">
                        <span class="h3 price-display"><?= htmlspecialchars($priceText) ?></span>
                    </div>
                <?php endif; ?>
                
                <?php if (!empty($listing['description'])): ?>
                    <div class="mt-4 pt-4 border-top">
                        <div class="kicker mb-2">About this PG</div>
                        <p class="text-muted mb-0 text-line-height-lg preserve-whitespace"><?= nl2br(htmlspecialchars($listing['description'])) ?></p>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Room Configurations -->
        <?php if (!empty($roomConfigs)): ?>
            <div class="card pg mb-4">
                <div class="card-body">
                    <div class="kicker mb-2">Room Options</div>
                    <h5 class="mb-4 listing-section-heading">
                        <i class="bi bi-door-open me-2"></i>Available Rooms
                    </h5>
                    <div class="row g-3">
                        <?php foreach ($roomConfigs as $room): ?>
                            <div class="col-md-6">
                                <div class="card pg h-100 room-card-nested">
                                    <div class="card-body">
                                        <div class="d-flex justify-content-between align-items-start mb-3">
                                            <h6 class="mb-0 fw-semibold"><?= ucfirst(str_replace(' sharing', '-Sharing', $room['room_type'])) ?></h6>
                                            <span class="badge badge-success-gradient"><?= $room['available_rooms'] ?> Available</span>
                                        </div>
                                        <div class="mb-3">
                                            <span class="h4 fw-bold price-display">₹<?= number_format($room['rent_per_month']) ?></span>
                                            <span class="text-muted">/month</span>
                                        </div>
                                        <div class="small text-muted">
                                            <div><i class="bi bi-door-closed me-1"></i>Total Rooms: <?= $room['total_rooms'] ?></div>
                                            <div><i class="bi bi-check-circle me-1"></i>Available: <?= $room['available_rooms'] ?></div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <!-- Amenities -->
        <?php if (!empty($amenities)): ?>
            <div class="card pg mb-4">
                <div class="card-body">
                    <div class="kicker mb-2">Amenities</div>
                    <h5 class="mb-4 listing-section-heading">
                        <i class="bi bi-star-fill me-2"></i>What's Included
                    </h5>
                    <div class="row g-3">
                        <?php foreach ($amenities as $amenity): ?>
                            <div class="col-md-4 col-sm-6">
                                <div class="d-flex align-items-center p-2 rounded accent-bg">
                                    <i class="bi bi-check-circle-fill me-2 icon-primary-700"></i>
                                    <span class="fw-medium"><?= htmlspecialchars($amenity['name']) ?></span>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <!-- Additional Information -->
        <div class="card pg mb-4">
            <div class="card-body">
                <div class="kicker mb-2">Additional Information</div>
                <h5 class="mb-4 listing-section-heading">
                    <i class="bi bi-info-circle me-2"></i>Property Details
                </h5>
                <div class="row g-4">
                    <?php if (!empty($listing['electricity_charges'])): ?>
                        <div class="col-md-6">
                            <div class="d-flex align-items-start p-3 rounded accent-bg">
                                <i class="bi bi-lightning-charge-fill me-3 mt-1 info-item-icon"></i>
                                <div>
                                    <div class="fw-semibold mb-1">Electricity Charges</div>
                                    <div class="text-muted small"><?= ucfirst(str_replace('_', ' ', $listing['electricity_charges'])) ?></div>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>
                    
                    <?php if (!empty($listing['food_availability'])): ?>
                        <div class="col-md-6">
                            <div class="d-flex align-items-start p-3 rounded accent-bg">
                                <i class="bi bi-egg-fried me-3 mt-1 info-item-icon"></i>
                                <div>
                                    <div class="fw-semibold mb-1">Food Availability</div>
                                    <div class="text-muted small"><?= ucfirst(str_replace('_', ' ', $listing['food_availability'])) ?></div>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>
                    
                    <?php if (!empty($listing['gate_closing_time'])): ?>
                        <div class="col-md-6">
                            <div class="d-flex align-items-start p-3 rounded accent-bg">
                                <i class="bi bi-clock-fill me-3 mt-1 info-item-icon"></i>
                                <div>
                                    <div class="fw-semibold mb-1">Gate Closing Time</div>
                                    <div class="text-muted small"><?= date('h:i A', strtotime($listing['gate_closing_time'])) ?></div>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>
                    
                    <?php if (!empty($listing['total_beds'])): ?>
                        <div class="col-md-6">
                            <div class="d-flex align-items-start p-3 rounded accent-bg">
                                <i class="bi bi-bed me-3 mt-1 info-item-icon"></i>
                                <div>
                                    <div class="fw-semibold mb-1">Total Beds</div>
                                    <div class="text-muted small"><?= $listing['total_beds'] ?> beds</div>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>
                    
                    <?php if (!empty($listing['security_deposit_amount'])): ?>
                        <div class="col-md-6">
                            <div class="d-flex align-items-start p-3 rounded accent-bg">
                                <i class="bi bi-shield-check me-3 mt-1 info-item-icon"></i>
                                <div>
                                    <div class="fw-semibold mb-1">Security Deposit</div>
                                    <div class="text-muted small"><?= htmlspecialchars($listing['security_deposit_amount']) ?></div>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>
                    
                    <?php if (!empty($listing['notice_period'])): ?>
                        <div class="col-md-6">
                            <div class="d-flex align-items-start p-3 rounded accent-bg">
                                <i class="bi bi-calendar-event me-3 mt-1 info-item-icon"></i>
                                <div>
                                    <div class="fw-semibold mb-1">Notice Period</div>
                                    <div class="text-muted small"><?= $listing['notice_period'] ?> days</div>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>
                    
                    <?php if (!empty($listing['preferred_tenants']) && $listing['preferred_tenants'] !== 'anyone'): ?>
                        <div class="col-md-6">
                            <div class="d-flex align-items-start p-3 rounded accent-bg">
                                <i class="bi bi-person-badge me-3 mt-1 info-item-icon"></i>
                                <div>
                                    <div class="fw-semibold mb-1">Preferred Tenants</div>
                                    <div class="text-muted small"><?= ucfirst(str_replace('_', ' ', $listing['preferred_tenants'])) ?></div>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- House Rules -->
        <?php if (!empty($houseRules)): ?>
            <div class="card pg mb-4">
                <div class="card-body">
                    <div class="kicker mb-2">House Rules</div>
                    <h5 class="mb-4 listing-section-heading">
                        <i class="bi bi-list-check me-2"></i>Rules & Guidelines
                    </h5>
                    <ul class="list-unstyled">
                        <?php foreach ($houseRules as $rule): ?>
                            <li class="mb-3 p-2 rounded accent-bg">
                                <i class="bi bi-check2-circle me-2 icon-primary-700"></i>
                                <span class="fw-medium"><?= htmlspecialchars($rule['name']) ?></span>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            </div>
        <?php endif; ?>

        <!-- Location Details -->
        <?php if (!empty($listing['complete_address']) || !empty($listing['google_maps_link'])): ?>
            <div class="card pg mb-4">
                <div class="card-body">
                    <div class="kicker mb-2">Location</div>
                    <h5 class="mb-4 listing-section-heading">
                        <i class="bi bi-geo-alt-fill me-2"></i>Where is it located?
                    </h5>
                    
                    <?php if (!empty($listing['complete_address'])): ?>
                        <div class="mb-4 p-3 rounded accent-bg">
                            <div class="fw-semibold mb-2">Complete Address:</div>
                            <p class="text-muted mb-0 text-line-height-lg"><?= nl2br(htmlspecialchars($listing['complete_address'])) ?></p>
                        </div>
                    <?php endif; ?>
                    
                    <?php if (!empty($nearbyLandmarks)): ?>
                        <div class="mb-4">
                            <div class="fw-semibold mb-3">Nearby Landmarks:</div>
                            <div class="d-flex flex-wrap gap-2">
                                <?php foreach ($nearbyLandmarks as $landmark): ?>
                                    <span class="badge px-3 py-2 badge-primary"><?= htmlspecialchars($landmark) ?></span>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endif; ?>
                    
                    <?php if (!empty($listing['google_maps_link'])): ?>
                        <div class="mt-3">
                            <a href="<?= htmlspecialchars($listing['google_maps_link']) ?>" 
                               target="_blank" 
                               rel="noopener" 
                               class="btn btn-primary">
                                <i class="bi bi-map me-2"></i>View on Google Maps
                            </a>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>

        <!-- Reviews -->
        <?php if (!empty($reviews)): ?>
            <div class="card pg mb-4">
                <div class="card-body">
                    <div class="kicker mb-2">Reviews</div>
                    <h5 class="mb-4 listing-section-heading">
                        <i class="bi bi-star-fill me-2"></i>What Others Say
                    </h5>
                    <?php foreach ($reviews as $review): ?>
                        <div class="border-bottom pb-4 mb-4 theme-border-color">
                            <div class="d-flex align-items-center mb-3">
                                <div class="me-3">
                                    <?php if (!empty($review['profile_image'])): ?>
                                        <img src="<?= htmlspecialchars(strpos($review['profile_image'], 'http') === 0 ? $review['profile_image'] : app_url($review['profile_image'])) ?>" 
                                             class="rounded-circle review-profile-img"
                                             alt="<?= htmlspecialchars($review['user_name']) ?>"
                                             onerror="this.style.display='none'; this.nextElementSibling.style.display='block';">
                                        <i class="bi bi-person-circle review-profile-icon" style="display: none;"></i>
                                    <?php else: ?>
                                        <i class="bi bi-person-circle review-profile-icon"></i>
                                    <?php endif; ?>
                                </div>
                                <div class="flex-grow-1">
                                    <div class="fw-semibold mb-1"><?= htmlspecialchars($review['user_name'] ?? 'Anonymous') ?></div>
                                    <div class="small text-muted">
                                        <?php for ($i = 1; $i <= 5; $i++): ?>
                                            <i class="bi bi-star<?= $i <= $review['rating'] ? '-fill text-warning' : '' ?>"></i>
                                        <?php endfor; ?>
                                        <span class="ms-2"><?= date('M d, Y', strtotime($review['created_at'])) ?></span>
                                    </div>
                                </div>
                            </div>
                            <?php if (!empty($review['comment'])): ?>
                                <p class="mb-0 text-muted text-line-height-md"><?= nl2br(htmlspecialchars($review['comment'])) ?></p>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <!-- Sidebar -->
    <div class="col-lg-4">
        <!-- Booking Card -->
        <div class="card pg sticky-top shadow-sm sticky-sidebar-card theme-border">
            <div class="card-body">
                <?php if (!empty($priceText)): ?>
                    <div class="text-center mb-4 pb-4 border-bottom theme-border-color">
                        <div class="h2 fw-bold mb-2 price-display"><?= htmlspecialchars($priceText) ?></div>
                        <small class="text-muted">Starting from</small>
                    </div>
                <?php endif; ?>
                
                <div class="d-grid gap-2 mb-4">
                    <?php if (isLoggedIn()): ?>
                        <a href="<?= htmlspecialchars(app_url('book?id=' . $listingId)) ?>" 
                           class="btn btn-primary btn-lg text-white">
                            <i class="bi bi-check-circle me-2"></i>Book Now
                        </a>
                    <?php else: ?>
                        <button type="button" 
                                class="btn btn-primary btn-lg text-white" 
                                data-bs-toggle="modal" 
                                data-bs-target="#loginModal"
                                data-booking-url="<?= htmlspecialchars(app_url('book?id=' . $listingId)) ?>"
                                id="bookNowBtn">
                            <i class="bi bi-check-circle me-2"></i>Book Now
                        </button>
                    <?php endif; ?>
                    <?php if (isLoggedIn()): ?>
                        <a href="<?= htmlspecialchars(app_url('visit-book?id=' . $listingId)) ?>" 
                           class="btn btn-outline-primary btn-lg text-white">
                            <i class="bi bi-calendar-check me-2"></i>Book a Visit
                        </a>
                    <?php else: ?>
                        <button type="button" 
                                class="btn btn-outline-primary btn-lg text-white" 
                                data-bs-toggle="modal" 
                                data-bs-target="#loginModal"
                                data-booking-url="<?= htmlspecialchars(app_url('visit-book?id=' . $listingId)) ?>"
                                id="bookVisitBtn">
                            <i class="bi bi-calendar-check me-2"></i>Book a Visit
                        </button>
                    <?php endif; ?>
                </div>
                
                <hr class="theme-border-color">
                
                <div class="small">
                    <div class="mb-3">
                        <div class="kicker mb-1">Owner</div>
                        <div class="fw-semibold"><?= htmlspecialchars($listing['owner_name']) ?></div>
                    </div>
                    <?php if (!empty($locationText)): ?>
                        <div class="mb-3">
                            <div class="kicker mb-1">Location</div>
                            <div class="fw-semibold"><?= htmlspecialchars($locationText) ?></div>
                        </div>
                    <?php endif; ?>
                    <?php if (!empty($availableForText)): ?>
                        <div class="mb-3">
                            <div class="kicker mb-1">Available for</div>
                            <div class="fw-semibold"><?= htmlspecialchars($availableForText) ?></div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require __DIR__ . '/../app/includes/footer.php'; ?>

