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
let selectedPoi = null;
let selectedTransportMode = 'cycling'; // Default mode: cycling
let selectedMaxDistance = 15; // Default time: 15 minutes
let currentIsochroneData = null; // Store current isochrone data for POI requests

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
    }
};

// Initialize the map when the DOM is fully loaded
document.addEventListener('DOMContentLoaded', function() {
    initMap();
    initControls();
});

// Initialize the Leaflet map
function initMap() {
    // Center coordinates for Portugal
    const portugalCenter = [39.5, -8.0];
    
    // Create a new map centered on Portugal
    map = L.map('map').setView(portugalCenter, 7);
    
    // Add OpenStreetMap tile layer
    L.tileLayer(MAP_TILES_URL, {
        attribution: MAP_TILES_ATTRIBUTION,
        maxZoom: 19
    }).addTo(map);
    
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

// Handle map click by placing a marker
function handleMapClick(latlng) {
    // Clear existing marker if present
    if (currentMarker) {
        map.removeLayer(currentMarker);
    }
    
    // Add a new marker at clicked location
    currentMarker = L.marker(latlng).addTo(map);
    
    // Don't automatically generate isochrone - wait for Calculate button
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
            iconSize: [24, 24],
            iconAnchor: [12, 12]
        });
        
        // Criar marcador com ícone personalizado
        const marker = L.marker([poi.latitude, poi.longitude], {
            icon: icon
        });
        
        // Adicionar popup com informações básicas
        marker.bindPopup(`<strong>${poi.name || poiInfo.name}</strong><br>Clique para mais detalhes`);
        
        // Adicionar evento de clique para mostrar detalhes
        marker.on('click', function() {
            selectedPoi = poi;
            showPoiDetails(poi);
            
            // Mostrar painel de detalhes do POI
            showPoiDetailsPanel();
        });
        
        // Adicionar ao grupo de camadas
        marker.addTo(poiLayers[type]);
    });
}

// Mostrar detalhes do POI na barra lateral
function showPoiDetails(poi) {
    const poiInfoDiv = document.getElementById('poi-info');
    
    // Criar HTML para os detalhes do POI
    let html = `
        <div class="poi-detail">
            <div class="poi-title">${poi.name || 'Sem nome'}</div>
            <div>${poi.type}</div>
        </div>
    `;
    
    // Adicionar endereço se disponível
    if (poi.address) {
        html += `
            <div class="poi-detail">
                <div class="poi-title">Endereço</div>
                <div>${poi.address}</div>
            </div>
        `;
    }
    
    // Adicionar propriedades adicionais se disponíveis
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
    
    // Adicionar botão de direções
    html += `
        <div class="poi-detail">
            <button class="direction-button" onclick="openDirections(${poi.latitude}, ${poi.longitude})">
                <i class="fas fa-directions"></i> Obter Direções
            </button>
        </div>
    `;
    
    // Definir o HTML para a div
    poiInfoDiv.innerHTML = html;
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
            // Exibir estatísticas
            let html = '<div class="stats-list">';
            
            // Agrupar estatísticas por categoria
            const categories = {
                'Saúde': ['hospitals', 'health_centers', 'pharmacies', 'dentists'],
                'Educação': ['schools', 'universities', 'kindergartens', 'libraries'],
                'Comércio e Serviços': ['supermarkets', 'malls', 'restaurants', 'atms'],
                'Segurança e Emergência': ['police', 'fire_stations', 'civil_protection'],
                'Administração Pública': ['parish_councils', 'city_halls'],
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
                                <span class="stat-label">${poiTypes[type].name}:</span>
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
                        <span class="stat-label">Área Total:</span>
                        <span class="stat-value">${data.stats.area_km2.toFixed(2)} km²</span>
                    </div>
                    <div class="stat-item">
                        <span class="stat-label">População (est.):</span>
                        <span class="stat-value">${data.stats.population_estimate || 'N/D'}</span>
                    </div>
            `;
            
            // Adicionar informações da freguesia se disponíveis
            if (data.stats.parish && data.stats.parish !== 'Unknown') {
                html += `
                    <div class="stat-item">
                        <span class="stat-label">Freguesia:</span>
                        <span class="stat-value">${data.stats.parish}</span>
                    </div>
                `;
            }
            
            html += '</div></div>';
            statsDiv.innerHTML = html;
        } else {
            statsDiv.innerHTML = '<p>Erro ao carregar estatísticas</p>';
        }
    })
    .catch(error => {
        console.error('Erro:', error);
        statsDiv.innerHTML = '<p>Erro ao carregar estatísticas</p>';
    });
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

// Mostrar painel de detalhes do POI
function showPoiDetailsPanel() {
    const poiDetailsPanel = document.querySelector('.poi-details-panel');
    if (poiDetailsPanel) {
        poiDetailsPanel.classList.add('visible');
    }
}