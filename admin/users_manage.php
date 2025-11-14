<?php
/**
 * Admin Users Management Page
 * List, search, filter, and manage users
 */

$pageTitle = "Users Management";
require __DIR__ . '/../app/includes/admin_header.php';
// functions.php is already included in admin_header.php

// Get filter parameters
$search = trim($_GET['search'] ?? '');
$role = $_GET['role'] ?? '';
$sort = $_GET['sort'] ?? 'created_at';
$order = $_GET['order'] ?? 'DESC';
$page = max(1, intval($_GET['page'] ?? 1));
$perPage = 20;
$offset = ($page - 1) * $perPage;

// Handle actions
$action = $_GET['action'] ?? '';
$userId = intval($_GET['id'] ?? 0);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action && $userId) {
    // Handle POST actions (delete, change role, etc.)
    if ($action === 'delete' && isset($_POST['confirm_delete'])) {
        try {
            $db = db();
            // Check if user exists
            $user = $db->fetchOne("SELECT id, email FROM users WHERE id = ?", [$userId]);
            if ($user) {
                // Delete user (cascade will handle related records)
                $db->execute("DELETE FROM users WHERE id = ?", [$userId]);
                error_log("Admin deleted user: ID {$userId} ({$user['email']}) by Admin ID {$_SESSION['user_id']}");
                $_SESSION['flash_message'] = 'User deleted successfully';
                $_SESSION['flash_type'] = 'success';
                header('Location: ' . app_url('admin/users'));
                exit;
            }
        } catch (Exception $e) {
            error_log("Error deleting user: " . $e->getMessage());
            $_SESSION['flash_message'] = 'Error deleting user';
            $_SESSION['flash_type'] = 'danger';
        }
    } elseif ($action === 'change_role' && isset($_POST['new_role'])) {
        $newRole = $_POST['new_role'];
        if (in_array($newRole, ['user', 'admin'])) {
            try {
                $db = db();
                $db->execute("UPDATE users SET role = ? WHERE id = ?", [$newRole, $userId]);
                error_log("Admin changed user role: ID {$userId} to {$newRole} by Admin ID {$_SESSION['user_id']}");
                $_SESSION['flash_message'] = 'User role updated successfully';
                $_SESSION['flash_type'] = 'success';
                header('Location: ' . app_url('admin/users'));
                exit;
            } catch (Exception $e) {
                error_log("Error changing user role: " . $e->getMessage());
                $_SESSION['flash_message'] = 'Error updating user role';
                $_SESSION['flash_type'] = 'danger';
            }
        }
    }
}

try {
    $db = db();
    
    // Build WHERE clause
    $where = [];
    $params = [];
    
    if (!empty($search)) {
        $where[] = "(name LIKE ? OR email LIKE ? OR phone LIKE ?)";
        $searchParam = "%{$search}%";
        $params[] = $searchParam;
        $params[] = $searchParam;
        $params[] = $searchParam;
    }
    
    if (!empty($role) && in_array($role, ['user', 'admin'])) {
        $where[] = "role = ?";
        $params[] = $role;
    }
    
    $whereClause = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';
    
    // Get total count
    $totalUsers = $db->fetchValue("SELECT COUNT(*) FROM users {$whereClause}", $params);
    $totalPages = ceil($totalUsers / $perPage);
    
    // Validate sort and order
    $allowedSorts = ['id', 'name', 'email', 'role', 'created_at'];
    $sort = in_array($sort, $allowedSorts) ? $sort : 'created_at';
    $order = strtoupper($order) === 'ASC' ? 'ASC' : 'DESC';
    
    // Get users with pagination
    $users = $db->fetchAll(
        "SELECT u.id, u.name, u.email, u.phone, u.role, u.referral_code, u.google_id, u.profile_image,
                u.created_at, u.updated_at,
                (SELECT COUNT(*) FROM listings WHERE owner_name = u.name) as listings_count,
                (SELECT COUNT(*) FROM bookings WHERE user_id = u.id) as bookings_count,
                (SELECT name FROM users WHERE id = u.referred_by) as referred_by_name
         FROM users u
         {$whereClause}
         ORDER BY u.{$sort} {$order}
         LIMIT ? OFFSET ?",
        array_merge($params, [$perPage, $offset])
    );
    
    // Get statistics
    $stats = [
        'total' => $db->fetchValue("SELECT COUNT(*) FROM users"),
        'users' => $db->fetchValue("SELECT COUNT(*) FROM users WHERE role = 'user'"),
        'admins' => $db->fetchValue("SELECT COUNT(*) FROM users WHERE role = 'admin'"),
        'with_listings' => $db->fetchValue("SELECT COUNT(DISTINCT owner_name) FROM listings WHERE owner_name IS NOT NULL AND owner_name != ''"),
        'today' => $db->fetchValue("SELECT COUNT(*) FROM users WHERE DATE(created_at) = CURDATE()"),
        'this_month' => $db->fetchValue("SELECT COUNT(*) FROM users WHERE MONTH(created_at) = MONTH(CURDATE()) AND YEAR(created_at) = YEAR(CURDATE())")
    ];
    
} catch (Exception $e) {
    error_log("Error in users_manage.php: " . $e->getMessage());
    $users = [];
    $stats = ['total' => 0, 'users' => 0, 'admins' => 0, 'with_listings' => 0, 'today' => 0, 'this_month' => 0];
    $totalUsers = 0;
    $totalPages = 0;
    $_SESSION['flash_message'] = 'Error loading users';
    $_SESSION['flash_type'] = 'danger';
}

