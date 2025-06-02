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

// Generate isochrone polygon using OpenRouteService API
function generateIsochrone(latlng) {
    // Clear existing isochrone if present
    if (isochroneLayer) {
        map.removeLayer(isochroneLayer);
        currentIsochroneData = null;
    }
    
    // Show loading indicator
    showLoading();
    
    // Get ORS profile based on selected transport mode
    const profile = orsProfiles[selectedTransportMode];
    
    // Prepare parameters for OpenRouteService API request
    const params = {
        locations: [[latlng.lng, latlng.lat]],
        range: [selectedMaxDistance * 60], // Convert minutes to seconds
        attributes: ['area'],
        area_units: 'km',
        range_type: 'time'
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
            // Try to get the error response as JSON
            return response.json().then(errData => {
                throw new Error(`API Error: ${errData.message || response.statusText}`);
            });
        }
        return response.json();
    })
    .then(data => {
        // First check if the response indicates an API error
        if (data.success === false) {
            // This is an error response from our PHP proxy
            const errorMessage = data.message || 'Unknown API error';
            const statusCode = data.status || '';
            throw new Error(`API Error (${statusCode}): ${errorMessage}`);
        }
        
        // Log the full response for debugging
        console.log('Received API response:', data);
        
        // Validate the GeoJSON structure more thoroughly
        if (!data || typeof data !== 'object') {
            throw new Error('Empty or invalid response from API');
        }
        
        if (!data.type || data.type !== 'FeatureCollection') {
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
        
        // Notify user with more details
        alert('Falha ao gerar isócrona precisa: ' + error.message + '\nUsando método alternativo baseado em distância.');
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
        let radiusInMeters;
        
        // Try to get area from isochrone properties
        if (data.features && 
            data.features[0] && 
            data.features[0].properties && 
            data.features[0].properties.area) {
            // Convert km² to m² to get an equivalent radius
            const areaInKm2 = data.features[0].properties.area;
            radiusInMeters = Math.sqrt(areaInKm2 * 1000000 / Math.PI);
        } else {
            // Fallback: use speed-based estimate
            const speedKmPerHour = transportSpeeds[selectedTransportMode];
            const distanceInKm = (speedKmPerHour * selectedMaxDistance) / 60;
            radiusInMeters = distanceInKm * 1000;
        }
        
        // Update statistics panel
        updateAreaStats(latlng, radiusInMeters, JSON.stringify(data));
        
        // Show statistics panel
        showStatisticsPanel();
        
        // Hide loading indicator
        hideLoading();
    } catch (error) {
        console.error('Error displaying isochrone:', error);
        
        // Use fallback circle buffer if display fails
        useCircleBufferFallback(latlng);
        
        // Hide loading indicator
        hideLoading();
        
        // Notify user
        alert('Erro ao exibir a isócrona. Usando método alternativo com base na distância.');
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
    
    // Atualizar painel de estatísticas
    updateAreaStats(latlng, distanceInMeters);
    
    // Mostrar painel de estatísticas
    showStatisticsPanel();
    
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

// Atualizar estatísticas da área na barra lateral
function updateAreaStats(latlng, radius, isochroneGeoJSON) {
    const statsDiv = document.getElementById('area-stats');
    
    // Criar dados do formulário para a requisição
    const formData = new FormData();
    formData.append('lat', latlng.lat);
    formData.append('lng', latlng.lng);
    formData.append('radius', radius);
    
    // Adicionar o GeoJSON da isócrona se disponível
    if (isochroneGeoJSON) {
        formData.append('isochrone', isochroneGeoJSON);
    }
    
    // Fazer requisição AJAX para o servidor
    fetch('includes/fetch_statistics.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            // Calcular o Accessibility Score
            const accessibilityScore = calculateAccessibilityScore(data.stats);
            
            // Exibir estatísticas
            let html = '<div class="stats-list">';
            
            // Adicionar o Accessibility Score no topo
            html += `
                <div class="stat-category accessibility-score">
                    <div class="stat-category-title">Accessibility Score</div>
                    <div class="score-display">
                        <div class="score-value">${accessibilityScore.score}</div>
                        <div class="score-label">${getScoreLabel(accessibilityScore.score)}</div>
                    </div>
                    <div class="score-description">
                        Baseado na disponibilidade de ${accessibilityScore.poiCount} serviços essenciais 
                        em ${selectedMaxDistance} minutos ${getTransportModeText()}.
                        ${getTimeAdjustmentText(selectedMaxDistance)}
                    </div>
                </div>
            `;
            
            // Agrupar estatísticas por categoria
            const categories = {
                'Saúde': ['hospitals', 'health_centers', 'pharmacies', 'dentists'],
                'Educação': ['schools', 'universities', 'kindergartens', 'libraries'],
                'Comércio e Serviços': ['supermarkets', 'malls', 'restaurants', 'atms'],
                'Transporte': ['bus_stops', 'train_stations', 'subway_stations', 'parking'],
                'Segurança e Emergência': ['police', 'fire_stations', 'civil_protection'],
                'Administração Pública': ['parish_councils', 'city_halls', 'post_offices'],
                'Cultura e Lazer': ['museums', 'theaters', 'sports', 'parks']
            };
            
            // Iterar pelas categorias
            for (const [categoryName, typeList] of Object.entries(categories)) {
                let categoryHasCheckedItem = false;
                let categoryHtml = `
                    <div class="stat-category">
                        <div class="stat-category-title">${categoryName}</div>
                `;
                
                // Verificar cada tipo de POI na categoria
                typeList.forEach(type => {
                    const checkbox = document.getElementById(`poi-${type}`);
                    // Somente incluir os POIs que estão selecionados
                    if (checkbox && checkbox.checked) {
                        categoryHasCheckedItem = true;
                        const count = data.stats[type] || 0;
                        categoryHtml += `
                            <div class="stat-item">
                                <span class="stat-label"><i class="fas fa-${poiTypes[type].icon} ${poiTypes[type].class}"></i> ${poiTypes[type].name}:</span>
                                <span class="stat-value">${count}</span>
                            </div>
                        `;
                    }
                });
                
                categoryHtml += '</div>';
                
                // Adicionar a categoria ao HTML apenas se tiver pelo menos um item selecionado
                if (categoryHasCheckedItem) {
                    html += categoryHtml;
                }
            }
            
            // Adicionar informações gerais da área
            html += `
                <div class="stat-category">
                    <div class="stat-category-title">Informações Gerais</div>
                    <div class="stat-item">
                        <span class="stat-label"><i class="fas fa-ruler-combined"></i> Área Total:</span>
                        <span class="stat-value">${data.stats.area_km2.toFixed(2)} km²</span>
                    </div>
                    <div class="stat-item">
                        <span class="stat-label"><i class="fas fa-users"></i> População (est.):</span>
                        <span class="stat-value">${data.stats.population_estimate || 'N/D'}</span>
                    </div>
            `;
            
            // Adicionar informações administrativas se disponíveis
            if (data.stats.freguesia && data.stats.freguesia !== 'Unknown') {
                html += `
                    <div class="stat-item">
                        <span class="stat-label"><i class="fas fa-map-marker-alt"></i> Freguesia:</span>
                        <span class="stat-value">${data.stats.freguesia}</span>
                    </div>
                `;
            }
            
            if (data.stats.concelho && data.stats.concelho !== 'Unknown') {
                html += `
                    <div class="stat-item">
                        <span class="stat-label"><i class="fas fa-city"></i> Concelho:</span>
                        <span class="stat-value">${data.stats.concelho}</span>
                    </div>
                `;
            }
            
            if (data.stats.distrito && data.stats.distrito !== 'Unknown') {
                html += `
                    <div class="stat-item">
                        <span class="stat-label"><i class="fas fa-map"></i> Distrito:</span>
                        <span class="stat-value">${data.stats.distrito}</span>
                    </div>
                `;
            }
            
            html += '</div>';
            
            // Adicionar informações demográficas do INE via GeoAPI.pt se disponíveis
            if (data.stats.demographics) {
                const demo = data.stats.demographics;
                
                html += `
                    <div class="stat-category">
                        <div class="stat-category-title">Dados Demográficos (INE)</div>
                `;
                
                if (demo.population !== null) {
                    html += `
                        <div class="stat-item">
                            <span class="stat-label"><i class="fas fa-users"></i> População:</span>
                            <span class="stat-value">${demo.population.toLocaleString('pt-PT')}</span>
                        </div>
                    `;
                }
                
                if (demo.households !== null) {
                    html += `
                        <div class="stat-item">
                            <span class="stat-label"><i class="fas fa-home"></i> Famílias:</span>
                            <span class="stat-value">${demo.households.toLocaleString('pt-PT')}</span>
                        </div>
                    `;
                }
                
                if (demo.buildings !== null) {
                    html += `
                        <div class="stat-item">
                            <span class="stat-label"><i class="fas fa-building"></i> Edifícios:</span>
                            <span class="stat-value">${demo.buildings.toLocaleString('pt-PT')}</span>
                        </div>
                    `;
                }
                
                if (demo.housing_units !== null) {
                    html += `
                        <div class="stat-item">
                            <span class="stat-label"><i class="fas fa-door-open"></i> Alojamentos:</span>
                            <span class="stat-value">${demo.housing_units.toLocaleString('pt-PT')}</span>
                        </div>
                    `;
                }
                
                if (demo.area_km2 !== null) {
                    html += `
                        <div class="stat-item">
                            <span class="stat-label"><i class="fas fa-ruler-combined"></i> Área:</span>
                            <span class="stat-value">${demo.area_km2.toFixed(2)} km²</span>
                        </div>
                    `;
                }
                
                if (demo.population_density !== null) {
                    html += `
                        <div class="stat-item">
                            <span class="stat-label"><i class="fas fa-chart-area"></i> Densidade Pop.:</span>
                            <span class="stat-value">${demo.population_density.toFixed(1)} hab/km²</span>
                        </div>
                    `;
                }
                
                if (demo.census_year !== null) {
                    html += `
                        <div class="stat-item census-year">
                            <span class="stat-label"><i class="fas fa-calendar"></i> Dados do Censo:</span>
                            <span class="stat-value">${demo.census_year}</span>
                        </div>
                    `;
                }
                
                html += `
                    <div class="stat-item data-source">
                        <span class="stat-label"><i class="fas fa-info-circle"></i> Fonte:</span>
                        <span class="stat-value">GeoAPI.pt / INE</span>
                    </div>
                `;
                
                html += '</div>';
            }
            
            html += '</div>';
            statsDiv.innerHTML = html;
            
            // Adicionar botão para ver mais detalhes da freguesia
            if (data.stats.freguesia && data.stats.freguesia !== 'Unknown' && 
                data.stats.concelho && data.stats.concelho !== 'Unknown') {
                
                const viewMoreBtn = document.createElement('button');
                viewMoreBtn.className = 'view-more-btn';
                viewMoreBtn.innerHTML = '<i class="fas fa-info-circle"></i> Ver Mais Detalhes';
                viewMoreBtn.onclick = function() {
                    // Criar URL para a nova página de localização
                    const url = `location.php?freguesia=${encodeURIComponent(data.stats.freguesia)}&concelho=${encodeURIComponent(data.stats.concelho)}&lat=${latlng.lat}&lng=${latlng.lng}`;
                    window.location.href = url;
                };
                
                statsDiv.appendChild(viewMoreBtn);
            }
        } else {
            statsDiv.innerHTML = '<p>Erro ao carregar estatísticas</p>';
        }
    })
    .catch(error => {
        console.error('Erro:', error);
        statsDiv.innerHTML = '<p>Erro ao carregar estatísticas</p>';
    });
}

// Calcular o Accessibility Score baseado nos POIs disponíveis
function calculateAccessibilityScore(stats) {
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
        police: 8,
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
        safety: ['police', 'fire_stations', 'civil_protection'],
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
    
    return {
        score: finalScore,
        poiCount: poiCount,
        categories: categoriesWithPOIs
    };
}

// Calcular o fator de ajuste baseado no tempo selecionado
function calculateTimeAdjustmentFactor(minutes) {
    // Tempo de referência é 15 minutos (o padrão para o conceito de cidade de 15 minutos)
    const referenceTime = 15;
    
    // Se o tempo for igual a 15 minutos, não há ajuste (fator = 1)
    if (minutes === referenceTime) return 1.0;
    
    // Para tempos menores que 15 minutos, aumentar o score (mais impressionante)
    // Para tempos maiores que 15 minutos, diminuir o score (menos impressionante)
    // Fórmula: referenceTime / minutes, com limites para evitar valores extremos
    
    if (minutes < referenceTime) {
        // Limite superior de ajuste: 1.5x (para 5 minutos ou menos)
        const factor = Math.min(referenceTime / minutes, 1.5);
        return factor;
    } else {
        // Limite inferior de ajuste: 0.6x (para 30 minutos ou mais)
        const factor = Math.max(referenceTime / minutes, 0.6);
        return factor;
    }
}

// Obter label descritivo para o score
function getScoreLabel(score) {
    if (score >= 90) return "Excelente";
    if (score >= 75) return "Muito Bom";
    if (score >= 60) return "Bom";
    if (score >= 45) return "Razoável";
    if (score >= 30) return "Limitado";
    if (score >= 15) return "Fraco";
    return "Muito Fraco";
}

// Obter texto do modo de transporte
function getTransportModeText() {
    switch (selectedTransportMode) {
        case 'walking':
            return 'a pé';
        case 'cycling':
            return 'de bicicleta';
        case 'driving':
            return 'de carro';
        default:
            return '';
    }
}

// Obter texto explicativo sobre o ajuste de tempo
function getTimeAdjustmentText(minutes) {
    const referenceTime = 15;
    
    if (minutes === referenceTime) {
        return '';
    } else if (minutes < referenceTime) {
        return `<span class="time-bonus">(Score aumentado por ser menos de 15 minutos)</span>`;
    } else {
        return `<span class="time-penalty">(Score reduzido por ser mais de 15 minutos)</span>`;
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