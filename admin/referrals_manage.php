<?php
/**
 * Admin Referrals Management Page
 * List, search, filter, and manage referral records
 */

// Start session and load config/functions BEFORE any output
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require __DIR__ . '/../app/config.php';
require __DIR__ . '/../app/functions.php';

// Check if user is logged in and is admin BEFORE processing actions
if (empty($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
    header('Location: ' . app_url('admin/login'));
    exit;
}

// Handle POST actions BEFORE including header (to avoid headers already sent error)
$action = $_GET['action'] ?? $_POST['action'] ?? '';
$referralId = intval($_GET['id'] ?? $_POST['id'] ?? 0);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action && $referralId) {
    // Handle POST actions (credit reward, etc.)
    if ($action === 'credit' && isset($_POST['confirm_credit'])) {
        try {
            $db = db();
            // Get referral details
            $referral = $db->fetchOne(
                "SELECT r.*, u1.name as referrer_name, u2.name as referred_name 
                 FROM referrals r
                 LEFT JOIN users u1 ON r.referrer_id = u1.id
                 LEFT JOIN users u2 ON r.referred_id = u2.id
                 WHERE r.id = ?",
                [$referralId]
            );
            
            if ($referral && $referral['status'] === 'pending') {
                // Update referral status to credited
                $db->execute(
                    "UPDATE referrals SET status = 'credited', credited_at = NOW() WHERE id = ?",
                    [$referralId]
                );
                error_log("Admin credited referral: ID {$referralId} (Referrer: {$referral['referrer_name']}, Referred: {$referral['referred_name']}) by Admin ID {$_SESSION['user_id']}");
                $_SESSION['flash_message'] = 'Referral reward credited successfully';
                $_SESSION['flash_type'] = 'success';
                header('Location: ' . app_url('admin/referrals'));
                exit;
            }
        } catch (Exception $e) {
            error_log("Error crediting referral: " . $e->getMessage());
            $_SESSION['flash_message'] = 'Error crediting referral reward';
            $_SESSION['flash_type'] = 'danger';
        }
    }
}

// Now include header and continue with page rendering
$pageTitle = "Referrals Management";
require __DIR__ . '/../app/includes/admin_header.php';

// Get filter parameters
$search = trim($_GET['search'] ?? '');
$status = $_GET['status'] ?? '';
$sort = $_GET['sort'] ?? 'created_at';
$order = $_GET['order'] ?? 'DESC';
$page = max(1, intval($_GET['page'] ?? 1));
$perPage = 20;
$offset = ($page - 1) * $perPage;

