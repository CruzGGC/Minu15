/**
 * Explorador de Cidade em 15 Minutos - Map Functionality
 * Handles map initialization, isochrone generation, and POI display
 * 
 * @version 2.0
 */

// Global variables
let map;
let currentMarker;
let isochroneLayer;
let poiLayers = {};
let selectedTransportMode = 'walking'; // Default mode: walking
let selectedMaxDistance = 15; // Default time: 15 minutes
let currentIsochroneData = null; // Store current isochrone data for POI requests
let currentTileLayer = null; // Store current tile layer
let selectedTileProvider = DEFAULT_TILE_PROVIDER; // Default tile provider from config

// Map transport modes to OpenRouteService API profiles
const orsProfiles = {
    walking: 'foot-walking',
    cycling: 'cycling-regular',
    driving: 'driving-car'
};

// Transport mode speeds (km/h) for fallback calculation if ORS API fails
const transportSpeeds = {
    walking: 5,  // Walking: 5 km/h
    cycling: 15, // Cycling: 15 km/h
    driving: 60  // Driving: 60 km/h
};

// POI types definition with display details
const poiTypes = {
    // === Health ===
    hospitals: { 
        name: 'Hospitais', 
        icon: 'hospital', 
        class: 'poi-hospital',
        category: 'health'
    },
    health_centers: { 
        name: 'Centros de Saúde', 
        icon: 'first-aid-kit', 
        class: 'poi-health',
        category: 'health'
    },
    pharmacies: { 
        name: 'Farmácias', 
        icon: 'prescription-bottle-alt', 
        class: 'poi-pharmacy',
        category: 'health'
    },
    dentists: { 
        name: 'Clínicas Dentárias', 
        icon: 'tooth', 
        class: 'poi-dentist',
        category: 'health'
    },
    
    // === Education ===
    schools: { 
        name: 'Escolas Primárias e Secundárias', 
        icon: 'school', 
        class: 'poi-school',
        category: 'education'
    },
    universities: { 
        name: 'Universidades e Institutos Superiores', 
        icon: 'graduation-cap', 
        class: 'poi-university',
        category: 'education'
    },
    kindergartens: { 
        name: 'Jardins de Infância e Creches', 
        icon: 'baby', 
        class: 'poi-kindergarten',
        category: 'education'
    },
    libraries: { 
        name: 'Bibliotecas', 
        icon: 'book', 
        class: 'poi-library',
        category: 'education'
    },
    
    // === Commercial & Services ===
    supermarkets: { 
        name: 'Supermercados', 
        icon: 'shopping-basket', 
        class: 'poi-supermarket',
        category: 'commercial'
    },
    malls: { 
        name: 'Centros Comerciais', 
        icon: 'shopping-bag', 
        class: 'poi-mall',
        category: 'commercial'
    },
    restaurants: { 
        name: 'Restaurantes e Cafés', 
        icon: 'utensils', 
        class: 'poi-restaurant',
        category: 'commercial'
    },
    atms: { 
        name: 'Caixas de Multibanco', 
        icon: 'money-bill-wave', 
        class: 'poi-atm',
        category: 'commercial'
    },
    
    // === Safety & Emergency ===
    police: { 
        name: 'Esquadras da Polícia', 
        icon: 'shield-alt', 
        class: 'poi-police',
        category: 'safety'
    },
    fire_stations: { 
        name: 'Quartéis de Bombeiros', 
        icon: 'fire-extinguisher', 
        class: 'poi-fire-station',
        category: 'safety'
    },
    civil_protection: { 
        name: 'Serviços Governamentais Públicos', 
        icon: 'building-columns', 
        class: 'poi-civil-protection',
        category: 'safety'
    },
    
    // === Public Administration ===
    parish_councils: { 
        name: 'Juntas de Freguesia', 
        icon: 'city', 
        class: 'poi-parish',
        category: 'administration'
    },
    city_halls: { 
        name: 'Câmaras Municipais', 
        icon: 'landmark', 
        class: 'poi-city-hall',
        category: 'administration'
    },
    post_offices: { 
        name: 'Correios', 
        icon: 'envelope', 
        class: 'poi-post-office',
        category: 'administration'
    },
    
    // === Culture & Leisure ===
    museums: { 
        name: 'Museus', 
        icon: 'museum', 
        class: 'poi-museum',
        category: 'culture'
    },
    theaters: { 
        name: 'Teatros', 
        icon: 'theater-masks', 
        class: 'poi-theater',
        category: 'culture'
    },
    sports: { 
        name: 'Ginásios e Centros Desportivos', 
        icon: 'dumbbell', 
        class: 'poi-sport',
        category: 'culture'
    },
    parks: { 
        name: 'Parques', 
        icon: 'tree', 
        class: 'poi-park',
        category: 'culture'
    },
    
    // === Transportation ===
    bus_stops: { 
        name: 'Paragens de Autocarro', 
        icon: 'bus', 
        class: 'poi-bus',
        category: 'transportation'
    },
    train_stations: { 
        name: 'Estações de Comboio', 
        icon: 'train', 
        class: 'poi-train',
        category: 'transportation'
    },
    subway_stations: { 
        name: 'Estações de Metro', 
        icon: 'subway', 
        class: 'poi-subway',
        category: 'transportation'
    },
    parking: { 
        name: 'Estacionamentos', 
        icon: 'parking', 
        class: 'poi-parking',
        category: 'transportation'
    }
};

// Initialize the map when the DOM is fully loaded
document.addEventListener('DOMContentLoaded', function() {
    initMap();
    initControls();
    showInitialInstructions();
});

// Initialize the Leaflet map
function initMap() {
    // Center coordinates for Aveiro, Portugal
    const aveiroCenter = [40.6405, -8.6538];
    
    // Create a new map centered on Aveiro with zoom controls disabled
    map = L.map('map', {
        zoomControl: false  // Disable zoom controls
    }).setView(aveiroCenter, 13);
    
    // Add the selected tile layer
    updateMapTiles(selectedTileProvider);
    
    // Initialize empty POI layer groups
    Object.keys(poiTypes).forEach(type => {
        poiLayers[type] = L.layerGroup().addTo(map);
    });
    
    // Set up map click event
    setupMapClickEvents();
    
    // Add the POI legend
    addLegend();
}

// Update map tiles based on selected provider
function updateMapTiles(provider) {
    // If there's an existing tile layer, remove it
    if (currentTileLayer) {
        map.removeLayer(currentTileLayer);
    }
    
    // Get the provider configuration
    const tileConfig = MAP_TILE_PROVIDERS[provider] || MAP_TILE_PROVIDERS[DEFAULT_TILE_PROVIDER];
    
    // Create and add the new tile layer
    currentTileLayer = L.tileLayer(tileConfig.url, {
        attribution: tileConfig.attribution,
        maxZoom: tileConfig.maxZoom
    }).addTo(map);
    
    // Update the selectedTileProvider variable
    selectedTileProvider = provider;
    
    // Update the map style selector UI if it exists
    updateMapStyleSelector();
}

// Update the map style selector buttons to show the active style
function updateMapStyleSelector() {
    document.querySelectorAll('.map-style-option').forEach(button => {
        const provider = button.getAttribute('data-provider');
        if (provider === selectedTileProvider) {
            button.classList.add('active');
        } else {
            button.classList.remove('active');
        }
    });
}

