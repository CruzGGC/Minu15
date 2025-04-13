/**
 * Explorador de Cidade em 15 Minutos - Funcionalidade do Mapa
 * Gere a inicialização do mapa, geração de isócronas e exibição de POIs
 */

// Variáveis globais
let map;
let currentMarker;
let isochroneLayer;
let poiLayers = {};
let selectedPoi = null;
let selectedTransportMode = 'cycling'; // Bicicleta como modo padrão
let selectedMaxDistance = 15; // em minutos

// Mapeamento dos modos de transporte para os perfis da API Open Route Service
const orsProfiles = {
    walking: 'foot-walking',
    cycling: 'cycling-regular',
    driving: 'driving-car'
};

// Velocidades dos modos de transporte em km/h (usado como fallback)
const transportSpeeds = {
    walking: 5,  // A pé
    cycling: 15, // Bicicleta
    driving: 60  // Carro
};

// Tipos de POI e seus ícones
const poiTypes = {
    // Saúde
    hospitals: { 
        name: 'Hospitais', 
        icon: 'hospital', 
        class: 'poi-hospital',
        table: 'amenity',
        condition: "amenity = 'hospital'"
    },
    health_centers: { 
        name: 'Centros de Saúde', 
        icon: 'first-aid-kit', 
        class: 'poi-health',
        table: 'amenity',
        condition: "amenity = 'clinic' OR amenity = 'doctors'"
    },
    pharmacies: { 
        name: 'Farmácias', 
        icon: 'prescription-bottle-alt', 
        class: 'poi-pharmacy',
        table: 'amenity',
        condition: "amenity = 'pharmacy'"
    },
    dentists: { 
        name: 'Clínicas Dentárias', 
        icon: 'tooth', 
        class: 'poi-dentist',
        table: 'amenity',
        condition: "amenity = 'dentist'"
    },
    
    // Educação
    schools: { 
        name: 'Escolas Primárias e Secundárias', 
        icon: 'school', 
        class: 'poi-school',
        table: 'amenity',
        condition: "amenity = 'school'"
    },
    universities: { 
        name: 'Universidades e Institutos Superiores', 
        icon: 'graduation-cap', 
        class: 'poi-university',
        table: 'amenity',
        condition: "amenity IN ('university', 'college')"
    },
    kindergartens: { 
        name: 'Jardins de Infância e Creches', 
        icon: 'baby', 
        class: 'poi-kindergarten',
        table: 'amenity',
        condition: "amenity = 'kindergarten'"
    },
    libraries: { 
        name: 'Bibliotecas', 
        icon: 'book', 
        class: 'poi-library',
        table: 'amenity',
        condition: "amenity = 'library'"
    },
    
    // Comércio e serviços
    supermarkets: { 
        name: 'Supermercados', 
        icon: 'shopping-basket', 
        class: 'poi-supermarket',
        table: 'shop',
        condition: "shop IN ('supermarket', 'grocery', 'convenience')"
    },
    malls: { 
        name: 'Centros Comerciais', 
        icon: 'shopping-bag', 
        class: 'poi-mall',
        table: 'shop',
        condition: "shop = 'mall' OR amenity = 'marketplace'"
    },
    restaurants: { 
        name: 'Restaurantes e Cafés', 
        icon: 'utensils', 
        class: 'poi-restaurant',
        table: 'amenity',
        condition: "amenity IN ('restaurant', 'cafe', 'bar', 'pub', 'fast_food')"
    },
    atms: { 
        name: 'Caixas de Multibanco', 
        icon: 'money-bill-wave', 
        class: 'poi-atm',
        table: 'amenity',
        condition: "amenity = 'atm' OR amenity = 'bank'"
    },
    
    // Segurança e emergência
    police: { 
        name: 'Esquadras da Polícia', 
        icon: 'shield-alt', 
        class: 'poi-police',
        table: 'amenity',
        condition: "amenity = 'police'"
    },
    fire_stations: { 
        name: 'Quartéis de Bombeiros', 
        icon: 'fire-extinguisher', 
        class: 'poi-fire-station',
        table: 'amenity',
        condition: "amenity = 'fire_station'"
    },
    civil_protection: { 
        name: 'Proteção Civil', 
        icon: 'hard-hat', 
        class: 'poi-civil-protection',
        table: 'amenity',
        condition: "amenity = 'ranger_station' OR office = 'government' AND name ILIKE '%proteção civil%'"
    },
    
    // Administração pública
    parish_councils: { 
        name: 'Juntas de Freguesia', 
        icon: 'city', 
        class: 'poi-parish',
        table: 'office',
        condition: "office = 'government' AND name ILIKE '%junta de freguesia%'"
    },
    city_halls: { 
        name: 'Câmaras Municipais', 
        icon: 'landmark', 
        class: 'poi-city-hall',
        table: 'office',
        condition: "office = 'government' AND (name ILIKE '%câmara municipal%' OR name ILIKE '%camara municipal%')"
    },
    
    // Cultura e lazer
    museums: { 
        name: 'Museus', 
        icon: 'museum', 
        class: 'poi-museum',
        table: 'tourism',
        condition: "tourism = 'museum' OR amenity = 'museum'"
    },
    theaters: { 
        name: 'Teatros', 
        icon: 'theater-masks', 
        class: 'poi-theater',
        table: 'amenity',
        condition: "amenity = 'theatre'"
    },
    sports: { 
        name: 'Ginásios e Centros Desportivos', 
        icon: 'dumbbell', 
        class: 'poi-sport',
        table: 'leisure',
        condition: "leisure IN ('sports_centre', 'stadium', 'pitch', 'swimming_pool', 'fitness_centre', 'fitness_station')"
    },
    parks: { 
        name: 'Parques', 
        icon: 'tree', 
        class: 'poi-park',
        table: 'leisure',
        condition: "leisure IN ('park', 'garden', 'playground')"
    }
};

