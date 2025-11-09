<?php
/**
 * Admin Profile Page
 * Display and manage admin profile information
 */

$pageTitle = "My Profile";
require __DIR__ . '/../app/includes/admin_header.php';
require __DIR__ . '/../app/functions.php';

$adminId = $_SESSION['user_id'];
$admin = null;
$adminStats = [];

try {
    $db = db();
    
    // Get admin data
    $admin = $db->fetchOne(
        "SELECT id, name, email, phone, profile_image, created_at, updated_at, role
         FROM users 
         WHERE id = ? AND role = 'admin'",
        [$adminId]
    );
    
    if (!$admin) {
        header('Location: ' . app_url('admin'));
        exit;
    }
    
    // Get admin statistics
    $adminStats = [
        'total_users' => (int)$db->fetchValue("SELECT COUNT(*) FROM users WHERE role != 'admin'") ?: 0,
        'total_listings' => (int)$db->fetchValue("SELECT COUNT(*) FROM listings") ?: 0,
        'active_listings' => (int)$db->fetchValue("SELECT COUNT(*) FROM listings WHERE status = 'active'") ?: 0,
        'total_bookings' => (int)$db->fetchValue("SELECT COUNT(*) FROM bookings") ?: 0,
        'pending_bookings' => (int)$db->fetchValue("SELECT COUNT(*) FROM bookings WHERE status = 'pending'") ?: 0,
        'total_revenue' => (float)$db->fetchValue("SELECT COALESCE(SUM(amount), 0) FROM payments WHERE status = 'success'") ?: 0,
    ];
    
} catch (Exception $e) {
    error_log("Error loading admin profile data: " . $e->getMessage());
    $admin = [
        'id' => $adminId,
        'name' => $_SESSION['user_name'] ?? 'Admin',
        'email' => $_SESSION['user_email'] ?? '',
        'phone' => null,
        'profile_image' => null,
        'created_at' => null,
        'updated_at' => null,
        'role' => 'admin'
    ];
    $adminStats = [
        'total_users' => 0,
        'total_listings' => 0,
        'active_listings' => 0,
        'total_bookings' => 0,
        'pending_bookings' => 0,
        'total_revenue' => 0,
    ];
}

// Set defaults for null values
$admin = array_merge([
    'name' => '',
    'email' => '',
    'phone' => null,
    'profile_image' => null,
    'created_at' => null,
    'updated_at' => null,
], $admin);
?>