// Set up map click event
function setupMapClickEvents() {
    // Variable to track if tutorial is active
    let tutorialActive = false;
    
    // Set tutorial active state when showing instructions
    document.addEventListener('tutorialShown', function() {
        tutorialActive = true;
    });
    
    // Set tutorial inactive when instructions are closed
    document.addEventListener('tutorialClosed', function() {
        tutorialActive = false;
    });
    
    // Add click handler to map
    map.on('click', function(e) {
        // If tutorial is active, don't process the map click for sidebar hiding
        if (tutorialActive && window.innerWidth > 768) {
            return;
        }
        
        // Get click coordinates
        const latlng = e.latlng;
        
        // Remove existing marker if any
        if (currentMarker) {
            map.removeLayer(currentMarker);
        }
        
        // Add new marker at click location
        currentMarker = L.marker(latlng).addTo(map);
        
        // Generate isochrone for the clicked location
        generateIsochrone(latlng);
    });
}

// Generate isochrone for the selected location
function generateIsochrone(latlng) {
    // Show loading indicator
    showLoading();
    
    // Clear existing isochrone and marker
    if (isochroneLayer) {
        map.removeLayer(isochroneLayer);
    }
    
    // Clear existing POI layers
    Object.keys(poiTypes).forEach(type => {
        poiLayers[type].clearLayers();
    });
    
    // Add marker at the selected location
    if (currentMarker) {
        map.removeLayer(currentMarker);
    }
    
    currentMarker = L.marker(latlng).addTo(map);
    
    // Get the selected transport mode profile for OpenRouteService
    const profile = orsProfiles[selectedTransportMode];
    
    // Prepare parameters for OpenRouteService API request
    const params = {
        locations: [[latlng.lng, latlng.lat]],
        range: [selectedMaxDistance * 60], // Convert minutes to seconds
        range_type: 'time',
        attributes: ['area'],
        area_units: 'km',
        smoothing: 0.5
    };
    
    // Use our PHP proxy instead of direct ORS API call
    const formData = new FormData();
    formData.append('endpoint', `/v2/isochrones/${profile}`);
    formData.append('data', JSON.stringify(params));
    
    console.log(`Generating isochrone for ${profile} mode, ${selectedMaxDistance} minutes`);
    
    // Make request to our proxy
    fetch('includes/proxy_ors.php', {
        method: 'POST',
        body: formData
    })
    .then(response => {
        if (!response.ok) {
            throw new Error(`HTTP error! Status: ${response.status}`);
        }
        return response.json();
    })
    .then(data => {
        // First check if the response indicates an API error
        if (data.success === false) {
            // This is an error response from our PHP proxy
            const errorMessage = data.message || 'Unknown API error';
            throw new Error(`API Error: ${errorMessage}`);
        }
        
        console.log('Received API response:', data);
        
        // Validate GeoJSON response
        if (!data.type) {
            throw new Error('Missing type property in GeoJSON');
        }
        
        if (data.type !== 'FeatureCollection') {
            throw new Error(`Invalid GeoJSON type: ${data.type || 'undefined'}`);
        }
        
        if (!data.features || !Array.isArray(data.features) || data.features.length === 0) {
            throw new Error('Missing or empty features array in GeoJSON');
        }
        
        const feature = data.features[0];
        if (!feature.geometry || !feature.geometry.coordinates) {
            throw new Error('Missing geometry or coordinates in GeoJSON feature');
        }
        
        if (!feature.geometry.type || feature.geometry.type !== 'Polygon') {
            throw new Error(`Invalid geometry type: ${feature.geometry.type || 'undefined'}`);
        }
        
        console.log('Validated GeoJSON response successfully');
        
        // Process response and display isochrone
        currentIsochroneData = data;
        displayIsochrone(data, latlng);
        
        // Now fetch POIs within the isochrone area
        fetchPOIsWithinIsochrone(latlng, data);
    })
    .catch(error => {
        console.error('Error generating isochrone:', error);
        console.error('Error details:', error.message);
        
        // Use fallback circle buffer if API fails
        useCircleBufferFallback(latlng);
        
        // Hide loading indicator
        hideLoading();
    });
}

// Display the isochrone on the map
function displayIsochrone(data, latlng) {
    try {
        console.log('Displaying isochrone with data:', data);
        
        // Create GeoJSON layer from API response
        isochroneLayer = L.geoJSON(data, {
            style: function() {
                return {
                    fillColor: getIsochroneColor(),
                    weight: 2,
                    opacity: 0.8,
                    color: getIsochroneColor(),
                    dashArray: getIsochroneDashArray(),
                    fillOpacity: 0.3
                };
            }
        }).addTo(map);
        
        // Fit map view to isochrone bounds
        map.fitBounds(isochroneLayer.getBounds());
        
        // Calculate radius for statistics
        let radius = null;
        
        // If we have an isochrone, calculate approximate radius
        if (data && data.features && data.features[0]) {
            // Calculate the area of the isochrone
            const area = turf.area(data.features[0]);
            // Approximate radius from area (assuming circular shape): r = sqrt(area / π)
            radius = Math.sqrt(area / Math.PI);
        } else {
            // Fallback to speed-based calculation
            const speedKmPerHour = transportSpeeds[selectedTransportMode];
            const distanceInKm = (speedKmPerHour * selectedMaxDistance) / 60;
            radius = distanceInKm * 1000; // Convert to meters
        }
        
        // Show the statistics panel
        showStatisticsPanel();
        
        // Update area statistics with the isochrone data
        updateAreaStats(latlng, radius, data);
        
        // Fetch POIs within the isochrone area
        fetchPOIs(latlng);
        
        // Hide loading indicator
        hideLoading();
        
    } catch (error) {
        console.error('Error displaying isochrone:', error);
        
        // Fallback to simple circle buffer if isochrone display fails
        useCircleBufferFallback(latlng);
        
        // Hide loading indicator
        hideLoading();
    }
}

/**
 * Show POIs within the given area
 * This function is a wrapper for fetchPOIs to maintain compatibility
 */
function showPOIsInArea(data) {
    // If we have a current marker, use its position to fetch POIs
    if (currentMarker) {
        const latlng = currentMarker.getLatLng();
        fetchPOIs(latlng);
    }
}

// Buscar POIs dentro da isócrona
function fetchPOIsWithinIsochrone(latlng, isochroneData) {
    // Limpar camadas de POI existentes
    Object.keys(poiTypes).forEach(type => {
        poiLayers[type].clearLayers();
    });
    
    // Mostrar indicador de carregamento
    showLoading();
    
    // Extrair área da isócrona se disponível
    let radiusInMeters;
    if (isochroneData.features && 
        isochroneData.features[0] && 
        isochroneData.features[0].properties && 
        isochroneData.features[0].properties.area) {
        // Converter km² para m² para manter consistência com o resto do código
        const areaInKm2 = isochroneData.features[0].properties.area;
        radiusInMeters = Math.sqrt(areaInKm2 * 1000000 / Math.PI);
    } else {
        // Fallback: usar estimativa baseada na velocidade
        const speedKmPerHour = transportSpeeds[selectedTransportMode];
        const distanceInKm = (speedKmPerHour * selectedMaxDistance) / 60;
        radiusInMeters = distanceInKm * 1000;
    }
    
    // Serializar o GeoJSON da isócrona para enviar ao servidor
    const isochroneGeoJSON = JSON.stringify(isochroneData);
    
    // Array de promessas para controlar todas as requisições de POIs
    const poiPromises = [];
    
    // Buscar tipos de POI habilitados
    Object.keys(poiTypes).forEach(type => {
        const checkbox = document.getElementById(`poi-${type}`);
        if (checkbox && checkbox.checked) {
            // Adicionar promessa da requisição ao array
            const promise = fetchPOIsByType(type, latlng, radiusInMeters, isochroneGeoJSON)
                .catch(error => {
                    console.error(`Error fetching ${type} POIs:`, error);
                    return { success: false, type: type, error: error.message };
                });
            poiPromises.push(promise);
        }
    });
    
    // Quando todas as requisições terminarem, esconder o indicador de carregamento
    Promise.all(poiPromises)
        .then(() => {
            hideLoading();
        })
        .catch(error => {
            console.error("Erro ao buscar POIs:", error);
            hideLoading();
        });
    
    // Atualizar estatísticas usando o polígono da isócrona
    try {
        updateAreaStats(latlng, radiusInMeters, isochroneGeoJSON);
    } catch (error) {
        console.error("Error updating area statistics:", error);
        // Continue with the application flow even if statistics fail
        displayAreaStats(null, latlng);
    }
}

