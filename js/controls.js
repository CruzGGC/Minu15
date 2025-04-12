/**
 * 15-Minute City Explorer - Control Functionality
 * Handles UI controls interactions and their effects on the map
 */

// Initialize controls when DOM is loaded
function initControls() {
    // Initialize collapsible panels
    initCollapsiblePanels();
    
    // Initialize transport mode selector
    initTransportModeSelector();
    
    // Initialize distance slider
    initDistanceSlider();
    
    // Initialize POI checkboxes
    initPoiCheckboxes();
    
    // Initialize calculate button
    initCalculateButton();
    
    // Initialize search functionality
    initSearchBox();
    
    // Initialize panel close buttons
    initPanelCloseButtons();
}

// Initialize collapsible panels
function initCollapsiblePanels() {
    document.querySelectorAll('.panel-header').forEach(header => {
        header.addEventListener('click', function() {
            const content = this.nextElementSibling;
            if (content && content.classList.contains('panel-content')) {
                content.classList.toggle('expanded');
                const arrow = this.querySelector('.dropdown-arrow');
                if (arrow) {
                    arrow.classList.toggle('up');
                }
            }
        });
    });
    
    // Start with POI panel expanded
    const poiContent = document.getElementById('poi-content');
    if (poiContent) {
        poiContent.classList.add('expanded');
        const arrow = document.querySelector('#poi-header .dropdown-arrow');
        if (arrow) {
            arrow.classList.add('up');
        }
    }
}

// Initialize transport mode selector
function initTransportModeSelector() {
    const transportOptions = document.querySelectorAll('.transport-option');
    
    transportOptions.forEach(option => {
        option.addEventListener('click', function() {
            // Remove active class from all options
            transportOptions.forEach(opt => {
                opt.classList.remove('active');
            });
            
            // Add active class to selected option
            this.classList.add('active');
            
            // Update the selected transport mode
            selectedTransportMode = this.getAttribute('data-mode');
            
            // Update the map if there's a current marker
            if (currentMarker) {
                generateIsochrone(currentMarker.getLatLng());
                fetchPOIs(currentMarker.getLatLng());
            }
        });
    });
    
    // Set initial transport mode
    const activeModeElement = document.querySelector('.transport-option.active');
    if (activeModeElement) {
        selectedTransportMode = activeModeElement.getAttribute('data-mode');
    }
}

// Initialize distance slider
function initDistanceSlider() {
    const distanceSlider = document.getElementById('max-distance');
    const distanceValue = document.getElementById('distance-value');
    
    // Set initial distance value display
    distanceValue.textContent = distanceSlider.value + ' minutes';
    
    // Add event listener to slider
    distanceSlider.addEventListener('input', function() {
        // Update display value
        distanceValue.textContent = this.value + ' minutes';
        
        // Update selected max distance
        selectedMaxDistance = parseInt(this.value);
        
        // Update the map if there's a current marker
        if (currentMarker) {
            generateIsochrone(currentMarker.getLatLng());
            fetchPOIs(currentMarker.getLatLng());
        }
    });
}

// Initialize POI checkboxes
function initPoiCheckboxes() {
    Object.keys(poiTypes).forEach(type => {
        const checkbox = document.getElementById(`poi-${type}`);
        if (checkbox) {
            checkbox.addEventListener('change', () => handlePoiToggle(type));
        }
    });
}

// Handle POI type toggle
function handlePoiToggle(type) {
    const checkbox = document.getElementById(`poi-${type}`);
    const isChecked = checkbox.checked;
    
    // Show or hide the layer based on checkbox state
    if (isChecked) {
        if (currentMarker) {
            // Fetch POIs of this type if a point is selected
            const latlng = currentMarker.getLatLng();
            const speedKmPerHour = transportSpeeds[selectedTransportMode];
            const distanceInKm = (speedKmPerHour * selectedMaxDistance) / 60;
            const radiusInMeters = distanceInKm * 1000;
            
            fetchPOIsByType(type, latlng, radiusInMeters);
        }
        
        // Make sure the layer is added to the map
        if (!map.hasLayer(poiLayers[type])) {
            map.addLayer(poiLayers[type]);
        }
    } else {
        // Remove the layer from the map
        if (map.hasLayer(poiLayers[type])) {
            map.removeLayer(poiLayers[type]);
        }
    }
}

