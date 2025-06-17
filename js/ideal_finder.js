// JavaScript do Localizador Ideal
class IdealLocationFinder {
    constructor() {
        this.map = null;
        this.currentMarker = null;
        this.heatmapLayer = null;
        this.topLocationMarkers = [];
        this.isAnalyzing = false;
        this.currentLocation = null;
        this.currentTileLayer = null;
        this.selectedTileProvider = DEFAULT_TILE_PROVIDER;
        
        this.init();
    }

    init() {
        this.initMap();
        this.initEventListeners();
        this.initAutocomplete();
        this.setupPOIControls();
        
        // Mostrar o tutorial após um curto atraso
        setTimeout(() => {
            this.showIdealFinderTutorial();
        }, 800);
    }

    initMap() {
        // Inicializar mapa centrado em Aveiro, Portugal
        const aveiroCenter = [40.6405, -8.6538];
        this.map = L.map('map', { zoomControl: false }).setView(aveiroCenter, 13);
        
        // Pode adicionar controlos de zoom personalizados, se necessário:
        // L.control.zoom({ position: 'topright' }).addTo(this.map);
        
        // Adicionar camada de 'tiles' usando a configuração do mapa
        this.currentTileLayer = null;
        this.selectedTileProvider = DEFAULT_TILE_PROVIDER;
        this.updateMapTiles(this.selectedTileProvider);

        // Manipulador de clique no mapa
        this.map.on('click', (e) => {
            this.setLocation(e.latlng.lat, e.latlng.lng);
        });
    }

    initEventListeners() {
        // Botões de modo de transporte (agora usando a classe transport-option como em app.php)
        document.querySelectorAll('.transport-option').forEach(btn => {
            btn.addEventListener('click', () => {
                document.querySelector('.transport-option.active')?.classList.remove('active');
                btn.classList.add('active');
            });
        });

        // Deslizador de tempo
        const timeSlider = document.getElementById('max-time');
        const timeDisplay = document.getElementById('time-display');
        timeSlider.addEventListener('input', () => {
            timeDisplay.textContent = timeSlider.value + ' minutos';
        });

        // Deslizador de intensidade do mapa de calor
        const intensitySlider = document.getElementById('heatmap-intensity');
        const intensityValue = document.getElementById('intensity-value');
        if (intensitySlider && intensityValue) {
            intensitySlider.addEventListener('input', () => {
                intensityValue.textContent = intensitySlider.value;
            });
        }

        // Seletor de estilo do mapa
        document.querySelectorAll('.map-style-option').forEach(option => {
            option.addEventListener('click', () => {
                const provider = option.getAttribute('data-provider');
                this.updateMapTiles(provider);
            });
        });

        // Botão de analisar (agora usando a classe calculate-button como em app.php)
        document.getElementById('analyze-btn').addEventListener('click', () => {
            this.startAnalysis();
        });

        // Alternar painel de resultados
        document.getElementById('toggle-results').addEventListener('click', () => {
            this.toggleResultsPanel();
        });

        // Controlos do mapa de calor
        document.getElementById('toggle-heatmap').addEventListener('click', () => {
            this.toggleHeatmap();
        });

        document.getElementById('reset-view').addEventListener('click', () => {
            this.resetMapView();
        });

        // Secções colapsáveis do painel
        this.initCollapsibleSections();

        // Alternar menu móvel
        const mobileToggle = document.getElementById('mobile-menu-toggle');
        const overlayPanel = document.getElementById('overlay-panel');
        const mobileClose = document.getElementById('mobile-panel-close');

        if (mobileToggle) {
            mobileToggle.addEventListener('click', () => {
                overlayPanel.classList.toggle('mobile-open');
            });
        }

        if (mobileClose) {
            mobileClose.addEventListener('click', () => {
                overlayPanel.classList.remove('mobile-open');
            });
        }
    }

