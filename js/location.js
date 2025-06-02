/**
 * Location.js
 * Handles the functionality for the location.php page
 * Allows users to select locations from dropdowns or by clicking on the map
 * Fetches and displays data from GeoAPI.pt
 */

// Initialize variables
let map;
let locationMarker;
let locationPolygon;
let currentLocation = null;

// Map style providers
const mapProviders = {
    osm: {
        url: 'https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png',
        attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors'
    },
    positron: {
        url: 'https://{s}.basemaps.cartocdn.com/light_all/{z}/{x}/{y}{r}.png',
        attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors &copy; <a href="https://carto.com/attributions">CARTO</a>'
    },
    dark_matter: {
        url: 'https://{s}.basemaps.cartocdn.com/dark_all/{z}/{x}/{y}{r}.png',
        attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors &copy; <a href="https://carto.com/attributions">CARTO</a>'
    }
};

// POI icons and colors
const poiIcons = {
    hospitals: { icon: 'hospital', color: '#e74c3c' },
    health_centers: { icon: 'first-aid', color: '#e74c3c' },
    pharmacies: { icon: 'prescription-bottle-alt', color: '#e74c3c' },
    dentists: { icon: 'tooth', color: '#e74c3c' },
    schools: { icon: 'school', color: '#3498db' },
    universities: { icon: 'graduation-cap', color: '#3498db' },
    kindergartens: { icon: 'baby', color: '#3498db' },
    libraries: { icon: 'book', color: '#3498db' },
    supermarkets: { icon: 'shopping-basket', color: '#f39c12' },
    malls: { icon: 'shopping-bag', color: '#f39c12' },
    restaurants: { icon: 'utensils', color: '#f39c12' },
    atms: { icon: 'money-bill-wave', color: '#f39c12' },
    parks: { icon: 'tree', color: '#2ecc71' },
    sports: { icon: 'dumbbell', color: '#2ecc71' },
    bus_stops: { icon: 'bus', color: '#9b59b6' },
    police_stations: { icon: 'shield-alt', color: '#9b59b6' }
};

// Initialize the map when the DOM is fully loaded
document.addEventListener('DOMContentLoaded', function() {
    initializeMap();
    loadDistritos();
    setupEventListeners();
});

/**
 * Initialize the Leaflet map
 */
function initializeMap() {
    // Create the map centered on Portugal
    map = L.map('map', {
        center: [39.6, -8.0],
        zoom: 7,
        zoomControl: false,
        attributionControl: false
    });
    
    // Add zoom control to the top-right
    L.control.zoom({
        position: 'topright'
    }).addTo(map);
    
    // Add attribution control to the bottom-right
    L.control.attribution({
        position: 'bottomright'
    }).addTo(map);
    
    // Set the default map style (Positron)
    setMapStyle('positron');
    
    // Add click event to the map
    map.on('click', function(e) {
        const lat = e.latlng.lat;
        const lng = e.latlng.lng;
        
        // Clear previous selection
        clearLocationSelection();
        
        // Add marker at clicked location
        locationMarker = L.marker([lat, lng]).addTo(map);
        
        // Update UI to show loading state
        document.querySelector('.calculate-button').textContent = 'A carregar...';
        document.querySelector('.calculate-button').disabled = true;
        
        // Fetch location data for the clicked coordinates
        fetchLocationByCoordinates(lat, lng);
    });
}

/**
 * Set the map style based on the selected provider
 */
function setMapStyle(provider) {
    // Remove existing tile layer if it exists
    if (window.tileLayer && map.hasLayer(window.tileLayer)) {
        map.removeLayer(window.tileLayer);
    }
    
    // Create new tile layer with selected provider
    window.tileLayer = L.tileLayer(mapProviders[provider].url, {
        attribution: mapProviders[provider].attribution,
        maxZoom: 19
    }).addTo(map);
    
    // Update active style in UI
    document.querySelectorAll('.map-style-option').forEach(function(el) {
        el.classList.remove('active');
    });
    document.querySelector(`.map-style-option[data-provider="${provider}"]`).classList.add('active');
}

/**
 * Load the list of distritos from the API
 */
