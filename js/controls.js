/**
 * Explorador de Cidade em 15 Minutos - Controls Functionality
 * Handles UI control interactions and their effects on the map
 * 
 * @version 2.0
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
    
    // Initialize search box
    initSearchBox();
    
    // Initialize panel close buttons
    initPanelCloseButtons();
}

// Initialize collapsible panels
function initCollapsiblePanels() {
    // Initialize main panel headers
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
    
    // Initialize POI category headers
    document.querySelectorAll('.category-header').forEach(header => {
        header.addEventListener('click', function(e) {
            // Prevent event propagation to parent panel
            e.stopPropagation();
            
            const content = this.nextElementSibling;
            if (content && content.classList.contains('category-content')) {
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
    
    // Start with first category expanded (Health)
    const firstCategory = document.querySelector('.category-content');
    if (firstCategory) {
        firstCategory.classList.add('expanded');
        const arrow = firstCategory.previousElementSibling.querySelector('.dropdown-arrow');
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
            
            // Update selected transport mode
            selectedTransportMode = this.getAttribute('data-mode');
            
            // Don't automatically update map - wait for Calculate button
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
    
    // Set initial distance value
    distanceValue.textContent = distanceSlider.value + ' minutos';
    
    // Add input event listener to slider
    distanceSlider.addEventListener('input', function() {
        // Update displayed value
        distanceValue.textContent = this.value + ' minutos';
        
        // Update selected max distance
        selectedMaxDistance = parseInt(this.value);
        
        // Don't automatically update map - wait for Calculate button
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

// Handle POI checkbox toggle
function handlePoiToggle(type) {
    const checkbox = document.getElementById(`poi-${type}`);
    const isChecked = checkbox.checked;
    
    // Show or hide the layer based on checkbox state
    if (isChecked) {
        // Just make sure the layer is added to the map
        // Don't fetch new POIs - that will happen when Calculate is clicked
        if (!map.hasLayer(poiLayers[type])) {
            map.addLayer(poiLayers[type]);
        }
    } else {
        // Remove layer from map
        if (map.hasLayer(poiLayers[type])) {
            map.removeLayer(poiLayers[type]);
        }
    }
    
    // If we have an active isochrone, update the statistics
    // to reflect the currently selected POIs
    if (currentIsochroneData && currentMarker) {
        updateAreaStats(
            currentMarker.getLatLng(), 
            calculateRadiusFromIsochrone(currentIsochroneData),
            JSON.stringify(currentIsochroneData)
        );
    }
}

// Helper to calculate radius from isochrone for statistics
function calculateRadiusFromIsochrone(isochroneData) {
    let radiusInMeters;
    
    if (isochroneData.features && 
        isochroneData.features[0] && 
        isochroneData.features[0].properties && 
        isochroneData.features[0].properties.area) {
        // Convert km² to m² to get an equivalent radius
        const areaInKm2 = isochroneData.features[0].properties.area;
        radiusInMeters = Math.sqrt(areaInKm2 * 1000000 / Math.PI);
    } else {
        // Fallback: use speed-based estimate
        const speedKmPerHour = transportSpeeds[selectedTransportMode];
        const distanceInKm = (speedKmPerHour * selectedMaxDistance) / 60;
        radiusInMeters = distanceInKm * 1000;
    }
    
    return radiusInMeters;
}

// Initialize calculate button
function initCalculateButton() {
    const calculateButton = document.querySelector('.calculate-button');
    if (calculateButton) {
        calculateButton.addEventListener('click', function() {
            if (currentMarker) {
                // Show loading indicator
                showLoading();
                
                // Generate isochrone using ORS API
                generateIsochrone(currentMarker.getLatLng());
            } else {
                alert('Por favor, selecione primeiro uma localização no mapa');
            }
        });
    }
}

// Initialize search box
function initSearchBox() {
    const searchBox = document.querySelector('.search-box');
    const searchButton = document.querySelector('.search-button');
    
    if (searchBox && searchButton) {
        // Search when button is clicked
        searchButton.addEventListener('click', function() {
            performSearch(searchBox.value);
        });
        
        // Search when Enter key is pressed
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
                
                // Don't generate isochrone automatically - wait for Calculate button
                
                // Guide the user to click Calculate
                alert('Localização encontrada! Clique em "Calcular" para gerar a isócrona.');
            } else {
                alert('Localização não encontrada. Por favor, tente outro termo de pesquisa.');
            }
        })
        .catch(error => {
            hideLoading();
            console.error('Error searching location:', error);
            alert('Ocorreu um erro ao pesquisar a localização.');
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

// Reset the UI to its initial state
function resetUI() {
    // Clear statistics panel
    document.getElementById('area-stats').innerHTML = '<p>Clique no mapa para ver estatísticas</p>';
    
    // Clear POI info panel
    document.getElementById('poi-info').innerHTML = '<p>Clique num ponto de interesse para ver detalhes</p>';
    
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
    clearAllPOIs();
    
    // Reset current isochrone data
    currentIsochroneData = null;
}