// Initialize calculate button
function initCalculateButton() {
    const calculateButton = document.querySelector('.calculate-button');
    if (calculateButton) {
        calculateButton.addEventListener('click', function() {
            if (currentMarker) {
                generateIsochrone(currentMarker.getLatLng());
                fetchPOIs(currentMarker.getLatLng());
                
                // Show statistics panel
                showStatisticsPanel();
            } else {
                alert('Please select a location on the map first');
            }
        });
    }
}

// Initialize search box
function initSearchBox() {
    const searchBox = document.querySelector('.search-box');
    const searchButton = document.querySelector('.search-button');
    
    if (searchBox && searchButton) {
        // Search on button click
        searchButton.addEventListener('click', function() {
            performSearch(searchBox.value);
        });
        
        // Search on Enter key
        searchBox.addEventListener('keydown', function(e) {
            if (e.key === 'Enter') {
                performSearch(this.value);
            }
        });
    }
}

// Perform location search
function performSearch(searchTerm) {
    if (!searchTerm.trim()) {
        return;
    }
    
    // Show loading indicator
    showLoading();
    
    // Use Nominatim for geocoding (OpenStreetMap's geocoding service)
    const searchUrl = `https://nominatim.openstreetmap.org/search?format=json&q=${encodeURIComponent(searchTerm)},Portugal&limit=1`;
    
    fetch(searchUrl)
        .then(response => response.json())
        .then(data => {
            hideLoading();
            
            if (data && data.length > 0) {
                const result = data[0];
                const latlng = L.latLng(result.lat, result.lon);
                
                // Set map view to found location
                map.setView(latlng, 15);
                
                // Create marker at found location
                if (currentMarker) {
                    map.removeLayer(currentMarker);
                }
                currentMarker = L.marker(latlng).addTo(map);
                
                // Generate isochrone
                generateIsochrone(latlng);
                
                // Fetch POIs
                fetchPOIs(latlng);
                
                // Show statistics panel
                showStatisticsPanel();
            } else {
                alert('Location not found. Please try another search term.');
            }
        })
        .catch(error => {
            hideLoading();
            console.error('Error searching for location:', error);
            alert('An error occurred while searching for the location.');
        });
}

// Initialize panel close buttons
function initPanelCloseButtons() {
    // Statistics panel close button
    const closeStatsButton = document.querySelector('.close-stats');
    if (closeStatsButton) {
        closeStatsButton.addEventListener('click', function() {
            hideStatisticsPanel();
        });
    }
    
    // POI details panel close button
    const closePoiDetailsButton = document.querySelector('.close-poi-details');
    if (closePoiDetailsButton) {
        closePoiDetailsButton.addEventListener('click', function() {
            hidePoiDetailsPanel();
        });
    }
}

// Show statistics panel
function showStatisticsPanel() {
    const statsPanel = document.querySelector('.statistics-panel');
    if (statsPanel) {
        statsPanel.classList.add('visible');
    }
}

// Hide statistics panel
function hideStatisticsPanel() {
    const statsPanel = document.querySelector('.statistics-panel');
    if (statsPanel) {
        statsPanel.classList.remove('visible');
    }
}

// Show POI details panel
function showPoiDetailsPanel() {
    const poiDetailsPanel = document.querySelector('.poi-details-panel');
    if (poiDetailsPanel) {
        poiDetailsPanel.classList.add('visible');
    }
}

// Hide POI details panel
function hidePoiDetailsPanel() {
    const poiDetailsPanel = document.querySelector('.poi-details-panel');
    if (poiDetailsPanel) {
        poiDetailsPanel.classList.remove('visible');
    }
}

// Function to reset the UI
function resetUI() {
    // Clear the area stats panel
    document.getElementById('area-stats').innerHTML = '<p>Click on the map to see statistics</p>';
    
    // Clear the POI info panel
    document.getElementById('poi-info').innerHTML = '<p>Click on a point of interest for details</p>';
    
    // Hide panels
    hideStatisticsPanel();
    hidePoiDetailsPanel();
    
    // Reset all layers
    if (isochroneLayer) {
        map.removeLayer(isochroneLayer);
        isochroneLayer = null;
    }
    
    if (currentMarker) {
        map.removeLayer(currentMarker);
        currentMarker = null;
    }
    
    // Clear POI layers
    Object.keys(poiLayers).forEach(type => {
        poiLayers[type].clearLayers();
    });
}