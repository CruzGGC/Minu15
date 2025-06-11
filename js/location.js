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
    fetch('../location.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: 'action=fetchAllDistritos'
    })
        .then(response => response.json())
        .then(data => {
            const distritoSelect = document.getElementById('distrito-select');
            
            // Ensure data.data is an array before sorting
            const distritos = data.data || [];

            // Sort distritos alphabetically
            distritos.sort();
            
            // Add options to select
            distritos.forEach(distritoName => {
                const option = document.createElement('option');
                option.value = distritoName;
                option.textContent = distritoName;
                distritoSelect.appendChild(option);
            });
        })
        .catch(error => {
            console.error('Error loading distritos:', error);
            alert('Ocorreu um erro ao carregar os distritos.');
        });
}

/**
 * Load concelhos for the selected distrito
 */
function loadConcelhos(distrito) {
    fetch('../location.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: `action=fetchMunicipiosByDistrito&distrito=${encodeURIComponent(distrito)}`
    })
        .then(response => response.json())
        .then(data => {
            const concelhoSelect = document.getElementById('concelho-select');
            
            // Clear previous options
            concelhoSelect.innerHTML = '<option value="">Selecione um concelho...</option>';
            
            // Get the municipios array from the response
            const municipios = data.data?.municipios || [];
            
            // Sort municipios alphabetically (by nome)
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
            alert('Ocorreu um erro ao carregar os concelhos.');
        });
}

/**
 * Load freguesias for the selected concelho
 */
