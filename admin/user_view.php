<?php
/**
 * Admin User Details View Page
 * Display comprehensive user information
 */

// Start session and load config/functions BEFORE any output
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require __DIR__ . '/../app/config.php';
require_once __DIR__ . '/../app/functions.php';

// Check admin authentication
if (empty($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
    header('Location: ' . app_url('admin/login'));
    exit;
}

// Get user ID
$userId = intval($_GET['id'] ?? 0);

if ($userId <= 0) {
    $_SESSION['flash_message'] = 'Invalid user ID';
    $_SESSION['flash_type'] = 'danger';
    header('Location: ' . app_url('admin/users'));
    exit;
}

try {
    $db = db();
    
    // Initialize defaults
    $user = null;
    $userStats = ['listings_count' => 0, 'bookings_count' => 0, 'visit_bookings_count' => 0, 'referrals_count' => 0, 'total_spent' => 0, 'kyc_count' => 0];
    $listings = [];
    $bookings = [];
    $visitBookings = [];
    $kycDocuments = [];
    $referrals = [];
    $payments = [];
    
    // Get user basic information
    $user = $db->fetchOne(
        "SELECT u.*, 
                (SELECT name FROM users WHERE id = u.referred_by) as referred_by_name
         FROM users u
         WHERE u.id = ?",
        [$userId]
    );
    
    if (!$user) {
        $_SESSION['flash_message'] = 'User not found';
        $_SESSION['flash_type'] = 'danger';
        header('Location: ' . app_url('admin/users'));
        exit;
    }
    
    // Get user statistics with error handling for each query
    try {
        $userStats['listings_count'] = $db->fetchValue("SELECT COUNT(*) FROM listings WHERE owner_name = ?", [$user['name']]) ?: 0;
    } catch (Exception $e) {
        error_log("Error fetching listings_count: " . $e->getMessage());
    }
    
    try {
        $userStats['bookings_count'] = $db->fetchValue("SELECT COUNT(*) FROM bookings WHERE user_id = ?", [$userId]) ?: 0;
    } catch (Exception $e) {
        error_log("Error fetching bookings_count: " . $e->getMessage());
    }
    
    try {
        $userStats['visit_bookings_count'] = $db->fetchValue("SELECT COUNT(*) FROM visit_bookings WHERE user_id = ?", [$userId]) ?: 0;
    } catch (Exception $e) {
        error_log("Error fetching visit_bookings_count: " . $e->getMessage());
    }
    
    try {
        $userStats['referrals_count'] = $db->fetchValue("SELECT COUNT(*) FROM referrals WHERE referrer_id = ?", [$userId]) ?: 0;
    } catch (Exception $e) {
        error_log("Error fetching referrals_count: " . $e->getMessage());
    }
    
    try {
        $userStats['total_spent'] = $db->fetchValue("SELECT COALESCE(SUM(p.amount), 0) FROM payments p INNER JOIN bookings b ON p.booking_id = b.id WHERE b.user_id = ? AND p.status = 'success'", [$userId]) ?: 0;
    } catch (Exception $e) {
        error_log("Error fetching total_spent: " . $e->getMessage());
    }
    
    try {
        $userStats['kyc_count'] = $db->fetchValue("SELECT COUNT(*) FROM user_kyc WHERE user_id = ?", [$userId]) ?: 0;
    } catch (Exception $e) {
        error_log("Error fetching kyc_count: " . $e->getMessage());
    }
    
    // Get user listings
    try {
        $listings = $db->fetchAll(
            "SELECT l.id, l.title, l.status, l.created_at,
                    loc.city, loc.pin_code,
                    (SELECT MIN(rent_per_month) FROM room_configurations WHERE listing_id = l.id) as min_rent,
                    (SELECT MAX(rent_per_month) FROM room_configurations WHERE listing_id = l.id) as max_rent
             FROM listings l
             LEFT JOIN listing_locations loc ON l.id = loc.listing_id
             WHERE l.owner_name = ?
             ORDER BY l.created_at DESC
             LIMIT 20",
            [$user['name']]
        ) ?: [];
    } catch (Exception $e) {
        error_log("Error fetching listings: " . $e->getMessage());
        $listings = [];
    }
    
    // Get user bookings
    try {
        $bookings = $db->fetchAll(
            "SELECT b.id, b.booking_start_date, b.total_amount, b.status, b.created_at,
                    l.title as listing_title,
                    loc.city as listing_city,
                    rc.room_type,
                    (SELECT p.status FROM payments p WHERE p.booking_id = b.id ORDER BY p.created_at DESC LIMIT 1) as payment_status,
                    (SELECT p.amount FROM payments p WHERE p.booking_id = b.id ORDER BY p.created_at DESC LIMIT 1) as payment_amount,
                    (SELECT i.invoice_number FROM invoices i WHERE i.booking_id = b.id ORDER BY i.created_at DESC LIMIT 1) as invoice_number
             FROM bookings b
             LEFT JOIN listings l ON b.listing_id = l.id
             LEFT JOIN listing_locations loc ON l.id = loc.listing_id
             LEFT JOIN room_configurations rc ON b.room_config_id = rc.id
             WHERE b.user_id = ?
             ORDER BY b.created_at DESC
             LIMIT 20",
            [$userId]
        ) ?: [];
    } catch (Exception $e) {
        error_log("Error fetching bookings: " . $e->getMessage());
        $bookings = [];
    }
    
    // Get visit bookings
    try {
        $visitBookings = $db->fetchAll(
            "SELECT vb.id, vb.preferred_date, vb.preferred_time, vb.status, vb.created_at,
                    l.title as listing_title,
                    loc.city as listing_city
             FROM visit_bookings vb
             LEFT JOIN listings l ON vb.listing_id = l.id
             LEFT JOIN listing_locations loc ON l.id = loc.listing_id
             WHERE vb.user_id = ?
             ORDER BY vb.created_at DESC
             LIMIT 20",
            [$userId]
        ) ?: [];
    } catch (Exception $e) {
        error_log("Error fetching visit_bookings: " . $e->getMessage());
        $visitBookings = [];
    }
    
    // Get KYC documents
    try {
        $kycDocuments = $db->fetchAll(
            "SELECT kyc.*, 
                    (SELECT name FROM users WHERE id = kyc.verified_by) as verified_by_name
             FROM user_kyc kyc
             WHERE kyc.user_id = ?
             ORDER BY kyc.created_at DESC",
            [$userId]
        ) ?: [];
    } catch (Exception $e) {
        error_log("Error fetching kyc documents: " . $e->getMessage());
        $kycDocuments = [];
    }
    
    // Get referrals made by this user
    try {
        $referrals = $db->fetchAll(
            "SELECT r.*, 
                    u.name as referred_user_name, u.email as referred_user_email, u.created_at as referred_user_joined
             FROM referrals r
             LEFT JOIN users u ON r.referred_id = u.id
             WHERE r.referrer_id = ?
             ORDER BY r.created_at DESC",
            [$userId]
        ) ?: [];
    } catch (Exception $e) {
        error_log("Error fetching referrals: " . $e->getMessage());
        $referrals = [];
    }
    
    // Get payment history
    try {
        $payments = $db->fetchAll(
            "SELECT p.*, 
                    b.id as booking_id, b.status as booking_status,
                    l.title as listing_title
             FROM payments p
             INNER JOIN bookings b ON p.booking_id = b.id
             LEFT JOIN listings l ON b.listing_id = l.id
             WHERE b.user_id = ?
             ORDER BY p.created_at DESC
             LIMIT 20",
            [$userId]
        ) ?: [];
    } catch (Exception $e) {
        error_log("Error fetching payments: " . $e->getMessage());
        $payments = [];
    }
    
} catch (Exception $e) {
    error_log("Error loading user details: " . $e->getMessage());
    error_log("Stack trace: " . $e->getTraceAsString());
    $_SESSION['flash_message'] = 'Error loading user details: ' . (getenv('APP_DEBUG') === 'true' ? $e->getMessage() : 'Please try again later.');
    $_SESSION['flash_type'] = 'danger';
    header('Location: ' . app_url('admin/users'));
    exit;
}

$pageTitle = "User Details - " . htmlspecialchars($user['name']);
require __DIR__ . '/../app/includes/admin_header.php';

$flashMessage = getFlashMessage();
?>

<!-- Page Header -->
<div class="admin-page-header mb-4">
    <div class="d-flex justify-content-between align-items-center">
        <div>
            <h1 class="admin-page-title">User Details</h1>
            <p class="admin-page-subtitle text-muted">Comprehensive user information</p>
        </div>
        <div>
            <a href="<?= app_url('admin/users') ?>" class="btn btn-secondary">
                <i class="bi bi-arrow-left me-2"></i>Back to Users
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

<!-- User Profile Header -->
<div class="admin-card mb-4">
    <div class="admin-card-body">
        <div class="row align-items-center">
            <div class="col-md-auto">
                <?php
                $profileImageUrl = null;
                $hasProfileImage = false;
                if (!empty($user['profile_image']) && $user['profile_image'] !== null && trim($user['profile_image']) !== '') {
                    if (!empty($user['google_id'])) {
                        if (strpos($user['profile_image'], 'http://') === 0 || strpos($user['profile_image'], 'https://') === 0) {
                            $profileImageUrl = $user['profile_image'];
                        } else {
                            $profileImageUrl = app_url($user['profile_image']);
                        }
                    } else {
                        $profileImageUrl = app_url($user['profile_image']);
                    }
                    $hasProfileImage = true;
                }
                ?>
                <div style="width: 120px; height: 120px; border-radius: 50%; overflow: hidden; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); display: flex; align-items: center; justify-content: center; flex-shrink: 0;">
                    <?php if ($hasProfileImage && !empty($profileImageUrl)): ?>
                        <img src="<?= htmlspecialchars($profileImageUrl) ?>" 
                             alt="<?= htmlspecialchars($user['name']) ?>" 
                             style="width: 100%; height: 100%; object-fit: cover;"
                             onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';">
                        <i class="bi bi-person-circle" style="display: none; font-size: 60px; color: white; position: absolute;"></i>
                    <?php else: ?>
                        <i class="bi bi-person-circle" style="font-size: 60px; color: white;"></i>
                    <?php endif; ?>
                </div>
            </div>
            <div class="col-md">
                <h3 class="mb-2"><?= htmlspecialchars($user['name']) ?></h3>
                <div class="mb-2">
                    <span class="badge bg-<?= $user['role'] === 'admin' ? 'danger' : 'primary' ?> me-2">
                        <?= ucfirst($user['role']) ?>
                    </span>
                    <?php if ($user['google_id']): ?>
                        <span class="badge bg-info">
                            <i class="bi bi-google"></i> Google Account
                        </span>
                    <?php endif; ?>
                </div>
                <div class="text-muted">
                    <div><i class="bi bi-envelope me-2"></i><?= htmlspecialchars($user['email']) ?></div>
                    <?php if ($user['phone']): ?>
                        <div><i class="bi bi-telephone me-2"></i><?= htmlspecialchars($user['phone']) ?></div>
                    <?php endif; ?>
                    <div><i class="bi bi-calendar me-2"></i>Joined <?= formatDate($user['created_at'], 'F d, Y') ?></div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Statistics Cards -->
<div class="row g-3 mb-4">
    <div class="col-xl-3 col-md-6">
        <div class="admin-stat-card">
            <div class="admin-stat-card-icon" style="background: linear-gradient(135deg, #8B6BD1 0%, #6F55B2 100%);">
                <i class="bi bi-building"></i>
            </div>
            <div class="admin-stat-card-content">
                <div class="admin-stat-card-label">Listings</div>
                <div class="admin-stat-card-value"><?= number_format($userStats['listings_count']) ?></div>
            </div>
        </div>
    </div>
    <div class="col-xl-3 col-md-6">
        <div class="admin-stat-card">
            <div class="admin-stat-card-icon" style="background: linear-gradient(135deg, #9B7BE1 0%, #7F65C2 100%);">
                <i class="bi bi-calendar-check"></i>
            </div>
            <div class="admin-stat-card-content">
                <div class="admin-stat-card-label">Bookings</div>
                <div class="admin-stat-card-value"><?= number_format($userStats['bookings_count']) ?></div>
            </div>
        </div>
    </div>
    <div class="col-xl-3 col-md-6">
        <div class="admin-stat-card">
            <div class="admin-stat-card-icon" style="background: linear-gradient(135deg, #AB8BF1 0%, #8F75D2 100%);">
                <i class="bi bi-currency-rupee"></i>
            </div>
            <div class="admin-stat-card-content">
                <div class="admin-stat-card-label">Total Spent</div>
                <div class="admin-stat-card-value">₹<?= number_format($userStats['total_spent']) ?></div>
            </div>
        </div>
    </div>
    <div class="col-xl-3 col-md-6">
        <div class="admin-stat-card">
            <div class="admin-stat-card-icon" style="background: linear-gradient(135deg, #BB9BFF 0%, #9F85E2 100%);">
                <i class="bi bi-gift"></i>
            </div>
            <div class="admin-stat-card-content">
                <div class="admin-stat-card-label">Referrals</div>
                <div class="admin-stat-card-value"><?= number_format($userStats['referrals_count']) ?></div>
            </div>
        </div>
    </div>
</div>

<!-- Main Content Tabs -->
<style>
.admin-tabs {
    border-bottom: 2px solid rgba(139, 107, 209, 0.12);
    padding: 0;
    margin: 0;
}

.admin-tabs .nav-item {
    margin-bottom: -2px;
}

.admin-tabs .nav-link {
    color: var(--admin-text-muted, #757095);
    border: none;
    border-bottom: 3px solid transparent;
    padding: 1rem 1.5rem;
    font-weight: 500;
    transition: all 0.3s ease;
    background: transparent;
    border-radius: 0;
}

.admin-tabs .nav-link:hover {
    color: var(--admin-primary, #8B6BD1);
    background: rgba(139, 107, 209, 0.05);
    border-bottom-color: rgba(139, 107, 209, 0.3);
}

.admin-tabs .nav-link.active {
    color: var(--admin-primary, #8B6BD1);
    background: transparent;
    border-bottom-color: var(--admin-primary, #8B6BD1);
    font-weight: 600;
}

.admin-tabs .nav-link i {
    margin-right: 0.5rem;
}

.tab-content {
    padding: 2rem 0 0 0;
}
</style>

<div class="admin-card mb-4">
    <ul class="nav nav-tabs admin-tabs" role="tablist">
        <li class="nav-item" role="presentation">
            <button class="nav-link active" id="profile-tab" data-bs-toggle="tab" data-bs-target="#profile" type="button" role="tab">
                <i class="bi bi-person me-2"></i>Profile
            </button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link" id="listings-tab" data-bs-toggle="tab" data-bs-target="#listings" type="button" role="tab">
                <i class="bi bi-building me-2"></i>Listings (<?= count($listings) ?>)
            </button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link" id="bookings-tab" data-bs-toggle="tab" data-bs-target="#bookings" type="button" role="tab">
                <i class="bi bi-calendar-check me-2"></i>Bookings (<?= count($bookings) ?>)
            </button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link" id="visits-tab" data-bs-toggle="tab" data-bs-target="#visits" type="button" role="tab">
                <i class="bi bi-calendar-event me-2"></i>Visit Bookings (<?= count($visitBookings) ?>)
            </button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link" id="kyc-tab" data-bs-toggle="tab" data-bs-target="#kyc" type="button" role="tab">
                <i class="bi bi-shield-check me-2"></i>KYC (<?= count($kycDocuments) ?>)
            </button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link" id="referrals-tab" data-bs-toggle="tab" data-bs-target="#referrals" type="button" role="tab">
                <i class="bi bi-gift me-2"></i>Referrals (<?= count($referrals) ?>)
            </button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link" id="payments-tab" data-bs-toggle="tab" data-bs-target="#payments" type="button" role="tab">
                <i class="bi bi-credit-card me-2"></i>Payments (<?= count($payments) ?>)
            </button>
        </li>
    </ul>
    
    <div class="tab-content" id="userTabsContent" style="padding: 2rem;">
        <!-- Profile Tab -->
        <div class="tab-pane fade show active" id="profile" role="tabpanel">
            <div class="row g-4">
                <div class="col-md-6">
                    <h5 class="mb-3">Basic Information</h5>
                    <table class="table table-borderless">
                        <tr>
                            <td class="text-muted" style="width: 40%;">User ID</td>
                            <td><strong>#<?= htmlspecialchars($user['id']) ?></strong></td>
                        </tr>
                        <tr>
                            <td class="text-muted">Name</td>
                            <td><?= htmlspecialchars($user['name']) ?></td>
                        </tr>
                        <tr>
                            <td class="text-muted">Email</td>
                            <td><?= htmlspecialchars($user['email']) ?></td>
                        </tr>
                        <tr>
                            <td class="text-muted">Phone</td>
                            <td><?= htmlspecialchars($user['phone'] ?: 'Not provided') ?></td>
                        </tr>
                        <tr>
                            <td class="text-muted">Gender</td>
                            <td><?= htmlspecialchars(ucfirst($user['gender'] ?: 'Not specified')) ?></td>
                        </tr>
                        <tr>
                            <td class="text-muted">Role</td>
                            <td>
                                <span class="badge bg-<?= $user['role'] === 'admin' ? 'danger' : 'primary' ?>">
                                    <?= ucfirst($user['role']) ?>
                                </span>
                            </td>
                        </tr>
                        <tr>
                            <td class="text-muted">Account Type</td>
                            <td>
                                <?php if ($user['google_id']): ?>
                                    <span class="badge bg-info"><i class="bi bi-google"></i> Google Account</span>
                                <?php else: ?>
                                    <span class="badge bg-secondary">Email/Password</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    </table>
                </div>
                
                <div class="col-md-6">
                    <h5 class="mb-3">Address Information</h5>
                    <table class="table table-borderless">
                        <tr>
                            <td class="text-muted" style="width: 40%;">Address</td>
                            <td><?= htmlspecialchars($user['address'] ?: 'Not provided') ?></td>
                        </tr>
                        <tr>
                            <td class="text-muted">City</td>
                            <td><?= htmlspecialchars($user['city'] ?: 'Not provided') ?></td>
                        </tr>
                        <tr>
                            <td class="text-muted">State</td>
                            <td><?= htmlspecialchars($user['state'] ?: 'Not provided') ?></td>
                        </tr>
                        <tr>
                            <td class="text-muted">Pincode</td>
                            <td><?= htmlspecialchars($user['pincode'] ?: 'Not provided') ?></td>
                        </tr>
                    </table>
                </div>
                
                <div class="col-md-6">
                    <h5 class="mb-3">Referral Information</h5>
                    <table class="table table-borderless">
                        <tr>
                            <td class="text-muted" style="width: 40%;">Referral Code</td>
                            <td>
                                <?php if ($user['referral_code']): ?>
                                    <code><?= htmlspecialchars($user['referral_code']) ?></code>
                                <?php else: ?>
                                    <span class="text-muted">Not generated</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <tr>
                            <td class="text-muted">Referred By</td>
                            <td>
                                <?php if ($user['referred_by'] && $user['referred_by_name']): ?>
                                    <a href="<?= app_url('admin/users?id=' . $user['referred_by']) ?>">
                                        <?= htmlspecialchars($user['referred_by_name']) ?>
                                    </a>
                                <?php else: ?>
                                    <span class="text-muted">Not referred</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    </table>
                </div>
                
                <div class="col-md-6">
                    <h5 class="mb-3">Account Information</h5>
                    <table class="table table-borderless">
                        <tr>
                            <td class="text-muted" style="width: 40%;">Created At</td>
                            <td><?= formatDate($user['created_at'], 'F d, Y h:i A') ?></td>
                        </tr>
                        <tr>
                            <td class="text-muted">Last Updated</td>
                            <td><?= formatDate($user['updated_at'], 'F d, Y h:i A') ?></td>
                        </tr>
                        <tr>
                            <td class="text-muted">Account Age</td>
                            <td><?= timeAgo($user['created_at']) ?></td>
                        </tr>
                    </table>
                </div>
            </div>
        </div>
        
        <!-- Listings Tab -->
        <div class="tab-pane fade" id="listings" role="tabpanel">
            <?php if (empty($listings)): ?>
                <div class="text-center py-5 text-muted">
                    <i class="bi bi-building fs-1 d-block mb-2"></i>
                    <p>No listings found</p>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Title</th>
                                <th>Location</th>
                                <th>Price Range</th>
                                <th>Status</th>
                                <th>Created</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($listings as $listing): ?>
                                <tr>
                                    <td><?= htmlspecialchars($listing['id']) ?></td>
                                    <td><?= htmlspecialchars($listing['title']) ?></td>
                                    <td>
                                        <?= htmlspecialchars($listing['city'] ?: 'N/A') ?>
                                        <?php if ($listing['pin_code']): ?>
                                            - <?= htmlspecialchars($listing['pin_code']) ?>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($listing['min_rent'] && $listing['max_rent']): ?>
                                            ₹<?= number_format($listing['min_rent']) ?> - ₹<?= number_format($listing['max_rent']) ?>
                                        <?php else: ?>
                                            <span class="text-muted">N/A</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php
                                        $statusColors = ['draft' => 'warning', 'active' => 'success', 'inactive' => 'secondary'];
                                        $statusColor = $statusColors[$listing['status']] ?? 'secondary';
                                        ?>
                                        <span class="badge bg-<?= $statusColor ?>"><?= ucfirst($listing['status']) ?></span>
                                    </td>
                                    <td><?= formatDate($listing['created_at'], 'd M Y') ?></td>
                                    <td>
                                        <a href="<?= app_url('admin/listings/view?id=' . $listing['id']) ?>" class="btn btn-sm btn-outline-primary">
                                            <i class="bi bi-eye"></i>
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
        
        <!-- Bookings Tab -->
        <div class="tab-pane fade" id="bookings" role="tabpanel">
            <?php if (empty($bookings)): ?>
                <div class="text-center py-5 text-muted">
                    <i class="bi bi-calendar-check fs-1 d-block mb-2"></i>
                    <p>No bookings found</p>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Property</th>
                                <th>Room Type</th>
                                <th>Start Date</th>
                                <th>Amount</th>
                                <th>Status</th>
                                <th>Payment</th>
                                <th>Invoice</th>
                                <th>Created</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($bookings as $booking): ?>
                                <tr>
                                    <td>#<?= htmlspecialchars($booking['id']) ?></td>
                                    <td><?= htmlspecialchars($booking['listing_title'] ?: 'N/A') ?></td>
                                    <td><?= htmlspecialchars(ucfirst(str_replace(' sharing', '', $booking['room_type'] ?: 'N/A'))) ?></td>
                                    <td><?= formatDate($booking['booking_start_date'], 'd M Y') ?></td>
                                    <td>₹<?= number_format($booking['total_amount']) ?></td>
                                    <td>
                                        <?php
                                        $statusColors = ['pending' => 'warning', 'confirmed' => 'success', 'cancelled' => 'danger', 'completed' => 'info'];
                                        $statusColor = $statusColors[$booking['status']] ?? 'secondary';
                                        ?>
                                        <span class="badge bg-<?= $statusColor ?>"><?= ucfirst($booking['status']) ?></span>
                                    </td>
                                    <td>
                                        <?php if ($booking['payment_status']): ?>
                                            <?php
                                            $paymentColors = ['success' => 'success', 'initiated' => 'warning', 'failed' => 'danger'];
                                            $paymentColor = $paymentColors[$booking['payment_status']] ?? 'secondary';
                                            ?>
                                            <span class="badge bg-<?= $paymentColor ?>"><?= ucfirst($booking['payment_status']) ?></span>
                                        <?php else: ?>
                                            <span class="text-muted">-</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($booking['invoice_number']): ?>
                                            <code><?= htmlspecialchars($booking['invoice_number']) ?></code>
                                        <?php else: ?>
                                            <span class="text-muted">-</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?= formatDate($booking['created_at'], 'd M Y') ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
        
        <!-- Visit Bookings Tab -->
        <div class="tab-pane fade" id="visits" role="tabpanel">
            <?php if (empty($visitBookings)): ?>
                <div class="text-center py-5 text-muted">
                    <i class="bi bi-calendar-event fs-1 d-block mb-2"></i>
                    <p>No visit bookings found</p>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Property</th>
                                <th>Visit Date</th>
                                <th>Visit Time</th>
                                <th>Status</th>
                                <th>Created</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($visitBookings as $visit): ?>
                                <tr>
                                    <td>#<?= htmlspecialchars($visit['id']) ?></td>
                                    <td><?= htmlspecialchars($visit['listing_title'] ?: 'N/A') ?></td>
                                    <td><?= formatDate($visit['preferred_date'], 'd M Y') ?></td>
                                    <td><?= date('h:i A', strtotime($visit['preferred_time'])) ?></td>
                                    <td>
                                        <?php
                                        $statusColors = ['pending' => 'warning', 'confirmed' => 'info', 'completed' => 'success', 'cancelled' => 'danger'];
                                        $statusColor = $statusColors[$visit['status']] ?? 'secondary';
                                        ?>
                                        <span class="badge bg-<?= $statusColor ?>"><?= ucfirst($visit['status']) ?></span>
                                    </td>
                                    <td><?= formatDate($visit['created_at'], 'd M Y') ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
        
        <!-- KYC Tab -->
        <div class="tab-pane fade" id="kyc" role="tabpanel">
            <?php if (empty($kycDocuments)): ?>
                <div class="text-center py-5 text-muted">
                    <i class="bi bi-shield-check fs-1 d-block mb-2"></i>
                    <p>No KYC documents found</p>
                </div>
            <?php else: ?>
                <div class="row g-3">
                    <?php foreach ($kycDocuments as $kyc): ?>
                        <div class="col-md-6">
                            <div class="card">
                                <div class="card-body">
                                    <h6 class="card-title"><?= ucfirst(str_replace('_', ' ', $kyc['document_type'])) ?></h6>
                                    <p class="card-text small text-muted mb-2">Document Number: <code><?= htmlspecialchars($kyc['document_number']) ?></code></p>
                                    <p class="card-text small">
                                        Status: 
                                        <?php
                                        $kycStatusColors = ['pending' => 'warning', 'verified' => 'success', 'rejected' => 'danger'];
                                        $kycStatusColor = $kycStatusColors[$kyc['status']] ?? 'secondary';
                                        ?>
                                        <span class="badge bg-<?= $kycStatusColor ?>"><?= ucfirst($kyc['status']) ?></span>
                                    </p>
                                    <?php if ($kyc['verified_by_name']): ?>
                                        <p class="card-text small text-muted">Verified by: <?= htmlspecialchars($kyc['verified_by_name']) ?></p>
                                    <?php endif; ?>
                                    <?php if ($kyc['verified_at']): ?>
                                        <p class="card-text small text-muted">Verified at: <?= formatDate($kyc['verified_at'], 'd M Y h:i A') ?></p>
                                    <?php endif; ?>
                                    <?php if ($kyc['rejection_reason']): ?>
                                        <p class="card-text small text-danger">Reason: <?= htmlspecialchars($kyc['rejection_reason']) ?></p>
                                    <?php endif; ?>
                                    <div class="mt-2">
                                        <?php if ($kyc['document_front']): ?>
                                            <a href="<?= app_url($kyc['document_front']) ?>" target="_blank" class="btn btn-sm btn-outline-primary me-2">
                                                <i class="bi bi-image"></i> Front
                                            </a>
                                        <?php endif; ?>
                                        <?php if ($kyc['document_back']): ?>
                                            <a href="<?= app_url($kyc['document_back']) ?>" target="_blank" class="btn btn-sm btn-outline-primary">
                                                <i class="bi bi-image"></i> Back
                                            </a>
                                        <?php endif; ?>
                                    </div>
                                    <p class="card-text small text-muted mt-2">Submitted: <?= formatDate($kyc['created_at'], 'd M Y') ?></p>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
        
        <!-- Referrals Tab -->
        <div class="tab-pane fade" id="referrals" role="tabpanel">
            <?php if (empty($referrals)): ?>
                <div class="text-center py-5 text-muted">
                    <i class="bi bi-gift fs-1 d-block mb-2"></i>
                    <p>No referrals found</p>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>Referred User</th>
                                <th>Email</th>
                                <th>Status</th>
                                <th>Reward</th>
                                <th>Referred Date</th>
                                <th>Joined Date</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($referrals as $referral): ?>
                                <tr>
                                    <td><?= htmlspecialchars($referral['referred_user_name'] ?: 'N/A') ?></td>
                                    <td><?= htmlspecialchars($referral['referred_user_email'] ?: 'N/A') ?></td>
                                    <td>
                                        <?php
                                        $refStatusColors = ['pending' => 'warning', 'credited' => 'success'];
                                        $refStatusColor = $refStatusColors[$referral['status']] ?? 'secondary';
                                        ?>
                                        <span class="badge bg-<?= $refStatusColor ?>"><?= ucfirst($referral['status']) ?></span>
                                    </td>
                                    <td>₹<?= number_format($referral['reward_amount']) ?></td>
                                    <td><?= formatDate($referral['created_at'], 'd M Y') ?></td>
                                    <td>
                                        <?php if ($referral['referred_user_joined']): ?>
                                            <?= formatDate($referral['referred_user_joined'], 'd M Y') ?>
                                        <?php else: ?>
                                            <span class="text-muted">-</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
        
        <!-- Payments Tab -->
        <div class="tab-pane fade" id="payments" role="tabpanel">
            <?php if (empty($payments)): ?>
                <div class="text-center py-5 text-muted">
                    <i class="bi bi-credit-card fs-1 d-block mb-2"></i>
                    <p>No payments found</p>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>Payment ID</th>
                                <th>Booking ID</th>
                                <th>Property</th>
                                <th>Amount</th>
                                <th>Status</th>
                                <th>Provider</th>
                                <th>Date</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($payments as $payment): ?>
                                <tr>
                                    <td>#<?= htmlspecialchars($payment['id']) ?></td>
                                    <td>#<?= htmlspecialchars($payment['booking_id']) ?></td>
                                    <td><?= htmlspecialchars($payment['listing_title'] ?: 'N/A') ?></td>
                                    <td>₹<?= number_format($payment['amount']) ?></td>
                                    <td>
                                        <?php
                                        $paymentColors = ['success' => 'success', 'initiated' => 'warning', 'failed' => 'danger'];
                                        $paymentColor = $paymentColors[$payment['status']] ?? 'secondary';
                                        ?>
                                        <span class="badge bg-<?= $paymentColor ?>"><?= ucfirst($payment['status']) ?></span>
                                    </td>
                                    <td><?= htmlspecialchars(ucfirst($payment['provider'] ?: 'N/A')) ?></td>
                                    <td><?= formatDate($payment['created_at'], 'd M Y h:i A') ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php require __DIR__ . '/../app/includes/admin_footer.php'; ?>

