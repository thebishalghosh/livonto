<?php
/**
 * User Profile Page
 * Display and manage user profile information
 */

// Start session and load config/functions BEFORE any output
if (session_status() === PHP_SESSION_NONE) session_start();
require __DIR__ . '/../app/config.php';
require __DIR__ . '/../app/functions.php';

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
                referral_code, created_at, updated_at, google_id
         FROM users 
         WHERE id = ?",
        [$userId]
    );
    
    if (!$user) {
        header('Location: ' . app_url('login'));
        exit;
    }
    
    // Get user statistics
    $userStats = [
        'total_bookings' => (int)$db->fetchValue("SELECT COUNT(*) FROM bookings WHERE user_id = ?", [$userId]) ?: 0,
        'confirmed_bookings' => (int)$db->fetchValue("SELECT COUNT(*) FROM bookings WHERE user_id = ? AND status = 'confirmed'", [$userId]) ?: 0,
        'pending_bookings' => (int)$db->fetchValue("SELECT COUNT(*) FROM bookings WHERE user_id = ? AND status = 'pending'", [$userId]) ?: 0,
        'total_visits' => (int)$db->fetchValue("SELECT COUNT(*) FROM visit_bookings WHERE user_id = ?", [$userId]) ?: 0,
        'total_referrals' => (int)$db->fetchValue("SELECT COUNT(*) FROM referrals WHERE referrer_id = ?", [$userId]) ?: 0,
        'total_rewards' => (float)$db->fetchValue("SELECT COALESCE(SUM(reward_amount), 0) FROM referrals WHERE referrer_id = ? AND status = 'credited'", [$userId]) ?: 0,
    ];
    
    // Get recent bookings (last 5)
    $recentBookings = $db->fetchAll(
        "SELECT b.id, b.status, b.total_amount, b.created_at,
                l.title as listing_title, l.cover_image,
                loc.city as listing_city
         FROM bookings b
         LEFT JOIN listings l ON b.listing_id = l.id
         LEFT JOIN listing_locations loc ON l.id = loc.listing_id
         WHERE b.user_id = ?
         ORDER BY b.created_at DESC
         LIMIT 5",
        [$userId]
    );
    
    // Get recent visits (last 5)
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
    
    // Get KYC status
    $kycStatus = $db->fetchOne(
        "SELECT status, document_type, created_at, verified_at, rejection_reason
         FROM user_kyc
         WHERE user_id = ?
         ORDER BY created_at DESC
         LIMIT 1",
        [$userId]
    );
    
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
    $userStats = [
        'total_bookings' => 0,
        'confirmed_bookings' => 0,
        'pending_bookings' => 0,
        'total_visits' => 0,
        'total_referrals' => 0,
        'total_rewards' => 0.0
    ];
    $recentBookings = [];
    $recentVisits = [];
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

// Get profile image URL
$profileImageUrl = null;
$hasProfileImage = false;
if (!empty($user['profile_image'])) {
    $profileImageUrl = strpos($user['profile_image'], 'http') === 0 
        ? $user['profile_image'] 
        : app_url($user['profile_image']);
    $hasProfileImage = true;
}

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

