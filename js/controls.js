/**
 * Explorador de Cidade em 15 Minutos - Funcionalidade de Controles
 * Lida com interações dos controles da UI e seus efeitos no mapa
 */

// Inicializar controles quando o DOM estiver carregado
function initControls() {
    // Inicializar painéis retráteis
    initCollapsiblePanels();
    
    // Inicializar seletor de modo de transporte
    initTransportModeSelector();
    
    // Inicializar slider de distância
    initDistanceSlider();
    
    // Inicializar checkboxes de POI
    initPoiCheckboxes();
    
    // Inicializar botão de calcular
    initCalculateButton();
    
    // Inicializar funcionalidade de pesquisa
    initSearchBox();
    
    // Inicializar botões de fechar painéis
    initPanelCloseButtons();
}

// Inicializar painéis retráteis
function initCollapsiblePanels() {
    // Inicializar cabeçalhos de painéis principais
    document.querySelectorAll('.panel-header').forEach(header => {
        header.addEventListener('click', function() {
            const content = this.nextElementSibling;
            if (content && content.classList.contains('panel-content')) {
                content.classList.toggle('expanded');
                const arrow = this.querySelector('.dropdown-arrow');
                if (arrow) {
                    arrow.classList.toggle('up');
                }
            }
        });
    });
    
    // Inicializar cabeçalhos de categorias POI
    document.querySelectorAll('.category-header').forEach(header => {
        header.addEventListener('click', function(e) {
            // Evitar que o clique se propague para o painel pai
            e.stopPropagation();
            
            const content = this.nextElementSibling;
            if (content && content.classList.contains('category-content')) {
                content.classList.toggle('expanded');
                const arrow = this.querySelector('.dropdown-arrow');
                if (arrow) {
                    arrow.classList.toggle('up');
                }
            }
        });
    });
    
    // Começar com o painel de POI expandido
    const poiContent = document.getElementById('poi-content');
    if (poiContent) {
        poiContent.classList.add('expanded');
        const arrow = document.querySelector('#poi-header .dropdown-arrow');
        if (arrow) {
            arrow.classList.add('up');
        }
    }
    
    // Começar com a primeira categoria expandida (Saúde)
    const firstCategory = document.querySelector('.category-content');
    if (firstCategory) {
        firstCategory.classList.add('expanded');
        const arrow = firstCategory.previousElementSibling.querySelector('.dropdown-arrow');
        if (arrow) {
            arrow.classList.add('up');
        }
    }
}

// Inicializar seletor de modo de transporte
function initTransportModeSelector() {
    const transportOptions = document.querySelectorAll('.transport-option');
    
    transportOptions.forEach(option => {
        option.addEventListener('click', function() {
            // Remover classe ativa de todas as opções
            transportOptions.forEach(opt => {
                opt.classList.remove('active');
            });
            
            // Adicionar classe ativa à opção selecionada
            this.classList.add('active');
            
            // Atualizar o modo de transporte selecionado
            selectedTransportMode = this.getAttribute('data-mode');
            
            // Atualizar o mapa se houver um marcador atual
            if (currentMarker) {
                generateIsochrone(currentMarker.getLatLng());
                fetchPOIs(currentMarker.getLatLng());
            }
        });
    });
    
    // Definir modo de transporte inicial
    const activeModeElement = document.querySelector('.transport-option.active');
    if (activeModeElement) {
        selectedTransportMode = activeModeElement.getAttribute('data-mode');
    }
}

// Inicializar slider de distância
function initDistanceSlider() {
    const distanceSlider = document.getElementById('max-distance');
    const distanceValue = document.getElementById('distance-value');
    
    // Definir valor inicial de distância
    distanceValue.textContent = distanceSlider.value + ' minutos';
    
    // Adicionar listener de evento ao slider
    distanceSlider.addEventListener('input', function() {
        // Atualizar valor exibido
        distanceValue.textContent = this.value + ' minutos';
        
        // Atualizar distância máxima selecionada
        selectedMaxDistance = parseInt(this.value);
        
        // Atualizar o mapa se houver um marcador atual
        if (currentMarker) {
            generateIsochrone(currentMarker.getLatLng());
            fetchPOIs(currentMarker.getLatLng());
        }
    });
}

// Inicializar checkboxes de POI
function initPoiCheckboxes() {
    Object.keys(poiTypes).forEach(type => {
        const checkbox = document.getElementById(`poi-${type}`);
        if (checkbox) {
            checkbox.addEventListener('change', () => handlePoiToggle(type));
        }
    });
}

// Tratar alternância de tipo de POI
function handlePoiToggle(type) {
    const checkbox = document.getElementById(`poi-${type}`);
    const isChecked = checkbox.checked;
    
    // Mostrar ou ocultar a camada com base no estado da checkbox
    if (isChecked) {
        if (currentMarker) {
            // Buscar POIs deste tipo se um ponto estiver selecionado
            const latlng = currentMarker.getLatLng();
            const speedKmPerHour = transportSpeeds[selectedTransportMode];
            const distanceInKm = (speedKmPerHour * selectedMaxDistance) / 60;
            const radiusInMeters = distanceInKm * 1000;
            
            fetchPOIsByType(type, latlng, radiusInMeters);
        }
        
        // Garantir que a camada seja adicionada ao mapa
        if (!map.hasLayer(poiLayers[type])) {
            map.addLayer(poiLayers[type]);
        }
    } else {
        // Remover a camada do mapa
        if (map.hasLayer(poiLayers[type])) {
            map.removeLayer(poiLayers[type]);
        }
    }
}

