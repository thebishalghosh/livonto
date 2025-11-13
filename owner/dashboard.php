<?php
/**
 * Owner Dashboard
 * Shows owner's listing and allows them to manage availability
 */

session_start();
require __DIR__ . '/../app/config.php';
require __DIR__ . '/../app/functions.php';

requireOwnerLogin();

$listingId = getCurrentOwnerListingId();
$db = db();

    // Get owner's listing details
try {
    $listing = $db->fetchOne(
        "SELECT l.*, 
                ll.city, ll.pin_code, ll.complete_address,
                (SELECT COUNT(*) FROM room_configurations WHERE listing_id = l.id) as room_count,
                (SELECT COUNT(*) FROM bookings WHERE listing_id = l.id) as booking_count
         FROM listings l
         LEFT JOIN listing_locations ll ON l.id = ll.listing_id
         WHERE l.id = ?",
        [$listingId]
    );
    
    if (!$listing) {
        $_SESSION['flash_message'] = 'Listing not found';
        $_SESSION['flash_type'] = 'danger';
        header('Location: ' . app_url('owner/login'));
        exit;
    }
    
    // Check if owner has password set
    $hasPassword = !empty($listing['owner_password_hash']);
    
    // Get room configurations with bed-based availability
    // available_rooms column represents available beds (not rooms)
    $rooms = $db->fetchAll(
        "SELECT rc.*, 
                (SELECT COUNT(*) FROM bookings b 
                 WHERE b.room_config_id = rc.id 
                 AND b.status IN ('pending', 'confirmed')) as booked_beds
         FROM room_configurations rc
         WHERE rc.listing_id = ?
         ORDER BY rc.room_type, rc.rent_per_month",
        [$listingId]
    );
    
    // Calculate bed information for each room using unified calculation
    foreach ($rooms as &$room) {
        $room['beds_per_room'] = getBedsPerRoom($room['room_type']);
        $room['total_beds'] = calculateTotalBeds($room['total_rooms'], $room['room_type']);
        $room['booked_beds'] = (int)($room['booked_beds'] ?? 0);
        
        // Use unified calculation: total_beds - booked_beds (ensures consistency)
        $room['available_beds'] = calculateAvailableBeds($room['total_rooms'], $room['room_type'], $room['booked_beds']);
    }
    unset($room);
    
} catch (Exception $e) {
    $_SESSION['flash_message'] = 'Error loading listing details';
    $_SESSION['flash_type'] = 'danger';
    header('Location: ' . app_url('owner/login'));
    exit;
}

$pageTitle = "Owner Dashboard - " . htmlspecialchars($listing['title']);
require __DIR__ . '/../app/includes/owner_header.php';

$flashMessage = getFlashMessage();

// Get additional listing details
try {
    $location = $db->fetchOne("SELECT * FROM listing_locations WHERE listing_id = ?", [$listingId]);
    $additionalInfo = $db->fetchOne("SELECT * FROM listing_additional_info WHERE listing_id = ?", [$listingId]);
    $amenities = $db->fetchAll(
        "SELECT a.name FROM amenities a 
         INNER JOIN listing_amenities la ON a.id = la.amenity_id 
         WHERE la.listing_id = ? ORDER BY a.name",
        [$listingId]
    );
    $houseRules = $db->fetchAll(
        "SELECT hr.name FROM house_rules hr 
         INNER JOIN listing_rules lr ON hr.id = lr.rule_id 
         WHERE lr.listing_id = ? ORDER BY hr.name",
        [$listingId]
    );
    $recentBookings = $db->fetchAll(
        "SELECT b.*, u.name as user_name, u.email as user_email, u.phone as user_phone
         FROM bookings b
         LEFT JOIN users u ON b.user_id = u.id
         WHERE b.listing_id = ?
         ORDER BY b.created_at DESC
         LIMIT 5",
        [$listingId]
    );
} catch (Exception $e) {
    $location = null;
    $additionalInfo = null;
    $amenities = [];
    $houseRules = [];
    $recentBookings = [];
}
?>

