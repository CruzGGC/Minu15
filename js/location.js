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
let freguesiaPolygons = []; // New array to store individual freguesia boundaries
let currentLocation = null;
let currentClickedCoordinates;
let genderChart = null;
let censusSidebarActive = false;
let currentCensusYear = 2021; // Default to 2021
let showFreguesias = false; // Track whether to show freguesias

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

// POI Icons configuration
const poiIcons = {
    hospitals: { icon: 'hospital', color: '#e74c3c' },
    health_centers: { icon: 'clinic-medical', color: '#e67e22' },
    pharmacies: { icon: 'prescription-bottle-alt', color: '#27ae60' },
    dentists: { icon: 'tooth', color: '#3498db' },
    
    schools: { icon: 'school', color: '#3498db' },
    universities: { icon: 'graduation-cap', color: '#9b59b6' },
    kindergartens: { icon: 'baby', color: '#f1c40f' },
    libraries: { icon: 'book', color: '#34495e' },
    
    supermarkets: { icon: 'shopping-basket', color: '#27ae60' },
    malls: { icon: 'shopping-bag', color: '#e67e22' },
    restaurants: { icon: 'utensils', color: '#e74c3c' },
    atms: { icon: 'money-bill-wave', color: '#2ecc71' },
    
    parks: { icon: 'tree', color: '#27ae60' },
    sports: { icon: 'dumbbell', color: '#3498db' },
    bus_stops: { icon: 'bus', color: '#f39c12' },
    police_stations: { icon: 'shield-alt', color: '#34495e' }
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
            // We'll still proceed but warn the user
            alert('As coordenadas selecionadas parecem estar fora de Portugal. Os dados podem não estar disponíveis.');
        }
        
        // Clear previous selection
        clearLocationSelection();
        
        // Store the clicked coordinates
        currentClickedCoordinates = {
            lat: parseFloat(lat.toFixed(6)),
            lng: parseFloat(lng.toFixed(6))
        };
        
        // Add marker at clicked location
        locationMarker = L.marker([lat, lng]).addTo(map);
        
        // Focus the Carregar Dados button to indicate the next step
        document.querySelector('.calculate-button').focus();
        document.querySelector('.calculate-button').classList.add('highlight');
        
        // Remove highlight after 2 seconds
        setTimeout(() => {
            document.querySelector('.calculate-button').classList.remove('highlight');
        }, 2000);
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
    console.log('Loading distritos...');
    
    fetch('location.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: 'action=fetchAllDistritos'
    })
        .then(response => {
            if (!response.ok) {
                throw new Error(`HTTP error! Status: ${response.status}`);
            }
            return response.json();
        })
        .then(data => {
            console.log('Distritos data received:', data);
            const distritoSelect = document.getElementById('distrito-select');
            
            // Ensure data is properly structured
            const distritos = data.data || [];
            
            // Check if distritos is an array of objects with distrito property
            let distritoNames = [];
            
            if (distritos.length > 0 && typeof distritos[0] === 'object' && distritos[0].distrito) {
                // Extract distrito names from objects
                distritoNames = distritos.map(d => d.distrito);
            } else if (Array.isArray(distritos)) {
                // If it's just an array of strings, use as is
                distritoNames = distritos;
            }
            
            // Sort distrito names alphabetically
            distritoNames.sort((a, b) => a.localeCompare(b));
            
            // Add options to select
            distritoNames.forEach(distritoName => {
                const option = document.createElement('option');
                option.value = distritoName;
                option.textContent = distritoName;
                distritoSelect.appendChild(option);
            });
        })
        .catch(error => {
            console.error('Error loading distritos:', error);
            // Display error in the UI
            const distritoSelect = document.getElementById('distrito-select');
            distritoSelect.innerHTML = '<option value="">Erro ao carregar distritos</option>';
        });
}

/**
 * Load concelhos for the selected distrito
 */