// Método de fallback usando buffer circular com Turf.js
function useCircleBufferFallback(latlng) {
    // Calcular distância em metros com base no modo de transporte e tempo máximo
    const speedKmPerHour = transportSpeeds[selectedTransportMode];
    const distanceInKm = (speedKmPerHour * selectedMaxDistance) / 60;
    const distanceInMeters = distanceInKm * 1000;
    
    // Usar turf.js para criar um buffer ao redor do ponto
    const point = turf.point([latlng.lng, latlng.lat]);
    const buffered = turf.buffer(point, distanceInMeters / 1000, { units: 'kilometers' });
    
    // Criar camada GeoJSON a partir do buffer
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
    
    // Ajustar o mapa à isócrona
    map.fitBounds(isochroneLayer.getBounds());
    
    try {
        // Update statistics panel - wrapped in try/catch to prevent errors from breaking the map functionality
        updateAreaStats(latlng, distanceInMeters, buffered);
    } catch (statsError) {
        console.error('Error updating area statistics in fallback mode:', statsError);
        // Continue with the application flow even if statistics fail
        displayAreaStats(null, latlng);
        showStatisticsPanel();
    }
    
    // Buscar POIs dentro da área
    fetchPOIs(latlng);
}

// Obter cor com base no modo de transporte
function getIsochroneColor() {
    const colors = {
        walking: '#3A86FF',
        cycling: '#2ecc71',
        driving: '#e74c3c'
    };
    
    return colors[selectedTransportMode];
}

// Obter array de traços com base no modo de transporte
function getIsochroneDashArray() {
    const dashArrays = {
        walking: null,
        cycling: '5,5',
        driving: '10,5'
    };
    
    return dashArrays[selectedTransportMode];
}

// Buscar POIs da base de dados
function fetchPOIs(latlng) {
    // Limpar camadas de POI existentes
    Object.keys(poiTypes).forEach(type => {
        poiLayers[type].clearLayers();
    });
    
    // Calcular raio de busca com base no modo de transporte e tempo máximo
    const speedKmPerHour = transportSpeeds[selectedTransportMode];
    const distanceInKm = (speedKmPerHour * selectedMaxDistance) / 60;
    const radiusInMeters = distanceInKm * 1000;
    
    // Buscar tipos de POI habilitados
    Object.keys(poiTypes).forEach(type => {
        const checkbox = document.getElementById(`poi-${type}`);
        if (checkbox && checkbox.checked) {
            // Buscar POIs deste tipo no servidor
            fetchPOIsByType(type, latlng, radiusInMeters);
        }
    });
}

// Buscar POIs de um tipo específico do servidor
function fetchPOIsByType(type, latlng, radius, isochroneGeoJSON) {
    // Criar dados do formulário para a requisição
    const formData = new FormData();
    formData.append('type', type);
    formData.append('lat', latlng.lat);
    formData.append('lng', latlng.lng);
    formData.append('radius', radius);
    
    // Adicionar o GeoJSON da isócrona se disponível
    if (isochroneGeoJSON) {
        formData.append('isochrone', isochroneGeoJSON);
    }
    
    // Retornar uma promessa para que possamos controlar o fluxo de múltiplas requisições
    return new Promise((resolve, reject) => {
        // Fazer requisição AJAX para o servidor
        fetch('includes/fetch_pois.php', {
            method: 'POST',
            body: formData
        })
        .then(response => {
            if (!response.ok) {
                throw new Error(`HTTP error! Status: ${response.status}`);
            }
            return response.text(); // Get as text first to check if it's valid JSON
        })
        .then(text => {
            // Try to parse the response as JSON
            try {
                const data = JSON.parse(text);
                
                if (data.success) {
                    // Adicionar POIs ao mapa
                    addPOIsToMap(type, data.pois);
                    resolve(data);
                } else {
                    console.error(`Erro ao buscar POIs do tipo ${type}:`, data.message);
                    if (data.debug) {
                        console.debug(`Debug info para ${type}:`, data.debug);
                    }
                    reject(new Error(data.message));
                }
            } catch (parseError) {
                console.error(`Error parsing JSON for ${type}:`, parseError);
                console.debug('Raw response:', text);
                reject(parseError);
            }
        })
        .catch(error => {
            console.error(`Erro ao processar requisição para ${type}:`, error);
            reject(error);
        });
    });
}

// Adicionar POIs ao mapa
function addPOIsToMap(type, pois) {
    const poiInfo = poiTypes[type];
    
    // Criar marcadores para cada POI
    pois.forEach(poi => {
        // Criar ícone personalizado
        const icon = L.divIcon({
            html: `<i class="fas fa-${poiInfo.icon} ${poiInfo.class}"></i>`,
            className: 'poi-icon',
            iconSize: [36, 36],
            iconAnchor: [18, 18]
        });
        
        // Criar marcador com ícone personalizado
        const marker = L.marker([poi.latitude, poi.longitude], {
            icon: icon
        });
        
        // Criar conteúdo do popup com toda a informação disponível
        let popupContent = `
            <div class="popup-content">
                <h4>${poi.name || poiInfo.name}</h4>
                <button class="popup-directions-btn" onclick="openDirections(${poi.latitude}, ${poi.longitude})">
                    <i class="fas fa-directions"></i> Obter Direções
                </button>
            </div>
        `;
        
        // Adicionar popup ao marcador
        marker.bindPopup(popupContent, { 
            maxWidth: 300, 
            minWidth: 240,
            className: 'poi-popup' 
        });
        
        // Adicionar ao grupo de camadas
        marker.addTo(poiLayers[type]);
    });
}

// Abrir direções no Google Maps
function openDirections(lat, lng) {
    const url = `https://www.google.com/maps/dir/?api=1&destination=${lat},${lng}`;
    window.open(url, '_blank');
}

/**
 * Update area statistics with data from the API
 */
