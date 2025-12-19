<?php
/**
 * User Profile Page
 * Display and manage user profile information
 */

// Start session and load config/functions BEFORE any output
if (session_status() === PHP_SESSION_NONE) session_start();
require __DIR__ . '/../app/config.php';
require_once __DIR__ . '/../app/functions.php';

// Require login - MUST be before header output
if (!isLoggedIn()) {
    header('Location: ' . app_url('login'));
    exit;
}

$userId = getCurrentUserId();
$user = null;
$userStats = [];
$recentBookings = [];
$recentVisits = [];
$kycStatus = null;

try {
    $db = db();
    
    // Get user data
    $user = $db->fetchOne(
        "SELECT id, name, email, phone, gender, profile_image, address, city, state, pincode, 
                referral_code, created_at, updated_at, google_id, password_hash
         FROM users 
         WHERE id = ?",
        [$userId]
    );
    
    if (!$user) {
        header('Location: ' . app_url('login'));
        exit;
    }
    
    // Ensure profile_image is fetched correctly
    // If profile_image is missing from the result, fetch it separately like header does
    if (!isset($user['profile_image'])) {
        $profileImageFromDb = $db->fetchValue("SELECT profile_image FROM users WHERE id = ?", [$userId]);
        $user['profile_image'] = $profileImageFromDb;
    }
    
    // Fetch gender separately to ensure we get it
    $genderFromDb = $db->fetchValue("SELECT gender FROM users WHERE id = ?", [$userId]);
    if ($genderFromDb !== null && $genderFromDb !== '') {
        $user['gender'] = $genderFromDb;
    }
    
    // Get user statistics
    // Optimize visit booking stats with a single query
    $visitStats = $db->fetchOne(
        "SELECT 
            COUNT(*) as total_visits,
            COALESCE(SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END), 0) as pending_visits,
            COALESCE(SUM(CASE WHEN status = 'confirmed' THEN 1 ELSE 0 END), 0) as confirmed_visits,
            COALESCE(SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END), 0) as completed_visits,
            COALESCE(SUM(CASE WHEN status = 'cancelled' THEN 1 ELSE 0 END), 0) as cancelled_visits
         FROM visit_bookings 
         WHERE user_id = ?",
        [$userId]
    );
    
    // Ensure visitStats is an array even if query returns null/empty
    if (empty($visitStats) || !is_array($visitStats)) {
        $visitStats = [
            'total_visits' => 0,
            'pending_visits' => 0,
            'confirmed_visits' => 0,
            'completed_visits' => 0,
            'cancelled_visits' => 0
        ];
    }
    
    $userStats = [
        'total_bookings' => (int)$db->fetchValue("SELECT COUNT(*) FROM bookings WHERE user_id = ?", [$userId]) ?: 0,
        'confirmed_bookings' => (int)$db->fetchValue("SELECT COUNT(*) FROM bookings WHERE user_id = ? AND status = 'confirmed'", [$userId]) ?: 0,
        'pending_bookings' => (int)$db->fetchValue("SELECT COUNT(*) FROM bookings WHERE user_id = ? AND status = 'pending'", [$userId]) ?: 0,
        'total_visits' => (int)($visitStats['total_visits'] ?? 0),
        'pending_visits' => (int)($visitStats['pending_visits'] ?? 0),
        'confirmed_visits' => (int)($visitStats['confirmed_visits'] ?? 0),
        'completed_visits' => (int)($visitStats['completed_visits'] ?? 0),
        'cancelled_visits' => (int)($visitStats['cancelled_visits'] ?? 0),
        'total_referrals' => (int)$db->fetchValue("SELECT COUNT(*) FROM referrals WHERE referrer_id = ?", [$userId]) ?: 0,
        'total_rewards' => (float)$db->fetchValue("SELECT COALESCE(SUM(reward_amount), 0) FROM referrals WHERE referrer_id = ? AND status = 'credited'", [$userId]) ?: 0,
    ];
    
} catch (Exception $e) {
    error_log("Error loading profile data: " . $e->getMessage());
    // Set defaults on error instead of redirecting (to avoid header issues)
    $user = [
        'id' => $userId,
        'name' => $_SESSION['user_name'] ?? 'User',
        'email' => $_SESSION['user_email'] ?? '',
        'phone' => null,
        'gender' => null,
        'profile_image' => null,
        'address' => null,
        'city' => null,
        'state' => null,
        'pincode' => null,
        'referral_code' => $_SESSION['referral_code'] ?? null,
        'created_at' => date('Y-m-d H:i:s'),
        'updated_at' => date('Y-m-d H:i:s'),
        'google_id' => null
    ];
    // Initialize visit stats to 0 on error
    $visitStats = [
        'total_visits' => 0,
        'pending_visits' => 0,
        'confirmed_visits' => 0,
        'completed_visits' => 0,
        'cancelled_visits' => 0
    ];
    $userStats = [
        'total_bookings' => 0,
        'confirmed_bookings' => 0,
        'pending_bookings' => 0,
        'total_visits' => 0,
        'pending_visits' => 0,
        'confirmed_visits' => 0,
        'completed_visits' => 0,
        'cancelled_visits' => 0,
        'total_referrals' => 0,
        'total_rewards' => 0.0
    ];
    $recentBookings = [];
    $recentVisits = [];
    $kycStatus = null;
}