function loadConcelhos(distrito) {
    fetch('location.php', {
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
    fetch('location.php', {
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
    
    // Fetch data from the API
    fetch('location.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: `action=fetchByGps&latitude=${lat}&longitude=${lng}`
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
function fetchLocationByFreguesia(freguesia, municipio) {
    console.log(`Fetching freguesia data: ${freguesia}, ${municipio}`);
    
    // Update UI to show loading state
    document.querySelector('.calculate-button').textContent = 'A carregar...';
    document.querySelector('.calculate-button').disabled = true;
    
    fetch('location.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: `action=fetchByFreguesiaAndMunicipio&freguesia=${encodeURIComponent(freguesia)}&municipio=${encodeURIComponent(municipio)}`
    })
        .then(response => response.json())
        .then(data => {
            // Reset UI state
            document.querySelector('.calculate-button').textContent = 'Carregar Dados';
            document.querySelector('.calculate-button').disabled = false;
            
            if (data.success) {
                currentLocation = data.data;
                displayLocationData(currentLocation);
                
                // Show the location data panel
                document.querySelector('.location-data-panel').classList.add('visible');
                
                // Log the data structure to help debug
                console.log('Freguesia data structure:', {
                    hasGeojson: !!currentLocation.geojson,
                    hasGeojsons: !!currentLocation.geojsons,
                    hasFreguesias: currentLocation.geojsons && !!currentLocation.geojsons.freguesias,
                    freguesiasLength: currentLocation.geojsons && currentLocation.geojsons.freguesias ? currentLocation.geojsons.freguesias.length : 0,
                    hasFreguesia: currentLocation.geojsons && !!currentLocation.geojsons.freguesia
                });
                
                // Draw the location boundary if geometry is available
                if (currentLocation.geojsons) {
                    // Pass the entire location object to preserve the geojsons structure
                    drawLocationBoundary(currentLocation);
                } else if (currentLocation.geojson) {
                    drawLocationBoundary(currentLocation.geojson);
                } else if (currentLocation.geometry) {
                    drawLocationBoundary(currentLocation.geometry);
                }
            } else {
                alert('Não foi possível obter dados para esta freguesia.');
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
    console.log(`Fetching municipio data: ${municipio}`);
    
    // Update UI to show loading state
    document.querySelector('.calculate-button').textContent = 'A carregar...';
    document.querySelector('.calculate-button').disabled = true;
    
    fetch('location.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: `action=fetchByMunicipio&municipio=${encodeURIComponent(municipio)}`
    })
        .then(response => response.json())
        .then(data => {
            // Reset UI state
            document.querySelector('.calculate-button').textContent = 'Carregar Dados';
            document.querySelector('.calculate-button').disabled = false;
            
            if (data.success) {
                currentLocation = data.data;
                displayLocationData(currentLocation);
                
                // Show the location data panel
                document.querySelector('.location-data-panel').classList.add('visible');
                
                // Log the data structure to help debug
                console.log('Municipality data structure:', {
                    hasGeojson: !!currentLocation.geojson,
                    hasGeojsons: !!currentLocation.geojsons,
                    hasFreguesias: currentLocation.geojsons && !!currentLocation.geojsons.freguesias,
                    freguesiasLength: currentLocation.geojsons && currentLocation.geojsons.freguesias ? currentLocation.geojsons.freguesias.length : 0,
                    hasMunicipio: currentLocation.geojsons && !!currentLocation.geojsons.municipio
                });
                
                // Draw the location boundary if geometry is available
                if (currentLocation.geojsons) {
                    // Pass the entire location object to preserve the geojsons structure
                    drawLocationBoundary(currentLocation);
                } else if (currentLocation.geojson) {
                    drawLocationBoundary(currentLocation.geojson);
                } else if (currentLocation.geometry) {
                    drawLocationBoundary(currentLocation.geometry);
                }
            } else {
                alert('Não foi possível obter dados para este município.');
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
    console.log(`Fetching distrito data: ${distrito}`);
    
    // Update UI to show loading state
    document.querySelector('.calculate-button').textContent = 'A carregar...';
    document.querySelector('.calculate-button').disabled = true;
    
    fetch('location.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: `action=fetchByDistrito&distrito=${encodeURIComponent(distrito)}`
    })
        .then(response => response.json())
        .then(data => {
            // Reset UI state
            document.querySelector('.calculate-button').textContent = 'Carregar Dados';
            document.querySelector('.calculate-button').disabled = false;
            
            if (data.success) {
                currentLocation = data.data;
                displayLocationData(currentLocation);
                
                // Show the location data panel
                document.querySelector('.location-data-panel').classList.add('visible');
                
                // Log the data structure to help debug
                console.log('Distrito data structure:', {
                    hasGeojson: !!currentLocation.geojson,
                    hasGeojsons: !!currentLocation.geojsons,
                    hasDistrito: currentLocation.geojsons && !!currentLocation.geojsons.distrito
                });
                
                // Draw the location boundary if geometry is available
                if (currentLocation.geojsons) {
                    // Pass the entire location object to preserve the geojsons structure
                    drawLocationBoundary(currentLocation);
                } else if (currentLocation.geojson) {
                    drawLocationBoundary(currentLocation.geojson);
                } else if (currentLocation.geometry) {
                    drawLocationBoundary(currentLocation.geometry);
                }
            } else {
                alert('Não foi possível obter dados para este distrito.');
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
 * Display location data in the right panel
 */
function displayLocationData(location) {
    console.log('Displaying location data:', location);
    currentLocation = location;
    
    // Ensure old panel is hidden
    document.querySelector('.location-data-panel').classList.remove('visible');
    
    // Show the census sidebar with location data
    showCensusSidebar(location);
}

/**
 * Show the census sidebar with smooth animations
 */
function showCensusSidebar(location) {
    if (!location) return;
    
    console.log('Showing census sidebar with location data:', location);
    
    // Set location name and type
    const locationName = document.getElementById('census-location-name');
    const locationType = document.getElementById('census-location-type');
    
    // Determine location name and type
    let name = '';
    let type = '';
    
    if (location.freguesia) {
        name = location.freguesia;
        type = 'Freguesia';
    if (location.municipio || location.concelho) {
            type += ' de ' + (location.municipio || location.concelho);
    }
    } else if (location.municipio || location.concelho) {
        name = location.municipio || location.concelho;
        type = 'Concelho';
    if (location.distrito) {
            type += ' de ' + location.distrito;
        }
    } else if (location.distrito) {
        name = location.distrito;
        type = 'Distrito';
    } else if (location.nome) {
        name = location.nome;
        type = location.tipo || 'Localidade';
    } else {
        name = 'Localização';
        type = 'Coordenadas GPS';
    }
    
    locationName.textContent = name;
    locationType.textContent = type;
    
    // Get census data - check both direct and nested locations
    let census2021 = location.censos2021 || null;
    let census2011 = location.censos2011 || null;
    
    // If census data is not directly on the location object, check nested objects
    if (!census2021) {
        if (location.detalhesFreguesia && location.detalhesFreguesia.censos2021) {
            census2021 = location.detalhesFreguesia.censos2021;
            console.log('Found census 2021 data in detalhesFreguesia');
        } else if (location.detalhesMunicipio && location.detalhesMunicipio.censos2021) {
            census2021 = location.detalhesMunicipio.censos2021;
            console.log('Found census 2021 data in detalhesMunicipio');
        }
    }
    
    if (!census2011) {
        if (location.detalhesFreguesia && location.detalhesFreguesia.censos2011) {
            census2011 = location.detalhesFreguesia.censos2011;
            console.log('Found census 2011 data in detalhesFreguesia');
        } else if (location.detalhesMunicipio && location.detalhesMunicipio.censos2011) {
            census2011 = location.detalhesMunicipio.censos2011;
            console.log('Found census 2011 data in detalhesMunicipio');
        }
    }
    
    // If no census data, show message
    if (!census2021 && !census2011) {
        console.warn('No census data found for this location');
        const ageContainer = document.getElementById('age-bars');
        if (ageContainer) {
            ageContainer.innerHTML = '<p class="no-data">Não existem dados censitários disponíveis para esta localização.</p>';
        }
        
        // Show sidebar with empty data
        document.getElementById('census-sidebar').classList.add('active');
        censusSidebarActive = true;
        return;
    }
    
    // Use 2021 data if available, otherwise use 2011
    const primaryCensus = census2021 || census2011;
    const secondaryCensus = census2021 && census2011 ? census2011 : null;
    
    console.log('Using census data:', { primary: primaryCensus, secondary: secondaryCensus });
    
    // Update toggle visibility
    const yearToggle = document.getElementById('census-year-toggle');
    if (census2021 && census2011) {
        // Both census years available, show toggle
        yearToggle.parentElement.parentElement.style.display = 'flex';
        yearToggle.checked = census2021 ? true : false; // Default to 2021 if available
    } else {
        // Only one census year available, hide toggle
        yearToggle.parentElement.parentElement.style.display = 'none';
    }
    
    // Update year in the current state
    currentCensusYear = census2021 ? 2021 : 2011;
    
    // Update main stats
    updateCensusStats(primaryCensus, secondaryCensus);
    
    // Create/update mini charts
    createMiniCharts(primaryCensus, secondaryCensus);
    
    // Create age distribution bars
    createAgeBars(primaryCensus);
    
    // Set "View Full Data" link
    const viewFullData = document.getElementById('census-view-full-data');
    if (viewFullData) {
        viewFullData.href = buildFullDataUrl(location);
    }
    
    // Show sidebar with animation
    document.getElementById('census-sidebar').classList.add('active');
    censusSidebarActive = true;
}

/**
 * Update census statistics based on the primary and secondary census data
 */
function updateCensusStats(primaryCensus, secondaryCensus) {
    // Population
    const populationValue = document.getElementById('population-value');
    const populationChange = document.getElementById('population-change');
        
    // Get population value
    const population = getCensusValue(primaryCensus, ['N_INDIVIDUOS_RESIDENT', 'N_INDIVIDUOS']);
    
    if (population) {
        // Format with thousands separator
        populationValue.textContent = new Intl.NumberFormat('pt-PT').format(population);
        
        // Calculate change if both census data available
        if (secondaryCensus) {
            const oldPopulation = getCensusValue(secondaryCensus, ['N_INDIVIDUOS_RESIDENT', 'N_INDIVIDUOS']);
            
            if (oldPopulation && oldPopulation > 0) {
                const changePercent = ((population - oldPopulation) / oldPopulation) * 100;
                const changeSign = changePercent > 0 ? '+' : '';
                const changeIcon = changePercent > 0 ? 'arrow-up' : (changePercent < 0 ? 'arrow-down' : 'minus');
                const changeClass = changePercent > 0 ? 'positive' : (changePercent < 0 ? 'negative' : '');
                
                populationChange.innerHTML = `<i class="fas fa-${changeIcon}"></i> ${changeSign}${Math.abs(changePercent).toFixed(1)}%`;
                populationChange.className = `stat-change ${changeClass}`;
                populationChange.style.display = 'flex';
            } else {
                populationChange.style.display = 'none';
            }
        } else {
            populationChange.style.display = 'none';
        }
    } else {
        populationValue.textContent = 'N/A';
        populationChange.style.display = 'none';
    }
    
    // Buildings
    const buildingsValue = document.getElementById('buildings-value');
    const buildings = getCensusValue(primaryCensus, ['N_EDIFICIOS_CLASSICOS', 'N_EDIFICIOS']);
    
    if (buildings) {
        buildingsValue.textContent = new Intl.NumberFormat('pt-PT').format(buildings);
    } else {
        buildingsValue.textContent = 'N/A';
    }
        
    // Dwellings
    const dwellingsValue = document.getElementById('dwellings-value');
    const dwellings = getCensusValue(primaryCensus, ['N_ALOJAMENTOS_TOTAL', 'N_ALOJAMENTOS']);
    
    if (dwellings) {
        dwellingsValue.textContent = new Intl.NumberFormat('pt-PT').format(dwellings);
    } else {
        dwellingsValue.textContent = 'N/A';
    }
    
    // Population density
    const densityValue = document.getElementById('density-value');
    let density = null;
    
    // Check for area in multiple possible locations
    let areaHa = null;
    
    // Debug area fields in location
    console.log('Location data for density calculation:', {
        location: currentLocation
    });
    
    // Check for area in multiple possible locations
    if (currentLocation) {
        // Direct properties
        areaHa = currentLocation.area_ha || currentLocation.areaha || currentLocation.area;
        
        // Nested in detalhesFreguesia
        if (!areaHa && currentLocation.detalhesFreguesia) {
            areaHa = currentLocation.detalhesFreguesia.areaha || 
                    currentLocation.detalhesFreguesia.area_ha ||
                    currentLocation.detalhesFreguesia.area;
        }
        
        // Nested in detalhesMunicipio
        if (!areaHa && currentLocation.detalhesMunicipio) {
            areaHa = currentLocation.detalhesMunicipio.areaha || 
                    currentLocation.detalhesMunicipio.area_ha ||
                    currentLocation.detalhesMunicipio.area;
        }
        
        // Try to parse if it's a string
        if (typeof areaHa === 'string') {
            areaHa = parseFloat(areaHa);
        }
    }
    
    console.log(`Found area: ${areaHa} ha`);
    
    if (population && areaHa) {
        const areaKm2 = areaHa / 100;
        density = Math.round(population / areaKm2);
        console.log(`Calculated density: ${density} from population ${population} and area ${areaHa} ha (${areaKm2} km²)`);
    } else if (population) {
        // Fallback density estimate
        density = Math.round(population / 10);
        console.log(`Using fallback density: ${density} based on population ${population}`);
    }
    
    if (density) {
        densityValue.textContent = `${new Intl.NumberFormat('pt-PT').format(density)} h/km²`;
    } else {
        densityValue.textContent = 'N/A';
    }
}

/**
 * Create mini charts for gender and age distribution
 */
function createMiniCharts(primaryCensus, secondaryCensus) {
    // Always load Chart.js first, then create charts
    loadChartJS(() => {
        createGenderChart(primaryCensus);
        calculateAverageAge(primaryCensus);
    });
}

/**
 * Load Chart.js dynamically
 */
function loadChartJS(callback) {
    // Check if Chart.js is already loaded
    if (typeof Chart !== 'undefined') {
        callback();
        return;
    }
    
    // Create script element
    const script = document.createElement('script');
    script.src = 'https://cdn.jsdelivr.net/npm/chart.js@3.9.1/dist/chart.min.js';
    script.onload = callback;
    document.head.appendChild(script);
}

/**
 * Create gender distribution pie chart
 */
function createGenderChart(census) {
    const males = getCensusValue(census, ['N_INDIVIDUOS_H']);
    const females = getCensusValue(census, ['N_INDIVIDUOS_M']);
    
    if (!males || !females) {
        document.getElementById('gender-chart').innerHTML = '<div class="no-data">Sem dados</div>';
        return;
    }
    
    // Get the container element
    const container = document.getElementById('gender-chart');
    
    // Clear previous content
    container.innerHTML = '';
    
    // Create a new canvas element
    const canvas = document.createElement('canvas');
    canvas.width = 100;
    canvas.height = 100;
    container.appendChild(canvas);
    
    // Destroy existing chart if it exists
    if (genderChart) {
        genderChart.destroy();
    }
    
    // Create new chart
    genderChart = new Chart(canvas, {
        type: 'pie',
        data: {
            labels: ['Homens', 'Mulheres'],
            datasets: [{
                data: [males, females],
                backgroundColor: ['#3498db', '#e74c3c'],
                borderWidth: 0
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            animation: {
                animateRotate: true,
                animateScale: true,
                duration: 1000,
                easing: 'easeOutQuart'
            },
            plugins: {
                legend: {
                    display: false
                },
                tooltip: {
                    callbacks: {
                        label: function(context) {
                            const total = males + females;
                            const percent = Math.round((context.raw / total) * 100);
                            return `${context.label}: ${new Intl.NumberFormat('pt-PT').format(context.raw)} (${percent}%)`;
                        }
                    }
                }
            }
        }
    });
}

/**
 * Calculate and display average age
 */
function calculateAverageAge(census) {
    const ageElement = document.getElementById('average-age-value');
    
    // Get age group data
    const age0_14 = getCensusValue(census, ['N_INDIVIDUOS_0_14', 'N_INDIVIDUOS_RESIDENT_0A14']) || 0;
    const age15_24 = getCensusValue(census, ['N_INDIVIDUOS_15_24', 'N_INDIVIDUOS_RESIDENT_15A24']) || 0;
    const age25_64 = getCensusValue(census, ['N_INDIVIDUOS_25_64', 'N_INDIVIDUOS_RESIDENT_25A64']) || 0;
    const age65plus = getCensusValue(census, ['N_INDIVIDUOS_65_OU_MAIS', 'N_INDIVIDUOS_RESIDENT_65']) || 0;
    
    // Calculate average age (using midpoints of age ranges)
    const totalPeople = age0_14 + age15_24 + age25_64 + age65plus;
    
    if (totalPeople > 0) {
        // Use approximate midpoints for each age group
        const avgAge = (
            (age0_14 * 7) +         // midpoint of 0-14 is 7
            (age15_24 * 19.5) +     // midpoint of 15-24 is 19.5
            (age25_64 * 44.5) +     // midpoint of 25-64 is 44.5
            (age65plus * 75)        // approximate midpoint for 65+ (conservative estimate)
        ) / totalPeople;
        
        // Display with one decimal place
        ageElement.textContent = avgAge.toFixed(1).replace('.', ',');
    } else {
        ageElement.textContent = 'N/A';
    }
}

/**
 * Create age distribution bars
 */
function createAgeBars(census) {
    const ageContainer = document.getElementById('age-bars');
    ageContainer.innerHTML = ''; // Clear previous bars
    
    // Define age groups
    const ageGroups = [
        { label: '0-14 anos', keys: ['N_INDIVIDUOS_0_14', 'N_INDIVIDUOS_RESIDENT_0A14'], color: '#3498db' },
        { label: '15-24 anos', keys: ['N_INDIVIDUOS_15_24', 'N_INDIVIDUOS_RESIDENT_15A24'], color: '#2ecc71' },
        { label: '25-64 anos', keys: ['N_INDIVIDUOS_25_64', 'N_INDIVIDUOS_RESIDENT_25A64'], color: '#f39c12' },
        { label: '65+ anos', keys: ['N_INDIVIDUOS_65_OU_MAIS', 'N_INDIVIDUOS_RESIDENT_65'], color: '#9b59b6' }
    ];
    
    // Get total population
    const population = getCensusValue(census, ['N_INDIVIDUOS_RESIDENT', 'N_INDIVIDUOS']);
    
    if (!population) {
        ageContainer.innerHTML = '<p class="no-data">Não existem dados de distribuição etária disponíveis.</p>';
        return;
    }
    
    // Create bars for each age group
    ageGroups.forEach((group, index) => {
        const value = getCensusValue(census, group.keys);
        
        if (value) {
            const percent = (value / population) * 100;
            const barGroup = document.createElement('div');
            barGroup.className = 'age-bar-group';
            barGroup.style.setProperty('--item-index', index);
            
            barGroup.innerHTML = `
                <div class="age-bar-label">
                    <span class="age-bar-label-text">${group.label}</span>
                    <span class="age-bar-value">${new Intl.NumberFormat('pt-PT').format(value)} (${percent.toFixed(1)}%)</span>
            </div>
                <div class="age-bar-container">
                    <div class="age-bar-fill" style="--width: ${percent}%; background: linear-gradient(to right, ${group.color}, ${adjustColor(group.color, -20)});"></div>
        </div>
    `;
    
            ageContainer.appendChild(barGroup);
        }
    });
    
    if (ageContainer.children.length === 0) {
        ageContainer.innerHTML = '<p class="no-data">Não existem dados de distribuição etária disponíveis.</p>';
    }
}

/**
 * Adjust color lightness
 */
function adjustColor(color, amount) {
    return color; // Simplified for now
}

/**
 * Build URL for the full data page
 */
function buildFullDataUrl(location) {
    let url = 'location_data.php?';
    
    if (location.freguesia && (location.municipio || location.concelho)) {
        url += `type=freguesia&id=${encodeURIComponent(location.freguesia)}&municipio=${encodeURIComponent(location.municipio || location.concelho)}`;
    } else if (location.municipio || location.concelho) {
        url += `type=municipio&id=${encodeURIComponent(location.municipio || location.concelho)}`;
    } else if (location.distrito) {
        url += `type=distrito&id=${encodeURIComponent(location.distrito)}`;
    } else if (currentClickedCoordinates) {
        url += `type=gps&id=${currentClickedCoordinates.lat},${currentClickedCoordinates.lng}`;
    }
    
    return url;
}

/**
 * Função auxiliar para extrair valores do censo de forma segura
 */
function getCensusValue(censusData, possibleKeys) {
    if (!censusData) return null;
    
    for (const key of possibleKeys) {
        if (censusData[key] !== undefined) {
            return censusData[key];
        }
    }
    
    return null;
}

/**
 * Draw the location boundary on the map
 */
function drawLocationBoundary(geojson) {
    console.log('Drawing boundary with data:', geojson);
    
    // Remove existing polygons
    clearBoundaries();
    
    // Check if we have valid GeoJSON data
    if (!geojson) {
        console.warn('No GeoJSON data provided');
        return;
    }
    
    try {
        // If geojson is a string, try to parse it
        if (typeof geojson === 'string') {
            try {
                geojson = JSON.parse(geojson);
            } catch (e) {
                console.error('Error parsing GeoJSON string:', e);
                return;
            }
        }
        
        // Store the original data to handle freguesia toggle
        const originalData = JSON.parse(JSON.stringify(geojson));
        
        // Log the structure of the data to help debug
        console.log('GeoJSON structure:', {
            hasGeojsons: !!geojson.geojsons,
            hasFreguesias: geojson.geojsons && !!geojson.geojsons.freguesias,
            freguesiasLength: geojson.geojsons && geojson.geojsons.freguesias ? geojson.geojsons.freguesias.length : 0,
            hasMunicipio: geojson.geojsons && !!geojson.geojsons.municipio,
            showFreguesias: showFreguesias
        });
        
        // Handle the case where we have a geojsons object with multiple geometries
        if (geojson.geojsons) {
            // Check if we have freguesias and the toggle is on
            if (showFreguesias && geojson.geojsons.freguesias && geojson.geojsons.freguesias.length > 0) {
                console.log('Drawing individual freguesias boundaries, count:', geojson.geojsons.freguesias.length);
                
                // Draw each freguesia as a separate layer
                geojson.geojsons.freguesias.forEach((freguesiaGeoJson, index) => {
                    console.log(`Drawing freguesia ${index}:`, freguesiaGeoJson.properties ? freguesiaGeoJson.properties.Freguesia || freguesiaGeoJson.properties.freguesia : 'Unknown');
                    
                    const freguesiaLayer = L.geoJSON(freguesiaGeoJson, {
                        style: function () {
                            return {
                                color: '#2ecc71', // Green for freguesias
                                weight: 2,
                                opacity: 0.7,
                                fillOpacity: 0.2,
                                className: 'freguesia-boundary'
                            };
                        },
                        onEachFeature: function (feature, layer) {
                            let name = 'Freguesia';
                            
                            // Try to extract name from properties
                            if (feature.properties) {
                                name = feature.properties.Freguesia || 
                                       feature.properties.freguesia || 
                                       feature.properties.FREGUESIA || 
                                       feature.properties.Nome || 
                                       feature.properties.nome || 
                                       feature.properties.NOME || 
                                       name;
                            }
                            
                            layer.bindPopup(name);
                        }
                    }).addTo(map);
                    
                    freguesiaPolygons.push(freguesiaLayer);
                });
                
                // Try to fit map to all freguesias bounds
                if (freguesiaPolygons.length > 0) {
                    const bounds = freguesiaPolygons[0].getBounds();
                    for (let i = 1; i < freguesiaPolygons.length; i++) {
                        bounds.extend(freguesiaPolygons[i].getBounds());
                    }
                    map.fitBounds(bounds);
                }
                
                // Optionally draw the município boundary as well, with different style
                if (geojson.geojsons.municipio) {
                    locationPolygon = L.geoJSON(geojson.geojsons.municipio, {
                        style: function () {
                            return {
                                color: '#3498db', // Blue for município
                                weight: 3,
                                opacity: 0.5,
                                fillOpacity: 0.05,
                                className: 'concelho-boundary'
                            };
                        },
                        onEachFeature: function (feature, layer) {
                            let name = 'Município';
                            
                            // Try to extract name from properties
                            if (feature.properties) {
                                name = feature.properties.Concelho || 
                                       feature.properties.concelho || 
                                       feature.properties.Municipio || 
                                       feature.properties.municipio || 
                                       feature.properties.Nome || 
                                       feature.properties.nome || 
                                       name;
                            }
                            
                            layer.bindPopup(name);
                        }
                    }).addTo(map);
                }
                
                console.log('Finished drawing freguesias');
                return; // Skip the rest of the function
            }
            
            // If we're not showing freguesias or don't have freguesia data, show the município
            if (geojson.geojsons.municipio) {
                console.log('Using municipio geometry');
                geojson = geojson.geojsons.municipio;
            }
            // For freguesias, use the freguesia geometry
            else if (geojson.geojsons.freguesia) {
                console.log('Using freguesia geometry');
                geojson = geojson.geojsons.freguesia;
            }
            // If we have a freguesias array but aren't showing them all, use the first one
            else if (geojson.geojsons.freguesias && geojson.geojsons.freguesias.length > 0) {
                console.log('Using first freguesia from freguesias array');
                geojson = geojson.geojsons.freguesias[0];
            }
        }
        
        // Normalize GeoJSON object if needed
        if (!geojson.type && geojson.coordinates) {
            // If it's missing the type but has coordinates, assume it's a Polygon or MultiPolygon
            geojson = {
                type: Array.isArray(geojson.coordinates[0][0][0]) ? 'MultiPolygon' : 'Polygon',
                coordinates: geojson.coordinates
            };
        }
        
        // Create a proper GeoJSON feature if needed
        if (geojson.coordinates && !geojson.features) {
            if (!geojson.type || (geojson.type !== 'Feature' && !geojson.geometry)) {
                geojson = {
                    type: 'Feature',
                    properties: {},
                    geometry: {
                        type: Array.isArray(geojson.coordinates[0][0][0]) ? 'MultiPolygon' : 'Polygon',
                        coordinates: geojson.coordinates
                    }
                };
            }
        }
        
        // For feature collections, ensure they have at least one feature
        if (geojson.type === 'FeatureCollection' && (!geojson.features || geojson.features.length === 0)) {
            console.warn('Empty GeoJSON FeatureCollection');
            return;
        }
        
        // Add new polygon
        locationPolygon = L.geoJSON(geojson, {
            style: function (feature) {
                // Determine the style based on the type of boundary
                let style = {
                    weight: 3,
                    opacity: 0.7,
                    fillOpacity: 0.2
                };
                
                // Try to determine the boundary type from properties
                if (feature.properties) {
                    if (feature.properties.Freguesia || feature.properties.freguesia) {
                        // Freguesia style
                        style.color = '#2ecc71'; // Green
                        style.className = 'freguesia-boundary';
                    } else if (feature.properties.Concelho || feature.properties.concelho || feature.properties.Municipio || feature.properties.municipio) {
                        // Concelho/Municipio style
                        style.color = '#3498db'; // Blue
                        style.className = 'concelho-boundary';
                    } else if (feature.properties.Distrito || feature.properties.distrito) {
                        // Distrito style
                        style.color = '#9b59b6'; // Purple
                        style.className = 'distrito-boundary';
                    } else {
                        // Default style
                        style.color = '#3498db';
                    }
                } else {
                    // Default style
                    style.color = '#3498db';
                }
                
                return style;
            },
            onEachFeature: function (feature, layer) {
                let name = 'Localização';
                
                // Try to extract name from properties
                if (feature.properties) {
                    name = feature.properties.Nome || 
                           feature.properties.nome || 
                           feature.properties.NOME || 
                           feature.properties.name ||
                           feature.properties.NAME ||
                           feature.properties.Freguesia ||
                           feature.properties.freguesia ||
                           feature.properties.Concelho ||
                           feature.properties.concelho ||
                           feature.properties.Municipio ||
                           feature.properties.municipio ||
                           feature.properties.Distrito ||
                           feature.properties.distrito ||
                           name;
                }
                
                layer.bindPopup(name);
            }
        }).addTo(map);
        
        // Fit map to polygon bounds if the polygon has valid bounds
        if (locationPolygon && locationPolygon.getBounds && !locationPolygon.getBounds().isValid()) {
            console.warn('Invalid bounds for the drawn polygon');
        } else if (locationPolygon && locationPolygon.getBounds) {
            map.fitBounds(locationPolygon.getBounds());
        }
        
        // Store the original data in the current location for redrawing
        if (currentLocation) {
            if (originalData.geojsons) {
                currentLocation._originalGeojsons = originalData.geojsons;
            } else {
                // If the original data doesn't have geojsons but the current location does
                currentLocation._originalGeojsons = currentLocation.geojsons;
            }
        }
        
        console.log('Boundary drawn successfully');
    } catch (error) {
        console.error('Error drawing boundary:', error);
    }
}

/**
 * Clear all boundary layers
 */
function clearBoundaries() {
    // Remove existing polygon if it exists
    if (locationPolygon) {
        console.log('Removing existing polygon');
        map.removeLayer(locationPolygon);
        locationPolygon = null;
    }
    
    // Remove all freguesia polygons
    if (freguesiaPolygons.length > 0) {
        console.log('Removing freguesia polygons:', freguesiaPolygons.length);
        freguesiaPolygons.forEach(polygon => {
            if (polygon) {
                map.removeLayer(polygon);
            }
        });
        freguesiaPolygons = [];
    }
}

/**
 * Clear the current location selection
 */
function clearLocationSelection() {
    console.log('Clearing location selection');
    
    // Remove marker if it exists
    if (locationMarker) {
        console.log('Removing marker');
        map.removeLayer(locationMarker);
        locationMarker = null;
    }
    
    // Remove all boundary polygons
    clearBoundaries();
    
    // Reset clicked coordinates
    currentClickedCoordinates = null;
    
    // Reset dropdowns
    document.getElementById('distrito-select').value = '';
    document.getElementById('concelho-select').value = '';
    document.getElementById('concelho-select').disabled = true;
    document.getElementById('freguesia-select').value = '';
    document.getElementById('freguesia-select').disabled = true;
    
    // Clear location data panel
    document.getElementById('location-data').innerHTML = '<p>Selecione uma localização para ver os dados</p>';
    
    // Esconder o painel de dados
    const locationPanel = document.querySelector('.location-data-panel');
    console.log('Closing panel, before removing class:', locationPanel.className);
    locationPanel.classList.remove('visible');
    console.log('Panel after removing class:', locationPanel.className);
    
    // Hide the "Página Completa" button
    const fullDataLink = document.getElementById('view-full-data');
    if (fullDataLink) {
        fullDataLink.style.display = 'none';
    }
    
    // Reset current location data
    currentLocation = null;
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

    // Initialize the "Página Completa" button as hidden
    const fullDataLink = document.getElementById('view-full-data');
    if (fullDataLink) {
        fullDataLink.style.display = 'none';
    }

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
        
        // Reset concelho and freguesia
        const concelhoSelect = document.getElementById('concelho-select');
        concelhoSelect.value = '';
        concelhoSelect.innerHTML = '<option value="">Selecione um concelho...</option>';
        concelhoSelect.disabled = true;
        
        const freguesiaSelect = document.getElementById('freguesia-select');
        freguesiaSelect.value = '';
        freguesiaSelect.innerHTML = '<option value="">Selecione uma freguesia...</option>';
        freguesiaSelect.disabled = true;
        
        // Remove any map markers or polygons from previous selections
        if (locationMarker) {
            map.removeLayer(locationMarker);
            locationMarker = null;
        }
        if (locationPolygon) {
            map.removeLayer(locationPolygon);
            locationPolygon = null;
        }
        
        // Reset currentClickedCoordinates
        currentClickedCoordinates = null;
        
        if (selectedDistrito) {
            loadConcelhos(selectedDistrito);
        }
    });

    // Concelho select change event
    document.getElementById('concelho-select').addEventListener('change', function() {
        const selectedConcelho = this.value;
        
        // Reset freguesia
        const freguesiaSelect = document.getElementById('freguesia-select');
        freguesiaSelect.value = '';
        freguesiaSelect.innerHTML = '<option value="">Selecione uma freguesia...</option>';
        freguesiaSelect.disabled = true;
        
        // Remove any map markers or polygons from previous selections
        if (locationMarker) {
            map.removeLayer(locationMarker);
            locationMarker = null;
        }
        if (locationPolygon) {
            map.removeLayer(locationPolygon);
            locationPolygon = null;
        }
        
        // Reset currentClickedCoordinates
        currentClickedCoordinates = null;
        
        if (selectedConcelho) {
            loadFreguesias(selectedConcelho);
        }
    });

    // Freguesia select change event
    document.getElementById('freguesia-select').addEventListener('change', function() {
        // Remove any map markers or polygons from previous selections
        if (locationMarker) {
            map.removeLayer(locationMarker);
            locationMarker = null;
        }
        if (locationPolygon) {
            map.removeLayer(locationPolygon);
            locationPolygon = null;
        }
        
        // Reset currentClickedCoordinates
        currentClickedCoordinates = null;
    });

    // Calculate button event - now this is the only place where data is fetched
    document.querySelector('.calculate-button').addEventListener('click', function() {
        // Get the selected values
        const selectedDistrito = document.getElementById('distrito-select').value;
        const selectedConcelho = document.getElementById('concelho-select').value;
        const selectedFreguesia = document.getElementById('freguesia-select').value;
        
        // Clear any existing location data (but keep the marker if it exists)
        if (locationPolygon) {
            map.removeLayer(locationPolygon);
            locationPolygon = null;
        }
        
        // Make sure the census sidebar is hidden before fetching new data
        document.getElementById('census-sidebar').classList.remove('active');
        censusSidebarActive = false;
        
        // Update UI to show loading state
        document.querySelector('.calculate-button').textContent = 'A carregar...';
        document.querySelector('.calculate-button').disabled = true;
        
        // Determine what to fetch based on selection or clicked coordinates
        if (currentClickedCoordinates) {
            // If map was clicked, prioritize those coordinates
            fetchLocationByCoordinates(
                currentClickedCoordinates.lat, 
                currentClickedCoordinates.lng
            );
        } else if (selectedFreguesia && selectedConcelho) {
            // If freguesia is selected, fetch freguesia data
            fetchLocationByFreguesia(selectedFreguesia, selectedConcelho);
        } else if (selectedConcelho) {
            // If only concelho is selected (no freguesia), fetch concelho data
            fetchLocationByMunicipio(selectedConcelho);
        } else if (selectedDistrito) {
            // If only distrito is selected (no concelho or freguesia), fetch distrito data
            fetchLocationByDistrito(selectedDistrito);
        } else {
            // Reset UI state
            document.querySelector('.calculate-button').textContent = 'Carregar Dados';
            document.querySelector('.calculate-button').disabled = false;
            alert('Selecione uma localização antes de carregar os dados.');
        }
    });

    // Close location data panel
    document.querySelector('.location-data-panel .close-panel').addEventListener('click', function() {
        const locationPanel = document.querySelector('.location-data-panel');
        console.log('Close button clicked, panel before:', locationPanel.className);
        locationPanel.classList.remove('visible');
        console.log('Panel after removing class:', locationPanel.className);
        
        // Verificar se o painel está realmente oculto
        setTimeout(() => {
            const computedStyle = window.getComputedStyle(locationPanel);
            console.log('Panel computed right value:', computedStyle.right);
            console.log('Panel is visible:', locationPanel.classList.contains('visible'));
        }, 100);
    });

    // Census sidebar close button
    document.getElementById('census-close-btn').addEventListener('click', function() {
        document.getElementById('census-sidebar').classList.remove('active');
        censusSidebarActive = false;
    });
    
    // Census year toggle
    document.getElementById('census-year-toggle').addEventListener('change', function() {
        if (!currentLocation) return;
        
        const selectedYear = this.checked ? 2021 : 2011;
        if (currentCensusYear === selectedYear) return;
        
        currentCensusYear = selectedYear;
        
        // Get the relevant census data
        const primaryCensus = selectedYear === 2021 ? currentLocation.censos2021 : currentLocation.censos2011;
        const secondaryCensus = selectedYear === 2021 ? currentLocation.censos2011 : currentLocation.censos2021;
        
        if (!primaryCensus) return;
        
        // Update stats with animation
        animateStatUpdate('population-value', primaryCensus);
        animateStatUpdate('buildings-value', primaryCensus);
        animateStatUpdate('dwellings-value', primaryCensus);
        animateStatUpdate('density-value', primaryCensus);
        
        // Update charts
        updateCensusStats(primaryCensus, secondaryCensus);
        
        // Recreate charts with new data
        if (typeof Chart !== 'undefined') {
            createGenderChart(primaryCensus);
            calculateAverageAge(primaryCensus);
        }
        
        // Recreate age bars
        createAgeBars(primaryCensus);
    });
    
    // Link to location_data.php
    document.getElementById('census-view-full-data').addEventListener('click', function(e) {
        if (currentLocation) {
            const url = buildFullDataUrl(currentLocation);
            if (url) {
                this.href = url;
                return true;
            }
        }
        
        e.preventDefault();
        alert('Por favor, selecione uma localização primeiro.');
        return false;
    });
    
    // Handle ESC key to close census sidebar
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape' && censusSidebarActive) {
            document.getElementById('census-sidebar').classList.remove('active');
            censusSidebarActive = false;
        }
    });

    // Freguesia toggle switch
    document.getElementById('show-freguesias-toggle').addEventListener('change', function() {
        showFreguesias = this.checked;
        console.log('Show freguesias toggle changed to:', showFreguesias);
        
        // Redraw boundaries if we have current location data with freguesias
        if (currentLocation) {
            console.log('Redrawing with freguesias toggle:', showFreguesias);
            
            if (currentLocation._originalGeojsons) {
                console.log('Using stored original geojsons');
                const tempData = { ...currentLocation };
                tempData.geojsons = currentLocation._originalGeojsons;
                drawLocationBoundary(tempData);
            } else if (currentLocation.geojsons) {
                console.log('Using current geojsons');
                drawLocationBoundary(currentLocation);
            }
        }
    });
}

/**
 * Animate stat value update
 */
function animateStatUpdate(elementId, censusData) {
    const element = document.getElementById(elementId);
    if (!element) return;
    
    let targetValue;
    
    switch (elementId) {
        case 'population-value':
            targetValue = getCensusValue(censusData, ['N_INDIVIDUOS_RESIDENT', 'N_INDIVIDUOS']);
            break;
        case 'buildings-value':
            targetValue = getCensusValue(censusData, ['N_EDIFICIOS_CLASSICOS', 'N_EDIFICIOS']);
            break;
        case 'dwellings-value':
            targetValue = getCensusValue(censusData, ['N_ALOJAMENTOS_TOTAL', 'N_ALOJAMENTOS']);
            break;
        case 'density-value':
            const population = getCensusValue(censusData, ['N_INDIVIDUOS_RESIDENT', 'N_INDIVIDUOS']);
            // Check multiple possible area property names
            const areaHa = currentLocation.area_ha || currentLocation.areaha || currentLocation.area || 
                          (currentLocation.data && currentLocation.data.area_ha) || 
                          (currentLocation.data && currentLocation.data.areaha);
            
            if (population && areaHa) {
                const areaKm2 = areaHa / 100;
                targetValue = Math.round(population / areaKm2);
            } else if (population) {
                // Fallback density estimate
                targetValue = Math.round(population / 10);
            }
            break;
        case 'average-age-value':
            // We'll handle this specially
            calculateAverageAge(censusData);
            return;
    }
    
    if (!targetValue) {
        element.textContent = 'N/A';
        return;
    }
    
    // Get current value
    let currentValue = parseInt(element.textContent.replace(/[^\d]/g, '')) || 0;
    const diff = targetValue - currentValue;
    
    // Use animation frame for smooth update
    let startTime;
    const duration = 1000; // 1 second
    
    function updateValue(timestamp) {
        if (!startTime) startTime = timestamp;
        
        const progress = Math.min((timestamp - startTime) / duration, 1);
        const easeProgress = progress === 1 ? 1 : 1 - Math.pow(2, -10 * progress); // Exponential ease out
        
        const currentVal = Math.round(currentValue + diff * easeProgress);
        
        // Format based on element type
        if (elementId === 'density-value') {
            element.textContent = `${new Intl.NumberFormat('pt-PT').format(currentVal)} h/km²`;
        } else {
            element.textContent = new Intl.NumberFormat('pt-PT').format(currentVal);
        }
        
        if (progress < 1) {
            requestAnimationFrame(updateValue);
        }
    }
    
    requestAnimationFrame(updateValue);
} 