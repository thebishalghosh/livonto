<?php
/**
 * Listings Page
 * Displays all active listings with pagination and filters
 */

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$pageTitle = "Browse Listings";
require __DIR__ . '/../app/config.php';
require __DIR__ . '/../app/functions.php';

// Add listings CSS - use same path pattern as header
$baseUrl = app_url('');
$cssBasePath = ($baseUrl === '' || $baseUrl === '/') ? '/public/assets/css/' : ($baseUrl . '/public/assets/css/');
if (substr($cssBasePath, 0, 1) !== '/') {
    $cssBasePath = '/' . ltrim($cssBasePath, '/');
}
$additionalCSS = '<link rel="stylesheet" href="' . htmlspecialchars($cssBasePath . 'listing.css') . '">';

require __DIR__ . '/../app/includes/header.php';

// Get filter parameters
$searchQuery = trim($_GET['q'] ?? '');
$city = trim($_GET['city'] ?? '');
$availableFor = $_GET['available_for'] ?? ''; // boys, girls, both
$genderAllowed = $_GET['gender_allowed'] ?? ''; // male, female, unisex
$foodAvailability = $_GET['food_availability'] ?? ''; // vegetarian, non-vegetarian, both, not available
$minPrice = (isset($_GET['min_price']) && $_GET['min_price'] !== '' && $_GET['min_price'] !== null)
    ? floatval($_GET['min_price'])
    : null;
$maxPrice = (isset($_GET['max_price']) && $_GET['max_price'] !== '' && $_GET['max_price'] !== null)
    ? floatval($_GET['max_price'])
    : null;
$sortBy = $_GET['sort'] ?? 'newest'; // newest, oldest, price_low, price_high, rating

// Pagination
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$perPage = 12; // Show 12 listings per page
$offset = ($page - 1) * $perPage;

$listings = [];
$totalListings = 0;
$totalPages = 0;
$hasFilters = !empty($searchQuery) || !empty($city) || !empty($availableFor) || !empty($genderAllowed) || !empty($foodAvailability) || $minPrice !== null || $maxPrice !== null;

