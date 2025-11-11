<?php
$pageTitle = "Find your PG";
require __DIR__ . '/../app/config.php';
require __DIR__ . '/../app/functions.php';
require __DIR__ . '/../app/includes/header.php';
$baseUrl = app_url('');

// Check if there's a search query
$searchQuery = trim($_GET['q'] ?? $_GET['search'] ?? '');
$searchCity = trim($_GET['city'] ?? '');

// Fetch dynamic data
try {
    $db = db();
    
    // If there's a search query, get search results, otherwise get latest listings
    if (!empty($searchQuery) || !empty($searchCity)) {
        $query = $searchQuery ?: $searchCity;
        $city = $searchCity ?: $searchQuery;
        
        // Build WHERE clause for search
        $where = ['l.status = ?'];
        $params = ['active'];
        
        if (!empty($city)) {
            $where[] = '(LOWER(TRIM(loc.city)) = LOWER(TRIM(?)) OR LOWER(loc.city) LIKE LOWER(?) OR loc.city LIKE ?)';
            $cityTrimmed = trim($city);
            $cityParam = "%{$cityTrimmed}%";
            $params[] = $cityTrimmed;
            $params[] = $cityParam;
            $params[] = $cityParam;
        }
        
        if (!empty($query)) {
            $where[] = '(l.title LIKE ? OR l.description LIKE ?)';
            $queryParam = "%{$query}%";
            $params[] = $queryParam;
            $params[] = $queryParam;
        }
        
        $whereClause = 'WHERE ' . implode(' AND ', $where);
        
        // Get search results
        $latestListings = $db->fetchAll(
            "SELECT l.id, l.title, l.description, l.cover_image, l.available_for, l.gender_allowed,
                    loc.city, loc.pin_code,
                    (SELECT MIN(rent_per_month) FROM room_configurations WHERE listing_id = l.id) as min_rent,
                    (SELECT MAX(rent_per_month) FROM room_configurations WHERE listing_id = l.id) as max_rent,
                    (SELECT AVG(rating) FROM reviews WHERE listing_id = l.id) as avg_rating,
                    (SELECT COUNT(*) FROM reviews WHERE listing_id = l.id) as reviews_count
             FROM listings l
             LEFT JOIN listing_locations loc ON l.id = loc.listing_id
             {$whereClause}
             ORDER BY l.created_at DESC
             LIMIT 50",
            $params
        );
    } else {
        // Get latest active listings (limit 9)
        $latestListings = $db->fetchAll(
            "SELECT l.id, l.title, l.description, l.cover_image, l.available_for, l.gender_allowed,
                    loc.city, loc.pin_code,
                    (SELECT MIN(rent_per_month) FROM room_configurations WHERE listing_id = l.id) as min_rent,
                    (SELECT MAX(rent_per_month) FROM room_configurations WHERE listing_id = l.id) as max_rent,
                    (SELECT AVG(rating) FROM reviews WHERE listing_id = l.id) as avg_rating,
                    (SELECT COUNT(*) FROM reviews WHERE listing_id = l.id) as reviews_count
             FROM listings l
             LEFT JOIN listing_locations loc ON l.id = loc.listing_id
             WHERE l.status = 'active'
             ORDER BY l.created_at DESC
             LIMIT 9"
        );
    }
    
    // Fetch all images for each listing
    foreach ($latestListings as &$listing) {
        $listingImages = $db->fetchAll(
            "SELECT image_path, image_order, is_cover 
             FROM listing_images 
             WHERE listing_id = ? 
             ORDER BY is_cover DESC, image_order ASC",
            [$listing['id']]
        );
        
        // Build image URLs
        $images = [];
        foreach ($listingImages as $img) {
            $imagePath = trim($img['image_path']);
            if (empty($imagePath)) continue;
            
            if (strpos($imagePath, 'http') === 0 || strpos($imagePath, '//') === 0) {
                $images[] = $imagePath;
            } else {
                // Use app_url() for consistent path handling
                $images[] = app_url($imagePath);
            }
        }
        
        // Fallback to cover_image if no images in listing_images table
        if (empty($images) && !empty($listing['cover_image'])) {
            $imagePath = $listing['cover_image'];
            if (strpos($imagePath, 'http') !== 0 && strpos($imagePath, '//') !== 0) {
                // Use app_url() for consistent path handling
                $images[] = app_url($imagePath);
            } else {
                $images[] = $imagePath;
            }
        }
        
        // Fallback to placeholder if still no images
        if (empty($images)) {
            $images[] = 'data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iNDAwIiBoZWlnaHQ9IjMwMCIgeG1sbnM9Imh0dHA6Ly93d3cudzMub3JnLzIwMDAvc3ZnIj48cmVjdCB3aWR0aD0iMTAwJSIgaGVpZ2h0PSIxMDAlIiBmaWxsPSIjZGRkIi8+PHRleHQgeD0iNTAlIiB5PSI1MCUiIGZvbnQtZmFtaWx5PSJBcmlhbCwgc2Fucy1zZXJpZiIgZm9udC1zaXplPSIxOCIgZmlsbD0iIzk5OSIgdGV4dC1hbmNob3I9Im1pZGRsZSIgZHk9Ii4zZW0iPk5vIEltYWdlPC90ZXh0Pjwvc3ZnPg==';
        }
        
        $listing['images'] = $images;
    }
    unset($listing); // Break reference
    
    // Get popular cities (cities with most listings)
    $popularCities = $db->fetchAll(
        "SELECT loc.city, COUNT(*) as listing_count
         FROM listing_locations loc
         INNER JOIN listings l ON loc.listing_id = l.id
         WHERE l.status = 'active' 
         AND loc.city IS NOT NULL 
         AND loc.city != ''
         GROUP BY loc.city
         ORDER BY listing_count DESC
         LIMIT 10"
    );
    
    // Get statistics
    $stats = [
        'total_listings' => (int)$db->fetchValue("SELECT COUNT(*) FROM listings WHERE status = 'active'") ?: 0,
        'total_cities' => (int)$db->fetchValue("SELECT COUNT(DISTINCT city) FROM listing_locations WHERE city IS NOT NULL AND city != ''") ?: 0,
        'total_bookings' => (int)$db->fetchValue("SELECT COUNT(*) FROM bookings WHERE status = 'confirmed'") ?: 0
    ];
    
    // Get testimonials from reviews (if available)
    $testimonials = $db->fetchAll(
        "SELECT r.comment, r.rating, u.name as user_name, l.title as listing_title, loc.city
         FROM reviews r
         INNER JOIN users u ON r.user_id = u.id
         INNER JOIN listings l ON r.listing_id = l.id
         LEFT JOIN listing_locations loc ON l.id = loc.listing_id
         WHERE r.comment IS NOT NULL AND r.comment != ''
         ORDER BY r.created_at DESC
         LIMIT 3"
    );
    
} catch (Exception $e) {
    error_log("Error loading homepage data: " . $e->getMessage());
    // Fallback to empty data
    $latestListings = [];
    $popularCities = [
        ['city' => 'Bengaluru', 'listing_count' => 0],
        ['city' => 'Mumbai', 'listing_count' => 0],
        ['city' => 'Pune', 'listing_count' => 0],
        ['city' => 'Kolkata', 'listing_count' => 0],
        ['city' => 'Delhi', 'listing_count' => 0],
        ['city' => 'Hyderabad', 'listing_count' => 0]
    ];
    $stats = ['total_listings' => 0, 'total_cities' => 0, 'total_bookings' => 0];
    $testimonials = [];
}
?>
<div class="row g-4 align-items-center">
  <div class="col-md-7">
    <h1 class="display-5">Discover PGs near you</h1>
    <p class="text-muted">Find trusted PGs, compare amenities, and book with confidence.</p>

    <form id="searchForm" class="row g-2 search-bar">
      <div class="col-md-10 position-relative">
        <input id="searchInput" class="form-control search-input" placeholder="Search city or location (e.g. Mumbai, near IIT, Kolkata)" autocomplete="off">
        <div id="searchSuggestions" class="search-suggestions" style="display: none;"></div>
      </div>
      <div class="col-md-2 d-grid">
        <button class="btn btn-primary">Search</button>
      </div>
    </form>
  </div>

  <div class="col-md-5 text-center">
    <div id="mapPlaceholder" style="display: block;">
      <?php
      $imagePath = ($baseUrl === '' || $baseUrl === '/') 
          ? '/public/assets/images/livonto-image.jpg' 
          : ($baseUrl . '/public/assets/images/livonto-image.jpg');
      ?>
      <img src="<?= htmlspecialchars($imagePath) ?>" alt="Livonto" class="img-fluid" style="max-height:260px; border-radius: 12px; box-shadow: 0 4px 12px rgba(0,0,0,0.1);">
    </div>
  </div>
