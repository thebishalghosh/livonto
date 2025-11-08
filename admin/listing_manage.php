<?php
/**
 * Admin Listings Management Page
 * List, search, filter, approve, and manage property listings
 */

$pageTitle = "Listings Management";
require __DIR__ . '/../app/includes/admin_header.php';
require __DIR__ . '/../app/functions.php';

// Get filter parameters
$search = trim($_GET['search'] ?? '');
$status = $_GET['status'] ?? '';
$city = trim($_GET['city'] ?? '');
$sort = $_GET['sort'] ?? 'created_at';
$order = $_GET['order'] ?? 'DESC';
$page = max(1, intval($_GET['page'] ?? 1));
$perPage = 20;
$offset = ($page - 1) * $perPage;

// Handle actions
$action = $_GET['action'] ?? '';
$listingId = intval($_GET['id'] ?? 0);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action && $listingId) {
    // Handle POST actions (approve, reject, delete, change status)
    if ($action === 'approve' && isset($_POST['confirm_approve'])) {
        try {
            $db = db();
            $db->execute("UPDATE listings SET status = 'active' WHERE id = ?", [$listingId]);
            error_log("Admin approved listing: ID {$listingId} by Admin ID {$_SESSION['user_id']}");
            $_SESSION['flash_message'] = 'Listing approved successfully';
            $_SESSION['flash_type'] = 'success';
            header('Location: ' . app_url('admin/listings'));
            exit;
        } catch (Exception $e) {
            error_log("Error approving listing: " . $e->getMessage());
            $_SESSION['flash_message'] = 'Error approving listing';
            $_SESSION['flash_type'] = 'danger';
        }
    } elseif ($action === 'reject' && isset($_POST['confirm_reject'])) {
        try {
            $db = db();
            $db->execute("UPDATE listings SET status = 'inactive' WHERE id = ?", [$listingId]);
            error_log("Admin rejected listing: ID {$listingId} by Admin ID {$_SESSION['user_id']}");
            $_SESSION['flash_message'] = 'Listing rejected';
            $_SESSION['flash_type'] = 'success';
            header('Location: ' . app_url('admin/listings'));
            exit;
        } catch (Exception $e) {
            error_log("Error rejecting listing: " . $e->getMessage());
            $_SESSION['flash_message'] = 'Error rejecting listing';
            $_SESSION['flash_type'] = 'danger';
        }
    } elseif ($action === 'delete' && isset($_POST['confirm_delete'])) {
        try {
            $db = db();
            $listing = $db->fetchOne("SELECT id, title FROM listings WHERE id = ?", [$listingId]);
            if ($listing) {
                $db->execute("DELETE FROM listings WHERE id = ?", [$listingId]);
                error_log("Admin deleted listing: ID {$listingId} ({$listing['title']}) by Admin ID {$_SESSION['user_id']}");
                $_SESSION['flash_message'] = 'Listing deleted successfully';
                $_SESSION['flash_type'] = 'success';
                header('Location: ' . app_url('admin/listings'));
                exit;
            }
        } catch (Exception $e) {
            error_log("Error deleting listing: " . $e->getMessage());
            $_SESSION['flash_message'] = 'Error deleting listing';
            $_SESSION['flash_type'] = 'danger';
        }
    } elseif ($action === 'change_status' && isset($_POST['new_status'])) {
        $newStatus = $_POST['new_status'];
        if (in_array($newStatus, ['draft', 'active', 'inactive'])) {
            try {
                $db = db();
                $db->execute("UPDATE listings SET status = ? WHERE id = ?", [$newStatus, $listingId]);
                error_log("Admin changed listing status: ID {$listingId} to {$newStatus} by Admin ID {$_SESSION['user_id']}");
                $_SESSION['flash_message'] = 'Listing status updated successfully';
                $_SESSION['flash_type'] = 'success';
                header('Location: ' . app_url('admin/listings'));
                exit;
            } catch (Exception $e) {
                error_log("Error changing listing status: " . $e->getMessage());
                $_SESSION['flash_message'] = 'Error updating listing status';
                $_SESSION['flash_type'] = 'danger';
            }
        }
    }
}