<!-- Profile Hero Section -->
<div class="profile-hero mb-4">
    <div class="card admin-card border-0 shadow-sm" style="background: linear-gradient(135deg, var(--admin-primary) 0%, var(--admin-primary-700) 100%); border-radius: 20px !important;">
        <div class="card-body p-4 p-md-5">
            <div class="row align-items-center">
                <div class="col-auto">
                    <!-- Profile Image -->
                    <div class="profile-avatar-wrapper">
                        <?php if (!empty($admin['profile_image'])): ?>
                            <img src="<?= htmlspecialchars(app_url($admin['profile_image'])) ?>" 
                                 alt="<?= htmlspecialchars($admin['name']) ?>"
                                 class="profile-avatar-img">
                        <?php else: ?>
                            <div class="profile-avatar-placeholder">
                                <i class="bi bi-person-fill"></i>
                            </div>
                    <?php endif; ?>
                    </div>
                </div>
                <div class="col">
                    <div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center gap-3">
                        <div>
                            <h2 class="text-white mb-2" style="font-size: 1.75rem; font-weight: 700;">
                                <?= htmlspecialchars($admin['name']) ?>
                            </h2>
                            <div class="d-flex flex-wrap align-items-center gap-3 mb-2">
                                <span class="badge bg-white text-primary px-3 py-2" style="font-size: 0.875rem; font-weight: 600;">
                                    <i class="bi bi-shield-check me-1"></i>Administrator
                                </span>
                                <span class="text-white-50" style="font-size: 0.9375rem;">
                                    <i class="bi bi-envelope me-1"></i><?= htmlspecialchars($admin['email']) ?>
                                </span>
                                <?php if ($admin['phone']): ?>
                                <span class="text-white-50" style="font-size: 0.9375rem;">
                                    <i class="bi bi-telephone me-1"></i><?= htmlspecialchars($admin['phone']) ?>
                                </span>
                                <?php endif; ?>
                            </div>
                            <div class="text-white-50" style="font-size: 0.875rem;">
                                <i class="bi bi-calendar3 me-1"></i>Member since <?= $admin['created_at'] ? date('F j, Y', strtotime($admin['created_at'])) : 'N/A' ?>
                            </div>
                        </div>
                        <button class="btn btn-light btn-lg px-4" data-bs-toggle="modal" data-bs-target="#editProfileModal" style="font-weight: 600;">
                            <i class="bi bi-pencil me-2"></i>Edit Profile
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Statistics Section -->
<div class="mb-4">
    <h5 class="mb-3" style="color: var(--admin-text); font-weight: 600;">
        <i class="bi bi-bar-chart me-2" style="color: var(--admin-primary);"></i>Overview Statistics
    </h5>
    <div class="row g-3">
        <div class="col-lg-4 col-md-6">
            <div class="card admin-stat-card h-100">
                <div class="admin-stat-card-icon" style="background: linear-gradient(135deg, #8B6BD1, #6F55B2);">
                    <i class="bi bi-people"></i>
                </div>
                <div class="admin-stat-card-content">
                    <div class="admin-stat-card-label">Total Users</div>
                    <div class="admin-stat-card-value"><?= number_format($adminStats['total_users']) ?></div>
                </div>
            </div>
        </div>
        <div class="col-lg-4 col-md-6">
            <div class="card admin-stat-card h-100">
                <div class="admin-stat-card-icon" style="background: linear-gradient(135deg, #10B981, #059669);">
                    <i class="bi bi-building"></i>
                </div>
                <div class="admin-stat-card-content">
                    <div class="admin-stat-card-label">Total Listings</div>
                    <div class="admin-stat-card-value"><?= number_format($adminStats['total_listings']) ?></div>
                </div>
            </div>
        </div>
        <div class="col-lg-4 col-md-6">
            <div class="card admin-stat-card h-100">
                <div class="admin-stat-card-icon" style="background: linear-gradient(135deg, #3B82F6, #2563EB);">
                    <i class="bi bi-check-circle"></i>
                </div>
                <div class="admin-stat-card-content">
                    <div class="admin-stat-card-label">Active Listings</div>
                    <div class="admin-stat-card-value"><?= number_format($adminStats['active_listings']) ?></div>
                </div>
            </div>
        </div>
        <div class="col-lg-4 col-md-6">
            <div class="card admin-stat-card h-100">
                <div class="admin-stat-card-icon" style="background: linear-gradient(135deg, #F59E0B, #D97706);">
                    <i class="bi bi-calendar-check"></i>
                </div>
                <div class="admin-stat-card-content">
                    <div class="admin-stat-card-label">Total Bookings</div>
                    <div class="admin-stat-card-value"><?= number_format($adminStats['total_bookings']) ?></div>
                </div>
            </div>
        </div>
        <div class="col-lg-4 col-md-6">
            <div class="card admin-stat-card h-100">
                <div class="admin-stat-card-icon" style="background: linear-gradient(135deg, #EF4444, #DC2626);">
                    <i class="bi bi-clock-history"></i>
                </div>
                <div class="admin-stat-card-content">
                    <div class="admin-stat-card-label">Pending Bookings</div>
                    <div class="admin-stat-card-value"><?= number_format($adminStats['pending_bookings']) ?></div>
                </div>
            </div>
        </div>
        <div class="col-lg-4 col-md-6">
            <div class="card admin-stat-card h-100">
                <div class="admin-stat-card-icon" style="background: linear-gradient(135deg, #10B981, #059669);">
                    <i class="bi bi-currency-rupee"></i>
                </div>
                <div class="admin-stat-card-content">
                    <div class="admin-stat-card-label">Total Revenue</div>
                    <div class="admin-stat-card-value">₹<?= number_format($adminStats['total_revenue'], 2) ?></div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Profile Information Section -->