try {
    $db = db();
    
    // Fetch Statistics
    $stats = [
        'total_referrals' => (int)$db->fetchValue("SELECT COUNT(*) FROM referrals") ?: 0,
        'pending_referrals' => (int)$db->fetchValue("SELECT COUNT(*) FROM referrals WHERE status = 'pending'") ?: 0,
        'credited_referrals' => (int)$db->fetchValue("SELECT COUNT(*) FROM referrals WHERE status = 'credited'") ?: 0,
        'total_rewards' => (float)$db->fetchValue("SELECT COALESCE(SUM(reward_amount), 0) FROM referrals WHERE status = 'credited'") ?: 0,
        'pending_rewards' => (float)$db->fetchValue("SELECT COALESCE(SUM(reward_amount), 0) FROM referrals WHERE status = 'pending'") ?: 0,
        'today_referrals' => (int)$db->fetchValue("SELECT COUNT(*) FROM referrals WHERE DATE(created_at) = CURDATE()") ?: 0,
        'this_month_referrals' => (int)$db->fetchValue("SELECT COUNT(*) FROM referrals WHERE MONTH(created_at) = MONTH(CURRENT_DATE()) AND YEAR(created_at) = YEAR(CURRENT_DATE())") ?: 0,
    ];
    
    // Build WHERE clause
    $where = [];
    $params = [];
    
    if (!empty($search)) {
        $where[] = "(u1.name LIKE ? OR u1.email LIKE ? OR u2.name LIKE ? OR u2.email LIKE ? OR r.code LIKE ?)";
        $searchParam = "%{$search}%";
        $params[] = $searchParam;
        $params[] = $searchParam;
        $params[] = $searchParam;
        $params[] = $searchParam;
        $params[] = $searchParam;
    }
    
    if (!empty($status) && in_array($status, ['pending', 'credited'])) {
        $where[] = "r.status = ?";
        $params[] = $status;
    }
    
    $whereClause = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';
    
    // Get total count
    $totalReferrals = $db->fetchValue(
        "SELECT COUNT(*) 
         FROM referrals r
         LEFT JOIN users u1 ON r.referrer_id = u1.id
         LEFT JOIN users u2 ON r.referred_id = u2.id
         {$whereClause}",
        $params
    );
    $totalPages = ceil($totalReferrals / $perPage);
    
    // Validate sort and order
    $allowedSorts = ['id', 'created_at', 'status', 'reward_amount', 'credited_at'];
    $sort = in_array($sort, $allowedSorts) ? $sort : 'created_at';
    $order = strtoupper($order) === 'ASC' ? 'ASC' : 'DESC';
    
    // Get referrals with pagination
    $referrals = $db->fetchAll(
        "SELECT r.id, r.referrer_id, r.referred_id, r.code, r.status, r.reward_amount, 
                r.created_at, r.credited_at,
                u1.id as referrer_user_id, u1.name as referrer_name, u1.email as referrer_email,
                u2.id as referred_user_id, u2.name as referred_name, u2.email as referred_email
         FROM referrals r
         LEFT JOIN users u1 ON r.referrer_id = u1.id
         LEFT JOIN users u2 ON r.referred_id = u2.id
         {$whereClause}
         ORDER BY r.{$sort} {$order}
         LIMIT ? OFFSET ?",
        array_merge($params, [$perPage, $offset])
    );
    
} catch (Exception $e) {
    error_log("Error loading referrals: " . $e->getMessage());
    $referrals = [];
    $stats = [
        'total_referrals' => 0,
        'pending_referrals' => 0,
        'credited_referrals' => 0,
        'total_rewards' => 0,
        'pending_rewards' => 0,
        'today_referrals' => 0,
        'this_month_referrals' => 0,
    ];
    $totalReferrals = 0;
    $totalPages = 0;
}

// Get flash message
$flashMessage = getFlashMessage();
?>

<!-- Page Header -->
<div class="admin-page-header mb-4">
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center gap-3">
        <div>
            <h1 class="admin-page-title">Referrals Management</h1>
            <p class="admin-page-subtitle text-muted">Manage referral records and rewards</p>
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
                <i class="bi bi-gift"></i>
            </div>
            <div class="admin-stat-card-content">
                <div class="admin-stat-card-label">Total Referrals</div>
                <div class="admin-stat-card-value"><?= number_format($stats['total_referrals']) ?></div>
            </div>
        </div>
    </div>
    <div class="col-xl-2 col-md-4 col-6">
        <div class="admin-stat-card">
            <div class="admin-stat-card-icon" style="background: linear-gradient(135deg, #43e97b 0%, #38f9d7 100%);">
                <i class="bi bi-check-circle"></i>
            </div>
            <div class="admin-stat-card-content">
                <div class="admin-stat-card-label">Credited</div>
                <div class="admin-stat-card-value"><?= number_format($stats['credited_referrals']) ?></div>
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
                <div class="admin-stat-card-value"><?= number_format($stats['pending_referrals']) ?></div>
            </div>
        </div>
    </div>
    <div class="col-xl-2 col-md-4 col-6">
        <div class="admin-stat-card">
            <div class="admin-stat-card-icon" style="background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);">
                <i class="bi bi-currency-rupee"></i>
            </div>
            <div class="admin-stat-card-content">
                <div class="admin-stat-card-label">Total Rewards</div>
                <div class="admin-stat-card-value">₹<?= number_format($stats['total_rewards']) ?></div>
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
                <div class="admin-stat-card-value"><?= number_format($stats['today_referrals']) ?></div>
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
                <div class="admin-stat-card-value"><?= number_format($stats['this_month_referrals']) ?></div>
            </div>
        </div>
    </div>