// Get flash message
$flashMessage = getFlashMessage();
?>

<!-- Page Header -->
<div class="admin-page-header mb-4">
    <div class="d-flex justify-content-between align-items-center">
        <div>
            <h1 class="admin-page-title">Users Management</h1>
            <p class="admin-page-subtitle text-muted">Manage all registered users</p>
        </div>
        <div>
            <button class="btn btn-primary" onclick="exportUsers()">
                <i class="bi bi-download me-2"></i>Export Users
            </button>
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

<!-- Statistics Cards -->
<!-- Row 1: Main Statistics -->
<div class="row g-3 mb-3">
    <div class="col-xl-4 col-lg-4 col-md-4 col-sm-6 col-12">
        <div class="admin-stat-card">
            <div class="admin-stat-card-icon" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);">
                <i class="bi bi-people"></i>
            </div>
            <div class="admin-stat-card-content">
                <div class="admin-stat-card-label">Total Users</div>
                <div class="admin-stat-card-value"><?= number_format($stats['total']) ?></div>
            </div>
        </div>
    </div>
    <div class="col-xl-4 col-lg-4 col-md-4 col-sm-6 col-12">
        <div class="admin-stat-card">
            <div class="admin-stat-card-icon" style="background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);">
                <i class="bi bi-person"></i>
            </div>
            <div class="admin-stat-card-content">
                <div class="admin-stat-card-label">Regular Users</div>
                <div class="admin-stat-card-value"><?= number_format($stats['users']) ?></div>
            </div>
        </div>
    </div>
    <div class="col-xl-4 col-lg-4 col-md-4 col-sm-6 col-12">
        <div class="admin-stat-card">
            <div class="admin-stat-card-icon" style="background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);">
                <i class="bi bi-shield-check"></i>
            </div>
            <div class="admin-stat-card-content">
                <div class="admin-stat-card-label">Admins</div>
                <div class="admin-stat-card-value"><?= number_format($stats['admins']) ?></div>
            </div>
        </div>
    </div>
</div>

<!-- Row 2: Additional Statistics -->
<div class="row g-3 mb-4">
    <div class="col-xl-4 col-lg-4 col-md-4 col-sm-6 col-12">
        <div class="admin-stat-card">
            <div class="admin-stat-card-icon" style="background: linear-gradient(135deg, #43e97b 0%, #38f9d7 100%);">
                <i class="bi bi-building"></i>
            </div>
            <div class="admin-stat-card-content">
                <div class="admin-stat-card-label">With Listings</div>
                <div class="admin-stat-card-value"><?= number_format($stats['with_listings']) ?></div>
            </div>
        </div>
    </div>
    <div class="col-xl-4 col-lg-4 col-md-4 col-sm-6 col-12">
        <div class="admin-stat-card">
            <div class="admin-stat-card-icon" style="background: linear-gradient(135deg, #fa709a 0%, #fee140 100%);">
                <i class="bi bi-calendar-day"></i>
            </div>
            <div class="admin-stat-card-content">
                <div class="admin-stat-card-label">Today</div>
                <div class="admin-stat-card-value"><?= number_format($stats['today']) ?></div>
            </div>
        </div>
    </div>
    <div class="col-xl-4 col-lg-4 col-md-4 col-sm-6 col-12">
        <div class="admin-stat-card">
            <div class="admin-stat-card-icon" style="background: linear-gradient(135deg, #30cfd0 0%, #330867 100%);">
                <i class="bi bi-calendar-month"></i>
            </div>
            <div class="admin-stat-card-content">
                <div class="admin-stat-card-label">This Month</div>
                <div class="admin-stat-card-value"><?= number_format($stats['this_month']) ?></div>
            </div>
        </div>
    </div>
</div>

