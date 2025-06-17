/**
 * Explorador de Cidade em 15 Minutos - Funcionalidades dos Controlos
 * Gere as interações dos controlos da interface de utilizador (UI) e os seus efeitos no mapa.
 * 
 * @version 2.0
 */

// Inicializa os controlos quando o DOM é carregado
function initControls() {
    // Inicializa a funcionalidade do menu móvel
    initMobileMenu();
    
    // Inicializa os painéis colapsáveis
    initCollapsiblePanels();
    
    // Inicializa o seletor de estilo do mapa
    initMapStyleSelector();
    
    // Inicializa o seletor de modo de transporte
    initTransportModeSelector();
    
    // Inicializa o deslizador de distância
    initDistanceSlider();
    
    // Inicializa as caixas de verificação dos POI
    initPoiCheckboxes();
    
    // Inicializa o botão de calcular
    initCalculateButton();
    
    // Inicializa a caixa de pesquisa
    initSearchBox();
    
    // Inicializa os botões de fechar painel
    initPanelCloseButtons();
    
    // Inicializa os controlos das definições
    initSettingsControls();
}

// Inicializa a funcionalidade do menu móvel
function initMobileMenu() {
    const menuToggle = document.getElementById('mobile-menu-toggle');
    const closeButton = document.getElementById('mobile-panel-close');
    const panel = document.getElementById('overlay-panel');
    
    // Rastrear se o tutorial está ativo
    let tutorialActive = false;
    
    // Monitorizar eventos do tutorial
    document.addEventListener('tutorialShown', function() {
        tutorialActive = true;
    });
    
    document.addEventListener('tutorialClosed', function() {
        tutorialActive = false;
    });
    
    if (menuToggle && closeButton && panel) {
        // Mostrar menu quando o interruptor é clicado
        menuToggle.addEventListener('click', function() {
            panel.classList.add('mobile-active');
        });
        
        // Esconder menu quando o botão de fechar é clicado
        closeButton.addEventListener('click', function() {
            panel.classList.remove('mobile-active');
        });
        
        // Esconder menu ao clicar no mapa (apenas em dispositivos móveis)
        document.getElementById('map').addEventListener('click', function() {
            // Nunca esconder o painel no ambiente de trabalho, independentemente do estado do tutorial
            if (window.innerWidth > 768) {
                // Em vez de esconder, garantir que está visível
                panel.style.display = 'block';
                panel.style.transform = 'none';
                panel.style.visibility = 'visible';
                panel.style.opacity = '1';
                return;
            }
            
            // Apenas esconder em dispositivos móveis
            if (window.innerWidth <= 768) {
                panel.classList.remove('mobile-active');
            }
        });
        
        // Esconder menu ao clicar no botão de calcular (apenas em dispositivos móveis)
        document.querySelector('.calculate-button').addEventListener('click', function() {
            // Nunca esconder o painel no ambiente de trabalho, independentemente do estado do tutorial
            if (window.innerWidth > 768) {
                // Em vez de esconder, garantir que está visível
                panel.style.display = 'block';
                panel.style.transform = 'none';
                panel.style.visibility = 'visible';
                panel.style.opacity = '1';
                return;
            }
            
            // Apenas esconder em dispositivos móveis
            if (window.innerWidth <= 768) {
                panel.classList.remove('mobile-active');
            }
        });
    }
}

// Inicializa o seletor de estilo do mapa
function initMapStyleSelector() {
    const mapStyleOptions = document.querySelectorAll('.map-style-option');
    
    mapStyleOptions.forEach(option => {
        option.addEventListener('click', function() {
            // Obter o fornecedor do atributo data-provider
            const provider = this.getAttribute('data-provider');
            
            // Atualiza os 'tiles' do mapa
            updateMapTiles(provider);
        });
    });
}