    initCollapsibleSections() {
        // Inicializa todos os conteúdos colapsáveis e configura os seus interruptores usando jQuery slideToggle
        
        // Função para lidar com a lógica de alternância para um cabeçalho e o seu conteúdo
        const setupToggle = (headerId, contentId, expandByDefault = false) => {
            const header = document.getElementById(headerId);
            const content = document.getElementById(contentId);
            
            if (header && content) {
                const $content = $(content);
                const $arrow = $(header).find('.dropdown-arrow');

                // Estado inicial: ocultar conteúdo, a menos que deva expandir por padrão
                if (!expandByDefault) {
                    $content.hide();
                    $arrow.removeClass('up');
                } else {
                    // Se expandir por padrão, usar slideDown e adicionar classe
                    $content.slideDown(300, function() {
                        $(this).addClass('expanded');
                        $arrow.addClass('up');
                    });
                }

                $(header).on('click', (e) => {
                    // Para cabeçalhos de categoria, prevenir a propagação para o cabeçalho do painel pai
                    if ($(header).hasClass('category-header')) {
                        e.stopPropagation(); 
                    }

                    if ($content.is(':hidden')) {
                        $content.addClass('expanded'); // Adicionar classe antes da animação para transições CSS
                        $arrow.addClass('up');
                        $content.slideDown(300);
                    } else {
                        $content.slideUp(300, function() {
                            $(this).removeClass('expanded'); // Remover classe após a animação completar
                            $arrow.removeClass('up');
                        });
                    }
                });
            }
        };

        // Configurar secções principais do painel
        setupToggle('poi-header', 'poi-content', true); // A secção POI expande por padrão
        setupToggle('map-style-header', 'map-style-content');
        setupToggle('settings-header', 'settings-content');

        // Configurar interruptores de categoria dentro da secção POI (ex: Saúde, Educação)
        // Estes são .category-header e o seu nextElementSibling é .category-content
        document.querySelectorAll('.poi-category .category-header').forEach(header => {
            const content = header.nextElementSibling;
            if (content && content.classList.contains('category-content')) {
                // Garantir que o estado inicial para o conteúdo da categoria é oculto e a seta para baixo
                $(content).hide();
                $(header).find('.dropdown-arrow').removeClass('up');

                $(header).on('click', (e) => {
                    e.stopPropagation(); // Prevenir a propagação para o cabeçalho do painel pai
                    const $content = $(content);
                    const $arrow = $(header).find('.dropdown-arrow');

                    if ($content.is(':hidden')) {
                        $content.addClass('expanded');
                        $arrow.addClass('up');
                        $content.slideDown(300);
                    } else {
                        $content.slideUp(300, function() {
                            $(this).removeClass('expanded');
                            $arrow.removeClass('up');
                        });
                    }
                });
            }
        });
    }

    initAutocomplete() {
        $('#location-input').autocomplete({
            source: (request, response) => {
                // Usar Nominatim para geocodificação
                $.ajax({
                    url: 'https://nominatim.openstreetmap.org/search',
                    data: {
                        q: request.term,
                        format: 'json',
                        countrycodes: 'pt',
                        limit: 5,
                        addressdetails: 1
                    },
                    success: (data) => {
                        const suggestions = data.map(item => ({
                            label: item.display_name,
                            value: item.display_name,
                            lat: parseFloat(item.lat),
                            lon: parseFloat(item.lon)
                        }));
                        response(suggestions);
                    }
                });
            },
            select: (event, ui) => {
                this.setLocation(ui.item.lat, ui.item.lon);
            },
            minLength: 3
        });
    }

    setupPOIControls() {
        // Ativar/desativar seletores de importância com base nas caixas de verificação
        document.querySelectorAll('input[name="poi"]').forEach(checkbox => {
            checkbox.addEventListener('change', () => {
                const poiItem = checkbox.closest('.poi-item-finder') || checkbox.closest('.poi-item');
                const select = poiItem ? poiItem.querySelector('.importance-select') : 
                             checkbox.closest('.poi-item')?.querySelector('.importance-select');
                
                if (select) {
                    select.disabled = !checkbox.checked;
                    if (checkbox.checked) {
                        select.style.opacity = '1';
                    } else {
                        select.style.opacity = '0.5';
                    }
                }
            });
        });
    }