function loadDistritos() {
    fetch('../includes/geoapi_proxy.php?endpoint=' + encodeURIComponent('distritos/base'))
        .then(response => response.json())
        .then(data => {
            const distritoSelect = document.getElementById('distrito-select');
            
            // Sort distritos alphabetically (they should already be sorted)
            data.sort();
            
            // Add options to select
            data.forEach(distritoName => {
                const option = document.createElement('option');
                option.value = distritoName;
                option.textContent = distritoName;
                distritoSelect.appendChild(option);
            });
        })
        .catch(error => {
            console.error('Error loading distritos:', error);
        });
}

/**
 * Load concelhos for the selected distrito
 */
function loadConcelhos(distrito) {
    fetch('../includes/geoapi_proxy.php?endpoint=' + encodeURIComponent(`distrito/${encodeURIComponent(distrito)}/municipios`))
        .then(response => response.json())
        .then(data => {
            const concelhoSelect = document.getElementById('concelho-select');
            
            // Clear previous options
            concelhoSelect.innerHTML = '<option value="">Selecione um concelho...</option>';
            
            // Get the municipios array from the response
            const municipios = data.municipios || [];
            
            // Sort municipios alphabetically
            municipios.sort();
            
            // Add options to select
            municipios.forEach(municipioName => {
                const option = document.createElement('option');
                option.value = municipioName;
                option.textContent = municipioName;
                concelhoSelect.appendChild(option);
            });
            
            // Enable the select
            concelhoSelect.disabled = false;
            
            // Disable freguesia select until concelho is selected
            document.getElementById('freguesia-select').disabled = true;
            document.getElementById('freguesia-select').innerHTML = '<option value="">Selecione uma freguesia...</option>';
        })
        .catch(error => {
            console.error('Error loading concelhos:', error);
        });
}

/**
 * Load freguesias for the selected concelho
 */
function loadFreguesias(concelho) {
    fetch('../includes/geoapi_proxy.php?endpoint=' + encodeURIComponent(`municipio/${encodeURIComponent(concelho)}/freguesias`))
        .then(response => response.json())
        .then(data => {
            const freguesiaSelect = document.getElementById('freguesia-select');
            
            // Clear previous options
            freguesiaSelect.innerHTML = '<option value="">Selecione uma freguesia...</option>';
            
            // Get the freguesias array from the response
            const freguesias = data.freguesias || [];
            
            // Sort freguesias alphabetically
            freguesias.sort();
            
            // Add options to select
            freguesias.forEach(freguesiaName => {
                const option = document.createElement('option');
                option.value = freguesiaName;
                option.textContent = freguesiaName;
                freguesiaSelect.appendChild(option);
            });
            
            // Enable the select
            freguesiaSelect.disabled = false;
        })
        .catch(error => {
            console.error('Error loading freguesias:', error);
        });
}

/**
 * Fetch location data by coordinates
 */
