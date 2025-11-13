/**
 * Map Functionality for Livonto
 * Uses Leaflet.js with Google Maps-like styling
 */

let map = null;
let markers = [];
let listingsData = [];

/**
 * Initialize map with Google Maps-like styling
 */
function initMap(centerLat = 20.5937, centerLng = 78.9629, zoom = 10) {
    const mapContainer = document.getElementById('listingsMap');
    if (!mapContainer) {
        return;
    }
    
    // Remove existing map if any
    if (map) {
        map.remove();
        map = null;
    }
    
    // Ensure map container is visible and has dimensions
    mapContainer.style.display = 'block';
    mapContainer.style.height = '500px';
    mapContainer.style.width = '100%';
    mapContainer.style.visibility = 'visible';
    
    // Clear any existing content
    mapContainer.innerHTML = '';
    
    // Wait a bit longer to ensure container is fully rendered and visible
    setTimeout(() => {
        try {
            // Create map with Google Maps-like tile layer
            map = L.map('listingsMap', {
                center: [centerLat, centerLng],
                zoom: zoom,
                zoomControl: true,
                attributionControl: true
            });
            
            // Use OpenStreetMap tiles styled to look like Google Maps
            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors',
                maxZoom: 19
            }).addTo(map);
            
            // Force map to invalidate size after initialization
            setTimeout(() => {
                if (map) {
                    map.invalidateSize();
                    // Trigger a custom event when map is ready
                    window.dispatchEvent(new CustomEvent('mapReady'));
                }
            }, 200);
            
            // Style to look more like Google Maps
            mapContainer.style.borderRadius = '12px';
            mapContainer.style.boxShadow = '0 4px 12px rgba(0,0,0,0.1)';
        } catch (error) {
            // Error initializing map
        }
    }, 100);
}

/**
 * Add custom marker icon
 */
function createCustomIcon(listing) {
    return L.divIcon({
        className: 'custom-marker',
        html: `
            <div class="marker-pin" style="
                background: linear-gradient(135deg, #8B6BD1 0%, #6F55B2 100%);
                width: 40px;
                height: 40px;
                border-radius: 50% 50% 50% 0;
                transform: rotate(-45deg);
                border: 3px solid white;
                box-shadow: 0 2px 8px rgba(0,0,0,0.3);
                display: flex;
                align-items: center;
                justify-content: center;
            ">
                <i class="bi bi-building" style="
                    color: white;
                    font-size: 18px;
                    transform: rotate(45deg);
                "></i>
            </div>
        `,
        iconSize: [40, 40],
        iconAnchor: [20, 40],
        popupAnchor: [0, -40]
    });
}

/**
 * Add markers to map
 */