try {
    $db = db();
    
    // Build WHERE clause
    $where = ['l.status = ?'];
    $params = ['active'];
    
    if (!empty($searchQuery)) {
        $where[] = '(l.title LIKE ? OR l.description LIKE ?)';
        $searchParam = "%{$searchQuery}%";
        $params[] = $searchParam;
        $params[] = $searchParam;
    }
    
    if (!empty($city)) {
        // Make city filter more flexible: match city, address, title, or description
        $cityTrimmed = trim($city);
        $cityParam = "%{$cityTrimmed}%";
        $where[] = '('
            . 'LOWER(TRIM(loc.city)) = LOWER(TRIM(?)) '
            . 'OR LOWER(loc.city) LIKE LOWER(?) '
            . 'OR LOWER(loc.complete_address) LIKE LOWER(?) '
            . 'OR LOWER(l.title) LIKE LOWER(?) '
            . 'OR LOWER(l.description) LIKE LOWER(?)'
            . ')';
        $params[] = $cityTrimmed;
        $params[] = $cityParam;
        $params[] = $cityParam;
        $params[] = $cityParam;
        $params[] = $cityParam;
    }
    
    if (!empty($availableFor) && in_array($availableFor, ['boys', 'girls', 'both'])) {
        $where[] = 'l.available_for = ?';
        $params[] = $availableFor;
    }
    
    if (!empty($genderAllowed) && in_array($genderAllowed, ['male', 'female', 'unisex'])) {
        $where[] = 'l.gender_allowed = ?';
        $params[] = $genderAllowed;
    }
    
    if (!empty($foodAvailability) && in_array($foodAvailability, ['vegetarian', 'non-vegetarian', 'both', 'not available'])) {
        $where[] = 'EXISTS (SELECT 1 FROM listing_additional_info add_info WHERE add_info.listing_id = l.id AND add_info.food_availability = ?)';
        $params[] = $foodAvailability;
    }
    
    // Price filter (based on room configurations)
    if ($minPrice !== null || $maxPrice !== null) {
        $priceConditions = [];
        $priceParams = [];
        
        if ($minPrice !== null) {
            $priceConditions[] = "EXISTS (SELECT 1 FROM room_configurations rc WHERE rc.listing_id = l.id AND rc.rent_per_month >= ?)";
            $priceParams[] = $minPrice;
        }
        
        if ($maxPrice !== null) {
            $priceConditions[] = "EXISTS (SELECT 1 FROM room_configurations rc WHERE rc.listing_id = l.id AND rc.rent_per_month <= ?)";
            $priceParams[] = $maxPrice;
        }
        
        if (!empty($priceConditions)) {
            $where[] = '(' . implode(' AND ', $priceConditions) . ')';
            $params = array_merge($params, $priceParams);
        }
    }
    
    $whereClause = 'WHERE ' . implode(' AND ', $where);
    
    // Get total count
    $totalListings = $db->fetchValue(
        "SELECT COUNT(DISTINCT l.id) 
         FROM listings l
         LEFT JOIN listing_locations loc ON l.id = loc.listing_id
         {$whereClause}",
        $params
    );
    
    $totalPages = ceil($totalListings / $perPage);
    
    // Build ORDER BY clause
    $orderBy = 'l.created_at DESC';
    switch ($sortBy) {
        case 'oldest':
            $orderBy = 'l.created_at ASC';
            break;
        case 'price_low':
            $orderBy = '(SELECT MIN(rent_per_month) FROM room_configurations WHERE listing_id = l.id) ASC';
            break;
        case 'price_high':
            $orderBy = '(SELECT MAX(rent_per_month) FROM room_configurations WHERE listing_id = l.id) DESC';
            break;
        case 'rating':
            $orderBy = '(SELECT AVG(rating) FROM reviews WHERE listing_id = l.id) DESC, l.created_at DESC';
            break;
        default:
            $orderBy = 'l.created_at DESC';
    }
    
    // Get listings with pagination
    $listings = $db->fetchAll(
        "SELECT l.id, l.title, l.description, l.cover_image, l.available_for, l.gender_allowed,
                loc.city, loc.pin_code,
                (SELECT MIN(rent_per_month) FROM room_configurations WHERE listing_id = l.id) as min_rent,
                (SELECT MAX(rent_per_month) FROM room_configurations WHERE listing_id = l.id) as max_rent,
                (SELECT AVG(rating) FROM reviews WHERE listing_id = l.id) as avg_rating,
                (SELECT COUNT(*) FROM reviews WHERE listing_id = l.id) as reviews_count
         FROM listings l
         LEFT JOIN listing_locations loc ON l.id = loc.listing_id
         {$whereClause}
         ORDER BY {$orderBy}
         LIMIT {$perPage} OFFSET {$offset}",
        $params
    );
    
    // Fetch images for each listing
    foreach ($listings as &$listing) {
        $listingImages = $db->fetchAll(
            "SELECT image_path, is_cover 
             FROM listing_images 
             WHERE listing_id = ? 
             ORDER BY is_cover DESC, image_order ASC 
             LIMIT 5",
            [$listing['id']]
        );
        
        $images = [];
        foreach ($listingImages as $img) {
            $imgPath = trim($img['image_path']);
            if (!empty($imgPath)) {
                if (strpos($imgPath, 'http') === 0 || strpos($imgPath, '//') === 0) {
                    $images[] = $imgPath;
                } else {
                    $images[] = app_url($imgPath);
                }
            }
        }
        
        // Fallback to cover_image if no images in listing_images table
        if (empty($images) && !empty($listing['cover_image'])) {
            $coverImagePath = $listing['cover_image'];
            if (strpos($coverImagePath, 'http') === 0 || strpos($coverImagePath, '//') === 0) {
                $images[] = $coverImagePath;
            } else {
                $images[] = app_url($coverImagePath);
            }
        }
        
        $listing['images'] = $images;
    }
    unset($listing);
    
} catch (Exception $e) {
    error_log("Error loading listings: " . $e->getMessage());
    $listings = [];
    $totalListings = 0;
    $totalPages = 0;
}
?>


