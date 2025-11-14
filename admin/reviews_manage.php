<?php
/**
 * Admin Reviews Management Page
 * View and manage all reviews
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require __DIR__ . '/../app/config.php';
require_once __DIR__ . '/../app/functions.php';

if (empty($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
    header('Location: ' . app_url('admin/login'));
    exit;
}

$action = $_GET['action'] ?? '';
$reviewId = intval($_GET['id'] ?? 0);

// Handle delete action
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'delete' && $reviewId) {
    try {
        $db = db();
        
        $review = $db->fetchOne("SELECT id FROM reviews WHERE id = ?", [$reviewId]);
        if (!$review) {
            $_SESSION['flash_message'] = 'Review not found';
            $_SESSION['flash_type'] = 'danger';
            header('Location: ' . app_url('admin/reviews'));
            exit;
        }
        
        if (isset($_POST['confirm_delete'])) {
            // Delete review
            $db->execute("DELETE FROM reviews WHERE id = ?", [$reviewId]);
            
            $_SESSION['flash_message'] = 'Review deleted successfully';
            $_SESSION['flash_type'] = 'success';
            header('Location: ' . app_url('admin/reviews'));
            exit;
        }
    } catch (Exception $e) {
        $_SESSION['flash_message'] = 'Error deleting review: ' . $e->getMessage();
        $_SESSION['flash_type'] = 'danger';
    }
}

$pageTitle = "Reviews Management";
require __DIR__ . '/../app/includes/admin_header.php';

$search = trim($_GET['search'] ?? '');
$rating = $_GET['rating'] ?? '';
$sort = $_GET['sort'] ?? 'r.created_at';
$order = $_GET['order'] ?? 'DESC';
$page = max(1, intval($_GET['page'] ?? 1));
$perPage = 20;
$offset = ($page - 1) * $perPage;

try {
    $db = db();
    
    $where = [];
    $params = [];
    
    if (!empty($search)) {
        $where[] = "(u.name LIKE ? OR u.email LIKE ? OR l.title LIKE ? OR r.comment LIKE ?)";
        $searchParam = "%{$search}%";
        $params[] = $searchParam;
        $params[] = $searchParam;
        $params[] = $searchParam;
        $params[] = $searchParam;
    }
    
    if (!empty($rating) && in_array($rating, ['1', '2', '3', '4', '5'])) {
        $where[] = "r.rating = ?";
        $params[] = intval($rating);
    }
    
    $whereClause = !empty($where) ? "WHERE " . implode(" AND ", $where) : "";
    
    $totalReviews = $db->fetchValue(
        "SELECT COUNT(*) FROM reviews r
         LEFT JOIN users u ON r.user_id = u.id
         LEFT JOIN listings l ON r.listing_id = l.id
         {$whereClause}",
        $params
    ) ?: 0;
    
    $totalPages = ceil($totalReviews / $perPage);
    
    $allowedSorts = ['r.id', 'r.rating', 'r.created_at', 'u.name', 'l.title'];
    $sort = in_array($sort, $allowedSorts) ? $sort : 'r.created_at';
    $order = strtoupper($order) === 'ASC' ? 'ASC' : 'DESC';
    
    $reviews = $db->fetchAll(
        "SELECT r.id, r.user_id, r.listing_id, r.rating, r.comment, r.created_at,
                u.name as user_name, u.email as user_email, u.profile_image,
                l.title as listing_title, l.status as listing_status,
                loc.city as listing_city
         FROM reviews r
         LEFT JOIN users u ON r.user_id = u.id
         LEFT JOIN listings l ON r.listing_id = l.id
         LEFT JOIN listing_locations loc ON l.id = loc.listing_id
         {$whereClause}
         ORDER BY {$sort} {$order}
         LIMIT ? OFFSET ?",
        array_merge($params, [$perPage, $offset])
    );
    
    // Get statistics
    $statsRow = $db->fetchOne(
        "SELECT 
            COUNT(*) as total,
            AVG(rating) as avg_rating,
            SUM(CASE WHEN rating = 5 THEN 1 ELSE 0 END) as rating_5,
            SUM(CASE WHEN rating = 4 THEN 1 ELSE 0 END) as rating_4,
            SUM(CASE WHEN rating = 3 THEN 1 ELSE 0 END) as rating_3,
            SUM(CASE WHEN rating = 2 THEN 1 ELSE 0 END) as rating_2,
            SUM(CASE WHEN rating = 1 THEN 1 ELSE 0 END) as rating_1,
            SUM(CASE WHEN DATE(created_at) = CURDATE() THEN 1 ELSE 0 END) as today,
            SUM(CASE WHEN MONTH(created_at) = MONTH(CURDATE()) AND YEAR(created_at) = YEAR(CURDATE()) THEN 1 ELSE 0 END) as this_month
         FROM reviews"
    );
    $stats = [
        'total' => intval($statsRow['total'] ?? 0),
        'avg_rating' => floatval($statsRow['avg_rating'] ?? 0),
        'rating_5' => intval($statsRow['rating_5'] ?? 0),
        'rating_4' => intval($statsRow['rating_4'] ?? 0),
        'rating_3' => intval($statsRow['rating_3'] ?? 0),
        'rating_2' => intval($statsRow['rating_2'] ?? 0),
        'rating_1' => intval($statsRow['rating_1'] ?? 0),
        'today' => intval($statsRow['today'] ?? 0),
        'this_month' => intval($statsRow['this_month'] ?? 0)
    ];
    
} catch (Exception $e) {
    error_log("Error in reviews_manage.php: " . $e->getMessage());
    $reviews = [];
    $stats = [
        'total' => 0, 
        'avg_rating' => 0, 
        'rating_5' => 0, 
        'rating_4' => 0, 
        'rating_3' => 0, 
        'rating_2' => 0, 
        'rating_1' => 0, 
        'today' => 0, 
        'this_month' => 0
    ];
    $totalReviews = 0;
    $totalPages = 0;
    $_SESSION['flash_message'] = 'Error loading reviews';
    $_SESSION['flash_type'] = 'danger';
}
?>

<!-- Page Header -->
<div class="admin-page-header mb-4">
    <div class="d-flex justify-content-between align-items-center">
        <div>
            <h1 class="admin-page-title">Reviews Management</h1>
            <p class="admin-page-subtitle text-muted">Manage all user reviews</p>
        </div>
    </div>
</div>

<!-- Statistics Cards -->
<div class="row g-3 mb-4">
    <div class="col-xl-3 col-md-4 col-6">
        <div class="admin-stat-card">
            <div class="admin-stat-card-icon" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);">
                <i class="bi bi-star"></i>
            </div>
            <div class="admin-stat-card-content">
                <div class="admin-stat-card-label">Total Reviews</div>
                <div class="admin-stat-card-value"><?= number_format($stats['total']) ?></div>
            </div>
        </div>
    </div>
    <div class="col-xl-3 col-md-4 col-6">
        <div class="admin-stat-card">
            <div class="admin-stat-card-icon" style="background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);">
                <i class="bi bi-star-fill"></i>
            </div>
            <div class="admin-stat-card-content">
                <div class="admin-stat-card-label">Average Rating</div>
                <div class="admin-stat-card-value"><?= number_format($stats['avg_rating'], 1) ?></div>
            </div>
        </div>
    </div>
    <div class="col-xl-3 col-md-4 col-6">
        <div class="admin-stat-card">
            <div class="admin-stat-card-icon" style="background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);">
                <i class="bi bi-calendar-day"></i>
            </div>
            <div class="admin-stat-card-content">
                <div class="admin-stat-card-label">Today</div>
                <div class="admin-stat-card-value"><?= number_format($stats['today']) ?></div>
            </div>
        </div>
    </div>
    <div class="col-xl-3 col-md-4 col-6">
        <div class="admin-stat-card">
            <div class="admin-stat-card-icon" style="background: linear-gradient(135deg, #43e97b 0%, #38f9d7 100%);">
                <i class="bi bi-calendar-month"></i>
            </div>
            <div class="admin-stat-card-content">
                <div class="admin-stat-card-label">This Month</div>
                <div class="admin-stat-card-value"><?= number_format($stats['this_month']) ?></div>
            </div>
        </div>
    </div>
</div>

<!-- Rating Distribution -->
<div class="admin-card mb-4">
    <div class="admin-card-body">
        <h5 class="mb-3">Rating Distribution</h5>
        <div class="row g-3">
            <div class="col-md-2 col-6">
                <div class="text-center p-3 rounded" style="background: var(--accent);">
                    <div class="h4 mb-1 text-warning">
                        <i class="bi bi-star-fill"></i> 5
                    </div>
                    <div class="small text-muted"><?= number_format($stats['rating_5']) ?> reviews</div>
                </div>
            </div>
            <div class="col-md-2 col-6">
                <div class="text-center p-3 rounded" style="background: var(--accent);">
                    <div class="h4 mb-1 text-warning">
                        <i class="bi bi-star-fill"></i> 4
                    </div>
                    <div class="small text-muted"><?= number_format($stats['rating_4']) ?> reviews</div>
                </div>
            </div>
            <div class="col-md-2 col-6">
                <div class="text-center p-3 rounded" style="background: var(--accent);">
                    <div class="h4 mb-1 text-warning">
                        <i class="bi bi-star-fill"></i> 3
                    </div>
                    <div class="small text-muted"><?= number_format($stats['rating_3']) ?> reviews</div>
                </div>
            </div>
            <div class="col-md-2 col-6">
                <div class="text-center p-3 rounded" style="background: var(--accent);">
                    <div class="h4 mb-1 text-warning">
                        <i class="bi bi-star-fill"></i> 2
                    </div>
                    <div class="small text-muted"><?= number_format($stats['rating_2']) ?> reviews</div>
                </div>
            </div>
            <div class="col-md-2 col-6">
                <div class="text-center p-3 rounded" style="background: var(--accent);">
                    <div class="h4 mb-1 text-warning">
                        <i class="bi bi-star-fill"></i> 1
                    </div>
                    <div class="small text-muted"><?= number_format($stats['rating_1']) ?> reviews</div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Filters and Search -->
<div class="admin-card mb-4">
    <div class="admin-card-body">
        <form method="GET" action="<?= htmlspecialchars(app_url('admin/reviews')) ?>" class="row g-3">
            <div class="col-md-4">
                <label class="form-label">Search</label>
                <input type="text" 
                       class="form-control form-control-sm" 
                       name="search" 
                       placeholder="Search by user, listing, comment..." 
                       value="<?= htmlspecialchars($search) ?>"
                       style="height: 38px;">
            </div>
            <div class="col-md-2">
                <label class="form-label">Rating</label>
                <select class="form-control form-control-sm filter-select" name="rating" style="height: 38px;">
                    <option value="">All Ratings</option>
                    <option value="5" <?= $rating === '5' ? 'selected' : '' ?>>5 Stars</option>
                    <option value="4" <?= $rating === '4' ? 'selected' : '' ?>>4 Stars</option>
                    <option value="3" <?= $rating === '3' ? 'selected' : '' ?>>3 Stars</option>
                    <option value="2" <?= $rating === '2' ? 'selected' : '' ?>>2 Stars</option>
                    <option value="1" <?= $rating === '1' ? 'selected' : '' ?>>1 Star</option>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label">Sort By</label>
                <select class="form-control form-control-sm filter-select" name="sort" style="height: 38px;">
                    <option value="r.created_at" <?= $sort === 'r.created_at' ? 'selected' : '' ?>>Date</option>
                    <option value="r.rating" <?= $sort === 'r.rating' ? 'selected' : '' ?>>Rating</option>
                    <option value="u.name" <?= $sort === 'u.name' ? 'selected' : '' ?>>User Name</option>
                    <option value="l.title" <?= $sort === 'l.title' ? 'selected' : '' ?>>Listing</option>
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

<!-- Reviews Table -->
<div class="admin-card">
    <div class="admin-card-body p-0">
        <?php if (empty($reviews)): ?>
            <div class="text-center py-5">
                <i class="bi bi-star fs-1 text-muted"></i>
                <p class="text-muted mt-3">No reviews found</p>
            </div>
        <?php else: ?>
            <div class="table-responsive d-none d-lg-block">
                <table class="table table-hover admin-table mb-0">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>User</th>
                            <th>Listing</th>
                            <th>Rating</th>
                            <th>Comment</th>
                            <th>Created</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($reviews as $review): ?>
                            <tr>
                                <td>#<?= $review['id'] ?></td>
                                <td>
                                    <div class="d-flex align-items-center">
                                        <?php if (!empty($review['profile_image'])): ?>
                                            <img src="<?= htmlspecialchars(strpos($review['profile_image'], 'http') === 0 ? $review['profile_image'] : app_url($review['profile_image'])) ?>" 
                                                 class="rounded-circle me-2" 
                                                 style="width: 32px; height: 32px; object-fit: cover;"
                                                 alt="<?= htmlspecialchars($review['user_name']) ?>"
                                                 onerror="this.style.display='none';">
                                        <?php endif; ?>
                                        <div>
                                            <div class="fw-semibold"><?= htmlspecialchars($review['user_name'] ?? 'Unknown') ?></div>
                                            <small class="text-muted"><?= htmlspecialchars($review['user_email'] ?? '') ?></small>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <div>
                                        <div class="fw-semibold"><?= htmlspecialchars($review['listing_title'] ?? 'Unknown Listing') ?></div>
                                        <?php if (!empty($review['listing_city'])): ?>
                                            <small class="text-muted"><?= htmlspecialchars($review['listing_city']) ?></small>
                                        <?php endif; ?>
                                    </div>
                                </td>
                                <td>
                                    <div class="d-flex align-items-center">
                                        <?php for ($i = 1; $i <= 5; $i++): ?>
                                            <i class="bi bi-star<?= $i <= $review['rating'] ? '-fill text-warning' : '' ?>"></i>
                                        <?php endfor; ?>
                                        <span class="ms-2 fw-semibold"><?= $review['rating'] ?></span>
                                    </div>
                                </td>
                                <td>
                                    <?php if (!empty($review['comment'])): ?>
                                        <div class="text-truncate" style="max-width: 300px;" title="<?= htmlspecialchars($review['comment']) ?>">
                                            <?= htmlspecialchars($review['comment']) ?>
                                        </div>
                                    <?php else: ?>
                                        <span class="text-muted">No comment</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div><?= date('M d, Y', strtotime($review['created_at'])) ?></div>
                                    <small class="text-muted"><?= date('h:i A', strtotime($review['created_at'])) ?></small>
                                </td>
                                <td>
                                    <div class="d-flex gap-2">
                                        <a href="<?= htmlspecialchars(app_url('listings/' . $review['listing_id'])) ?>" 
                                           class="btn btn-sm btn-outline-primary" 
                                           target="_blank"
                                           title="View Listing">
                                            <i class="bi bi-eye"></i>
                                        </a>
                                        <a href="<?= htmlspecialchars(app_url('admin/users/view?id=' . $review['user_id'])) ?>" 
                                           class="btn btn-sm btn-outline-info"
                                           title="View User">
                                            <i class="bi bi-person"></i>
                                        </a>
                                        <button type="button" 
                                                class="btn btn-sm btn-outline-danger" 
                                                data-bs-toggle="modal" 
                                                data-bs-target="#deleteModal<?= $review['id'] ?>"
                                                title="Delete Review">
                                            <i class="bi bi-trash"></i>
                                        </button>
                                    </div>
                                    
                                    <!-- Delete Modal -->
                                    <div class="modal fade" id="deleteModal<?= $review['id'] ?>" tabindex="-1">
                                        <div class="modal-dialog">
                                            <div class="modal-content">
                                                <div class="modal-header">
                                                    <h5 class="modal-title">Delete Review</h5>
                                                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                                </div>
                                                <div class="modal-body">
                                                    <p>Are you sure you want to delete this review?</p>
                                                    <div class="alert alert-warning">
                                                        <strong>Review Details:</strong><br>
                                                        User: <?= htmlspecialchars($review['user_name'] ?? 'Unknown') ?><br>
                                                        Listing: <?= htmlspecialchars($review['listing_title'] ?? 'Unknown') ?><br>
                                                        Rating: <?= $review['rating'] ?> stars
                                                    </div>
                                                    <p class="text-danger small">This action cannot be undone.</p>
                                                </div>
                                                <div class="modal-footer">
                                                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                                    <form method="POST" action="<?= htmlspecialchars(app_url('admin/reviews?action=delete&id=' . $review['id'])) ?>" class="d-inline">
                                                        <input type="hidden" name="confirm_delete" value="1">
                                                        <button type="submit" class="btn btn-danger">Delete Review</button>
                                                    </form>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            
            <!-- Mobile View -->
            <div class="d-lg-none">
                <?php foreach ($reviews as $review): ?>
                    <div class="border-bottom p-3">
                        <div class="d-flex justify-content-between align-items-start mb-2">
                            <div>
                                <div class="fw-semibold"><?= htmlspecialchars($review['user_name'] ?? 'Unknown') ?></div>
                                <small class="text-muted"><?= htmlspecialchars($review['user_email'] ?? '') ?></small>
                            </div>
                            <div class="text-end">
                                <?php for ($i = 1; $i <= 5; $i++): ?>
                                    <i class="bi bi-star<?= $i <= $review['rating'] ? '-fill text-warning' : '' ?>"></i>
                                <?php endfor; ?>
                            </div>
                        </div>
                        <div class="mb-2">
                            <strong>Listing:</strong> <?= htmlspecialchars($review['listing_title'] ?? 'Unknown') ?>
                        </div>
                        <?php if (!empty($review['comment'])): ?>
                            <div class="mb-2">
                                <strong>Comment:</strong> <?= htmlspecialchars($review['comment']) ?>
                            </div>
                        <?php endif; ?>
                        <div class="small text-muted mb-2">
                            <?= date('M d, Y h:i A', strtotime($review['created_at'])) ?>
                        </div>
                        <div class="d-flex gap-2">
                            <a href="<?= htmlspecialchars(app_url('listings/' . $review['listing_id'])) ?>" 
                               class="btn btn-sm btn-outline-primary" 
                               target="_blank">
                                <i class="bi bi-eye me-1"></i>View Listing
                            </a>
                            <a href="<?= htmlspecialchars(app_url('admin/users/view?id=' . $review['user_id'])) ?>" 
                               class="btn btn-sm btn-outline-info">
                                <i class="bi bi-person me-1"></i>View User
                            </a>
                            <button type="button" 
                                    class="btn btn-sm btn-outline-danger" 
                                    data-bs-toggle="modal" 
                                    data-bs-target="#deleteModal<?= $review['id'] ?>">
                                <i class="bi bi-trash me-1"></i>Delete
                            </button>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
            
            <!-- Pagination -->
            <?php if ($totalPages > 1): ?>
                <div class="admin-card-body border-top">
                    <nav aria-label="Reviews pagination">
                        <ul class="pagination justify-content-center mb-0">
                            <?php if ($page > 1): ?>
                                <li class="page-item">
                                    <a class="page-link" href="<?= htmlspecialchars(app_url('admin/reviews?' . http_build_query(array_merge($_GET, ['page' => $page - 1])))) ?>">
                                        <i class="bi bi-chevron-left"></i>
                                    </a>
                                </li>
                            <?php endif; ?>
                            
                            <?php
                            $startPage = max(1, $page - 2);
                            $endPage = min($totalPages, $page + 2);
                            
                            for ($i = $startPage; $i <= $endPage; $i++):
                            ?>
                                <li class="page-item <?= $i === $page ? 'active' : '' ?>">
                                    <a class="page-link" href="<?= htmlspecialchars(app_url('admin/reviews?' . http_build_query(array_merge($_GET, ['page' => $i])))) ?>">
                                        <?= $i ?>
                                    </a>
                                </li>
                            <?php endfor; ?>
                            
                            <?php if ($page < $totalPages): ?>
                                <li class="page-item">
                                    <a class="page-link" href="<?= htmlspecialchars(app_url('admin/reviews?' . http_build_query(array_merge($_GET, ['page' => $page + 1])))) ?>">
                                        <i class="bi bi-chevron-right"></i>
                                    </a>
                                </li>
                            <?php endif; ?>
                        </ul>
                    </nav>
                    <div class="text-center mt-2 text-muted small">
                        Showing <?= $offset + 1 ?> to <?= min($offset + $perPage, $totalReviews) ?> of <?= number_format($totalReviews) ?> reviews
                    </div>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</div>

<?php require __DIR__ . '/../app/includes/admin_footer.php'; ?>