try {
    $db = db();
    
    // Initialize defaults in case of error
    $listings = [];
    $stats = ['total' => 0, 'active' => 0, 'pending' => 0, 'inactive' => 0, 'today' => 0, 'this_month' => 0];
    $totalListings = 0;
    $totalPages = 0;
    $cities = [];
    
    // Build WHERE clause
    $where = [];
    $params = [];
    
    if (!empty($search)) {
        $where[] = "(l.title LIKE ? OR l.description LIKE ?)";
        $searchParam = "%{$search}%";
        $params[] = $searchParam;
        $params[] = $searchParam;
    }
    
    if (!empty($status) && in_array($status, ['draft', 'active', 'inactive'])) {
        $where[] = "l.status = ?";
        $params[] = $status;
    }
    
    if (!empty($city)) {
        $where[] = "loc.city LIKE ?";
        $params[] = "%{$city}%";
    }
    
    $whereClause = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';
    
    // Get total count
    $totalListings = $db->fetchValue(
        "SELECT COUNT(DISTINCT l.id) 
         FROM listings l
         LEFT JOIN listing_locations loc ON l.id = loc.listing_id
         {$whereClause}",
        $params
    );
    $totalPages = ceil($totalListings / $perPage);
    
    // Validate sort and order
    $allowedSorts = ['id', 'title', 'status', 'created_at', 'updated_at'];
    $sort = in_array($sort, $allowedSorts) ? $sort : 'created_at';
    $order = strtoupper($order) === 'ASC' ? 'ASC' : 'DESC';
    
    // Get listings with pagination and related data
    $listingsQuery = "SELECT l.id, l.title, l.description, l.status, l.cover_image, l.available_for, l.gender_allowed,
                l.created_at, l.updated_at,
                u.id as owner_id, u.name as owner_name, u.email as owner_email,
                loc.city, loc.pin_code, loc.complete_address,
                (SELECT MIN(rent_per_month) FROM room_configurations WHERE listing_id = l.id) as min_rent,
                (SELECT MAX(rent_per_month) FROM room_configurations WHERE listing_id = l.id) as max_rent,
                (SELECT COUNT(*) FROM bookings WHERE listing_id = l.id) as bookings_count,
                (SELECT COUNT(*) FROM visits WHERE listing_id = l.id) as visits_count,
                (SELECT AVG(rating) FROM reviews WHERE listing_id = l.id) as avg_rating,
                (SELECT COUNT(*) FROM reviews WHERE listing_id = l.id) as reviews_count
         FROM listings l
         LEFT JOIN users u ON l.owner_id = u.id
         LEFT JOIN listing_locations loc ON l.id = loc.listing_id
         {$whereClause}
         ORDER BY l.{$sort} {$order}
         LIMIT ? OFFSET ?";
    
    $listings = $db->fetchAll($listingsQuery, array_merge($params, [$perPage, $offset]));
    
    // Get statistics
    $stats = [
        'total' => $db->fetchValue("SELECT COUNT(*) FROM listings"),
        'active' => $db->fetchValue("SELECT COUNT(*) FROM listings WHERE status = 'active'"),
        'pending' => $db->fetchValue("SELECT COUNT(*) FROM listings WHERE status = 'draft'"),
        'inactive' => $db->fetchValue("SELECT COUNT(*) FROM listings WHERE status = 'inactive'"),
        'today' => $db->fetchValue("SELECT COUNT(*) FROM listings WHERE DATE(created_at) = CURDATE()"),
        'this_month' => $db->fetchValue("SELECT COUNT(*) FROM listings WHERE MONTH(created_at) = MONTH(CURDATE()) AND YEAR(created_at) = YEAR(CURDATE())")
    ];
    
    // Get unique cities for filter
    $cities = $db->fetchAll("SELECT DISTINCT city FROM listing_locations WHERE city IS NOT NULL AND city != '' ORDER BY city");
    
} catch (PDOException $e) {
    error_log("Database error in listing_manage.php: " . $e->getMessage());
    error_log("SQL Error Code: " . $e->getCode());
    if (!isset($listings)) $listings = [];
    if (!isset($stats)) $stats = ['total' => 0, 'active' => 0, 'pending' => 0, 'inactive' => 0, 'today' => 0, 'this_month' => 0];
    if (!isset($totalListings)) $totalListings = 0;
    if (!isset($totalPages)) $totalPages = 0;
    if (!isset($cities)) $cities = [];
    $errorMessage = 'Error loading listings. ' . (getenv('APP_DEBUG') === 'true' ? $e->getMessage() : 'Please try again later.');
    $_SESSION['flash_message'] = $errorMessage;
    $_SESSION['flash_type'] = 'danger';
} catch (Exception $e) {
    error_log("Error in listing_manage.php: " . $e->getMessage());
    if (!isset($listings)) $listings = [];
    if (!isset($stats)) $stats = ['total' => 0, 'active' => 0, 'pending' => 0, 'inactive' => 0, 'today' => 0, 'this_month' => 0];
    if (!isset($totalListings)) $totalListings = 0;
    if (!isset($totalPages)) $totalPages = 0;
    if (!isset($cities)) $cities = [];
    $_SESSION['flash_message'] = 'Error loading listings';
    $_SESSION['flash_type'] = 'danger';
}

