/**
 * Map Functionality for Livonto
 * Uses Google Maps API
 */

let map = null;
let markers = [];
let listingsData = [];
let googleMapsLoaded = false;
let mapInitPending = false;
let userMarker = null; // Store the "You" marker

/**
 * Initialize map
 */
async function initMap(centerLat = 20.5937, centerLng = 78.9629, zoom = 10) {
    const mapContainer = document.getElementById('listingsMap');
    if (!mapContainer) {
        return;
    }
    
    // Ensure map container is visible and has dimensions
    mapContainer.style.display = 'block';
    mapContainer.style.height = '500px';
    mapContainer.style.width = '100%';

    // Wait for Google Maps to be available (loaded via footer.php)
    if (!window.google || !window.google.maps) {
        console.log('Map: Waiting for Google Maps script...');
        await new Promise(resolve => {
            const checkInterval = setInterval(() => {
                if (window.google && window.google.maps) {
                    clearInterval(checkInterval);
                    resolve();
                }
            }, 100);
        });
    }

    try {
        if (!map) {
            console.log('Initializing new map at:', centerLat, centerLng);
            const mapOptions = {
                center: { lat: parseFloat(centerLat), lng: parseFloat(centerLng) },
                zoom: zoom,
                mapTypeControl: false,
                streetViewControl: false,
                fullscreenControl: true,
                mapId: "DEMO_MAP_ID", // Required for AdvancedMarkerElement
            };
            
            map = new google.maps.Map(mapContainer, mapOptions);
            
            // Trigger a custom event when map is ready
            window.dispatchEvent(new CustomEvent('mapReady'));
        } else {
            console.log('Updating existing map center to:', centerLat, centerLng);
            map.setCenter({ lat: parseFloat(centerLat), lng: parseFloat(centerLng) });
            map.setZoom(zoom);
        }

    } catch (error) {
        console.error('Error initializing Google Maps:', error);
    }
}

/**
 * Add markers to map
 */
async function addMarkers(listings) {
    // Clear existing markers (except user marker)
    clearMarkers();
    
    listingsData = listings;
    
    if (!map || !window.google) {
        return;
    }

    console.log(`Adding markers for ${listings.length} listings...`);

    if (!listings || listings.length === 0) {
        console.log('No listings to show on map.');
        return;
    }
    
    const bounds = new google.maps.LatLngBounds();
    const infoWindow = new google.maps.InfoWindow({
        maxWidth: 280
    });

    // Check if AdvancedMarkerElement is available
    let AdvancedMarkerElement = null;
    try {
        const markerLib = await google.maps.importLibrary("marker");
        AdvancedMarkerElement = markerLib.AdvancedMarkerElement;
    } catch (e) {
        console.warn("AdvancedMarkerElement not available, falling back to legacy Marker");
    }

    let validMarkersCount = 0;

    listings.forEach((listing) => {
        // Try different possible field names for coordinates
        let lat = listing.lat || listing.latitude || listing.lat_val;
        let lng = listing.lng || listing.longitude || listing.lng_val;
        
        // Parse to float
        lat = parseFloat(lat);
        lng = parseFloat(lng);
        
        if (isNaN(lat) || isNaN(lng) || lat === 0 || lng === 0) {
            console.warn(`Skipping listing ${listing.id}: Invalid coordinates (${lat}, ${lng})`);
            return;
        }
        
        // Validate coordinate ranges
        if (lat < -90 || lat > 90 || lng < -180 || lng > 180) {
            console.warn(`Skipping listing ${listing.id}: Coordinates out of range (${lat}, ${lng})`);
            return;
        }
        
        try {
            const position = { lat: lat, lng: lng };
            
            // Create popup content
            const title = escapeHtml(listing.title || 'Untitled');
            const city = escapeHtml(listing.city || 'N/A');
            const price = escapeHtml(listing.price || 'Price on request');
            const distance = listing.distance ? parseFloat(listing.distance).toFixed(1) : null;
            const imageHtml = listing.image ? `
                <img src="${escapeHtml(listing.image)}" 
                     alt="${title}" 
                     style="width: 100%; height: 140px; object-fit: cover; border-radius: 8px 8px 0 0; display: block;"
                     onerror="this.style.display='none'">
            ` : '';
            const distanceHtml = distance ? `
                <p class="small text-muted mb-2" style="font-size: 12px; margin: 4px 0 0 0;">
                    <i class="bi bi-signpost-2 me-1"></i>${distance} km away
                </p>
            ` : '';
            
            const contentString = `
                <div class="map-popup" style="padding: 0; overflow: hidden;">
                    ${imageHtml}
                    <div style="padding: 12px;">
                        <h6 class="mb-1" style="color: var(--primary-700); font-size: 15px; font-weight: 600; margin: 0;">
                            ${title}
                        </h6>
                        <p class="small text-muted mb-1" style="font-size: 13px; margin: 4px 0 0 0;">
                            <i class="bi bi-geo-alt me-1"></i>${city}
                        </p>
                        <p class="mb-0" style="color: var(--primary); font-weight: 700; font-size: 15px; margin-top: 6px;">
                            ${price}
                        </p>
                        ${distanceHtml}
                        <a href="${escapeHtml(listing.url)}"
                           class="btn btn-primary btn-sm w-100 mt-2"
                           style="font-size: 13px; padding: 6px 12px; border-radius: 6px;">
                            View Details
                        </a>
                    </div>
                </div>
            `;

            // Create marker using AdvancedMarkerElement if available, else fallback
            let marker;
            if (AdvancedMarkerElement) {
                marker = new AdvancedMarkerElement({
                    map: map,
                    position: position,
                    title: listing.title || 'Untitled',
                });
                // Use gmp-click for AdvancedMarkerElement
                marker.addListener("gmp-click", () => {
                    infoWindow.setContent(contentString);
                    infoWindow.open(map, marker);
                });
            } else {
                marker = new google.maps.Marker({
                    position: position,
                    map: map,
                    title: listing.title || 'Untitled',
                    animation: google.maps.Animation.DROP
                });
                // Use standard click for legacy Marker
                marker.addListener("click", () => {
                    infoWindow.setContent(contentString);
                    infoWindow.open(map, marker);
                });
            }
            
            markers.push(marker);
            bounds.extend(position);
            validMarkersCount++;

        } catch (error) {
            console.error('Error adding marker:', error);
        }
    });

    console.log(`Successfully added ${validMarkersCount} markers.`);

    // Fit map to bounds if there are markers
    if (markers.length > 0) {
        // If we have a user marker, include it in bounds
        if (userMarker) {
             // We need to know user marker position.
             // Since we just centered the map on it in initMap/loadListingsOnMap,
             // we can use map.getCenter() if we haven't moved it yet.
             bounds.extend(map.getCenter());
        }

        // Don't zoom in too close if only one marker
        if (markers.length === 1 && !userMarker) {
            map.setCenter(bounds.getCenter());
            map.setZoom(14);
        } else {
            map.fitBounds(bounds);
        }
    }
}