if (!isset($jsVisitStats)) {
    $jsVisitStats = json_encode([
        'userId' => $userId ?? 0,
        'visitStats' => $visitStats ?? ['total_visits' => 0, 'pending_visits' => 0, 'confirmed_visits' => 0, 'completed_visits' => 0, 'cancelled_visits' => 0],
        'userStats' => $userStats ?? []
    ], JSON_PRETTY_PRINT);
}

// Get recent bookings (last 5) - always fetch fresh data
try {
    if (!isset($db)) {
        $db = db();
    }
    $recentBookings = $db->fetchAll(
        "SELECT b.id, b.status, b.total_amount, b.created_at,
                l.title as listing_title, l.cover_image,
                loc.city as listing_city,
                i.id as invoice_id, i.invoice_number
         FROM bookings b
         LEFT JOIN listings l ON b.listing_id = l.id
         LEFT JOIN listing_locations loc ON l.id = loc.listing_id
         LEFT JOIN invoices i ON b.id = i.booking_id
         WHERE b.user_id = ?
         ORDER BY b.created_at DESC
         LIMIT 5",
        [$userId]
    );
} catch (Exception $e) {
    $recentBookings = [];
}

// Get recent visits (last 5) - always fetch fresh data
try {
    if (!isset($db)) {
        $db = db();
    }
    $recentVisits = $db->fetchAll(
        "SELECT vb.id, vb.preferred_date as visit_date, vb.status, vb.created_at,
                l.title as listing_title, l.cover_image,
                loc.city as listing_city
         FROM visit_bookings vb
         LEFT JOIN listings l ON vb.listing_id = l.id
         LEFT JOIN listing_locations loc ON l.id = loc.listing_id
         WHERE vb.user_id = ?
         ORDER BY vb.created_at DESC
         LIMIT 5",
        [$userId]
    );
} catch (Exception $e) {
    $recentVisits = [];
}

// Get KYC status - always fetch fresh
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
} catch (Exception $e) {
    error_log("Error fetching KYC status in profile: " . $e->getMessage());
    $kycStatus = null;
}

// Ensure user data exists
if (!$user || empty($user['id'])) {
    // If still no user, redirect (but this should not happen after the catch block)
    if (session_status() === PHP_SESSION_ACTIVE) {
        header('Location: ' . app_url('login'));
        exit;
    }
}

// Preserve all important values before array_merge
$preservedGender = isset($user['gender']) ? $user['gender'] : null;
$preservedPhone = isset($user['phone']) ? $user['phone'] : null;
$preservedAddress = isset($user['address']) ? $user['address'] : null;
$preservedCity = isset($user['city']) ? $user['city'] : null;
$preservedState = isset($user['state']) ? $user['state'] : null;
$preservedPincode = isset($user['pincode']) ? $user['pincode'] : null;

// Set default values for user data to prevent undefined key warnings
$user = array_merge([
    'name' => '',
    'email' => '',
    'phone' => null,
    'gender' => null,
    'profile_image' => null,
    'address' => null,
    'city' => null,
    'state' => null,
    'pincode' => null,
    'referral_code' => null,
    'created_at' => date('Y-m-d H:i:s'),
    'updated_at' => date('Y-m-d H:i:s'),
    'google_id' => null
], $user);

// Restore preserved values (array_merge should preserve them, but this ensures they're kept)
if ($preservedGender !== null && $preservedGender !== '') {
    $user['gender'] = $preservedGender;
}
if ($preservedPhone !== null) {
    $user['phone'] = $preservedPhone;
}
if ($preservedAddress !== null) {
    $user['address'] = $preservedAddress;
}
if ($preservedCity !== null) {
    $user['city'] = $preservedCity;
}
if ($preservedState !== null) {
    $user['state'] = $preservedState;
}
if ($preservedPincode !== null) {
    $user['pincode'] = $preservedPincode;
}

// Fetch all user fields again after merge to ensure we have the latest values
// Use the existing $db connection from the try block above
try {
    // Reuse the database connection - don't create a new one
    if (!isset($db)) {
        $db = db();
    }
    $userDataAfterMerge = $db->fetchOne(
        "SELECT phone, gender, address, city, state, pincode FROM users WHERE id = ?",
        [$userId]
    );
    if ($userDataAfterMerge) {
        // Update user array with fresh data from database
        if (isset($userDataAfterMerge['phone'])) {
            $user['phone'] = $userDataAfterMerge['phone'];
        }
        if (isset($userDataAfterMerge['gender']) && $userDataAfterMerge['gender'] !== null && $userDataAfterMerge['gender'] !== '') {
            $user['gender'] = $userDataAfterMerge['gender'];
        }
        if (isset($userDataAfterMerge['address'])) {
            $user['address'] = $userDataAfterMerge['address'];
        }
        if (isset($userDataAfterMerge['city'])) {
            $user['city'] = $userDataAfterMerge['city'];
        }
        if (isset($userDataAfterMerge['state'])) {
            $user['state'] = $userDataAfterMerge['state'];
        }
        if (isset($userDataAfterMerge['pincode'])) {
            $user['pincode'] = $userDataAfterMerge['pincode'];
        }
    }
} catch (Exception $e) {
    error_log("Error fetching user data after merge: " . $e->getMessage());
}

