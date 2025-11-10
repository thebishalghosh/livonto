<?php
/**
 * Admin Listings Management Page
 * List, search, filter, approve, and manage property listings
 */

// Start session and load config/functions BEFORE handling POST
if (session_status() === PHP_SESSION_NONE) session_start();
require __DIR__ . '/../app/config.php';
require __DIR__ . '/../app/functions.php';

// Ensure admin is logged in
if (empty($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
    header('Location: ' . app_url('admin/login'));
    exit;
}

// No POST handling here - all handled in separate delete script

// Now include header for regular page display
$pageTitle = "Listings Management";
require __DIR__ . '/../app/includes/admin_header.php';

// Get filter parameters
$search = trim($_GET['search'] ?? '');
$status = $_GET['status'] ?? '';
$city = trim($_GET['city'] ?? '');
$sort = $_GET['sort'] ?? 'created_at';
$order = $_GET['order'] ?? 'DESC';
$page = max(1, intval($_GET['page'] ?? 1));
$perPage = 20;
$offset = ($page - 1) * $perPage;

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
                l.owner_name,
                l.created_at, l.updated_at,
                loc.city, loc.pin_code, loc.complete_address,
                (SELECT MIN(rent_per_month) FROM room_configurations WHERE listing_id = l.id) as min_rent,
                (SELECT MAX(rent_per_month) FROM room_configurations WHERE listing_id = l.id) as max_rent,
                (SELECT COUNT(*) FROM bookings WHERE listing_id = l.id) as bookings_count,
                (SELECT COUNT(*) FROM visit_bookings WHERE listing_id = l.id) as visits_count,
                (SELECT AVG(rating) FROM reviews WHERE listing_id = l.id) as avg_rating,
                (SELECT COUNT(*) FROM reviews WHERE listing_id = l.id) as reviews_count
         FROM listings l
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
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center gap-3">
        <div>
            <h1 class="admin-page-title">Listings Management</h1>
            <p class="admin-page-subtitle text-muted">Manage all property listings</p>
        </div>
        <div class="d-flex flex-column flex-sm-row gap-2 w-100 w-md-auto">
            <a href="<?= htmlspecialchars(app_url('admin/listings/add')) ?>" class="btn btn-primary">
                <i class="bi bi-plus-circle me-2"></i>Add New Listing
            </a>
            <button class="btn btn-outline-primary" onclick="exportListings()">
                <i class="bi bi-download me-2"></i><span class="d-none d-sm-inline">Export</span>
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
                       class="form-control form-control-sm" 
                       name="search" 
                       placeholder="Title or description..." 
                       value="<?= htmlspecialchars($search) ?>"
                       style="height: 38px;">
            </div>
            <div class="col-md-2">
                <label class="form-label">Status</label>
                <select class="form-control form-control-sm filter-select" name="status" style="height: 38px;">
                    <option value="">All Status</option>
                    <option value="draft" <?= $status === 'draft' ? 'selected' : '' ?>>Draft</option>
                    <option value="active" <?= $status === 'active' ? 'selected' : '' ?>>Active</option>
                    <option value="inactive" <?= $status === 'inactive' ? 'selected' : '' ?>>Inactive</option>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label">City</label>
                <input type="text" 
                       class="form-control form-control-sm" 
                       name="city" 
                       placeholder="City name..." 
                       value="<?= htmlspecialchars($city) ?>"
                       style="height: 38px;">
            </div>
            <div class="col-md-2">
                <label class="form-label">Sort By</label>
                <select class="form-control form-control-sm filter-select" name="sort" style="height: 38px;">
                    <option value="created_at" <?= $sort === 'created_at' ? 'selected' : '' ?>>Date</option>
                    <option value="title" <?= $sort === 'title' ? 'selected' : '' ?>>Title</option>
                    <option value="status" <?= $sort === 'status' ? 'selected' : '' ?>>Status</option>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label">Order</label>
                <select class="form-control form-control-sm filter-select" name="order" style="height: 38px;">
                    <option value="DESC" <?= $order === 'DESC' ? 'selected' : '' ?>>Newest First</option>
                    <option value="ASC" <?= $order === 'ASC' ? 'selected' : '' ?>>Oldest First</option>
                </select>
            </div>
            <div class="col-md-1 d-flex align-items-end">
                <button type="submit" class="btn btn-primary btn-sm w-100" style="height: 38px;">
                    <i class="bi bi-search me-1"></i>Filter
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
            <!-- Desktop Table View -->
            <div class="table-responsive listings-table-desktop d-none d-lg-block">
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
                                        <?php if (!empty($listing['cover_image'])): ?>
                                            <?php 
                                            // Build full image URL
                                            $imagePath = $listing['cover_image'];
                                            if (strpos($imagePath, 'http') !== 0 && strpos($imagePath, '//') !== 0) {
                                                // Use app_url() for consistent path handling
                                                $imagePath = app_url($imagePath);
                                            }
                                            ?>
                                            <img src="<?= htmlspecialchars($imagePath) ?>" 
                                                 alt="<?= htmlspecialchars($listing['title']) ?>"
                                                 class="me-2" 
                                                 style="width: 60px; height: 60px; object-fit: cover; border-radius: 4px;"
                                                 onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';">
                                            <div class="bg-light d-flex align-items-center justify-content-center me-2" 
                                                 style="width: 60px; height: 60px; border-radius: 4px; display: none;">
                                                <i class="bi bi-image text-muted"></i>
                                            </div>
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
                                    <?php if (!empty($listing['owner_name'])): ?>
                                        <div><?= htmlspecialchars($listing['owner_name']) ?></div>
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
                                        <a href="<?= htmlspecialchars(app_url('admin/listings/edit?id=' . $listing['id'])) ?>" 
                                           class="btn btn-outline-info"
                                           title="Edit">
                                            <i class="bi bi-pencil"></i>
                                        </a>
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
            
            <!-- Mobile Card View -->
            <div class="listings-cards-mobile d-lg-none">
                <div class="row g-3">
                    <?php foreach ($listings as $listing): ?>
                        <div class="col-12">
                            <div class="admin-card">
                                <div class="admin-card-body">
                                    <div class="d-flex align-items-start gap-3 mb-3">
                                        <?php if (!empty($listing['cover_image'])): ?>
                                            <?php 
                                            $imagePath = $listing['cover_image'];
                                            if (strpos($imagePath, 'http') !== 0) {
                                                $imagePath = app_url('') . '/' . ltrim($imagePath, '/');
                                            }
                                            ?>
                                            <img src="<?= htmlspecialchars($imagePath) ?>" 
                                                 alt="<?= htmlspecialchars($listing['title']) ?>"
                                                 class="rounded" 
                                                 style="width: 80px; height: 80px; object-fit: cover; flex-shrink: 0;"
                                                 onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';">
                                            <div class="bg-light d-flex align-items-center justify-content-center rounded" 
                                                 style="width: 80px; height: 80px; display: none; flex-shrink: 0;">
                                                <i class="bi bi-image text-muted fs-4"></i>
                                            </div>
                                        <?php else: ?>
                                            <div class="bg-light d-flex align-items-center justify-content-center rounded" 
                                                 style="width: 80px; height: 80px; flex-shrink: 0;">
                                                <i class="bi bi-image text-muted fs-4"></i>
                                            </div>
                                        <?php endif; ?>
                                        <div class="flex-grow-1">
                                            <div class="d-flex justify-content-between align-items-start mb-2">
                                                <div>
                                                    <h6 class="mb-1 fw-semibold"><?= htmlspecialchars($listing['title']) ?></h6>
                                                    <div class="small text-muted">ID: #<?= htmlspecialchars($listing['id']) ?></div>
                                                </div>
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
                                            </div>
                                            <div class="small text-muted mb-2">
                                                <?= htmlspecialchars($listing['available_for']) ?> • 
                                                <?= htmlspecialchars($listing['gender_allowed']) ?>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <div class="row g-2 mb-3">
                                        <div class="col-6">
                                            <div class="small text-muted">Location</div>
                                            <div>
                                                <?php if ($listing['city']): ?>
                                                    <i class="bi bi-geo-alt me-1"></i><?= htmlspecialchars($listing['city']) ?>
                                                    <?php if ($listing['pin_code']): ?>
                                                        <br><small class="text-muted"><?= htmlspecialchars($listing['pin_code']) ?></small>
                                                    <?php endif; ?>
                                                <?php else: ?>
                                                    <span class="text-muted">Not set</span>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                        <div class="col-6">
                                            <div class="small text-muted">Owner</div>
                                            <div>
                                                <?php if (!empty($listing['owner_name'])): ?>
                                                    <?= htmlspecialchars($listing['owner_name']) ?>
                                                <?php else: ?>
                                                    <span class="text-muted">No owner</span>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                        <div class="col-6">
                                            <div class="small text-muted">Price Range</div>
                                            <div class="fw-semibold">
                                                <?php if ($listing['min_rent']): ?>
                                                    <?php if ($listing['min_rent'] == $listing['max_rent']): ?>
                                                        ₹<?= number_format($listing['min_rent']) ?>/mo
                                                    <?php else: ?>
                                                        ₹<?= number_format($listing['min_rent']) ?> - ₹<?= number_format($listing['max_rent']) ?>
                                                    <?php endif; ?>
                                                <?php else: ?>
                                                    <span class="text-muted">Not set</span>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                        <div class="col-6">
                                            <div class="small text-muted">Created</div>
                                            <div><?= formatDate($listing['created_at'], 'd M Y') ?></div>
                                            <div class="text-muted small"><?= timeAgo($listing['created_at']) ?></div>
                                        </div>
                                    </div>
                                    
                                    <div class="d-flex flex-wrap gap-2 mb-3 small">
                                        <span><i class="bi bi-calendar-check me-1"></i><?= $listing['bookings_count'] ?> bookings</span>
                                        <span><i class="bi bi-eye me-1"></i><?= $listing['visits_count'] ?> visits</span>
                                        <?php if ($listing['avg_rating']): ?>
                                            <span>
                                                <i class="bi bi-star-fill text-warning me-1"></i>
                                                <?= number_format($listing['avg_rating'], 1) ?> (<?= $listing['reviews_count'] ?>)
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                    
                                    <div class="d-flex flex-column flex-sm-row gap-2">
                                        <button type="button" 
                                                class="btn btn-outline-primary btn-sm flex-fill" 
                                                onclick="viewListing(<?= $listing['id'] ?>)">
                                            <i class="bi bi-eye me-1"></i>View
                                        </button>
                                        <a href="<?= htmlspecialchars(app_url('admin/listings/edit?id=' . $listing['id'])) ?>" 
                                           class="btn btn-outline-info btn-sm flex-fill">
                                            <i class="bi bi-pencil me-1"></i>Edit
                                        </a>
                                        <button type="button" 
                                                class="btn btn-outline-danger btn-sm flex-fill" 
                                                onclick="deleteListing(<?= $listing['id'] ?>, '<?= htmlspecialchars($listing['title'], ENT_QUOTES) ?>')">
                                            <i class="bi bi-trash me-1"></i>Delete
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
            
            <!-- Pagination -->
            <?php if ($totalPages > 1): ?>
                <nav aria-label="Listings pagination" class="mt-4">
                    <ul class="pagination justify-content-center flex-wrap">
                        <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">
                            <a class="page-link" href="<?= app_url('admin/listings?' . http_build_query(array_merge($_GET, ['page' => $page - 1]))) ?>">
                                <i class="bi bi-chevron-left d-md-none"></i>
                                <span class="d-none d-md-inline">Previous</span>
                            </a>
                        </li>
                        <?php for ($i = max(1, $page - 2); $i <= min($totalPages, $page + 2); $i++): ?>
                            <li class="page-item <?= $i === $page ? 'active' : '' ?>">
                                <a class="page-link" href="<?= app_url('admin/listings?' . http_build_query(array_merge($_GET, ['page' => $i]))) ?>"><?= $i ?></a>
                            </li>
                        <?php endfor; ?>
                        <li class="page-item <?= $page >= $totalPages ? 'disabled' : '' ?>">
                            <a class="page-link" href="<?= app_url('admin/listings?' . http_build_query(array_merge($_GET, ['page' => $page + 1]))) ?>">
                                <span class="d-none d-md-inline">Next</span>
                                <i class="bi bi-chevron-right d-md-none"></i>
                            </a>
                        </li>
                    </ul>
                </nav>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</div>

<!-- Delete Listing Modal -->
<div class="modal fade" id="deleteListingModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" action="<?= htmlspecialchars(app_url('admin/listings/delete')) ?>">
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
function deleteListing(listingId, listingTitle) {
    document.getElementById('deleteListingId').value = listingId;
    document.getElementById('deleteListingTitle').textContent = listingTitle;
    new bootstrap.Modal(document.getElementById('deleteListingModal')).show();
}

function viewListing(listingId) {
    window.location.href = '<?= htmlspecialchars(app_url('admin/listings/view')) ?>?id=' + listingId;
}

function exportListings() {
    // TODO: Implement CSV export
    alert('Export functionality - Coming soon!');
}
</script>

<style>
/* Ensure inputs and selects match exactly */
.listings-filters .form-control-sm,
.listings-filters .form-select-sm {
    height: calc(1.5em + 0.5rem + 2px);
    padding: 0.25rem 0.5rem;
    font-size: 0.875rem;
    line-height: 1.5;
    border-radius: 0.25rem;
}

.listings-filters .form-select-sm {
    padding-right: 1.75rem;
    background-position: right 0.25rem center;
}

.listings-filters .btn-sm {
    height: calc(1.5em + 0.5rem + 2px);
    padding: 0.25rem 0.5rem;
    font-size: 0.875rem;
    line-height: 1.5;
}

/* Responsive fixes for listings page */
@media (max-width: 991.98px) {
    /* Hide table on mobile/tablet */
    .listings-table-desktop {
        display: none !important;
    }
    
    /* Show cards on mobile/tablet */
    .listings-cards-mobile {
        display: block !important;
    }
    
    /* Ensure filters stack properly */
    .listings-filters .col-6 {
        margin-bottom: 0.5rem;
    }
    
    /* Ensure admin main content is full width on mobile */
    .admin-main-content {
        padding: 1rem !important;
        max-width: 100% !important;
    }
    
    /* Make cards full width on mobile */
    .admin-card {
        margin-left: 0 !important;
        margin-right: 0 !important;
    }
    
    /* Better filter form on mobile */
    .listings-filters .form-label {
        font-size: 0.875rem;
        margin-bottom: 0.25rem;
    }
    
    /* Statistics cards on mobile */
    .admin-stat-card {
        min-height: auto;
    }
}

/* Tablet adjustments */
@media (max-width: 768px) {
    /* Stack filter form better on tablets */
    .listings-filters .col-md-6 {
        margin-bottom: 0.75rem;
    }
    
    /* Reduce padding in cards */
    .admin-card-body {
        padding: 1rem !important;
    }
    
    /* Statistics cards spacing */
    .row.g-3.mb-4 {
        margin-bottom: 1.5rem !important;
    }
}

/* Mobile phone adjustments */
@media (max-width: 575.98px) {
    /* Full width filters on mobile */
    .listings-filters > div {
        width: 100% !important;
        flex: 0 0 100% !important;
        max-width: 100% !important;
    }
    
    /* Stack all filter inputs on mobile */
    .listings-filters .col-6,
    .listings-filters .col-12 {
        flex: 0 0 100% !important;
        max-width: 100% !important;
    }
    
    /* Page header on mobile */
    .admin-page-header {
        margin-bottom: 1rem !important;
    }
    
    .admin-page-title {
        font-size: 1.5rem !important;
    }
    
    /* Statistics cards on small mobile */
    .admin-stat-card {
        padding: 1rem !important;
    }
    
    .admin-stat-card-icon {
        width: 48px !important;
        height: 48px !important;
        font-size: 1.25rem !important;
    }
    
    .admin-stat-card-value {
        font-size: 1.25rem !important;
    }
}

@media (min-width: 992px) {
    /* Hide cards on desktop */
    .listings-cards-mobile {
        display: none !important;
    }
    
    /* Show table on desktop */
    .listings-table-desktop {
        display: block !important;
    }
}

/* Mobile card improvements */
.listings-cards-mobile .admin-card {
    margin-bottom: 1rem;
}

.listings-cards-mobile .admin-card-body {
    padding: 1rem;
}

/* Ensure table doesn't overflow on smaller screens */
@media (max-width: 991.98px) {
    .table-responsive {
        overflow-x: auto;
        -webkit-overflow-scrolling: touch;
    }
}

/* Better button spacing on mobile */
@media (max-width: 575.98px) {
    .listings-cards-mobile .btn-group,
    .listings-cards-mobile .d-flex.gap-2 {
        flex-direction: column;
    }
    
    .listings-cards-mobile .btn {
        width: 100% !important;
        margin-bottom: 0.5rem;
    }
    
    .listings-cards-mobile .flex-fill {
        flex: 1 1 100% !important;
    }
}

/* Ensure proper spacing on all screen sizes */
.listings-cards-mobile .row.g-3 {
    margin: 0;
}

.listings-cards-mobile .row.g-3 > * {
    padding-right: calc(var(--bs-gutter-x) * 0.5);
    padding-left: calc(var(--bs-gutter-x) * 0.5);
    margin-top: var(--bs-gutter-y);
}
</style>

<?php require __DIR__ . '/../app/includes/admin_footer.php'; ?>

