// main.js - search + small helpers
/**
 * Navigate carousel for a listing
 */
function navigateCarousel(listingId, direction) {
  const carousel = document.querySelector(`.listing-carousel[data-listing-id="${listingId}"]`);
  if (!carousel) return;
  
  const slides = carousel.querySelectorAll('.carousel-slide');
  if (slides.length <= 1) return;
  
  let currentIndex = -1;
  slides.forEach((slide, index) => {
    if (slide.classList.contains('active')) {
      currentIndex = index;
    }
  });
  
  if (currentIndex === -1) return;
  
  // Hide current slide
  slides[currentIndex].classList.remove('active');
  slides[currentIndex].style.display = 'none';
  
  // Calculate new index
  let newIndex = currentIndex + direction;
  if (newIndex < 0) {
    newIndex = slides.length - 1;
  } else if (newIndex >= slides.length) {
    newIndex = 0;
  }
  
  // Show new slide
  slides[newIndex].classList.add('active');
  slides[newIndex].style.display = 'block';
}

// Make function globally available
window.navigateCarousel = navigateCarousel;

/**
 * Initialize horizontal card carousel on homepage
 */
function initTopRatedCarousel() {
  const wrappers = document.querySelectorAll('[data-card-carousel]');
  if (!wrappers.length) return;

  wrappers.forEach((wrapper) => {
    const scroller = wrapper.querySelector('.top-rated-carousel-scroll');
    if (!scroller) return;

    const prevBtn = wrapper.querySelector('[data-carousel-prev]');
    const nextBtn = wrapper.querySelector('[data-carousel-next]');
    const slides = scroller.querySelectorAll('.top-rated-card');

    if (!slides.length) {
      if (prevBtn) prevBtn.classList.add('d-none');
      if (nextBtn) nextBtn.classList.add('d-none');
      return;
    }

    const getScrollAmount = () => Math.max(scroller.clientWidth * 0.9, slides[0].clientWidth || 0);

    const updateNavState = () => {
      const maxScroll = Math.max(0, scroller.scrollWidth - scroller.clientWidth - 4);
      const atStart = scroller.scrollLeft <= 4;
      const atEnd = scroller.scrollLeft >= maxScroll;

      if (prevBtn) {
        prevBtn.disabled = atStart;
        prevBtn.classList.toggle('d-none', maxScroll <= 0);
      }
      if (nextBtn) {
        nextBtn.disabled = atEnd;
        nextBtn.classList.toggle('d-none', maxScroll <= 0);
      }
    };

    const scrollByAmount = (direction) => {
      scroller.scrollBy({
        left: direction * getScrollAmount(),
        behavior: 'smooth'
      });
    };

    if (prevBtn) {
      prevBtn.addEventListener('click', () => scrollByAmount(-1));
    }
    if (nextBtn) {
      nextBtn.addEventListener('click', () => scrollByAmount(1));
    }

    scroller.addEventListener('scroll', () => {
      window.requestAnimationFrame(updateNavState);
    }, { passive: true });

    let resizeTimer;
    window.addEventListener('resize', () => {
      clearTimeout(resizeTimer);
      resizeTimer = setTimeout(updateNavState, 150);
    });

    updateNavState();
  });
}

document.addEventListener('DOMContentLoaded', function() {
  initTopRatedCarousel();
  const searchForm = document.getElementById('searchForm');
  if (searchForm) {
    searchForm.addEventListener('submit', async function(e){
      e.preventDefault();
      const searchInput = document.getElementById('searchInput');
      const searchValue = (searchInput || {value:''}).value.trim();
      
      if (!searchValue) {
        return; // Don't do anything if search is empty
      }
      
      // First, geocode the search term to get its coordinates
      // This ensures the map centers on the searched location
      let searchLat = null;
      let searchLng = null;
      
      try {
        // Try to get coordinates for the search term
        let cityCoords = getDefaultCityCoords(searchValue);
        if (!cityCoords) {
          cityCoords = await geocodeCity(searchValue);
        }
        
        if (cityCoords) {
          searchLat = cityCoords.lat;
          searchLng = cityCoords.lng;
        }
      } catch (error) {
        // Geocoding failed, will use search term as text query
      }
      
      // Try to get user location if available (for distance calculation)
      let userLat = null;
      let userLng = null;
      
      try {
        const location = await getUserLocation();
        userLat = location.lat;
        userLng = location.lng;
      } catch (error) {
        // User location not available, use search coordinates only
      }
      
      // Use geocoded search coordinates if available, otherwise use user location
      const mapLat = searchLat || userLat;
      const mapLng = searchLng || userLng;
      
      // Load listings on map - pass geocoded coordinates to find nearby listings
      await loadListingsOnMap(searchValue, searchValue, mapLat, mapLng);
      
      // Load search results in listings section (right column)
      await loadSearchResults(searchValue, searchValue);
      
      // Scroll to map section
      const mapSection = document.getElementById('mapSection');
      if (mapSection) {
        mapSection.scrollIntoView({ behavior: 'smooth', block: 'start' });
      }
    });
  }
});

