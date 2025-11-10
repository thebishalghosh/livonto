<?php
// app/includes/footer.php
// Load settings if functions.php is available
if (function_exists('getSetting')) {
    $siteName = getSetting('site_name', 'Livonto');
    $siteTagline = getSetting('site_tagline', 'Find, compare and book PGs instantly. Reliable listings, verified hosts, and smooth booking experience.');
    $contactEmail = getSetting('contact_email', 'support@pgfinder.com');
    $contactPhone = getSetting('contact_phone', '+91 9876543210');
    $bookingEnquiryPhone = getSetting('booking_enquiry_phone', '6293010501 | 7047133182 | 9831068248');
    $bookingTimings = getSetting('booking_timings', '10:00 AM to 8:00 PM');
    // Use footer_copyright if set, otherwise use copyright_text, otherwise default
    $footerCopyright = getSetting('footer_copyright', '');
    if (empty($footerCopyright)) {
        $footerCopyright = getSetting('copyright_text', '© ' . date('Y') . ' Livonto — All Rights Reserved.');
    }
    // Replace {YEAR} placeholder if present
    $footerCopyright = str_replace('{YEAR}', date('Y'), $footerCopyright);
    $facebookUrl = getSetting('facebook_url', '');
    $instagramUrl = getSetting('instagram_url', '');
    $twitterUrl = getSetting('twitter_url', '');
    $linkedinUrl = getSetting('linkedin_url', '');
} else {
    // Fallback to defaults if function doesn't exist
    $siteName = 'Livonto';
    $siteTagline = 'Find, compare and book PGs instantly. Reliable listings, verified hosts, and smooth booking experience.';
    $contactEmail = 'support@pgfinder.com';
    $contactPhone = '+91 9876543210';
    $bookingEnquiryPhone = '6293010501 | 7047133182 | 9831068248';
    $bookingTimings = '10:00 AM to 8:00 PM';
    $footerCopyright = '© ' . date('Y') . ' Livonto — All Rights Reserved.';
    $facebookUrl = '';
    $instagramUrl = '';
    $twitterUrl = '';
    $linkedinUrl = '';
}

// Parse booking enquiry phone numbers
$bookingPhones = array_map('trim', explode('|', $bookingEnquiryPhone));
?>
</div> <!-- /container -->