function fetchLocationByCoordinates(lat, lng) {
    fetch(`../includes/fetch_location_data.php?lat=${lat}&lng=${lng}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                currentLocation = data.data;
                displayLocationData(currentLocation);
                
                // Show the location data panel
                document.querySelector('.location-data-panel').classList.add('active');
                
                // Update UI to show normal state
                document.querySelector('.calculate-button').textContent = 'Carregar Dados';
                document.querySelector('.calculate-button').disabled = false;
                
                // Draw the location boundary if geometry is available
                if (currentLocation.geojson) {
                    drawLocationBoundary(currentLocation.geojson);
                } else if (currentLocation.geometry) {
                    drawLocationBoundary(currentLocation.geometry);
                }
            } else {
                alert('Não foi possível obter dados para esta localização.');
                document.querySelector('.calculate-button').textContent = 'Carregar Dados';
                document.querySelector('.calculate-button').disabled = false;
            }
        })
        .catch(error => {
            console.error('Error fetching location data:', error);
            alert('Ocorreu um erro ao obter os dados da localização.');
            document.querySelector('.calculate-button').textContent = 'Carregar Dados';
            document.querySelector('.calculate-button').disabled = false;
        });
}

/**
 * Fetch location data by freguesia
 */
function fetchLocationByFreguesia(freguesia, concelho) {
    // Update UI to show loading state
    document.querySelector('.calculate-button').textContent = 'A carregar...';
    document.querySelector('.calculate-button').disabled = true;
    
    fetch(`../includes/fetch_location_data.php?freguesia=${encodeURIComponent(freguesia)}&concelho=${encodeURIComponent(concelho)}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                currentLocation = data.data;
                displayLocationData(currentLocation);
                
                // Show the location data panel
                document.querySelector('.location-data-panel').classList.add('active');
                
                // Update UI to show normal state
                document.querySelector('.calculate-button').textContent = 'Carregar Dados';
                document.querySelector('.calculate-button').disabled = false;
                
                // Draw the location boundary if geometry is available
                if (currentLocation.geojson) {
                    drawLocationBoundary(currentLocation.geojson);
                } else if (currentLocation.geometry) {
                    drawLocationBoundary(currentLocation.geometry);
                }
            } else {
                alert('Não foi possível obter dados para esta freguesia.');
                document.querySelector('.calculate-button').textContent = 'Carregar Dados';
                document.querySelector('.calculate-button').disabled = false;
            }
        })
        .catch(error => {
            console.error('Error fetching freguesia data:', error);
            alert('Ocorreu um erro ao obter os dados da freguesia.');
            document.querySelector('.calculate-button').textContent = 'Carregar Dados';
            document.querySelector('.calculate-button').disabled = false;
        });
}

/**
 * Fetch location data by concelho
 */
function fetchLocationByConcelho(concelho) {
    // Update UI to show loading state
    document.querySelector('.calculate-button').textContent = 'A carregar...';
    document.querySelector('.calculate-button').disabled = true;
    
    fetch(`../includes/fetch_location_data.php?concelho=${encodeURIComponent(concelho)}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                currentLocation = data.data;
                displayLocationData(currentLocation);
                
                // Show the location data panel
                document.querySelector('.location-data-panel').classList.add('active');
                
                // Update UI to show normal state
                document.querySelector('.calculate-button').textContent = 'Carregar Dados';
                document.querySelector('.calculate-button').disabled = false;
                
                // Draw the location boundary if geometry is available
                if (currentLocation.geojson) {
                    drawLocationBoundary(currentLocation.geojson);
                } else if (currentLocation.geometry) {
                    drawLocationBoundary(currentLocation.geometry);
                }
            } else {
                alert('Não foi possível obter dados para este concelho.');
                document.querySelector('.calculate-button').textContent = 'Carregar Dados';
                document.querySelector('.calculate-button').disabled = false;
            }
        })
        .catch(error => {
            console.error('Error fetching concelho data:', error);
            alert('Ocorreu um erro ao obter os dados do concelho.');
            document.querySelector('.calculate-button').textContent = 'Carregar Dados';
            document.querySelector('.calculate-button').disabled = false;
        });
}

/**
 * Fetch location data by distrito
 */
function fetchLocationByDistrito(distrito) {
    // Update UI to show loading state
    document.querySelector('.calculate-button').textContent = 'A carregar...';
    document.querySelector('.calculate-button').disabled = true;
    
    fetch(`../includes/fetch_location_data.php?distrito=${encodeURIComponent(distrito)}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                currentLocation = data.data;
                displayLocationData(currentLocation);
                
                // Show the location data panel
                document.querySelector('.location-data-panel').classList.add('active');
                
                // Update UI to show normal state
                document.querySelector('.calculate-button').textContent = 'Carregar Dados';
                document.querySelector('.calculate-button').disabled = false;
                
                // Draw the location boundary if geometry is available
                if (currentLocation.geojson) {
                    drawLocationBoundary(currentLocation.geojson);
                } else if (currentLocation.geometry) {
                    drawLocationBoundary(currentLocation.geometry);
                }
            } else {
                alert('Não foi possível obter dados para este distrito.');
                document.querySelector('.calculate-button').textContent = 'Carregar Dados';
                document.querySelector('.calculate-button').disabled = false;
            }
        })
        .catch(error => {
            console.error('Error fetching distrito data:', error);
            alert('Ocorreu um erro ao obter os dados do distrito.');
            document.querySelector('.calculate-button').textContent = 'Carregar Dados';
            document.querySelector('.calculate-button').disabled = false;
        });
}

