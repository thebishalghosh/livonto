<?php
/**
 * Admin Payments Management Page
 * View and manage all payment records
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
$paymentId = intval($_GET['id'] ?? 0);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action && $paymentId) {
    try {
        $db = db();
        
        $payment = $db->fetchOne("SELECT id FROM payments WHERE id = ?", [$paymentId]);
        if (!$payment) {
            $_SESSION['flash_message'] = 'Payment not found';
            $_SESSION['flash_type'] = 'danger';
            header('Location: ' . app_url('admin/payments'));
            exit;
        }
        
        if ($action === 'update_status' && isset($_POST['new_status'])) {
            $newStatus = $_POST['new_status'];
            if (in_array($newStatus, ['initiated', 'success', 'failed'])) {
                $db->execute("UPDATE payments SET status = ? WHERE id = ?", [$newStatus, $paymentId]);
                $_SESSION['flash_message'] = 'Payment status updated successfully';
                $_SESSION['flash_type'] = 'success';
                header('Location: ' . app_url('admin/payments'));
                exit;
            }
        }
    } catch (Exception $e) {
        $_SESSION['flash_message'] = 'Error processing request: ' . $e->getMessage();
        $_SESSION['flash_type'] = 'danger';
    }
}

$pageTitle = "Payments Management";
require __DIR__ . '/../app/includes/admin_header.php';

$search = trim($_GET['search'] ?? '');
$status = $_GET['status'] ?? '';
$provider = $_GET['provider'] ?? '';
$sort = $_GET['sort'] ?? 'p.created_at';
$order = $_GET['order'] ?? 'DESC';
$page = max(1, intval($_GET['page'] ?? 1));
$perPage = 20;
$offset = ($page - 1) * $perPage;

try {
    $db = db();
    
    $where = [];
    $params = [];
    
    if (!empty($search)) {
        $where[] = "(u.name LIKE ? OR u.email LIKE ? OR u.phone LIKE ? OR l.title LIKE ? OR p.provider_payment_id LIKE ?)";
        $searchParam = "%{$search}%";
        $params[] = $searchParam;
        $params[] = $searchParam;
        $params[] = $searchParam;
        $params[] = $searchParam;
        $params[] = $searchParam;
    }
    
    if (!empty($status) && in_array($status, ['initiated', 'success', 'failed'])) {
        $where[] = "p.status = ?";
        $params[] = $status;
    }
    
    if (!empty($provider)) {
        $where[] = "p.provider = ?";
        $params[] = $provider;
    }
    
    $whereClause = !empty($where) ? "WHERE " . implode(" AND ", $where) : "";
    
    $totalPayments = $db->fetchValue(
        "SELECT COUNT(*) FROM payments p
         LEFT JOIN bookings b ON p.booking_id = b.id
         LEFT JOIN users u ON b.user_id = u.id
         LEFT JOIN listings l ON b.listing_id = l.id
         {$whereClause}",
        $params
    ) ?: 0;
    
    $totalPages = ceil($totalPayments / $perPage);
    
    $allowedSorts = ['p.id', 'p.amount', 'p.status', 'p.created_at', 'u.name', 'l.title', 'p.provider'];
    $sort = in_array($sort, $allowedSorts) ? $sort : 'p.created_at';
    $order = strtoupper($order) === 'ASC' ? 'ASC' : 'DESC';
    
    $payments = $db->fetchAll(
        "SELECT p.*, 
                b.id as booking_id, b.booking_start_date, b.status as booking_status,
                u.id as user_id, u.name as user_name, u.email as user_email, u.phone as user_phone,
                l.id as listing_id, l.title as listing_title,
                loc.city as listing_city, loc.pin_code as listing_pincode
         FROM payments p
         LEFT JOIN bookings b ON p.booking_id = b.id
         LEFT JOIN users u ON b.user_id = u.id
         LEFT JOIN listings l ON b.listing_id = l.id
         LEFT JOIN listing_locations loc ON l.id = loc.listing_id
         {$whereClause}
         ORDER BY {$sort} {$order}
         LIMIT ? OFFSET ?",
        array_merge($params, [$perPage, $offset])
    );
    
    $statsRow = $db->fetchOne(
        "SELECT 
            COUNT(*) as total,
            SUM(CASE WHEN status = 'initiated' THEN 1 ELSE 0 END) as initiated,
            SUM(CASE WHEN status = 'success' THEN 1 ELSE 0 END) as success,
            SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END) as failed,
            SUM(CASE WHEN DATE(created_at) = CURDATE() THEN 1 ELSE 0 END) as today,
            SUM(CASE WHEN MONTH(created_at) = MONTH(CURDATE()) AND YEAR(created_at) = YEAR(CURDATE()) THEN 1 ELSE 0 END) as this_month,
            SUM(CASE WHEN status = 'success' THEN amount ELSE 0 END) as total_revenue
         FROM payments"
    );
    $stats = [
        'total' => intval($statsRow['total'] ?? 0),
        'initiated' => intval($statsRow['initiated'] ?? 0),
        'success' => intval($statsRow['success'] ?? 0),
        'failed' => intval($statsRow['failed'] ?? 0),
        'today' => intval($statsRow['today'] ?? 0),
        'this_month' => intval($statsRow['this_month'] ?? 0),
        'total_revenue' => floatval($statsRow['total_revenue'] ?? 0)
    ];
    
} catch (Exception $e) {
    $payments = [];
    $stats = ['total' => 0, 'initiated' => 0, 'success' => 0, 'failed' => 0, 'today' => 0, 'this_month' => 0, 'total_revenue' => 0];
    $totalPayments = 0;
    $totalPages = 0;
    $_SESSION['flash_message'] = 'Error loading payments';
    $_SESSION['flash_type'] = 'danger';
}

$flashMessage = getFlashMessage();
?>

<!-- Page Header -->
<div class="admin-page-header mb-4">
    <div class="d-flex justify-content-between align-items-center">
        <div>
            <h1 class="admin-page-title">Payments Management</h1>
            <p class="admin-page-subtitle text-muted">View and manage all payment records</p>
        </div>
    </div>
</div>

<?php if ($flashMessage): ?>
    <div class="alert alert-<?= htmlspecialchars($flashMessage['type']) ?> alert-dismissible fade show" role="alert">
        <?= htmlspecialchars($flashMessage['message']) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<!-- Statistics Cards -->
<div class="row g-3 mb-4">
    <div class="col-xl-4 col-md-4 col-6">
        <div class="admin-stat-card">
            <div class="admin-stat-card-icon" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);">
                <i class="bi bi-credit-card"></i>
            </div>
            <div class="admin-stat-card-content">
                <div class="admin-stat-card-label">Total Payments</div>
                <div class="admin-stat-card-value"><?= number_format($stats['total']) ?></div>
            </div>
        </div>
    </div>
    <div class="col-xl-4 col-md-4 col-6">
        <div class="admin-stat-card">
            <div class="admin-stat-card-icon" style="background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);">
                <i class="bi bi-check-circle"></i>
            </div>
            <div class="admin-stat-card-content">
                <div class="admin-stat-card-label">Successful</div>
                <div class="admin-stat-card-value"><?= number_format($stats['success']) ?></div>
            </div>
        </div>
    </div>
    <div class="col-xl-4 col-md-4 col-6">
        <div class="admin-stat-card">
            <div class="admin-stat-card-icon" style="background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);">
                <i class="bi bi-clock-history"></i>
            </div>
            <div class="admin-stat-card-content">
                <div class="admin-stat-card-label">Initiated</div>
                <div class="admin-stat-card-value"><?= number_format($stats['initiated']) ?></div>
            </div>
        </div>
    </div>
    <div class="col-xl-4 col-md-4 col-6">
        <div class="admin-stat-card">
            <div class="admin-stat-card-icon" style="background: linear-gradient(135deg, #fa709a 0%, #fee140 100%);">
                <i class="bi bi-x-circle"></i>
            </div>
            <div class="admin-stat-card-content">
                <div class="admin-stat-card-label">Failed</div>
                <div class="admin-stat-card-value"><?= number_format($stats['failed']) ?></div>
            </div>
        </div>
    </div>
    <div class="col-xl-4 col-md-4 col-6">
        <div class="admin-stat-card">
            <div class="admin-stat-card-icon" style="background: linear-gradient(135deg, #30cfd0 0%, #330867 100%);">
                <i class="bi bi-calendar-day"></i>
            </div>
            <div class="admin-stat-card-content">
                <div class="admin-stat-card-label">Today</div>
                <div class="admin-stat-card-value"><?= number_format($stats['today']) ?></div>
            </div>
        </div>
    </div>
    <div class="col-xl-4 col-md-4 col-6">
        <div class="admin-stat-card">
            <div class="admin-stat-card-icon" style="background: linear-gradient(135deg, #43e97b 0%, #38f9d7 100%);">
                <i class="bi bi-currency-rupee"></i>
            </div>
            <div class="admin-stat-card-content">
                <div class="admin-stat-card-label">Total Revenue</div>
                <div class="admin-stat-card-value">₹<?= number_format($stats['total_revenue'], 0) ?></div>
            </div>
        </div>
    </div>
</div>

<!-- Filters and Search -->
<div class="admin-card mb-4">
    <div class="admin-card-body">
        <form method="GET" action="<?= htmlspecialchars(app_url('admin/payments')) ?>" class="row g-3">
            <div class="col-md-3">
                <label class="form-label">Search</label>
                <input type="text" 
                       class="form-control form-control-sm" 
                       name="search" 
                       placeholder="Search by user, listing, transaction ID..." 
                       value="<?= htmlspecialchars($search) ?>"
                       style="height: 38px;">
            </div>
            <div class="col-md-2">
                <label class="form-label">Status</label>
                <select class="form-control form-control-sm filter-select" name="status" style="height: 38px;">
                    <option value="">All Status</option>
                    <option value="initiated" <?= $status === 'initiated' ? 'selected' : '' ?>>Initiated</option>
                    <option value="success" <?= $status === 'success' ? 'selected' : '' ?>>Success</option>
                    <option value="failed" <?= $status === 'failed' ? 'selected' : '' ?>>Failed</option>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label">Provider</label>
                <select class="form-control form-control-sm filter-select" name="provider" style="height: 38px;">
                    <option value="">All Providers</option>
                    <option value="razorpay" <?= $provider === 'razorpay' ? 'selected' : '' ?>>Razorpay</option>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label">Sort By</label>
                <select class="form-control form-control-sm filter-select" name="sort" style="height: 38px;">
                    <option value="p.created_at" <?= $sort === 'p.created_at' ? 'selected' : '' ?>>Date</option>
                    <option value="p.amount" <?= $sort === 'p.amount' ? 'selected' : '' ?>>Amount</option>
                    <option value="p.status" <?= $sort === 'p.status' ? 'selected' : '' ?>>Status</option>
                    <option value="u.name" <?= $sort === 'u.name' ? 'selected' : '' ?>>User</option>
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

<!-- Payments Table -->
<div class="admin-card">
    <div class="admin-card-header">
        <h5 class="mb-0">Payments (<?= number_format($totalPayments) ?>)</h5>
    </div>
    <div class="admin-card-body p-0">
        <?php if (empty($payments)): ?>
            <div class="text-center py-5">
                <i class="bi bi-inbox" style="font-size: 3rem; color: #ccc;"></i>
                <p class="text-muted mt-3">No payments found</p>
            </div>
        <?php else: ?>
            <!-- Desktop Table View -->
            <div class="table-responsive d-none d-md-block">
                <table class="table table-hover mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>ID</th>
                            <th>User</th>
                            <th>Booking</th>
                            <th>Amount</th>
                            <th>Provider</th>
                            <th>Transaction ID</th>
                            <th>Status</th>
                            <th>Date</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($payments as $payment): ?>
                            <tr>
                                <td>#<?= $payment['id'] ?></td>
                                <td>
                                    <div>
                                        <strong><?= htmlspecialchars($payment['user_name'] ?? 'N/A') ?></strong>
                                        <?php if (!empty($payment['user_email'])): ?>
                                            <div class="small text-muted"><?= htmlspecialchars($payment['user_email']) ?></div>
                                        <?php endif; ?>
                                    </div>
                                </td>
                                <td>
                                    <div>
                                        <strong><?= htmlspecialchars($payment['listing_title'] ?? 'N/A') ?></strong>
                                        <?php if ($payment['booking_id']): ?>
                                            <div class="small text-muted">
                                                Booking #<?= $payment['booking_id'] ?>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </td>
                                <td><strong>₹<?= number_format($payment['amount'], 2) ?></strong></td>
                                <td>
                                    <?php if ($payment['provider']): ?>
                                        <span class="badge bg-secondary"><?= strtoupper($payment['provider']) ?></span>
                                    <?php else: ?>
                                        <span class="text-muted">-</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($payment['provider_payment_id']): ?>
                                        <code class="small"><?= htmlspecialchars(substr($payment['provider_payment_id'], 0, 20)) ?>...</code>
                                    <?php else: ?>
                                        <span class="text-muted">-</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php
                                    $statusColors = [
                                        'initiated' => 'warning',
                                        'success' => 'success',
                                        'failed' => 'danger'
                                    ];
                                    $badgeColor = $statusColors[$payment['status']] ?? 'secondary';
                                    ?>
                                    <span class="badge bg-<?= $badgeColor ?>"><?= ucfirst($payment['status']) ?></span>
                                </td>
                                <td><?= date('d M Y h:i A', strtotime($payment['created_at'])) ?></td>
                                <td>
                                    <button type="button" class="btn btn-sm btn-outline-primary" 
                                            data-bs-toggle="modal" data-bs-target="#paymentModal<?= $payment['id'] ?>">
                                        <i class="bi bi-eye"></i> View
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            
            <!-- Mobile Card View -->
            <div class="d-md-none">
                <?php foreach ($payments as $payment): ?>
                    <div class="card mb-3 border-0 border-bottom">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-start mb-2">
                                <div>
                                    <h6 class="mb-1">Payment #<?= $payment['id'] ?></h6>
                                    <p class="text-muted small mb-0">
                                        <?= date('d M Y h:i A', strtotime($payment['created_at'])) ?>
                                    </p>
                                </div>
                                <?php
                                $statusColors = [
                                    'initiated' => 'warning',
                                    'success' => 'success',
                                    'failed' => 'danger'
                                ];
                                $badgeColor = $statusColors[$payment['status']] ?? 'secondary';
                                ?>
                                <span class="badge bg-<?= $badgeColor ?>"><?= ucfirst($payment['status']) ?></span>
                            </div>
                            <div class="mb-2">
                                <strong>Amount:</strong> ₹<?= number_format($payment['amount'], 2) ?>
                            </div>
                            <div class="mb-2">
                                <strong>User:</strong> <?= htmlspecialchars($payment['user_name'] ?? 'N/A') ?>
                            </div>
                            <div class="mb-2">
                                <strong>Booking:</strong> <?= htmlspecialchars($payment['listing_title'] ?? 'N/A') ?>
                            </div>
                            <?php if ($payment['provider']): ?>
                                <div class="mb-2">
                                    <strong>Provider:</strong> <?= strtoupper($payment['provider']) ?>
                                </div>
                            <?php endif; ?>
                            <button type="button" class="btn btn-sm btn-outline-primary w-100" 
                                    data-bs-toggle="modal" data-bs-target="#paymentModal<?= $payment['id'] ?>">
                                <i class="bi bi-eye"></i> View Details
                            </button>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
    
    <!-- Pagination -->
    <?php if ($totalPages > 1): ?>
        <div class="admin-card-footer">
            <nav aria-label="Page navigation">
                <ul class="pagination pagination-sm justify-content-center mb-0">
                    <?php
                    $currentUrl = '?' . http_build_query(array_merge($_GET, ['page' => 1]));
                    $prevPage = max(1, $page - 1);
                    $nextPage = min($totalPages, $page + 1);
                    ?>
                    <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">
                        <a class="page-link" href="?<?= http_build_query(array_merge($_GET, ['page' => $prevPage])) ?>">Previous</a>
                    </li>
                    <?php for ($i = max(1, $page - 2); $i <= min($totalPages, $page + 2); $i++): ?>
                        <li class="page-item <?= $i === $page ? 'active' : '' ?>">
                            <a class="page-link" href="?<?= http_build_query(array_merge($_GET, ['page' => $i])) ?>"><?= $i ?></a>
                        </li>
                    <?php endfor; ?>
                    <li class="page-item <?= $page >= $totalPages ? 'disabled' : '' ?>">
                        <a class="page-link" href="?<?= http_build_query(array_merge($_GET, ['page' => $nextPage])) ?>">Next</a>
                    </li>
                </ul>
            </nav>
        </div>
    <?php endif; ?>
</div>

<!-- Payment Detail Modals -->
<?php foreach ($payments as $payment): ?>
    <div class="modal fade" id="paymentModal<?= $payment['id'] ?>" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Payment Details #<?= $payment['id'] ?></h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <h6 class="text-muted mb-2">Payment Information</h6>
                            <p class="mb-1"><strong>Payment ID:</strong> #<?= $payment['id'] ?></p>
                            <p class="mb-1"><strong>Amount:</strong> ₹<?= number_format($payment['amount'], 2) ?></p>
                            <p class="mb-1"><strong>Status:</strong> 
                                <?php
                                $statusColors = [
                                    'initiated' => 'warning',
                                    'success' => 'success',
                                    'failed' => 'danger'
                                ];
                                $badgeColor = $statusColors[$payment['status']] ?? 'secondary';
                                ?>
                                <span class="badge bg-<?= $badgeColor ?>"><?= ucfirst($payment['status']) ?></span>
                            </p>
                            <p class="mb-1"><strong>Provider:</strong> <?= $payment['provider'] ? strtoupper($payment['provider']) : 'N/A' ?></p>
                            <?php if ($payment['provider_payment_id']): ?>
                                <p class="mb-1"><strong>Transaction ID:</strong> <code><?= htmlspecialchars($payment['provider_payment_id']) ?></code></p>
                            <?php endif; ?>
                            <p class="mb-1"><strong>Created:</strong> <?= date('d M Y h:i A', strtotime($payment['created_at'])) ?></p>
                        </div>
                        <div class="col-md-6">
                            <h6 class="text-muted mb-2">User Information</h6>
                            <p class="mb-1"><strong>Name:</strong> <?= htmlspecialchars($payment['user_name'] ?? 'N/A') ?></p>
                            <?php if ($payment['user_email']): ?>
                                <p class="mb-1"><strong>Email:</strong> <?= htmlspecialchars($payment['user_email']) ?></p>
                            <?php endif; ?>
                            <?php if ($payment['user_phone']): ?>
                                <p class="mb-1"><strong>Phone:</strong> <?= htmlspecialchars($payment['user_phone']) ?></p>
                            <?php endif; ?>
                        </div>
                        <?php if ($payment['booking_id']): ?>
                            <div class="col-12">
                                <h6 class="text-muted mb-2">Booking Information</h6>
                                <p class="mb-1"><strong>Booking ID:</strong> #<?= $payment['booking_id'] ?></p>
                                <p class="mb-1"><strong>Property:</strong> <?= htmlspecialchars($payment['listing_title'] ?? 'N/A') ?></p>
                                <?php if ($payment['booking_start_date']): ?>
                                    <p class="mb-1"><strong>Booking Start:</strong> <?= date('F 1, Y', strtotime($payment['booking_start_date'])) ?></p>
                                <?php endif; ?>
                                <?php if ($payment['booking_status']): ?>
                                    <p class="mb-1"><strong>Booking Status:</strong> 
                                        <span class="badge bg-<?= $payment['booking_status'] === 'confirmed' ? 'success' : ($payment['booking_status'] === 'pending' ? 'warning' : 'secondary') ?>">
                                            <?= ucfirst($payment['booking_status']) ?>
                                        </span>
                                    </p>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <?php if ($payment['status'] !== 'success'): ?>
                        <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#statusModal<?= $payment['id'] ?>" data-bs-dismiss="modal">
                            Update Status
                        </button>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    
    <div class="modal fade" id="statusModal<?= $payment['id'] ?>" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Update Payment Status #<?= $payment['id'] ?></h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form method="POST" action="?action=update_status&id=<?= $payment['id'] ?>">
                        <div class="mb-3">
                            <label for="new_status<?= $payment['id'] ?>" class="form-label">Status</label>
                            <select class="form-control filter-select" id="new_status<?= $payment['id'] ?>" name="new_status" required>
                                <option value="initiated" <?= $payment['status'] === 'initiated' ? 'selected' : '' ?>>Initiated</option>
                                <option value="success" <?= $payment['status'] === 'success' ? 'selected' : '' ?>>Success</option>
                                <option value="failed" <?= $payment['status'] === 'failed' ? 'selected' : '' ?>>Failed</option>
                            </select>
                        </div>
                        <button type="submit" class="btn btn-primary btn-sm">Update Status</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
<?php endforeach; ?>

<?php require __DIR__ . '/../app/includes/admin_footer.php'; ?>

