<?php
$pageTitle = "Find your PG";
require __DIR__ . '/../app/includes/header.php';
$baseUrl = app_url('');
?>
<div class="row g-4 align-items-center">
  <div class="col-md-7">
    <h1 class="display-5">Discover PGs near you</h1>
    <p class="text-muted">Find trusted PGs, compare amenities, and book with confidence.</p>

    <form id="searchForm" class="row g-2 search-bar">
      <div class="col-md-6">
        <input id="q" class="form-control search-input" placeholder="Search (e.g. near IIT, locality)">
      </div>
      <div class="col-md-4">
        <input id="city" class="form-control" placeholder="City">
      </div>
      <div class="col-md-2 d-grid">
        <button class="btn btn-primary">Search</button>
      </div>
    </form>
  </div>

  <div class="col-md-5 text-center">
    <img src="<?= htmlspecialchars($baseUrl . '/public/assets/images/livonto-image.jpg') ?>" alt="Livonto" class="img-fluid" style="max-height:260px; border-radius: 12px; box-shadow: 0 4px 12px rgba(0,0,0,0.1);">
  </div>
</div>


<!-- Feature highlights -->
<div class="row g-3 mt-4">
  <div class="col-md-4">
    <div class="card pg">
      <div class="card-body d-flex align-items-start gap-3">
        <i class="bi bi-shield-check fs-3 text-primary"></i>
        <div>
          <div class="kicker mb-1">Verified</div>
          <h6 class="mb-1">Trusted Listings</h6>
          <p class="small text-muted mb-0">Profiles validated by our team so you can book with confidence.</p>
        </div>
      </div>
    </div>
  </div>
  <div class="col-md-4">
    <div class="card pg">
      <div class="card-body d-flex align-items-start gap-3">
        <i class="bi bi-currency-rupee fs-3 text-primary"></i>
        <div>
          <div class="kicker mb-1">Affordable</div>
          <h6 class="mb-1">Transparent Pricing</h6>
          <p class="small text-muted mb-0">No surprises — compare amenities and prices side by side.</p>
        </div>
      </div>
    </div>
  </div>
  <div class="col-md-4">
    <div class="card pg">
      <div class="card-body d-flex align-items-start gap-3">
        <i class="bi bi-lightning-charge fs-3 text-primary"></i>
        <div>
          <div class="kicker mb-1">Fast</div>
          <h6 class="mb-1">Instant Booking</h6>
          <p class="small text-muted mb-0">Message hosts and confirm your stay in minutes.</p>
        </div>
      </div>
    </div>
  </div>
</div>

<hr>
<h3 class="mb-3">Latest listings</h3>
<div id="featured" class="row gy-3">
  <!-- Placeholder featured cards (replace with server data) -->
  <div class="col-md-4">
    <div class="card pg shadow-sm">
      <img src="/assets/images/placeholder.png" class="card-img-top" style="height:180px;object-fit:cover" alt="">
      <div class="card-body">
        <div class="d-flex justify-content-between align-items-start">
          <div>
            <h5 class="listing-title mb-1">Dipak Paul PG</h5>
            <div class="listing-meta">Kolkata • Unisex</div>
          </div>
          <div class="text-end">
            <div class="price">₹6,500</div>
            <div class="text-muted small">/month</div>
          </div>
        </div>
        <p class="mt-2 small text-muted">Comfortable rooms, WiFi, food available.</p>
      </div>
    </div>
  </div>
  <div class="col-md-4">
    <div class="card pg shadow-sm">
      <img src="/assets/images/placeholder.png" class="card-img-top" style="height:180px;object-fit:cover" alt="">
      <div class="card-body">
        <div class="d-flex justify-content-between align-items-start">
          <div>
            <h5 class="listing-title mb-1">Green Nest PG</h5>
            <div class="listing-meta">Bengaluru • Female</div>
          </div>
          <div class="text-end">
            <div class="price">₹8,000</div>
            <div class="text-muted small">/month</div>
          </div>
        </div>
        <p class="mt-2 small text-muted">Near tech parks, 24x7 security, homely food.</p>
      </div>
    </div>
  </div>
  <div class="col-md-4">
    <div class="card pg shadow-sm">
      <img src="/assets/images/placeholder.png" class="card-img-top" style="height:180px;object-fit:cover" alt="">
      <div class="card-body">
        <div class="d-flex justify-content-between align-items-start">
          <div>
            <h5 class="listing-title mb-1">City Comfort PG</h5>
            <div class="listing-meta">Mumbai • Male</div>
          </div>
          <div class="text-end">
            <div class="price">₹9,500</div>
            <div class="text-muted small">/month</div>
          </div>
        </div>
        <p class="mt-2 small text-muted">AC rooms, housekeeping, metro at 5 mins.</p>
      </div>
    </div>
  </div>
  <!-- duplicate as needed -->
</div>

<!-- Popular areas quick links -->
<div class="mt-5">
  <div class="kicker mb-2">Popular Areas</div>
  <div class="d-flex flex-wrap gap-2">
    <a class="btn btn-sm btn-outline-secondary" href="<?= htmlspecialchars(app_url('listings?city=Bengaluru')) ?>">Bengaluru</a>
    <a class="btn btn-sm btn-outline-secondary" href="<?= htmlspecialchars(app_url('listings?city=Mumbai')) ?>">Mumbai</a>
    <a class="btn btn-sm btn-outline-secondary" href="<?= htmlspecialchars(app_url('listings?city=Pune')) ?>">Pune</a>
    <a class="btn btn-sm btn-outline-secondary" href="<?= htmlspecialchars(app_url('listings?city=Kolkata')) ?>">Kolkata</a>
    <a class="btn btn-sm btn-outline-secondary" href="<?= htmlspecialchars(app_url('listings?city=Delhi')) ?>">Delhi</a>
    <a class="btn btn-sm btn-outline-secondary" href="<?= htmlspecialchars(app_url('listings?city=Hyderabad')) ?>">Hyderabad</a>
  </div>