<div class="mb-4">
    <h2 class="h4 mb-1">Property Dashboard</h2>
    <p class="text-muted small mb-0">Welcome, <?= htmlspecialchars($_SESSION['owner_name']) ?></p>
</div>
    
    <?php if ($flashMessage): ?>
        <div class="alert alert-<?= htmlspecialchars($flashMessage['type']) ?> alert-dismissible fade show" role="alert">
            <?= htmlspecialchars($flashMessage['message']) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>
    
    <!-- Property Header -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="card shadow-sm border-0 property-header-card">
                <div class="card-body p-4">
                    <div class="d-flex justify-content-between align-items-center flex-wrap gap-3">
                        <div>
                            <h3 class="mb-2 fw-bold text-primary"><?= htmlspecialchars($listing['title']) ?></h3>
                            <div class="d-flex flex-wrap align-items-center gap-3">
                                <span class="badge bg-<?= $listing['status'] === 'active' ? 'success' : ($listing['status'] === 'draft' ? 'warning' : 'secondary') ?>">
                                    <?= ucfirst($listing['status']) ?>
                                </span>
                                <span class="text-muted">
                                    <i class="bi bi-building me-1"></i>Listing ID: <strong><?= $listing['id'] ?></strong>
                                </span>
                                <?php if ($location): ?>
                                    <span class="text-muted">
                                        <i class="bi bi-geo-alt-fill me-1 text-primary"></i>
                                        <?= htmlspecialchars($location['city'] ?? 'N/A') ?>
                                        <?= $location['pin_code'] ? ' - ' . htmlspecialchars($location['pin_code']) : '' ?>
                                    </span>
                                <?php endif; ?>
                            </div>
                        </div>
                        <a href="<?= app_url('owner/listings/edit') ?>" class="btn btn-primary btn-lg">
                            <i class="bi bi-pencil me-2"></i>Update Availability
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Statistics Cards -->
    <div class="row g-3 mb-4">
        <div class="col-lg-3 col-md-6 col-sm-6">
            <div class="card stats-card stats-card-primary h-100 shadow-sm border-0">
                <div class="card-body text-center p-4">
                    <div class="stats-icon mb-3">
                        <i class="bi bi-door-open fs-1 text-white"></i>
                    </div>
                    <h2 class="fw-bold mb-1 text-white"><?= count($rooms) ?></h2>
                    <p class="mb-0 text-white-50 small">Room Types</p>
                </div>
            </div>
        </div>
        <div class="col-lg-3 col-md-6 col-sm-6">
            <div class="card stats-card stats-card-success h-100 shadow-sm border-0">
                <div class="card-body text-center p-4">
                    <div class="stats-icon mb-3">
                        <i class="bi bi-calendar-check fs-1 text-white"></i>
                    </div>
                    <h2 class="fw-bold mb-1 text-white"><?= $listing['booking_count'] ?></h2>
                    <p class="mb-0 text-white-50 small">Total Bookings</p>
                </div>
            </div>
        </div>
        <div class="col-lg-3 col-md-6 col-sm-6">
            <div class="card stats-card stats-card-info h-100 shadow-sm border-0">
                <div class="card-body text-center p-4">
                    <div class="stats-icon mb-3">
                        <i class="bi bi-grid-3x3-gap fs-1" style="color: white !important; opacity: 0.95;"></i>
                    </div>
                    <h2 class="fw-bold mb-1" style="color: white !important;">
                        <?php
                        $totalBeds = array_sum(array_column($rooms, 'total_beds'));
                        echo $totalBeds;
                        ?>
                    </h2>
                    <p class="mb-0 small fw-medium" style="color: rgba(255, 255, 255, 0.95) !important;">Total Beds</p>
                </div>
            </div>
        </div>
        <div class="col-lg-3 col-md-6 col-sm-6">
            <div class="card stats-card stats-card-warning h-100 shadow-sm border-0">
                <div class="card-body text-center p-4">
                    <div class="stats-icon mb-3">
                        <i class="bi bi-check-circle fs-1"></i>
                    </div>
                    <h2 class="fw-bold mb-1 text-white">
                        <?php
                        $totalAvailableBeds = 0;
                        foreach ($rooms as $room) {
                            $totalAvailableBeds += (int)($room['available_beds'] ?? 0);
                        }
                        echo $totalAvailableBeds;
                        ?>
                    </h2>
                    <p class="mb-0 text-white-50 small">Available Beds</p>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Property Details -->
    <div class="row g-4 mb-4">
        <div class="col-lg-6 col-md-12">
            <div class="card shadow-sm border-0 h-100">
                <div class="card-header bg-gradient-primary text-white border-0">
                    <h6 class="mb-0 fw-semibold"><i class="bi bi-info-circle me-2"></i>Property Information</h6>
                </div>
                <div class="card-body p-4">
                    <div class="mb-3">
                        <label class="text-muted small mb-1 d-block">Description</label>
                        <p class="mb-0"><?= nl2br(htmlspecialchars($listing['description'] ?: 'N/A')) ?></p>
                    </div>
                    <hr class="my-3">
                    <div class="row g-3">
                        <div class="col-6">
                            <label class="text-muted small mb-1 d-block">Available For</label>
                            <p class="mb-0 fw-semibold"><?= ucfirst($listing['available_for'] ?? 'N/A') ?></p>
                        </div>
                        <div class="col-6">
                            <label class="text-muted small mb-1 d-block">Gender Allowed</label>
                            <p class="mb-0 fw-semibold"><?= ucfirst($listing['gender_allowed'] ?? 'N/A') ?></p>
                        </div>
                        <div class="col-6">
                            <label class="text-muted small mb-1 d-block">Preferred Tenants</label>
                            <p class="mb-0 fw-semibold"><?= ucfirst(str_replace('_', ' ', $listing['preferred_tenants'] ?? 'N/A')) ?></p>
                        </div>
                        <div class="col-6">
                            <label class="text-muted small mb-1 d-block">Security Deposit</label>
                            <p class="mb-0 fw-semibold"><?= htmlspecialchars($listing['security_deposit_amount'] ?? 'N/A') ?></p>
                        </div>
                        <div class="col-12">
                            <label class="text-muted small mb-1 d-block">Notice Period</label>
                            <p class="mb-0 fw-semibold"><?= $listing['notice_period'] ? $listing['notice_period'] . ' days' : 'N/A' ?></p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-lg-6 col-md-12">
            <div class="card shadow-sm border-0 h-100">
                <div class="card-header bg-gradient-primary text-white border-0">
                    <h6 class="mb-0 fw-semibold"><i class="bi bi-geo-alt me-2"></i>Location Details</h6>
                </div>
                <div class="card-body p-4">
                    <?php if ($location): ?>
                        <div class="mb-3">
                            <label class="text-muted small mb-1 d-block">Address</label>
                            <p class="mb-0"><?= nl2br(htmlspecialchars($location['complete_address'] ?: 'N/A')) ?></p>
                        </div>
                        <hr class="my-3">
                        <div class="row g-3">
                            <div class="col-6">
                                <label class="text-muted small mb-1 d-block">City</label>
                                <p class="mb-0 fw-semibold"><?= htmlspecialchars($location['city'] ?? 'N/A') ?></p>
                            </div>
                            <div class="col-6">
                                <label class="text-muted small mb-1 d-block">Pin Code</label>
                                <p class="mb-0 fw-semibold"><?= htmlspecialchars($location['pin_code'] ?? 'N/A') ?></p>
                            </div>
                        </div>
                        <?php if ($location['google_maps_link']): ?>
                            <div class="mt-3">
                                <a href="<?= htmlspecialchars($location['google_maps_link']) ?>" target="_blank" class="btn btn-primary btn-sm">
                                    <i class="bi bi-map me-1"></i> View on Maps
                                </a>
                            </div>
                        <?php endif; ?>
                    <?php else: ?>
                        <p class="text-muted mb-0">Location information not available</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    
    <?php if ($additionalInfo): ?>
    <div class="row g-4 mb-4">
        <div class="col-12">
            <div class="card shadow-sm border-0">
                <div class="card-header bg-gradient-primary text-white border-0">
                    <h6 class="mb-0 fw-semibold"><i class="bi bi-list-check me-2"></i>Additional Information</h6>
                </div>
                <div class="card-body p-4">
                    <div class="row g-3">
                        <?php if ($additionalInfo['electricity_charges']): ?>
                            <div class="col-lg-3 col-md-6 col-sm-6">
                                <div class="info-item">
                                    <label class="text-muted small mb-1 d-block"><i class="bi bi-lightning-charge me-1"></i>Electricity</label>
                                    <p class="mb-0 fw-semibold"><?= ucfirst(htmlspecialchars($additionalInfo['electricity_charges'])) ?></p>
                                </div>
                            </div>
                        <?php endif; ?>
                        <?php if ($additionalInfo['food_availability']): ?>
                            <div class="col-lg-3 col-md-6 col-sm-6">
                                <div class="info-item">
                                    <label class="text-muted small mb-1 d-block"><i class="bi bi-egg-fried me-1"></i>Food</label>
                                    <p class="mb-0 fw-semibold"><?= ucfirst(htmlspecialchars($additionalInfo['food_availability'])) ?></p>
                                </div>
                            </div>
                        <?php endif; ?>
                        <?php if ($additionalInfo['gate_closing_time']): ?>
                            <div class="col-lg-3 col-md-6 col-sm-6">
                                <div class="info-item">
                                    <label class="text-muted small mb-1 d-block"><i class="bi bi-clock me-1"></i>Gate Closing</label>
                                    <p class="mb-0 fw-semibold"><?= htmlspecialchars($additionalInfo['gate_closing_time']) ?></p>
                                </div>
                            </div>
                        <?php endif; ?>
                        <?php if ($additionalInfo['total_beds']): ?>
                            <div class="col-lg-3 col-md-6 col-sm-6">
                                <div class="info-item">
                                    <label class="text-muted small mb-1 d-block"><i class="bi bi-bed me-1"></i>Total Beds</label>
                                    <p class="mb-0 fw-semibold"><?= htmlspecialchars($additionalInfo['total_beds']) ?></p>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>
    
    <?php if (!empty($amenities)): ?>
    <div class="row g-4 mb-4">
        <div class="col-12">
            <div class="card shadow-sm border-0">
                <div class="card-header bg-gradient-primary text-white border-0">
                    <h6 class="mb-0 fw-semibold"><i class="bi bi-star me-2"></i>Amenities</h6>
                </div>
                <div class="card-body p-4">
                    <?php foreach ($amenities as $amenity): ?>
                        <span class="badge badge-amenity me-2 mb-2"><?= htmlspecialchars($amenity['name']) ?></span>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>
    
    <?php if (!empty($houseRules)): ?>
    <div class="row g-4 mb-4">
        <div class="col-12">
            <div class="card shadow-sm border-0">
                <div class="card-header bg-gradient-primary text-white border-0">
                    <h6 class="mb-0 fw-semibold"><i class="bi bi-shield-check me-2"></i>House Rules</h6>
                </div>
                <div class="card-body p-4">
                    <ul class="list-unstyled mb-0">
                        <?php foreach ($houseRules as $rule): ?>
                            <li class="mb-2 d-flex align-items-center">
                                <i class="bi bi-check-circle-fill text-success me-2"></i>
                                <span><?= htmlspecialchars($rule['name']) ?></span>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>
    
    <!-- Room Availability -->
    <div class="card shadow-sm border-0 mb-4">
        <div class="card-header bg-gradient-primary text-white border-0 d-flex justify-content-between align-items-center flex-wrap gap-2">
            <h6 class="mb-0 fw-semibold"><i class="bi bi-door-open me-2"></i>Room Availability</h6>
            <a href="<?= app_url('owner/listings/edit') ?>" class="btn btn-light btn-sm">
                <i class="bi bi-pencil me-1"></i> Update
            </a>
        </div>
        <div class="card-body p-0">
            <?php if (empty($rooms)): ?>
                <div class="p-4 text-center">
                    <i class="bi bi-inbox fs-1 text-muted d-block mb-2"></i>
                    <p class="text-muted mb-0">No rooms configured for this listing.</p>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead class="table-light">
                            <tr>
                                <th class="ps-4">Room Type</th>
                                <th>Rent/Month</th>
                                <th>Total</th>
                                <th>Booked Beds</th>
                                <th>Available Beds</th>
                                <th class="pe-4">Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($rooms as $room): ?>
                                <?php
                                // Bed-based availability (available_rooms = available beds)
                                $availableBeds = (int)($room['available_beds'] ?? 0);
                                $totalBeds = (int)($room['total_beds'] ?? 0);
                                $bookedBeds = (int)($room['booked_beds'] ?? 0);
                                $isAvailable = $availableBeds > 0;
                                ?>
                                <tr>
                                    <td class="ps-4">
                                        <div class="fw-semibold"><?= htmlspecialchars(ucfirst(str_replace(' sharing', '', $room['room_type'] ?? 'N/A'))) ?></div>
                                        <small class="text-muted"><i class="bi bi-bed me-1"></i><?= $room['beds_per_room'] ?> bed<?= $room['beds_per_room'] > 1 ? 's' : '' ?> per room</small>
                                    </td>
                                    <td>
                                        <span class="fw-semibold text-primary">₹<?= number_format($room['rent_per_month'] ?? 0) ?></span>
                                    </td>
                                    <td>
                                        <div><?= $room['total_rooms'] ?? 0 ?> room<?= ($room['total_rooms'] ?? 0) != 1 ? 's' : '' ?></div>
                                        <small class="text-muted"><?= $totalBeds ?> beds</small>
                                    </td>
                                    <td>
                                        <span class="badge bg-secondary"><?= $bookedBeds ?> bed<?= $bookedBeds != 1 ? 's' : '' ?></span>
                                    </td>
                                    <td>
                                        <span class="badge bg-<?= $isAvailable ? 'success' : 'danger' ?>">
                                            <?= $availableBeds ?> bed<?= $availableBeds != 1 ? 's' : '' ?>
                                        </span>
                                    </td>
                                    <td class="pe-4">
                                        <?php if ($isAvailable): ?>
                                            <span class="badge bg-success-subtle text-success">
                                                <i class="bi bi-check-circle me-1"></i>Available
                                            </span>
                                        <?php else: ?>
                                            <span class="badge bg-danger-subtle text-danger">
                                                <i class="bi bi-x-circle me-1"></i>Fully Booked
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- Recent Bookings -->
    <?php if (!empty($recentBookings)): ?>
    <div class="card shadow-sm border-0 mb-4">
        <div class="card-header bg-gradient-primary text-white border-0">
            <h6 class="mb-0 fw-semibold"><i class="bi bi-calendar-check me-2"></i>Recent Bookings</h6>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead class="table-light">
                        <tr>
                            <th class="ps-4">User</th>
                            <th>Start Date</th>
                            <th>Amount</th>
                            <th class="pe-4">Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($recentBookings as $booking): ?>
                            <tr>
                                <td class="ps-4">
                                    <div class="fw-semibold"><?= htmlspecialchars($booking['user_name'] ?: 'Unknown') ?></div>
                                    <small class="text-muted"><?= htmlspecialchars($booking['user_email'] ?? '') ?></small>
                                </td>
                                <td>
                                    <i class="bi bi-calendar3 me-1 text-muted"></i>
                                    <?= $booking['booking_start_date'] ? date('d M Y', strtotime($booking['booking_start_date'])) : 'N/A' ?>
                                </td>
                                <td>
                                    <span class="fw-semibold text-primary">₹<?= number_format($booking['total_amount'] ?? 0, 2) ?></span>
                                </td>
                                <td class="pe-4">
                                    <?php
                                    $statusClass = $booking['status'] === 'confirmed' ? 'success' : ($booking['status'] === 'pending' ? 'warning' : 'secondary');
                                    $statusIcon = $booking['status'] === 'confirmed' ? 'check-circle' : ($booking['status'] === 'pending' ? 'clock' : 'x-circle');
                                    ?>
                                    <span class="badge bg-<?= $statusClass ?>">
                                        <i class="bi bi-<?= $statusIcon ?> me-1"></i><?= ucfirst($booking['status']) ?>
                                    </span>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <?php endif; ?>
    
    <!-- Change Password -->
    <?php if ($hasPassword): ?>
    <div class="row g-4 mb-4">
        <div class="col-12">
            <div class="card shadow-sm border-0">
                <div class="card-header bg-gradient-primary text-white border-0">
                    <h6 class="mb-0 fw-semibold"><i class="bi bi-key me-2"></i>Change Password</h6>
                </div>
                <div class="card-body p-4">
                    <form id="changePasswordForm">
                        <div id="passwordAlert"></div>
                        
                        <div class="row g-4">
                            <div class="col-lg-4 col-md-6">
                                <label for="currentPassword" class="form-label fw-semibold mb-2">Current Password</label>
                                <input type="password" class="form-control form-control-lg" id="currentPassword" name="current_password" required placeholder="Enter current password">
                            </div>
                            
                            <div class="col-lg-4 col-md-6">
                                <label for="newPassword" class="form-label fw-semibold mb-2">New Password</label>
                                <input type="password" class="form-control form-control-lg" id="newPassword" name="new_password" required minlength="8" placeholder="Enter new password">
                                <small class="text-muted d-block mt-1">Must be at least 8 characters long</small>
                            </div>
                            
                            <div class="col-lg-4 col-md-12">
                                <label for="confirmNewPassword" class="form-label fw-semibold mb-2">Confirm New Password</label>
                                <input type="password" class="form-control form-control-lg" id="confirmNewPassword" name="confirm_new_password" required minlength="8" placeholder="Confirm new password">
                            </div>
                        </div>
                        
                        <div class="mt-4">
                            <button type="submit" class="btn btn-primary btn-lg" id="changePasswordBtn">
                                <span id="changePasswordSpinner" class="spinner-border spinner-border-sm me-2 d-none"></span>
                                <i class="bi bi-key me-2"></i>Change Password
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>