// Get flash message
$flashMessage = getFlashMessage();
?>

<!-- Page Header -->
<div class="admin-page-header mb-4">
    <div class="d-flex justify-content-between align-items-center">
        <div>
            <h1 class="admin-page-title">Listings Management</h1>
            <p class="admin-page-subtitle text-muted">Manage all property listings</p>
        </div>
        <div class="d-flex gap-2">
            <a href="<?= htmlspecialchars(app_url('admin/listings/add')) ?>" class="btn btn-primary">
                <i class="bi bi-plus-circle me-2"></i>Add New Listing
            </a>
            <button class="btn btn-primary" onclick="exportListings()">
                <i class="bi bi-download me-2"></i>Export Listings
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
<div class="row g-3 mb-4">
    <div class="col-xl-2 col-md-4 col-6">
        <div class="admin-stat-card">
            <div class="admin-stat-card-icon" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);">
                <i class="bi bi-building"></i>
            </div>
            <div class="admin-stat-card-content">
                <div class="admin-stat-card-label">Total Listings</div>
                <div class="admin-stat-card-value"><?= number_format($stats['total']) ?></div>
            </div>
        </div>
    </div>
    <div class="col-xl-2 col-md-4 col-6">
        <div class="admin-stat-card">
            <div class="admin-stat-card-icon" style="background: linear-gradient(135deg, #43e97b 0%, #38f9d7 100%);">
                <i class="bi bi-check-circle"></i>
            </div>
            <div class="admin-stat-card-content">
                <div class="admin-stat-card-label">Active</div>
                <div class="admin-stat-card-value"><?= number_format($stats['active']) ?></div>
            </div>
        </div>
    </div>
    <div class="col-xl-2 col-md-4 col-6">
        <div class="admin-stat-card">
            <div class="admin-stat-card-icon" style="background: linear-gradient(135deg, #fbbf24 0%, #f59e0b 100%);">
                <i class="bi bi-clock-history"></i>
            </div>
            <div class="admin-stat-card-content">
                <div class="admin-stat-card-label">Pending</div>
                <div class="admin-stat-card-value"><?= number_format($stats['pending']) ?></div>
            </div>
        </div>
    </div>
    <div class="col-xl-2 col-md-4 col-6">
        <div class="admin-stat-card">
            <div class="admin-stat-card-icon" style="background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%);">
                <i class="bi bi-x-circle"></i>
            </div>
            <div class="admin-stat-card-content">
                <div class="admin-stat-card-label">Inactive</div>
                <div class="admin-stat-card-value"><?= number_format($stats['inactive']) ?></div>
            </div>
        </div>
    </div>
    <div class="col-xl-2 col-md-4 col-6">
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
    <div class="col-xl-2 col-md-4 col-6">
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
        <form method="GET" action="<?= htmlspecialchars(app_url('admin/listings')) ?>" class="row g-3">
            <div class="col-md-3">
                <label class="form-label">Search</label>
                <input type="text" 
                       class="form-control" 
                       name="search" 
                       placeholder="Title or description..." 
                       value="<?= htmlspecialchars($search) ?>">
            </div>
            <div class="col-md-2">
                <label class="form-label">Status</label>
                <select class="form-select" name="status">
                    <option value="">All Status</option>
                    <option value="draft" <?= $status === 'draft' ? 'selected' : '' ?>>Draft</option>
                    <option value="active" <?= $status === 'active' ? 'selected' : '' ?>>Active</option>
                    <option value="inactive" <?= $status === 'inactive' ? 'selected' : '' ?>>Inactive</option>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label">City</label>
                <input type="text" 
                       class="form-control" 
                       name="city" 
                       placeholder="City name..." 
                       value="<?= htmlspecialchars($city) ?>">
            </div>
            <div class="col-md-2">
                <label class="form-label">Sort By</label>
                <select class="form-select" name="sort">
                    <option value="created_at" <?= $sort === 'created_at' ? 'selected' : '' ?>>Date</option>
                    <option value="title" <?= $sort === 'title' ? 'selected' : '' ?>>Title</option>
                    <option value="status" <?= $sort === 'status' ? 'selected' : '' ?>>Status</option>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label">Order</label>
                <select class="form-select" name="order">
                    <option value="DESC" <?= $order === 'DESC' ? 'selected' : '' ?>>Newest First</option>
                    <option value="ASC" <?= $order === 'ASC' ? 'selected' : '' ?>>Oldest First</option>
                </select>
            </div>
            <div class="col-md-1 d-flex align-items-end">
                <button type="submit" class="btn btn-primary w-100">
                    <i class="bi bi-search"></i>
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Listings Table -->
<div class="admin-card">
    <div class="admin-card-header">
        <h5 class="admin-card-title">
            <i class="bi bi-building me-2"></i>Listings List
            <span class="badge bg-secondary ms-2"><?= number_format($totalListings) ?></span>
        </h5>
    </div>
    <div class="admin-card-body">
        <?php if (empty($listings)): ?>
            <div class="text-center py-5">
                <i class="bi bi-building fs-1 d-block mb-3 text-muted"></i>
                <h5 class="mb-2">No listings found</h5>
                <p class="text-muted mb-4">
                    <?php if (!empty($search) || !empty($status) || !empty($city)): ?>
                        Try adjusting your filters or search terms.
                    <?php else: ?>
                        Get started by adding your first PG listing.
                    <?php endif; ?>
                </p>
                <?php if (empty($search) && empty($status) && empty($city)): ?>
                    <a href="<?= htmlspecialchars(app_url('admin/listings/add')) ?>" class="btn btn-success">
                        <i class="bi bi-plus-circle me-2"></i>Add New PG Listing
                    </a>
                <?php endif; ?>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover align-middle">
                    <thead>
                        <tr>
                            <th style="width: 80px;">ID</th>
                            <th style="width: 200px;">Listing</th>
                            <th>Location</th>
                            <th>Owner</th>
                            <th>Price Range</th>
                            <th>Status</th>
                            <th>Activity</th>
                            <th>Created</th>
                            <th style="width: 200px;">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($listings as $listing): ?>
                            <tr>
                                <td><?= htmlspecialchars($listing['id']) ?></td>
                                <td>
                                    <div class="d-flex align-items-start">
                                        <?php if ($listing['cover_image']): ?>
                                            <img src="<?= htmlspecialchars($listing['cover_image']) ?>" 
                                                 alt="<?= htmlspecialchars($listing['title']) ?>"
                                                 class="me-2" 
                                                 style="width: 60px; height: 60px; object-fit: cover; border-radius: 4px;">
                                        <?php else: ?>
                                            <div class="bg-light d-flex align-items-center justify-content-center me-2" 
                                                 style="width: 60px; height: 60px; border-radius: 4px;">
                                                <i class="bi bi-image text-muted"></i>
                                            </div>
                                        <?php endif; ?>
                                        <div class="flex-grow-1">
                                            <div class="fw-semibold"><?= htmlspecialchars($listing['title']) ?></div>
                                            <div class="small text-muted">
                                                <?= htmlspecialchars($listing['available_for']) ?> • 
                                                <?= htmlspecialchars($listing['gender_allowed']) ?>
                                            </div>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <?php if ($listing['city']): ?>
                                        <div><i class="bi bi-geo-alt me-1"></i><?= htmlspecialchars($listing['city']) ?></div>
                                        <?php if ($listing['pin_code']): ?>
                                            <small class="text-muted"><?= htmlspecialchars($listing['pin_code']) ?></small>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <span class="text-muted">Not set</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($listing['owner_name']): ?>
                                        <div><?= htmlspecialchars($listing['owner_name']) ?></div>
                                        <small class="text-muted"><?= htmlspecialchars($listing['owner_email']) ?></small>
                                    <?php else: ?>
                                        <span class="text-muted">No owner</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($listing['min_rent']): ?>
                                        <?php if ($listing['min_rent'] == $listing['max_rent']): ?>
                                            ₹<?= number_format($listing['min_rent']) ?>/mo
                                        <?php else: ?>
                                            ₹<?= number_format($listing['min_rent']) ?> - ₹<?= number_format($listing['max_rent']) ?>/mo
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <span class="text-muted">Not set</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php
                                    $statusBadge = match($listing['status']) {
                                        'active' => 'success',
                                        'draft' => 'warning',
                                        'inactive' => 'danger',
                                        default => 'secondary'
                                    };
                                    ?>
                                    <span class="badge bg-<?= $statusBadge ?>">
                                        <?= ucfirst($listing['status']) ?>
                                    </span>
                                </td>
                                <td>
                                    <div class="small">
                                        <div><i class="bi bi-calendar-check me-1"></i><?= $listing['bookings_count'] ?> bookings</div>
                                        <div><i class="bi bi-eye me-1"></i><?= $listing['visits_count'] ?> visits</div>
                                        <?php if ($listing['avg_rating']): ?>
                                            <div>
                                                <i class="bi bi-star-fill text-warning me-1"></i>
                                                <?= number_format($listing['avg_rating'], 1) ?> 
                                                (<?= $listing['reviews_count'] ?>)
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </td>
                                <td>
                                    <div class="small">
                                        <div><?= formatDate($listing['created_at'], 'd M Y') ?></div>
                                        <div class="text-muted"><?= timeAgo($listing['created_at']) ?></div>
                                    </div>
                                </td>
                                <td>
                                    <div class="btn-group btn-group-sm">
                                        <button type="button" 
                                                class="btn btn-outline-primary" 
                                                onclick="viewListing(<?= $listing['id'] ?>)"
                                                title="View Details">
                                            <i class="bi bi-eye"></i>
                                        </button>
                                        <?php if ($listing['status'] === 'draft'): ?>
                                            <button type="button" 
                                                    class="btn btn-outline-success" 
                                                    onclick="approveListing(<?= $listing['id'] ?>, '<?= htmlspecialchars($listing['title'], ENT_QUOTES) ?>')"
                                                    title="Approve">
                                                <i class="bi bi-check"></i>
                                            </button>
                                            <button type="button" 
                                                    class="btn btn-outline-danger" 
                                                    onclick="rejectListing(<?= $listing['id'] ?>, '<?= htmlspecialchars($listing['title'], ENT_QUOTES) ?>')"
                                                    title="Reject">
                                                <i class="bi bi-x"></i>
                                            </button>
                                        <?php else: ?>
                                            <button type="button" 
                                                    class="btn btn-outline-secondary" 
                                                    onclick="changeStatus(<?= $listing['id'] ?>, '<?= htmlspecialchars($listing['status'], ENT_QUOTES) ?>')"
                                                    title="Change Status">
                                                <i class="bi bi-arrow-repeat"></i>
                                            </button>
                                        <?php endif; ?>
                                        <button type="button" 
                                                class="btn btn-outline-danger" 
                                                onclick="deleteListing(<?= $listing['id'] ?>, '<?= htmlspecialchars($listing['title'], ENT_QUOTES) ?>')"
                                                title="Delete">
                                            <i class="bi bi-trash"></i>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            
            <!-- Pagination -->
            <?php if ($totalPages > 1): ?>
                <nav aria-label="Listings pagination">
                    <ul class="pagination justify-content-center mt-4">
                        <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">
                            <a class="page-link" href="<?= app_url('admin/listings?' . http_build_query(array_merge($_GET, ['page' => $page - 1]))) ?>">Previous</a>
                        </li>
                        <?php for ($i = max(1, $page - 2); $i <= min($totalPages, $page + 2); $i++): ?>
                            <li class="page-item <?= $i === $page ? 'active' : '' ?>">
                                <a class="page-link" href="<?= app_url('admin/listings?' . http_build_query(array_merge($_GET, ['page' => $i]))) ?>"><?= $i ?></a>
                            </li>
                        <?php endfor; ?>
                        <li class="page-item <?= $page >= $totalPages ? 'disabled' : '' ?>">
                            <a class="page-link" href="<?= app_url('admin/listings?' . http_build_query(array_merge($_GET, ['page' => $page + 1]))) ?>">Next</a>
                        </li>
                    </ul>
                </nav>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</div>

