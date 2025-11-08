/**
 * Autocomplete functionality for search input
 */

let autocompleteTimeout = null;
let selectedIndex = -1;
let suggestions = [];

/**
 * Escape HTML to prevent XSS
 */
function escapeHtml(text) {
    // Use the global escapeHtml from main.js if available, but avoid recursion
    if (typeof window.escapeHtml === 'function' && window.escapeHtml !== escapeHtml) {
        return window.escapeHtml(text);
    }
    // Fallback implementation
    return String(text || '').replace(/[&<>"'`=\/]/g, c => ({
        '&': '&amp;',
        '<': '&lt;',
        '>': '&gt;',
        '"': '&quot;',
        "'": '&#39;',
        '/': '&#x2F;',
        '`': '&#60;',
        '=': '&#61;'
    }[c] || c));
}

/**
 * Initialize autocomplete for search input
 */
function initAutocomplete() {
    const searchInput = document.getElementById('searchInput');
    const suggestionsContainer = document.getElementById('searchSuggestions');
    
    if (!searchInput || !suggestionsContainer) {
        return;
    }
    
    // Handle input typing
    searchInput.addEventListener('input', function(e) {
        const value = e.target.value.trim();
        
        // Clear previous timeout
        if (autocompleteTimeout) {
            clearTimeout(autocompleteTimeout);
        }
        
        // Hide suggestions if input is empty
        if (value.length === 0) {
            hideSuggestions();
            return;
        }
        
        // Show suggestions after a short delay (debounce)
        autocompleteTimeout = setTimeout(() => {
            fetchSuggestions(value);
        }, 300);
    });
    
    // Handle keyboard navigation
    searchInput.addEventListener('keydown', function(e) {
        if (suggestions.length === 0) return;
        
        if (e.key === 'ArrowDown') {
            e.preventDefault();
            selectedIndex = Math.min(selectedIndex + 1, suggestions.length - 1);
            updateSelectedSuggestion();
        } else if (e.key === 'ArrowUp') {
            e.preventDefault();
            selectedIndex = Math.max(selectedIndex - 1, -1);
            updateSelectedSuggestion();
        } else if (e.key === 'Enter' && selectedIndex >= 0) {
            e.preventDefault();
            selectSuggestion(suggestions[selectedIndex]);
        } else if (e.key === 'Escape') {
            hideSuggestions();
        }
    });
    
    // Hide suggestions when clicking outside
    document.addEventListener('click', function(e) {
        if (!searchInput.contains(e.target) && !suggestionsContainer.contains(e.target)) {
            hideSuggestions();
        }
    });
    
    // Handle suggestion click
    suggestionsContainer.addEventListener('click', function(e) {
        const suggestionItem = e.target.closest('.suggestion-item');
        if (suggestionItem) {
            const index = parseInt(suggestionItem.dataset.index);
            if (suggestions[index]) {
                selectSuggestion(suggestions[index]);
            }
        }
    });
}

/**
 * Fetch suggestions from geocoding API
 */
async function fetchSuggestions(query) {
    if (query.length < 2) {
        hideSuggestions();
        return;
    }
    
    const suggestionsContainer = document.getElementById('searchSuggestions');
    if (!suggestionsContainer) return;
    
    // Show loading state
    suggestionsContainer.innerHTML = '<div class="suggestion-item suggestion-loading"><i class="bi bi-hourglass-split me-2"></i>Loading suggestions...</div>';
    suggestionsContainer.style.display = 'block';
    
    try {
        // First, check common cities
        const commonCities = getCommonCitySuggestions(query);
        
        // Then fetch from geocoding API
        const geocodeResults = await geocodeCitySuggestions(query);
        
        // Combine results (common cities first, then geocoded results)
        suggestions = [...commonCities, ...geocodeResults].slice(0, 8); // Limit to 8 suggestions
        
        displaySuggestions(suggestions);
    } catch (error) {
        // CORS errors are expected when calling Nominatim from browser
        // Show common cities as fallback
        suggestions = getCommonCitySuggestions(query);
        displaySuggestions(suggestions);
    }
}

/**
 * Get suggestions from common cities
 */