    setLocation(lat, lng) {
        this.currentLocation = { lat, lng };
        
        // Remover marcador existente
        if (this.currentMarker) {
            this.map.removeLayer(this.currentMarker);
        }

        // Adicionar novo marcador
        this.currentMarker = L.marker([lat, lng], {
            icon: L.divIcon({
                className: 'custom-marker reference-marker',
                html: '<i class="fas fa-map-marker-alt"></i>',
                iconSize: [30, 30],
                iconAnchor: [15, 30]
            })
        }).addTo(this.map);

        // Centrar mapa na localização
        this.map.setView([lat, lng], 13);

        // Geocodificação inversa para atualizar o input
        this.reverseGeocode(lat, lng);
    }

    reverseGeocode(lat, lng) {
        $.ajax({
            url: 'https://nominatim.openstreetmap.org/reverse',
            data: {
                lat: lat,
                lon: lng,
                format: 'json',
                addressdetails: 1
            },
            success: (data) => {
                if (data && data.display_name) {
                    document.getElementById('location-input').value = data.display_name;
                }
            }
        });
    }

    getCurrentLocation() {
        if (navigator.geolocation) {
            navigator.geolocation.getCurrentPosition(
                (position) => {
                    this.setLocation(position.coords.latitude, position.coords.longitude);
                },
                (error) => {
                    alert('Erro ao obter localização: ' + error.message);
                }
            );
        } else {
            alert('Geolocalização não é suportada neste navegador.');
        }
    }

    getSelectedPOIs() {
        const selectedPOIs = [];
        document.querySelectorAll('input[name="poi"]:checked').forEach(checkbox => {
            const poiItem = checkbox.closest('.poi-item-finder') || checkbox.closest('.poi-item');
            const importance = parseInt(poiItem.querySelector('.importance-select').value);
            selectedPOIs.push({
                type: checkbox.value,
                importance: importance
            });
        });
        return selectedPOIs;
    }

    getAnalysisSettings() {
        const selectedPOIs = this.getSelectedPOIs();
        const transportMode = document.querySelector('.transport-option.active')?.dataset.mode || 
                           document.querySelector('.transport-btn.active')?.dataset.mode;
        const maxTime = parseInt(document.getElementById('max-time').value);
        const gridResolution = parseInt(document.getElementById('grid-resolution').value);
        const topLocations = parseInt(document.getElementById('top-locations').value);

        return {
            location: this.currentLocation,
            pois: selectedPOIs,
            transport_mode: transportMode,
            max_time: maxTime,
            grid_resolution: gridResolution,
            top_locations: topLocations
        };
    }

    async startAnalysis() {
        if (!this.currentLocation) {
            alert('Por favor, selecione uma localização no mapa.');
            return;
        }

        const selectedPOIs = this.getSelectedPOIs();
        if (selectedPOIs.length === 0) {
            alert('Por favor, selecione pelo menos um tipo de ponto de interesse.');
            return;
        }

        if (this.isAnalyzing) return;

        this.isAnalyzing = true;
        this.showLoading();
        this.clearPreviousResults();

        try {
            const settings = this.getAnalysisSettings();
            const response = await this.performAnalysis(settings);
            
            if (response.success) {
                this.displayResults(response.data);
            } else {
                throw new Error(response.error || 'Erro na análise');
            }
        } catch (error) {
            console.error('Erro na análise:', error);
            alert('Erro durante a análise: ' + error.message);
        } finally {
            this.isAnalyzing = false;
            this.hideLoading();
        }
    }

    async performAnalysis(settings) {
        return new Promise((resolve, reject) => {
            $.ajax({
                url: 'includes/fetch_ideal_locations.php',
                method: 'POST',
                data: JSON.stringify(settings),
                contentType: 'application/json',
                timeout: 120000, // Tempo limite de 2 minutos
                xhr: () => {
                    const xhr = new XMLHttpRequest();
                    xhr.upload.addEventListener('progress', (e) => {
                        if (e.lengthComputable) {
                            const percentComplete = (e.loaded / e.total) * 100;
                            this.updateProgress(percentComplete, 'A enviar dados...');
                        }
                    });
                    return xhr;
                },
                success: (response) => {
                    try {
                        const data = typeof response === 'string' ? JSON.parse(response) : response;
                        resolve(data);
                    } catch (e) {
                        reject(new Error('Resposta inválida do servidor'));
                    }
                },
                error: (xhr, status, error) => {
                    reject(new Error(`Erro na comunicação: ${error}`));
                }
            });

            // Simular atualizações de progresso
            this.simulateProgress();
        });
    }

