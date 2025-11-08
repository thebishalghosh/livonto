<?php
$pageTitle = "Refer & Earn";
require __DIR__ . '/../app/includes/header.php';
require __DIR__ . '/../app/functions.php';
$baseUrl = app_url('');

// Try to read referral code from session if available
$isLoggedIn = !empty($_SESSION['user_id']);
$userId = $_SESSION['user_id'] ?? null;
$referralCode = $_SESSION['referral_code'] ?? '';
$refLink = $referralCode ? app_url('register?ref=' . urlencode($referralCode)) : '';

// Get user's referral statistics if logged in
$referralStats = null;
$myReferrals = [];

if ($isLoggedIn && $userId) {
    try {
        $db = db();
        
        // Get user's referral statistics
        $referralStats = [
            'total_referrals' => (int)$db->fetchValue("SELECT COUNT(*) FROM referrals WHERE referrer_id = ?", [$userId]) ?: 0,
            'pending_referrals' => (int)$db->fetchValue("SELECT COUNT(*) FROM referrals WHERE referrer_id = ? AND status = 'pending'", [$userId]) ?: 0,
            'credited_referrals' => (int)$db->fetchValue("SELECT COUNT(*) FROM referrals WHERE referrer_id = ? AND status = 'credited'", [$userId]) ?: 0,
            'total_rewards' => (float)$db->fetchValue("SELECT COALESCE(SUM(reward_amount), 0) FROM referrals WHERE referrer_id = ? AND status = 'credited'", [$userId]) ?: 0,
            'pending_rewards' => (float)$db->fetchValue("SELECT COALESCE(SUM(reward_amount), 0) FROM referrals WHERE referrer_id = ? AND status = 'pending'", [$userId]) ?: 0,
        ];
        
        // Get user's referral list (last 10)
        $myReferrals = $db->fetchAll(
            "SELECT r.id, r.status, r.reward_amount, r.created_at, r.credited_at,
                    u.name as referred_name, u.email as referred_email
             FROM referrals r
             LEFT JOIN users u ON r.referred_id = u.id
             WHERE r.referrer_id = ?
             ORDER BY r.created_at DESC
             LIMIT 10",
            [$userId]
        );
        
        // Get referral code from database if not in session
        if (empty($referralCode)) {
            $userData = $db->fetchOne("SELECT referral_code FROM users WHERE id = ?", [$userId]);
            $referralCode = $userData['referral_code'] ?? '';
            $refLink = $referralCode ? app_url('register?ref=' . urlencode($referralCode)) : '';
        }
        
    } catch (Exception $e) {
        error_log("Error loading referral stats: " . $e->getMessage());
        $referralStats = [
            'total_referrals' => 0,
            'pending_referrals' => 0,
            'credited_referrals' => 0,
            'total_rewards' => 0,
            'pending_rewards' => 0,
        ];
    }
}
?>