function loadFreguesias(concelho) {
    fetch('../location.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: `action=fetchFreguesiasByMunicipio&municipio=${encodeURIComponent(concelho)}`
    })
        .then(response => response.json())
        .then(data => {
            console.log('Freguesias data received:', data);
            
            const freguesiaSelect = document.getElementById('freguesia-select');
            
            // Clear previous options
            freguesiaSelect.innerHTML = '<option value="">Selecione uma freguesia...</option>';
            
            // Get the freguesias names array from the response, handling the new structure
            let freguesias = data.data?.freguesias || [];
            const freguesiaGeojsons = data.data?.geojsons?.freguesias || [];

            console.log('Original freguesias array:', freguesias);

            // Handle case where freguesias might be objects instead of strings
            if (freguesias.length > 0 && typeof freguesias[0] !== 'string') {
                // Check if freguesias are objects with 'nome' property
                if (freguesias[0] && typeof freguesias[0].nome === 'string') {
                    console.log('Freguesias are objects with nome property');
                    freguesias = freguesias.map(f => f.nome);
                } else {
                    console.log('Freguesias have unexpected format, trying to convert to strings');
                    freguesias = freguesias.map(f => String(f));
                }
            }
            
            console.log('Processed freguesias array:', freguesias);
            
            // Create a map of freguesia names to their respective codes from geojsons
            const freguesiaCodes = {};
            freguesiaGeojsons.forEach(geojson => {
                if (geojson?.properties?.Freguesia && geojson?.properties?.Dicofre) {
                    freguesiaCodes[geojson.properties.Freguesia] = geojson.properties.Dicofre;
                }
            });
            
            // Sort freguesia names alphabetically only if they are strings
            if (freguesias.length > 0 && typeof freguesias[0] === 'string') {
                try {
                    freguesias.sort((a, b) => a.localeCompare(b));
                } catch (error) {
                    console.error('Error sorting freguesia names:', error);
                    console.log('Unable to sort freguesia names, using as-is');
                }
            }
            
            // Add options to select
            freguesias.forEach(freguesiaName => {
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
            alert('Ocorreu um erro ao carregar as freguesias.');
        });
}

/**
 * Fetch location data by coordinates
 */
function fetchLocationByCoordinates(lat, lng) {
    console.log(`Fetching location data for coordinates: ${lat}, ${lng}`);
    
    // Format coordinates to 6 decimal places for precision
    const formattedLat = parseFloat(lat.toFixed(6));
    const formattedLng = parseFloat(lng.toFixed(6));
    
    // Fetch data from the API
    fetch('../location.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: `action=fetchByGps&latitude=${formattedLat}&longitude=${formattedLng}`
    })
        .then(response => {
            if (!response.ok) {
                throw new Error(`HTTP error! Status: ${response.status}`);
            }
            console.log("Response received:", response);
            return response.text().then(text => {
                console.log("Raw response text:", text);
                if (!text || text.trim() === '') {
                    throw new Error('Empty response received');
                }
                try {
                    return JSON.parse(text);
                } catch (e) {
                    console.error("JSON parse error:", e);
                    throw new Error(`JSON parse error: ${e.message}`);
                }
            });
        })
        .then(data => {
            // Reset UI state
            document.querySelector('.calculate-button').textContent = 'Carregar Dados';
            document.querySelector('.calculate-button').disabled = false;
            
            if (data.success && data.data) {
                // Store the current location data
                currentLocation = data.data;
                
                // Update dropdowns to match the selected location
                if (currentLocation.distrito) {
                    const distritoSelect = document.getElementById('distrito-select');
                    distritoSelect.value = currentLocation.distrito;
                    
                    // Load concelhos for this distrito
                    loadConcelhos(currentLocation.distrito);
                    
                    // Wait for concelhos to load, then set the concelho
                    setTimeout(() => {
                        if (currentLocation.concelho) {
                            const concelhoSelect = document.getElementById('concelho-select');
                            concelhoSelect.value = currentLocation.concelho;
                            
                            // Load freguesias for this concelho
                            loadFreguesias(currentLocation.concelho);
                            
                            // Wait for freguesias to load, then set the freguesia
                            setTimeout(() => {
                                if (currentLocation.freguesia) {
                                    const freguesiaSelect = document.getElementById('freguesia-select');
                                    
                                    // Find the option with matching text
                                    const options = Array.from(freguesiaSelect.options);
                                    const option = options.find(opt => opt.textContent === currentLocation.freguesia);
                                    
                                    if (option) {
                                        freguesiaSelect.value = option.value;
                                    }
                                }
                            }, 1000);
                        }
                    }, 1000);
                }
                
                // Draw the location boundary
                if (currentLocation.geometry) {
                    drawLocationBoundary(currentLocation.geometry);
                }
                
                // Display location data
                displayLocationData(currentLocation);
                
                // If we have coordinates, center the map
                if (currentLocation.centroid) {
                    map.setView([currentLocation.centroid.lat, currentLocation.centroid.lng], 12);
                }
            } else {
                // Show error message
                document.getElementById('location-data').innerHTML = `
                    <div class="error-message">
                        <i class="fas fa-exclamation-triangle"></i>
                        <p>Não foi possível obter dados para esta localização.</p>
                        <p class="error-details">${data.message || 'Erro desconhecido'}</p>
                    </div>
                `;
                
                // Show the data panel
                document.querySelector('.location-data-panel').classList.add('visible');
            }
        })
        .catch(error => {
            console.error('Error fetching location data:', error);
            
            // Reset UI state
            document.querySelector('.calculate-button').textContent = 'Carregar Dados';
            document.querySelector('.calculate-button').disabled = false;
            
            // Show error message
            document.getElementById('location-data').innerHTML = `
                <div class="error-message">
                    <i class="fas fa-exclamation-triangle"></i>
                    <p>Erro ao obter dados da localização.</p>
                    <p class="error-details">${error.message}</p>
                </div>
            `;
            
            // Show the data panel
            document.querySelector('.location-data-panel').classList.add('visible');
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
    fetch('../includes/fetch_location_data.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: `action=fetchByFreguesiaAndMunicipio&freguesia=${encodeURIComponent(freguesia)}&municipio=${encodeURIComponent(concelho)}`
    })
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
    
    fetch('../includes/fetch_location_data.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: `action=fetchByMunicipio&municipio=${encodeURIComponent(municipio)}`
    })
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
    
    fetch('../includes/fetch_location_data.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: `action=fetchByDistrito&distrito=${encodeURIComponent(distrito)}`
    })
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

    // Show the data panel
    document.querySelector('.location-data-panel').classList.add('visible');
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
    
    // Add new polygon
    if (geojson && geojson.coordinates) {
        console.log('Adding new polygon');
        locationPolygon = L.geoJSON(geojson, {
            style: function (feature) {
                return {
                    color: '#3498db',
                    weight: 3,
                    opacity: 0.7,
                    fillOpacity: 0.2
                };
            },
            onEachFeature: function (feature, layer) {
                layer.bindPopup(feature.properties.Nome || 'Localização');
            }
        }).addTo(map);
        
        // Fit map to polygon bounds
        map.fitBounds(locationPolygon.getBounds());
    } else {
        console.warn('No valid GeoJSON coordinates to draw boundary.', geojson);
    }
}

/**
 * Clear any previous location selection (marker, polygon, dropdowns)
 */
function clearLocationSelection() {
    // Remove marker
    if (locationMarker) {
        map.removeLayer(locationMarker);
        locationMarker = null;
    }
    
    // Remove polygon
    if (locationPolygon) {
        map.removeLayer(locationPolygon);
        locationPolygon = null;
    }
    
    // Reset dropdowns
    document.getElementById('distrito-select').value = '';
    document.getElementById('concelho-select').value = '';
    document.getElementById('concelho-select').disabled = true;
    document.getElementById('freguesia-select').value = '';
    document.getElementById('freguesia-select').disabled = true;
    
    // Clear location data panel
    document.getElementById('location-data').innerHTML = '<p>Selecione uma localização para ver os dados</p>';
    document.querySelector('.location-data-panel').classList.remove('visible');
}

/**
 * Setup all event listeners for UI elements
 */