<!-- Approve Listing Modal -->
<div class="modal fade" id="approveListingModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" action="<?= app_url('admin/listings') ?>">
                <input type="hidden" name="action" value="approve">
                <input type="hidden" name="id" id="approveListingId">
                <input type="hidden" name="confirm_approve" value="1">
                <div class="modal-header">
                    <h5 class="modal-title text-success">Approve Listing</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p>Are you sure you want to approve listing: <strong id="approveListingTitle"></strong>?</p>
                    <p class="text-muted small">This will make the listing visible to all users.</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-success">Approve Listing</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Reject Listing Modal -->
<div class="modal fade" id="rejectListingModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" action="<?= app_url('admin/listings') ?>">
                <input type="hidden" name="action" value="reject">
                <input type="hidden" name="id" id="rejectListingId">
                <input type="hidden" name="confirm_reject" value="1">
                <div class="modal-header">
                    <h5 class="modal-title text-danger">Reject Listing</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p>Are you sure you want to reject listing: <strong id="rejectListingTitle"></strong>?</p>
                    <p class="text-danger small">This will mark the listing as inactive.</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-danger">Reject Listing</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Change Status Modal -->
<div class="modal fade" id="changeStatusModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" action="<?= app_url('admin/listings') ?>">
                <input type="hidden" name="action" value="change_status">
                <input type="hidden" name="id" id="changeStatusListingId">
                <div class="modal-header">
                    <h5 class="modal-title">Change Listing Status</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p>Select new status for listing: <strong id="changeStatusListingTitle"></strong></p>
                    <div class="mb-3">
                        <label class="form-label">Status</label>
                        <select class="form-select" name="new_status" id="newStatusSelect" required>
                            <option value="draft">Draft</option>
                            <option value="active">Active</option>
                            <option value="inactive">Inactive</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Change Status</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Delete Listing Modal -->