</div>

<!-- Map Section (hidden by default, shown when location is searched) -->
<div id="mapSection" class="mt-4" style="display: none;">
  <div class="card pg">
    <div class="card-header d-flex justify-content-between align-items-center">
      <h5 class="mb-0" style="color: var(--primary-700);">
        <i class="bi bi-map me-2"></i>Listings on Map
      </h5>
      <button class="btn btn-sm btn-outline-secondary" onclick="document.getElementById('mapSection').style.display='none';">
        <i class="bi bi-x"></i> Close
      </button>
    </div>
    <div class="card-body p-0 position-relative">
      <div id="mapLoading" class="position-absolute top-50 start-50 translate-middle" style="z-index: 1000; display: none; background: rgba(255,255,255,0.9); padding: 20px; border-radius: 8px;">
        <div class="text-center">
          <div class="spinner-border text-primary" role="status">
            <span class="visually-hidden">Loading...</span>
          </div>
          <p class="mt-2 mb-0 small text-muted">Loading map...</p>
        </div>
      </div>
      <div id="listingsMap" style="height: 500px; width: 100%; border-radius: 0 0 12px 12px;"></div>
    </div>
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
<h3 class="mb-3" id="listingsTitle">
    <?php if (!empty($searchQuery) || !empty($searchCity)): ?>
        Search Results
        <?php if (!empty($searchQuery) || !empty($searchCity)): ?>
            <small class="text-muted">for "<?= htmlspecialchars($searchQuery ?: $searchCity) ?>"</small>
        <?php endif; ?>
    <?php else: ?>
        Latest listings
    <?php endif; ?>