// Get profile image URL (after array_merge to ensure we have final user data)
// Fetch separately like header does to ensure we get the value
$profileImageUrl = null;
$hasProfileImage = false;

// Fetch profile image directly from database like header does
try {
    $db = db();
    $profileImage = $db->fetchValue("SELECT profile_image FROM users WHERE id = ?", [$userId]);
    
    if (!empty($profileImage) && $profileImage !== null && trim($profileImage) !== '') {
        // Handle both local paths and external URLs (like Google profile images)
        if (strpos($profileImage, 'http://') === 0 || strpos($profileImage, 'https://') === 0) {
            // External URL (e.g., Google profile image)
            $profileImageUrl = $profileImage;
        } else {
            // Local file path - use app_url() like header does
            $profileImageUrl = app_url($profileImage);
        }
        $hasProfileImage = true;
        
        // Also update user array for consistency
        $user['profile_image'] = $profileImage;
    }
} catch (Exception $e) {
    error_log("Error fetching profile image: " . $e->getMessage());
}

// Set default values for stats
$userStats = array_merge([
    'total_bookings' => 0,
    'confirmed_bookings' => 0,
    'pending_bookings' => 0,
    'total_visits' => 0,
    'pending_visits' => 0,
    'confirmed_visits' => 0,
    'completed_visits' => 0,
    'cancelled_visits' => 0,
    'total_referrals' => 0,
    'total_rewards' => 0.0
], $userStats);

// Now include header after all processing
$pageTitle = "My Profile";
$baseUrl = app_url('');
require __DIR__ . '/../app/includes/header.php';
?>