</div>

<!-- How it works -->
<div class="mt-5">
  <div class="kicker mb-2">How it works</div>
  <div class="row g-3">
    <div class="col-md-4">
      <div class="card pg h-100">
        <div class="card-body">
          <div class="d-flex align-items-start gap-3">
            <span class="badge-soft">Step 1</span>
            <i class="bi bi-search fs-4 text-primary"></i>
          </div>
          <h6 class="mt-3">Search</h6>
          <p class="text-muted small mb-0">Filter by city, price and amenities to find the perfect PG for you.</p>
        </div>
      </div>
    </div>
    <div class="col-md-4">
      <div class="card pg h-100">
        <div class="card-body">
          <div class="d-flex align-items-start gap-3">
            <span class="badge-soft">Step 2</span>
            <i class="bi bi-chat-dots fs-4 text-primary"></i>
          </div>
          <h6 class="mt-3">Connect</h6>
          <p class="text-muted small mb-0">Message the host, clarify details and schedule a quick visit.</p>
        </div>
      </div>
    </div>
    <div class="col-md-4">
      <div class="card pg h-100">
        <div class="card-body">
          <div class="d-flex align-items-start gap-3">
            <span class="badge-soft">Step 3</span>
            <i class="bi bi-check2-circle fs-4 text-primary"></i>
          </div>
          <h6 class="mt-3">Book</h6>
          <p class="text-muted small mb-0">Reserve instantly with secure payment and get digital confirmation.</p>
        </div>
      </div>
    </div>
  </div>
  
  <!-- Trust badges -->
  <div class="d-flex flex-wrap align-items-center gap-3 mt-3">
    <div class="small text-muted">Trusted by students & professionals from</div>
    <img src="/assets/images/placeholder.png" alt="brand" style="height:24px; width:auto; opacity:.7">
    <img src="/assets/images/placeholder.png" alt="brand" style="height:24px; width:auto; opacity:.7">
    <img src="/assets/images/placeholder.png" alt="brand" style="height:24px; width:auto; opacity:.7">
  </div>
  
  <!-- App download strip -->
  <div class="mt-4 p-3 rounded-3" style="background: var(--accent);">
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-3">
      <div>
        <h6 class="mb-1">Get the Livonto app</h6>
        <div class="text-muted small">Manage bookings, chat with hosts, and get alerts.</div>
      </div>
      <div class="d-flex gap-2">
        <a href="#" class="btn btn-dark btn-sm">App Store</a>
        <a href="#" class="btn btn-dark btn-sm">Google Play</a>
      </div>
    </div>
  </div>
</div>

<!-- Testimonials -->
<div class="mt-5">
  <div class="kicker mb-2">Stories</div>
  <h3 class="mb-3">What our users say</h3>
  <div class="row g-3">
    <div class="col-md-4">
      <div class="card pg h-100">
        <div class="card-body">
          <p class="mb-2">“Booking my PG was super quick and transparent. Photos matched the place.”</p>
          <div class="small text-muted">Ananya, Pune</div>
        </div>
      </div>
    </div>
    <div class="col-md-4">
      <div class="card pg h-100">
        <div class="card-body">
          <p class="mb-2">“Loved the filters and verified badge. Helped me avoid brokers.”</p>
          <div class="small text-muted">Rahul, Bengaluru</div>
        </div>
      </div>
    </div>
    <div class="col-md-4">
      <div class="card pg h-100">
        <div class="card-body">
          <p class="mb-2">“Host response time was fast and move‑in was smooth.”</p>
          <div class="small text-muted">Sneha, Mumbai</div>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- FAQ -->
<div class="mt-5">
  <div class="kicker mb-2">FAQ</div>
  <div class="accordion" id="faq">
    <div class="accordion-item">
      <h2 class="accordion-header" id="q1">
        <button class="accordion-button" type="button" data-bs-toggle="collapse" data-bs-target="#a1" aria-expanded="true" aria-controls="a1">
          Is there any booking fee?
        </button>
      </h2>
      <div id="a1" class="accordion-collapse collapse show" aria-labelledby="q1" data-bs-parent="#faq">
        <div class="accordion-body small text-muted">No hidden charges. You only pay what you see on the listing during booking.</div>
      </div>
    </div>
    <div class="accordion-item">
      <h2 class="accordion-header" id="q2">
        <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#a2" aria-expanded="false" aria-controls="a2">
          Can I schedule a visit before booking?
        </button>
      </h2>
      <div id="a2" class="accordion-collapse collapse" aria-labelledby="q2" data-bs-parent="#faq">
        <div class="accordion-body small text-muted">Yes, use the message option on a listing to request a quick visit from the host.</div>
      </div>
    </div>
    <div class="accordion-item">
      <h2 class="accordion-header" id="q3">
        <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#a3" aria-expanded="false" aria-controls="a3">
          What documents are needed?
        </button>
      </h2>
      <div id="a3" class="accordion-collapse collapse" aria-labelledby="q3" data-bs-parent="#faq">
        <div class="accordion-body small text-muted">Basic KYC like ID proof may be requested by hosts at check-in as per city norms.</div>
      </div>
    </div>
  </div>
</div>

<!-- Call to action -->
<div class="mt-5">
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
</div>

<?php require __DIR__ . '/../app/includes/footer.php'; ?>