</h3>
<div id="featured" class="row g-4">
  <?php if (empty($latestListings)): ?>
    <div class="col-12">
      <div class="alert alert-info">
        <p class="mb-0">No listings available at the moment. Check back soon!</p>
      </div>
    </div>
  <?php else: ?>
    <?php foreach ($latestListings as $listing): ?>
      <?php 
      // Build listing URL
      $listingUrl = app_url('listings/' . $listing['id']);
      
      // Format price
      $priceText = '';
      if ($listing['min_rent']) {
          if ($listing['min_rent'] == $listing['max_rent']) {
              $priceText = '₹' . number_format($listing['min_rent']);
          } else {
              $priceText = '₹' . number_format($listing['min_rent']) . ' - ₹' . number_format($listing['max_rent']);
          }
      }
      
      // Format description (truncate if too long)
      $description = !empty($listing['description']) ? $listing['description'] : 'Comfortable PG accommodation.';
      $description = mb_substr($description, 0, 100);
      if (mb_strlen($listing['description'] ?? '') > 100) {
          $description .= '...';
      }
      
      // Format location
      $location = '';
      if (!empty($listing['city'])) {
          $location = $listing['city'];
          if (!empty($listing['pin_code'])) {
              $location .= ' • ' . $listing['pin_code'];
          }
      }
      if (empty($location)) {
          $location = 'Location not specified';
      }
      
      // Format gender/available for
      $genderInfo = '';
      if (!empty($listing['available_for']) && $listing['available_for'] !== 'both') {
          $genderInfo = ucfirst($listing['available_for']);
      } elseif (!empty($listing['gender_allowed'])) {
          $genderInfo = ucfirst($listing['gender_allowed']);
      }
      if ($genderInfo) {
          $location .= ' • ' . $genderInfo;
      }
      ?>
      <div class="col-md-4">
        <div class="card pg shadow-sm h-100">
          <!-- Image Carousel -->
          <a href="<?= htmlspecialchars($listingUrl) ?>" class="text-decoration-none">
            <div class="listing-carousel position-relative" data-listing-id="<?= $listing['id'] ?>">
              <div class="carousel-container">
                <?php foreach ($listing['images'] as $index => $imgUrl): ?>
                  <div class="carousel-slide <?= $index === 0 ? 'active' : '' ?>" 
                       style="display: <?= $index === 0 ? 'block' : 'none' ?>; position: absolute; top: 0; left: 0; width: 100%; height: 100%;">
                    <img src="<?= htmlspecialchars($imgUrl) ?>" 
                         class="w-100 h-100" 
                         style="object-fit: cover;"
                         alt="<?= htmlspecialchars($listing['title']) ?> - Image <?= $index + 1 ?>"
                         onerror="this.src='data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iNDAwIiBoZWlnaHQ9IjMwMCIgeG1sbnM9Imh0dHA6Ly93d3cudzMub3JnLzIwMDAvc3ZnIj48cmVjdCB3aWR0aD0iMTAwJSIgaGVpZ2h0PSIxMDAlIiBmaWxsPSIjZGRkIi8+PHRleHQgeD0iNTAlIiB5PSI1MCUiIGZvbnQtZmFtaWx5PSJBcmlhbCwgc2Fucy1zZXJpZiIgZm9udC1zaXplPSIxOCIgZmlsbD0iIzk5OSIgdGV4dC1hbmNob3I9Im1pZGRsZSIgZHk9Ii4zZW0iPk5vIEltYWdlPC90ZXh0Pjwvc3ZnPg=='">
                  </div>
                <?php endforeach; ?>
              </div>
              
              <!-- Navigation Arrows -->
              <?php if (count($listing['images']) > 1): ?>
                <button class="carousel-btn carousel-prev" 
                        onclick="event.preventDefault(); event.stopPropagation(); navigateCarousel(<?= $listing['id'] ?>, -1)"
                        aria-label="Previous image">
                  <i class="bi bi-chevron-left"></i>
                </button>
                <button class="carousel-btn carousel-next" 
                        onclick="event.preventDefault(); event.stopPropagation(); navigateCarousel(<?= $listing['id'] ?>, 1)"
                        aria-label="Next image">
                  <i class="bi bi-chevron-right"></i>
                </button>
                
                <!-- Image Count Badge -->
                <div class="carousel-badge">
                  <?= count($listing['images']) ?> Photo<?= count($listing['images']) !== 1 ? 's' : '' ?>
                </div>
              <?php endif; ?>
            </div>
          </a>
          <div class="card-body d-flex flex-column listing-card-body">
            <h5 class="listing-title mb-2"><?= htmlspecialchars($listing['title']) ?></h5>
            <p class="small text-muted mb-3 flex-grow-1"><?= htmlspecialchars($description) ?></p>
            <div class="d-flex gap-2 mt-auto">
              <a href="<?= htmlspecialchars(app_url('visit-book?id=' . $listing['id'])) ?>" 
                 class="btn btn-outline-primary btn-sm flex-fill text-center"
                 onclick="event.stopPropagation();"
                 style="border-color: var(--primary); color: var(--primary);">
                Book a Visit
              </a>
              <a href="<?= htmlspecialchars($listingUrl . '?action=book') ?>" 
                 class="btn btn-primary btn-sm flex-fill text-white text-center"
                 onclick="event.stopPropagation();">
                Book Now
              </a>
            </div>
          </div>
        </div>
      </div>
    <?php endforeach; ?>
  <?php endif; ?>