/**
 * Load search results and display in listings section
 */
async function loadSearchResults(city = '', query = '') {
  const featuredSection = document.getElementById('featured');
  const sectionTitle = document.getElementById('listingsTitle');
  
  if (!featuredSection) {
    return;
  }
  
  // Show loading state
  featuredSection.innerHTML = '<div class="col-12"><div class="text-center py-4"><div class="spinner-border text-primary" role="status"><span class="visually-hidden">Loading...</span></div></div></div>';
  
  try {
    const params = new URLSearchParams();
    if (city) {
      params.append('city', city);
    }
    if (query) {
      params.append('q', query);
    }
    
    const response = await fetch(`${baseUrl}/app/listings_search_api.php?${params.toString()}`);
    
    if (!response.ok) {
      const errorText = await response.text();
      throw new Error(`HTTP error! status: ${response.status}`);
    }
    
    const responseText = await response.text();
    let data;
    try {
      data = JSON.parse(responseText);
    } catch (parseError) {
      throw new Error('Invalid JSON response from server');
    }
    
    if (data && (data.status === 'ok' || data.status === 'success')) {
      const listings = (data.data && data.data.listings) ? data.data.listings : (data.listings || []);
      
      // Update section title
      if (sectionTitle) {
        const searchTerm = city || query;
        sectionTitle.innerHTML = `
          Search Results
          <small class="text-muted">for "${escapeHtml(searchTerm)}"</small>
        `;
      }
      
      // Ensure listings are shown in full-width section (map is separate above)
      const defaultListingsSection = document.getElementById('defaultListingsSection');
      const defaultTitle = document.getElementById('listingsTitleDefault');
      const defaultFeatured = document.getElementById('featuredDefault');
      
      if (defaultListingsSection) {
        defaultListingsSection.style.display = 'block';
        // Update the default section's title
        if (defaultTitle && sectionTitle) {
          defaultTitle.innerHTML = sectionTitle.innerHTML;
        }
        // Use default featured section for full-width display
        if (defaultFeatured) {
          featuredSection = defaultFeatured;
        }
      }
      
      // Display listings
      if (listings.length === 0) {
        featuredSection.innerHTML = `
          <div class="col-12">
            <div class="alert alert-info">
              <p class="mb-0">No listings found for "${escapeHtml(city || query)}". Showing all available listings.</p>
            </div>
          </div>
        `;
      } else {
        featuredSection.innerHTML = listings.map(listing => {
          const imageUrl = listing.cover_image 
            ? (listing.cover_image.startsWith('http') ? listing.cover_image : `${baseUrl}/${listing.cover_image}`)
            : 'data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iNDAwIiBoZWlnaHQ9IjMwMCIgeG1sbnM9Imh0dHA6Ly93d3cudzMub3JnLzIwMDAvc3ZnIj48cmVjdCB3aWR0aD0iMTAwJSIgaGVpZ2h0PSIxMDAlIiBmaWxsPSIjZGRkIi8+PHRleHQgeD0iNTAlIiB5PSI1MCUiIGZvbnQtZmFtaWx5PSJBcmlhbCwgc2Fucy1zZXJpZiIgZm9udC1zaXplPSIxOCIgZmlsbD0iIzk5OSIgdGV4dC1hbmNob3I9Im1pZGRsZSIgZHk9Ii4zZW0iPk5vIEltYWdlPC90ZXh0Pjwvc3ZnPg==';
          
          const priceText = listing.min_rent 
            ? (listing.min_rent === listing.max_rent 
                ? `₹${parseInt(listing.min_rent).toLocaleString()}` 
                : `₹${parseInt(listing.min_rent).toLocaleString()} - ₹${parseInt(listing.max_rent).toLocaleString()}`)
            : 'Price on request';
          
          const location = listing.city 
            ? (listing.pin_code ? `${listing.city} • ${listing.pin_code}` : listing.city)
            : 'Location not specified';
          
          const description = listing.description 
            ? (listing.description.length > 100 ? listing.description.substring(0, 100) + '...' : listing.description)
            : 'Comfortable PG accommodation.';
          
          const ratingHtml = listing.avg_rating 
            ? `<div class="d-flex align-items-center gap-1">
                 <i class="bi bi-star-fill text-warning"></i>
                 <span>${parseFloat(listing.avg_rating).toFixed(1)}</span>
                 ${listing.reviews_count > 0 ? `<span class="text-muted">(${listing.reviews_count})</span>` : ''}
               </div>`
            : '';
          
          // Build carousel HTML
          const images = listing.images || [imageUrl];
          const carouselId = `carousel-${listing.id}`;
          let carouselHtml = '';
          
          if (images.length > 0) {
            carouselHtml = `
              <div class="listing-carousel position-relative" data-listing-id="${listing.id}">
                <div class="carousel-container">
                  ${images.map((img, idx) => `
                    <div class="carousel-slide ${idx === 0 ? 'active' : ''}" 
                         style="display: ${idx === 0 ? 'block' : 'none'}; position: absolute; top: 0; left: 0; width: 100%; height: 100%;">
                      <img src="${escapeHtml(img)}" 
                           class="w-100 h-100" 
                           style="object-fit: cover;"
                           alt="${escapeHtml(listing.title)} - Image ${idx + 1}"
                           onerror="this.src='data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iNDAwIiBoZWlnaHQ9IjMwMCIgeG1sbnM9Imh0dHA6Ly93d3cudzMub3JnLzIwMDAvc3ZnIj48cmVjdCB3aWR0aD0iMTAwJSIgaGVpZ2h0PSIxMDAlIiBmaWxsPSIjZGRkIi8+PHRleHQgeD0iNTAlIiB5PSI1MCUiIGZvbnQtZmFtaWx5PSJBcmlhbCwgc2Fucy1zZXJpZiIgZm9udC1zaXplPSIxOCIgZmlsbD0iIzk5OSIgdGV4dC1hbmNob3I9Im1pZGRsZSIgZHk9Ii4zZW0iPk5vIEltYWdlPC90ZXh0Pjwvc3ZnPg=='">
                    </div>
                  `).join('')}
                </div>
                ${images.length > 1 ? `
                  <button class="carousel-btn carousel-prev" 
                          onclick="event.preventDefault(); event.stopPropagation(); navigateCarousel(${listing.id}, -1)"
                          aria-label="Previous image">
                    <i class="bi bi-chevron-left"></i>
                  </button>
                  <button class="carousel-btn carousel-next" 
                          onclick="event.preventDefault(); event.stopPropagation(); navigateCarousel(${listing.id}, 1)"
                          aria-label="Next image">
                    <i class="bi bi-chevron-right"></i>
                  </button>
                  <div class="carousel-badge">
                    ${images.length} Photo${images.length !== 1 ? 's' : ''}
                  </div>
                ` : ''}
              </div>
            `;
          }
          
          // Always use col-md-4 for listings (3 per row in full-width view)
          const colClass = 'col-md-4';
          
          return `
            <div class="${colClass}">
              <div class="card pg shadow-sm h-100">
                <a href="${baseUrl}/listings/${listing.id}" class="text-decoration-none">
                  ${carouselHtml}
                </a>
                <div class="card-body d-flex flex-column listing-card-body">
                  <h5 class="listing-title mb-2">${escapeHtml(listing.title)}</h5>
                  <p class="small text-muted mb-3 flex-grow-1">${escapeHtml(description)}</p>
                  <div class="d-flex gap-2 mt-auto">
                    <button type="button"
                            class="btn btn-outline-primary btn-sm flex-fill text-center"
                            onclick="event.stopPropagation(); if(typeof showLoginModal === 'function') { showLoginModal('${baseUrl}/visit-book?id=${listing.id}'); } else { window.location.href='${baseUrl}/visit-book?id=${listing.id}'; }"
                            style="border-color: var(--primary); color: var(--primary);">
                      Book a Visit
                    </button>
                    <button type="button"
                            class="btn btn-primary btn-sm flex-fill text-white text-center"
                            onclick="event.stopPropagation(); if(typeof showLoginModal === 'function') { showLoginModal('${baseUrl}/listings/${listing.id}?action=book'); } else { window.location.href='${baseUrl}/listings/${listing.id}?action=book'; }">
                      Book Now
                    </button>
                  </div>
                </div>
              </div>
            </div>
          `;
        }).join('');
      }
    } else {
      featuredSection.innerHTML = `
        <div class="col-12">
          <div class="alert alert-warning">
            <p class="mb-0">Error loading search results. Please try again.</p>
          </div>
        </div>
      `;
    }
  } catch (error) {
    featuredSection.innerHTML = `
      <div class="col-12">
        <div class="alert alert-danger">
          <p class="mb-0">Error loading search results. Please try again.</p>
        </div>
      </div>
    `;
  }
}