/**
 * Display location data in the panel
 */
function displayLocationData(location) {
    const locationDataElement = document.getElementById('location-data');
    
    // Create HTML content
    let html = '';
    
    // Location header
    if (location.nome) {
        html += `<h2>${location.nome}</h2>`;
    }
    
    // Administrative hierarchy
    html += '<div class="location-hierarchy">';
    if (location.municipio) {
        html += `<p><strong>Concelho:</strong> ${location.municipio}</p>`;
    }
    if (location.distrito) {
        html += `<p><strong>Distrito:</strong> ${location.distrito}</p>`;
    }
    html += '</div>';
    
    // Census data
    if (location.censos2021 || location.censos2011) {
        html += '<div class="location-census">';
        html += '<h3>Dados Demográficos</h3>';
        
        // Population
        const population = location.censos2021?.N_INDIVIDUOS_RESIDENT || location.censos2011?.N_INDIVIDUOS_RESIDENT;
        if (population) {
            html += `<p><strong>População:</strong> ${population.toLocaleString()} habitantes</p>`;
        }
        
        // Buildings and housing
        const buildings = location.censos2021?.N_EDIFICIOS_CLASSICOS || location.censos2011?.N_EDIFICIOS_CLASSICOS;
        if (buildings) {
            html += `<p><strong>Edifícios:</strong> ${buildings.toLocaleString()}</p>`;
        }
        
        const dwellings = location.censos2021?.N_ALOJAMENTOS || location.censos2011?.N_ALOJAMENTOS;
        if (dwellings) {
            html += `<p><strong>Alojamentos:</strong> ${dwellings.toLocaleString()}</p>`;
        }
        
        // Area and density if available
        if (location.area_ha || location.areaha) {
            const areaHa = location.area_ha || location.areaha;
            const areaKm2 = parseFloat(areaHa) / 100;
            html += `<p><strong>Área:</strong> ${areaKm2.toLocaleString()} km²</p>`;
            
            if (population) {
                const density = Math.round(population / areaKm2);
                html += `<p><strong>Densidade Populacional:</strong> ${density.toLocaleString()} hab/km²</p>`;
            }
        }
        
        html += '</div>';
    }
    
    // POI counts if available
    if (location.poi_counts) {
        html += '<div class="location-pois">';
        html += '<h3>Infraestruturas</h3>';
        
        // Group POIs by category
        const poiCategories = {
            'Saúde': ['hospitals', 'health_centers', 'pharmacies', 'dentists'],
            'Educação': ['schools', 'universities', 'kindergartens', 'libraries'],
            'Comércio e Serviços': ['supermarkets', 'malls', 'restaurants', 'atms'],
            'Outros': ['parks', 'sports', 'bus_stops', 'police_stations']
        };
        
        // POI names in Portuguese
        const poiNames = {
            hospitals: 'Hospitais',
            health_centers: 'Centros de Saúde',
            pharmacies: 'Farmácias',
            dentists: 'Clínicas Dentárias',
            schools: 'Escolas',
            universities: 'Universidades',
            kindergartens: 'Jardins de Infância',
            libraries: 'Bibliotecas',
            supermarkets: 'Supermercados',
            malls: 'Centros Comerciais',
            restaurants: 'Restaurantes',
            atms: 'Multibancos',
            parks: 'Parques',
            sports: 'Instalações Desportivas',
            bus_stops: 'Paragens de Autocarro',
            police_stations: 'Esquadras de Polícia'
        };
        
        // Create POI count tables by category
        for (const [category, pois] of Object.entries(poiCategories)) {
            let categoryHtml = `<h4>${category}</h4>`;
            categoryHtml += '<table class="poi-table">';
            
            let hasData = false;
            
            pois.forEach(poi => {
                if (location.poi_counts && location.poi_counts[poi] !== undefined) {
                    hasData = true;
                    categoryHtml += `
                        <tr>
                            <td><i class="fas fa-${poiIcons[poi].icon}" style="color: ${poiIcons[poi].color}"></i> ${poiNames[poi]}</td>
                            <td>${location.poi_counts[poi]}</td>
                        </tr>
                    `;
                }
            });
            
            categoryHtml += '</table>';
            
            if (hasData) {
                html += categoryHtml;
            }
        }
        
        html += '</div>';
    }
    
    // If no data is available
    if (html === '') {
        html = '<p>Não foram encontrados dados para esta localização.</p>';
    }
    
    // Set the HTML content
    locationDataElement.innerHTML = html;
}