</div>

<?php if (!empty($latestListings) && count($latestListings) >= 9): ?>
  <div class="text-center mt-4">
    <a href="<?= htmlspecialchars(app_url('listings')) ?>" class="btn btn-outline-primary">View All Listings</a>
  </div>
<?php endif; ?>

<!-- Popular areas quick links -->
<div class="mt-5">
  <div class="kicker mb-2">Popular Areas</div>
  <div class="d-flex flex-wrap gap-2">
    <?php if (!empty($popularCities)): ?>
      <?php foreach ($popularCities as $cityData): ?>
        <a class="btn btn-sm btn-outline-secondary" 
           href="<?= htmlspecialchars(app_url('listings?city=' . urlencode($cityData['city']))) ?>">
          <?= htmlspecialchars($cityData['city']) ?>
          <?php if ($cityData['listing_count'] > 0): ?>
            <span class="badge bg-secondary ms-1"><?= $cityData['listing_count'] ?></span>
          <?php endif; ?>
        </a>
      <?php endforeach; ?>
    <?php else: ?>
      <!-- Fallback cities if no data -->
      <a class="btn btn-sm btn-outline-secondary" href="<?= htmlspecialchars(app_url('listings?city=Bengaluru')) ?>">Bengaluru</a>
      <a class="btn btn-sm btn-outline-secondary" href="<?= htmlspecialchars(app_url('listings?city=Mumbai')) ?>">Mumbai</a>
      <a class="btn btn-sm btn-outline-secondary" href="<?= htmlspecialchars(app_url('listings?city=Pune')) ?>">Pune</a>
      <a class="btn btn-sm btn-outline-secondary" href="<?= htmlspecialchars(app_url('listings?city=Kolkata')) ?>">Kolkata</a>
      <a class="btn btn-sm btn-outline-secondary" href="<?= htmlspecialchars(app_url('listings?city=Delhi')) ?>">Delhi</a>
      <a class="btn btn-sm btn-outline-secondary" href="<?= htmlspecialchars(app_url('listings?city=Hyderabad')) ?>">Hyderabad</a>
    <?php endif; ?>
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
    <!-- Brand logos can be added here when available -->
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
<?php if (!empty($testimonials)): ?>
<div class="mt-5">
  <div class="kicker mb-2">Stories</div>
  <h3 class="mb-3">What our users say</h3>
  <div class="row g-3">
    <?php foreach ($testimonials as $testimonial): ?>
      <div class="col-md-4">
        <div class="card pg h-100">
          <div class="card-body">
            <?php if ($testimonial['rating']): ?>
              <div class="mb-2">
                <span class="text-warning">
                  <?php for ($i = 1; $i <= 5; $i++): ?>
                    <i class="bi bi-star<?= $i <= $testimonial['rating'] ? '-fill' : '' ?>"></i>
                  <?php endfor; ?>
                </span>
              </div>
            <?php endif; ?>
            <p class="mb-2">"<?= htmlspecialchars($testimonial['comment']) ?>"</p>
            <div class="small text-muted">
              <?= htmlspecialchars($testimonial['user_name']) ?>
              <?php if (!empty($testimonial['city'])): ?>
                , <?= htmlspecialchars($testimonial['city']) ?>
              <?php endif; ?>
            </div>
          </div>
        </div>
      </div>
    <?php endforeach; ?>
  </div>