// Inicializa os painéis colapsáveis
function initCollapsiblePanels() {
    // Registar todos os cabeçalhos de painel para depuração
    console.log('Cabeçalhos de painel encontrados:', document.querySelectorAll('.panel-header').length);
    
    // Inicializa os cabeçalhos de painel (excluindo definições que são tratadas separadamente)
    document.querySelectorAll('.panel-header').forEach(header => {
        // Ignorar cabeçalhos com a classe 'js-custom-handled' (ex: cabeçalho das definições) pois são tratados por outros scripts
        if (header.classList.contains('js-custom-handled')) {
            console.log('A ignorar cabeçalho de painel com classe js-custom-handled:', header.id);
            return;
        }
        
        console.log('A inicializar cabeçalho de painel:', header.id);
        
        header.addEventListener('click', function() {
            console.log('Cabeçalho de painel clicado:', this.id);
            const content = this.nextElementSibling;
            console.log('Elemento seguinte:', content?.id);
            
            if (content && content.classList.contains('panel-content')) {
                console.log('A alternar a classe expanded em', content.id);
                content.classList.toggle('expanded');
                const arrow = this.querySelector('.dropdown-arrow');
                if (arrow) {
                    arrow.classList.toggle('up');
                }
            }
        });
    });
    
    // Inicializa os cabeçalhos das categorias de POI
    document.querySelectorAll('.category-header').forEach(header => {
        header.addEventListener('click', function(e) {
            // Previne a propagação do evento para o painel pai
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
    
    // Garantir que o painel de definições tem a classe correta
    const settingsContent = document.getElementById('settings-content');
    if (settingsContent) {
        // Garantir que tem a classe panel-content
        settingsContent.classList.add('panel-content');
        // Garantir que está inicialmente colapsado
        settingsContent.classList.remove('expanded');
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

// Inicializa o seletor de modo de transporte
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
            
            // Atualizar modo de transporte selecionado
            selectedTransportMode = this.getAttribute('data-mode');
            
            // Se já houver um marcador no mapa, recalcular a isócrona para o novo modo de transporte
            if (currentMarker) {
                console.log(`Modo de transporte alterado para: ${selectedTransportMode}. Recalculando isócrona...`);
                generateIsochrone(currentMarker.getLatLng());
            }
        });
    });
    
    // Define o modo de transporte inicial
    const activeModeElement = document.querySelector('.transport-option.active');
    if (activeModeElement) {
        selectedTransportMode = activeModeElement.getAttribute('data-mode');
    }
}

// Inicializa o deslizador de distância
function initDistanceSlider() {
    const distanceSlider = document.getElementById('max-distance');
    const distanceValue = document.getElementById('distance-value');
    
    // Define o valor de distância inicial
    distanceValue.textContent = distanceSlider.value + ' minutos';
    
    // Adiciona um ouvinte de evento de entrada ao deslizador
    distanceSlider.addEventListener('input', function() {
        // Atualiza o valor exibido
        distanceValue.textContent = this.value + ' minutos';
        
        // Atualiza a distância máxima selecionada
        selectedMaxDistance = parseInt(this.value);
    });
    
    // Adiciona um ouvinte para o evento 'change' (dispara quando o utilizador solta o deslizador)
    distanceSlider.addEventListener('change', function() {
        // Se já houver um marcador no mapa, recalcular a isócrona com a nova distância
        if (currentMarker) {
            console.log(`Distância alterada para: ${selectedMaxDistance} minutos. Recalculando isócrona...`);
            generateIsochrone(currentMarker.getLatLng());
        }
    });
}

// Inicializa as caixas de verificação dos POI
function initPoiCheckboxes() {
    Object.keys(poiTypes).forEach(type => {
        const checkbox = document.getElementById(`poi-${type}`);
        if (checkbox) {
            checkbox.addEventListener('change', () => handlePoiToggle(type));
        }
    });
}

// Lida com a alternância da caixa de verificação do POI
function handlePoiToggle(type) {
    const checkbox = document.getElementById(`poi-${type}`);
    const isChecked = checkbox.checked;
    
    // Mostrar ou ocultar a camada com base no estado da caixa de verificação
    if (isChecked) {
        // Apenas garantir que a camada é adicionada ao mapa
        if (!map.hasLayer(poiLayers[type])) {
            map.addLayer(poiLayers[type]);
        }
    } else {
        // Remover camada do mapa
        if (map.hasLayer(poiLayers[type])) {
            map.removeLayer(poiLayers[type]);
        }
    }
    
    // Se tivermos uma isócrona ativa e um marcador, atualizar as estatísticas
    // e atualizar POIs sem mostrar o indicador de carregamento
    if (currentIsochroneData && currentMarker) {
        // Atualizar as estatísticas para refletir os POIs atualmente selecionados
        updateAreaStats(
            currentMarker.getLatLng(), 
            calculateRadiusFromIsochrone(currentIsochroneData),
            JSON.stringify(currentIsochroneData)
        );
        
        // Recarregar POIs se houver uma isócrona ativa, mas sem mostrar o indicador de carregamento
        if (isochroneLayer) {
            fetchPOIs(currentMarker.getLatLng(), false);
        }
    }
}

// Função auxiliar para calcular o raio a partir da isócrona para estatísticas
function calculateRadiusFromIsochrone(isochroneData) {
    let radiusInMeters;
    
    if (isochroneData.features && 
        isochroneData.features[0] && 
        isochroneData.features[0].properties && 
        isochroneData.features[0].properties.area) {
        // Converter km² para m² para obter um raio equivalente
        const areaInKm2 = isochroneData.features[0].properties.area;
        radiusInMeters = Math.sqrt(areaInKm2 * 1000000 / Math.PI);
    } else {
        // Alternativa: usar estimativa baseada na velocidade
        const speedKmPerHour = transportSpeeds[selectedTransportMode];
        const distanceInKm = (speedKmPerHour * selectedMaxDistance) / 60;
        radiusInMeters = distanceInKm * 1000;
    }
    
    return radiusInMeters;
}

// Inicializa o botão de calcular
function initCalculateButton() {
    const calculateButton = document.querySelector('.calculate-button');
    if (calculateButton) {
        // Ocultar o botão de calcular, pois estamos a automatizar o processo
        calculateButton.style.display = 'none';
        
        // Manter o ouvinte de evento para compatibilidade com outros códigos
        calculateButton.addEventListener('click', function() {
            if (currentMarker) {
                // Mostrar indicador de carregamento
                showLoading();
                
                // Gerar isócrona usando a API ORS
                generateIsochrone(currentMarker.getLatLng());
            } else {
                alert('Por favor, selecione primeiro uma localização no mapa');
            }
        });
    }
}

// Inicializa a caixa de pesquisa
function initSearchBox() {
    const searchBox = document.querySelector('.search-box');
    const searchButton = document.querySelector('.search-button');
    
    if (searchBox && searchButton) {
        // Adicionar autocompletar da interface de utilizador jQuery à caixa de pesquisa
        $(searchBox).autocomplete({
            minLength: 3,
            delay: 500,
            source: function(request, response) {
                // Só mostrar o indicador de carregamento se houver um termo de pesquisa válido
                if (request.term && request.term.length >= 3) {
                    // Mostrar indicador de carregamento
                    showLoading();
                    
                    // Fazer requisição ao nosso proxy Nominatim
                    fetch(`includes/proxy_nominatim.php?term=${encodeURIComponent(request.term)}`)
                        .then(res => res.json())
                        .then(data => {
                            hideLoading();
                            response(data);
                        })
                        .catch(error => {
                            hideLoading();
                            console.error('Erro na pesquisa de autocompletar:', error);
                            response([]);
                        });
                } else {
                    // Se não houver termo de pesquisa válido, retornar lista vazia
                    response([]);
                }
            },
            select: function(event, ui) {
                // Quando um item é selecionado, realizar a pesquisa com a localização selecionada
                if (ui.item) {
                    // Definir o valor da caixa de pesquisa
                    searchBox.value = ui.item.label;
                    
                    // Criar objeto LatLng
                    const latlng = L.latLng(ui.item.lat, ui.item.lon);
                    
                    // Definir a vista do mapa para a localização encontrada
                    map.setView(latlng, 15);
                    
                    // Criar marcador na localização encontrada
                    if (currentMarker) {
                        map.removeLayer(currentMarker);
                    }
                    currentMarker = L.marker(latlng).addTo(map);
                    
                    // Gerar isócrona automaticamente
                    generateIsochrone(latlng);
                    
                    // Em dispositivos móveis, fechar o painel após a pesquisa
                    if (window.innerWidth <= 768) {
                        document.getElementById('overlay-panel').classList.remove('mobile-active');
                    }
                    
                    return false; // Prevenir ação padrão
                }
            }
        }).autocomplete("instance")._renderItem = function(ul, item) {
            // Personalizar a aparência de cada item no menu pendente de autocompletar
            return $("<li>")
                .append("<div class='autocomplete-item'><i class='fas fa-map-marker-alt'></i> " + item.label + "</div>")
                .appendTo(ul);
        };
        
        // Pesquisar quando o botão é clicado
        searchButton.addEventListener('click', function() {
            performSearch(searchBox.value);
        });
        
        // Pesquisar quando a tecla Enter é pressionada
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
                
                // Definir a vista do mapa para a localização encontrada
                map.setView(latlng, 15);
                
                // Criar marcador na localização encontrada
                if (currentMarker) {
                    map.removeLayer(currentMarker);
                }
                currentMarker = L.marker(latlng).addTo(map);
                
                // Gerar isócrona automaticamente em vez de esperar pelo botão Calcular
                generateIsochrone(latlng);
                
                // Em dispositivos móveis, fechar o painel após a pesquisa
                if (window.innerWidth <= 768) {
                    document.getElementById('overlay-panel').classList.remove('mobile-active');
                }
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

// Inicializa os botões de fechar painel
function initPanelCloseButtons() {
    // Botão de fechar painel de estatísticas
    const closeStatsButton = document.querySelector('.close-stats');
    if (closeStatsButton) {
        closeStatsButton.addEventListener('click', function() {
            hideStatisticsPanel();
            console.log('Painel de estatísticas fechado');
        });
    } else {
        console.error('Botão de fechar estatísticas não encontrado');
    }
}

// Redefine a interface de utilizador para o seu estado inicial
function resetUI() {
    // Limpar painel de estatísticas
    document.getElementById('area-stats').innerHTML = '<p>Clique no mapa para ver estatísticas</p>';
    
    // Ocultar painéis
    hideStatisticsPanel();
    
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
    clearAllPOIs();
    
    // Redefinir dados da isócrona atual
    currentIsochroneData = null;
}

// Lida com eventos de redimensionamento da janela
window.addEventListener('resize', function() {
    // Se houver transição da vista móvel para a de ambiente de trabalho, garantir que o painel está visível
    if (window.innerWidth > 768) {
        document.getElementById('overlay-panel').classList.remove('mobile-active');
    }
});

// Ocultar painel de estatísticas
function hideStatisticsPanel() {
    const statsPanel = document.querySelector('.statistics-panel');
    if (statsPanel) {
        statsPanel.classList.remove('visible');
        console.log('Painel de estatísticas escondido');
    } else {
        console.error('Painel de estatísticas não encontrado');
    }
}

// Inicializa os controlos das definições
function initSettingsControls() {
    console.log('Inicialização dos controlos de definições ignorada - a usar jQuery em vez disso');
    
    // Apenas lidar com a funcionalidade de armazenamento local, todas as interações da interface de utilizador estão em jQuery
    
    // Inicializa o seletor de nível de detalhe da localização - apenas carregamento do armazenamento local
    const locationDetailLevel = document.getElementById('location-detail-level');
    if (locationDetailLevel) {
        // Definir valor inicial do armazenamento local se disponível
        const savedLevel = localStorage.getItem('locationDetailLevel');
        if (savedLevel) {
            locationDetailLevel.value = savedLevel;
        }
        
        // Adicionar ouvinte de evento de mudança para armazenar no armazenamento local
        locationDetailLevel.addEventListener('change', function() {
            localStorage.setItem('locationDetailLevel', this.value);
            console.log(`Nível de detalhe da localização alterado para: ${this.value}`);
        });
    }
    
    // Inicializa os campos de peso para carregar do armazenamento local
    document.querySelectorAll('.weight-input').forEach(input => {
        // Definir valor inicial do armazenamento local se disponível
        const poiType = input.id.replace('weight-', '');
        const savedWeight = localStorage.getItem(`weight-${poiType}`);
        if (savedWeight) {
            input.value = savedWeight;
        }
        
        // Adicionar ouvinte de evento de mudança para armazenar no armazenamento local
        input.addEventListener('change', function() {
            // Validar valor de entrada (entre 1-10)
            let value = parseInt(this.value);
            if (isNaN(value) || value < 1) {
                value = 1;
                this.value = 1;
            } else if (value > 10) {
                value = 10;
                this.value = 10;
            }
            
            // Armazenar o peso no armazenamento local
            const poiType = this.id.replace('weight-', '');
            localStorage.setItem(`weight-${poiType}`, value);
        });
    });
}