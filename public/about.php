<?php
$pageTitle = "About Livonto";
require __DIR__ . '/../app/includes/header.php';
$baseUrl = app_url('');
?>

<div class="row g-4">
  <!-- Main column -->
  <main class="col-12">
    <header class="mb-3">
      <h1 class="display-6 mb-1">About Livonto</h1>
      <p class="lead text-muted">A trusted platform simplifying the search for quality PG accommodations.</p>
    </header>

    <!-- Intro card -->
    <section class="card pg mb-4">
      <div class="card-body">
        <p class="mb-3">
          Ayushman Agarwal and Aditiya Agarwal, co-founders and students at St. Xavier’s College, Kolkata,
          identified a gap in the PG rental market and launched Livonto — a trusted platform simplifying the
          search for quality accommodations.
        </p>
        <p class="mb-3">
          With a vision to create a thriving community of happy tenants, Livonto streamlines the PG search process,
          ensuring students can effortlessly find and secure suitable housing while also benefiting PG owners.
        </p>
        <p class="mb-0">
          Leading the tech innovation, CTO Amaan Ansari is developing a robust platform that enables students to
          explore PGs seamlessly from anywhere, redefining the student housing experience with efficiency and
          reliability.
        </p>
      </div>
    </section>

    <!-- Mission & Vision -->
    <section class="row g-3 mb-4">
      <div class="col-md-6">
        <div class="card pg h-100">
          <div class="card-body">
            <div class="kicker mb-1">Our Mission</div>
            <h5 class="mb-2">Make PG discovery effortless</h5>
            <p class="text-muted mb-0">We help students find verified, comfortable, and affordable PGs faster with transparent information and a delightful experience.</p>
          </div>
        </div>
      </div>
      <div class="col-md-6">
        <div class="card pg h-100">
          <div class="card-body">
            <div class="kicker mb-1">Our Vision</div>
            <h5 class="mb-2">A thriving community of happy tenants</h5>
            <p class="text-muted mb-0">We aim to be India’s most trusted PG platform, loved by students and hosts for its reliability, speed, and support.</p>
          </div>
        </div>
      </div>
    </section>

    <!-- Story timeline -->
    <section class="mb-4">
      <div class="kicker mb-1">Our Story</div>
      <h5 class="mb-4">From a college idea to a growing platform</h5>

      <div class="timeline">
        <div class="timeline-item">
          <span class="timeline-dot"></span>
          <div class="timeline-card">
            <div class="timeline-date">2024</div>
            <h6 class="timeline-title">Problem discovered</h6>
            <p class="timeline-description">Struggles renting PGs sparked the idea: a simpler, trustworthy way was needed.</p>
          </div>
        </div>

        <div class="timeline-item">
          <span class="timeline-dot"></span>
          <div class="timeline-card">
            <div class="timeline-date">Early 2025</div>
            <h6 class="timeline-title">Livonto is born</h6>
            <p class="timeline-description">The founders start building and validating with real students and hosts.</p>
          </div>
        </div>

        <div class="timeline-item">
          <span class="timeline-dot"></span>
          <div class="timeline-card">
            <div class="timeline-date">Today</div>
            <h6 class="timeline-title">Growing community</h6>
            <p class="timeline-description">Continuous improvements, more listings, and a fast, reliable experience.</p>
          </div>
        </div>
      </div>
    </section>

    <script>
      document.addEventListener('DOMContentLoaded', function () {
        const items = document.querySelectorAll('.timeline-item');
        if (!('IntersectionObserver' in window) || !items.length) {
          items.forEach(item => item.classList.add('is-visible'));
          return;
        }

        const observer = new IntersectionObserver((entries, obs) => {
          entries.forEach(entry => {
            if (entry.isIntersecting) {
              entry.target.classList.add('is-visible');
              obs.unobserve(entry.target);
            }
          });
        }, {
          threshold: 0.3
        });

        items.forEach(item => observer.observe(item));
      });
    </script>

    <!-- Our Team -->
    <section class="mb-4">
      <div class="kicker mb-1">Our Team</div>
      <h5 class="mb-3">The people behind Livonto</h5>

      <div class="row g-4 justify-content-center">
        <div class="col-12 col-md-6">
          <div class="card pg h-100 text-center team-card">
            <div class="card-body">
              <div>
                <img src="<?= htmlspecialchars($baseUrl . '/public/assets/images/Ayushman.jpg') ?>"
                     alt="Ayushman Agarwal"
                     class="img-fluid rounded-circle"
                     style="width: 180px; height: 180px; object-fit: cover; border: 4px solid var(--primary); box-shadow: 0 4px 12px rgba(0,0,0,0.1);">
              </div>
              <h5 class="team-name mt-3 mb-1">Ayushman Agarwal</h5>
              <p class="team-role mb-0">Co-founder · Product & Partnerships</p>
            </div>
          </div>
        </div>
        <div class="col-12 col-md-6">
          <div class="card pg h-100 text-center team-card">
            <div class="card-body">
              <div>
                <img src="<?= htmlspecialchars($baseUrl . '/public/assets/images/Aditya.jpg') ?>"
                     alt="Aditya Agarwal"
                     class="img-fluid rounded-circle"
                     style="width: 180px; height: 180px; object-fit: cover; border: 4px solid var(--primary); box-shadow: 0 4px 12px rgba(0,0,0,0.1);">
              </div>
              <h5 class="team-name mt-3 mb-1">Aditya Agarwal</h5>
              <p class="team-role mb-0">Co-founder · Operations & Growth</p>
            </div>
          </div>
        </div>
      </div>
    </section>

    <!-- How it works -->
    <section class="mb-4">
      <div class="kicker mb-1">How it works</div>
      <h5 class="mb-3">Find your PG in three easy steps</h5>

      <div class="row g-3">
        <div class="col-12 col-md-4">
          <div class="card pg h-100">
            <div class="card-body d-flex align-items-start gap-3">
              <i class="bi bi-search fs-3 text-primary"></i>
              <div>
                <h6 class="mb-1">Search</h6>
                <p class="small text-muted mb-0">Filter by location, budget, and amenities to see the best matches.</p>
              </div>
            </div>
          </div>
        </div>
        <div class="col-12 col-md-4">
          <div class="card pg h-100">
            <div class="card-body d-flex align-items-start gap-3">
              <i class="bi bi-chat-text fs-3 text-primary"></i>
              <div>
                <h6 class="mb-1">Connect</h6>
                <p class="small text-muted mb-0">Message hosts to ask questions or schedule a visit quickly.</p>
              </div>
            </div>
          </div>
        </div>
        <div class="col-12 col-md-4">
          <div class="card pg h-100">
            <div class="card-body d-flex align-items-start gap-3">
              <i class="bi bi-check2-circle fs-3 text-primary"></i>
              <div>
                <h6 class="mb-1">Book</h6>
                <p class="small text-muted mb-0">Confirm your stay and move in — simple and transparent.</p>
              </div>
            </div>
          </div>
        </div>
      </div>
    </section>

    <!-- Values -->
    <section class="mb-4">
      <div class="kicker mb-1">Our Values</div>
      <h5 class="mb-3">What guides us</h5>
      <ul class="list-group list-group-flush">
        <li class="list-group-item">Trust &amp; Transparency</li>
        <li class="list-group-item">Student-first Experience</li>
        <li class="list-group-item">Speed with Reliability</li>
        <li class="list-group-item">Continuous Improvement</li>
      </ul>
    </section>

    <!-- FAQ -->
    <section class="mb-4">
      <div class="kicker mb-1">FAQ</div>
      <h5 class="mb-3">Frequently asked questions</h5>
      <div class="accordion" id="aboutFaq">
        <div class="accordion-item">
          <h2 class="accordion-header" id="q1h">
            <button class="accordion-button" type="button" data-bs-toggle="collapse" data-bs-target="#q1" aria-expanded="true" aria-controls="q1">
              Is Livonto free for students?
            </button>
          </h2>
          <div id="q1" class="accordion-collapse collapse show" aria-labelledby="q1h" data-bs-parent="#aboutFaq">
            <div class="accordion-body">
              Yes. Browsing PGs and contacting hosts is free for students. Some premium services may be optional.
            </div>
          </div>
        </div>
        <div class="accordion-item">
          <h2 class="accordion-header" id="q2h">
            <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#q2" aria-expanded="false" aria-controls="q2">
              How do you verify listings?
            </button>
          </h2>
          <div id="q2" class="accordion-collapse collapse" aria-labelledby="q2h" data-bs-parent="#aboutFaq">
            <div class="accordion-body">
              We review host details, photos, and key documents. Community feedback helps us keep listings accurate.
            </div>
          </div>
        </div>
        <div class="accordion-item">
          <h2 class="accordion-header" id="q3h">
            <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#q3" aria-expanded="false" aria-controls="q3">
              Can I list my PG on Livonto?
            </button>
          </h2>
          <div id="q3" class="accordion-collapse collapse" aria-labelledby="q3h" data-bs-parent="#aboutFaq">
            <div class="accordion-body">
              Absolutely. Hosts can add and manage listings on the <a href="<?= htmlspecialchars(app_url('host-dashboard')) ?>">Host Dashboard</a>.
            </div>
          </div>
        </div>
      </div>
    </section>

    <!-- CTA -->
    <section class="mb-2">
      <div class="p-4 rounded-3" style="background: linear-gradient(90deg, var(--primary), var(--primary-700));">
        <div class="d-flex flex-wrap align-items-center justify-content-between gap-3">
          <div class="text-white">
            <div class="kicker" style="color: rgba(255,255,255,0.8)">Are you a PG owner?</div>
            <h5 class="mb-1 text-white">Get listed on Livonto</h5>
            <div class="small" style="color: rgba(255,255,255,0.9)">PG Booking Enquiry: 6293010501 | 7047133182 | 9831068248 • Timings: 10:00 AM to 8:00 PM</div>
          </div>
          <a class="btn btn-light" href="<?= htmlspecialchars(app_url('contact')) ?>">Contact Us</a>
        </div>
      </div>
    </section>

  </main>
</div>

<?php require __DIR__ . '/../app/includes/footer.php'; ?>