<div class="container-xxl py-5">
    <div class="row g-4">
        <div class="col-lg-8">
            <div class="kicker mb-2">Refer & Earn</div>
            <h1 class="display-5 mb-3">Refer your Friends and Win Rewards</h1>
            <p class="lead text-muted mb-4">When your friend makes a purchase, they will get <strong>₹500 off</strong> instantly, &amp; you will get <strong>₹1,500</strong> cash within 7 working days.</p>

            <?php if ($isLoggedIn && $referralCode): ?>
                <!-- Referral Code Card -->
                <div class="card pg mb-4">
                    <div class="card-body p-4">
                        <div class="d-flex flex-column flex-md-row align-items-start align-items-md-center justify-content-between gap-3">
                            <div class="flex-grow-1">
                                <div class="kicker mb-2">Your Referral Code</div>
                                <h2 class="mb-3 fw-bold" style="color: var(--primary); font-size: 2rem; letter-spacing: 2px;"><?= htmlspecialchars($referralCode) ?></h2>
                                <?php if ($refLink): ?>
                                <div class="small text-muted">
                                    <i class="bi bi-link-45deg me-1"></i>Share this link: 
                                    <span class="text-break d-inline-block" style="max-width: 100%; word-break: break-all;"><?= htmlspecialchars($refLink) ?></span>
                                </div>
                                <?php endif; ?>
                            </div>
                            <div class="d-flex flex-column gap-2 w-100 w-md-auto">
                                <?php if ($refLink): ?>
                                <button class="btn btn-primary" type="button" id="copyLinkBtn" onclick="copyToClipboard('<?= htmlspecialchars($refLink, ENT_QUOTES) ?>', 'copyLinkBtn')">
                                    <i class="bi bi-link-45deg me-2"></i>Copy Link
                                </button>
                                <?php endif; ?>
                                <button class="btn btn-outline-secondary" type="button" id="copyCodeBtn" onclick="copyToClipboard('<?= htmlspecialchars($referralCode, ENT_QUOTES) ?>', 'copyCodeBtn')">
                                    <i class="bi bi-clipboard me-2"></i>Copy Code
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- My Referrals Statistics -->
                <?php if ($referralStats): ?>
                <div class="row g-3 mb-4">
                    <div class="col-md-4">
                        <div class="card pg" style="background: linear-gradient(135deg, var(--primary) 0%, var(--primary-700) 100%); color: white; border: none;">
                            <div class="card-body text-center p-4">
                                <h3 class="mb-2 fw-bold"><?= number_format($referralStats['total_referrals']) ?></h3>
                                <p class="mb-0 small opacity-90 text-white">Total Referrals</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="card pg" style="background: linear-gradient(135deg, #43e97b 0%, #38f9d7 100%); color: white; border: none;">
                            <div class="card-body text-center p-4">
                                <h3 class="mb-2 fw-bold"><?= number_format($referralStats['credited_referrals']) ?></h3>
                                <p class="mb-0 small opacity-90 text-white">Credited</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="card pg" style="background: linear-gradient(135deg, #fbbf24 0%, #f59e0b 100%); color: white; border: none;">
                            <div class="card-body text-center p-4">
                                <h3 class="mb-2 fw-bold">₹<?= number_format($referralStats['total_rewards']) ?></h3>
                                <p class="mb-0 small opacity-90 text-white">Total Rewards</p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- My Referrals List -->
                <?php if (!empty($myReferrals)): ?>
                <div class="card pg">
                    <div class="card-header referral-table-header">
                        <h5 class="mb-0 referral-header-title"><i class="bi bi-people me-2"></i>My Referrals</h5>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-hover mb-0 referral-table">
                                <thead class="referral-table-thead">
                                    <tr>
                                        <th>Referred User</th>
                                        <th>Status</th>
                                        <th>Reward</th>
                                        <th>Date</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($myReferrals as $ref): ?>
                                    <tr>
                                        <td>
                                            <div class="fw-semibold"><?= htmlspecialchars($ref['referred_name'] ?: 'Unknown') ?></div>
                                            <div class="small text-muted"><?= htmlspecialchars($ref['referred_email'] ?: '') ?></div>
                                        </td>
                                        <td>
                                            <span class="badge" style="background: <?= $ref['status'] === 'credited' ? 'linear-gradient(135deg, #43e97b 0%, #38f9d7 100%)' : 'linear-gradient(135deg, #fbbf24 0%, #f59e0b 100%)' ?>; color: white; border: none;">
                                                <?= ucfirst($ref['status']) ?>
                                            </span>
                                        </td>
                                        <td class="fw-semibold referral-reward">₹<?= number_format($ref['reward_amount'], 2) ?></td>
                                        <td>
                                            <div class="small"><?= formatDate($ref['created_at'], 'd M Y') ?></div>
                                            <?php if ($ref['credited_at']): ?>
                                            <div class="small text-muted">Credited: <?= formatDate($ref['credited_at'], 'd M Y') ?></div>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
                <?php else: ?>
                <div class="card pg">
                    <div class="card-body text-center py-5">
                        <i class="bi bi-gift fs-1 d-block mb-3" style="color: var(--accent);"></i>
                        <h5 class="mb-2" style="color: var(--primary-700);">No referrals yet</h5>
                        <p class="text-muted mb-0">Start sharing your referral code to earn rewards!</p>
                    </div>
                </div>
                <?php endif; ?>
                <?php endif; ?>

            <?php else: ?>
                <div class="card pg mb-4">
                    <div class="card-body p-4">
                        <div class="d-flex align-items-center mb-3">
                            <i class="bi bi-person-check me-3 fs-1" style="color: var(--primary);"></i>
                            <div>
                                <h5 class="mb-1" style="color: var(--primary-700);">Login required</h5>
                                <p class="text-muted mb-0 small">Please login to see your referral code and start earning rewards.</p>
                            </div>
                        </div>
                        <button class="btn btn-primary btn-lg" data-bs-toggle="modal" data-bs-target="#loginModal">
                            <i class="bi bi-box-arrow-in-right me-2"></i>Login Now
                        </button>
                    </div>
                </div>
            <?php endif; ?>
        </div>

        <div class="col-lg-4">
            <!-- How it Works -->
            <div class="card pg mb-4">
                <div class="card-body">
                    <h5 class="mb-3" style="color: var(--primary-700);">
                        <i class="bi bi-info-circle me-2"></i>How it works
                    </h5>
                    <ol class="mb-0 ps-3">
                        <li class="mb-3">Share your unique referral code or link with friends and family.</li>
                        <li class="mb-3">They sign up using your referral code and make a booking on Livonto.</li>
                        <li class="mb-0">You receive ₹1,500 cash reward within 7 working days after their first booking.</li>
                    </ol>
                </div>
            </div>

            <!-- Benefits -->
            <div class="card pg mb-4">
                <div class="card-body">
                    <h5 class="mb-3" style="color: var(--primary-700);">
                        <i class="bi bi-gift me-2"></i>Benefits
                    </h5>
                    <ul class="mb-0 ps-3">
                        <li class="mb-2"><strong>For You:</strong> ₹1,500 cash reward per successful referral</li>
                        <li class="mb-2"><strong>For Your Friend:</strong> ₹500 instant discount on first booking</li>
                        <li class="mb-0"><strong>Unlimited:</strong> Refer as many friends as you want!</li>
                    </ul>
                </div>
            </div>

            <!-- Terms & Conditions -->
            <div class="card pg">
                <div class="card-body">
                    <h5 class="mb-3" style="color: var(--primary-700);">Terms &amp; Conditions</h5>
                    <ul class="small text-muted mb-0 ps-3">
                        <li class="mb-2">Referrer should be an existing user of Livonto.</li>
                        <li class="mb-2">Rewards are credited after the referred user completes their first booking.</li>
                        <li class="mb-2">Rewards will be processed within 7 working days.</li>
                        <li class="mb-2">Only registered users can use this benefit.</li>
                        <li class="mb-0">Livonto reserves the right to amend these terms and conditions at any time.</li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function copyToClipboard(text, buttonId) {
    navigator.clipboard.writeText(text).then(function() {
        const btn = document.getElementById(buttonId);
        const originalText = btn.innerHTML;
        btn.innerHTML = '<i class="bi bi-check me-2"></i>Copied!';
        btn.classList.add('btn-success');
        btn.classList.remove('btn-primary', 'btn-outline-primary');
        setTimeout(function() {
            btn.innerHTML = originalText;
            btn.classList.remove('btn-success');
            btn.classList.add(buttonId.includes('Link') ? 'btn-primary' : 'btn-outline-primary');
        }, 2000);
    }).catch(function(err) {
        alert('Failed to copy: ' + err);
    });
}

