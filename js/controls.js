/**
 * 15-Minute City Explorer - Control Functionality
 * Handles UI controls interactions and their effects on the map
 */

// Initialize controls when DOM is loaded
function initControls() {
    // Get control elements
    const transportSelector = document.getElementById('transport-mode');
    const distanceSlider = document.getElementById('max-distance');
    const distanceValue = document.getElementById('distance-value');
    
    // Set initial distance value display
    distanceValue.textContent = distanceSlider.value;
    
    // Add event listeners to controls
    transportSelector.addEventListener('change', handleTransportChange);
    distanceSlider.addEventListener('input', handleDistanceChange);
    
    // Add event listeners to POI checkboxes
    Object.keys(poiTypes).forEach(type => {
        const checkbox = document.getElementById(`poi-${type}`);
        if (checkbox) {
            checkbox.addEventListener('change', () => handlePoiToggle(type));
        }
    });
}

// Handle transport mode change
function handleTransportChange(event) {
    // Update selected transport mode
    selectedTransportMode = event.target.value;
    
    // Update the map if there's a current marker
    if (currentMarker) {
        // Generate new isochrone with updated transport mode
        generateIsochrone(currentMarker.getLatLng());
        
        // Fetch POIs with updated transport mode
        fetchPOIs(currentMarker.getLatLng());
    }
}

// Handle distance slider change
function handleDistanceChange(event) {
    // Update selected max distance
    selectedMaxDistance = parseInt(event.target.value);
    
    // Update the distance value display
    document.getElementById('distance-value').textContent = selectedMaxDistance;
    
    // Update the map if there's a current marker
    if (currentMarker) {
        // Generate new isochrone with updated distance
        generateIsochrone(currentMarker.getLatLng());
        
        // Fetch POIs with updated distance
        fetchPOIs(currentMarker.getLatLng());
    }
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

// Function to reset the UI
function resetUI() {
    // Clear the area stats panel
    document.getElementById('area-stats').innerHTML = '<p>Click on the map to see statistics</p>';
    
    // Clear the POI info panel
    document.getElementById('poi-info').innerHTML = '<p>Click on a point of interest for details</p>';
    
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