</div>
<?php else: ?>
<!-- Fallback testimonials if no reviews -->
<div class="mt-5">
  <div class="kicker mb-2">Stories</div>
  <h3 class="mb-3">What our users say</h3>
  <div class="row g-3">
    <div class="col-md-4">
      <div class="card pg h-100">
        <div class="card-body">
          <p class="mb-2">"Booking my PG was super quick and transparent. Photos matched the place."</p>
          <div class="small text-muted">Ananya, Pune</div>
        </div>
      </div>
    </div>
    <div class="col-md-4">
      <div class="card pg h-100">
        <div class="card-body">
          <p class="mb-2">"Loved the filters and verified badge. Helped me avoid brokers."</p>
          <div class="small text-muted">Rahul, Bengaluru</div>
        </div>
      </div>
    </div>
    <div class="col-md-4">
      <div class="card pg h-100">
        <div class="card-body">
          <p class="mb-2">"Host response time was fast and move‑in was smooth."</p>
          <div class="small text-muted">Sneha, Mumbai</div>
        </div>
      </div>
    </div>
  </div>
</div>
<?php endif; ?>

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
        <h5 class="mb-1 text-white">Get listed on <?= htmlspecialchars(function_exists('getSetting') ? getSetting('site_name', 'Livonto') : 'Livonto') ?></h5>
        <div class="small" style="color: rgba(255,255,255,0.9)">
          PG Booking Enquiry: <?= htmlspecialchars(function_exists('getSetting') ? getSetting('booking_enquiry_phone', '6293010501 | 7047133182 | 9831068248') : '6293010501 | 7047133182 | 9831068248') ?> 
          • Timings: <?= htmlspecialchars(function_exists('getSetting') ? getSetting('booking_timings', '10:00 AM to 8:00 PM') : '10:00 AM to 8:00 PM') ?>
        </div>
      </div>
      <a class="btn btn-light" href="<?= htmlspecialchars(app_url('contact')) ?>">Contact Us</a>
    </div>
  </div>
</div>

<?php require __DIR__ . '/../app/includes/footer.php'; ?>