function setupEventListeners() {
    // Mobile menu toggle
    document.getElementById('mobile-menu-toggle').addEventListener('click', function() {
        document.getElementById('overlay-panel').classList.toggle('active');
    });

    // Mobile panel close
    document.getElementById('mobile-panel-close').addEventListener('click', function() {
        document.getElementById('overlay-panel').classList.remove('active');
    });

    // POI header dropdown
    document.getElementById('poi-header').addEventListener('click', function() {
        const poiContent = document.getElementById('poi-content');
        const dropdownArrow = this.querySelector('.dropdown-arrow');
        if (poiContent.style.display === 'none' || poiContent.style.display === '') {
            poiContent.style.display = 'block';
            dropdownArrow.textContent = '▼';
        } else {
            poiContent.style.display = 'none';
            dropdownArrow.textContent = '►';
        }
    });
    
    // POI category dropdowns
    document.querySelectorAll('.poi-category .category-header').forEach(header => {
        header.addEventListener('click', function() {
            const categoryContent = this.nextElementSibling;
            const dropdownArrow = this.querySelector('.dropdown-arrow');
            if (categoryContent.style.display === 'none' || categoryContent.style.display === '') {
                categoryContent.style.display = 'block';
                dropdownArrow.textContent = '▼';
            } else {
                categoryContent.style.display = 'none';
                dropdownArrow.textContent = '►';
            }
        });
    });

    // Map style options
    document.querySelectorAll('.map-style-option').forEach(option => {
        option.addEventListener('click', function() {
            const provider = this.dataset.provider;
            setMapStyle(provider);
        });
    });
    
    // District select change event
    document.getElementById('distrito-select').addEventListener('change', function() {
        const selectedDistrito = this.value;
        clearLocationSelection(); // Clear existing map features and data
        if (selectedDistrito) {
            loadConcelhos(selectedDistrito);
            fetchLocationByDistrito(selectedDistrito); // Fetch and display data for the selected distrito
        } else {
            // Reset concelho and freguesia dropdowns if no distrito is selected
            document.getElementById('concelho-select').disabled = true;
            document.getElementById('concelho-select').innerHTML = '<option value="">Selecione um concelho...</option>';
            document.getElementById('freguesia-select').disabled = true;
            document.getElementById('freguesia-select').innerHTML = '<option value="">Selecione uma freguesia...</option>';
        }
    });

    // Concelho select change event
    document.getElementById('concelho-select').addEventListener('change', function() {
        const selectedConcelho = this.value;
        const selectedDistrito = document.getElementById('distrito-select').value;
        clearLocationSelection(); // Clear existing map features and data
        if (selectedConcelho) {
            loadFreguesias(selectedConcelho);
            fetchLocationByMunicipio(selectedConcelho); // Fetch and display data for the selected concelho
        } else if (selectedDistrito) {
            // If concelho is deselected but distrito is still selected, load distrito data
            fetchLocationByDistrito(selectedDistrito);
        } else {
            // Reset freguesia dropdown if no concelho is selected
            document.getElementById('freguesia-select').disabled = true;
            document.getElementById('freguesia-select').innerHTML = '<option value="">Selecione uma freguesia...</option>';
        }
    });

    // Freguesia select change event
    document.getElementById('freguesia-select').addEventListener('change', function() {
        const selectedFreguesia = this.value;
        const selectedConcelho = document.getElementById('concelho-select').value;
        clearLocationSelection(); // Clear existing map features and data
        if (selectedFreguesia) {
            // Pass both freguesia and concelho to fetch data
            fetchLocationByFreguesia(selectedFreguesia, selectedConcelho);
        } else if (selectedConcelho) {
            // If freguesia is deselected but concelho is still selected, load concelho data
            fetchLocationByMunicipio(selectedConcelho);
        }
    });
    
    // Calculate button event
    document.querySelector('.calculate-button').addEventListener('click', function() {
        // This button is primarily for triggering map click actions or re-fetching based on dropdowns
        // The primary fetch logic is now tied to dropdown changes or map clicks.
        // If no location is currently selected, prompt the user or do nothing.
        if (!currentLocation) {
            alert('Selecione uma localização no mapa ou através dos menus suspensos.');
            return;
        }

        // If a location is selected, re-display its data (useful if POI checkboxes changed)
        displayLocationData(currentLocation);

        // Re-draw boundary if exists and current location has geometry
        if (currentLocation.geometry) {
            drawLocationBoundary(currentLocation.geometry);
        } else if (currentLocation.geojson) {
            drawLocationBoundary(currentLocation.geojson);
        }

        // Show the panel (ensure it's visible after calculations)
        document.querySelector('.location-data-panel').classList.add('visible');
    });

    // Close location data panel
    document.querySelector('.location-data-panel .close-panel').addEventListener('click', function() {
        document.querySelector('.location-data-panel').classList.remove('visible');
    });
} 