<?php require __DIR__ . '/../app/includes/owner_footer.php'; ?>

<style>
/* Owner Dashboard Custom Styles */
:root {
    --primary: #8b6bd1;
    --primary-700: #6f55b2;
    --success: #43e97b;
    --info: #4facfe;
    --warning: #fbbf24;
}

.stats-card {
    border-radius: 16px;
    transition: all 0.3s ease;
    overflow: hidden;
    position: relative;
}

.stats-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 8px 25px rgba(0,0,0,0.15) !important;
}

.stats-card-primary {
    background: linear-gradient(135deg, var(--primary) 0%, var(--primary-700) 100%);
}

.stats-card-success {
    background: linear-gradient(135deg, #43e97b 0%, #38f9d7 100%);
}

.stats-card-info {
    background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);
}

.stats-card-warning {
    background: linear-gradient(135deg, #fbbf24 0%, #f59e0b 100%);
}

.stats-icon {
    opacity: 0.9;
}

.stats-icon i {
    color: white !important;
    opacity: 0.95;
    display: inline-block;
}

.stats-card .text-white-50,
.stats-card .text-white {
    color: rgba(255, 255, 255, 0.95) !important;
}

.bg-gradient-primary {
    background: linear-gradient(90deg, var(--primary) 0%, var(--primary-700) 100%) !important;
}

.property-header-card {
    background: linear-gradient(135deg, rgba(139, 107, 209, 0.05) 0%, rgba(111, 85, 178, 0.05) 100%);
    border-left: 4px solid var(--primary);
}

.card {
    border-radius: 12px;
    transition: all 0.3s ease;
}

.card:hover {
    box-shadow: 0 4px 20px rgba(0,0,0,0.08) !important;
}

.card-header {
    border-radius: 12px 12px 0 0 !important;
    padding: 1rem 1.5rem;
}

.badge-amenity {
    background: linear-gradient(135deg, rgba(139, 107, 209, 0.1) 0%, rgba(111, 85, 178, 0.1) 100%);
    color: var(--primary);
    padding: 0.5rem 1rem;
    border-radius: 20px;
    font-weight: 500;
    border: 1px solid rgba(139, 107, 209, 0.2);
}

.table thead th {
    font-weight: 600;
    text-transform: uppercase;
    font-size: 0.75rem;
    letter-spacing: 0.5px;
    color: #6c757d;
    border-bottom: 2px solid #e9ecef;
}

.table tbody tr {
    transition: all 0.2s ease;
}

.table tbody tr:hover {
    background-color: rgba(139, 107, 209, 0.05);
    transform: scale(1.01);
}

.info-item {
    padding: 0.75rem;
    background: rgba(139, 107, 209, 0.03);
    border-radius: 8px;
    border-left: 3px solid var(--primary);
}

.form-control {
    border: 1.5px solid #e0e0e0;
    border-radius: 8px;
    transition: all 0.2s ease;
}

.form-control:focus {
    border-color: var(--primary);
    box-shadow: 0 0 0 0.2rem rgba(139, 107, 209, 0.15);
}

.form-control-lg {
    padding: 0.75rem 1rem;
    font-size: 1rem;
}

@media (max-width: 768px) {
    .stats-card .card-body {
        padding: 1.5rem !important;
    }
    
    .stats-icon {
        font-size: 2rem !important;
    }
    
    .stats-card h2 {
        font-size: 1.75rem !important;
    }
    
    .table-responsive {
        font-size: 0.875rem;
    }
    
    .card-body {
        padding: 1rem !important;
    }
}

@media (max-width: 576px) {
    .property-header-card .btn {
        width: 100%;
        margin-top: 1rem;
    }
    
    .stats-card {
        margin-bottom: 1rem;
    }
}
</style>

<script>
// Change Password Form
(function() {
    const form = document.getElementById('changePasswordForm');
    if (form) {
        form.addEventListener('submit', async function(e) {
            e.preventDefault();
            
            const submitBtn = document.getElementById('changePasswordBtn');
            const spinner = document.getElementById('changePasswordSpinner');
            const alertDiv = document.getElementById('passwordAlert');
            
            // Clear previous alerts
            alertDiv.innerHTML = '';
            
            // Disable submit button
            submitBtn.disabled = true;
            spinner.classList.remove('d-none');
            
            const formData = new FormData(form);
            
            try {
                const response = await fetch('<?= htmlspecialchars(app_url('owner/change-password-api')) ?>', {
                    method: 'POST',
                    body: formData,
                    credentials: 'include',
                    headers: {
                        'Accept': 'application/json'
                    }
                });
                
                const data = await response.json();
                
                if (data.status === 'ok' || data.status === 'success') {
                    alertDiv.innerHTML = '<div class="alert alert-success alert-dismissible fade show" role="alert">' +
                        data.message +
                        '<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>';
                    form.reset();
                } else {
                    let errorHtml = '<div class="alert alert-danger alert-dismissible fade show" role="alert">' +
                        '<strong>Error:</strong> ' + (data.message || 'An error occurred') +
                        '<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>';
                    
                    if (data.errors) {
                        errorHtml += '<ul class="mb-0 mt-2">';
                        for (const [field, message] of Object.entries(data.errors)) {
                            errorHtml += '<li>' + message + '</li>';
                            const input = form.querySelector('[name="' + field + '"]');
                            if (input) {
                                input.classList.add('is-invalid');
                            }
                        }
                        errorHtml += '</ul>';
                    }
                    
                    alertDiv.innerHTML = errorHtml;
                }
            } catch (error) {
                alertDiv.innerHTML = '<div class="alert alert-danger alert-dismissible fade show" role="alert">' +
                    'An error occurred. Please try again later.' +
                    '<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>';
            } finally {
                submitBtn.disabled = false;
                spinner.classList.add('d-none');
            }
        });
        
        // Clear validation on input
        form.querySelectorAll('input').forEach(input => {
            input.addEventListener('input', function() {
                this.classList.remove('is-invalid');
            });
        });
    }
})();
</script>