function addMarkers(listings) {
    // Clear existing markers
    clearMarkers();
    
    listingsData = listings;
    
    if (!map) {
        return;
    }
    
    if (!listings || listings.length === 0) {
        return;
    }
    
    const bounds = [];
    let validMarkers = 0;
    
    listings.forEach((listing) => {
        // Try different possible field names for coordinates
        let lat = listing.lat || listing.latitude || listing.lat_val;
        let lng = listing.lng || listing.longitude || listing.lng_val;
        
        // Parse to float
        lat = parseFloat(lat);
        lng = parseFloat(lng);
        
        if (isNaN(lat) || isNaN(lng) || lat === 0 || lng === 0) {
            return;
        }
        
        // Validate coordinate ranges
        if (lat < -90 || lat > 90 || lng < -180 || lng > 180) {
            return;
        }
        
        try {
            // Ensure coordinates are numbers
            const markerLat = Number(lat);
            const markerLng = Number(lng);
            
            if (isNaN(markerLat) || isNaN(markerLng)) {
                return;
            }
            
            const marker = L.marker([markerLat, markerLng], {
                icon: createCustomIcon(listing)
            });
            
            marker.addTo(map);
            
            // Create popup content
            const title = escapeHtml(listing.title || 'Untitled');
            const city = escapeHtml(listing.city || 'N/A');
            const price = escapeHtml(listing.price || 'Price on request');
            const distance = listing.distance ? parseFloat(listing.distance).toFixed(1) : null;
            const imageHtml = listing.image ? `
                <img src="${escapeHtml(listing.image)}" 
                     alt="${title}" 
                     style="width: 100%; height: 120px; object-fit: cover; border-radius: 8px 8px 0 0; margin: -12px -12px 8px -12px;"
                     onerror="this.style.display='none'">
            ` : '';
            const distanceHtml = distance ? `
                <p class="small text-muted mb-2" style="font-size: 11px; margin: 0;">
                    <i class="bi bi-signpost-2 me-1"></i>${distance} km away
                </p>
            ` : '';
            
            const popupContent = `
                <div class="map-popup" style="min-width: 200px;">
                    ${imageHtml}
                    <h6 class="mb-2" style="color: var(--primary-700); font-size: 14px; font-weight: 600;">
                        ${title}
                    </h6>
                    <p class="small text-muted mb-2" style="font-size: 12px; margin: 0;">
                        <i class="bi bi-geo-alt me-1"></i>${city}
                    </p>
                    <p class="mb-2" style="color: var(--primary); font-weight: 600; font-size: 14px; margin: 0;">
                        ${price}
                    </p>
                    ${distanceHtml}
                    <a href="${escapeHtml(listing.url)}" 
                       class="btn btn-primary btn-sm w-100 mt-2" 
                       style="font-size: 12px; padding: 4px 8px;">
                        View Details
                    </a>
                </div>
            `;
            
            marker.bindPopup(popupContent, {
                maxWidth: 250,
                className: 'custom-popup'
            });
            
            markers.push(marker);
            bounds.push([lat, lng]);
            validMarkers++;
        } catch (error) {
            // Error adding marker
        }
    });
    
    // Don't auto-fit to markers - keep map centered on searched location
    // The map center is already set by loadListingsOnMap based on the search term
    // Just refresh the map to ensure markers are visible
    setTimeout(() => {
        map.invalidateSize();
    }, 100);
}

/**
 * Clear all markers
 */
function clearMarkers() {
    if (map && markers.length > 0) {
        markers.forEach(marker => {
            try {
                map.removeLayer(marker);
            } catch (error) {
                // Error removing marker
            }
        });
    }
    markers = [];
    listingsData = [];
}

/**
 * Load listings and show on map
 */
