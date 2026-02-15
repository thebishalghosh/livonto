/**
 * Autocomplete functionality for search input
 * Uses Google Maps Places Autocomplete Widget
 */

let autocomplete = null;

/**
 * Initialize autocomplete
 */
function initAutocomplete() {
    // Check for searchInput (Homepage) OR cityFilter (Listings page)
    const searchInput = document.getElementById('searchInput') || document.getElementById('cityFilter');

    if (!searchInput) {
        return;
    }

    console.log('Initializing Google Autocomplete Widget on:', searchInput.id);

    // Wait for Google Maps API to be available
    if (!window.google || !window.google.maps || !window.google.maps.places) {
        console.log('Waiting for Google Maps script...');
        const checkInterval = setInterval(() => {
            if (window.google && window.google.maps && window.google.maps.places) {
                clearInterval(checkInterval);
                setupAutocomplete(searchInput);
            }
        }, 100);
    } else {
        setupAutocomplete(searchInput);
    }
}

/**
 * Setup the Autocomplete Widget
 */
function setupAutocomplete(inputElement) {
    try {
        // Configuration matching the working test page
        // Removed 'types' restriction to allow all place types (areas, landmarks, etc.)
        const options = {
            componentRestrictions: { country: 'in' },
            fields: ["geometry", "name", "formatted_address"]
        };

        autocomplete = new google.maps.places.Autocomplete(inputElement, options);

        console.log('Google Autocomplete Widget attached.');

        // Handle place selection
        autocomplete.addListener("place_changed", () => {
            const place = autocomplete.getPlace();

            if (!place.geometry) {
                // User entered the name of a Place that was not suggested and
                // pressed the Enter key, or the Place Details request failed.
                console.log("No details available for input: '" + place.name + "'");
                return;
            }

            console.log('Place selected:', place.name);

            // Set hidden lat/lng fields
            const latInput = document.getElementById('searchLat');
            const lngInput = document.getElementById('searchLng');

            if (latInput && lngInput && place.geometry.location) {
                latInput.value = place.geometry.location.lat();
                lngInput.value = place.geometry.location.lng();
                console.log('Coordinates set:', latInput.value, lngInput.value);
            }

            // Trigger search immediately on selection
            // Check for either searchForm (Homepage) or filtersForm (Listings page)
            const form = document.getElementById('searchForm') || document.getElementById('filtersForm');
            if (form) {
                // Create a custom event that bubbles
                const submitEvent = new Event('submit', { cancelable: true, bubbles: true });
                form.dispatchEvent(submitEvent);
            }
        });

        // Clear coordinates when user types manually
        inputElement.addEventListener('input', function() {
            const latInput = document.getElementById('searchLat');
            const lngInput = document.getElementById('searchLng');
            if (latInput) latInput.value = '';
            if (lngInput) lngInput.value = '';
        });

    } catch (error) {
        console.error('Error setting up autocomplete:', error);
    }
}

// Initialize autocomplete when DOM is ready
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initAutocomplete);
} else {
    initAutocomplete();
}