function getCommonCitySuggestions(query) {
    const commonCities = [
        'Mumbai', 'Delhi', 'Bangalore', 'Hyderabad', 'Chennai', 'Kolkata', 'Pune', 'Ahmedabad',
        'Jaipur', 'Surat', 'Lucknow', 'Kanpur', 'Nagpur', 'Indore', 'Thane', 'Bhopal', 'Visakhapatnam',
        'Patna', 'Vadodara', 'Ghaziabad', 'Ludhiana', 'Agra', 'Nashik', 'Faridabad', 'Meerut',
        'Rajkot', 'Varanasi', 'Srinagar', 'Amritsar', 'Chandigarh', 'Kochi', 'Bhubaneswar',
        'Dehradun', 'Mangalore', 'Mysore', 'Gurgaon', 'Noida', 'Coimbatore', 'Madurai', 'Trichy',
        'Vijayawada', 'Guwahati', 'Jodhpur', 'Raipur', 'Ranchi', 'Jabalpur', 'Gwalior', 'Udaipur',
        'Tirupati', 'Salem', 'Hubli', 'Belgaum', 'Jammu', 'Durgapur', 'Siliguri', 'Aligarh', 'Bareilly',
        'Moradabad', 'Allahabad', 'Gorakhpur', 'Warangal', 'Nellore', 'Kakinada', 'Kolhapur',
        'Solapur', 'Aurangabad', 'Cuttack', 'Ajmer', 'Bilaspur', 'Panaji', 'Pondicherry', 'Shillong',
        'Aizawl', 'Itanagar', 'Imphal', 'Agartala', 'Dharamshala', 'Haridwar', 'Rishikesh',
        'Jamshedpur', 'Dhanbad', 'Bokaro', 'Silchar', 'Bhavnagar', 'Karimnagar', 'Tirunelveli',
        'Erode', 'Kottayam', 'Thrissur', 'Kannur', 'Palakkad', 'Guntur', 'Anantapur', 'Nizamabad'
      ];
      
    
    const queryLower = query.toLowerCase();
    return commonCities
        .filter(city => city.toLowerCase().includes(queryLower))
        .slice(0, 5)
        .map(city => ({
            name: city,
            display_name: city + ', India',
            type: 'city'
        }));
}

/**
 * Fetch suggestions from OpenStreetMap Nominatim API
 */
async function geocodeCitySuggestions(query) {
    try {
        const encodedQuery = encodeURIComponent(query.trim() + ', India');
        const response = await fetch(
            `https://nominatim.openstreetmap.org/search?format=json&q=${encodedQuery}&limit=5&addressdetails=1`,
            {
                headers: {
                    'User-Agent': 'Livonto PG Finder'
                }
            }
        );
        
        if (!response.ok) {
            return [];
        }
        
        const data = await response.json();
        
        return data
            .filter(item => {
                // Filter to show cities, towns, and villages
                const type = item.type || '';
                const classType = item.class || '';
                return type === 'city' || type === 'town' || type === 'village' || 
                       classType === 'place' || classType === 'boundary';
            })
            .map(item => ({
                name: item.name || item.display_name.split(',')[0],
                display_name: item.display_name,
                lat: parseFloat(item.lat),
                lng: parseFloat(item.lon),
                type: item.type || 'location'
            }))
            .slice(0, 5);
    } catch (error) {
        console.error('Error geocoding suggestions:', error);
        return [];
    }
}

/**
 * Display suggestions in the dropdown
 */
function displaySuggestions(suggestionsList) {
    const suggestionsContainer = document.getElementById('searchSuggestions');
    if (!suggestionsContainer) return;
    
    if (suggestionsList.length === 0) {
        suggestionsContainer.innerHTML = '<div class="suggestion-item suggestion-empty">No suggestions found</div>';
        suggestionsContainer.style.display = 'block';
        return;
    }
    
    suggestions = suggestionsList;
    selectedIndex = -1;
    
    const html = suggestionsList.map((suggestion, index) => {
        const icon = suggestion.type === 'city' ? 'bi-building' : 'bi-geo-alt';
        return `
            <div class="suggestion-item" data-index="${index}">
                <i class="bi ${icon} me-2"></i>
                <div class="flex-grow-1">
                    <div class="suggestion-name">${escapeHtml(suggestion.name)}</div>
                    ${suggestion.display_name && suggestion.display_name !== suggestion.name ? 
                        `<div class="suggestion-details">${escapeHtml(suggestion.display_name)}</div>` : ''}
                </div>
            </div>
        `;
    }).join('');
    
    suggestionsContainer.innerHTML = html;
    suggestionsContainer.style.display = 'block';
}

/**
 * Update selected suggestion (for keyboard navigation)
 */
function updateSelectedSuggestion() {
    const items = document.querySelectorAll('.suggestion-item');
    items.forEach((item, index) => {
        if (index === selectedIndex) {
            item.classList.add('selected');
            item.scrollIntoView({ block: 'nearest', behavior: 'smooth' });
        } else {
            item.classList.remove('selected');
        }
    });
}

/**
 * Select a suggestion
 */
function selectSuggestion(suggestion) {
    const searchInput = document.getElementById('searchInput');
    if (searchInput) {
        searchInput.value = suggestion.name;
        hideSuggestions();
        
        // Trigger search
        const searchForm = document.getElementById('searchForm');
        if (searchForm) {
            searchForm.dispatchEvent(new Event('submit', { cancelable: true, bubbles: true }));
        }
    }
}

/**
 * Hide suggestions dropdown
 */
function hideSuggestions() {
    const suggestionsContainer = document.getElementById('searchSuggestions');
    if (suggestionsContainer) {
        suggestionsContainer.style.display = 'none';
        suggestionsContainer.innerHTML = '';
    }
    selectedIndex = -1;
    suggestions = [];
}

// Initialize autocomplete when DOM is ready
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initAutocomplete);
} else {
    initAutocomplete();
}