// Set default values for stats
$userStats = array_merge([
    'total_bookings' => 0,
    'confirmed_bookings' => 0,
    'pending_bookings' => 0,
    'total_visits' => 0,
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
                    <div class="profile-avatar" style="width: 120px; height: 120px; border-radius: 50%; overflow: hidden; border: 4px solid var(--primary); background: linear-gradient(135deg, var(--primary) 0%, var(--primary-700) 100%); display: flex; align-items: center; justify-content: center; box-shadow: 0 4px 12px rgba(139, 107, 209, 0.2);">
                        <?php if ($hasProfileImage && $profileImageUrl): ?>
                        <img src="<?= htmlspecialchars($profileImageUrl) ?>" 
                             alt="<?= htmlspecialchars($user['name'] ?: 'User') ?>" 
                             class="w-100 h-100" 
                             style="object-fit: cover;"
                             onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';">
                        <?php endif; ?>
                        <div class="profile-icon-placeholder" style="display: <?= $hasProfileImage ? 'none' : 'flex' ?>; align-items: center; justify-content: center; width: 100%; height: 100%;">
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
                                        <div class="d-flex align-items-center gap-3">
                                            <span class="badge" style="background: <?= $booking['status'] === 'confirmed' ? 'linear-gradient(135deg, #43e97b 0%, #38f9d7 100%)' : ($booking['status'] === 'pending' ? 'linear-gradient(135deg, #fbbf24 0%, #f59e0b 100%)' : 'linear-gradient(135deg, #ef4444 0%, #dc2626 100%)') ?>; color: white; border: none;">
                                                <?= ucfirst($booking['status']) ?>
                                            </span>
                                            <span class="text-muted small">₹<?= number_format($booking['total_amount'], 2) ?></span>
                                            <span class="text-muted small"><?= formatDate($booking['created_at'], 'd M Y') ?></span>
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
                                            <span class="badge" style="background: <?= $visit['status'] === 'confirmed' ? 'linear-gradient(135deg, #43e97b 0%, #38f9d7 100%)' : ($visit['status'] === 'requested' ? 'linear-gradient(135deg, #fbbf24 0%, #f59e0b 100%)' : 'linear-gradient(135deg, #ef4444 0%, #dc2626 100%)') ?>; color: white; border: none;">
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
                    <?php if (!empty($user['referral_code'])): ?>
                    <div class="mb-3">
                        <div class="kicker mb-2">Your Code</div>
                        <h3 class="mb-2 fw-bold" style="color: var(--primary); letter-spacing: 2px;"><?= htmlspecialchars($user['referral_code']) ?></h3>
                        <button class="btn btn-outline-secondary btn-sm" onclick="copyToClipboard('<?= htmlspecialchars($user['referral_code'], ENT_QUOTES) ?>', 'copyRefCodeBtn')" id="copyRefCodeBtn">
                            <i class="bi bi-clipboard me-1"></i>Copy Code
                        </button>
                    </div>
                    <a href="<?= htmlspecialchars(app_url('refer')) ?>" class="btn btn-primary btn-sm">
                        <i class="bi bi-share me-1"></i>Share & Earn
                    </a>
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
                        <span class="badge" style="background: <?= $kycStatus['status'] === 'verified' ? 'linear-gradient(135deg, #43e97b 0%, #38f9d7 100%)' : ($kycStatus['status'] === 'pending' ? 'linear-gradient(135deg, #fbbf24 0%, #f59e0b 100%)' : 'linear-gradient(135deg, #ef4444 0%, #dc2626 100%)') ?>; color: white; border: none; padding: 0.5rem 1rem;">
                            <?= ucfirst($kycStatus['status']) ?>
                        </span>
                    </div>
                    <p class="small text-muted mb-1">
                        <strong>Document Type:</strong> <?= ucfirst(str_replace('_', ' ', $kycStatus['document_type'])) ?>
                    </p>
                    <?php if ($kycStatus['verified_at']): ?>
                    <p class="small text-muted mb-0">
                        Verified: <?= formatDate($kycStatus['verified_at'], 'd M Y') ?>
                    </p>
                    <?php endif; ?>
                    <?php if ($kycStatus['rejection_reason']): ?>
                    <p class="small text-danger mt-2 mb-0">
                        <strong>Reason:</strong> <?= htmlspecialchars($kycStatus['rejection_reason']) ?>
                    </p>
                    <?php endif; ?>
                    <?php else: ?>
                    <p class="text-muted small mb-3">KYC not submitted</p>
                    <button class="btn btn-primary btn-sm">
                        <i class="bi bi-upload me-1"></i>Submit KYC
                    </button>
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
                    
                    <div class="mb-3">
                        <label for="editName" class="form-label">Full Name</label>
                        <input type="text" class="form-control modal-input" id="editName" name="name" 
                               value="<?= htmlspecialchars($user['name']) ?>" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="editPhone" class="form-label">Phone</label>
                        <input type="tel" class="form-control modal-input" id="editPhone" name="phone" 
                               value="<?= htmlspecialchars($user['phone'] ?? '') ?>">
                    </div>
                    
                    <div class="mb-3">
                        <label for="editGender" class="form-label">Gender</label>
                        <select class="form-select modal-input" id="editGender" name="gender">
                            <option value="">Select Gender</option>
                            <option value="male" <?= $user['gender'] === 'male' ? 'selected' : '' ?>>Male</option>
                            <option value="female" <?= $user['gender'] === 'female' ? 'selected' : '' ?>>Female</option>
                            <option value="other" <?= $user['gender'] === 'other' ? 'selected' : '' ?>>Other</option>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label for="editAddress" class="form-label">Address</label>
                        <textarea class="form-control modal-input" id="editAddress" name="address" rows="3"><?= htmlspecialchars($user['address'] ?? '') ?></textarea>
                    </div>
                    
                    <div class="row g-2">
                        <div class="col-md-4">
                            <label for="editCity" class="form-label">City</label>
                            <input type="text" class="form-control modal-input" id="editCity" name="city" 
                                   value="<?= htmlspecialchars($user['city'] ?? '') ?>">
                        </div>
                        <div class="col-md-4">
                            <label for="editState" class="form-label">State</label>
                            <input type="text" class="form-control modal-input" id="editState" name="state" 
                                   value="<?= htmlspecialchars($user['state'] ?? '') ?>">
                        </div>
                        <div class="col-md-4">
                            <label for="editPincode" class="form-label">Pin Code</label>
                            <input type="text" class="form-control modal-input" id="editPincode" name="pincode" 
                                   value="<?= htmlspecialchars($user['pincode'] ?? '') ?>">
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
                    setTimeout(() => {
                        window.location.reload();
                    }, 1000);
                } else {
                    alertContainer.innerHTML = '<div class="alert alert-danger">' + (data.message || 'Error updating profile') + '</div>';
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

