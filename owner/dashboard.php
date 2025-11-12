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
    
    // Recalculate availability for each room to ensure accuracy (handles old data)
    foreach ($rooms as $room) {
        recalculateAvailableBeds($room['id']);
    }
    
    // Reload rooms with updated availability
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
    
    // Calculate bed information for each room (use real-time calculation, not cached column)
    foreach ($rooms as &$room) {
        $room['beds_per_room'] = getBedsPerRoom($room['room_type']);
        $room['total_beds'] = calculateTotalBeds($room['total_rooms'], $room['room_type']);
        
        // Calculate available beds from actual bookings (real-time, ensures accuracy)
        $bookedBeds = (int)($room['booked_beds'] ?? 0);
        $room['available_beds'] = max(0, $room['total_beds'] - $bookedBeds);
        
        // Also update the available_rooms column to keep it in sync
        $room['available_rooms'] = $room['available_beds'];
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
    
    <!-- Quick Actions -->
    <div class="row g-3 mb-4">
        <div class="col-md-12">
            <div class="card border-primary">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h5 class="card-title mb-1"><?= htmlspecialchars($listing['title']) ?></h5>
                            <p class="text-muted small mb-0">
                                <span class="badge bg-<?= $listing['status'] === 'active' ? 'success' : ($listing['status'] === 'draft' ? 'warning' : 'secondary') ?>">
                                    <?= ucfirst($listing['status']) ?>
                                </span>
                                <?php if ($location): ?>
                                    <span class="ms-2">
                                        <i class="bi bi-geo-alt"></i> <?= htmlspecialchars($location['city'] ?? 'N/A') ?>
                                        <?= $location['pin_code'] ? ' - ' . htmlspecialchars($location['pin_code']) : '' ?>
                                    </span>
                                <?php endif; ?>
                            </p>
                        </div>
                        <a href="<?= app_url('owner/listings/edit') ?>" class="btn btn-primary">
                            <i class="bi bi-pencil"></i> Update Availability
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Statistics Cards -->
    <div class="row g-3 mb-4">
        <div class="col-md-3">
            <div class="card text-center">
                <div class="card-body">
                    <h3 class="text-primary mb-0"><?= count($rooms) ?></h3>
                    <small class="text-muted">Room Types</small>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card text-center">
                <div class="card-body">
                    <h3 class="text-success mb-0"><?= $listing['booking_count'] ?></h3>
                    <small class="text-muted">Total Bookings</small>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card text-center">
                <div class="card-body">
                    <h3 class="text-info mb-0">
                        <?php
                        $totalBeds = array_sum(array_column($rooms, 'total_beds'));
                        echo $totalBeds;
                        ?>
                    </h3>
                    <small class="text-muted">Total Beds</small>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card text-center">
                <div class="card-body">
                    <h3 class="text-warning mb-0">
                        <?php
                        // Calculate available beds from actual bookings (real-time, not from cached column)
                        $totalAvailableBeds = 0;
                        foreach ($rooms as $room) {
                            // Use the calculated available_beds which is based on actual bookings
                            $totalAvailableBeds += (int)($room['available_beds'] ?? 0);
                        }
                        echo $totalAvailableBeds;
                        ?>
                    </h3>
                    <small class="text-muted">Available Beds</small>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Property Details -->
    <div class="row g-3 mb-4">
        <div class="col-md-6">
            <div class="card">
                <div class="card-header">
                    <h6 class="mb-0"><i class="bi bi-info-circle me-2"></i>Property Information</h6>
                </div>
                <div class="card-body">
                    <p class="mb-2"><strong>Description:</strong></p>
                    <p class="text-muted small"><?= nl2br(htmlspecialchars($listing['description'] ?: 'N/A')) ?></p>
                    <hr>
                    <p class="mb-1"><strong>Available For:</strong> <?= ucfirst($listing['available_for'] ?? 'N/A') ?></p>
                    <p class="mb-1"><strong>Gender Allowed:</strong> <?= ucfirst($listing['gender_allowed'] ?? 'N/A') ?></p>
                    <p class="mb-1"><strong>Preferred Tenants:</strong> <?= ucfirst(str_replace('_', ' ', $listing['preferred_tenants'] ?? 'N/A')) ?></p>
                    <p class="mb-1"><strong>Security Deposit:</strong> <?= htmlspecialchars($listing['security_deposit_amount'] ?? 'N/A') ?></p>
                    <p class="mb-0"><strong>Notice Period:</strong> <?= $listing['notice_period'] ? $listing['notice_period'] . ' days' : 'N/A' ?></p>
                </div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="card">
                <div class="card-header">
                    <h6 class="mb-0"><i class="bi bi-geo-alt me-2"></i>Location Details</h6>
                </div>
                <div class="card-body">
                    <?php if ($location): ?>
                        <p class="mb-2"><strong>Address:</strong></p>
                        <p class="text-muted small"><?= nl2br(htmlspecialchars($location['complete_address'] ?: 'N/A')) ?></p>
                        <hr>
                        <p class="mb-1"><strong>City:</strong> <?= htmlspecialchars($location['city'] ?? 'N/A') ?></p>
                        <p class="mb-1"><strong>Pin Code:</strong> <?= htmlspecialchars($location['pin_code'] ?? 'N/A') ?></p>
                        <?php if ($location['google_maps_link']): ?>
                            <a href="<?= htmlspecialchars($location['google_maps_link']) ?>" target="_blank" class="btn btn-sm btn-outline-primary mt-2">
                                <i class="bi bi-map"></i> View on Maps
                            </a>
                        <?php endif; ?>
                    <?php else: ?>
                        <p class="text-muted">Location information not available</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    
    <?php if ($additionalInfo): ?>
    <div class="row g-3 mb-4">
        <div class="col-md-12">
            <div class="card">
                <div class="card-header">
                    <h6 class="mb-0"><i class="bi bi-list-check me-2"></i>Additional Information</h6>
                </div>
                <div class="card-body">
                    <div class="row">
                        <?php if ($additionalInfo['electricity_charges']): ?>
                            <div class="col-md-4 mb-2">
                                <strong>Electricity:</strong> <?= ucfirst(htmlspecialchars($additionalInfo['electricity_charges'])) ?>
                            </div>
                        <?php endif; ?>
                        <?php if ($additionalInfo['food_availability']): ?>
                            <div class="col-md-4 mb-2">
                                <strong>Food:</strong> <?= ucfirst(htmlspecialchars($additionalInfo['food_availability'])) ?>
                            </div>
                        <?php endif; ?>
                        <?php if ($additionalInfo['gate_closing_time']): ?>
                            <div class="col-md-4 mb-2">
                                <strong>Gate Closing:</strong> <?= htmlspecialchars($additionalInfo['gate_closing_time']) ?>
                            </div>
                        <?php endif; ?>
                        <?php if ($additionalInfo['total_beds']): ?>
                            <div class="col-md-4 mb-2">
                                <strong>Total Beds:</strong> <?= htmlspecialchars($additionalInfo['total_beds']) ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>
    
    <?php if (!empty($amenities)): ?>
    <div class="row g-3 mb-4">
        <div class="col-md-12">
            <div class="card">
                <div class="card-header">
                    <h6 class="mb-0"><i class="bi bi-star me-2"></i>Amenities</h6>
                </div>
                <div class="card-body">
                    <?php foreach ($amenities as $amenity): ?>
                        <span class="badge bg-secondary me-1 mb-1"><?= htmlspecialchars($amenity['name']) ?></span>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>
    
    <?php if (!empty($houseRules)): ?>
    <div class="row g-3 mb-4">
        <div class="col-md-12">
            <div class="card">
                <div class="card-header">
                    <h6 class="mb-0"><i class="bi bi-shield-check me-2"></i>House Rules</h6>
                </div>
                <div class="card-body">
                    <ul class="list-unstyled mb-0">
                        <?php foreach ($houseRules as $rule): ?>
                            <li class="mb-1"><i class="bi bi-check-circle text-success me-2"></i><?= htmlspecialchars($rule['name']) ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>
    
    <!-- Room Availability -->
    <div class="card mb-4">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h6 class="mb-0"><i class="bi bi-door-open me-2"></i>Room Availability</h6>
            <a href="<?= app_url('owner/listings/edit') ?>" class="btn btn-sm btn-primary">
                <i class="bi bi-pencil"></i> Update
            </a>
        </div>
        <div class="card-body">
            <?php if (empty($rooms)): ?>
                <p class="text-muted">No rooms configured for this listing.</p>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>Room Type</th>
                                <th>Rent/Month</th>
                                <th>Total</th>
                                <th>Booked Beds</th>
                                <th>Available Beds</th>
                                <th>Status</th>
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
                                    <td>
                                        <?= htmlspecialchars(ucfirst(str_replace(' sharing', '', $room['room_type'] ?? 'N/A'))) ?>
                                        <br><small class="text-muted"><?= $room['beds_per_room'] ?> bed<?= $room['beds_per_room'] > 1 ? 's' : '' ?> per room</small>
                                    </td>
                                    <td>₹<?= number_format($room['rent_per_month'] ?? 0) ?></td>
                                    <td>
                                        <?= $room['total_rooms'] ?? 0 ?> room<?= ($room['total_rooms'] ?? 0) != 1 ? 's' : '' ?>
                                        <br><small class="text-muted"><?= $totalBeds ?> beds</small>
                                    </td>
                                    <td><?= $bookedBeds ?> bed<?= $bookedBeds != 1 ? 's' : '' ?></td>
                                    <td>
                                        <span class="badge bg-<?= $isAvailable ? 'success' : 'danger' ?>">
                                            <?= $availableBeds ?> bed<?= $availableBeds != 1 ? 's' : '' ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php if ($isAvailable): ?>
                                            <span class="text-success">Available</span>
                                        <?php else: ?>
                                            <span class="text-danger">Fully Booked</span>
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
    <div class="card">
        <div class="card-header">
            <h6 class="mb-0"><i class="bi bi-calendar-check me-2"></i>Recent Bookings</h6>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-sm">
                    <thead>
                        <tr>
                            <th>User</th>
                            <th>Start Date</th>
                            <th>Amount</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($recentBookings as $booking): ?>
                            <tr>
                                <td>
                                    <div><?= htmlspecialchars($booking['user_name'] ?: 'Unknown') ?></div>
                                    <small class="text-muted"><?= htmlspecialchars($booking['user_email'] ?? '') ?></small>
                                </td>
                                <td><?= $booking['booking_start_date'] ? date('d M Y', strtotime($booking['booking_start_date'])) : 'N/A' ?></td>
                                <td>₹<?= number_format($booking['total_amount'] ?? 0, 2) ?></td>
                                <td>
                                    <span class="badge bg-<?= $booking['status'] === 'confirmed' ? 'success' : ($booking['status'] === 'pending' ? 'warning' : 'secondary') ?>">
                                        <?= ucfirst($booking['status']) ?>
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

<?php require __DIR__ . '/../app/includes/owner_footer.php'; ?>