</script>

<style>
/* Refer page dark mode styling */
.referral-table-header {
    background: var(--accent);
    border-bottom: 1px solid var(--border);
}

.referral-header-title {
    color: var(--primary-700);
}

.referral-table-thead {
    background: var(--bg);
}

.referral-table-thead th {
    color: var(--primary-700);
    border-bottom: 1px solid var(--border);
}

.referral-table tbody tr {
    border-bottom: 1px solid var(--border);
}

.referral-table tbody tr:hover {
    background: var(--accent);
}

.referral-reward {
    color: var(--primary-700);
}

/* Dark mode overrides */
:root[data-theme="dark"] .referral-table-header {
    background: rgba(75, 59, 99, 0.3);
    border-bottom: 1px solid rgba(255, 255, 255, 0.04);
}

:root[data-theme="dark"] .referral-header-title {
    color: var(--primary);
}

:root[data-theme="dark"] .referral-table-thead {
    background: rgba(18, 18, 33, 0.5);
}

:root[data-theme="dark"] .referral-table-thead th {
    color: var(--primary);
    border-bottom: 1px solid rgba(255, 255, 255, 0.04);
}

:root[data-theme="dark"] .referral-table tbody tr {
    border-bottom: 1px solid rgba(255, 255, 255, 0.04);
}

:root[data-theme="dark"] .referral-table tbody tr:hover {
    background: rgba(75, 59, 99, 0.2);
}

:root[data-theme="dark"] .referral-reward {
    color: var(--primary);
}

:root[data-theme="dark"] .referral-table tbody tr td {
    color: var(--muted);
}

:root[data-theme="dark"] .referral-table tbody tr td .fw-semibold {
    color: #efeaff;
}
</style>

<?php require __DIR__ . '/../app/includes/footer.php'; ?>