<!-- Filters and Search -->
<div class="admin-card mb-4">
    <div class="admin-card-body">
        <form method="GET" action="<?= htmlspecialchars(app_url('admin/users')) ?>" class="row g-3">
            <div class="col-md-4">
                <label class="form-label">Search</label>
                <input type="text" 
                       class="form-control form-control-sm" 
                       name="search" 
                       placeholder="Name, email, or phone..." 
                       value="<?= htmlspecialchars($search) ?>"
                       style="height: 38px;">
            </div>
            <div class="col-md-2">
                <label class="form-label">Role</label>
                <select class="form-control form-control-sm filter-select" name="role" style="height: 38px;">
                    <option value="">All Roles</option>
                    <option value="user" <?= $role === 'user' ? 'selected' : '' ?>>User</option>
                    <option value="admin" <?= $role === 'admin' ? 'selected' : '' ?>>Admin</option>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label">Sort By</label>
                <select class="form-control form-control-sm filter-select" name="sort" style="height: 38px;">
                    <option value="created_at" <?= $sort === 'created_at' ? 'selected' : '' ?>>Date</option>
                    <option value="name" <?= $sort === 'name' ? 'selected' : '' ?>>Name</option>
                    <option value="email" <?= $sort === 'email' ? 'selected' : '' ?>>Email</option>
                    <option value="role" <?= $sort === 'role' ? 'selected' : '' ?>>Role</option>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label">Order</label>
                <select class="form-control form-control-sm filter-select" name="order" style="height: 38px;">
                    <option value="DESC" <?= $order === 'DESC' ? 'selected' : '' ?>>Newest First</option>
                    <option value="ASC" <?= $order === 'ASC' ? 'selected' : '' ?>>Oldest First</option>
                </select>
            </div>
            <div class="col-md-2 d-flex align-items-end">
                <button type="submit" class="btn btn-primary btn-sm w-100" style="height: 38px;">
                    <i class="bi bi-search me-1"></i>Filter
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Users Table -->
<div class="admin-card">
    <div class="admin-card-header">
        <h5 class="admin-card-title">
            <i class="bi bi-people me-2"></i>Users List
            <span class="badge bg-secondary ms-2"><?= number_format($totalUsers) ?></span>
        </h5>
    </div>
    <div class="admin-card-body">
        <?php if (empty($users)): ?>
            <div class="text-center py-5 text-muted">
                <i class="bi bi-inbox fs-1 d-block mb-2"></i>
                <p>No users found</p>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover align-middle">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>User</th>
                            <th>Contact</th>
                            <th>Role</th>
                            <th>Referral Code</th>
                            <th>Activity</th>
                            <th>Joined</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($users as $user): ?>
                            <tr>
                                <td><?= htmlspecialchars($user['id']) ?></td>
                                <td>
                                    <div class="d-flex align-items-center">
                                        <?php
                                        // Get profile image URL - simple logic
                                        $profileImageUrl = null;
                                        $hasProfileImage = false;
                                        if (!empty($user['profile_image']) && $user['profile_image'] !== null && trim($user['profile_image']) !== '') {
                                            // If user has google_id, use profile_image value directly
                                            // It could be a Google URL or a cached local path
                                            if (!empty($user['google_id'])) {
                                                // Check if it's already a full URL (Google URL) or a local path
                                                if (strpos($user['profile_image'], 'http://') === 0 || strpos($user['profile_image'], 'https://') === 0) {
                                                    $profileImageUrl = $user['profile_image']; // Google URL
                                                } else {
                                                    $profileImageUrl = app_url($user['profile_image']); // Cached local path
                                                }
                                            } else {
                                                // For non-Google users, use app_url for local paths
                                                $profileImageUrl = app_url($user['profile_image']);
                                            }
                                            $hasProfileImage = true;
                                        }
                                        ?>
                                        <div class="admin-user-avatar me-2" style="position: relative; width: 40px; height: 40px; border-radius: 50%; overflow: hidden; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); display: flex; align-items: center; justify-content: center; flex-shrink: 0;">
                                            <?php if ($hasProfileImage && !empty($profileImageUrl)): ?>
                                                <img src="<?= htmlspecialchars($profileImageUrl) ?>" 
                                                     alt="<?= htmlspecialchars($user['name']) ?>" 
                                                     style="width: 100%; height: 100%; object-fit: cover;"
                                                     onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';">
                                                <i class="bi bi-person-circle" style="display: none; font-size: 24px; color: white; position: absolute;"></i>
                                            <?php else: ?>
                                                <i class="bi bi-person-circle" style="font-size: 24px; color: white;"></i>
                                            <?php endif; ?>
                                        </div>
                                        <div>
                                            <div class="fw-semibold"><?= htmlspecialchars($user['name']) ?></div>
                                            <?php if ($user['google_id']): ?>
                                                <small class="text-muted">
                                                    <i class="bi bi-google"></i> Google
                                                </small>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <div><?= htmlspecialchars($user['email']) ?></div>
                                    <?php if ($user['phone']): ?>
                                        <small class="text-muted"><?= htmlspecialchars($user['phone']) ?></small>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="badge bg-<?= $user['role'] === 'admin' ? 'danger' : 'primary' ?>">
                                        <?= ucfirst($user['role']) ?>
                                    </span>
                                </td>
                                <td>
                                    <?php if ($user['referral_code']): ?>
                                        <code><?= htmlspecialchars($user['referral_code']) ?></code>
                                    <?php else: ?>
                                        <span class="text-muted">-</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div class="small">
                                        <div><i class="bi bi-building me-1"></i><?= $user['listings_count'] ?> listings</div>
                                        <div><i class="bi bi-calendar-check me-1"></i><?= $user['bookings_count'] ?> bookings</div>
                                    </div>
                                </td>
                                <td>
                                    <div class="small">
                                        <div><?= formatDate($user['created_at'], 'd M Y') ?></div>
                                        <div class="text-muted"><?= timeAgo($user['created_at']) ?></div>
                                    </div>
                                </td>
                                <td>
                                    <div class="btn-group btn-group-sm">
                                        <button type="button" 
                                                class="btn btn-outline-primary" 
                                                onclick="viewUser(<?= $user['id'] ?>)"
                                                title="View Details">
                                            <i class="bi bi-eye"></i>
                                        </button>
                                        <?php if ($user['role'] !== 'admin' || $user['id'] != $_SESSION['user_id']): ?>
                                            <button type="button" 
                                                    class="btn btn-outline-secondary" 
                                                    onclick="changeRole(<?= $user['id'] ?>, '<?= htmlspecialchars($user['role'], ENT_QUOTES) ?>')"
                                                    title="Change Role">
                                                <i class="bi bi-person-badge"></i>
                                            </button>
                                            <button type="button" 
                                                    class="btn btn-outline-danger" 
                                                    onclick="deleteUser(<?= $user['id'] ?>, '<?= htmlspecialchars($user['name'], ENT_QUOTES) ?>')"
                                                    title="Delete User">
                                                <i class="bi bi-trash"></i>
                                            </button>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            
            <!-- Pagination -->
            <?= renderAdminPagination($page, $totalPages, $totalUsers, $perPage, $offset, app_url('admin/users'), 'Users pagination', true) ?>
        <?php endif; ?>
    </div>