// Inicializar o mapa quando o DOM estiver carregado
document.addEventListener('DOMContentLoaded', function() {
    initMap();
    initControls();
});

// Inicializar o mapa Leaflet
function initMap() {
    // Coordenadas centrais de Portugal
    const portugalCenter = [39.5, -8.0];
    
    // Criar instância do mapa
    map = L.map('map').setView(portugalCenter, 7);
    
    // Adicionar camada base do OpenStreetMap
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors',
        maxZoom: 19
    }).addTo(map);
    
    // Inicializar grupos de camadas de POI vazios
    Object.keys(poiTypes).forEach(type => {
        poiLayers[type] = L.layerGroup().addTo(map);
    });
    
    // Adicionar evento de clique ao mapa
    map.on('click', function(e) {
        handleMapClick(e.latlng);
    });
    
    // Adicionar legenda ao mapa
    addLegend();
}

// Tratar clique no mapa para gerar isócrona
function handleMapClick(latlng) {
    // Limpar marcador existente, se houver
    if (currentMarker) {
        map.removeLayer(currentMarker);
    }
    
    // Adicionar um marcador na localização clicada
    currentMarker = L.marker(latlng).addTo(map);
    
    // Não gerar isócrona automaticamente - aguardar clique no botão Calcular
}

// Gerar polígono de isócrona usando Open Route Service
function generateIsochrone(latlng) {
    // Limpar isócrona existente, se houver
    if (isochroneLayer) {
        map.removeLayer(isochroneLayer);
    }
    
    // Mostrar indicador de carregamento
    showLoading();
    
    // Obter o perfil ORS correspondente ao modo de transporte selecionado
    const profile = orsProfiles[selectedTransportMode];
    
    // Preparar parâmetros para a API do Open Route Service
    const params = {
        locations: [[latlng.lng, latlng.lat]],
        range: [selectedMaxDistance * 60], // Converter minutos para segundos
        attributes: ['area'],
        area_units: 'km',
        range_type: 'time'
    };
    
    // URL da API para isócronas
    const url = `${ORS_API_URL}/v2/isochrones/${profile}`;
    
    // Realizar a chamada à API
    fetch(url, {
        method: 'POST',
        headers: {
            'Accept': 'application/json, application/geo+json, application/gpx+xml',
            'Content-Type': 'application/json',
            'Authorization': ORS_API_KEY
        },
        body: JSON.stringify(params)
    })
    .then(response => {
        if (!response.ok) {
            // Se houver erro, lançar para ser tratado pelo catch
            return response.json().then(errData => {
                throw new Error(`Erro na API: ${errData.error || response.statusText}`);
            });
        }
        return response.json();
    })
    .then(data => {
        // Processar a resposta e mostrar isócrona no mapa
        displayIsochrone(data, latlng);
        
        // Buscar POIs dentro da área
        fetchPOIsWithinIsochrone(latlng, data);
    })
    .catch(error => {
        console.error('Erro ao gerar isócrona:', error);
        
        // Em caso de erro, usar o fallback com Turf.js
        useCircleBufferFallback(latlng);
        
        // Esconder indicador de carregamento
        hideLoading();
        
        // Mostrar erro ao usuário
        alert('Não foi possível gerar a isócrona precisa. Usando método alternativo.');
    });
}

// Exibir isócrona no mapa
function displayIsochrone(data, latlng) {
    // Criar camada GeoJSON a partir da resposta da API
    isochroneLayer = L.geoJSON(data, {
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
    
    // Extrair área da isócrona se disponível
    let radiusInMeters;
    if (data.features && data.features[0] && data.features[0].properties && data.features[0].properties.area) {
        // Converter km² para m² para manter consistência com o resto do código
        const areaInKm2 = data.features[0].properties.area;
        radiusInMeters = Math.sqrt(areaInKm2 * 1000000 / Math.PI);
    } else {
        // Fallback: usar estimativa baseada na velocidade
        const speedKmPerHour = transportSpeeds[selectedTransportMode];
        const distanceInKm = (speedKmPerHour * selectedMaxDistance) / 60;
        radiusInMeters = distanceInKm * 1000;
    }
    
    // Atualizar painel de estatísticas
    updateAreaStats(latlng, radiusInMeters, JSON.stringify(data));
    
    // Mostrar painel de estatísticas
    showStatisticsPanel();
    
    // Esconder indicador de carregamento
    hideLoading();
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