/**
 * Add "You" marker for searched location
 */
async function addUserMarker(lat, lng) {
    if (!map || !window.google) return;

    // Remove existing user marker
    if (userMarker) {
        userMarker.map = null;
        userMarker = null;
    }

    if (lat === null || lng === null) return;

    try {
        const position = { lat: parseFloat(lat), lng: parseFloat(lng) };

        // Check if AdvancedMarkerElement is available
        let AdvancedMarkerElement = null;
        let PinElement = null;
        try {
            const markerLib = await google.maps.importLibrary("marker");
            AdvancedMarkerElement = markerLib.AdvancedMarkerElement;
            PinElement = markerLib.PinElement;
        } catch (e) {}

        if (AdvancedMarkerElement && PinElement) {
            // Create a distinctive pin for "You"
            const pin = new PinElement({
                background: "#4285F4",
                borderColor: "#1a73e8",
                glyphColor: "white",
                scale: 1.2
            });

            userMarker = new AdvancedMarkerElement({
                map: map,
                position: position,
                title: "You are here",
                content: pin.element
            });
        } else {
            // Legacy marker with blue icon (default is red) or label
            userMarker = new google.maps.Marker({
                position: position,
                map: map,
                label: "You",
                title: "You are here",
                animation: google.maps.Animation.DROP
            });
        }

    } catch (error) {
        console.error('Error adding user marker:', error);
    }
}

/**
 * Clear all markers
 */
function clearMarkers() {
    if (markers.length > 0) {
        markers.forEach(marker => {
            marker.map = null; // For AdvancedMarkerElement
        });
    }
    markers = [];
    listingsData = [];
}

/**
 * Load listings and show on map
 */
async function loadListingsOnMap(city = '', query = '', lat = null, lng = null, radius = 50) {
    const mapContainer = document.getElementById('listingsMap');
    if (!mapContainer) {
        return;
    }
    
    // Show map section
    const mapSection = document.getElementById('mapSection');
    if (mapSection) {
        mapSection.style.display = 'block';

        // Set up close button
        const closeBtn = document.getElementById('closeMapBtn');
        if (closeBtn) {
            closeBtn.onclick = function() {
                mapSection.style.display = 'none';
            };
        }
    }

    // Show loading state
    const loadingDiv = document.getElementById('mapLoading');
    if (loadingDiv) {
        loadingDiv.style.display = 'flex';
    }
    
    try {
        const params = new URLSearchParams();
        if (city) {
            params.append('city', city);
            params.append('q', city);
        }
        if (query && query !== city) {
            params.append('q', query);
        }
        if (lat !== null) params.append('lat', lat);
        if (lng !== null) params.append('lng', lng);
        if (radius) params.append('radius', radius);

        console.log('Fetching map data with params:', params.toString());

        const response = await fetch(`${baseUrl}/app/listings_map_api.php?${params.toString()}`);
        
        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }
        
        const data = await response.json();
        console.log('Map API response:', data);

        if (data && (data.status === 'ok' || data.status === 'success')) {
            let listings = [];
            let center = null;
            
            if (data.data) {
                listings = data.data.listings || [];
                center = data.data.center || null;
            } else {
                listings = data.listings || [];
                center = data.center || null;
            }
            
            // Initialize map
            const defaultCenter = center || { lat: 20.5937, lng: 78.9629 };
            console.log('Using center:', defaultCenter);

            await initMap(defaultCenter.lat, defaultCenter.lng, listings.length > 0 ? 12 : 15); // Zoom 15 if no listings (specific location)

            // Add "You" marker if we have search coordinates
            if (lat !== null && lng !== null) {
                await addUserMarker(lat, lng);
            } else if (userMarker) {
                // Clear user marker if no coordinates provided (e.g. text search only)
                userMarker.map = null;
                userMarker = null;
            }

            // Add markers
            if (listings.length > 0) {
                addMarkers(listings);
            } else {
                console.log('No listings returned from API.');
            }
            
        } else {
            // Fallback initialization
            await initMap();
        }
        
    } catch (error) {
        console.error('Error loading listings map:', error);
        // Still try to init map
        await initMap();
    } finally {
        if (loadingDiv) {
            loadingDiv.style.display = 'none';
        }
    }
}

/**
 * Escape HTML to prevent XSS
 */
function escapeHtml(text) {
    if (!text) return '';
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

// Make functions available globally
window.initMap = initMap;
window.addMarkers = addMarkers;
window.clearMarkers = clearMarkers;
window.loadListingsOnMap = loadListingsOnMap;
