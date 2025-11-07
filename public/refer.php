<?php
$pageTitle = "Refer & Earn";
require __DIR__ . '/../app/includes/header.php';
$baseUrl = app_url('');

// Try to read referral code from session if available
$isLoggedIn = !empty($_SESSION['user_id']);
$referralCode = $_SESSION['referral_code'] ?? '';
$refLink = $referralCode ? app_url('register?ref=' . urlencode($referralCode)) : '';
?>

<div class="row g-4 align-items-center">
  <div class="col-lg-7">
    <h1 class="display-6">Refer your Friends and Win Rewards</h1>
    <p class="lead mb-3">When your friend makes a purchase, they will get <strong>₹500 off</strong> instantly, &amp; you will get <strong>₹1,500</strong> cash within 7 working days.</p>

    <?php if ($isLoggedIn && $referralCode): ?>
      <div class="card pg mb-3">
        <div class="card-body d-flex flex-wrap align-items-center justify-content-between gap-3">
          <div>
            <div class="kicker mb-1">Your referral code</div>
            <h3 class="mb-0"><?= htmlspecialchars($referralCode) ?></h3>
            <?php if ($refLink): ?>
            <div class="small text-muted mt-1">Share this link: <span class="text-break"><?= htmlspecialchars($refLink) ?></span></div>
            <?php endif; ?>
          </div>
          <div class="d-flex gap-2">
            <?php if ($refLink): ?>
            <button class="btn btn-primary" type="button" onclick="navigator.clipboard.writeText('<?= htmlspecialchars($refLink, ENT_QUOTES) ?>')">Copy Link</button>
            <?php endif; ?>
            <button class="btn btn-outline-secondary" type="button" onclick="navigator.clipboard.writeText('<?= htmlspecialchars($referralCode, ENT_QUOTES) ?>')">Copy Code</button>
          </div>
        </div>
      </div>
    <?php else: ?>
      <div class="alert alert-info d-flex align-items-center" role="alert">
        <i class="bi bi-person-check me-2"></i>
        <div>Login to see your referral code</div>
      </div>
      <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#loginModal">Login</button>
    <?php endif; ?>
  </div>

  <div class="col-lg-5">
    <div class="card pg h-100">
      <div class="card-body">
        <div class="kicker mb-2">How it works</div>
        <ol class="mb-0">
          <li class="mb-2">Share your code or referral link with friends.</li>
          <li class="mb-2">They sign up and make a booking/purchase on Livonto.</li>
          <li class="mb-0">You receive ₹1,500 cash within 7 working days.</li>
        </ol>
      </div>
    </div>
  </div>
</div>

<div class="mt-5">
  <h5 class="mb-3">Terms &amp; Conditions</h5>
  <ul class="text-muted">
    <li>Referrer should be an existing tenant of Livonto.</li>
    <li>Rewards earned from Refer &amp; Earn will be shown at your profile page.</li>
    <li>Points can neither be exchanged nor be transferred.</li>
    <li>Only registered users can use this benefit.</li>
    <li>Livonto reserves the right to amend these terms and conditions at any time without any prior notice.</li>
  </ul>
</div>

<?php require __DIR__ . '/../app/includes/footer.php'; ?>