</div>

<!-- Filters and Search -->
<div class="admin-card mb-4">
    <div class="admin-card-body">
        <form method="GET" action="<?= htmlspecialchars(app_url('admin/referrals')) ?>" class="row g-3 align-items-end listings-filters">
            <div class="col-12 col-sm-6 col-md-4 col-lg-4">
                <label class="form-label">Search</label>
                <input type="text" 
                       class="form-control form-control-sm" 
                       name="search" 
                       placeholder="Referrer, Referred user, or code..." 
                       value="<?= htmlspecialchars($search) ?>">
            </div>
            <div class="col-6 col-sm-3 col-md-2 col-lg-2">
                <label class="form-label">Status</label>
                <select class="form-select form-select-sm" name="status">
                    <option value="">All Status</option>
                    <option value="pending" <?= $status === 'pending' ? 'selected' : '' ?>>Pending</option>
                    <option value="credited" <?= $status === 'credited' ? 'selected' : '' ?>>Credited</option>
                </select>
            </div>
            <div class="col-6 col-sm-3 col-md-2 col-lg-2">
                <label class="form-label">Sort By</label>
                <select class="form-select form-select-sm" name="sort">
                    <option value="created_at" <?= $sort === 'created_at' ? 'selected' : '' ?>>Date</option>
                    <option value="status" <?= $sort === 'status' ? 'selected' : '' ?>>Status</option>
                    <option value="reward_amount" <?= $sort === 'reward_amount' ? 'selected' : '' ?>>Reward</option>
                </select>
            </div>
            <div class="col-6 col-sm-3 col-md-2 col-lg-2">
                <label class="form-label">Order</label>
                <select class="form-select form-select-sm" name="order">
                    <option value="DESC" <?= $order === 'DESC' ? 'selected' : '' ?>>Newest First</option>
                    <option value="ASC" <?= $order === 'ASC' ? 'selected' : '' ?>>Oldest First</option>
                </select>
            </div>
            <div class="col-12 col-sm-12 col-md-12 col-lg-2">
                <label class="form-label d-block d-lg-none mb-1">&nbsp;</label>
                <button type="submit" class="btn btn-primary btn-sm w-100">
                    <i class="bi bi-search d-md-none"></i>
                    <span class="d-none d-md-inline">Search</span>
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Referrals Table -->
<div class="admin-card">
    <div class="admin-card-header">
        <h5 class="admin-card-title">
            <i class="bi bi-gift me-2"></i>Referrals List
            <span class="badge bg-secondary ms-2"><?= number_format($totalReferrals) ?></span>
        </h5>
    </div>
    <div class="admin-card-body">
        <?php if (empty($referrals)): ?>
            <div class="text-center py-5">
                <i class="bi bi-gift fs-1 d-block mb-3 text-muted"></i>
                <h5 class="mb-2">No referrals found</h5>
                <p class="text-muted mb-4">
                    <?php if (!empty($search) || !empty($status)): ?>
                        Try adjusting your filters or search terms.
                    <?php else: ?>
                        No referral records yet.
                    <?php endif; ?>
                </p>
            </div>
        <?php else: ?>
            <!-- Desktop Table View -->
            <div class="table-responsive listings-table-desktop d-none d-lg-block">
                <table class="table table-hover align-middle">
                    <thead>
                        <tr>
                            <th style="width: 80px;">ID</th>
                            <th>Referrer</th>
                            <th>Referred User</th>
                            <th>Code</th>
                            <th>Reward</th>
                            <th>Status</th>
                            <th>Created</th>
                            <th>Credited</th>
                            <th style="width: 150px;">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($referrals as $referral): ?>
                            <tr>
                                <td><?= htmlspecialchars($referral['id']) ?></td>
                                <td>
                                    <div class="fw-semibold"><?= htmlspecialchars($referral['referrer_name'] ?: 'Unknown') ?></div>
                                    <div class="small text-muted"><?= htmlspecialchars($referral['referrer_email'] ?: 'N/A') ?></div>
                                </td>
                                <td>
                                    <div class="fw-semibold"><?= htmlspecialchars($referral['referred_name'] ?: 'Unknown') ?></div>
                                    <div class="small text-muted"><?= htmlspecialchars($referral['referred_email'] ?: 'N/A') ?></div>
                                </td>
                                <td>
                                    <code class="bg-light px-2 py-1 rounded"><?= htmlspecialchars($referral['code']) ?></code>
                                </td>
                                <td>
                                    <div class="fw-semibold">₹<?= number_format($referral['reward_amount'], 2) ?></div>
                                </td>
                                <td>
                                    <?php
                                    $statusBadge = match($referral['status']) {
                                        'credited' => 'success',
                                        'pending' => 'warning',
                                        default => 'secondary'
                                    };
                                    ?>
                                    <span class="badge bg-<?= $statusBadge ?>">
                                        <?= ucfirst($referral['status']) ?>
                                    </span>
                                </td>
                                <td>
                                    <div class="small">
                                        <div><?= formatDate($referral['created_at'], 'd M Y') ?></div>
                                        <div class="text-muted"><?= timeAgo($referral['created_at']) ?></div>
                                    </div>
                                </td>
                                <td>
                                    <?php if ($referral['credited_at']): ?>
                                        <div class="small">
                                            <div><?= formatDate($referral['credited_at'], 'd M Y') ?></div>
                                            <div class="text-muted"><?= timeAgo($referral['credited_at']) ?></div>
                                        </div>
                                    <?php else: ?>
                                        <span class="text-muted">-</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($referral['status'] === 'pending'): ?>
                                        <button type="button" 
                                                class="btn btn-sm btn-success" 
                                                onclick="creditReward(<?= $referral['id'] ?>, '<?= htmlspecialchars($referral['referrer_name'] ?: 'User', ENT_QUOTES) ?>', '<?= htmlspecialchars($referral['referred_name'] ?: 'User', ENT_QUOTES) ?>')"
                                                title="Credit Reward">
                                            <i class="bi bi-check-circle me-1"></i>Credit
                                        </button>
                                    <?php else: ?>
                                        <span class="text-muted small">Credited</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            
            <!-- Mobile Card View -->
            <div class="listings-cards-mobile d-lg-none">
                <div class="row g-3">
                    <?php foreach ($referrals as $referral): ?>
                        <div class="col-12">
                            <div class="admin-card">
                                <div class="admin-card-body">
                                    <div class="d-flex justify-content-between align-items-start mb-3">
                                        <div>
                                            <h6 class="mb-1 fw-semibold">Referral #<?= htmlspecialchars($referral['id']) ?></h6>
                                            <code class="bg-light px-2 py-1 rounded small"><?= htmlspecialchars($referral['code']) ?></code>
                                        </div>
                                        <?php
                                        $statusBadge = match($referral['status']) {
                                            'credited' => 'success',
                                            'pending' => 'warning',
                                            default => 'secondary'
                                        };
                                        ?>
                                        <span class="badge bg-<?= $statusBadge ?>">
                                            <?= ucfirst($referral['status']) ?>
                                        </span>
                                    </div>
                                    
                                    <div class="row g-2 mb-3">
                                        <div class="col-12">
                                            <div class="small text-muted">Referrer</div>
                                            <div class="fw-semibold"><?= htmlspecialchars($referral['referrer_name'] ?: 'Unknown') ?></div>
                                            <div class="small text-muted"><?= htmlspecialchars($referral['referrer_email'] ?: 'N/A') ?></div>
                                        </div>
                                        <div class="col-12">
                                            <div class="small text-muted">Referred User</div>
                                            <div class="fw-semibold"><?= htmlspecialchars($referral['referred_name'] ?: 'Unknown') ?></div>
                                            <div class="small text-muted"><?= htmlspecialchars($referral['referred_email'] ?: 'N/A') ?></div>
                                        </div>
                                        <div class="col-6">
                                            <div class="small text-muted">Reward</div>
                                            <div class="fw-semibold">₹<?= number_format($referral['reward_amount'], 2) ?></div>
                                        </div>
                                        <div class="col-6">
                                            <div class="small text-muted">Created</div>
                                            <div><?= formatDate($referral['created_at'], 'd M Y') ?></div>
                                            <div class="text-muted small"><?= timeAgo($referral['created_at']) ?></div>
                                        </div>
                                        <?php if ($referral['credited_at']): ?>
                                            <div class="col-12">
                                                <div class="small text-muted">Credited At</div>
                                                <div><?= formatDate($referral['credited_at'], 'd M Y, h:i A') ?></div>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                    
                                    <?php if ($referral['status'] === 'pending'): ?>
                                        <div class="d-grid">
                                            <button type="button" 
                                                    class="btn btn-sm btn-success" 
                                                    onclick="creditReward(<?= $referral['id'] ?>, '<?= htmlspecialchars($referral['referrer_name'] ?: 'User', ENT_QUOTES) ?>', '<?= htmlspecialchars($referral['referred_name'] ?: 'User', ENT_QUOTES) ?>')">
                                                <i class="bi bi-check-circle me-1"></i>Credit Reward
                                            </button>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
            
            <!-- Pagination -->
            <?php if ($totalPages > 1): ?>
                <nav aria-label="Referrals pagination" class="mt-4">
                    <ul class="pagination justify-content-center flex-wrap">
                        <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">
                            <a class="page-link" href="<?= app_url('admin/referrals?' . http_build_query(array_merge($_GET, ['page' => $page - 1]))) ?>">
                                <i class="bi bi-chevron-left d-md-none"></i>
                                <span class="d-none d-md-inline">Previous</span>
                            </a>
                        </li>
                        <?php for ($i = max(1, $page - 2); $i <= min($totalPages, $page + 2); $i++): ?>
                            <li class="page-item <?= $i === $page ? 'active' : '' ?>">
                                <a class="page-link" href="<?= app_url('admin/referrals?' . http_build_query(array_merge($_GET, ['page' => $i]))) ?>"><?= $i ?></a>
                            </li>
                        <?php endfor; ?>
                        <li class="page-item <?= $page >= $totalPages ? 'disabled' : '' ?>">
                            <a class="page-link" href="<?= app_url('admin/referrals?' . http_build_query(array_merge($_GET, ['page' => $page + 1]))) ?>">
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

<!-- Credit Reward Modal -->
<div class="modal fade" id="creditRewardModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" action="<?= htmlspecialchars(app_url('admin/referrals')) ?>" id="creditRewardForm">
                <input type="hidden" name="id" id="creditReferralId">
                <input type="hidden" name="action" value="credit">
                <input type="hidden" name="confirm_credit" value="1">
                <div class="modal-header">
                    <h5 class="modal-title text-success">Credit Referral Reward</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p>Are you sure you want to credit the reward for this referral?</p>
                    <div class="alert alert-info">
                        <strong>Referrer:</strong> <span id="creditReferrerName"></span><br>
                        <strong>Referred User:</strong> <span id="creditReferredName"></span>
                    </div>
                    <p class="text-muted small">This action will mark the referral as credited and record the credit timestamp.</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-success">Credit Reward</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function creditReward(referralId, referrerName, referredName) {
    document.getElementById('creditReferralId').value = referralId;
    document.getElementById('creditReferrerName').textContent = referrerName;
    document.getElementById('creditReferredName').textContent = referredName;
    new bootstrap.Modal(document.getElementById('creditRewardModal')).show();
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

/* Responsive fixes for referrals page */
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

