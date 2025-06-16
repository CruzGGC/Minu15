// Ideal Location Finder JavaScript
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
        
        // Show the tutorial after a short delay
        setTimeout(() => {
            this.showIdealFinderTutorial();
        }, 800);
    }

    initMap() {
        // Initialize map centered on Aveiro, Portugal
        const aveiroCenter = [40.6405, -8.6538];
        this.map = L.map('map').setView(aveiroCenter, 13);
        
        // Add tile layer using map configuration
        this.currentTileLayer = null;
        this.selectedTileProvider = DEFAULT_TILE_PROVIDER;
        this.updateMapTiles(this.selectedTileProvider);

        // Map click handler
        this.map.on('click', (e) => {
            this.setLocation(e.latlng.lat, e.latlng.lng);
        });
    }

    initEventListeners() {
        // Transport mode buttons (now using transport-option class like app.php)
        document.querySelectorAll('.transport-option').forEach(btn => {
            btn.addEventListener('click', () => {
                document.querySelector('.transport-option.active')?.classList.remove('active');
                btn.classList.add('active');
            });
        });

        // Time slider
        const timeSlider = document.getElementById('max-time');
        const timeDisplay = document.getElementById('time-display');
        timeSlider.addEventListener('input', () => {
            timeDisplay.textContent = timeSlider.value + ' minutos';
        });

        // Heatmap intensity slider
        const intensitySlider = document.getElementById('heatmap-intensity');
        const intensityValue = document.getElementById('intensity-value');
        if (intensitySlider && intensityValue) {
            intensitySlider.addEventListener('input', () => {
                intensityValue.textContent = intensitySlider.value;
            });
        }

        // My location button
        document.getElementById('my-location-btn').addEventListener('click', () => {
            this.getCurrentLocation();
        });

        // Map style selector
        document.querySelectorAll('.map-style-option').forEach(option => {
            option.addEventListener('click', () => {
                const provider = option.getAttribute('data-provider');
                this.updateMapTiles(provider);
            });
        });

        // Analyze button (now using calculate-button class like app.php)
        document.getElementById('analyze-btn').addEventListener('click', () => {
            this.startAnalysis();
        });

        // Results panel toggle
        document.getElementById('toggle-results').addEventListener('click', () => {
            this.toggleResultsPanel();
        });

        // Heatmap controls
        document.getElementById('toggle-heatmap').addEventListener('click', () => {
            this.toggleHeatmap();
        });

        document.getElementById('reset-view').addEventListener('click', () => {
            this.resetMapView();
        });

        // Panel collapsible sections
        this.initCollapsibleSections();

        // Mobile menu toggle
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
        console.log('Initializing collapsible sections...'); // Debug log
        
        // POI section toggle
        const poiHeader = document.getElementById('poi-header');
        const poiContent = document.getElementById('poi-content');
        
        console.log('POI Header:', poiHeader, 'POI Content:', poiContent); // Debug log
        
        if (poiHeader && poiContent) {
            poiHeader.addEventListener('click', () => {
                console.log('POI header clicked'); // Debug log
                poiContent.classList.toggle('expanded');
                const arrow = poiHeader.querySelector('.dropdown-arrow');
                if (arrow) {
                    arrow.classList.toggle('up');
                }
            });
        }

        // Settings section toggle
        const settingsHeader = document.getElementById('settings-header');
        const settingsContent = document.getElementById('settings-content');
        
        console.log('Settings Header:', settingsHeader, 'Settings Content:', settingsContent); // Debug log
        
        if (settingsHeader && settingsContent) {
            settingsHeader.addEventListener('click', () => {
                console.log('Settings header clicked'); // Debug log
                settingsContent.classList.toggle('expanded');
                const arrow = settingsHeader.querySelector('.dropdown-arrow');
                if (arrow) {
                    arrow.classList.toggle('up');
                }
            });
        }

        // Category toggles
        const categoryHeaders = document.querySelectorAll('.category-header');
        console.log('Found category headers:', categoryHeaders.length); // Debug log
        
        categoryHeaders.forEach((header, index) => {
            console.log(`Setting up category header ${index}:`, header); // Debug log
            header.addEventListener('click', (e) => {
                console.log(`Category header ${index} clicked`); // Debug log
                // Prevent event propagation to parent panel
                e.stopPropagation();
                
                const content = header.nextElementSibling;
                if (content && content.classList.contains('category-content')) {
                    content.classList.toggle('expanded');
                    const arrow = header.querySelector('.dropdown-arrow');
                    if (arrow) {
                        arrow.classList.toggle('up');
                    }
                }
            });
        });

        // Start with POI panel expanded
        if (poiContent) {
            console.log('Expanding POI panel by default'); // Debug log
            poiContent.classList.add('expanded');
            const arrow = poiHeader ? poiHeader.querySelector('.dropdown-arrow') : null;
            if (arrow) {
                arrow.classList.add('up');
            }
        }

        // Map style section toggle
        const mapStyleHeader = document.getElementById('map-style-header');
        const mapStyleContent = document.getElementById('map-style-content');
        
        if (mapStyleHeader && mapStyleContent) {
            mapStyleHeader.addEventListener('click', () => {
                mapStyleContent.classList.toggle('expanded');
                const arrow = mapStyleHeader.querySelector('.dropdown-arrow');
                if (arrow) {
                    arrow.classList.toggle('up');
                }
            });
        }
    }

    initAutocomplete() {
        $('#location-input').autocomplete({
            source: (request, response) => {
                // Use Nominatim for geocoding
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
        // Enable/disable importance selectors based on checkboxes
        document.querySelectorAll('input[name="poi"]').forEach(checkbox => {
            checkbox.addEventListener('change', () => {
                const poiItem = checkbox.closest('.poi-item-finder');
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
        
        // Remove existing marker
        if (this.currentMarker) {
            this.map.removeLayer(this.currentMarker);
        }

        // Add new marker
        this.currentMarker = L.marker([lat, lng], {
            icon: L.divIcon({
                className: 'custom-marker reference-marker',
                html: '<i class="fas fa-map-marker-alt"></i>',
                iconSize: [30, 30],
                iconAnchor: [15, 30]
            })
        }).addTo(this.map);

        // Center map on location
        this.map.setView([lat, lng], 13);

        // Reverse geocode to update input
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
            console.error('Analysis error:', error);
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
                timeout: 120000, // 2 minutes timeout
                xhr: () => {
                    const xhr = new XMLHttpRequest();
                    xhr.upload.addEventListener('progress', (e) => {
                        if (e.lengthComputable) {
                            const percentComplete = (e.loaded / e.total) * 100;
                            this.updateProgress(percentComplete, 'Enviando dados...');
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

            // Simulate progress updates
            this.simulateProgress();
        });
    }

    simulateProgress() {
        let progress = 0;
        const stages = [
            { progress: 20, message: 'Configurando análise de grelha...' },
            { progress: 40, message: 'Consultando base de dados...' },
            { progress: 60, message: 'Calculando acessibilidade...' },
            { progress: 80, message: 'Gerando mapa de calor...' },
            { progress: 95, message: 'Finalizando resultados...' }
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
        this.updateProgress(0, 'Iniciando análise...');
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
        // Remove heatmap
        if (this.heatmapLayer) {
            this.map.removeLayer(this.heatmapLayer);
            this.heatmapLayer = null;
        }

        // Remove top location markers
        this.topLocationMarkers.forEach(marker => {
            this.map.removeLayer(marker);
        });
        this.topLocationMarkers = [];

        // Clear results list
        document.getElementById('results-list').innerHTML = '';
        document.getElementById('results-panel').style.display = 'none';
    }

    displayResults(data) {
        // Display heatmap
        if (data.heatmap && data.heatmap.length > 0) {
            this.displayHeatmap(data.heatmap);
        }

        // Display top locations
        if (data.top_locations && data.top_locations.length > 0) {
            this.displayTopLocations(data.top_locations);
        }

        // Show results panel
        document.getElementById('results-panel').style.display = 'block';
    }

    displayHeatmap(heatmapData) {
        const intensity = parseFloat(document.getElementById('heatmap-intensity').value);
        
        this.heatmapLayer = L.heatLayer(heatmapData, {
            radius: 25,
            blur: 15,
            maxZoom: 17,
            gradient: {
                0.0: '#0000ff',  // Blue for low scores
                0.2: '#00ffff',  // Cyan
                0.4: '#00ff00',  // Green
                0.6: '#ffff00',  // Yellow
                0.8: '#ff8000',  // Orange
                1.0: '#ff0000'   // Red for high scores
            },
            max: intensity
        }).addTo(this.map);
    }

    displayTopLocations(topLocations) {
        const resultsList = document.getElementById('results-list');
        resultsList.innerHTML = '';

        topLocations.forEach((location, index) => {
            // Add marker to map
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

            // Create popup with details
            const popupContent = this.createLocationPopup(location, index + 1);
            marker.bindPopup(popupContent, { maxWidth: 300 });

            this.topLocationMarkers.push(marker);

            // Add to results list
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

        // Add click handler to expand/collapse details
        resultItem.querySelector('.result-header').addEventListener('click', () => {
            resultItem.classList.toggle('expanded');
        });

        return resultItem;
    }

    getPOIDisplayName(type) {
        const displayNames = {
            // === Health ===
            'hospitals': 'Hospitais',
            'health_centers': 'Centros de Saúde',
            'pharmacies': 'Farmácias',
            'dentists': 'Clínicas Dentárias',
            
            // === Education ===
            'schools': 'Escolas Primárias e Secundárias',
            'universities': 'Universidades e Institutos',
            'kindergartens': 'Jardins de Infância e Creches',
            'libraries': 'Bibliotecas',
            
            // === Commercial & Services ===
            'supermarkets': 'Supermercados',
            'malls': 'Centros Comerciais',
            'restaurants': 'Restaurantes e Cafés',
            'atms': 'Caixas de Multibanco',
            
            // === Safety ===
            'police_stations': 'Esquadras de Polícia',
            'fire_stations': 'Bombeiros',
            'civil_protection': 'Proteção Civil',
            
            // === Public Administration ===
            'parish_councils': 'Juntas de Freguesia',
            'city_halls': 'Câmaras Municipais',
            'post_offices': 'Correios',
            
            // === Culture & Leisure ===
            'museums': 'Museus',
            'theaters': 'Teatros',
            'sports': 'Ginásios e Centros Desportivos',
            'parks': 'Parques',
            
            // === Legacy support for old POI types ===
            'hospital': 'Hospital',
            'clinic': 'Clínica',
            'pharmacy': 'Farmácia',
            'school': 'Escola',
            'university': 'Universidade',
            'kindergarten': 'Creche',
            'supermarket': 'Supermercado',
            'restaurant': 'Restaurante',
            'bank': 'Banco',
            'shopping_mall': 'Shopping',
            'bus_stop': 'Parada de Ônibus',
            'subway_station': 'Estação Metro',
            'post_office': 'Correios',
            'fuel': 'Posto Combustível'
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

    // Update map tiles based on selected provider
    updateMapTiles(provider) {
        // If there's an existing tile layer, remove it
        if (this.currentTileLayer) {
            this.map.removeLayer(this.currentTileLayer);
        }

        // Get the provider configuration
        const tileConfig = MAP_TILE_PROVIDERS[provider] || MAP_TILE_PROVIDERS[DEFAULT_TILE_PROVIDER];

        // Create and add the new tile layer
        this.currentTileLayer = L.tileLayer(tileConfig.url, {
            attribution: tileConfig.attribution,
            maxZoom: tileConfig.maxZoom
        }).addTo(this.map);

        // Update the selectedTileProvider variable
        this.selectedTileProvider = provider;

        // Update the map style selector UI
        this.updateMapStyleSelector();
    }

    // Update the map style selector buttons to show the active style
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
     * Shows a tutorial explaining the concept and functionality of the Ideal Location Finder
     */
    showIdealFinderTutorial() {
        // Check if the user has seen the tutorial before
        if (localStorage.getItem('minu15_ideal_finder_tutorial_seen') === 'true') {
            return;
        }
        
        // Create the tutorial container
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
        
        // Create tutorial content
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
                            O Localizador Ideal é uma ferramenta conceitual que permite descobrir as áreas que melhor satisfazem suas necessidades de acessibilidade a serviços e pontos de interesse. A ferramenta utiliza análises espaciais e algoritmos para gerar um mapa de calor mostrando as áreas mais adequadas.
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
                                <strong style="font-weight: 600; color: #2c3e50;">Configure os parâmetros</strong> 
                                <p style="margin-top: 5px; color: #555;">Escolha o modo de transporte (a pé, bicicleta ou carro) e defina o tempo máximo de deslocamento que considera aceitável.</p>
                            </div>
                        </div>
                        
                        <div style="display: flex; margin-bottom: 15px; align-items: flex-start;">
                            <div style="background: #3498db; color: white; border-radius: 50%; width: 28px; height: 28px; display: flex; justify-content: center; align-items: center; margin-right: 15px; flex-shrink: 0;">
                                <span style="font-weight: bold;">4</span>
                            </div>
                            <div>
                                <strong style="font-weight: 600; color: #2c3e50;">Analise os resultados</strong> 
                                <p style="margin-top: 5px; color: #555;">O <span style="color: #3498db; font-weight: 500;">mapa de calor</span> mostrará as áreas mais adequadas, com marcadores indicando os melhores locais encontrados com base nos seus critérios.</p>
                            </div>
                        </div>
                    </div>
                    
                    <div style="background-color: #fff8e6; border-left: 4px solid #f39c12; padding: 15px; border-radius: 5px; margin-top: 20px;">
                        <div style="display: flex; align-items: center; margin-bottom: 8px;">
                            <i class="fas fa-lightbulb" style="color: #f39c12; margin-right: 10px; font-size: 18px;"></i>
                            <strong style="font-weight: 600; color: #2c3e50;">Dica</strong>
                        </div>
                        <p style="margin: 0; color: #555; font-size: 14px;">
                            Este é um modelo conceitual para demonstração. Em uma implementação completa, a análise utilizaria dados reais de POIs, transportes e acessibilidade para encontrar locais ideais com base nos seus critérios específicos.
                        </p>
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
        
        // Add to document
        document.body.appendChild(tutorialBox);
        
        // Add entrance animation
        setTimeout(() => {
            tutorialBox.style.opacity = '1';
            tutorialBox.style.transform = 'translate(-50%, -50%) scale(1)';
            
            // Dispatch event that tutorial is shown
            document.dispatchEvent(new Event('tutorialShown'));
        }, 100);
        
        // Prevent clicks on the tutorial from propagating to the map
        tutorialBox.addEventListener('click', function(event) {
            event.stopPropagation();
        });
        
        // Add hover effect to the button
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
        
        // Close button event
        tutorialBtn.addEventListener('click', function(event) {
            event.stopPropagation();
            
            // Add exit animation
            tutorialBox.style.opacity = '0';
            tutorialBox.style.transform = 'translate(-50%, -50%) scale(0.9)';
            
            // Save preference if checkbox is checked
            if (document.getElementById('dont-show-ideal-finder-tutorial').checked) {
                localStorage.setItem('minu15_ideal_finder_tutorial_seen', 'true');
            }
            
            // Remove after animation completes
            setTimeout(() => {
                document.getElementById('ideal-finder-tutorial-box').remove();
                
                // Dispatch event that tutorial is closed
                document.dispatchEvent(new Event('tutorialClosed'));
            }, 300);
        });
    }
}

// Initialize when page loads
let idealFinder;
$(document).ready(() => {
    idealFinder = new IdealLocationFinder();
});