<div class="container-xxl py-5">
    <!-- Profile Header -->
    <div class="card pg mb-4">
        <div class="card-body p-4">
            <div class="row align-items-center">
                <div class="col-auto">
                    <div class="profile-avatar" style="width: 120px; height: 120px; border-radius: 50%; overflow: hidden; border: 4px solid var(--primary); background: linear-gradient(135deg, var(--primary) 0%, var(--primary-700) 100%); display: flex; align-items: center; justify-content: center; box-shadow: 0 4px 12px rgba(139, 107, 209, 0.2); position: relative;">
                        <?php if ($hasProfileImage && !empty($profileImageUrl)): ?>
                        <img src="<?= htmlspecialchars($profileImageUrl) ?>" 
                             alt="<?= htmlspecialchars($user['name'] ?: 'User') ?>" 
                             id="profilePageImage"
                             class="w-100 h-100" 
                             style="object-fit: cover; position: absolute; top: 0; left: 0; width: 100%; height: 100%; z-index: 2;"
                             onerror="this.style.display='none'; document.getElementById('profileIconPlaceholder').style.display='flex';">
                        <?php endif; ?>
                        <div class="profile-icon-placeholder" id="profileIconPlaceholder" style="display: <?= ($hasProfileImage && !empty($profileImageUrl)) ? 'none' : 'flex' ?>; align-items: center; justify-content: center; width: 100%; height: 100%; position: absolute; top: 0; left: 0; z-index: 1;">
                            <i class="bi bi-person-fill" style="font-size: 60px; color: white; opacity: 0.9;"></i>
                        </div>
                    </div>
                </div>
                <div class="col">
                    <h1 class="mb-2" style="color: var(--primary-700);"><?= htmlspecialchars($user['name']) ?></h1>
                    <p class="text-muted mb-2">
                        <i class="bi bi-envelope me-2"></i><?= htmlspecialchars($user['email']) ?>
                    </p>
                    <?php if (!empty($user['phone'])): ?>
                    <p class="text-muted mb-2">
                        <i class="bi bi-telephone me-2"></i><?= htmlspecialchars($user['phone']) ?>
                    </p>
                    <?php endif; ?>
                    <p class="text-muted mb-0 small">
                        <i class="bi bi-calendar me-1"></i>Member since <?= formatDate($user['created_at'], 'F Y') ?>
                    </p>
                </div>
                <div class="col-auto">
                    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#editProfileModal">
                        <i class="bi bi-pencil me-2"></i>Edit Profile
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Statistics Cards -->
    <div class="row g-3 mb-4">
        <div class="col-md-3 col-6">
            <div class="card pg" style="background: linear-gradient(135deg, var(--primary) 0%, var(--primary-700) 100%); color: white; border: none;">
                <div class="card-body text-center p-3">
                    <h4 class="mb-1 fw-bold"><?= number_format($userStats['total_bookings']) ?></h4>
                    <p class="mb-0 small opacity-90 text-white">Total Bookings</p>
                </div>
            </div>
        </div>
        <div class="col-md-3 col-6">
            <div class="card pg" style="background: linear-gradient(135deg, #43e97b 0%, #38f9d7 100%); color: white; border: none;">
                <div class="card-body text-center p-3">
                    <h4 class="mb-1 fw-bold"><?= number_format($userStats['confirmed_bookings']) ?></h4>
                    <p class="mb-0 small opacity-90 text-white">Confirmed</p>
                </div>
            </div>
        </div>
        <div class="col-md-3 col-6">
            <div class="card pg" style="background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%); color: white; border: none;">
                <div class="card-body text-center p-3">
                    <h4 class="mb-1 fw-bold"><?= number_format($userStats['total_visits']) ?></h4>
                    <p class="mb-0 small opacity-90 text-white">Property Visits</p>
                </div>
            </div>
        </div>
        <div class="col-md-3 col-6">
            <div class="card pg" style="background: linear-gradient(135deg, #fbbf24 0%, #f59e0b 100%); color: white; border: none;">
                <div class="card-body text-center p-3">
                    <h4 class="mb-1 fw-bold">₹<?= number_format($userStats['total_rewards']) ?></h4>
                    <p class="mb-0 small opacity-90 text-white">Referral Rewards</p>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-4">
        <!-- Left Column -->
        <div class="col-lg-8">
            <!-- Personal Information -->
            <div class="card pg mb-4">
                <div class="card-header profile-section-header">
                    <h5 class="mb-0 profile-section-title">
                        <i class="bi bi-person me-2"></i>Personal Information
                    </h5>
                </div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label text-muted small mb-1">Full Name</label>
                            <div class="fw-semibold"><?= htmlspecialchars($user['name']) ?></div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label text-muted small mb-1">Email</label>
                            <div><?= htmlspecialchars($user['email']) ?></div>
                        </div>
                        <?php if (!empty($user['phone'])): ?>
                        <div class="col-md-6">
                            <label class="form-label text-muted small mb-1">Phone</label>
                            <div><?= htmlspecialchars($user['phone']) ?></div>
                        </div>
                        <?php endif; ?>
                        <?php if (!empty($user['gender'])): ?>
                        <div class="col-md-6">
                            <label class="form-label text-muted small mb-1">Gender</label>
                            <div><?= ucfirst($user['gender']) ?></div>
                        </div>
                        <?php endif; ?>
                        <?php if (!empty($user['address'])): ?>
                        <div class="col-12">
                            <label class="form-label text-muted small mb-1">Address</label>
                            <div><?= nl2br(htmlspecialchars($user['address'])) ?></div>
                        </div>
                        <?php endif; ?>
                        <?php if (!empty($user['city']) || !empty($user['state']) || !empty($user['pincode'])): ?>
                        <div class="col-md-4">
                            <label class="form-label text-muted small mb-1">City</label>
                            <div><?= htmlspecialchars($user['city'] ?: 'N/A') ?></div>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label text-muted small mb-1">State</label>
                            <div><?= htmlspecialchars($user['state'] ?: 'N/A') ?></div>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label text-muted small mb-1">Pin Code</label>
                            <div><?= htmlspecialchars($user['pincode'] ?: 'N/A') ?></div>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <?php 
            // Only show password change section if user has a password (not Google-only account)
            $hasPassword = !empty($user['password_hash']);
            if ($hasPassword): 
            ?>
            <!-- Change Password -->
            <div class="card pg mb-4">
                <div class="card-header profile-section-header">
                    <h5 class="mb-0 profile-section-title">
                        <i class="bi bi-key me-2"></i>Change Password
                    </h5>
                </div>
                <div class="card-body">
                    <form id="changePasswordForm">
                        <div id="passwordAlert"></div>
                        
                        <div class="mb-3">
                            <label for="currentPassword" class="form-label">Current Password</label>
                            <input type="password" class="form-control" id="currentPassword" name="current_password" required>
                        </div>
                        
                        <div class="mb-3">
                            <label for="newPassword" class="form-label">New Password</label>
                            <input type="password" class="form-control" id="newPassword" name="new_password" required minlength="8">
                            <small class="text-muted">Must be at least 8 characters long</small>
                        </div>
                        
                        <div class="mb-3">
                            <label for="confirmNewPassword" class="form-label">Confirm New Password</label>
                            <input type="password" class="form-control" id="confirmNewPassword" name="confirm_new_password" required minlength="8">
                        </div>
                        
                        <button type="submit" class="btn btn-primary" id="changePasswordBtn">
                            <span id="changePasswordSpinner" class="spinner-border spinner-border-sm me-2 d-none"></span>
                            <i class="bi bi-key me-2"></i>Change Password
                        </button>
                    </form>
                </div>
            </div>
            <?php else: ?>
            <!-- Google Login Notice -->
            <div class="card pg mb-4">
                <div class="card-header profile-section-header">
                    <h5 class="mb-0 profile-section-title">
                        <i class="bi bi-google me-2"></i>Account Security
                    </h5>
                </div>
                <div class="card-body">
                    <div class="alert alert-info mb-0">
                        <i class="bi bi-info-circle me-2"></i>
                        <strong>Google Account:</strong> Your account is linked to Google. To change your password, please update it in your Google account settings.
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <!-- Recent Bookings -->
            <div class="card pg mb-4">
                <div class="card-header profile-section-header">
                    <h5 class="mb-0 profile-section-title">
                        <i class="bi bi-calendar-check me-2"></i>Recent Bookings
                    </h5>
                    <?php if ($userStats['total_bookings'] > 0): ?>
                    <a href="#" class="btn btn-sm btn-outline-secondary">View All</a>
                    <?php endif; ?>
                </div>
                <div class="card-body">
                    <?php if (empty($recentBookings)): ?>
                        <div class="text-center py-4">
                            <i class="bi bi-calendar-x fs-1 d-block mb-3" style="color: var(--accent);"></i>
                            <p class="text-muted mb-0">No bookings yet</p>
                        </div>
                    <?php else: ?>
                        <div class="list-group list-group-flush">
                            <?php foreach ($recentBookings as $booking): ?>
                            <div class="list-group-item px-0 border-0 border-bottom">
                                <div class="d-flex align-items-start gap-3">
                                    <?php if (!empty($booking['cover_image'])): ?>
                                    <img src="<?= htmlspecialchars(app_url($booking['cover_image'])) ?>" 
                                         alt="<?= htmlspecialchars($booking['listing_title']) ?>" 
                                         class="rounded" 
                                         style="width: 80px; height: 80px; object-fit: cover;"
                                         onerror="this.style.display='none'">
                                    <?php endif; ?>
                                    <div class="flex-grow-1">
                                        <h6 class="mb-1"><?= htmlspecialchars($booking['listing_title'] ?: 'Unknown Listing') ?></h6>
                                        <p class="small text-muted mb-1">
                                            <i class="bi bi-geo-alt me-1"></i><?= htmlspecialchars($booking['listing_city'] ?: 'N/A') ?>
                                        </p>
                                        <div class="d-flex align-items-center gap-3 flex-wrap">
                                            <span class="badge" style="background: <?= $booking['status'] === 'confirmed' ? 'linear-gradient(135deg, #43e97b 0%, #38f9d7 100%)' : ($booking['status'] === 'pending' ? 'linear-gradient(135deg, #fbbf24 0%, #f59e0b 100%)' : 'linear-gradient(135deg, #ef4444 0%, #dc2626 100%)') ?>; color: white; border: none;">
                                                <?= ucfirst($booking['status']) ?>
                                            </span>
                                            <span class="text-muted small">₹<?= number_format($booking['total_amount'], 2) ?></span>
                                            <span class="text-muted small"><?= formatDate($booking['created_at'], 'd M Y') ?></span>
                                            <?php if ($booking['status'] === 'confirmed' && !empty($booking['invoice_id'])): ?>
                                            <a href="<?= app_url('invoice?id=' . $booking['invoice_id']) ?>" 
                                               class="btn btn-sm btn-outline-primary" 
                                               title="View Invoice">
                                                <i class="bi bi-receipt me-1"></i>Invoice
                                            </a>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Recent Visits -->
            <div class="card pg">
                <div class="card-header profile-section-header">
                    <h5 class="mb-0 profile-section-title">
                        <i class="bi bi-calendar-event me-2"></i>Property Visits
                    </h5>
                    <?php if ($userStats['total_visits'] > 0): ?>
                    <a href="#" class="btn btn-sm btn-outline-secondary">View All</a>
                    <?php endif; ?>
                </div>
                <div class="card-body">
                    <?php if (empty($recentVisits)): ?>
                        <div class="text-center py-4">
                            <i class="bi bi-calendar-x fs-1 d-block mb-3" style="color: var(--accent);"></i>
                            <p class="text-muted mb-0">No property visits scheduled</p>
                        </div>
                    <?php else: ?>
                        <div class="list-group list-group-flush">
                            <?php foreach ($recentVisits as $visit): ?>
                            <div class="list-group-item px-0 border-0 border-bottom">
                                <div class="d-flex align-items-start gap-3">
                                    <?php if (!empty($visit['cover_image'])): ?>
                                    <img src="<?= htmlspecialchars(app_url($visit['cover_image'])) ?>" 
                                         alt="<?= htmlspecialchars($visit['listing_title']) ?>" 
                                         class="rounded" 
                                         style="width: 80px; height: 80px; object-fit: cover;"
                                         onerror="this.style.display='none'">
                                    <?php endif; ?>
                                    <div class="flex-grow-1">
                                        <h6 class="mb-1"><?= htmlspecialchars($visit['listing_title'] ?: 'Unknown Listing') ?></h6>
                                        <p class="small text-muted mb-1">
                                            <i class="bi bi-geo-alt me-1"></i><?= htmlspecialchars($visit['listing_city'] ?: 'N/A') ?>
                                        </p>
                                        <div class="d-flex align-items-center gap-3">
                                            <span class="badge" style="background: <?= 
                                                $visit['status'] === 'confirmed' ? 'linear-gradient(135deg, #43e97b 0%, #38f9d7 100%)' : 
                                                ($visit['status'] === 'pending' ? 'linear-gradient(135deg, #fbbf24 0%, #f59e0b 100%)' : 
                                                ($visit['status'] === 'completed' ? 'linear-gradient(135deg, #4facfe 0%, #00f2fe 100%)' : 
                                                'linear-gradient(135deg, #ef4444 0%, #dc2626 100%)')) ?>; color: white; border: none;">
                                                <?= ucfirst($visit['status']) ?>
                                            </span>
                                            <span class="text-muted small">
                                                <i class="bi bi-calendar me-1"></i><?= formatDate($visit['visit_date'], 'd M Y') ?>
                                            </span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Right Column -->
        <div class="col-lg-4">
            <!-- Referral Code -->
            <div class="card pg mb-4">
                <div class="card-body">
                    <h5 class="mb-3" style="color: var(--primary-700);">
                        <i class="bi bi-gift me-2"></i>Referral Code
                    </h5>
                    <?php if (!empty($user['referral_code']) && $userStats['confirmed_bookings'] > 0): ?>
                    <div class="mb-3">
                        <div class="kicker mb-2">Your Code</div>
                        <h3 class="mb-2 fw-bold" style="color: var(--primary); letter-spacing: 2px;"><?= htmlspecialchars($user['referral_code']) ?></h3>
                        <button class="btn btn-outline-secondary btn-sm" onclick="copyToClipboard('<?= htmlspecialchars($user['referral_code'], ENT_QUOTES) ?>', 'copyRefCodeBtn')" id="copyRefCodeBtn">
                            <i class="bi bi-clipboard me-1"></i>Copy Code
                        </button>
                    </div>
                    <a href="<?= htmlspecialchars(app_url('refer')) ?>" class="btn btn-primary btn-sm text-white">
                        <i class="bi bi-share me-1"></i>Share & Earn
                    </a>
                    <?php elseif (!empty($user['referral_code']) && $userStats['confirmed_bookings'] <= 0): ?>
                    <p class="text-muted small mb-0">
                        Your referral code will be visible after your first successful booking is confirmed.
                    </p>
                    <?php else: ?>
                    <p class="text-muted small mb-0">No referral code assigned</p>
                    <?php endif; ?>
                </div>
            </div>

            <!-- KYC Status -->
            <div class="card pg mb-4">
                <div class="card-body">
                    <h5 class="mb-3" style="color: var(--primary-700);">
                        <i class="bi bi-shield-check me-2"></i>KYC Status
                    </h5>
                    <?php if ($kycStatus): ?>
                    <div class="mb-3">
                        <?php
                        $status = $kycStatus['status'] ?? 'pending';
                        $badgeColor = $status === 'verified' 
                            ? 'linear-gradient(135deg, #43e97b 0%, #38f9d7 100%)' 
                            : ($status === 'pending' 
                                ? 'linear-gradient(135deg, #fbbf24 0%, #f59e0b 100%)' 
                                : 'linear-gradient(135deg, #ef4444 0%, #dc2626 100%)');
                        ?>
                        <span class="badge" style="background: <?= $badgeColor ?>; color: white; border: none; padding: 0.5rem 1rem;">
                            <?= ucfirst($status) ?>
                        </span>
                    </div>
                    <?php if (!empty($kycStatus['document_type'])): ?>
                    <p class="small text-muted mb-1">
                        <strong>Document Type:</strong> <?= ucfirst(str_replace('_', ' ', $kycStatus['document_type'])) ?>
                    </p>
                    <?php endif; ?>
                    <?php if (!empty($kycStatus['verified_at'])): ?>
                    <p class="small text-muted mb-1">
                        <strong>Verified:</strong> <?= date('d M Y', strtotime($kycStatus['verified_at'])) ?>
                    </p>
                    <?php endif; ?>
                    <?php if (!empty($kycStatus['rejection_reason'])): ?>
                    <p class="small text-danger mt-2 mb-0">
                        <strong>Reason:</strong> <?= htmlspecialchars($kycStatus['rejection_reason']) ?>
                    </p>
                    <?php endif; ?>
                    <?php else: ?>
                    <p class="text-muted small mb-3">KYC not submitted</p>
                    <a href="<?= app_url('book?id=0') ?>" class="btn btn-primary btn-sm">
                        <i class="bi bi-upload me-1"></i>Submit KYC
                    </a>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Quick Actions -->
            <div class="card pg">
                <div class="card-body">
                    <h5 class="mb-3" style="color: var(--primary-700);">
                        <i class="bi bi-lightning me-2"></i>Quick Actions
                    </h5>
                    <div class="d-grid gap-2">
                        <a href="<?= htmlspecialchars(app_url('listings')) ?>" class="btn btn-outline-secondary">
                            <i class="bi bi-search me-2"></i>Browse Listings
                        </a>
                        <a href="<?= htmlspecialchars(app_url('refer')) ?>" class="btn btn-outline-secondary">
                            <i class="bi bi-gift me-2"></i>Refer & Earn
                        </a>
                        <button class="btn btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#editProfileModal">
                            <i class="bi bi-pencil me-2"></i>Edit Profile
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Edit Profile Modal -->
<div class="modal fade" id="editProfileModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Edit Profile</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="editProfileForm" method="POST" action="<?= htmlspecialchars($baseUrl . '/app/profile_update.php') ?>" enctype="multipart/form-data">
                <div class="modal-body">
                    <div id="profileAlert"></div>
                    
                    <!-- Profile Picture Upload -->
                    <div class="mb-4 text-center">
                        <label class="form-label d-block">Profile Picture</label>
                        <div class="position-relative d-inline-block">
                            <div class="profile-avatar-upload" style="width: 120px; height: 120px; border-radius: 50%; overflow: hidden; border: 4px solid var(--primary); background: linear-gradient(135deg, var(--primary) 0%, var(--primary-700) 100%); display: flex; align-items: center; justify-content: center; box-shadow: 0 4px 12px rgba(139, 107, 209, 0.2); cursor: pointer; position: relative;" onclick="document.getElementById('profileImageInput').click();">
                                <img id="profileImagePreview" 
                                     src="<?= ($hasProfileImage && !empty($profileImageUrl)) ? htmlspecialchars($profileImageUrl) : '' ?>" 
                                     alt="Profile Picture" 
                                     class="w-100 h-100" 
                                     style="object-fit: cover; position: absolute; top: 0; left: 0; width: 100%; height: 100%; z-index: 1; <?= !$hasProfileImage || empty($profileImageUrl) ? 'display: none;' : '' ?>"
                                     onerror="this.style.display='none'; document.getElementById('profileImagePlaceholder').style.display='flex';">
                                <div id="profileImagePlaceholder" class="w-100 h-100" style="display: <?= ($hasProfileImage && !empty($profileImageUrl)) ? 'none' : 'flex' ?>; align-items: center; justify-content: center; position: absolute; top: 0; left: 0; z-index: 0;">
                                    <i class="bi bi-person-fill" style="font-size: 60px; color: white; opacity: 0.9;"></i>
                                </div>
                                <div class="position-absolute bottom-0 end-0 bg-primary text-white rounded-circle p-2" style="width: 36px; height: 36px; display: flex; align-items: center; justify-content: center; border: 3px solid white; box-shadow: 0 2px 8px rgba(0,0,0,0.2);">
                                    <i class="bi bi-camera-fill" style="font-size: 16px;"></i>
                                </div>
                            </div>
                        </div>
                        <input type="file" 
                               id="profileImageInput" 
                               name="profile_image" 
                               accept="image/jpeg,image/jpg,image/png,image/gif,image/webp" 
                               class="d-none"
                               onchange="previewProfileImage(this);">
                        <div class="mt-2">
                            <small class="text-muted d-block">Click to upload (Max 2MB, JPG/PNG/GIF/WebP)</small>
                            <button type="button" class="btn btn-sm btn-outline-danger mt-1" id="removeProfileImageBtn" style="<?= !$hasProfileImage ? 'display: none;' : '' ?>" onclick="removeProfileImage();">
                                <i class="bi bi-trash me-1"></i>Remove Picture
                            </button>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="editName" class="form-label">Full Name</label>
                        <input type="text" class="form-control modal-input" id="editName" name="name" 
                               value="<?= htmlspecialchars($user['name'] ?? '') ?>" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="editPhone" class="form-label">Phone</label>
                        <input type="tel" class="form-control modal-input" id="editPhone" name="phone" 
                               value="<?= htmlspecialchars(isset($user['phone']) && $user['phone'] !== null ? $user['phone'] : '') ?>" 
                               placeholder="Enter your phone number">
                    </div>
                    
                    <div class="mb-3">
                        <label for="editGender" class="form-label">Gender</label>
                        <?php 
                        // Get gender value directly - check multiple sources
                        $currentGender = '';
                        if (isset($user['gender']) && $user['gender'] !== null && $user['gender'] !== '') {
                            $currentGender = trim($user['gender']);
                        }
                        ?>
                        <select class="form-select modal-input" id="editGender" name="gender">
                            <option value="">Select Gender</option>
                            <option value="male" <?= ($currentGender === 'male') ? 'selected="selected"' : '' ?>>Male</option>
                            <option value="female" <?= ($currentGender === 'female') ? 'selected="selected"' : '' ?>>Female</option>
                            <option value="other" <?= ($currentGender === 'other') ? 'selected="selected"' : '' ?>>Other</option>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label for="editAddress" class="form-label">Address</label>
                        <textarea class="form-control modal-input" id="editAddress" name="address" rows="3" 
                                  placeholder="Enter your address"><?= htmlspecialchars(isset($user['address']) && $user['address'] !== null ? $user['address'] : '') ?></textarea>
                    </div>
                    
                    <div class="row g-2">
                        <div class="col-md-4">
                            <label for="editCity" class="form-label">City</label>
                            <input type="text" class="form-control modal-input" id="editCity" name="city" 
                                   value="<?= htmlspecialchars(isset($user['city']) && $user['city'] !== null ? $user['city'] : '') ?>" 
                                   placeholder="City">
                        </div>
                        <div class="col-md-4">
                            <label for="editState" class="form-label">State</label>
                            <input type="text" class="form-control modal-input" id="editState" name="state" 
                                   value="<?= htmlspecialchars(isset($user['state']) && $user['state'] !== null ? $user['state'] : '') ?>" 
                                   placeholder="State">
                        </div>
                        <div class="col-md-4">
                            <label for="editPincode" class="form-label">Pin Code</label>
                            <input type="text" class="form-control modal-input" id="editPincode" name="pincode" 
                                   value="<?= htmlspecialchars(isset($user['pincode']) && $user['pincode'] !== null ? $user['pincode'] : '') ?>" 
                                   placeholder="Pin Code">
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary" id="profileSubmit">
                        <span id="profileSpinner" class="spinner-border spinner-border-sm me-2 d-none"></span>
                        Save Changes
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function copyToClipboard(text, buttonId) {
    navigator.clipboard.writeText(text).then(function() {
        const btn = document.getElementById(buttonId);
        const originalText = btn.innerHTML;
        btn.innerHTML = '<i class="bi bi-check me-1"></i>Copied!';
        btn.classList.add('btn-success');
        btn.classList.remove('btn-outline-secondary');
        setTimeout(function() {
            btn.innerHTML = originalText;
            btn.classList.remove('btn-success');
            btn.classList.add('btn-outline-secondary');
        }, 2000);
    }).catch(function(err) {
        alert('Failed to copy: ' + err);
    });
}

