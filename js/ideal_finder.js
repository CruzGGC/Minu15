// Ideal Location Finder JavaScript
class IdealLocationFinder {
    constructor() {
        this.map = null;
        this.currentMarker = null;
        this.heatmapLayer = null;
        this.topLocationMarkers = [];
        this.isAnalyzing = false;
        this.currentLocation = null;
        
        this.init();
    }

    init() {
        this.initMap();
        this.initEventListeners();
        this.initAutocomplete();
        this.setupPOIControls();
    }

    initMap() {
        // Initialize map centered on Lisbon
        this.map = L.map('map').setView([38.7223, -9.1393], 11);
        
        // Add tile layer
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            attribution: '© OpenStreetMap contributors'
        }).addTo(this.map);

        // Map click handler
        this.map.on('click', (e) => {
            this.setLocation(e.latlng.lat, e.latlng.lng);
        });
    }

    initEventListeners() {
        // Transport mode buttons
        document.querySelectorAll('.transport-btn').forEach(btn => {
            btn.addEventListener('click', () => {
                document.querySelector('.transport-btn.active')?.classList.remove('active');
                btn.classList.add('active');
            });
        });

        // Time slider
        const timeSlider = document.getElementById('max-time');
        const timeDisplay = document.getElementById('time-display');
        timeSlider.addEventListener('input', () => {
            timeDisplay.textContent = timeSlider.value;
        });

        // My location button
        document.getElementById('my-location-btn').addEventListener('click', () => {
            this.getCurrentLocation();
        });

        // Analyze button
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
                const select = checkbox.closest('.poi-item').querySelector('.importance-select');
                select.disabled = !checkbox.checked;
                if (checkbox.checked) {
                    select.style.opacity = '1';
                } else {
                    select.style.opacity = '0.5';
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
            const poiItem = checkbox.closest('.poi-item');
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
        const transportMode = document.querySelector('.transport-btn.active').dataset.mode;
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
}

// Initialize when page loads
let idealFinder;
$(document).ready(() => {
    idealFinder = new IdealLocationFinder();
});