<footer class="bg-dark text-light mt-5 pt-5 pb-3">
  <div class="container-xxl">

    <div class="row gy-4">

      <!-- Column 1: About -->
      <div class="col-md-3 col-sm-6">
        <a href="<?= htmlspecialchars(app_url('')) ?>" class="d-inline-block mb-3">
          <img src="<?= htmlspecialchars($baseUrl . '/public/assets/images/logo-white-removebg.png') ?>" 
               alt="<?= htmlspecialchars($siteName) ?>" 
               class="footer-logo" 
               style="max-height: 50px; width: auto;">
        </a>
        <p class="small text-white">
          <?= htmlspecialchars($siteTagline) ?>
        </p>
      </div>

      <!-- Column 2: Quick Links -->
      <div class="col-md-3 col-sm-6">
        <h6 class="fw-bold mb-3">Quick Links</h6>
        <ul class="list-unstyled small text-white">
          <li class="mb-2"><a href="<?= htmlspecialchars(app_url('listings')) ?>" class="text-light text-decoration-none">Browse Listings</a></li>
          <li class="mb-2"><a href="<?= htmlspecialchars(app_url('profile')) ?>" class="text-light text-decoration-none">My Profile</a></li>
          <li class="mb-2"><a href="#" class="text-light text-decoration-none" data-bs-toggle="modal" data-bs-target="#loginModal">Login</a></li>
          <li class="mb-2"><a href="#" class="text-light text-decoration-none" data-bs-toggle="modal" data-bs-target="#registerModal">Register</a></li>
        </ul>
      </div>

      <!-- Column 3: For Hosts -->
      <div class="col-md-3 col-sm-6">
        <h6 class="fw-bold mb-3">Get Listed</h6>
        <ul class="list-unstyled small">
          <li class="mb-2">Are you a PG owner?</li>
          <li class="mb-2">PG Booking Enquiry:</li>
          <li class="mb-2">
            <?php if (!empty($bookingPhones)): ?>
              <?php foreach ($bookingPhones as $index => $phone): ?>
                <?php if ($index > 0): ?><span class="mx-1">|</span><?php endif; ?>
                <a class="text-light text-decoration-none" href="tel:+91<?= preg_replace('/[^0-9]/', '', $phone) ?>"><?= htmlspecialchars($phone) ?></a>
              <?php endforeach; ?>
            <?php else: ?>
              <span class="text-light"><?= htmlspecialchars($bookingEnquiryPhone) ?></span>
            <?php endif; ?>
          </li>
          <li class="mb-2">Timings: <?= htmlspecialchars($bookingTimings) ?></li>
          <li class="mb-2"><a href="<?= htmlspecialchars(app_url('contact')) ?>" class="text-light text-decoration-none">Contact us to list</a></li>
        </ul>
      </div>

      <!-- Column 4: Contact -->
      <div class="col-md-3 col-sm-6">
        <h6 class="fw-bold mb-3">Contact Us</h6>
        <p class="small mb-2">
          <span class="text-white fw-semibold">Email:</span> 
          <a href="mailto:<?= htmlspecialchars($contactEmail) ?>" class="text-light text-decoration-none"><?= htmlspecialchars($contactEmail) ?></a>
        </p>
        <p class="small mb-3">
          <span class="text-white fw-semibold">Phone:</span> 
          <a href="tel:<?= preg_replace('/[^0-9+]/', '', $contactPhone) ?>" class="text-light text-decoration-none"><?= htmlspecialchars($contactPhone) ?></a>
        </p>

        <!-- Social icons -->
        <div class="mt-3">
          <p class="small text-white fw-semibold mb-2">Follow Us:</p>
          <div class="d-flex gap-3">
            <?php if (!empty($facebookUrl)): ?>
              <a href="<?= htmlspecialchars($facebookUrl) ?>" target="_blank" rel="noopener" class="text-light fs-4 social-icon" title="Facebook">
                <i class="bi bi-facebook"></i>
              </a>
            <?php else: ?>
              <span class="text-light fs-4 social-icon-disabled" style="opacity: 0.5;" title="Facebook URL not set - Add in Admin Settings">
                <i class="bi bi-facebook"></i>
              </span>
            <?php endif; ?>
            <?php if (!empty($instagramUrl)): ?>
              <a href="<?= htmlspecialchars($instagramUrl) ?>" target="_blank" rel="noopener" class="text-light fs-4 social-icon" title="Instagram">
                <i class="bi bi-instagram"></i>
              </a>
            <?php else: ?>
              <span class="text-light fs-4 social-icon-disabled" style="opacity: 0.5;" title="Instagram URL not set - Add in Admin Settings">
                <i class="bi bi-instagram"></i>
              </span>
            <?php endif; ?>
            <?php if (!empty($twitterUrl)): ?>
              <a href="<?= htmlspecialchars($twitterUrl) ?>" target="_blank" rel="noopener" class="text-light fs-4 social-icon" title="Twitter/X">
                <i class="bi bi-twitter"></i>
              </a>
            <?php else: ?>
              <span class="text-light fs-4 social-icon-disabled" style="opacity: 0.5;" title="Twitter/X URL not set - Add in Admin Settings">
                <i class="bi bi-twitter"></i>
              </span>
            <?php endif; ?>
            <?php if (!empty($linkedinUrl)): ?>
              <a href="<?= htmlspecialchars($linkedinUrl) ?>" target="_blank" rel="noopener" class="text-light fs-4 social-icon" title="LinkedIn">
                <i class="bi bi-linkedin"></i>
              </a>
            <?php else: ?>
              <span class="text-light fs-4 social-icon-disabled" style="opacity: 0.5;" title="LinkedIn URL not set - Add in Admin Settings">
                <i class="bi bi-linkedin"></i>
              </span>
            <?php endif; ?>
          </div>
        </div>
      </div>

    </div>

    <hr class="mt-4 mb-3 border-secondary">

    <div class="text-center small text-muted">
      <?= htmlspecialchars($footerCopyright) ?>
    </div>

  </div>
</footer>

<!-- Bootstrap JS bundle -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>

<!-- Leaflet JS (for maps) -->
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" 
        integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo=" 
        crossorigin=""></script>

<!-- Main JS -->
<script src="<?= htmlspecialchars($baseUrl . '/public/assets/js/main.js') ?>"></script>

<!-- Map JS (loaded conditionally) -->
<script>
  // Set baseUrl for map.js
  const baseUrl = '<?= htmlspecialchars($baseUrl) ?>';
</script>
<script src="<?= htmlspecialchars($baseUrl . '/public/assets/js/map.js') ?>"></script>

<!-- Autocomplete JS -->
<script src="<?= htmlspecialchars($baseUrl . '/public/assets/js/autocomplete.js') ?>"></script>

</body>
</html>