/**
 * Draw the location boundary on the map
 */
function drawLocationBoundary(geojson) {
    // Remove existing polygon if it exists
    if (locationPolygon) {
        map.removeLayer(locationPolygon);
    }
    
    // Create the polygon from GeoJSON
    locationPolygon = L.geoJSON(geojson, {
        style: {
            color: '#2980b9',
            weight: 3,
            opacity: 0.8,
            fillColor: '#3498db',
            fillOpacity: 0.2
        }
    }).addTo(map);
    
    // Zoom to the polygon bounds
    map.fitBounds(locationPolygon.getBounds());
}

/**
 * Clear the current location selection
 */
function clearLocationSelection() {
    // Remove marker if it exists
    if (locationMarker) {
        map.removeLayer(locationMarker);
        locationMarker = null;
    }
    
    // Remove polygon if it exists
    if (locationPolygon) {
        map.removeLayer(locationPolygon);
        locationPolygon = null;
    }
    
    // Reset current location
    currentLocation = null;
    
    // Hide the location data panel
    document.querySelector('.location-data-panel').classList.remove('active');
}

/**
 * Set up event listeners for UI elements
 */
function setupEventListeners() {
    // Distrito select change
    document.getElementById('distrito-select').addEventListener('change', function() {
        const distrito = this.value;
        if (distrito) {
            loadConcelhos(distrito);
            clearLocationSelection();
        }
    });
    
    // Concelho select change
    document.getElementById('concelho-select').addEventListener('change', function() {
        const concelho = this.value;
        if (concelho) {
            loadFreguesias(concelho);
            clearLocationSelection();
        }
    });
    
    // Freguesia select change
    document.getElementById('freguesia-select').addEventListener('change', function() {
        clearLocationSelection();
    });
    
    // Calculate button click
    document.querySelector('.calculate-button').addEventListener('click', function() {
        const distrito = document.getElementById('distrito-select').value;
        const concelho = document.getElementById('concelho-select').value;
        const freguesia = document.getElementById('freguesia-select').value;
        
        clearLocationSelection();
        
        if (freguesia) {
            fetchLocationByFreguesia(freguesia, concelho);
        } else if (concelho) {
            fetchLocationByConcelho(concelho);
        } else if (distrito) {
            fetchLocationByDistrito(distrito);
        } else {
            alert('Por favor, selecione pelo menos um distrito ou clique no mapa.');
        }
    });
    
    // Map style selector
    document.querySelectorAll('.map-style-option').forEach(function(el) {
        el.addEventListener('click', function() {
            const provider = this.getAttribute('data-provider');
            setMapStyle(provider);
        });
    });
    
    // Mobile panel toggle
    document.getElementById('mobile-menu-toggle').addEventListener('click', function() {
        document.getElementById('overlay-panel').classList.toggle('active');
    });
    
    document.getElementById('mobile-panel-close').addEventListener('click', function() {
        document.getElementById('overlay-panel').classList.remove('active');
    });
    
    // POI category headers toggle
    document.querySelectorAll('.category-header').forEach(function(header) {
        header.addEventListener('click', function() {
            this.parentElement.classList.toggle('collapsed');
            this.querySelector('.dropdown-arrow').textContent = 
                this.parentElement.classList.contains('collapsed') ? '▼' : '▲';
        });
    });
    
    // POI header toggle
    document.getElementById('poi-header').addEventListener('click', function() {
        document.getElementById('poi-content').classList.toggle('collapsed');
        this.querySelector('.dropdown-arrow').textContent = 
            document.getElementById('poi-content').classList.contains('collapsed') ? '▼' : '▲';
    });
    
    // Close location data panel
    document.querySelector('.location-data-panel .close-panel').addEventListener('click', function() {
        document.querySelector('.location-data-panel').classList.remove('active');
    });
} 