// Profile Image Preview
function previewProfileImage(input) {
    const file = input.files[0];
    if (file) {
        // Validate file size (2MB)
        if (file.size > 2 * 1024 * 1024) {
            alert('Image size must be less than 2MB');
            input.value = '';
            return;
        }
        
        // Validate file type
        const allowedTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/webp'];
        if (!allowedTypes.includes(file.type)) {
            alert('Please select a valid image file (JPG, PNG, GIF, or WebP)');
            input.value = '';
            return;
        }
        
        const reader = new FileReader();
        reader.onload = function(e) {
            const preview = document.getElementById('profileImagePreview');
            const placeholder = document.getElementById('profileImagePlaceholder');
            const removeBtn = document.getElementById('removeProfileImageBtn');
            
            preview.src = e.target.result;
            preview.style.display = 'block';
            placeholder.style.display = 'none';
            removeBtn.style.display = 'inline-block';
        };
        reader.readAsDataURL(file);
    }
}

// Remove Profile Image
function removeProfileImage() {
    const input = document.getElementById('profileImageInput');
    const preview = document.getElementById('profileImagePreview');
    const placeholder = document.getElementById('profileImagePlaceholder');
    const removeBtn = document.getElementById('removeProfileImageBtn');
    
    input.value = '';
    preview.src = '';
    preview.style.display = 'none';
    placeholder.style.display = 'flex';
    removeBtn.style.display = 'none';
    
    // Add a hidden field to indicate removal
    let removeField = document.getElementById('removeProfileImage');
    if (!removeField) {
        removeField = document.createElement('input');
        removeField.type = 'hidden';
        removeField.id = 'removeProfileImage';
        removeField.name = 'remove_profile_image';
        removeField.value = '1';
        document.getElementById('editProfileForm').appendChild(removeField);
    }
}

