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
        name: 'Proteção Civil', 
        icon: 'hard-hat', 
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
    
    // Add click event handler to the map
    map.on('click', function(e) {
        handleMapClick(e.latlng);
    });
    
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

// Handle map click by placing a marker
function handleMapClick(latlng) {
    // Clear existing marker if present
    if (currentMarker) {
        map.removeLayer(currentMarker);
    }
    
    // Add a new marker at clicked location
    currentMarker = L.marker(latlng).addTo(map);
    
    // Automatically generate isochrone without waiting for Calculate button
    generateIsochrone(latlng);
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
            const promise = fetchPOIsByType(type, latlng, radiusInMeters, isochroneGeoJSON);
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
    updateAreaStats(latlng, radiusInMeters, isochroneGeoJSON);
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
        .then(response => response.json())
        .then(data => {
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
                <p><strong>Tipo:</strong> ${poi.type}</p>
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
        return response.json();
    })
    .then(data => {
        if (data.success) {
            // Display statistics
            displayAreaStats(data.stats, latlng);
        } else {
            console.error('Error fetching area statistics:', data.message);
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
                <p class="freguesia-info">Clique aqui para identificar a freguesia</p>
            </div>
        `;
        
        // Add click event to the freguesia info element
        document.querySelector('.freguesia-info').addEventListener('click', function() {
            identifyFreguesia(latlng);
        });
        
        // Make the general-info section clickable to identify freguesia
        document.querySelector('.general-info').addEventListener('click', function() {
            const lat = parseFloat(this.getAttribute('data-lat'));
            const lng = parseFloat(this.getAttribute('data-lng'));
            identifyFreguesia({lat, lng});
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
            ${stats.population_estimate ? `<p><strong>População Estimada:</strong> ${stats.population_estimate.toLocaleString()} habitantes</p>` : ''}
            <p class="freguesia-info">Clique aqui para identificar a freguesia</p>
        </div>
    `;
    
    // Add POI statistics by category
    const poiCategories = {
        'Saúde': ['hospitals', 'health_centers', 'pharmacies', 'dentists'],
        'Educação': ['schools', 'universities', 'kindergartens', 'libraries'],
        'Comércio e Serviços': ['supermarkets', 'malls', 'restaurants', 'atms'],
        'Segurança e Emergência': ['police_stations', 'fire_stations', 'civil_protection'],
        'Administração Pública': ['parish_councils', 'city_halls', 'post_offices'],
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
    
    // Add click event to the freguesia info element
    document.querySelector('.freguesia-info').addEventListener('click', function() {
        identifyFreguesia(latlng);
    });
    
    // Make the general-info section clickable to identify freguesia
    document.querySelector('.general-info').addEventListener('click', function() {
        const lat = parseFloat(this.getAttribute('data-lat'));
        const lng = parseFloat(this.getAttribute('data-lng'));
        identifyFreguesia({lat, lng});
    });
}

/**
 * Identify freguesia at the given coordinates
 */
function identifyFreguesia(latlng) {
    // Update the freguesia info text to show loading
    const freguesiaInfo = document.querySelector('.freguesia-info');
    freguesiaInfo.textContent = 'A identificar freguesia...';
    freguesiaInfo.classList.add('loading');
    
    // Fetch freguesia data from GeoAPI
    fetch(`includes/geoapi_proxy.php?endpoint=${encodeURIComponent(`gps/${latlng.lat},${latlng.lng}`)}`)
        .then(response => response.json())
        .then(data => {
            if (data && data.freguesia) {
                // Update the freguesia info with the result
                freguesiaInfo.textContent = `Freguesia: ${data.freguesia.nome}, ${data.concelho.nome}, ${data.distrito.nome}`;
                freguesiaInfo.classList.remove('loading');
                freguesiaInfo.classList.add('freguesia-found');
                
                // Fetch demographic data
                fetchFreguesiaDemographics(data.freguesia.codigo);
            } else {
                freguesiaInfo.textContent = 'Não foi possível identificar a freguesia';
                freguesiaInfo.classList.remove('loading');
            }
        })
        .catch(error => {
            console.error('Error identifying freguesia:', error);
            freguesiaInfo.textContent = 'Erro ao identificar freguesia';
            freguesiaInfo.classList.remove('loading');
        });
}

/**
 * Fetch demographic data for a freguesia
 */
function fetchFreguesiaDemographics(freguesiaCode) {
    // Create a new section for demographics if it doesn't exist
    let demographicsSection = document.querySelector('.demographics-section');
    if (!demographicsSection) {
        demographicsSection = document.createElement('div');
        demographicsSection.className = 'stats-section demographics-section';
        demographicsSection.innerHTML = '<h3>Dados Demográficos</h3><div class="loading-spinner"></div>';
        
        // Insert after general info section
        const generalInfoSection = document.querySelector('.general-info');
        generalInfoSection.parentNode.insertBefore(demographicsSection, generalInfoSection.nextSibling);
    } else {
        demographicsSection.innerHTML = '<h3>Dados Demográficos</h3><div class="loading-spinner"></div>';
    }
    
    // Fetch freguesia data from GeoAPI
    fetch(`includes/geoapi_proxy.php?endpoint=${encodeURIComponent(`freguesia/${freguesiaCode}`)}`)
        .then(response => response.json())
        .then(data => {
            if (data) {
                displayDemographicData(data, demographicsSection);
            } else {
                demographicsSection.innerHTML = '<h3>Dados Demográficos</h3><p>Dados não disponíveis</p>';
            }
        })
        .catch(error => {
            console.error('Error fetching freguesia demographics:', error);
            demographicsSection.innerHTML = '<h3>Dados Demográficos</h3><p>Erro ao obter dados demográficos</p>';
        });
}

/**
 * Display demographic data in the statistics panel
 */
function displayDemographicData(freguesiaData, container) {
    // Get census data (prefer 2021 over 2011)
    const census = freguesiaData.censos2021 || freguesiaData.censos2011;
    
    if (!census) {
        container.innerHTML = '<h3>Dados Demográficos</h3><p>Dados censitários não disponíveis</p>';
        return;
    }
    
    // Create HTML content
    let html = '<h3>Dados Demográficos</h3>';
    
    // Population
    if (census.N_INDIVIDUOS_RESIDENT) {
        html += `<p><strong>População:</strong> ${census.N_INDIVIDUOS_RESIDENT.toLocaleString()} habitantes</p>`;
    }
    
    // Buildings and housing
    if (census.N_EDIFICIOS_CLASSICOS) {
        html += `<p><strong>Edifícios:</strong> ${census.N_EDIFICIOS_CLASSICOS.toLocaleString()}</p>`;
    }
    
    if (census.N_ALOJAMENTOS) {
        html += `<p><strong>Alojamentos:</strong> ${census.N_ALOJAMENTOS.toLocaleString()}</p>`;
    }
    
    // Households
    if (census.N_AGREGADOS || freguesiaData.censos2011?.N_FAMILIAS_CLASSICAS) {
        const households = census.N_AGREGADOS || freguesiaData.censos2011?.N_FAMILIAS_CLASSICAS;
        html += `<p><strong>Famílias:</strong> ${households.toLocaleString()}</p>`;
    }
    
    // Area and density if available
    if (freguesiaData.areaha) {
        const areaKm2 = parseFloat(freguesiaData.areaha) / 100;
        html += `<p><strong>Área:</strong> ${areaKm2.toLocaleString()} km²</p>`;
        
        if (census.N_INDIVIDUOS_RESIDENT) {
            const density = Math.round(census.N_INDIVIDUOS_RESIDENT / areaKm2);
            html += `<p><strong>Densidade Populacional:</strong> ${density.toLocaleString()} hab/km²</p>`;
        }
    }
    
    // Set the HTML content
    container.innerHTML = html;
}

/**
 * Display freguesia demographics data from fetch_statistics.php
 */
function displayFreguesiaDemographics(freguesiaData) {
    if (!freguesiaData || !freguesiaData.demographics) return;
    
    // Create a new section for demographics if it doesn't exist
    let demographicsSection = document.querySelector('.demographics-section');
    if (!demographicsSection) {
        demographicsSection = document.createElement('div');
        demographicsSection.className = 'stats-section demographics-section';
        
        // Insert after general info section
        const generalInfoSection = document.querySelector('.general-info');
        generalInfoSection.parentNode.insertBefore(demographicsSection, generalInfoSection.nextSibling);
    }
    
    // Create HTML content
    let html = '<h3>Dados Demográficos</h3>';
    html += `<p><strong>Freguesia:</strong> ${freguesiaData.freguesia}</p>`;
    
    if (freguesiaData.concelho) {
        html += `<p><strong>Concelho:</strong> ${freguesiaData.concelho}</p>`;
    }
    
    if (freguesiaData.distrito) {
        html += `<p><strong>Distrito:</strong> ${freguesiaData.distrito}</p>`;
    }
    
    const demographics = freguesiaData.demographics;
    
    // Population
    if (demographics.population && demographics.population.total) {
        html += `<p><strong>População:</strong> ${demographics.population.total.toLocaleString()} habitantes</p>`;
        
        if (demographics.population.male && demographics.population.female) {
            const malePercent = Math.round((demographics.population.male / demographics.population.total) * 100);
            const femalePercent = 100 - malePercent;
            html += `<p><strong>Distribuição:</strong> ${malePercent}% homens, ${femalePercent}% mulheres</p>`;
        }
        
        if (demographics.population.density) {
            html += `<p><strong>Densidade:</strong> ${demographics.population.density.toLocaleString()} hab/km²</p>`;
        }
    }
    
    // Housing
    if (demographics.housing) {
        if (demographics.housing.buildings) {
            html += `<p><strong>Edifícios:</strong> ${demographics.housing.buildings.toLocaleString()}</p>`;
        }
        
        if (demographics.housing.dwellings) {
            html += `<p><strong>Alojamentos:</strong> ${demographics.housing.dwellings.toLocaleString()}</p>`;
        }
        
        if (demographics.housing.households) {
            html += `<p><strong>Famílias:</strong> ${demographics.housing.households.toLocaleString()}</p>`;
        }
    }
    
    // Set the HTML content
    demographicsSection.innerHTML = html;
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
    const weights = {
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
    
    // Adicionar spinner ao overlay
    overlay.appendChild(spinner);
    
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