    simulateProgress() {
        let progress = 0;
        const stages = [
            { progress: 20, message: 'A configurar a análise da grelha...' },
            { progress: 40, message: 'A consultar a base de dados...' },
            { progress: 60, message: 'A calcular a acessibilidade...' },
            { progress: 80, message: 'A gerar o mapa de calor...' },
            { progress: 95, message: 'A finalizar os resultados...' }
        ];

        const updateStage = (index) => {
            if (index < stages.length && this.isAnalyzing) {
                const stage = stages[index];
                this.updateProgress(stage.progress, stage.message);
                setTimeout(() => updateStage(index + 1), 2000);
            }
        };

        updateStage(0);
    }

    showLoading() {
        document.getElementById('loading-overlay').style.display = 'flex';
        document.getElementById('analyze-btn').disabled = true;
        this.updateProgress(0, 'A iniciar a análise...');
    }

    hideLoading() {
        document.getElementById('loading-overlay').style.display = 'none';
        document.getElementById('analyze-btn').disabled = false;
    }

    updateProgress(percentage, message) {
        document.getElementById('progress-fill').style.width = percentage + '%';
        document.getElementById('progress-text').textContent = Math.round(percentage) + '%';
        document.getElementById('loading-status').textContent = message;
    }

    clearPreviousResults() {
        // Remover o mapa de calor
        if (this.heatmapLayer) {
            this.map.removeLayer(this.heatmapLayer);
            this.heatmapLayer = null;
        }

        // Remover os marcadores de localização superior
        this.topLocationMarkers.forEach(marker => {
            this.map.removeLayer(marker);
        });
        this.topLocationMarkers = [];

        // Limpar a lista de resultados
        document.getElementById('results-list').innerHTML = '';
        document.getElementById('results-panel').style.display = 'none';
    }

    displayResults(data) {
        // Exibir o mapa de calor
        if (data.heatmap && data.heatmap.length > 0) {
            this.displayHeatmap(data.heatmap);
        }

        // Exibir as localizações principais
        if (data.top_locations && data.top_locations.length > 0) {
            this.displayTopLocations(data.top_locations);
        }

        // Mostrar o painel de resultados
        document.getElementById('results-panel').style.display = 'block';
    }

    displayHeatmap(heatmapData) {
        const intensity = parseFloat(document.getElementById('heatmap-intensity').value);
        
        this.heatmapLayer = L.heatLayer(heatmapData, {
            radius: 25,
            blur: 15,
            maxZoom: 17,
            gradient: {
                0.0: '#0000ff',  // Azul para pontuações baixas
                0.2: '#00ffff',  // Ciano
                0.4: '#00ff00',  // Verde
                0.6: '#ffff00',  // Amarelo
                0.8: '#ff8000',  // Laranja
                1.0: '#ff0000'   // Vermelho para pontuações altas
            },
            max: intensity
        }).addTo(this.map);
    }

    displayTopLocations(topLocations) {
        const resultsList = document.getElementById('results-list');
        resultsList.innerHTML = '';

        topLocations.forEach((location, index) => {
            // Adicionar marcador ao mapa
            const marker = L.marker([location.lat, location.lng], {
                icon: L.divIcon({
                    className: 'custom-marker top-location-marker',
                    html: `<div class="marker-content">
                             <span class="marker-rank">${index + 1}</span>
                             <i class="fas fa-star"></i>
                           </div>`,
                    iconSize: [40, 40],
                    iconAnchor: [20, 40]
                })
            }).addTo(this.map);

            // Criar 'popup' com detalhes
            const popupContent = this.createLocationPopup(location, index + 1);
            marker.bindPopup(popupContent, { maxWidth: 300 });

            this.topLocationMarkers.push(marker);

            // Adicionar à lista de resultados
            const resultItem = this.createResultItem(location, index + 1);
            resultsList.appendChild(resultItem);
        });
    }