async function loadListingsOnMap(city = '', query = '', lat = null, lng = null) {
    const mapContainer = document.getElementById('listingsMap');
    if (!mapContainer) {
        return;
    }
    
    // Show map section first - this is critical
    const mapSection = document.getElementById('mapSection');
    if (mapSection) {
        mapSection.style.display = 'block';
        // Force a reflow to ensure the element is rendered
        mapSection.offsetHeight;
        
        // Hide placeholder image if it exists
        const mapPlaceholder = document.getElementById('mapPlaceholder');
        if (mapPlaceholder) {
            mapPlaceholder.style.display = 'none';
        }
    } else {
        return;
    }
    
    // Ensure map container is visible and has proper dimensions
    mapContainer.style.display = 'block';
    mapContainer.style.height = '500px';
    mapContainer.style.width = '100%';
    mapContainer.style.visibility = 'visible';
    mapContainer.style.opacity = '1';
    
    // Show loading state
    const loadingDiv = document.getElementById('mapLoading');
    if (loadingDiv) {
        loadingDiv.style.display = 'flex';
    }
    
    try {
        const params = new URLSearchParams();
        // When using single search input, pass the same value as both city and query
        // This allows the API to search by city name and also by text query
        if (city) {
            params.append('city', city);
            params.append('q', city); // Also use as query for text search
        }
        if (query && query !== city) {
            params.append('q', query);
        }
        if (lat !== null) params.append('lat', lat);
        if (lng !== null) params.append('lng', lng);
        
        const response = await fetch(`${baseUrl}/app/listings_map_api.php?${params.toString()}`);
        
        if (!response.ok) {
            const errorText = await response.text();
            console.error('Map API error:', response.status, errorText);
            throw new Error(`HTTP error! status: ${response.status}`);
        }
        
        const responseText = await response.text();
        let data;
        try {
            data = JSON.parse(responseText);
        } catch (parseError) {
            console.error('JSON parse error:', parseError, 'Response:', responseText);
            throw new Error('Invalid JSON response from server');
        }
        
        // Check if response is successful
        if (data && (data.status === 'ok' || data.status === 'success')) {
            // Handle both data.data.listings and data.listings formats
            let listings = [];
            let center = null;
            
            if (data.data) {
                listings = data.data.listings || [];
                center = data.data.center || null;
            } else {
                listings = data.listings || [];
                center = data.center || null;
            }
            
            // If no listings found for the city, try fetching all listings (without city filter)
            // This shows nearby listings even if city doesn't match exactly
            if (listings.length === 0 && city) {
                try {
                    const allListingsResponse = await fetch(`${baseUrl}/app/listings_map_api.php?city=`);
                    const allListingsData = await allListingsResponse.json();
                    
                    if (allListingsData && (allListingsData.status === 'ok' || allListingsData.status === 'success')) {
                        const allListings = (allListingsData.data && allListingsData.data.listings) ? allListingsData.data.listings : (allListingsData.listings || []);
                        
                        if (allListings.length > 0) {
                            listings = allListings;
                            // DON'T update center to listings center - keep the searched location center
                            // The center will be set later based on the search term geocoding
                        }
                    }
                } catch (err) {
                    // Error fetching all listings
                }
            }
            
            // ALWAYS try to geocode the search term to center the map
            // This ensures the map shows the searched location even if no listings are found
            if (city || query) {
                const searchTerm = city || query;
                let cityCoords = null;
                
                // First try default city map (fast lookup for common cities)
                if (searchTerm) {
                    cityCoords = getDefaultCityCoords(searchTerm);
                }
                
                // If not found in defaults, use geocoding API (works for ANY city/location worldwide)
                if (!cityCoords && searchTerm) {
                    cityCoords = await geocodeCity(searchTerm);
                }
                
                // STRICTLY use the searched location for centering - never fall back to listings
                // This ensures the map always shows the area the user searched for
                if (cityCoords) {
                    center = { lat: cityCoords.lat, lng: cityCoords.lng };
                } else {
                    // If geocoding failed completely, still try to use a default for that search term
                    // But don't use listings center - that would show wrong location
                    center = { lat: 20.5937, lng: 78.9629 }; // Default India center
                }
            } else if (!center) {
                // No search term, use listings center or default
                center = data.data.center || { lat: 20.5937, lng: 78.9629 };
            }
            
            // Always initialize map, even if no listings
            // Wait a bit to ensure container is fully visible
            setTimeout(() => {
                const finalListings = listings;
                
                if (!map) {
                    // Initialize map with center coordinates
                    initMap(center.lat, center.lng, finalListings.length > 0 ? 12 : (city ? 11 : 6));
                    
                    // Store listings in a variable accessible to the callback
                    const listingsToAdd = finalListings;
                    
                    // Wait for map to be ready before adding markers
                    const addMarkersWhenReady = () => {
                        if (map && listingsToAdd.length > 0) {
                            // Ensure map is fully rendered
                            if (map.invalidateSize) {
                                map.invalidateSize();
                            }
                            // Use a longer delay to ensure map tiles are loaded
                            setTimeout(() => {
                                addMarkers(listingsToAdd);
                            }, 500);
                            window.removeEventListener('mapReady', addMarkersWhenReady);
                        }
                    };
                    
                    // Listen for map ready event or use timeout as fallback
                    window.addEventListener('mapReady', addMarkersWhenReady);
                    setTimeout(() => {
                        if (map && listingsToAdd.length > 0) {
                            if (map.invalidateSize) {
                                map.invalidateSize();
                            }
                            setTimeout(() => {
                                addMarkersWhenReady();
                            }, 300);
                        }
                    }, 1500);
                } else {
                    // Update map view - always center on searched location
                    const finalListings = listings;
                    const zoomLevel = finalListings.length > 0 ? 13 : (city || query ? 12 : 6);
                    map.setView([center.lat, center.lng], zoomLevel);
                    map.invalidateSize();
                    
                    // Add markers immediately if map already exists
                    if (finalListings.length > 0) {
                        setTimeout(() => {
                            addMarkers(finalListings);
                        }, 300);
                    }
                }
            }, 150);
        } else {
            // Check if data is in a different format
            if (data.listings) {
                const listings = data.listings || [];
                const center = data.center || { lat: 20.5937, lng: 78.9629 };
                
                setTimeout(() => {
                    if (!map) {
                        initMap(center.lat, center.lng, listings.length > 0 ? 12 : 11);
                        setTimeout(() => {
                            if (listings.length > 0) {
                                addMarkers(listings);
                            }
                        }, 500);
                    } else {
                        map.setView([center.lat, center.lng], listings.length > 0 ? 12 : 11);
                        if (listings.length > 0) {
                            addMarkers(listings);
                        }
                    }
                }, 150);
                return;
            }
            
            // Still show the map even if there's an error, but ALWAYS try to center on searched city
            let defaultCenter = { lat: 20.5937, lng: 78.9629 };
            let defaultZoom = 6;
            
            // If there's a search term, ALWAYS try to geocode it and center on it
            const searchTerm = city || query;
            if (searchTerm) {
                let cityCoords = getDefaultCityCoords(searchTerm);
                if (!cityCoords) {
                    cityCoords = await geocodeCity(searchTerm);
                }
                if (cityCoords) {
                    defaultCenter = { lat: cityCoords.lat, lng: cityCoords.lng };
                    defaultZoom = 12; // Closer zoom for searched location
                }
            }
            
            setTimeout(() => {
                initMap(defaultCenter.lat, defaultCenter.lng, defaultZoom);
            }, 150);
        }
    } catch (error) {
        // Still try to show the map, ALWAYS centered on searched location if available
        let defaultCenter = { lat: 20.5937, lng: 78.9629 };
        let defaultZoom = 6;
        
        // If there's a search term, ALWAYS try to geocode it and center on it
        const searchTerm = city || query;
        if (searchTerm) {
            try {
                let cityCoords = getDefaultCityCoords(searchTerm);
                if (!cityCoords) {
                    cityCoords = await geocodeCity(searchTerm);
                }
                if (cityCoords) {
                    defaultCenter = { lat: cityCoords.lat, lng: cityCoords.lng };
                    defaultZoom = 12; // Closer zoom for searched location
                }
            } catch (geocodeError) {
                // Error geocoding city - keep default center
            }
        }
        
        setTimeout(() => {
            initMap(defaultCenter.lat, defaultCenter.lng, defaultZoom);
        }, 150);
    } finally {
        // Hide loading state after a delay
        setTimeout(() => {
            if (loadingDiv) {
                loadingDiv.style.display = 'none';
            }
        }, 500);
    }
}