<div class="row g-4">
    <div class="col-12">
        <div class="card admin-card">
            <div class="card-header bg-transparent border-bottom" style="padding: 1.5rem;">
                <h5 class="mb-0" style="color: var(--admin-text); font-weight: 600;">
                    <i class="bi bi-info-circle me-2" style="color: var(--admin-primary);"></i>Profile Information
                </h5>
            </div>
            <div class="card-body" style="padding: 1.5rem;">
                <div class="row g-4">
                    <div class="col-md-6">
                        <div class="profile-info-item">
                            <label class="profile-info-label">
                                <i class="bi bi-person me-2"></i>Full Name
                            </label>
                            <div class="profile-info-value"><?= htmlspecialchars($admin['name']) ?></div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="profile-info-item">
                            <label class="profile-info-label">
                                <i class="bi bi-envelope me-2"></i>Email Address
                            </label>
                            <div class="profile-info-value"><?= htmlspecialchars($admin['email']) ?></div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="profile-info-item">
                            <label class="profile-info-label">
                                <i class="bi bi-telephone me-2"></i>Phone Number
                            </label>
                            <div class="profile-info-value">
                                <?= $admin['phone'] ? htmlspecialchars($admin['phone']) : '<span class="text-muted">Not set</span>' ?>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="profile-info-item">
                            <label class="profile-info-label">
                                <i class="bi bi-shield-check me-2"></i>Role
                            </label>
                            <div class="profile-info-value">
                                <span class="badge bg-primary px-3 py-2">Administrator</span>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="profile-info-item">
                            <label class="profile-info-label">
                                <i class="bi bi-calendar-plus me-2"></i>Account Created
                            </label>
                            <div class="profile-info-value">
                                <?= $admin['created_at'] ? date('F j, Y, g:i a', strtotime($admin['created_at'])) : 'N/A' ?>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="profile-info-item">
                            <label class="profile-info-label">
                                <i class="bi bi-clock me-2"></i>Last Updated
                            </label>
                            <div class="profile-info-value">
                                <?= $admin['updated_at'] ? date('F j, Y, g:i a', strtotime($admin['updated_at'])) : 'N/A' ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Edit Profile Modal -->
<div class="modal fade" id="editProfileModal" tabindex="-1" aria-labelledby="editProfileModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header" style="border-bottom: 1px solid var(--admin-border);">
                <h5 class="modal-title" id="editProfileModalLabel" style="font-weight: 600;">
                    <i class="bi bi-pencil me-2" style="color: var(--admin-primary);"></i>Edit Profile
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="editProfileForm">
                <div class="modal-body" style="padding: 1.5rem;">
                    <div class="mb-3">
                        <label for="editName" class="form-label" style="font-weight: 600;">
                            <i class="bi bi-person me-1"></i>Full Name
                        </label>
                        <input type="text" class="form-control" id="editName" name="name" 
                               value="<?= htmlspecialchars($admin['name']) ?>" required>
                    </div>
                    <div class="mb-3">
                        <label for="editEmail" class="form-label" style="font-weight: 600;">
                            <i class="bi bi-envelope me-1"></i>Email Address
                        </label>
                        <input type="email" class="form-control" id="editEmail" name="email" 
                               value="<?= htmlspecialchars($admin['email']) ?>" required>
                    </div>
                    <div class="mb-3">
                        <label for="editPhone" class="form-label" style="font-weight: 600;">
                            <i class="bi bi-telephone me-1"></i>Phone Number
                        </label>
                        <input type="tel" class="form-control" id="editPhone" name="phone" 
                               value="<?= htmlspecialchars($admin['phone'] ?? '') ?>" 
                               placeholder="Enter phone number">
                    </div>
                </div>
                <div class="modal-footer" style="border-top: 1px solid var(--admin-border);">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary" style="font-weight: 600;">
                        <i class="bi bi-check-lg me-2"></i>Save Changes
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<style>
/* Profile Hero Styles */
.profile-hero {
    margin-top: 0;
}