// Inicializar botão de calcular
function initCalculateButton() {
    const calculateButton = document.querySelector('.calculate-button');
    if (calculateButton) {
        calculateButton.addEventListener('click', function() {
            if (currentMarker) {
                generateIsochrone(currentMarker.getLatLng());
                fetchPOIs(currentMarker.getLatLng());
                
                // Mostrar painel de estatísticas
                showStatisticsPanel();
            } else {
                alert('Por favor, selecione primeiro uma localização no mapa');
            }
        });
    }
}

// Inicializar caixa de pesquisa
function initSearchBox() {
    const searchBox = document.querySelector('.search-box');
    const searchButton = document.querySelector('.search-button');
    
    if (searchBox && searchButton) {
        // Pesquisar ao clicar no botão
        searchButton.addEventListener('click', function() {
            performSearch(searchBox.value);
        });
        
        // Pesquisar ao pressionar Enter
        searchBox.addEventListener('keydown', function(e) {
            if (e.key === 'Enter') {
                performSearch(this.value);
            }
        });
    }
}

// Realizar pesquisa de localização
function performSearch(searchTerm) {
    if (!searchTerm.trim()) {
        return;
    }
    
    // Mostrar indicador de carregamento
    showLoading();
    
    // Usar Nominatim para geocodificação (serviço de geocodificação do OpenStreetMap)
    const searchUrl = `https://nominatim.openstreetmap.org/search?format=json&q=${encodeURIComponent(searchTerm)},Portugal&limit=1`;
    
    fetch(searchUrl)
        .then(response => response.json())
        .then(data => {
            hideLoading();
            
            if (data && data.length > 0) {
                const result = data[0];
                const latlng = L.latLng(result.lat, result.lon);
                
                // Definir visualização do mapa para a localização encontrada
                map.setView(latlng, 15);
                
                // Criar marcador na localização encontrada
                if (currentMarker) {
                    map.removeLayer(currentMarker);
                }
                currentMarker = L.marker(latlng).addTo(map);
                
                // Gerar isócrona
                generateIsochrone(latlng);
                
                // Buscar POIs
                fetchPOIs(latlng);
                
                // Mostrar painel de estatísticas
                showStatisticsPanel();
            } else {
                alert('Localização não encontrada. Por favor, tente outro termo de pesquisa.');
            }
        })
        .catch(error => {
            hideLoading();
            console.error('Erro ao pesquisar localização:', error);
            alert('Ocorreu um erro ao pesquisar a localização.');
        });
}

// Inicializar botões de fechar painéis
function initPanelCloseButtons() {
    // Botão de fechar painel de estatísticas
    const closeStatsButton = document.querySelector('.close-stats');
    if (closeStatsButton) {
        closeStatsButton.addEventListener('click', function() {
            hideStatisticsPanel();
        });
    }
    
    // Botão de fechar painel de detalhes do POI
    const closePoiDetailsButton = document.querySelector('.close-poi-details');
    if (closePoiDetailsButton) {
        closePoiDetailsButton.addEventListener('click', function() {
            hidePoiDetailsPanel();
        });
    }
}

// Mostrar painel de estatísticas
function showStatisticsPanel() {
    const statsPanel = document.querySelector('.statistics-panel');
    if (statsPanel) {
        statsPanel.classList.add('visible');
    }
}

// Ocultar painel de estatísticas
function hideStatisticsPanel() {
    const statsPanel = document.querySelector('.statistics-panel');
    if (statsPanel) {
        statsPanel.classList.remove('visible');
    }
}

// Mostrar painel de detalhes do POI
function showPoiDetailsPanel() {
    const poiDetailsPanel = document.querySelector('.poi-details-panel');
    if (poiDetailsPanel) {
        poiDetailsPanel.classList.add('visible');
    }
}

// Ocultar painel de detalhes do POI
function hidePoiDetailsPanel() {
    const poiDetailsPanel = document.querySelector('.poi-details-panel');
    if (poiDetailsPanel) {
        poiDetailsPanel.classList.remove('visible');
    }
}

// Função para redefinir a UI
function resetUI() {
    // Limpar o painel de estatísticas da área
    document.getElementById('area-stats').innerHTML = '<p>Clique no mapa para ver estatísticas</p>';
    
    // Limpar o painel de informações do POI
    document.getElementById('poi-info').innerHTML = '<p>Clique num ponto de interesse para ver detalhes</p>';
    
    // Ocultar painéis
    hideStatisticsPanel();
    hidePoiDetailsPanel();
    
    // Redefinir todas as camadas
    if (isochroneLayer) {
        map.removeLayer(isochroneLayer);
        isochroneLayer = null;
    }
    
    if (currentMarker) {
        map.removeLayer(currentMarker);
        currentMarker = null;
    }
    
    // Limpar camadas de POI
    Object.keys(poiLayers).forEach(type => {
        poiLayers[type].clearLayers();
    });
}