function updateAreaStats(latlng, radius, isochroneGeoJSON) {
    // Show loading indicator in stats panel
    const statsContent = document.getElementById('stats-content');
    if (statsContent) {
        statsContent.innerHTML = '<div class="loading-spinner"></div>';
    }
    
    // Prepare data for the request
    const requestData = new FormData();
    requestData.append('lat', latlng.lat);
    requestData.append('lng', latlng.lng);
    
    // Add isochrone GeoJSON if available
    if (isochroneGeoJSON) {
        // Check if isochroneGeoJSON is already a string or needs to be stringified
        const geoJsonString = typeof isochroneGeoJSON === 'string' 
            ? isochroneGeoJSON 
            : JSON.stringify(isochroneGeoJSON);
        requestData.append('isochrone', geoJsonString);
    }
    
    // Add radius as fallback
    if (radius) {
        requestData.append('radius', radius);
    }
    
    // Add selected POI types
    const selectedPOIs = [];
    Object.keys(poiTypes).forEach(type => {
        const checkbox = document.getElementById(`poi-${type}`);
        if (checkbox && checkbox.checked) {
            selectedPOIs.push(type);
        }
    });
    requestData.append('selected_pois', JSON.stringify(selectedPOIs));
    
    // Make API request
    fetch('includes/fetch_statistics.php', {
        method: 'POST',
        body: requestData
    })
    .then(response => {
        if (!response.ok) {
            throw new Error(`HTTP error! Status: ${response.status}`);
        }
        return response.text(); // Get as text first to check if it's valid JSON
    })
    .then(text => {
        try {
            const data = JSON.parse(text);
            
            if (data.success) {
                // Display statistics
                displayAreaStats(data.stats, latlng);
            } else {
                console.error('Error fetching area statistics:', data.message);
                // Display error in stats panel but still show basic info
                displayAreaStats(null, latlng);
            }
        } catch (parseError) {
            console.error('Error parsing statistics JSON:', parseError);
            console.debug('Raw response:', text);
            // Display error in stats panel but still show basic info
            displayAreaStats(null, latlng);
        }
    })
    .catch(error => {
        console.error('Error fetching area statistics:', error);
        // Handle error gracefully - still display the isochrone but with limited stats
        displayAreaStats(null, latlng);
    });
}

/**
 * Display area statistics in the panel
 */
function displayAreaStats(stats, latlng) {
    // Get the stats content element
    const statsContent = document.getElementById('stats-content');
    
    // Check if stats is undefined or null
    if (!stats) {
        // Display basic information without statistics
        statsContent.innerHTML = `
            <div class="error-message">
                <i class="fas fa-exclamation-triangle"></i>
                <p>Estatísticas não disponíveis. A API de estatísticas pode estar indisponível.</p>
            </div>
            <div class="stats-section general-info" data-lat="${latlng.lat}" data-lng="${latlng.lng}">
                <h3>Informações Gerais</h3>
                <p><strong>Tempo:</strong> ${selectedMaxDistance} minutos ${getTransportModeText()}</p>
                <p><strong>Coordenadas:</strong> ${latlng.lat.toFixed(5)}, ${latlng.lng.toFixed(5)}</p>
                <p class="freguesia-info">Clique aqui para identificar o municipio</p>
            </div>
        `;
        
        // Add click event to the freguesia info element
        document.querySelector('.freguesia-info').addEventListener('click', function() {
            identifyMunicipio(latlng);
        });
        
        // Make the general-info section clickable to identify municipio
        document.querySelector('.general-info').addEventListener('click', function() {
            const lat = parseFloat(this.getAttribute('data-lat'));
            const lng = parseFloat(this.getAttribute('data-lng'));
            identifyMunicipio({lat, lng});
        });
        
        return; // Exit the function early
    }
    
    // Calculate accessibility score
    const accessibilityScore = calculateAccessibilityScore(stats);
    
    // Create HTML for the statistics
    let html = `
        <div class="stats-section accessibility-score">
            <h3>Pontuação de Acessibilidade</h3>
            <div class="score-display score-${getScoreLabel(accessibilityScore.score).toLowerCase()}">
                <div class="score-value">${accessibilityScore.score}</div>
                <div class="score-label">${getScoreLabel(accessibilityScore.score)}</div>
            </div>
            <p class="score-explanation">
                Esta pontuação é baseada na disponibilidade de serviços essenciais 
                a ${selectedMaxDistance} minutos ${getTransportModeText()}.
                ${getTimeAdjustmentText(selectedMaxDistance)}
            </p>
            <p class="score-details">
                <strong>${accessibilityScore.poiCount}</strong> serviços em <strong>${accessibilityScore.categories}</strong> categorias
            </p>
        </div>
        
        <div class="stats-section general-info" data-lat="${latlng.lat}" data-lng="${latlng.lng}">
            <h3>Informações Gerais</h3>
            <p><strong>Área:</strong> ${stats.area_km2.toFixed(2)} km²</p>
            <p><strong>Tempo:</strong> ${selectedMaxDistance} minutos ${getTransportModeText()}</p>
            <p><strong>Coordenadas:</strong> ${latlng.lat.toFixed(5)}, ${latlng.lng.toFixed(5)}</p>
            <p class="freguesia-info">Carregando informações do município...</p>
        </div>
    `;
    
    // Add POI statistics by category
    const poiCategories = {
        'Saúde': ['hospitals', 'health_centers', 'pharmacies', 'dentists'],
        'Educação': ['schools', 'universities', 'kindergartens', 'libraries'],
        'Comércio e Serviços': ['supermarkets', 'malls', 'restaurants', 'atms'],
        'Segurança e Serviços Públicos': ['police', 'police_stations', 'fire_stations', 'civil_protection'],
        'Administração Pública': ['city_halls', 'post_offices'],
        'Cultura e Lazer': ['museums', 'theaters', 'sports', 'parks'],
        'Transportes': ['bus_stops', 'train_stations', 'subway_stations', 'parking']
    };
    
    // Add infrastructure statistics section
    html += `<div class="stats-section infrastructure-stats"><h3>Infraestruturas</h3>`;
    
    // Add POI statistics for each category
    let hasInfrastructureData = false;
    
    for (const [category, types] of Object.entries(poiCategories)) {
        let categoryHtml = `<div class="category-section"><h4>${category}</h4><ul>`;
        let hasData = false;
        
        for (const type of types) {
            if (stats[type] !== undefined) {
                const poiName = poiTypes[type]?.name || type;
                const poiIcon = poiTypes[type]?.icon || 'map-marker-alt';
                const poiClass = poiTypes[type]?.class || '';
                
                categoryHtml += `
                    <li>
                        <i class="fas fa-${poiIcon} ${poiClass}"></i>
                        <span class="poi-name">${poiName}</span>
                        <span class="poi-count">${stats[type]}</span>
                    </li>
                `;
                hasData = true;
                hasInfrastructureData = true;
            }
        }
        
        categoryHtml += `</ul></div>`;
        
        if (hasData) {
            html += categoryHtml;
        }
    }
    
    // Close infrastructure section
    html += `</div>`;
    
    // If no infrastructure data was found
    if (!hasInfrastructureData) {
        html = html.replace('<div class="stats-section infrastructure-stats"><h3>Infraestruturas</h3></div>', 
            '<div class="stats-section infrastructure-stats"><h3>Infraestruturas</h3><p>Não foram encontradas infraestruturas nesta área.</p></div>');
    }
    
    // Set the HTML content
    statsContent.innerHTML = html;
    
    // Automatically fetch municipality information
    identifyMunicipio(latlng);
}

