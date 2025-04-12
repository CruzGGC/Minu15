/**
 * 15-Minute City Explorer - Map Functionality
 * Handles map initialization, isochrone generation, and POI display
 */

// Global variables
let map;
let currentMarker;
let isochroneLayer;
let poiLayers = {};
let selectedPoi = null;
let selectedTransportMode = 'walking';
let selectedMaxDistance = 15; // in minutes

// Transport mode speed in km/h
const transportSpeeds = {
    walking: 5,
    cycling: 15,
    driving: 60
};

// POI types and their icons
const poiTypes = {
    hospitals: { 
        name: 'Hospitals', 
        icon: 'hospital', 
        class: 'poi-hospital',
        table: 'amenity',
        condition: "amenity = 'hospital'"
    },
    schools: { 
        name: 'Schools', 
        icon: 'school', 
        class: 'poi-school',
        table: 'amenity',
        condition: "amenity IN ('school', 'university', 'college', 'kindergarten')"
    },
    health: { 
        name: 'Health Centers', 
        icon: 'first-aid-kit', 
        class: 'poi-health',
        table: 'amenity',
        condition: "amenity IN ('clinic', 'doctors', 'dentist', 'pharmacy')"
    },
    culture: { 
        name: 'Cultural Facilities', 
        icon: 'landmark', 
        class: 'poi-culture',
        table: 'amenity',
        condition: "amenity IN ('theatre', 'cinema', 'library', 'arts_centre', 'community_centre', 'museum')"
    },
    shops: { 
        name: 'Shopping', 
        icon: 'shopping-cart', 
        class: 'poi-shop',
        table: 'shop',
        condition: "shop IS NOT NULL"
    },
    parks: { 
        name: 'Parks', 
        icon: 'tree', 
        class: 'poi-park',
        table: 'leisure',
        condition: "leisure IN ('park', 'garden', 'playground')"
    }
};

// Initialize the map when DOM is loaded
document.addEventListener('DOMContentLoaded', function() {
    initMap();
    initControls();
});

// Initialize Leaflet map
function initMap() {
    // Portugal center coordinates
    const portugalCenter = [39.5, -8.0];
    
    // Create map instance
    map = L.map('map').setView(portugalCenter, 7);
    
    // Add OpenStreetMap base layer
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors',
        maxZoom: 19
    }).addTo(map);
    
    // Initialize empty POI layer groups
    Object.keys(poiTypes).forEach(type => {
        poiLayers[type] = L.layerGroup().addTo(map);
    });
    
    // Add click event to the map
    map.on('click', function(e) {
        handleMapClick(e.latlng);
    });
    
    // Add legend to the map
    addLegend();
}

// Handle map click to generate isochrone
function handleMapClick(latlng) {
    // Clear existing marker if any
    if (currentMarker) {
        map.removeLayer(currentMarker);
    }
    
    // Add a marker at clicked location
    currentMarker = L.marker(latlng).addTo(map);
    
    // Show loading indicator
    showLoading();
    
    // Generate isochrone around the clicked point
    generateIsochrone(latlng);
    
    // Fetch POIs within the area
    fetchPOIs(latlng);
}

// Generate isochrone polygon
function generateIsochrone(latlng) {
    // Clear existing isochrone if any
    if (isochroneLayer) {
        map.removeLayer(isochroneLayer);
    }
    
    // Calculate distance in meters based on transport mode and max time
    const speedKmPerHour = transportSpeeds[selectedTransportMode];
    const distanceInKm = (speedKmPerHour * selectedMaxDistance) / 60;
    const distanceInMeters = distanceInKm * 1000;
    
    // Use turf.js to create a buffer around the point
    const point = turf.point([latlng.lng, latlng.lat]);
    const buffered = turf.buffer(point, distanceInMeters / 1000, { units: 'kilometers' });
    
    // Create GeoJSON layer from the buffer
    isochroneLayer = L.geoJSON(buffered, {
        style: function() {
            return {
                fillColor: getIsochroneColor(),
                weight: 2,
                opacity: 1,
                color: getIsochroneColor(),
                dashArray: getIsochroneDashArray(),
                fillOpacity: 0.3
            };
        }
    }).addTo(map);
    
    // Fit map to the isochrone
    map.fitBounds(isochroneLayer.getBounds());
    
    // Update statistics panel
    updateAreaStats(latlng, distanceInMeters);
}

// Get color based on transport mode
function getIsochroneColor() {
    const colors = {
        walking: '#3498db',
        cycling: '#2ecc71',
        driving: '#e74c3c'
    };
    
    return colors[selectedTransportMode];
}

// Get dash array based on transport mode
function getIsochroneDashArray() {
    const dashArrays = {
        walking: null,
        cycling: '5,5',
        driving: '10,5'
    };
    
    return dashArrays[selectedTransportMode];
}

// Fetch POIs from the database
function fetchPOIs(latlng) {
    // Clear existing POI layers
    Object.keys(poiTypes).forEach(type => {
        poiLayers[type].clearLayers();
    });
    
    // Calculate search radius based on transport mode and max time
    const speedKmPerHour = transportSpeeds[selectedTransportMode];
    const distanceInKm = (speedKmPerHour * selectedMaxDistance) / 60;
    const radiusInMeters = distanceInKm * 1000;
    
    // Fetch enabled POI types
    Object.keys(poiTypes).forEach(type => {
        const checkbox = document.getElementById(`poi-${type}`);
        if (checkbox.checked) {
            // Fetch POIs of this type from the server
            fetchPOIsByType(type, latlng, radiusInMeters);
        }
    });
}