    createLocationPopup(location, rank) {
        let popupHTML = `
            <div class="location-popup">
                <h4><i class="fas fa-trophy"></i> #${rank} Melhor Localização</h4>
                <p><strong>Pontuação Total:</strong> ${location.total_score.toFixed(1)}/100</p>
                <div class="poi-scores">
        `;

        location.poi_scores.forEach(poi => {
            const percentage = (poi.score / poi.max_score * 100).toFixed(1);
            popupHTML += `
                <div class="poi-score-item">
                    <span class="poi-name">${this.getPOIDisplayName(poi.type)}:</span>
                    <span class="poi-score">${percentage}%</span>
                </div>
            `;
        });

        popupHTML += `
                </div>
                <button onclick="idealFinder.centerOnLocation(${location.lat}, ${location.lng})" class="center-btn">
                    <i class="fas fa-crosshairs"></i> Centrar
                </button>
            </div>
        `;

        return popupHTML;
    }

    createResultItem(location, rank) {
        const resultItem = document.createElement('div');
        resultItem.className = 'result-item';
        
        let poiScoresHTML = '';
        location.poi_scores.forEach(poi => {
            const percentage = (poi.score / poi.max_score * 100).toFixed(1);
            poiScoresHTML += `
                <div class="poi-score-bar">
                    <span class="poi-label">${this.getPOIDisplayName(poi.type)}</span>
                    <div class="score-bar">
                        <div class="score-fill" style="width: ${percentage}%"></div>
                    </div>
                    <span class="score-value">${percentage}%</span>
                </div>
            `;
        });

        resultItem.innerHTML = `
            <div class="result-header">
                <div class="result-rank">#${rank}</div>
                <div class="result-info">
                    <h4>Localização Ideal</h4>
                    <p class="result-score">Pontuação: ${location.total_score.toFixed(1)}/100</p>
                </div>
                <button class="view-btn" onclick="idealFinder.centerOnLocation(${location.lat}, ${location.lng})">
                    <i class="fas fa-eye"></i>
                </button>
            </div>
            <div class="result-details">
                ${poiScoresHTML}
            </div>
        `;

        // Adicionar manipulador de clique para expandir/colapsar detalhes
        resultItem.querySelector('.result-header').addEventListener('click', () => {
            resultItem.classList.toggle('expanded');
        });

        return resultItem;
    }

    getPOIDisplayName(type) {
        const displayNames = {
            // === Saúde ===
            'hospitals': 'Hospitais',
            'health_centers': 'Centros de Saúde',
            'pharmacies': 'Farmácias',
            'dentists': 'Clínicas Dentárias',
            
            // === Educação ===
            'schools': 'Escolas Primárias e Secundárias',
            'universities': 'Universidades e Institutos Superiores',
            'kindergartens': 'Jardins de Infância e Creches',
            'libraries': 'Bibliotecas',
            
            // === Comércio e Serviços ===
            'supermarkets': 'Supermercados',
            'malls': 'Centros Comerciais',
            'restaurants': 'Restaurantes e Cafés',
            'atms': 'Caixas de Multibanco',
            
            // === Segurança ===
            'police_stations': 'Esquadras de Polícia',
            'fire_stations': 'Bombeiros',
            'civil_protection': 'Proteção Civil',
            
            // === Administração Pública ===
            'parish_councils': 'Juntas de Freguesia',
            'city_halls': 'Câmaras Municipais',
            'post_offices': 'Correios',
            
            // === Cultura e Lazer ===
            'museums': 'Museus',
            'theaters': 'Teatros',
            'sports': 'Ginásios e Centros Desportivos',
            'parks': 'Parques',
            
            // === Suporte legado para tipos de POI antigos ===
            'hospital': 'Hospital',
            'clinic': 'Clínica',
            'pharmacy': 'Farmácia',
            'school': 'Escola',
            'university': 'Universidade',
            'kindergarten': 'Creche',
            'supermarket': 'Supermercado',
            'restaurant': 'Restaurante',
            'bank': 'Banco',
            'shopping_mall': 'Centro Comercial',
            'bus_stop': 'Paragem de Autocarro',
            'subway_station': 'Estação de Metro',
            'post_office': 'Correios',
            'fuel': 'Posto de Combustível'
        };
        return displayNames[type] || type;
    }