// Add a new function to handle municipio identification only
function identifyMunicipio(latlng) {
    const municipioInfo = document.querySelector('.freguesia-info');
    municipioInfo.textContent = 'A identificar municipio...';
    municipioInfo.classList.add('loading');

    // Remove any previous municipio button
    let municipioBtn = document.getElementById('municipio-link-btn');
    if (municipioBtn) municipioBtn.remove();

    // Function to display location data based on detail level
    function displayMunicipioData(locationData) {
        // Get the detail level from settings or use default (municipio)
        const detailLevel = localStorage.getItem('locationDetailLevel') || 'municipio';
        
        // Make sure any previous button is removed
        let existingBtn = document.getElementById('municipio-link-btn');
        if (existingBtn) existingBtn.remove();
        
        // Clear the loading text and make it just the button
        municipioInfo.textContent = '';
        municipioInfo.classList.remove('loading');
        municipioInfo.classList.add('freguesia-found'); // Keep freguesia-found class for CSS compatibility

        // Get the appropriate name and type based on the detail level
        let locationName = '';
        let locationType = detailLevel;
        let locationIcon = 'fa-info-circle';
        
        // Set the location name and icon based on the detail level
        if (detailLevel === 'freguesia') {
            // Use freguesia data if available
            locationName = locationData.freguesia || locationData.nomeMunicipio || locationData;
            locationIcon = 'fa-map-marker-alt';
        } else if (detailLevel === 'distrito') {
            // Use distrito data if available
            locationName = locationData.distrito || locationData.nomeMunicipio || locationData;
            locationIcon = 'fa-map';
        } else {
            // Default to municipio
            locationName = locationData.nomeMunicipio || locationData;
            locationIcon = 'fa-city';
        }
        
        // Create button to view location data with modern styling
        const btn = document.createElement('button');
        btn.id = 'municipio-link-btn';
        btn.className = 'municipio-link-btn modern-button';
        
        // Add an icon and the location name to the button
        btn.innerHTML = `<i class="fas ${locationIcon}"></i> ${locationName}`;
        
        // Add modern styling directly to the button
        btn.style.backgroundColor = '#3498db';
        btn.style.color = 'white';
        btn.style.border = 'none';
        btn.style.borderRadius = '4px';
        btn.style.padding = '8px 16px';
        btn.style.fontSize = '14px';
        btn.style.fontWeight = '500';
        btn.style.cursor = 'pointer';
        btn.style.transition = 'all 0.2s ease';
        btn.style.boxShadow = '0 2px 5px rgba(0,0,0,0.1)';
        btn.style.display = 'inline-flex';
        btn.style.alignItems = 'center';
        btn.style.justifyContent = 'center';
        btn.style.margin = '5px 0';
        btn.style.width = '100%';
        
        // Add hover effect with JavaScript
        btn.onmouseover = function() {
            this.style.backgroundColor = '#2980b9';
            this.style.boxShadow = '0 4px 8px rgba(0,0,0,0.2)';
        };
        btn.onmouseout = function() {
            this.style.backgroundColor = '#3498db';
            this.style.boxShadow = '0 2px 5px rgba(0,0,0,0.1)';
        };
        
        btn.onclick = function() {
            if (locationType === 'freguesia') {
                const municipio = locationData.nomeMunicipio || '';
                if (!municipio) {
                    alert('Erro: Para visualizar dados de uma freguesia, é necessário especificar o município.');
                    return;
                }
                window.open(`location_data.php?type=${locationType}&id=${encodeURIComponent(locationName)}&municipio=${encodeURIComponent(municipio)}`, '_blank');
            } else {
                window.open(`location_data.php?type=${locationType}&id=${encodeURIComponent(locationName)}`, '_blank');
            }
        };
        
        municipioInfo.appendChild(btn);
    }

    // Get the detail level from settings or use default (municipio)
    const detailLevel = localStorage.getItem('locationDetailLevel') || 'municipio';
    
    // Update loading text based on selected detail level
    let loadingText = 'A identificar município...';
    if (detailLevel === 'freguesia') {
        loadingText = 'A identificar freguesia...';
    } else if (detailLevel === 'distrito') {
        loadingText = 'A identificar distrito...';
    }
    municipioInfo.textContent = loadingText;
    
    // First try with the proxy
    fetch(`includes/geoapi_proxy.php?endpoint=${encodeURIComponent(`gps/${latlng.lat},${latlng.lng}/base/detalhes`)}`)
        .then(response => {
            // Check if the response is valid JSON
            const contentType = response.headers.get('content-type');
            if (!contentType || !contentType.includes('application/json')) {
                // Not a JSON response, read text and log it
                return response.text().then(text => {
                    console.error('Non-JSON response:', text);
                    throw new Error('Invalid response format - not JSON');
                });
            }
            return response.json();
        })
        .then(data => {
            console.log('API response data:', data);
            
            // Create a location data object
            const locationData = {
                nomeMunicipio: data.concelho || (data.detalhesMunicipio ? data.detalhesMunicipio.nome : ''),
                freguesia: data.freguesia || (data.detalhesFreguesia ? data.detalhesFreguesia.nome : ''),
                distrito: data.distrito || (data.detalhesMunicipio ? data.detalhesMunicipio.distrito : '')
            };
            
            // Check if we have the data for the selected detail level
            let hasRequiredData = false;
            if (detailLevel === 'freguesia' && locationData.freguesia) {
                hasRequiredData = true;
            } else if (detailLevel === 'distrito' && locationData.distrito) {
                hasRequiredData = true;
            } else if (locationData.nomeMunicipio) {
                hasRequiredData = true;
            }
            
            if (hasRequiredData) {
                displayMunicipioData(locationData);
            } else {
                let errorMsg = 'Não foi possível identificar a localização';
                municipioInfo.textContent = errorMsg;
                municipioInfo.classList.remove('loading');
            }
        })
        .catch(error => {
            console.error('Error identifying location:', error);
            
            // Attempt to fetch directly from the API
            console.log('Attempting direct API fetch...');
            
            // Try direct API as fallback
            fetch(`http://json.localhost:8080/gps/${latlng.lat},${latlng.lng}/base/detalhes`)
                .then(response => response.json())
                .then(data => {
                    console.log('Direct API response:', data);
                    
                    // Create a location data object
                    const locationData = {
                        nomeMunicipio: data.concelho || (data.detalhesMunicipio ? data.detalhesMunicipio.nome : ''),
                        freguesia: data.freguesia || (data.detalhesFreguesia ? data.detalhesFreguesia.nome : ''),
                        distrito: data.distrito || (data.detalhesMunicipio ? data.detalhesMunicipio.distrito : '')
                    };
                    
                    // Check if we have the data for the selected detail level
                    let hasRequiredData = false;
                    if (detailLevel === 'freguesia' && locationData.freguesia) {
                        hasRequiredData = true;
                    } else if (detailLevel === 'distrito' && locationData.distrito) {
                        hasRequiredData = true;
                    } else if (locationData.nomeMunicipio) {
                        hasRequiredData = true;
                    }
                    
                    if (hasRequiredData) {
                        displayMunicipioData(locationData);
                    } else {
                        let errorMsg = 'Não foi possível identificar a localização';
                        municipioInfo.textContent = errorMsg;
                        municipioInfo.classList.remove('loading');
                    }
                })
                .catch(directError => {
                    console.error('Error with direct API call:', directError);
                    municipioInfo.textContent = 'Erro ao identificar localização';
                    municipioInfo.classList.remove('loading');
                });
        });
}