// Edit Profile Form AJAX
(function() {
    const form = document.getElementById('editProfileForm');
    if (form) {
        form.addEventListener('submit', async function(e) {
            e.preventDefault();
            
            const submitBtn = document.getElementById('profileSubmit');
            const spinner = document.getElementById('profileSpinner');
            const alertContainer = document.getElementById('profileAlert');
            
            submitBtn.disabled = true;
            spinner.classList.remove('d-none');
            alertContainer.innerHTML = '';
            
            const formData = new FormData(form);
            
            try {
                const response = await fetch(form.action, {
                    method: 'POST',
                    body: formData,
                    credentials: 'include',
                    headers: {
                        'Accept': 'application/json'
                    }
                });
                
                const data = await response.json();
                
                if (data.status === 'ok' || data.status === 'success') {
                    alertContainer.innerHTML = '<div class="alert alert-success">Profile updated successfully!</div>';
                    
                    // Update profile image preview if new image was uploaded
                    if (data.data && data.data.user && data.data.user.profile_image) {
                        const preview = document.getElementById('profileImagePreview');
                        const placeholder = document.getElementById('profileImagePlaceholder');
                        const removeBtn = document.getElementById('removeProfileImageBtn');
                        
                        // Update preview with new image URL
                        const baseUrl = '<?= htmlspecialchars($baseUrl) ?>';
                        preview.src = baseUrl + '/' + data.data.user.profile_image;
                        preview.style.display = 'block';
                        placeholder.style.display = 'none';
                        removeBtn.style.display = 'inline-block';
                    } else if (data.data && data.data.user && !data.data.user.profile_image) {
                        // Image was removed
                        const preview = document.getElementById('profileImagePreview');
                        const placeholder = document.getElementById('profileImagePlaceholder');
                        const removeBtn = document.getElementById('removeProfileImageBtn');
                        
                        preview.src = '';
                        preview.style.display = 'none';
                        placeholder.style.display = 'flex';
                        removeBtn.style.display = 'none';
                    }
                    
                    setTimeout(() => {
                        window.location.reload();
                    }, 1500);
                } else {
                    // Show field errors if any
                    if (data.errors && typeof data.errors === 'object') {
                        let errorHtml = '<div class="alert alert-danger"><ul class="mb-0">';
                        Object.keys(data.errors).forEach(field => {
                            errorHtml += '<li>' + data.errors[field] + '</li>';
                        });
                        errorHtml += '</ul></div>';
                        alertContainer.innerHTML = errorHtml;
                    } else {
                        alertContainer.innerHTML = '<div class="alert alert-danger">' + (data.message || 'Error updating profile') + '</div>';
                    }
                    submitBtn.disabled = false;
                    spinner.classList.add('d-none');
                }
            } catch (error) {
                alertContainer.innerHTML = '<div class="alert alert-danger">Network error. Please try again.</div>';
                submitBtn.disabled = false;
                spinner.classList.add('d-none');
            }
        });
    }
})();

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
                const response = await fetch('<?= htmlspecialchars(app_url('change-password-api')) ?>', {
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

<style>
.profile-section-header {
    background: var(--accent);
    border-bottom: 1px solid var(--border);
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.profile-section-title {
    color: var(--primary-700);
}

:root[data-theme="dark"] .profile-section-header {
    background: rgba(75, 59, 99, 0.3);
    border-bottom: 1px solid rgba(255, 255, 255, 0.04);
}

:root[data-theme="dark"] .profile-section-title {
    color: var(--primary);
}
</style>

<?php require __DIR__ . '/../app/includes/footer.php'; ?>