    centerOnLocation(lat, lng) {
        this.map.setView([lat, lng], 15);
    }

    toggleResultsPanel() {
        const panel = document.getElementById('results-panel');
        const toggleBtn = document.getElementById('toggle-results');
        const isExpanded = panel.classList.contains('expanded');
        
        if (isExpanded) {
            panel.classList.remove('expanded');
            toggleBtn.innerHTML = '<i class="fas fa-chevron-down"></i>';
        } else {
            panel.classList.add('expanded');
            toggleBtn.innerHTML = '<i class="fas fa-chevron-up"></i>';
        }
    }

    toggleHeatmap() {
        const btn = document.getElementById('toggle-heatmap');
        if (this.heatmapLayer) {
            if (this.map.hasLayer(this.heatmapLayer)) {
                this.map.removeLayer(this.heatmapLayer);
                btn.innerHTML = '<i class="fas fa-eye"></i><span>Mostrar Mapa de Calor</span>';
            } else {
                this.map.addLayer(this.heatmapLayer);
                btn.innerHTML = '<i class="fas fa-eye-slash"></i><span>Ocultar Mapa de Calor</span>';
            }
        }
    }

    resetMapView() {
        if (this.currentLocation) {
            this.map.setView([this.currentLocation.lat, this.currentLocation.lng], 13);
        }
    }

    // Atualiza os 'tiles' do mapa com base no fornecedor selecionado
    updateMapTiles(provider) {
        // Se houver uma camada de 'tiles' existente, remova-a
        if (this.currentTileLayer) {
            this.map.removeLayer(this.currentTileLayer);
        }

        // Obter a configuração do fornecedor
        const tileConfig = MAP_TILE_PROVIDERS[provider] || MAP_TILE_PROVIDERS[DEFAULT_TILE_PROVIDER];

        // Criar e adicionar a nova camada de 'tiles'
        this.currentTileLayer = L.tileLayer(tileConfig.url, {
            attribution: tileConfig.attribution,
            maxZoom: tileConfig.maxZoom
        }).addTo(this.map);

        // Atualizar a variável selectedTileProvider
        this.selectedTileProvider = provider;

        // Atualizar a interface de utilizador do seletor de estilo do mapa
        this.updateMapStyleSelector();
    }

    // Atualiza os botões do seletor de estilo do mapa para mostrar o estilo ativo
    updateMapStyleSelector() {
        document.querySelectorAll('.map-style-option').forEach(button => {
            const provider = button.getAttribute('data-provider');
            if (provider === this.selectedTileProvider) {
                button.classList.add('active');
            } else {
                button.classList.remove('active');
            }
        });
    }