// Calcular o Accessibility Score baseado nos POIs disponíveis
function calculateAccessibilityScore(stats) {
    // Check if stats is undefined or null
    if (!stats) {
        return {
            score: 0,
            poiCount: 0,
            categories: 0
        };
    }
    
    // Definir pesos para diferentes categorias de POIs
    // Valor padrão
    const defaultWeights = {
        // Saúde (maior peso - essencial)
        hospitals: 10,
        health_centers: 8,
        pharmacies: 7,
        dentists: 5,
        
        // Educação
        schools: 9,
        universities: 6,
        kindergartens: 7,
        libraries: 5,
        
        // Comércio e Serviços (essencial para vida diária)
        supermarkets: 10,
        malls: 6,
        restaurants: 7,
        atms: 6,
        
        // Transporte
        bus_stops: 8,
        train_stations: 7,
        subway_stations: 7,
        parking: 5,
        
        // Segurança e Emergência
        police_stations: 8,
        fire_stations: 7,
        civil_protection: 5,
        
        // Administração Pública
        parish_councils: 4,
        city_halls: 4,
        post_offices: 5,
        
        // Cultura e Lazer
        museums: 3,
        theaters: 3,
        sports: 6,
        parks: 8
    };
    
    // Get custom weights from settings panel if available
    const weights = { ...defaultWeights };
    
    // Update weights from user settings
    Object.keys(weights).forEach(key => {
        const savedWeight = localStorage.getItem(`weight-${key}`);
        if (savedWeight) {
            weights[key] = parseInt(savedWeight);
        }
    });
    
    // Contadores para o cálculo
    let totalScore = 0;
    let maxPossibleScore = 0;
    let poiCount = 0;
    let categoriesWithPOIs = 0;
    
    // Categorias principais para verificar diversidade
    const mainCategories = {
        health: ['hospitals', 'health_centers', 'pharmacies', 'dentists'],
        education: ['schools', 'universities', 'kindergartens', 'libraries'],
        commerce: ['supermarkets', 'malls', 'restaurants', 'atms'],
        transport: ['bus_stops', 'train_stations', 'subway_stations', 'parking'],
        safety: ['police_stations', 'fire_stations', 'civil_protection'],
        admin: ['parish_councils', 'city_halls', 'post_offices'],
        leisure: ['museums', 'theaters', 'sports', 'parks']
    };
    
    // Verificar quais categorias têm pelo menos um POI
    const categoriesPresent = {};
    
    // Calcular pontuação para cada tipo de POI
    for (const [type, weight] of Object.entries(weights)) {
        // Verificar se o tipo está selecionado (checkbox marcado)
        const checkbox = document.getElementById(`poi-${type}`);
        if (checkbox && checkbox.checked) {
            // Adicionar ao score máximo possível
            maxPossibleScore += weight * 3; // Considerando que 3+ POIs é ideal
            
            // Obter contagem de POIs
            const count = stats[type] || 0;
            
            if (count > 0) {
                // Encontrar a categoria principal deste POI
                for (const [category, types] of Object.entries(mainCategories)) {
                    if (types.includes(type)) {
                        categoriesPresent[category] = true;
                        break;
                    }
                }
                
                // Calcular pontuação com base na quantidade (até 3 POIs por tipo)
                const countScore = Math.min(count, 3);
                totalScore += weight * countScore;
                poiCount += count;
            }
        }
    }
    
    // Prevent division by zero
    if (maxPossibleScore === 0) {
        return {
            score: 0,
            poiCount: 0,
            categories: 0
        };
    }
    
    // Contar categorias presentes para bônus de diversidade
    categoriesWithPOIs = Object.keys(categoriesPresent).length;
    
    // Bônus por diversidade de categorias (até 20%)
    const diversityBonus = (categoriesWithPOIs / 7) * 20;
    
    // Calcular score final (0-100)
    let finalScore = Math.round((totalScore / maxPossibleScore) * 80 + diversityBonus);
    
    // Ajustar o score com base no tempo selecionado
    // Para tempos menores que 15 minutos, o score é aumentado (é mais impressionante ter muitos POIs em menos tempo)
    // Para tempos maiores que 15 minutos, o score é reduzido (é menos impressionante ter os mesmos POIs em mais tempo)
    const timeAdjustmentFactor = calculateTimeAdjustmentFactor(selectedMaxDistance);
    finalScore = Math.round(finalScore * timeAdjustmentFactor);
    
    // Garantir que o score esteja entre 0 e 100
    finalScore = Math.max(0, Math.min(100, finalScore));
    
    // Add essential services check - if there are no essential services, reduce score
    const hasEssentialServices = 
        (stats.hospitals > 0 || stats.health_centers > 0 || stats.pharmacies > 0) && // Health
        (stats.supermarkets > 0 || stats.restaurants > 0) && // Food
        (stats.schools > 0 || stats.kindergartens > 0); // Education
    
    if (!hasEssentialServices) {
        // If missing essential services, reduce score by 20%
        finalScore = Math.round(finalScore * 0.8);
    }
    
    return {
        score: finalScore,
        poiCount: poiCount,
        categories: categoriesWithPOIs,
        hasEssentialServices: hasEssentialServices
    };
}

/**
 * Calculate time adjustment factor for the accessibility score
 * For times less than 15 minutes, increase the score (more impressive to have POIs in less time)
 * For times more than 15 minutes, decrease the score (less impressive to have POIs in more time)
 */
function calculateTimeAdjustmentFactor(minutes) {
    if (minutes < 15) {
        // Increase score by up to 30% for shorter times
        return 1 + ((15 - minutes) / 15) * 0.3;
    } else if (minutes > 15) {
        // Decrease score by up to 20% for longer times
        return 1 - ((minutes - 15) / 15) * 0.2;
    }
    return 1; // No adjustment for 15 minutes
}

/**
 * Get text explaining the time adjustment factor
 */
function getTimeAdjustmentText(minutes) {
    if (minutes < 15) {
        return `<small>(Pontuação ajustada positivamente para tempos menores que 15 minutos)</small>`;
    } else if (minutes > 15) {
        return `<small>(Pontuação ajustada negativamente para tempos maiores que 15 minutos)</small>`;
    }
    return '';
}

/**
 * Get score label based on numeric score
 */
function getScoreLabel(score) {
    if (score >= 90) return 'Excelente';
    if (score >= 80) return 'Muito Bom';
    if (score >= 70) return 'Bom';
    if (score >= 60) return 'Satisfatório';
    if (score >= 50) return 'Médio';
    if (score >= 40) return 'Básico';
    if (score >= 30) return 'Limitado';
    if (score >= 20) return 'Fraco';
    if (score >= 10) return 'Muito Fraco';
    return 'Insuficiente';
}

/**
 * Get transport mode text for display
 */
function getTransportModeText() {
    switch (selectedTransportMode) {
        case 'walking': return 'a pé';
        case 'cycling': return 'de bicicleta';
        case 'driving': return 'de carro';
        default: return 'a pé';
    }
}

// Adicionar legenda ao mapa
function addLegend() {
    // Usando a legenda de POI que agora está no HTML
    // Não precisamos de L.control legend mais, pois temos uma personalizada na UI
}

// Mostrar indicador de carregamento
function showLoading() {
    // Remover overlay existente, se houver
    hideLoading();
    
    // Criar overlay
    const overlay = document.createElement('div');
    overlay.className = 'loading-overlay';
    overlay.id = 'loading-overlay';
    
    // Criar spinner
    const spinner = document.createElement('div');
    spinner.className = 'spinner';
    
    // Adicionar texto de carregamento
    const loadingText = document.createElement('div');
    loadingText.style.marginTop = '15px';
    loadingText.style.fontWeight = 'bold';
    loadingText.textContent = 'A calcular a isócrona...';
    
    // Adicionar spinner e texto ao overlay
    overlay.appendChild(spinner);
    overlay.appendChild(loadingText);
    
    // Adicionar overlay ao contêiner do mapa
    document.getElementById('map').appendChild(overlay);
}

// Ocultar indicador de carregamento
function hideLoading() {
    const overlay = document.getElementById('loading-overlay');
    if (overlay) {
        overlay.parentNode.removeChild(overlay);
    }
}

// Mostrar painel de estatísticas
function showStatisticsPanel() {
    const statsPanel = document.querySelector('.statistics-panel');
    if (statsPanel) {
        statsPanel.classList.add('visible');
    }
}

// Ocultar painel de estatísticas (adicionada para completude)
function hideStatisticsPanel() {
    const statsPanel = document.querySelector('.statistics-panel');
    if (statsPanel) {
        statsPanel.classList.remove('visible');
    }
}