.profile-avatar-wrapper {
    position: relative;
    width: 120px;
    height: 120px;
}

.profile-avatar-img {
    width: 120px;
    height: 120px;
    border-radius: 50%;
    object-fit: cover;
    border: 4px solid rgba(255, 255, 255, 0.3);
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
}

.profile-avatar-placeholder {
    width: 120px;
    height: 120px;
    border-radius: 50%;
    background: rgba(255, 255, 255, 0.2);
    border: 4px solid rgba(255, 255, 255, 0.3);
    display: flex;
    align-items: center;
    justify-content: center;
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
}

.profile-avatar-placeholder i {
    font-size: 4rem;
    color: white;
}

/* Profile Info Styles */
.profile-info-item {
    padding: 1rem 0;
    border-bottom: 1px solid var(--admin-border);
}

.profile-info-item:last-child {
    border-bottom: none;
}

.profile-info-label {
    display: flex;
    align-items: center;
    font-size: 0.875rem;
    font-weight: 600;
    color: var(--admin-text-muted);
    margin-bottom: 0.5rem 0;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.profile-info-label i {
    color: var(--admin-primary);
    font-size: 1rem;
}

.profile-info-value {
    font-size: 1rem;
    color: var(--admin-text);
    font-weight: 500;
}

@media (max-width: 768px) {
    .profile-hero .card-body {
        padding: 1.5rem !important;
    }
    
    .profile-avatar-wrapper {
        width: 100px;
        height: 100px;
        margin: 0 auto 1rem;
    }
    
    .profile-avatar-img,
    .profile-avatar-placeholder {
        width: 100px;
        height: 100px;
    }
    
    .profile-avatar-placeholder i {
        font-size: 3rem;
    }
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const editProfileForm = document.getElementById('editProfileForm');
    
    if (editProfileForm) {
        editProfileForm.addEventListener('submit', async function(e) {
            e.preventDefault();
            
            const formData = new FormData(editProfileForm);
            formData.append('action', 'update_profile');
            
            const submitBtn = editProfileForm.querySelector('button[type="submit"]');
            const originalText = submitBtn.innerHTML;
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Saving...';
            
            try {
                const response = await fetch('<?= htmlspecialchars($baseUrl . "/app/admin_profile_update.php") ?>', {
                    method: 'POST',
                    body: formData,
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest'
                    }
                });
                
                if (!response.ok) {
                    throw new Error(`HTTP error! status: ${response.status}`);
                }
                
                let data;
                try {
                    data = await response.json();
                } catch (jsonError) {
                    console.error('JSON parse error:', jsonError);
                    const text = await response.text();
                    console.error('Response text:', text);
                    throw new Error('Invalid response from server');
                }
                
                if (data.status === 'success') {
                    // Show success message
                    const alert = document.createElement('div');
                    alert.className = 'alert alert-success alert-dismissible fade show';
                    alert.innerHTML = `
                        <i class="bi bi-check-circle me-2"></i>${data.message}
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    `;
                    document.querySelector('.admin-main-content').insertBefore(alert, document.querySelector('.admin-main-content').firstChild);
                    
                    // Close modal and reload page after a short delay
                    const modal = bootstrap.Modal.getInstance(document.getElementById('editProfileModal'));
                    modal.hide();
                    
                    setTimeout(() => {
                        window.location.reload();
                    }, 1000);
                } else {
                    const errorMsg = data.message || data.error || 'Failed to update profile';
                    alert('Error: ' + errorMsg);
                    submitBtn.disabled = false;
                    submitBtn.innerHTML = originalText;
                }
            } catch (error) {
                console.error('Error:', error);
                let errorMessage = 'An error occurred while updating your profile';
                if (error.message) {
                    errorMessage += ': ' + error.message;
                }
                alert(errorMessage);
                submitBtn.disabled = false;
                submitBtn.innerHTML = originalText;
            }
        });
    }
});
</script>

<?php require __DIR__ . '/../app/includes/admin_footer.php'; ?>
