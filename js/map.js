/**
 * Explorador de Cidade em 15 Minutos - Funcionalidade do Mapa
 * Lida com a inicialização do mapa, geração de isócronas e exibição de Pontos de Interesse (POIs)
 * 
 * @version 2.0
 */

// Variáveis globais
let map;
let currentMarker;
let isochroneLayer;
let poiLayers = {};
let selectedTransportMode = 'walking'; // Default mode: walking
let selectedMaxDistance = 15; // Default time: 15 minutes
let currentIsochroneData = null; // Store current isochrone data for POI requests
let currentTileLayer = null; // Store current tile layer
let selectedTileProvider = DEFAULT_TILE_PROVIDER; // Default tile provider from config

// Mapear modos de transporte para perfis da API OpenRouteService
const orsProfiles = {
    walking: 'foot-walking',
    cycling: 'cycling-regular',
    driving: 'driving-car'
};

// Velocidades dos modos de transporte (km/h) para cálculo de fallback se a API ORS falhar
const transportSpeeds = {
    walking: 5,  // A pé: 5 km/h
    cycling: 15, // De bicicleta: 15 km/h
    driving: 60  // De carro: 60 km/h
};

// Definição de tipos de POI com detalhes de exibição
const poiTypes = {
    // === Saúde ===
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
    
    // === Educação ===
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
    
    // === Comércio e Serviços ===
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
    
    // === Segurança e Emergência ===
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
    
    // === Administração Pública ===
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
    
    // === Cultura e Lazer ===
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
    
    // === Transportes ===
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

// Inicializar o mapa quando o DOM estiver totalmente carregado
document.addEventListener('DOMContentLoaded', function() {
    initMap();
    initControls();
    showInitialInstructions();
});

// Inicializar o mapa Leaflet
function initMap() {
    // Coordenadas centrais para Aveiro, Portugal
    const aveiroCenter = [40.6405, -8.6538];
    
    // Criar um novo mapa centrado em Aveiro com os controlos de zoom desativados
    map = L.map('map', {
        zoomControl: false  // Desativar controlos de zoom
    }).setView(aveiroCenter, 13);
    
    // Adicionar a camada de mapa selecionada
    updateMapTiles(selectedTileProvider);
    
    // Inicializar grupos de camadas de POI vazios
    Object.keys(poiTypes).forEach(type => {
        poiLayers[type] = L.layerGroup().addTo(map);
    });
    
    // Configurar evento de clique no mapa
    setupMapClickEvents();
    
    // Adicionar a legenda de POI
    addLegend();
}

// Atualizar camadas de mapa com base no provedor selecionado
function updateMapTiles(provider) {
    // Se existir uma camada de mapa, removê-la
    if (currentTileLayer) {
        map.removeLayer(currentTileLayer);
    }
    
    // Obter a configuração do provedor
    const tileConfig = MAP_TILE_PROVIDERS[provider] || MAP_TILE_PROVIDERS[DEFAULT_TILE_PROVIDER];
    
    // Criar e adicionar a nova camada de mapa
    currentTileLayer = L.tileLayer(tileConfig.url, {
        attribution: tileConfig.attribution,
        maxZoom: tileConfig.maxZoom
    }).addTo(map);
    
    // Update the selectedTileProvider variable
    selectedTileProvider = provider;
    
    // Update the map style selector UI if it exists
    updateMapStyleSelector();
}

// Atualizar os botões do seletor de estilo do mapa para mostrar o estilo ativo
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

// Configurar evento de clique no mapa
function setupMapClickEvents() {
    // Variável para rastrear se o tutorial está ativo
    let tutorialActive = false;
    
    // Definir estado ativo do tutorial ao mostrar instruções
    document.addEventListener('tutorialShown', function() {
        tutorialActive = true;
    });
    
    // Definir tutorial inativo ao fechar instruções
    document.addEventListener('tutorialClosed', function() {
        tutorialActive = false;
    });
    
    // Adicionar manipulador de clique ao mapa
    map.on('click', function(e) {
        // Se o tutorial estiver ativo, não processar o clique no mapa para ocultar a barra lateral
        if (tutorialActive && window.innerWidth > 768) {
            return;
        }
        
        // Obter coordenadas do clique
        const latlng = e.latlng;
        
        // Remover marcador existente, se houver
        if (currentMarker) {
            map.removeLayer(currentMarker);
        }
        
        // Adicionar novo marcador no local do clique
        currentMarker = L.marker(latlng).addTo(map);
        
        // Gerar isócrona para o local clicado
        generateIsochrone(latlng);
    });
}

// Gerar isócrona para o local selecionado
function generateIsochrone(latlng) {
    // Mostrar indicador de carregamento
    showLoading();
    
    // Limpar isócrona e marcador existentes
    if (isochroneLayer) {
        map.removeLayer(isochroneLayer);
    }
    
    // Limpar camadas de POI existentes
    Object.keys(poiTypes).forEach(type => {
        poiLayers[type].clearLayers();
    });
    
    // Adicionar marcador no local selecionado
    if (currentMarker) {
        map.removeLayer(currentMarker);
    }
    
    currentMarker = L.marker(latlng).addTo(map);
    
    // Obter o perfil do modo de transporte selecionado para OpenRouteService
    const profile = orsProfiles[selectedTransportMode];
    
    // Preparar parâmetros para a requisição da API OpenRouteService
    const params = {
        locations: [[latlng.lng, latlng.lat]],
        range: [selectedMaxDistance * 60], // Converter minutos para segundos
        range_type: 'time',
        attributes: ['area'],
        area_units: 'km',
        smoothing: 0.5
    };
    
    // Usar o nosso proxy PHP em vez da chamada direta à API ORS
    const formData = new FormData();
    formData.append('endpoint', `/v2/isochrones/${profile}`);
    formData.append('data', JSON.stringify(params));
    
    console.log(`A gerar isócrona para o modo ${profile}, ${selectedMaxDistance} minutos`);
    
    // Fazer requisição ao nosso proxy
    fetch('includes/proxy_ors.php', {
        method: 'POST',
        body: formData
    })
    .then(response => {
        if (!response.ok) {
            throw new Error(`Erro HTTP! Estado: ${response.status}`);
        }
        return response.json();
    })
    .then(data => {
        // Primeiro verificar se a resposta indica um erro da API
        if (data.success === false) {
            // Esta é uma resposta de erro do nosso proxy PHP
            const errorMessage = data.message || 'Erro desconhecido da API';
            throw new Error(`Erro da API: ${errorMessage}`);
        }
        
        console.log('Resposta da API recebida:', data);
        
        // Validar resposta GeoJSON
        if (!data.type) {
            throw new Error('Propriedade de tipo GeoJSON em falta');
        }
        
        if (data.type !== 'FeatureCollection') {
            throw new Error(`Tipo GeoJSON inválido: ${data.type || 'indefinido'}`);
        }
        
        if (!data.features || !Array.isArray(data.features) || data.features.length === 0) {
            throw new Error('Array de funcionalidades GeoJSON em falta ou vazio');
        }
        
        const feature = data.features[0];
        if (!feature.geometry || !feature.geometry.coordinates) {
            throw new Error('Geometria ou coordenadas em falta na funcionalidade GeoJSON');
        }
        
        if (!feature.geometry.type || feature.geometry.type !== 'Polygon') {
            throw new Error(`Tipo de geometria inválido: ${feature.geometry.type || 'indefinido'}`);
        }
        
        console.log('Resposta GeoJSON validada com sucesso');
        
        // Processar resposta e exibir isócrona
        currentIsochroneData = data;
        displayIsochrone(data, latlng);
        
        // Agora buscar POIs dentro da área da isócrona
        fetchPOIsWithinIsochrone(latlng, data);
    })
    .catch(error => {
        console.error('Erro ao gerar isócrona:', error);
        console.error('Detalhes do erro:', error.message);
        
        // Usar buffer de círculo de fallback se a API falhar
        useCircleBufferFallback(latlng);
        
        // Ocultar indicador de carregamento
        hideLoading();
    });
}

// Exibir a isócrona no mapa
function displayIsochrone(data, latlng) {
    try {
        console.log('A exibir isócrona com dados:', data);
        
        // Criar camada GeoJSON a partir da resposta da API
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
        
        // Ajustar a vista do mapa aos limites da isócrona
        map.fitBounds(isochroneLayer.getBounds());
        
        // Calcular raio para estatísticas
        let radius = null;
        
        // Se tivermos uma isócrona, calcular o raio aproximado
        if (data && data.features && data.features[0]) {
            // Calcular a área da isócrona
            const area = turf.area(data.features[0]);
            // Raio aproximado da área (assumindo forma circular): r = sqrt(area / π)
            radius = Math.sqrt(area / Math.PI);
        } else {
            // Fallback para cálculo baseado na velocidade
            const speedKmPerHour = transportSpeeds[selectedTransportMode];
            const distanceInKm = (speedKmPerHour * selectedMaxDistance) / 60;
            radius = distanceInKm * 1000; // Converter para metros
        }
        
        // Mostrar o painel de estatísticas
        showStatisticsPanel();
        
        // Atualizar estatísticas de área com os dados da isócrona
        updateAreaStats(latlng, radius, data);
        
        // Buscar POIs dentro da área da isócrona
        fetchPOIs(latlng);
        
        // Ocultar indicador de carregamento
        hideLoading();
        
    } catch (error) {
        console.error('Erro ao exibir isócrona:', error);
        
        // Fallback para buffer de círculo simples se a exibição da isócrona falhar
        useCircleBufferFallback(latlng);
        
        // Ocultar indicador de carregamento
        hideLoading();
    }
}

/**
 * Mostrar POIs dentro da área dada
 * Esta função é um invólucro para fetchPOIs para manter a compatibilidade
 */
function showPOIsInArea(data) {
    // Se tivermos um marcador atual, usar a sua posição para buscar POIs
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
                    console.error(`Erro ao buscar POIs do tipo ${type}:`, error);
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
        console.error("Erro ao atualizar estatísticas de área:", error);
        // Continuar com o fluxo da aplicação mesmo que as estatísticas falhem
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
        // Atualizar painel de estatísticas - envolvido em try/catch para evitar que erros quebrem a funcionalidade do mapa
        updateAreaStats(latlng, distanceInMeters, buffered);
    } catch (statsError) {
        console.error('Erro ao atualizar estatísticas de área no modo fallback:', statsError);
        // Continuar com o fluxo da aplicação mesmo que as estatísticas falhem
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
                throw new Error(`Erro HTTP! Estado: ${response.status}`);
            }
            return response.text(); // Obter como texto primeiro para verificar se é JSON válido
        })
        .then(text => {
            // Tentar analisar a resposta como JSON
            try {
                const data = JSON.parse(text);
                
                if (data.success) {
                    // Adicionar POIs ao mapa
                    addPOIsToMap(type, data.pois);
                    resolve(data);
                } else {
                    console.error(`Erro ao buscar POIs do tipo ${type}:`, data.message);
                    if (data.debug) {
                        console.debug(`Informação de depuração para ${type}:`, data.debug);
                    }
                    reject(new Error(data.message));
                }
            } catch (parseError) {
                console.error(`Erro ao analisar JSON para ${type}:`, parseError);
                console.debug('Resposta bruta:', text);
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
 * Atualizar estatísticas de área com dados da API
 */
function updateAreaStats(latlng, radius, isochroneGeoJSON) {
    // Mostrar indicador de carregamento no painel de estatísticas
    const statsContent = document.getElementById('stats-content');
    if (statsContent) {
        statsContent.innerHTML = '<div class="loading-spinner"></div>';
    }
    
    // Preparar dados para a requisição
    const requestData = new FormData();
    requestData.append('lat', latlng.lat);
    requestData.append('lng', latlng.lng);
    
    // Adicionar GeoJSON da isócrona se disponível
    if (isochroneGeoJSON) {
        // Verificar se isochroneGeoJSON já é uma string ou precisa ser stringificado
        const geoJsonString = typeof isochroneGeoJSON === 'string' 
            ? isochroneGeoJSON 
            : JSON.stringify(isochroneGeoJSON);
        requestData.append('isochrone', geoJsonString);
    }
    
    // Adicionar raio como fallback
    if (radius) {
        requestData.append('radius', radius);
    }
    
    // Adicionar tipos de POI selecionados
    const selectedPOIs = [];
    Object.keys(poiTypes).forEach(type => {
        const checkbox = document.getElementById(`poi-${type}`);
        if (checkbox && checkbox.checked) {
            selectedPOIs.push(type);
        }
    });
    requestData.append('selected_pois', JSON.stringify(selectedPOIs));
    
    // Fazer requisição à API
    fetch('includes/fetch_statistics.php', {
        method: 'POST',
        body: requestData
    })
    .then(response => {
        if (!response.ok) {
            throw new Error(`Erro HTTP! Estado: ${response.status}`);
        }
        return response.text(); // Obter como texto primeiro para verificar se é JSON válido
    })
    .then(text => {
        try {
            const data = JSON.parse(text);
            
            if (data.success) {
                // Exibir estatísticas
                displayAreaStats(data.stats, latlng);
            } else {
                console.error('Erro ao buscar estatísticas de área:', data.message);
                // Exibir erro no painel de estatísticas, mas ainda mostrar informações básicas
                displayAreaStats(null, latlng);
            }
        } catch (parseError) {
            console.error('Erro ao analisar JSON de estatísticas:', parseError);
            console.debug('Resposta bruta:', text);
            // Exibir erro no painel de estatísticas, mas ainda mostrar informações básicas
            displayAreaStats(null, latlng);
        }
    })
    .catch(error => {
        console.error('Erro ao buscar estatísticas de área:', error);
        // Lidar com o erro graciosamente - ainda exibir a isócrona, mas com estatísticas limitadas
        displayAreaStats(null, latlng);
    });
}

/**
 * Exibir estatísticas de área no painel
 */
function displayAreaStats(stats, latlng) {
    // Obter o elemento de conteúdo das estatísticas
    const statsContent = document.getElementById('stats-content');
    
    // Verificar se as estatísticas são indefinidas ou nulas
    if (!stats) {
        // Exibir informações básicas sem estatísticas
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
    
    // Calcular pontuação de acessibilidade
    const accessibilityScore = calculateAccessibilityScore(stats);
    
    // Criar HTML para as estatísticas
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
            <p class="freguesia-info">A carregar informações do município...</p>
        </div>
    `;
    
    // Adicionar estatísticas de POI por categoria
    const poiCategories = {
        'Saúde': ['hospitals', 'health_centers', 'pharmacies', 'dentists'],
        'Educação': ['schools', 'universities', 'kindergartens', 'libraries'],
        'Comércio e Serviços': ['supermarkets', 'malls', 'restaurants', 'atms'],
        'Segurança e Serviços Públicos': ['police', 'police_stations', 'fire_stations', 'civil_protection'],
        'Administração Pública': ['city_halls', 'post_offices'],
        'Cultura e Lazer': ['museums', 'theaters', 'sports', 'parks'],
        'Transportes': ['bus_stops', 'train_stations', 'subway_stations', 'parking']
    };
    
    // Adicionar seção de estatísticas de infraestrutura
    html += `<div class="stats-section infrastructure-stats"><h3>Infraestruturas</h3>`;
    
    // Adicionar estatísticas de POI para cada categoria
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
    
    // Fechar seção de infraestrutura
    html += `</div>`;
    
    // Se nenhum dado de infraestrutura foi encontrado
    if (!hasInfrastructureData) {
        html = html.replace('<div class="stats-section infrastructure-stats"><h3>Infraestruturas</h3></div>', 
            '<div class="stats-section infrastructure-stats"><h3>Infraestruturas</h3><p>Não foram encontradas infraestruturas nesta área.</p></div>');
    }
    
    // Definir o conteúdo HTML
    statsContent.innerHTML = html;
    
    // Buscar informações do município automaticamente
    identifyMunicipio(latlng);
}

// Adicionar uma nova função para lidar apenas com a identificação do município
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
    
    // Atualizar texto de carregamento com base no nível de detalhe selecionado
    let loadingText = 'A identificar município...';
    if (detailLevel === 'freguesia') {
        loadingText = 'A identificar freguesia...';
    } else if (detailLevel === 'distrito') {
        loadingText = 'A identificar distrito...';
    }
    municipioInfo.textContent = loadingText;
    
    // Primeiro tentar com o proxy
    fetch(`includes/geoapi_proxy.php?endpoint=${encodeURIComponent(`gps/${latlng.lat},${latlng.lng}/base/detalhes`)}`)
        .then(response => {
            // Verificar se a resposta é JSON válido
            const contentType = response.headers.get('content-type');
            if (!contentType || !contentType.includes('application/json')) {
                // Não é uma resposta JSON, ler texto e registá-lo
                return response.text().then(text => {
                    console.error('Resposta não JSON:', text);
                    throw new Error('Formato de resposta inválido - não é JSON');
                });
            }
            return response.json();
        })
        .then(data => {
            console.log('Dados de resposta da API:', data);
            
            // Criar um objeto de dados de localização
            const locationData = {
                nomeMunicipio: data.concelho || (data.detalhesMunicipio ? data.detalhesMunicipio.nome : ''),
                freguesia: data.freguesia || (data.detalhesFreguesia ? data.detalhesFreguesia.nome : ''),
                distrito: data.distrito || (data.detalhesMunicipio ? data.detalhesMunicipio.distrito : '')
            };
            
            // Verificar se temos os dados para o nível de detalhe selecionado
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
            console.error('Erro ao identificar localização:', error);
            
            // Tentar buscar diretamente da API
            console.log('A tentar buscar diretamente da API...');
            
            // Tentar API direta como fallback
            fetch(`http://json.localhost:8080/gps/${latlng.lat},${latlng.lng}/base/detalhes`)
                .then(response => response.json())
                .then(data => {
                    console.log('Resposta direta da API:', data);
                    
                    // Criar um objeto de dados de localização
                    const locationData = {
                        nomeMunicipio: data.concelho || (data.detalhesMunicipio ? data.detalhesMunicipio.nome : ''),
                        freguesia: data.freguesia || (data.detalhesFreguesia ? data.detalhesFreguesia.nome : ''),
                        distrito: data.distrito || (data.detalhesMunicipio ? data.detalhesMunicipio.distrito : '')
                    };
                    
                    // Verificar se temos os dados para o nível de detalhe selecionado
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
                    console.error('Erro com a chamada direta da API:', directError);
                    municipioInfo.textContent = 'Erro ao identificar localização';
                    municipioInfo.classList.remove('loading');
                });
        });
}

// Calcular o Accessibility Score baseado nos POIs disponíveis
function calculateAccessibilityScore(stats) {
    // Verificar se as estatísticas são indefinidas ou nulas
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
    
    // Obter pesos personalizados do painel de configurações, se disponível
    const weights = { ...defaultWeights };
    
    // Atualizar pesos das configurações do utilizador
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
    
    // Evitar divisão por zero
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
    
    // Adicionar verificação de serviços essenciais - se não houver serviços essenciais, reduzir pontuação
    const hasEssentialServices = 
        (stats.hospitals > 0 || stats.health_centers > 0 || stats.pharmacies > 0) && // Saúde
        (stats.supermarkets > 0 || stats.restaurants > 0) && // Alimentação
        (stats.schools > 0 || stats.kindergartens > 0); // Educação
    
    if (!hasEssentialServices) {
        // Se faltarem serviços essenciais, reduzir pontuação em 20%
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
 * Calcular fator de ajuste de tempo para a pontuação de acessibilidade
 * Para tempos menores que 15 minutos, aumentar a pontuação (mais impressionante ter POIs em menos tempo)
 * Para tempos maiores que 15 minutos, diminuir a pontuação (menos impressionante ter POIs em mais tempo)
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
 * Obter texto explicando o fator de ajuste de tempo
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
 * Obter rótulo de pontuação com base na pontuação numérica
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
 * Obter texto do modo de transporte para exibição
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
    // Não precisamos mais de L.control legend, pois temos uma personalizada na UI
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

// Mostrar instruções iniciais para ajudar novos utilizadores
function showInitialInstructions() {
    // Verificar se o utilizador já viu o tutorial antes
    if (localStorage.getItem('minu15_instructions_seen') === 'true') {
        return;
    }
    
    // Criar o contêiner do tutorial
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
    
    // Criar conteúdo do tutorial
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
                            <strong style="font-weight: 600; color: #2c3e50;">Configure as suas preferências</strong> 
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
    
    // Adicionar ao documento
    document.body.appendChild(tutorialBox);
    
    // Adicionar animação de entrada
    setTimeout(() => {
        tutorialBox.style.opacity = '1';
        tutorialBox.style.transform = 'translate(-50%, -50%) scale(1)';
        
        // Mostrar uma dica visual de clique no mapa após o tutorial aparecer
        setTimeout(() => {
            showMapClickAnimation();
        }, 1000);
        
        // Disparar evento de que o tutorial é mostrado
        document.dispatchEvent(new Event('tutorialShown'));
    }, 100);
    
    // Impedir que cliques no tutorial se propaguem para o mapa
    tutorialBox.addEventListener('click', function(event) {
        event.stopPropagation();
    });
    
    // Adicionar efeito de hover ao botão
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
    
    // Evento do botão de fechar
    tutorialBtn.addEventListener('click', function(event) {
        event.stopPropagation();
        
        // Adicionar animação de saída
        tutorialBox.style.opacity = '0';
        tutorialBox.style.transform = 'translate(-50%, -50%) scale(0.9)';
        
        // Salvar preferência se a caixa de seleção estiver marcada
        if (document.getElementById('dont-show-again').checked) {
            localStorage.setItem('minu15_instructions_seen', 'true');
        }
        
        // Remover após a conclusão da animação
        setTimeout(() => {
            document.getElementById('instruction-box').remove();
            
            // Correção crítica para o problema de desaparecimento da barra lateral
            // Forçar a barra lateral a ser visível no desktop
            if (window.innerWidth > 768) {
                const panel = document.getElementById('overlay-panel');
                if (panel) {
                    // Primeiro remover qualquer classe ou estilo problemático que possa estar a ocultá-la
                    panel.classList.remove('mobile-active');
                    
                    // Aplicar estilos diretos para garantir a visibilidade
                    panel.style.display = 'block';
                    panel.style.transform = 'none';
                    panel.style.visibility = 'visible';
                    panel.style.opacity = '1';
                    panel.style.left = '20px';
                    panel.style.zIndex = '999';
                    panel.style.position = 'absolute';
                    
                    // Aplicar estilos de substituição adicionais através de uma regra CSS
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
                    
                    // Adicionar o elemento de estilo se ainda não existir
                    if (!document.getElementById('sidebar-fix-style')) {
                        document.head.appendChild(styleElement);
                    }
                    
                    // Definir várias correções atrasadas para capturar quaisquer condições de corrida
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
            
            // Disparar evento de que o tutorial foi fechado
            document.dispatchEvent(new Event('tutorialClosed'));
            
            // Destacar elementos chave da UI após o tutorial fechar
            highlightKeyElements();
        }, 300);
    });
    
    // Evento "Não mostrar novamente"
    document.getElementById('dont-show-again').addEventListener('click', function(event) {
        // Parar a propagação do evento para evitar disparar o clique no mapa
        event.stopPropagation();
    });
}

/**
 * Mostra uma animação visual sugerindo para clicar no mapa
 */
function showMapClickAnimation() {
    // Criar o elemento do cursor
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
    
    // Criar o elemento do efeito de clique
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
    
    // Obter um ponto na área centro-direita do mapa
    const mapElement = document.getElementById('map');
    const mapRect = mapElement.getBoundingClientRect();
    const startX = mapRect.left + mapRect.width * 0.65;
    const startY = mapRect.top + mapRect.height * 0.4;
    const targetX = startX + 50;
    const targetY = startY + 30;
    
    // Posicionar o cursor no ponto inicial
    cursor.style.left = startX + 'px';
    cursor.style.top = startY + 'px';
    
    // Animar o cursor para a posição alvo
    setTimeout(() => {
        cursor.style.transition = 'all 1s ease-in-out';
        cursor.style.left = targetX + 'px';
        cursor.style.top = targetY + 'px';
        
        // Mostrar efeito de clique na posição alvo
        setTimeout(() => {
            cursor.style.transform = 'scale(0.8)';
            
            // Posicionar e animar o efeito de clique
            clickEffect.style.left = targetX + 'px';
            clickEffect.style.top = targetY + 'px';
            clickEffect.style.transition = 'all 0.5s ease-out';
            clickEffect.style.transform = 'translate(-50%, -50%) scale(1)';
            clickEffect.style.opacity = '1';
            
            // Desvanecer efeito de clique
            setTimeout(() => {
                clickEffect.style.transform = 'translate(-50%, -50%) scale(1.5)';
                clickEffect.style.opacity = '0';
                
                // Limpar após a animação
                setTimeout(() => {
                    cursor.remove();
                    clickEffect.remove();
                }, 500);
            }, 500);
        }, 1000);
    }, 500);
}

/**
 * Destaca elementos chave da UI para guiar os utilizadores onde começar
 */
function highlightKeyElements() {
    // Destacar o painel lateral
    const panel = document.querySelector('.overlay-panel');
    const transportOptions = document.querySelector('.transport-mode');
    
    // Criar elementos de efeito de destaque
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
    
    // Adicionar o destaque ao painel
    panel.style.position = 'relative';
    panel.appendChild(panelHighlight);
    
    // Criar um destaque para as opções de modo de transporte
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
    
    // Adicionar o destaque às opções de transporte
    transportOptions.style.position = 'relative';
    transportOptions.appendChild(transportHighlight);
    
    // Animar o destaque para o painel
    setTimeout(() => {
        panelHighlight.style.opacity = '1';
        
        // Destacar opções de transporte após o painel
        setTimeout(() => {
            panelHighlight.style.opacity = '0';
            transportHighlight.style.opacity = '1';
            
            // Remover os destaques após 2 segundos
            setTimeout(() => {
                transportHighlight.style.opacity = '0';
                
                // Remover os elementos de destaque após o desvanecimento
                setTimeout(() => {
                    panelHighlight.remove();
                    transportHighlight.remove();
                }, 500);
            }, 2000);
        }, 2000);
    }, 500);
    
    // Adicionar a classe de animação de pulso se não existir
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