// Shows initial instructions to help new users
function showInitialInstructions() {
    // Check if the user has seen the tutorial before
    if (localStorage.getItem('minu15_instructions_seen') === 'true') {
        return;
    }
    
    // Create the tutorial container
    const tutorialBox = document.createElement('div');
    tutorialBox.id = 'instruction-box';
    tutorialBox.style.position = 'absolute';
    tutorialBox.style.top = '50%';
    tutorialBox.style.left = '50%';
    tutorialBox.style.transform = 'translate(-50%, -50%) scale(0.9)';
    tutorialBox.style.background = 'rgba(255, 255, 255, 0.97)';
    tutorialBox.style.padding = '30px';
    tutorialBox.style.borderRadius = '16px';
    tutorialBox.style.boxShadow = '0 10px 40px rgba(0, 0, 0, 0.15)';
    tutorialBox.style.zIndex = '1500';
    tutorialBox.style.maxWidth = '550px';
    tutorialBox.style.width = '90%';
    tutorialBox.style.opacity = '0';
    tutorialBox.style.transition = 'all 0.3s ease-out';
    
    // Create tutorial content
    tutorialBox.innerHTML = `
        <div style="position: relative;">
            <div style="text-align: center; margin-bottom: 25px;">
                <div style="width: 70px; height: 70px; background-color: #3498db; border-radius: 50%; display: flex; justify-content: center; align-items: center; margin: 0 auto 15px;">
                    <i class="fas fa-map-marked-alt" style="font-size: 32px; color: white;"></i>
                </div>
                <h2 style="font-size: 24px; color: #2c3e50; margin-bottom: 5px; font-weight: 600;">Bem-vindo ao Minu15</h2>
                <p style="color: #7f8c8d; font-size: 15px;">Explore áreas acessíveis em 15 minutos a pé, bicicleta ou carro</p>
            </div>
            
            <div style="margin-bottom: 25px;">
                <div style="margin-bottom: 20px;">
                    <div style="display: flex; margin-bottom: 15px; align-items: flex-start;">
                        <div style="background: #3498db; color: white; border-radius: 50%; width: 28px; height: 28px; display: flex; justify-content: center; align-items: center; margin-right: 15px; flex-shrink: 0;">
                            <span style="font-weight: bold;">1</span>
                        </div>
                        <div>
                            <strong style="font-weight: 600; color: #2c3e50;">Selecione um ponto no mapa</strong> 
                            <p style="margin-top: 5px; color: #555;">Clique em qualquer lugar no mapa para definir um ponto de partida, ou utilize a barra de pesquisa para encontrar um endereço específico.</p>
                        </div>
                    </div>
                    
                    <div style="display: flex; margin-bottom: 15px; align-items: flex-start;">
                        <div style="background: #3498db; color: white; border-radius: 50%; width: 28px; height: 28px; display: flex; justify-content: center; align-items: center; margin-right: 15px; flex-shrink: 0;">
                            <span style="font-weight: bold;">2</span>
                        </div>
                        <div>
                            <strong style="font-weight: 600; color: #2c3e50;">Configure suas preferências</strong> 
                            <p style="margin-top: 5px; color: #555;">Escolha o modo de transporte (a pé, bicicleta ou carro) e ajuste o tempo máximo de deslocamento utilizando o painel lateral.</p>
                        </div>
                    </div>
                    
                    <div style="display: flex; margin-bottom: 15px; align-items: flex-start;">
                        <div style="background: #3498db; color: white; border-radius: 50%; width: 28px; height: 28px; display: flex; justify-content: center; align-items: center; margin-right: 15px; flex-shrink: 0;">
                            <span style="font-weight: bold;">3</span>
                        </div>
                        <div>
                            <strong style="font-weight: 600; color: #2c3e50;">Visualize a área de cobertura</strong> 
                            <p style="margin-top: 5px; color: #555;">A <span style="color: #3498db; font-weight: 500;">isócrona</span> (área colorida) mostrará até onde você pode chegar no tempo definido, e os pontos de interesse disponíveis nessa região.</p>
                        </div>
                    </div>
                </div>
                
                <div style="background-color: #f0f7ff; border-left: 4px solid #3498db; padding: 15px; border-radius: 5px; margin-top: 20px;">
                    <div style="display: flex; align-items: center; margin-bottom: 8px;">
                        <i class="fas fa-lightbulb" style="color: #3498db; margin-right: 10px; font-size: 18px;"></i>
                        <strong style="font-weight: 600; color: #2c3e50;">Dica</strong>
                    </div>
                    <p style="margin: 0; color: #555; font-size: 14px;">Use o painel de configurações para personalizar os pesos de cada tipo de serviço no cálculo de pontuação de acessibilidade.</p>
                </div>
            </div>
            
            <div style="text-align: center; margin-top: 30px;">
                <div style="margin-bottom: 20px;">
                    <button id="got-it-btn" style="background-color: #3498db; color: white; border: none; padding: 12px 25px; border-radius: 30px; font-weight: 600; font-size: 16px; cursor: pointer; width: 100%; transition: all 0.2s ease;">Começar a explorar</button>
                </div>
                <div style="display: flex; align-items: center; justify-content: center; font-size: 14px; color: #7f8c8d;">
                    <input type="checkbox" id="dont-show-again" style="margin-right: 8px;">
                    <label for="dont-show-again">Não mostrar novamente</label>
                </div>
            </div>
        </div>
    `;
    
    // Add to document
    document.body.appendChild(tutorialBox);
    
    // Add entrance animation
    setTimeout(() => {
        tutorialBox.style.opacity = '1';
        tutorialBox.style.transform = 'translate(-50%, -50%) scale(1)';
        
        // Show a visual hint of map click after tutorial appears
        setTimeout(() => {
            showMapClickAnimation();
        }, 1000);
        
        // Dispatch event that tutorial is shown
        document.dispatchEvent(new Event('tutorialShown'));
    }, 100);
    
    // Prevent clicks on the tutorial from propagating to the map
    tutorialBox.addEventListener('click', function(event) {
        event.stopPropagation();
    });
    
    // Add hover effect to the button
    const tutorialBtn = document.getElementById('got-it-btn');
    tutorialBtn.addEventListener('mouseover', function() {
        this.style.backgroundColor = '#2980b9';
        this.style.transform = 'translateY(-2px)';
        this.style.boxShadow = '0 5px 15px rgba(52, 152, 219, 0.4)';
    });
    
    tutorialBtn.addEventListener('mouseout', function() {
        this.style.backgroundColor = '#3498db';
        this.style.transform = 'translateY(0)';
        this.style.boxShadow = 'none';
    });
    
    // Close button event
    tutorialBtn.addEventListener('click', function(event) {
        event.stopPropagation();
        
        // Add exit animation
        tutorialBox.style.opacity = '0';
        tutorialBox.style.transform = 'translate(-50%, -50%) scale(0.9)';
        
        // Save preference if checkbox is checked
        if (document.getElementById('dont-show-again').checked) {
            localStorage.setItem('minu15_instructions_seen', 'true');
        }
        
        // Remove after animation completes
        setTimeout(() => {
            document.getElementById('instruction-box').remove();
            
            // Critical fix for sidebar disappearing issue
            // Force the sidebar to be visible on desktop
            if (window.innerWidth > 768) {
                const panel = document.getElementById('overlay-panel');
                if (panel) {
                    // First remove any problematic class or style that might be hiding it
                    panel.classList.remove('mobile-active');
                    
                    // Apply direct styles to ensure visibility
                    panel.style.display = 'block';
                    panel.style.transform = 'none';
                    panel.style.visibility = 'visible';
                    panel.style.opacity = '1';
                    panel.style.left = '20px';
                    panel.style.zIndex = '999';
                    panel.style.position = 'absolute';
                    
                    // Apply additional override styles through a CSS rule
                    const styleElement = document.createElement('style');
                    styleElement.id = 'sidebar-fix-style';
                    styleElement.textContent = `
                        @media (min-width: 769px) {
                            #overlay-panel {
                                display: block !important;
                                transform: none !important;
                                visibility: visible !important;
                                opacity: 1 !important;
                                left: 20px !important;
                                z-index: 999 !important;
                                position: absolute !important;
                                top: 20px !important;
                                overflow-y: auto !important;
                                max-height: calc(100vh - 40px) !important;
                            }
                        }
                    `;
                    
                    // Add the style element if it doesn't exist yet
                    if (!document.getElementById('sidebar-fix-style')) {
                        document.head.appendChild(styleElement);
                    }
                    
                    // Set multiple delayed fixes to catch any race conditions
                    for (let i = 1; i <= 5; i++) {
                        setTimeout(() => {
                            panel.style.display = 'block';
                            panel.style.transform = 'none';
                            panel.style.visibility = 'visible';
                            panel.style.opacity = '1';
                        }, i * 100);
                    }
                }
            }
            
            // Dispatch event that tutorial is closed
            document.dispatchEvent(new Event('tutorialClosed'));
            
            // Highlight key UI elements after tutorial closes
            highlightKeyElements();
        }, 300);
    });
    
    // "Don't show again" event
    document.getElementById('dont-show-again').addEventListener('click', function(event) {
        // Stop event propagation to prevent triggering map click
        event.stopPropagation();
    });
}

