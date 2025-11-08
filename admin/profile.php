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

<!-- Main Content -->
<div class="admin-content">
        <!-- Page Header -->
        <div class="admin-page-header mb-4">
            <h1 class="admin-page-title">My Profile</h1>
            <p class="admin-page-subtitle text-muted">Manage your admin account information and preferences</p>
        </div>

        <div class="row g-4">
            <!-- Profile Card -->
            <div class="col-lg-4">
                <div class="card admin-card">
                    <div class="card-body text-center">
                        <!-- Profile Image -->
                        <div class="mb-3">
                            <?php if (!empty($admin['profile_image'])): ?>
                                <img src="<?= htmlspecialchars(app_url($admin['profile_image'])) ?>" 
                                     alt="<?= htmlspecialchars($admin['name']) ?>"
                                     class="rounded-circle"
                                     style="width: 120px; height: 120px; object-fit: cover; border: 4px solid var(--admin-primary);">
                            <?php else: ?>
                                <div class="rounded-circle d-inline-flex align-items-center justify-content-center"
                                     style="width: 120px; height: 120px; background: linear-gradient(135deg, var(--admin-primary), var(--admin-primary-700)); border: 4px solid var(--admin-primary);">
                                    <i class="bi bi-person-fill text-white" style="font-size: 4rem;"></i>
                                </div>
                            <?php endif; ?>
                        </div>
                        
                        <h4 class="mb-1"><?= htmlspecialchars($admin['name']) ?></h4>
                        <p class="text-muted mb-2"><?= htmlspecialchars($admin['email']) ?></p>
                        <span class="badge bg-primary">Administrator</span>
                        
                        <hr class="my-3">
                        
                        <div class="text-start">
                            <p class="mb-2">
                                <i class="bi bi-calendar3 me-2 text-muted"></i>
                                <strong>Member since:</strong><br>
                                <small class="text-muted">
                                    <?= $admin['created_at'] ? date('F j, Y', strtotime($admin['created_at'])) : 'N/A' ?>
                                </small>
                            </p>
                            <?php if ($admin['phone']): ?>
                            <p class="mb-0">
                                <i class="bi bi-telephone me-2 text-muted"></i>
                                <strong>Phone:</strong><br>
                                <small class="text-muted"><?= htmlspecialchars($admin['phone']) ?></small>
                            </p>
                            <?php endif; ?>
                        </div>
                        
                        <hr class="my-3">
                        
                        <button class="btn btn-primary w-100" data-bs-toggle="modal" data-bs-target="#editProfileModal">
                            <i class="bi bi-pencil me-2"></i>Edit Profile
                        </button>
                    </div>
                </div>
            </div>

            <!-- Statistics & Details -->
            <div class="col-lg-8">
                <!-- Statistics Cards -->
                <div class="row g-3 mb-4">
                    <div class="col-md-6">
                        <div class="card admin-stat-card">
                            <div class="admin-stat-card-icon" style="background: linear-gradient(135deg, #8B6BD1, #6F55B2);">
                                <i class="bi bi-people"></i>
                            </div>
                            <div class="admin-stat-card-content">
                                <div class="admin-stat-card-label">Total Users</div>
                                <div class="admin-stat-card-value"><?= number_format($adminStats['total_users']) ?></div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="card admin-stat-card">
                            <div class="admin-stat-card-icon" style="background: linear-gradient(135deg, #10B981, #059669);">
                                <i class="bi bi-building"></i>
                            </div>
                            <div class="admin-stat-card-content">
                                <div class="admin-stat-card-label">Total Listings</div>
                                <div class="admin-stat-card-value"><?= number_format($adminStats['total_listings']) ?></div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="card admin-stat-card">
                            <div class="admin-stat-card-icon" style="background: linear-gradient(135deg, #3B82F6, #2563EB);">
                                <i class="bi bi-check-circle"></i>
                            </div>
                            <div class="admin-stat-card-content">
                                <div class="admin-stat-card-label">Active Listings</div>
                                <div class="admin-stat-card-value"><?= number_format($adminStats['active_listings']) ?></div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="card admin-stat-card">
                            <div class="admin-stat-card-icon" style="background: linear-gradient(135deg, #F59E0B, #D97706);">
                                <i class="bi bi-calendar-check"></i>
                            </div>
                            <div class="admin-stat-card-content">
                                <div class="admin-stat-card-label">Total Bookings</div>
                                <div class="admin-stat-card-value"><?= number_format($adminStats['total_bookings']) ?></div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="card admin-stat-card">
                            <div class="admin-stat-card-icon" style="background: linear-gradient(135deg, #EF4444, #DC2626);">
                                <i class="bi bi-clock-history"></i>
                            </div>
                            <div class="admin-stat-card-content">
                                <div class="admin-stat-card-label">Pending Bookings</div>
                                <div class="admin-stat-card-value"><?= number_format($adminStats['pending_bookings']) ?></div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="card admin-stat-card">
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

                <!-- Profile Information -->
                <div class="card admin-card">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="bi bi-info-circle me-2"></i>Profile Information</h5>
                    </div>
                    <div class="card-body">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label text-muted">Full Name</label>
                                <div class="form-control-plaintext"><?= htmlspecialchars($admin['name']) ?></div>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label text-muted">Email Address</label>
                                <div class="form-control-plaintext"><?= htmlspecialchars($admin['email']) ?></div>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label text-muted">Phone Number</label>
                                <div class="form-control-plaintext"><?= $admin['phone'] ? htmlspecialchars($admin['phone']) : '<span class="text-muted">Not set</span>' ?></div>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label text-muted">Role</label>
                                <div class="form-control-plaintext">
                                    <span class="badge bg-primary">Administrator</span>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label text-muted">Account Created</label>
                                <div class="form-control-plaintext">
                                    <?= $admin['created_at'] ? date('F j, Y, g:i a', strtotime($admin['created_at'])) : 'N/A' ?>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label text-muted">Last Updated</label>
                                <div class="form-control-plaintext">
                                    <?= $admin['updated_at'] ? date('F j, Y, g:i a', strtotime($admin['updated_at'])) : 'N/A' ?>
                                </div>
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
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="editProfileModalLabel">Edit Profile</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="editProfileForm">
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="editName" class="form-label">Full Name</label>
                        <input type="text" class="form-control" id="editName" name="name" 
                               value="<?= htmlspecialchars($admin['name']) ?>" required>
                    </div>
                    <div class="mb-3">
                        <label for="editEmail" class="form-label">Email Address</label>
                        <input type="email" class="form-control" id="editEmail" name="email" 
                               value="<?= htmlspecialchars($admin['email']) ?>" required>
                    </div>
                    <div class="mb-3">
                        <label for="editPhone" class="form-label">Phone Number</label>
                        <input type="tel" class="form-control" id="editPhone" name="phone" 
                               value="<?= htmlspecialchars($admin['phone'] ?? '') ?>" 
                               placeholder="Enter phone number">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-check-lg me-2"></i>Save Changes
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

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
                const response = await fetch('<?= htmlspecialchars(app_url("app/admin_profile_update.php")) ?>', {
                    method: 'POST',
                    body: formData
                });
                
                const data = await response.json();
                
                if (data.status === 'success') {
                    // Show success message
                    const alert = document.createElement('div');
                    alert.className = 'alert alert-success alert-dismissible fade show';
                    alert.innerHTML = `
                        <i class="bi bi-check-circle me-2"></i>${data.message}
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    `;
                    document.querySelector('.admin-content').insertBefore(alert, document.querySelector('.admin-content').firstChild);
                    
                    // Close modal and reload page after a short delay
                    const modal = bootstrap.Modal.getInstance(document.getElementById('editProfileModal'));
                    modal.hide();
                    
                    setTimeout(() => {
                        window.location.reload();
                    }, 1000);
                } else {
                    alert('Error: ' + (data.message || 'Failed to update profile'));
                    submitBtn.disabled = false;
                    submitBtn.innerHTML = originalText;
                }
            } catch (error) {
                console.error('Error:', error);
                alert('An error occurred while updating your profile');
                submitBtn.disabled = false;
                submitBtn.innerHTML = originalText;
            }
        });
    }
});
</script>

<?php require __DIR__ . '/../app/includes/admin_footer.php'; ?>

