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
        
        console.log(`Map clicked at coordinates: ${lat}, ${lng}`);
        
        // Validate coordinates (ensure they're within Portugal's rough bounding box)
        const inPortugal = lat >= 36.8 && lat <= 42.2 && lng >= -9.6 && lng <= -6.1;
        
        if (!inPortugal) {
            console.warn('Coordinates outside Portugal\'s bounding box');
            // We'll still try to fetch data, but warn the user
        }
        
        // Clear previous selection
        clearLocationSelection();
        
        // Add marker at clicked location
        locationMarker = L.marker([lat, lng]).addTo(map);
        
        // Update UI to show loading state
        document.querySelector('.calculate-button').textContent = 'A carregar...';
        document.querySelector('.calculate-button').disabled = true;
        
        // Format coordinates to 6 decimal places for precision
        const formattedLat = parseFloat(lat.toFixed(6));
        const formattedLng = parseFloat(lng.toFixed(6));
        
        // Fetch location data for the clicked coordinates
        fetchLocationByCoordinates(formattedLat, formattedLng);
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
            
            // Sort municipios alphabetically (by name)
            municipios.sort((a, b) => a.nome.localeCompare(b.nome));
            
            // Add options to select
            municipios.forEach(municipio => {
                const option = document.createElement('option');
                option.value = municipio.nome;
                option.textContent = municipio.nome;
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
            
            // Get the freguesias names array and geojsons data from the response
            const freguesiaNames = data.freguesias || [];
            const freguesiaGeojsons = data.geojsons?.freguesias || [];
            
            // Create a map of freguesia names to their respective codes from geojsons
            const freguesiaCodes = {};
            freguesiaGeojsons.forEach(geojson => {
                if (geojson?.properties?.Freguesia && geojson?.properties?.Dicofre) {
                    freguesiaCodes[geojson.properties.Freguesia] = geojson.properties.Dicofre;
                }
            });
            
            // Sort freguesia names alphabetically
            freguesiaNames.sort((a, b) => a.localeCompare(b));
            
            // Add options to select
            freguesiaNames.forEach(freguesiaName => {
                const option = document.createElement('option');
                // Store both code and name - the name is what we'll use for API calls
                option.value = freguesiaName; // Store freguesia name as value for API calls
                option.dataset.code = freguesiaCodes[freguesiaName] || ''; // Store code as data attribute
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
    console.log(`Fetching location data for coordinates: ${lat}, ${lng}`);
    
    // Show loading state
    document.querySelector('.calculate-button').textContent = 'A carregar...';
    document.querySelector('.calculate-button').disabled = true;
    
    fetch(`../includes/fetch_location_data.php?lat=${lat}&lng=${lng}&debug=true`)
        .then(response => {
            console.log('Response status:', response.status);
            return response.json();
        })
        .then(data => {
            console.log('API Response:', data);
            
            if (data.success) {
                currentLocation = data.data;
                console.log('Current Location Data:', currentLocation);
                
                // Check if we have the expected data structure
                if (!currentLocation || !currentLocation.nome) {
                    console.error('Invalid location data structure:', currentLocation);
                    alert('Dados de localização inválidos. Por favor tente novamente.');
                    document.querySelector('.calculate-button').textContent = 'Carregar Dados';
                    document.querySelector('.calculate-button').disabled = false;
                    return;
                }
                
                displayLocationData(currentLocation);
                
                // Show the location data panel
                document.querySelector('.location-data-panel').classList.add('active');
                
                // Update UI to show normal state
                document.querySelector('.calculate-button').textContent = 'Carregar Dados';
                document.querySelector('.calculate-button').disabled = false;
                
                // Check for geometry data
                console.log('Checking for geometry data...');
                console.log('geojson property exists:', !!currentLocation.geojson);
                console.log('geometry property exists:', !!currentLocation.geometry);
                
                // Draw the location boundary if geometry is available
                if (currentLocation.geojson) {
                    console.log('Using geojson property for boundary');
                    console.log('GeoJSON type:', currentLocation.geojson.type);
                    drawLocationBoundary(currentLocation.geojson);
                } else if (currentLocation.geometry) {
                    console.log('Using geometry property for boundary');
                    console.log('Geometry type:', currentLocation.geometry.type);
                    drawLocationBoundary(currentLocation.geometry);
                } else {
                    console.log('No geometry data available for drawing boundary');
                }
            } else {
                console.error('API returned success: false', data.message);
                
                // Display debug information in console
                if (data.debug) {
                    console.log('Debug information:', data.debug);
                    
                    // Check specific error points
                    if (data.debug.coordinates_error) {
                        console.error('Coordinates error:', data.debug.coordinates_error);
                    }
                    if (data.debug.coordinates_response) {
                        console.log('Coordinates response:', data.debug.coordinates_response);
                    }
                    if (data.debug.geometry_error) {
                        console.error('Geometry error:', data.debug.geometry_error);
                    }
                    if (data.debug.coordinates_endpoint) {
                        console.log('Endpoint used:', data.debug.coordinates_endpoint);
                    }
                    if (data.debug.coordinates_http_code) {
                        console.log('HTTP code:', data.debug.coordinates_http_code);
                    }
                }
                
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
    
    // Use the freguesia name for the API call, not the code
    // Arrange parameters to match the API structure: /municipio/{municipio}/freguesia/{freguesia}
    fetch(`../includes/fetch_location_data.php?municipio=${encodeURIComponent(concelho)}&freguesia=${encodeURIComponent(freguesia)}`)
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
 * Fetch location data by municipio
 */
function fetchLocationByMunicipio(municipio) {
    // Update UI to show loading state
    document.querySelector('.calculate-button').textContent = 'A carregar...';
    document.querySelector('.calculate-button').disabled = true;
    
    fetch(`../includes/fetch_location_data.php?municipio=${encodeURIComponent(municipio)}`)
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
                alert('Não foi possível obter dados para este município.');
                document.querySelector('.calculate-button').textContent = 'Carregar Dados';
                document.querySelector('.calculate-button').disabled = false;
            }
        })
        .catch(error => {
            console.error('Error fetching municipio data:', error);
            alert('Ocorreu um erro ao obter os dados do município.');
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
    console.log('Drawing boundary with data:', geojson);
    
    // Remove existing polygon if it exists
    if (locationPolygon) {
        console.log('Removing existing polygon');
        map.removeLayer(locationPolygon);
    }
    
    try {
        // Handle different GeoJSON formats
        let validGeoJSON = geojson;
        
        // If it's a Feature with geometry property
        if (geojson.type === 'Feature' && geojson.geometry) {
            console.log('Processing Feature type GeoJSON');
            validGeoJSON = geojson;
        } 
        // If it's just the geometry object itself
        else if (geojson.type === 'Polygon' || geojson.type === 'MultiPolygon') {
            console.log('Processing direct geometry GeoJSON');
            validGeoJSON = {
                type: 'Feature',
                geometry: geojson,
                properties: {}
            };
        }
        // If it's a FeatureCollection
        else if (geojson.type === 'FeatureCollection' && geojson.features) {
            console.log('Processing FeatureCollection with', geojson.features.length, 'features');
            validGeoJSON = geojson;
        }
        // If it's an unexpected format
        else {
            console.warn('Unexpected GeoJSON format:', geojson.type);
            console.log('Full GeoJSON data:', geojson);
        }
        
        // Create the polygon from GeoJSON
        locationPolygon = L.geoJSON(validGeoJSON, {
            style: {
                color: '#2980b9',
                weight: 3,
                opacity: 0.8,
                fillColor: '#3498db',
                fillOpacity: 0.2
            }
        }).addTo(map);
        
        console.log('Polygon created successfully');
        
        // Zoom to the polygon bounds
        map.fitBounds(locationPolygon.getBounds());
        console.log('Map zoomed to polygon bounds');
    } catch (error) {
        console.error('Error creating boundary polygon:', error);
        console.error('GeoJSON data that caused the error:', JSON.stringify(geojson));
        
        // Try to recover by extracting geometry if possible
        try {
            if (geojson && typeof geojson === 'object') {
                let geometryData = null;
                
                // Try different possible paths to geometry
                if (geojson.geometry && geojson.geometry.coordinates) {
                    console.log('Attempting recovery using .geometry');
                    geometryData = geojson.geometry;
                } else if (geojson.coordinates) {
                    console.log('Attempting recovery using direct coordinates');
                    geometryData = {
                        type: Array.isArray(geojson.coordinates[0][0]) ? 'MultiPolygon' : 'Polygon',
                        coordinates: geojson.coordinates
                    };
                }
                
                if (geometryData) {
                    console.log('Recovery geometry:', geometryData);
                    locationPolygon = L.geoJSON({
                        type: 'Feature',
                        geometry: geometryData,
                        properties: {}
                    }, {
                        style: {
                            color: '#e74c3c',
                            weight: 3,
                            opacity: 0.8,
                            fillColor: '#e74c3c',
                            fillOpacity: 0.2
                        }
                    }).addTo(map);
                    
                    map.fitBounds(locationPolygon.getBounds());
                    console.log('Recovery successful');
                }
            }
        } catch (recoveryError) {
            console.error('Recovery attempt failed:', recoveryError);
        }
    }
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
            fetchLocationByMunicipio(concelho);
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