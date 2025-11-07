<?php
$pageTitle = "Contact";
require __DIR__ . '/../app/includes/header.php';
?>

<div class="row g-4">
  <!-- Main -->
  <div class="col-12">
    <h1 class="display-6 mb-2">Contact Us</h1>
    <p class="text-muted">Interested in listing your PG or have a question? Reach out and we’ll get back to you soon.</p>

    <!-- Enquiry & info -->
    <div class="card pg mb-4">
      <div class="card-body">
        <div class="row g-3 align-items-start">
          <div class="col-md-8">
            <div class="kicker mb-1">For PG Booking Enquiry</div>
            <div class="fw-semibold mb-1">
              <a class="text-decoration-none" href="tel:+916293010501">6293010501</a>
              <span class="mx-2">|</span>
              <a class="text-decoration-none" href="tel:+917047133182">7047133182</a>
              <span class="mx-2">|</span>
              <a class="text-decoration-none" href="tel:+919831068248">9831068248</a>
            </div>
            <div class="small text-muted">Timings: 10:00 AM to 8:00 PM</div>
          </div>
          <div class="col-md-4">
            <div class="kicker mb-1">Email</div>
            <div class="fw-semibold"><a class="text-decoration-none" href="mailto:support@pgfinder.com">support@pgfinder.com</a></div>
          </div>
        </div>
      </div>
    </div>

    <!-- Message form -->
    <div class="card pg mb-4">
      <div class="card-body">
        <h6 class="mb-3">Send us a message</h6>
        <form method="post" action="<?= htmlspecialchars(app_url('/app/mailer.php')) ?>">
          <div class="row g-3">
            <div class="col-md-6">
              <label class="form-label" for="name">Full name</label>
              <input class="form-control" id="name" name="name" required>
            </div>
            <div class="col-md-6">
              <label class="form-label" for="email">Email</label>
              <input type="email" class="form-control" id="email" name="email" required>
            </div>
            <div class="col-12">
              <label class="form-label" for="subject">Subject</label>
              <input class="form-control" id="subject" name="subject" required>
            </div>
            <div class="col-12">
              <label class="form-label" for="message">Message</label>
              <textarea class="form-control" id="message" name="message" rows="5" required></textarea>
            </div>
            <div class="col-12">
              <button class="btn btn-primary" type="submit">Send message</button>
            </div>
          </div>
        </form>
      </div>
    </div>

    <!-- Alternate contact methods -->
    <div class="card pg mb-4">
      <div class="card-body">
        <h6 class="mb-2">Prefer WhatsApp?</h6>
        <p class="small text-muted mb-3">Message us with your city, budget and move-in date. We’ll respond during office hours.</p>
        <a class="btn btn-outline-secondary me-2" href="https://wa.me/916293010501" target="_blank" rel="noopener">WhatsApp 6293010501</a>
        <a class="btn btn-outline-secondary me-2" href="https://wa.me/917047133182" target="_blank" rel="noopener">WhatsApp 7047133182</a>
        <a class="btn btn-outline-secondary" href="https://wa.me/919831068248" target="_blank" rel="noopener">WhatsApp 9831068248</a>
      </div>
    </div>

    <!-- Map / Address -->
    <div class="card pg mb-3">
      <div class="card-body">
        <h6 class="mb-2">Kolkata Office</h6>
        <p class="small text-muted mb-2">St. Xavier’s College area, Kolkata</p>
        <div class="ratio ratio-4x3 rounded overflow-hidden">
          <iframe
            src="https://www.google.com/maps?q=St.+Xavier%E2%80%99s+College,+Kolkata&output=embed"
            allowfullscreen
            loading="lazy"
            referrerpolicy="no-referrer-when-downgrade"
            style="border:0"></iframe>
        </div>
      </div>
    </div>

    <!-- Quick links -->
    <div class="card pg mb-3">
      <div class="card-body">
        <h6 class="mb-2">Quick links</h6>
        <ul class="list-unstyled small mb-0">
          <li class="mb-2"><a href="<?= htmlspecialchars(app_url('listings')) ?>">Browse listings</a></li>
          <li class="mb-2"><a href="<?= htmlspecialchars(app_url('about')) ?>">About Livonto</a></li>
          <li class="mb-2"><a href="tel:+916293010501">Call: 6293010501</a></li>
        </ul>
      </div>
    </div>

    <!-- FAQ mini -->
    <div class="card pg mb-3">
      <div class="card-body">
        <h6 class="mb-2">FAQ</h6>
        <div class="accordion" id="contactFaq">
          <div class="accordion-item">
            <h2 class="accordion-header" id="cq1h">
              <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#cq1" aria-expanded="false" aria-controls="cq1">
                How soon will I get a response?
              </button>
            </h2>
            <div id="cq1" class="accordion-collapse collapse" aria-labelledby="cq1h" data-bs-parent="#contactFaq">
              <div class="accordion-body small">We typically respond within business hours (10:00 AM to 8:00 PM).</div>
            </div>
          </div>
          <div class="accordion-item">
            <h2 class="accordion-header" id="cq2h">
              <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#cq2" aria-expanded="false" aria-controls="cq2">
                Can I list my PG with you?
              </button>
            </h2>
            <div id="cq2" class="accordion-collapse collapse" aria-labelledby="cq2h" data-bs-parent="#contactFaq">
              <div class="accordion-body small">Yes. Please call or send us a message using the numbers above and we’ll guide you.</div>
            </div>
          </div>
        </div>
      </div>
    </div>

  </div>
</div>

<?php require __DIR__ . '/../app/includes/footer.php'; ?>