/**
 * Get user's current location
 */
function getUserLocation() {
    return new Promise((resolve, reject) => {
        if (!navigator.geolocation) {
            reject(new Error('Geolocation is not supported by your browser'));
            return;
        }
        
        navigator.geolocation.getCurrentPosition(
            position => {
                resolve({
                    lat: position.coords.latitude,
                    lng: position.coords.longitude
                });
            },
            error => {
                reject(error);
            }
        );
    });
}

/**
 * Geocode city name to coordinates using OpenStreetMap Nominatim API
 * This works for ANY city worldwide, not just hardcoded ones
 */
async function geocodeCity(cityName) {
    if (!cityName || cityName.trim() === '') {
        return null;
    }
    
    try {
        // Use OpenStreetMap Nominatim API (free, no API key required)
        // First try with "India" suffix for better results
        let encodedCity = encodeURIComponent(cityName.trim() + ', India');
        let response = await fetch(`https://nominatim.openstreetmap.org/search?format=json&q=${encodedCity}&limit=1&addressdetails=1`, {
            headers: {
                'User-Agent': 'Livonto PG Finder' // Required by Nominatim
            }
        });
        
        let data = await response.json();
        
        // If no results with "India" suffix, try without it (for international cities)
        if (!data || data.length === 0) {
            encodedCity = encodeURIComponent(cityName.trim());
            response = await fetch(`https://nominatim.openstreetmap.org/search?format=json&q=${encodedCity}&limit=1&addressdetails=1`, {
                headers: {
                    'User-Agent': 'Livonto PG Finder'
                }
            });
            data = await response.json();
        }
        
        if (data && data.length > 0) {
            const result = {
                lat: parseFloat(data[0].lat),
                lng: parseFloat(data[0].lon),
                display_name: data[0].display_name
            };
            return result;
        }
        
        return null;
    } catch (error) {
        return null;
    }
}