    /**
     * Mostra um tutorial que explica o conceito e a funcionalidade do Localizador Ideal
     */
    showIdealFinderTutorial() {
        // Verificar se o utilizador já viu o tutorial antes
        if (localStorage.getItem('minu15_ideal_finder_tutorial_seen') === 'true') {
            return;
        }
        
        // Criar o contentor do tutorial
        const tutorialBox = document.createElement('div');
        tutorialBox.id = 'ideal-finder-tutorial-box';
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
                        <i class="fas fa-map-pin" style="font-size: 32px; color: white;"></i>
                    </div>
                    <h2 style="font-size: 24px; color: #2c3e50; margin-bottom: 5px; font-weight: 600;">Localizador Ideal</h2>
                    <p style="color: #7f8c8d; font-size: 15px;">Encontre os melhores locais para viver com base nas suas necessidades</p>
                </div>
                
                <div style="margin-bottom: 25px;">
                    <div style="background-color: #f0f7ff; border-left: 4px solid #3498db; padding: 15px; border-radius: 5px; margin-bottom: 20px;">
                        <div style="display: flex; align-items: center; margin-bottom: 8px;">
                            <i class="fas fa-info-circle" style="color: #3498db; margin-right: 10px; font-size: 18px;"></i>
                            <strong style="font-weight: 600; color: #2c3e50;">Conceito</strong>
                        </div>
                        <p style="margin: 0; color: #555; font-size: 14px;">
                            O Localizador Ideal é uma ferramenta conceptual que permite descobrir as áreas que melhor satisfazem suas necessidades de acessibilidade a serviços e pontos de interesse. A ferramenta utiliza análises espaciais e algoritmos para gerar um mapa de calor mostrando as áreas mais adequadas.
                        </p>
                    </div>
                
                    <div style="margin-bottom: 20px;">
                        <div style="display: flex; margin-bottom: 15px; align-items: flex-start;">
                            <div style="background: #3498db; color: white; border-radius: 50%; width: 28px; height: 28px; display: flex; justify-content: center; align-items: center; margin-right: 15px; flex-shrink: 0;">
                                <span style="font-weight: bold;">1</span>
                            </div>
                            <div>
                                <strong style="font-weight: 600; color: #2c3e50;">Defina um ponto de referência</strong> 
                                <p style="margin-top: 5px; color: #555;">Selecione um ponto no mapa ou utilize a barra de pesquisa para definir uma área de referência para sua busca.</p>
                            </div>
                        </div>
                        
                        <div style="display: flex; margin-bottom: 15px; align-items: flex-start;">
                            <div style="background: #3498db; color: white; border-radius: 50%; width: 28px; height: 28px; display: flex; justify-content: center; align-items: center; margin-right: 15px; flex-shrink: 0;">
                                <span style="font-weight: bold;">2</span>
                            </div>
                            <div>
                                <strong style="font-weight: 600; color: #2c3e50;">Selecione suas necessidades</strong> 
                                <p style="margin-top: 5px; color: #555;">Marque os serviços e pontos de interesse que são importantes para você e defina seu nível de importância (baixa, média, alta ou muito alta).</p>
                            </div>
                        </div>
                        
                        <div style="display: flex; margin-bottom: 15px; align-items: flex-start;">
                            <div style="background: #3498db; color: white; border-radius: 50%; width: 28px; height: 28px; display: flex; justify-content: center; align-items: center; margin-right: 15px; flex-shrink: 0;">
                                <span style="font-weight: bold;">3</span>
                            </div>
                            <div>
                                <strong style="font-weight: 600; color: #2c3e50;">Analise os resultados</strong> 
                                <p style="margin-top: 5px; color: #555;">O <span style="color: #3498db; font-weight: 500;">mapa de calor</span> mostrará as áreas mais adequadas, com marcadores indicando os melhores locais encontrados com base nos seus critérios.</p>
                            </div>
                        </div>
                    </div>
                    
                </div>
                
                <div style="text-align: center; margin-top: 30px;">
                    <div style="margin-bottom: 20px;">
                        <button id="ideal-finder-tutorial-btn" style="background-color: #3498db; color: white; border: none; padding: 12px 25px; border-radius: 30px; font-weight: 600; font-size: 16px; cursor: pointer; width: 100%; transition: all 0.2s ease;">Começar a explorar</button>
                    </div>
                    <div style="display: flex; align-items: center; justify-content: center; font-size: 14px; color: #7f8c8d;">
                        <input type="checkbox" id="dont-show-ideal-finder-tutorial" style="margin-right: 8px;">
                        <label for="dont-show-ideal-finder-tutorial">Não mostrar novamente</label>
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
            
            // Enviar evento de que o tutorial foi mostrado
            document.dispatchEvent(new Event('tutorialShown'));
        }, 100);
        
        // Prevenir cliques no tutorial de propagarem para o mapa
        tutorialBox.addEventListener('click', function(event) {
            event.stopPropagation();
        });
        
        // Adicionar efeito hover ao botão
        const tutorialBtn = document.getElementById('ideal-finder-tutorial-btn');
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
            
            // Guardar preferência se a caixa de verificação estiver marcada
            if (document.getElementById('dont-show-ideal-finder-tutorial').checked) {
                localStorage.setItem('minu15_ideal_finder_tutorial_seen', 'true');
            }
            
            // Remover após a animação completar
            setTimeout(() => {
                document.getElementById('ideal-finder-tutorial-box').remove();
                
                // Enviar evento de que o tutorial foi fechado
                document.dispatchEvent(new Event('tutorialClosed'));
            }, 300);
        });
    }
}

// Inicializar quando a página carrega
let idealFinder;
$(document).ready(() => {
    idealFinder = new IdealLocationFinder();
});