// Fetch POIs of a specific type from the server
function fetchPOIsByType(type, latlng, radius) {
    // Create form data for the request
    const formData = new FormData();
    formData.append('type', type);
    formData.append('lat', latlng.lat);
    formData.append('lng', latlng.lng);
    formData.append('radius', radius);
    
    // Make AJAX request to the server
    fetch('includes/fetch_pois.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            // Add POIs to the map
            addPOIsToMap(type, data.pois);
        } else {
            console.error('Error fetching POIs:', data.message);
        }
        
        // Hide loading indicator when all requests are done
        hideLoading();
    })
    .catch(error => {
        console.error('Error:', error);
        hideLoading();
    });
}

// Add POIs to the map
function addPOIsToMap(type, pois) {
    const poiInfo = poiTypes[type];
    
    // Create markers for each POI
    pois.forEach(poi => {
        // Create custom icon
        const icon = L.divIcon({
            html: `<i class="fas fa-${poiInfo.icon} ${poiInfo.class}"></i>`,
            className: 'poi-icon',
            iconSize: [24, 24],
            iconAnchor: [12, 12]
        });
        
        // Create marker with custom icon
        const marker = L.marker([poi.latitude, poi.longitude], {
            icon: icon
        });
        
        // Add popup with basic info
        marker.bindPopup(`<strong>${poi.name || poiInfo.name}</strong><br>Click for more details`);
        
        // Add click event to show details
        marker.on('click', function() {
            selectedPoi = poi;
            showPoiDetails(poi);
        });
        
        // Add to layer group
        marker.addTo(poiLayers[type]);
    });
}

// Show POI details in the sidebar
function showPoiDetails(poi) {
    const poiInfoDiv = document.getElementById('poi-info');
    
    // Create HTML for POI details
    let html = `
        <div class="poi-detail">
            <div class="poi-title">${poi.name || 'Unnamed'}</div>
            <div>${poi.type}</div>
        </div>
    `;
    
    // Add address if available
    if (poi.address) {
        html += `
            <div class="poi-detail">
                <div class="poi-title">Address</div>
                <div>${poi.address}</div>
            </div>
        `;
    }
    
    // Add additional properties if available
    if (poi.properties) {
        Object.keys(poi.properties).forEach(key => {
            const value = poi.properties[key];
            if (value && key !== 'name' && key !== 'type') {
                html += `
                    <div class="poi-detail">
                        <div class="poi-title">${key.replace('_', ' ')}</div>
                        <div>${value}</div>
                    </div>
                `;
            }
        });
    }
    
    // Set the HTML to the div
    poiInfoDiv.innerHTML = html;
}

// Update area statistics in the sidebar
function updateAreaStats(latlng, radius) {
    const statsDiv = document.getElementById('area-stats');
    
    // Create form data for the request
    const formData = new FormData();
    formData.append('lat', latlng.lat);
    formData.append('lng', latlng.lng);
    formData.append('radius', radius);
    
    // Make AJAX request to the server
    fetch('includes/fetch_statistics.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            // Display statistics
            let html = '<div class="stats-list">';
            
            Object.keys(poiTypes).forEach(type => {
                const count = data.stats[type] || 0;
                html += `
                    <div class="stat-item">
                        <span class="stat-label">${poiTypes[type].name}:</span>
                        <span class="stat-value">${count}</span>
                    </div>
                `;
            });
            
            html += `
                <div class="stat-item">
                    <span class="stat-label">Total Area:</span>
                    <span class="stat-value">${data.stats.area_km2.toFixed(2)} km²</span>
                </div>
                <div class="stat-item">
                    <span class="stat-label">Population (est.):</span>
                    <span class="stat-value">${data.stats.population_estimate || 'N/A'}</span>
                </div>
            `;
            
            html += '</div>';
            statsDiv.innerHTML = html;
        } else {
            statsDiv.innerHTML = '<p>Error loading statistics</p>';
        }
    })
    .catch(error => {
        console.error('Error:', error);
        statsDiv.innerHTML = '<p>Error loading statistics</p>';
    });
}

// Add legend to the map
function addLegend() {
    const legend = L.control({ position: 'bottomright' });
    
    legend.onAdd = function () {
        const div = L.DomUtil.create('div', 'info legend');
        
        div.innerHTML = '<strong>Points of Interest</strong><br>';
        
        Object.keys(poiTypes).forEach(type => {
            const poi = poiTypes[type];
            div.innerHTML += `
                <div>
                    <i class="fas fa-${poi.icon} ${poi.class}"></i> 
                    ${poi.name}
                </div>
            `;
        });
        
        return div;
    };
    
    legend.addTo(map);
}

// Show loading indicator
function showLoading() {
    // Remove existing overlay if any
    hideLoading();
    
    // Create overlay
    const overlay = document.createElement('div');
    overlay.className = 'loading-overlay';
    overlay.id = 'loading-overlay';
    
    // Create spinner
    const spinner = document.createElement('div');
    spinner.className = 'spinner';
    
    // Append spinner to overlay
    overlay.appendChild(spinner);
    
    // Append overlay to map container
    document.getElementById('map').appendChild(overlay);
}

// Hide loading indicator
function hideLoading() {
    const overlay = document.getElementById('loading-overlay');
    if (overlay) {
        overlay.parentNode.removeChild(overlay);
    }
}