// small escape util
function escapeHtml(s){ return String(s||'').replace(/[&<>"'`=\/]/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;','/':'&#x2F;','`':'&#x60;','=':'&#x3D;'}[c])); }


// Theme toggle — add at end of public/assets/js/main.js or in footer script
(function() {
  const STORAGE_KEY = 'pgfinder_theme'; // 'light' | 'dark'
  const html = document.documentElement;
  const toggleBtn = document.getElementById('themeToggle');
  const themeIcon = document.getElementById('themeIcon');

  function prefersDark() {
    return !!(window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches);
  }

  function setIconForTheme(theme) {
    if (!themeIcon) return;
    if (theme === 'dark') {
      themeIcon.classList.remove('bi-moon-stars');
      themeIcon.classList.add('bi-sun');
    } else {
      themeIcon.classList.remove('bi-sun');
      themeIcon.classList.add('bi-moon-stars');
    }
  }

  function applyTheme(theme) {
    if (theme === 'dark') {
      html.setAttribute('data-theme', 'dark');
      if (toggleBtn) toggleBtn.setAttribute('aria-pressed', 'true');
      setIconForTheme('dark');
    } else {
      html.removeAttribute('data-theme');
      if (toggleBtn) toggleBtn.setAttribute('aria-pressed', 'false');
      setIconForTheme('light');
    }
  }

  function getStoredTheme() {
    try { return localStorage.getItem(STORAGE_KEY); } catch(e) { return null; }
  }
  function storeTheme(val) {
    try { if (val) localStorage.setItem(STORAGE_KEY, val); } catch(e) {}
  }

  function getEffectiveTheme() {
    const stored = getStoredTheme();
    if (stored === 'dark' || stored === 'light') return stored;
    return prefersDark() ? 'dark' : 'light';
  }

  // Initial theme selection
  (function initTheme() {
    applyTheme(getEffectiveTheme());
  })();

  // Toggle handler: flip effective theme directly (no system cycle)
  if (toggleBtn) {
    toggleBtn.addEventListener('click', function() {
      const currentEffective = getEffectiveTheme();
      const next = currentEffective === 'dark' ? 'light' : 'dark';
      storeTheme(next);
      applyTheme(next);
    });
  }

  // React to system preference change only if user hasn't set an explicit preference
  if (window.matchMedia) {
    window.matchMedia('(prefers-color-scheme: dark)').addEventListener('change', () => {
      const stored = getStoredTheme();
      if (stored !== 'dark' && stored !== 'light') {
        applyTheme(getEffectiveTheme());
      }
    });
  }

  // Sync across tabs
  window.addEventListener('storage', function(e) {
    if (e.key === STORAGE_KEY) {
      const val = e.newValue;
      if (val === 'dark' || val === 'light') applyTheme(val);
    }
  });
})();