/**
 * Shows a visual animation suggesting to click on the map
 */
function showMapClickAnimation() {
    // Create the cursor element
    const cursor = document.createElement('div');
    cursor.style.position = 'absolute';
    cursor.style.width = '24px';
    cursor.style.height = '24px';
    cursor.style.background = 'url("data:image/svg+xml;charset=utf-8,%3Csvg xmlns=\'http://www.w3.org/2000/svg\' viewBox=\'0 0 16 16\'%3E%3Cpath fill=\'%23ffffff\' stroke=\'%23000000\' stroke-width=\'1\' d=\'M1 1 L15 8 L8 10 L10 15 L7 7 Z\'/%3E%3C/svg%3E") no-repeat';
    cursor.style.backgroundSize = 'contain';
    cursor.style.zIndex = '1001';
    cursor.style.pointerEvents = 'none';
    cursor.style.transition = 'transform 0.2s ease-out';
    cursor.style.transform = 'scale(1.2)';
    cursor.style.opacity = '0.9';
    document.body.appendChild(cursor);
    
    // Create the click effect element
    const clickEffect = document.createElement('div');
    clickEffect.style.position = 'absolute';
    clickEffect.style.width = '40px';
    clickEffect.style.height = '40px';
    clickEffect.style.borderRadius = '50%';
    clickEffect.style.background = 'rgba(52, 152, 219, 0.4)';
    clickEffect.style.transform = 'translate(-50%, -50%) scale(0)';
    clickEffect.style.zIndex = '1000';
    clickEffect.style.pointerEvents = 'none';
    document.body.appendChild(clickEffect);
    
    // Get a point in the center-right area of the map
    const mapElement = document.getElementById('map');
    const mapRect = mapElement.getBoundingClientRect();
    const startX = mapRect.left + mapRect.width * 0.65;
    const startY = mapRect.top + mapRect.height * 0.4;
    const targetX = startX + 50;
    const targetY = startY + 30;
    
    // Position the cursor at starting point
    cursor.style.left = startX + 'px';
    cursor.style.top = startY + 'px';
    
    // Animate cursor to target position
    setTimeout(() => {
        cursor.style.transition = 'all 1s ease-in-out';
        cursor.style.left = targetX + 'px';
        cursor.style.top = targetY + 'px';
        
        // Show click effect at target position
        setTimeout(() => {
            cursor.style.transform = 'scale(0.8)';
            
            // Position and animate the click effect
            clickEffect.style.left = targetX + 'px';
            clickEffect.style.top = targetY + 'px';
            clickEffect.style.transition = 'all 0.5s ease-out';
            clickEffect.style.transform = 'translate(-50%, -50%) scale(1)';
            clickEffect.style.opacity = '1';
            
            // Fade out click effect
            setTimeout(() => {
                clickEffect.style.transform = 'translate(-50%, -50%) scale(1.5)';
                clickEffect.style.opacity = '0';
                
                // Clean up after animation
                setTimeout(() => {
                    cursor.remove();
                    clickEffect.remove();
                }, 500);
            }, 500);
        }, 1000);
    }, 500);
}

/**
 * Highlights key UI elements to guide users where to start
 */
function highlightKeyElements() {
    // Highlight the sidebar panel
    const panel = document.querySelector('.overlay-panel');
    const transportOptions = document.querySelector('.transport-mode');
    
    // Create highlight effect elements
    const panelHighlight = document.createElement('div');
    panelHighlight.style.position = 'absolute';
    panelHighlight.style.top = '0';
    panelHighlight.style.left = '0';
    panelHighlight.style.width = '100%';
    panelHighlight.style.height = '100%';
    panelHighlight.style.boxShadow = '0 0 0 4px rgba(52, 152, 219, 0.7)';
    panelHighlight.style.borderRadius = 'inherit';
    panelHighlight.style.pointerEvents = 'none';
    panelHighlight.style.zIndex = '1000';
    panelHighlight.style.opacity = '0';
    panelHighlight.style.transition = 'opacity 0.5s ease-in-out';
    
    // Add the highlight to the panel
    panel.style.position = 'relative';
    panel.appendChild(panelHighlight);
    
    // Create a highlight for the transport mode options
    const transportHighlight = document.createElement('div');
    transportHighlight.style.position = 'absolute';
    transportHighlight.style.top = '0';
    transportHighlight.style.left = '0';
    transportHighlight.style.width = '100%';
    transportHighlight.style.height = '100%';
    transportHighlight.style.boxShadow = '0 0 0 4px rgba(52, 152, 219, 0.7)';
    transportHighlight.style.borderRadius = 'inherit';
    transportHighlight.style.pointerEvents = 'none';
    transportHighlight.style.zIndex = '1000';
    transportHighlight.style.opacity = '0';
    transportHighlight.style.transition = 'opacity 0.5s ease-in-out';
    
    // Add the highlight to the transport options
    transportOptions.style.position = 'relative';
    transportOptions.appendChild(transportHighlight);
    
    // Animate the highlight for the panel
    setTimeout(() => {
        panelHighlight.style.opacity = '1';
        
        // Highlight transport options after the panel
        setTimeout(() => {
            panelHighlight.style.opacity = '0';
            transportHighlight.style.opacity = '1';
            
            // Remove the highlights after 2 seconds
            setTimeout(() => {
                transportHighlight.style.opacity = '0';
                
                // Remove the highlight elements after fade out
                setTimeout(() => {
                    panelHighlight.remove();
                    transportHighlight.remove();
                }, 500);
            }, 2000);
        }, 2000);
    }, 500);
    
    // Add the pulse animation class if it doesn't exist
    if (!document.getElementById('pulse-animation-style')) {
        const style = document.createElement('style');
        style.id = 'pulse-animation-style';
        style.textContent = `
            @keyframes pulse-animation {
                0% { box-shadow: 0 0 0 0 rgba(52, 152, 219, 0.7); }
                70% { box-shadow: 0 0 0 10px rgba(52, 152, 219, 0); }
                100% { box-shadow: 0 0 0 0 rgba(52, 152, 219, 0); }
            }
            .pulse-highlight {
                animation: pulse-animation 1.5s infinite;
            }
        `;
        document.head.appendChild(style);
    }
}