</div>

<!-- Change Role Modal -->
<div class="modal fade" id="changeRoleModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" action="<?= app_url('admin/users') ?>">
                <input type="hidden" name="action" value="change_role">
                <input type="hidden" name="id" id="changeRoleUserId">
                <div class="modal-header">
                    <h5 class="modal-title">Change User Role</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p>Select new role for user: <strong id="changeRoleUserName"></strong></p>
                    <div class="mb-3">
                        <label class="form-label">Role</label>
                        <select class="form-control filter-select" name="new_role" id="newRoleSelect" required>
                            <option value="user">User</option>
                            <option value="admin">Admin</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Change Role</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Delete User Modal -->
<div class="modal fade" id="deleteUserModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" action="<?= app_url('admin/users') ?>">
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="id" id="deleteUserId">
                <input type="hidden" name="confirm_delete" value="1">
                <div class="modal-header">
                    <h5 class="modal-title text-danger">Delete User</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p>Are you sure you want to delete user: <strong id="deleteUserName"></strong>?</p>
                    <p class="text-danger small">This action cannot be undone. All related data will be deleted.</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-danger">Delete User</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function changeRole(userId, currentRole) {
    document.getElementById('changeRoleUserId').value = userId;
    document.getElementById('newRoleSelect').value = currentRole;
    document.getElementById('changeRoleUserName').textContent = 'User #' + userId;
    new bootstrap.Modal(document.getElementById('changeRoleModal')).show();
}

function deleteUser(userId, userName) {
    document.getElementById('deleteUserId').value = userId;
    document.getElementById('deleteUserName').textContent = userName;
    new bootstrap.Modal(document.getElementById('deleteUserModal')).show();
}

function viewUser(userId) {
    window.location.href = '<?= htmlspecialchars(app_url('admin/users/view')) ?>?id=' + userId;
}

function exportUsers() {
    // TODO: Implement CSV export
    alert('Export functionality - Coming soon!');
}
</script>

<?php require __DIR__ . '/../app/includes/admin_footer.php'; ?>