<div class="modal fade" id="deleteListingModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" action="<?= app_url('admin/listings') ?>">
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="id" id="deleteListingId">
                <input type="hidden" name="confirm_delete" value="1">
                <div class="modal-header">
                    <h5 class="modal-title text-danger">Delete Listing</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p>Are you sure you want to delete listing: <strong id="deleteListingTitle"></strong>?</p>
                    <p class="text-danger small">This action cannot be undone. All related data (bookings, reviews, etc.) will be deleted.</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-danger">Delete Listing</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function approveListing(listingId, listingTitle) {
    document.getElementById('approveListingId').value = listingId;
    document.getElementById('approveListingTitle').textContent = listingTitle;
    new bootstrap.Modal(document.getElementById('approveListingModal')).show();
}

function rejectListing(listingId, listingTitle) {
    document.getElementById('rejectListingId').value = listingId;
    document.getElementById('rejectListingTitle').textContent = listingTitle;
    new bootstrap.Modal(document.getElementById('rejectListingModal')).show();
}

function changeStatus(listingId, currentStatus) {
    document.getElementById('changeStatusListingId').value = listingId;
    document.getElementById('newStatusSelect').value = currentStatus;
    document.getElementById('changeStatusListingTitle').textContent = 'Listing #' + listingId;
    new bootstrap.Modal(document.getElementById('changeStatusModal')).show();
}

function deleteListing(listingId, listingTitle) {
    document.getElementById('deleteListingId').value = listingId;
    document.getElementById('deleteListingTitle').textContent = listingTitle;
    new bootstrap.Modal(document.getElementById('deleteListingModal')).show();
}

function viewListing(listingId) {
    // TODO: Implement listing detail view
    alert('Listing details view - Coming soon!');
}

function exportListings() {
    // TODO: Implement CSV export
    alert('Export functionality - Coming soon!');
}
</script>

<?php require __DIR__ . '/../app/includes/admin_footer.php'; ?>

