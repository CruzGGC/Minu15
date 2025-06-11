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
let currentClickedCoordinates;

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
function fetchLocationByFreguesia(freguesia, concelho) {
    console.log(`Fetching freguesia data: ${freguesia} in concelho ${concelho}`);
    
    // Update UI to show loading state
    document.querySelector('.calculate-button').textContent = 'A carregar...';
    document.querySelector('.calculate-button').disabled = true;
    
    // Use the freguesia name for the API call, not the code
    fetch('location.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: `action=fetchByFreguesiaAndMunicipio&freguesia=${encodeURIComponent(freguesia)}&municipio=${encodeURIComponent(concelho)}`
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
                
                // Draw the location boundary if geometry is available
                if (currentLocation.geojson) {
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
                
                // Draw the location boundary if geometry is available
                if (currentLocation.geojson) {
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
                
                // Draw the location boundary if geometry is available
                if (currentLocation.geojson) {
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
 * Display location data in the panel
 */
function displayLocationData(location) {
    console.log('Displaying location data:', location);
    
    const locationDataElement = document.getElementById('location-data');
    const locationPanel = document.querySelector('.location-data-panel');
    const fullDataLink = document.getElementById('view-full-data');
    
    // Debug: mostrar o objeto completo no console
    console.log('Dados completos recebidos:', JSON.stringify(location, null, 2));
    
    // Create HTML content
    let html = '';
    
    // Location header
    if (location.nome) {
        html += `<h2>${location.nome}</h2>`;
    } else if (location.distrito) {
        html += `<h2>Distrito de ${location.distrito}</h2>`;
    } else if (location.concelho || location.municipio) {
        html += `<h2>Município de ${location.concelho || location.municipio}</h2>`;
    } else if (location.freguesia) {
        html += `<h2>Freguesia de ${location.freguesia}</h2>`;
    } else {
        html += `<h2>Localização</h2>`;
        console.warn('No name found in location data');
    }
    
    // Administrative hierarchy
    html += '<div class="location-hierarchy">';
    if (location.freguesia) {
        html += `<p><strong>Freguesia:</strong> ${location.freguesia}</p>`;
    }
    if (location.municipio || location.concelho) {
        html += `<p><strong>Concelho:</strong> ${location.municipio || location.concelho}</p>`;
    }
    if (location.distrito) {
        html += `<p><strong>Distrito:</strong> ${location.distrito}</p>`;
    }
    html += '</div>';
    
    // Verificar e mostrar informações básicas administrativas para municípios
    if (location.nif || location.codigo || location.email) {
        html += '<div class="administrative-info">';
        html += '<h3>Informações Administrativas</h3>';
        
        if (location.codigo) {
            html += `<p><strong>Código:</strong> ${location.codigo}</p>`;
        }
        
        if (location.nif) {
            html += `<p><strong>NIF:</strong> ${location.nif}</p>`;
        }
        
        if (location.email) {
            html += `<p><strong>Email:</strong> ${location.email}</p>`;
        }
        
        if (location.telefone) {
            html += `<p><strong>Telefone:</strong> ${location.telefone}</p>`;
        }
        
        if (location.sitio) {
            const url = location.sitio.startsWith('http') ? location.sitio : `http://${location.sitio}`;
            html += `<p><strong>Website:</strong> <a href="${url}" target="_blank">${location.sitio}</a></p>`;
        }
        
        html += '</div>';
    }
    
    // Debug: informações sobre a presença de censos
    console.log('Censos2021 presente:', !!location.censos2021);
    console.log('Censos2011 presente:', !!location.censos2011);
    if (location.censos2021) {
        console.log('Dados do Censos2021:', location.censos2021);
    }
    if (location.censos2011) {
        console.log('Dados do Censos2011:', location.censos2011);
    }
    
    // Census data
    if (location.censos2021 || location.censos2011) {
        html += '<div class="location-census">';
        html += '<h3>Dados Demográficos</h3>';
        
        // Population
        const censusData = location.censos2021 || location.censos2011;
        const censusYear = location.censos2021 ? 2021 : 2011;
        
        const population = getCensusValue(censusData, ['N_INDIVIDUOS_RESIDENT', 'N_INDIVIDUOS']);
        if (population) {
            html += `<p><strong>População:</strong> ${Number(population).toLocaleString()} habitantes (${censusYear})</p>`;
        }
        
        // Buildings and housing
        const buildings = getCensusValue(censusData, ['N_EDIFICIOS_CLASSICOS', 'N_EDIFICIOS']);
        if (buildings) {
            html += `<p><strong>Edifícios:</strong> ${Number(buildings).toLocaleString()}</p>`;
        }
        
        const dwellings = getCensusValue(censusData, ['N_ALOJAMENTOS_TOTAL', 'N_ALOJAMENTOS']);
        if (dwellings) {
            html += `<p><strong>Alojamentos:</strong> ${Number(dwellings).toLocaleString()}</p>`;
        }
        
        // Area and density if available
        const areaHa = location.area_ha || location.areaha;
        if (areaHa) {
            const areaKm2 = parseFloat(areaHa) / 100;
            html += `<p><strong>Área:</strong> ${areaKm2.toLocaleString()} km²</p>`;
            
            if (population) {
                const density = Math.round(population / areaKm2);
                html += `<p><strong>Densidade Populacional:</strong> ${density.toLocaleString()} hab/km²</p>`;
            }
        }
        
        html += '</div>';
        
        // Add demographic highlight if available
        html += '<div class="demographic-highlights">';
        html += '<h3>Destaques</h3>';
        
        // Working population
        const workingPopulation = getCensusValue(censusData, ['N_IND_RESID_EMPREGADOS', 'N_INDIVIDUOS_25_64', 'N_INDIVIDUOS_RESIDENT_25A64']);
        if (workingPopulation) {
            html += `<p><strong>População em idade ativa:</strong> ${Number(workingPopulation).toLocaleString()}</p>`;
        }
        
        // Young population
        const youngPopulation = getCensusValue(censusData, ['N_INDIVIDUOS_0_14']);
        if (youngPopulation) {
            html += `<p><strong>População jovem (0-14):</strong> ${Number(youngPopulation).toLocaleString()}</p>`;
        }
        
        // Elderly population
        const elderlyPopulation = getCensusValue(censusData, ['N_INDIVIDUOS_65_OU_MAIS', 'N_INDIVIDUOS_RESIDENT_65']);
        if (elderlyPopulation) {
            html += `<p><strong>População idosa (65+):</strong> ${Number(elderlyPopulation).toLocaleString()}</p>`;
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
                            <td><i class="fas fa-${poiIcons[poi]?.icon || 'map-marker'}" style="color: ${poiIcons[poi]?.color || '#666'}"></i> ${poiNames[poi]}</td>
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
    
    // Adicionar botão para mostrar dados completos para debugging
    html += `
        <div class="debug-section" style="margin-top: 20px; padding-top: 10px; border-top: 1px dashed #ccc;">
            <button id="toggle-debug" style="padding: 5px 10px; background: #f0f0f0; border: 1px solid #ccc; border-radius: 4px; cursor: pointer;">
                Mostrar Dados Completos
            </button>
            <div id="debug-data" style="display: none; margin-top: 10px; padding: 10px; background: #f8f8f8; border-radius: 4px; overflow: auto; max-height: 300px;">
                <pre>${JSON.stringify(location, null, 2)}</pre>
            </div>
        </div>
    `;
    
    // If no data is available
    if (html === '') {
        html = '<p>Não foram encontrados dados para esta localização.</p>';
        console.warn('No displayable data found in:', location);
    }
    
    // Set the HTML content
    locationDataElement.innerHTML = html;
    
    // Adicionar event listener para o botão de debug
    const toggleDebugButton = document.getElementById('toggle-debug');
    if (toggleDebugButton) {
        toggleDebugButton.addEventListener('click', function() {
            const debugData = document.getElementById('debug-data');
            if (debugData.style.display === 'none') {
                debugData.style.display = 'block';
                this.textContent = 'Ocultar Dados Completos';
            } else {
                debugData.style.display = 'none';
                this.textContent = 'Mostrar Dados Completos';
            }
        });
    }
    
    // Configure full data link based on location type
    if (location.distrito) {
        fullDataLink.href = `location_data.php?type=distrito&id=${encodeURIComponent(location.distrito)}`;
        fullDataLink.style.display = 'block';
    } else if (location.municipio || location.concelho) {
        const municipioName = location.municipio || location.concelho;
        fullDataLink.href = `location_data.php?type=municipio&id=${encodeURIComponent(municipioName)}`;
        fullDataLink.style.display = 'block';
    } else if (location.freguesia && (location.municipio || location.concelho)) {
        const municipioName = location.municipio || location.concelho;
        fullDataLink.href = `location_data.php?type=freguesia&id=${encodeURIComponent(location.freguesia)}&municipio=${encodeURIComponent(municipioName)}`;
        fullDataLink.style.display = 'block';
    } else if (currentClickedCoordinates) {
        fullDataLink.href = `location_data.php?type=gps&id=${encodeURIComponent(currentClickedCoordinates.lat)},${encodeURIComponent(currentClickedCoordinates.lng)}`;
        fullDataLink.style.display = 'block';
    } else {
        fullDataLink.style.display = 'none';
    }

    // Show the data panel - garantir que a classe "visible" é adicionada corretamente
    console.log('Panel before adding class:', locationPanel.className);
    locationPanel.classList.add('visible');
    console.log('Panel after adding class:', locationPanel.className);
    
    // Verificar se o painel está realmente visível
    setTimeout(() => {
        const computedStyle = window.getComputedStyle(locationPanel);
        console.log('Panel computed right value:', computedStyle.right);
        console.log('Panel is visible:', locationPanel.classList.contains('visible'));
    }, 100);
    
    console.log('Location data panel updated and made visible');
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
    
    // Remove existing polygon if it exists
    if (locationPolygon) {
        console.log('Removing existing polygon');
        map.removeLayer(locationPolygon);
    }
    
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
                return {
                    color: '#3498db',
                    weight: 3,
                    opacity: 0.7,
                    fillOpacity: 0.2
                };
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
        
        console.log('Boundary drawn successfully');
    } catch (error) {
        console.error('Error drawing boundary:', error);
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
    
    // Remove polygon if it exists
    if (locationPolygon) {
        console.log('Removing polygon');
        map.removeLayer(locationPolygon);
        locationPolygon = null;
    }
    
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
        document.querySelector('.location-data-panel').classList.remove('visible');
        
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
} 