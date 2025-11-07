<?php
// app/includes/footer.php
?>
</div> <!-- /container -->

<footer class="bg-dark text-light mt-5 pt-5 pb-3">
  <div class="container-xxl">

    <div class="row gy-4">

      <!-- Column 1: About -->
      <div class="col-md-3 col-sm-6">
        <h5 class="fw-bold mb-3">PG Finder</h5>
        <p class="small text-muted">
          Find, compare and book PGs instantly.  
          Reliable listings, verified hosts, and smooth booking experience.
        </p>
      </div>

      <!-- Column 2: Quick Links -->
      <div class="col-md-3 col-sm-6">
        <h6 class="fw-bold mb-3">Quick Links</h6>
        <ul class="list-unstyled small">
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
            <a class="text-light text-decoration-none" href="tel:+916293010501">6293010501</a>
            <span class="mx-1">|</span>
            <a class="text-light text-decoration-none" href="tel:+917047133182">7047133182</a>
            <span class="mx-1">|</span>
            <a class="text-light text-decoration-none" href="tel:+919831068248">9831068248</a>
          </li>
          <li class="mb-2">Timings: 10:00 AM to 8:00 PM</li>
          <li class="mb-2"><a href="<?= htmlspecialchars(app_url('contact')) ?>" class="text-light text-decoration-none">Contact us to list</a></li>
        </ul>
      </div>

      <!-- Column 4: Contact -->
      <div class="col-md-3 col-sm-6">
        <h6 class="fw-bold mb-3">Contact Us</h6>
        <p class="small text-muted mb-1">Email: support@pgfinder.com</p>
        <p class="small text-muted mb-3">Phone: +91 9876543210</p>

        <!-- Social icons -->
        <div class="d-flex gap-3">
          <a href="#" class="text-light fs-5"><i class="bi bi-facebook"></i></a>
          <a href="#" class="text-light fs-5"><i class="bi bi-instagram"></i></a>
          <a href="#" class="text-light fs-5"><i class="bi bi-twitter"></i></a>
        </div>
      </div>

    </div>

    <hr class="mt-4 mb-3 border-secondary">

    <div class="text-center small text-muted">
      &copy; <?= date('Y') ?> Livonto — All Rights Reserved.
    </div>

  </div>
</footer>

<!-- Bootstrap JS bundle -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script src="<?= htmlspecialchars($baseUrl . '/public/assets/js/main.js') ?>"></script>

</body>
</html>