<div class="listings-page">
    <div class="listings-header">
        <div class="container-xxl">
            <div class="listings-header-content">
                <div class="listings-header-text">
                    <div class="kicker">Explore</div>
                    <h1>Find Your Perfect PG</h1>
                    <p class="lead">Browse verified listings, compare amenities, and book with confidence</p>
                </div>
            </div>
        </div>
    </div>

    <div class="container-xxl">
        <!-- Filters -->
        <div class="filters-card">
            <form method="GET" action="<?= app_url('listings') ?>" id="filtersForm">
                <!-- First Row -->
                <div class="filter-row">
                    <div class="filter-group">
                        <label for="searchQuery">Search</label>
                        <input type="text" 
                               class="form-control" 
                               id="searchQuery" 
                               name="q" 
                               value="<?= htmlspecialchars($searchQuery) ?>" 
                               placeholder="Search by name or description...">
                    </div>
                    
                    <div class="filter-group">
                        <label for="availableFor">Available For</label>
                        <select class="form-select" id="availableFor" name="available_for">
                            <option value="">All</option>
                            <option value="boys" <?= $availableFor === 'boys' ? 'selected' : '' ?>>Boys</option>
                            <option value="girls" <?= $availableFor === 'girls' ? 'selected' : '' ?>>Girls</option>
                            <option value="both" <?= $availableFor === 'both' ? 'selected' : '' ?>>Both</option>
                        </select>
                    </div>
                    
                    <div class="filter-group">
                        <label for="genderAllowed">Gender Allowed</label>
                        <select class="form-select" id="genderAllowed" name="gender_allowed">
                            <option value="">All</option>
                            <option value="male" <?= $genderAllowed === 'male' ? 'selected' : '' ?>>Male</option>
                            <option value="female" <?= $genderAllowed === 'female' ? 'selected' : '' ?>>Female</option>
                            <option value="unisex" <?= $genderAllowed === 'unisex' ? 'selected' : '' ?>>Unisex</option>
                        </select>
                    </div>
                    
                    <div class="filter-group">
                        <label for="foodAvailability">Food Preference</label>
                        <select class="form-select" id="foodAvailability" name="food_availability">
                            <option value="">All</option>
                            <option value="vegetarian" <?= $foodAvailability === 'vegetarian' ? 'selected' : '' ?>>Vegetarian</option>
                            <option value="non-vegetarian" <?= $foodAvailability === 'non-vegetarian' ? 'selected' : '' ?>>Non-Vegetarian</option>
                            <option value="both" <?= $foodAvailability === 'both' ? 'selected' : '' ?>>Both</option>
                            <option value="not available" <?= $foodAvailability === 'not available' ? 'selected' : '' ?>>Not Available</option>
                        </select>
                    </div>
                    
                    <div class="filter-group">
                        <label for="minPrice">Min Price (₹)</label>
                        <input type="number" 
                               class="form-control" 
                               id="minPrice" 
                               name="min_price" 
                               value="<?= $minPrice !== null ? htmlspecialchars($minPrice) : '' ?>" 
                               placeholder="Min" 
                               min="0" 
                               step="100">
                    </div>
                    
                    <div class="filter-group">
                        <label for="maxPrice">Max Price (₹)</label>
                        <input type="number" 
                               class="form-control" 
                               id="maxPrice" 
                               name="max_price" 
                               value="<?= $maxPrice !== null ? htmlspecialchars($maxPrice) : '' ?>" 
                               placeholder="Max" 
                               min="0" 
                               step="100">
                    </div>
                </div>
                
                <!-- Second Row -->
                <div class="filter-row">
                    <div class="filter-group">
                        <label for="cityFilter">City</label>
                        <input type="text" 
                               class="form-control" 
                               id="cityFilter" 
                               name="city" 
                               value="<?= htmlspecialchars($city) ?>" 
                               placeholder="Enter city...">
                    </div>
                    
                    <div class="filter-group">
                        <button type="submit" class="btn btn-primary w-100">
                            <i class="bi bi-search me-2"></i>Search
                        </button>
                    </div>
                </div>
                
                <?php if ($hasFilters): ?>
                <div class="clear-filters">
                    <a href="<?= app_url('listings') ?>" class="btn btn-outline-secondary btn-sm">
                        <i class="bi bi-x-circle me-1"></i>Clear All Filters
                    </a>
                </div>
                <?php endif; ?>
            </form>
        </div>

        <!-- Results Header -->
        <div class="results-header">
            <div class="results-count">
                <strong><?= number_format($totalListings) ?></strong> listing<?= $totalListings !== 1 ? 's' : '' ?> found
                <?php if ($hasFilters): ?>
                    <span class="text-muted">(filtered)</span>
                <?php endif; ?>
            </div>
            
            <div class="sort-group">
                <label for="sortBy">Sort by:</label>
                <select class="form-select form-select-sm" id="sortBy" name="sort" onchange="updateSort(this.value)">
                    <option value="newest" <?= $sortBy === 'newest' ? 'selected' : '' ?>>Newest First</option>
                    <option value="oldest" <?= $sortBy === 'oldest' ? 'selected' : '' ?>>Oldest First</option>
                    <option value="price_low" <?= $sortBy === 'price_low' ? 'selected' : '' ?>>Price: Low to High</option>
                    <option value="price_high" <?= $sortBy === 'price_high' ? 'selected' : '' ?>>Price: High to Low</option>
                    <option value="rating" <?= $sortBy === 'rating' ? 'selected' : '' ?>>Highest Rated</option>
                </select>
            </div>
        </div>

        <!-- Listings Grid -->
        <?php if (!empty($listings)): ?>
        <div class="row g-4">
            <?php foreach ($listings as $listing): ?>
                <?php
                $listingUrl = app_url('listings/' . $listing['id']);
                $description = !empty($listing['description']) ? strip_tags($listing['description']) : '';
                $description = mb_substr($description, 0, 120);
                if (mb_strlen($listing['description'] ?? '') > 120) {
                    $description .= '...';
                }
                
                $priceText = '';
                if (!empty($listing['min_rent']) && !empty($listing['max_rent'])) {
                    if ($listing['min_rent'] == $listing['max_rent']) {
                        $priceText = '₹' . number_format($listing['min_rent']) . '/month';
                    } else {
                        $priceText = '₹' . number_format($listing['min_rent']) . ' - ₹' . number_format($listing['max_rent']) . '/month';
                    }
                } elseif (!empty($listing['min_rent'])) {
                    $priceText = 'Starting from ₹' . number_format($listing['min_rent']) . '/month';
                }
                
                $rating = !empty($listing['avg_rating']) ? round($listing['avg_rating'], 1) : 0;
                $reviewsCount = (int)($listing['reviews_count'] ?? 0);
                ?>
                
                <div class="col-md-4 col-lg-3">
                    <div class="card pg shadow-sm h-100">
                        <a href="<?= htmlspecialchars($listingUrl) ?>" class="text-decoration-none">
                            <div class="listing-carousel" data-listing-id="<?= $listing['id'] ?>">
                                <?php if (!empty($listing['images'])): ?>
                                    <?php foreach (array_slice($listing['images'], 0, 1) as $index => $image): ?>
                                        <div class="carousel-slide <?= $index === 0 ? 'active' : '' ?>" style="display: <?= $index === 0 ? 'block' : 'none' ?>;">
                                            <img src="<?= htmlspecialchars($image) ?>" 
                                                 alt="<?= htmlspecialchars($listing['title']) ?>" 
                                                 class="card-img-top listing-image"
                                                 style="height: 200px; object-fit: cover; width: 100%;"
                                                 onerror="this.src='<?= app_url('public/assets/images/livonto-image.jpg') ?>'">
                                        </div>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <img src="<?= app_url('public/assets/images/livonto-image.jpg') ?>" 
                                         alt="<?= htmlspecialchars($listing['title']) ?>" 
                                         class="card-img-top listing-image"
                                         style="height: 200px; object-fit: cover; width: 100%;">
                                <?php endif; ?>
                            </div>
                        </a>
                        <div class="card-body d-flex flex-column listing-card-body">
                            <h5 class="listing-title mb-2"><?= htmlspecialchars($listing['title']) ?></h5>
                            <p class="small text-muted mb-2"><?= htmlspecialchars($listing['city'] ?? 'N/A') ?></p>
                            <?php if (!empty($priceText)): ?>
                                <p class="text-primary fw-bold mb-2"><?= htmlspecialchars($priceText) ?></p>
                            <?php endif; ?>
                            <?php if ($rating > 0): ?>
                                <div class="mb-2">
                                    <span class="text-warning">
                                        <?php for ($i = 1; $i <= 5; $i++): ?>
                                            <i class="bi bi-star<?= $i <= round($rating) ? '-fill' : '' ?>"></i>
                                        <?php endfor; ?>
                                    </span>
                                    <span class="small text-muted ms-1"><?= $rating ?> (<?= $reviewsCount ?>)</span>
                                </div>
                            <?php endif; ?>
                            <p class="small text-muted mb-3 flex-grow-1"><?= htmlspecialchars($description) ?></p>
                            <div class="d-flex gap-2 mt-auto">
                                <?php if (isLoggedIn()): ?>
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
                                <?php else: ?>
                                    <button type="button"
                                            class="btn btn-outline-primary btn-sm flex-fill text-center"
                                            onclick="event.stopPropagation(); if(typeof showLoginModal === 'function') { showLoginModal('<?= htmlspecialchars(app_url('visit-book?id=' . $listing['id'])) ?>'); } else { window.location.href='<?= htmlspecialchars(app_url('visit-book?id=' . $listing['id'])) ?>'; }"
                                            style="border-color: var(--primary); color: var(--primary);">
                                        Book a Visit
                                    </button>
                                    <button type="button"
                                            class="btn btn-primary btn-sm flex-fill text-white text-center"
                                            onclick="event.stopPropagation(); if(typeof showLoginModal === 'function') { showLoginModal('<?= htmlspecialchars($listingUrl . '?action=book') ?>'); } else { window.location.href='<?= htmlspecialchars($listingUrl . '?action=book') ?>'; }">
                                        Book Now
                                    </button>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <!-- Pagination -->
        <?php if ($totalPages > 1): ?>
        <div class="pagination-wrapper">
            <nav aria-label="Listings pagination">
                <ul class="pagination">
                    <!-- Previous Button -->
                    <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">
                        <a class="page-link" href="<?= $page > 1 ? getPaginationUrl($page - 1) : '#' ?>">
                            <i class="bi bi-chevron-left"></i> Previous
                        </a>
                    </li>
                    
                    <!-- Page Numbers -->
                    <?php
                    $startPage = max(1, $page - 2);
                    $endPage = min($totalPages, $page + 2);
                    
                    if ($startPage > 1): ?>
                        <li class="page-item">
                            <a class="page-link" href="<?= getPaginationUrl(1) ?>">1</a>
                        </li>
                        <?php if ($startPage > 2): ?>
                            <li class="page-item disabled">
                                <span class="page-link">...</span>
                            </li>
                        <?php endif; ?>
                    <?php endif; ?>
                    
                    <?php for ($i = $startPage; $i <= $endPage; $i++): ?>
                        <li class="page-item <?= $i === $page ? 'active' : '' ?>">
                            <a class="page-link" href="<?= getPaginationUrl($i) ?>"><?= $i ?></a>
                        </li>
                    <?php endfor; ?>
                    
                    <?php if ($endPage < $totalPages): ?>
                        <?php if ($endPage < $totalPages - 1): ?>
                            <li class="page-item disabled">
                                <span class="page-link">...</span>
                            </li>
                        <?php endif; ?>
                        <li class="page-item">
                            <a class="page-link" href="<?= getPaginationUrl($totalPages) ?>"><?= $totalPages ?></a>
                        </li>
                    <?php endif; ?>
                    
                    <!-- Next Button -->
                    <li class="page-item <?= $page >= $totalPages ? 'disabled' : '' ?>">
                        <a class="page-link" href="<?= $page < $totalPages ? getPaginationUrl($page + 1) : '#' ?>">
                            Next <i class="bi bi-chevron-right"></i>
                        </a>
                    </li>
                </ul>
            </nav>
        </div>
        <?php endif; ?>
        
        <?php else: ?>
        <div class="no-results">
            <i class="bi bi-inbox"></i>
            <h4 class="mt-3 mb-2">No Listings Found</h4>
            <p class="text-muted mb-4">
                <?php if ($hasFilters): ?>
                    Try adjusting your filters or search criteria.
                <?php else: ?>
                    There are currently no active listings available.
                <?php endif; ?>
            </p>
            <?php if ($hasFilters): ?>
                <a href="<?= app_url('listings') ?>" class="btn btn-primary">
                    <i class="bi bi-arrow-left me-2"></i>View All Listings
                </a>
            <?php endif; ?>
        </div>
        <?php endif; ?>
    </div>
</div>

<?php
/**
 * Generate pagination URL with current filters
 */
function getPaginationUrl($pageNum) {
    $params = $_GET;
    $params['page'] = $pageNum;
    return app_url('listings') . '?' . http_build_query($params);
}
?>

<script>
function updateSort(sortValue) {
    const url = new URL(window.location.href);
    url.searchParams.set('sort', sortValue);
    url.searchParams.set('page', '1'); // Reset to first page when sorting changes
    window.location.href = url.toString();
}
</script>

<?php require __DIR__ . '/../app/includes/footer.php'; ?>