/**
 * Get default coordinates for common Indian cities
 */
function getDefaultCityCoords(cityName) {
    const cityMap = {
        'bengaluru': { lat: 12.9716, lng: 77.5946 },
        'bangalore': { lat: 12.9716, lng: 77.5946 },
        'mumbai': { lat: 19.0760, lng: 72.8777 },
        'pune': { lat: 18.5204, lng: 73.8567 },
        'delhi': { lat: 28.6139, lng: 77.2090 },
        'new delhi': { lat: 28.6139, lng: 77.2090 },
        'kolkata': { lat: 22.5726, lng: 88.3639 },
        'calcutta': { lat: 22.5726, lng: 88.3639 },
        'hyderabad': { lat: 17.3850, lng: 78.4867 },
        'chennai': { lat: 13.0827, lng: 80.2707 },
        'madras': { lat: 13.0827, lng: 80.2707 },
        'ahmedabad': { lat: 23.0225, lng: 72.5714 },
        'jaipur': { lat: 26.9124, lng: 75.7873 },
        'lucknow': { lat: 26.8467, lng: 80.9462 },
        'kanpur': { lat: 26.4499, lng: 80.3319 },
        'nagpur': { lat: 21.1458, lng: 79.0882 },
        'indore': { lat: 22.7196, lng: 75.8577 },
        'thane': { lat: 19.2183, lng: 72.9781 },
        'bhopal': { lat: 23.2599, lng: 77.4126 },
        'visakhapatnam': { lat: 17.6868, lng: 83.2185 },
        'patna': { lat: 25.5941, lng: 85.1376 },
        'vadodara': { lat: 22.3072, lng: 73.1812 },
        'ghaziabad': { lat: 28.6692, lng: 77.4538 },
        'ludhiana': { lat: 30.9010, lng: 75.8573 },
        'agra': { lat: 27.1767, lng: 78.0081 },
        'nashik': { lat: 19.9975, lng: 73.7898 },
        'faridabad': { lat: 28.4089, lng: 77.3178 },
        'meerut': { lat: 28.9845, lng: 77.7064 },
        'rajkot': { lat: 22.3039, lng: 70.8022 },
        'varanasi': { lat: 25.3176, lng: 82.9739 },
        'srinagar': { lat: 34.0837, lng: 74.7973 },
        'amritsar': { lat: 31.6340, lng: 74.8723 },
        'chandigarh': { lat: 30.7333, lng: 76.7794 },
        'kochi': { lat: 9.9312, lng: 76.2673 },
        'cochin': { lat: 9.9312, lng: 76.2673 },
        'bhubaneswar': { lat: 20.2961, lng: 85.8245 },
        'dehradun': { lat: 30.3165, lng: 78.0322 },
        'mangalore': { lat: 12.9141, lng: 74.8560 },
        'mysore': { lat: 12.2958, lng: 76.6394 },
        'gurgaon': { lat: 28.4089, lng: 77.0378 },
        'gurugram': { lat: 28.4089, lng: 77.0378 },
        'noida': { lat: 28.5355, lng: 77.3910 },
        'beldanga': { lat: 23.9333, lng: 88.2500 } // Example for user's search
    };
    
    const normalizedCity = cityName.toLowerCase().trim();
    return cityMap[normalizedCity] || null;
}

/**
 * Escape HTML to prevent XSS
 */
function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

// Make functions available globally
window.initMap = initMap;
window.addMarkers = addMarkers;
window.clearMarkers = clearMarkers;
window.loadListingsOnMap = loadListingsOnMap;
window.getUserLocation = getUserLocation;
window.geocodeCity = geocodeCity;
window.getDefaultCityCoords = getDefaultCityCoords;

