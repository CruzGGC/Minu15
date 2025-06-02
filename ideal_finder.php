<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Localizador Ideal - Minu15</title>
    
    <!-- jQuery UI for autocomplete -->
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="https://code.jquery.com/ui/1.13.2/jquery-ui.min.js"></script>
    <link rel="stylesheet" href="https://code.jquery.com/ui/1.13.2/themes/base/jquery-ui.css">
    
    <!-- Leaflet CSS and JS -->
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    
    <!-- Leaflet Heat plugin for heatmap -->
    <script src="https://cdn.jsdelivr.net/gh/Leaflet/Leaflet.heat/dist/leaflet-heat.js"></script>
    
    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- Configuration Files -->
    <script src="config/api_config.js"></script>
    <script src="config/map_config.js"></script>
    
    <!-- Custom CSS -->
    <link rel="stylesheet" href="css/style.css">
    <link rel="stylesheet" href="css/landing.css">
</head>
<body class="ideal-finder-page">
    <div id="map"></div>
    
    <!-- Mobile menu toggle button -->
    <div class="mobile-menu-toggle" id="mobile-menu-toggle">
        <i class="fas fa-bars"></i>
    </div>
    
    <div class="overlay-panel" id="overlay-panel">
        <!-- Close button for mobile -->
        <div class="mobile-panel-close" id="mobile-panel-close">
            <i class="fas fa-times"></i>
        </div>
        
        <div class="logo-header">
            <a href="index.php">
                <img src="images/Minu15.png" alt="Minu15 Logo" class="app-logo">
            </a>
            <h3>Localizador Ideal</h3>
        </div>
        
        <!-- Location Input Section -->
        <div class="panel-section">
            <div class="panel-header">
                <span><i class="fas fa-map-marker-alt"></i> Local de Referência</span>
            </div>
            <div class="panel-content">
                <div class="search-container">
                    <input type="text" id="location-input" class="search-box" placeholder="Digite o endereço ou clique no mapa">
                    <button id="my-location-btn" class="search-button" title="Usar minha localização">
                        <i class="fas fa-crosshairs"></i>
                    </button>
                </div>
            </div>
        </div>
        
        <!-- POI Requirements Section -->
        <div class="panel-section">
            <div class="panel-header" id="poi-header">
                <span><i class="fas fa-list-check"></i> Necessidades</span>
                <span class="dropdown-arrow">▼</span>
            </div>
            <div class="panel-content" id="poi-content">
                <!-- Health Category -->
                <div class="poi-category">
                    <div class="category-header">
                        <span><i class="fas fa-heart-pulse"></i> Saúde</span>
                        <span class="dropdown-arrow">▼</span>
                    </div>
                    <div class="category-content">
                        <div class="poi-item-finder">
                            <div class="poi-checkbox">
                                <input type="checkbox" id="poi-hospitals" name="poi" value="hospitals">
                                <label for="poi-hospitals"><i class="fas fa-hospital"></i> Hospitais</label>
                            </div>
                            <select class="importance-select" disabled>
                                <option value="1">Baixa</option>
                                <option value="2">Média</option>
                                <option value="3" selected>Alta</option>
                                <option value="4">Muito Alta</option>
                            </select>
                        </div>
                        <div class="poi-item-finder">
                            <div class="poi-checkbox">
                                <input type="checkbox" id="poi-health_centers" name="poi" value="health_centers">
                                <label for="poi-health_centers"><i class="fas fa-first-aid-kit"></i> Centros de Saúde</label>
                            </div>
                            <select class="importance-select" disabled>
                                <option value="1">Baixa</option>
                                <option value="2" selected>Média</option>
                                <option value="3">Alta</option>
                                <option value="4">Muito Alta</option>
                            </select>
                        </div>
                        <div class="poi-item-finder">
                            <div class="poi-checkbox">
                                <input type="checkbox" id="poi-pharmacies" name="poi" value="pharmacies">
                                <label for="poi-pharmacies"><i class="fas fa-prescription-bottle-alt"></i> Farmácias</label>
                            </div>
                            <select class="importance-select" disabled>
                                <option value="1">Baixa</option>
                                <option value="2" selected>Média</option>
                                <option value="3">Alta</option>
                                <option value="4">Muito Alta</option>
                            </select>
                        </div>
                        <div class="poi-item-finder">
                            <div class="poi-checkbox">
                                <input type="checkbox" id="poi-dentists" name="poi" value="dentists">
                                <label for="poi-dentists"><i class="fas fa-tooth"></i> Clínicas Dentárias</label>
                            </div>
                            <select class="importance-select" disabled>
                                <option value="1">Baixa</option>
                                <option value="2" selected>Média</option>
                                <option value="3">Alta</option>
                                <option value="4">Muito Alta</option>
                            </select>
                        </div>
                    </div>
                </div>

                <!-- Education Category -->
                <div class="poi-category">
                    <div class="category-header">
                        <span><i class="fas fa-graduation-cap"></i> Educação</span>
                        <span class="dropdown-arrow">▼</span>
                    </div>
                    <div class="category-content">
                        <div class="poi-item-finder">
                            <div class="poi-checkbox">
                                <input type="checkbox" id="poi-schools" name="poi" value="schools">
                                <label for="poi-schools"><i class="fas fa-school"></i> Escolas Primárias e Secundárias</label>
                            </div>
                            <select class="importance-select" disabled>
                                <option value="1">Baixa</option>
                                <option value="2">Média</option>
                                <option value="3" selected>Alta</option>
                                <option value="4">Muito Alta</option>
                            </select>
                        </div>
                        <div class="poi-item-finder">
                            <div class="poi-checkbox">
                                <input type="checkbox" id="poi-universities" name="poi" value="universities">
                                <label for="poi-universities"><i class="fas fa-university"></i> Universidades e Institutos</label>
                            </div>
                            <select class="importance-select" disabled>
                                <option value="1">Baixa</option>
                                <option value="2" selected>Média</option>
                                <option value="3">Alta</option>
                                <option value="4">Muito Alta</option>
                            </select>
                        </div>
                        <div class="poi-item-finder">
                            <div class="poi-checkbox">
                                <input type="checkbox" id="poi-kindergartens" name="poi" value="kindergartens">
                                <label for="poi-kindergartens"><i class="fas fa-baby"></i> Jardins de Infância e Creches</label>
                            </div>
                            <select class="importance-select" disabled>
                                <option value="1">Baixa</option>
                                <option value="2">Média</option>
                                <option value="3" selected>Alta</option>
                                <option value="4">Muito Alta</option>
                            </select>
                        </div>
                        <div class="poi-item-finder">
                            <div class="poi-checkbox">
                                <input type="checkbox" id="poi-libraries" name="poi" value="libraries">
                                <label for="poi-libraries"><i class="fas fa-book"></i> Bibliotecas</label>
                            </div>
                            <select class="importance-select" disabled>
                                <option value="1" selected>Baixa</option>
                                <option value="2">Média</option>
                                <option value="3">Alta</option>
                                <option value="4">Muito Alta</option>
                            </select>
                        </div>
                    </div>
                </div>

                <!-- Commercial & Services Category -->
                <div class="poi-category">
                    <div class="category-header">
                        <span><i class="fas fa-shopping-cart"></i> Comércio e Serviços</span>
                        <span class="dropdown-arrow">▼</span>
                    </div>
                    <div class="category-content">
                        <div class="poi-item-finder">
                            <div class="poi-checkbox">
                                <input type="checkbox" id="poi-supermarkets" name="poi" value="supermarkets">
                                <label for="poi-supermarkets"><i class="fas fa-shopping-basket"></i> Supermercados</label>
                            </div>
                            <select class="importance-select" disabled>
                                <option value="1">Baixa</option>
                                <option value="2">Média</option>
                                <option value="3" selected>Alta</option>
                                <option value="4">Muito Alta</option>
                            </select>
                        </div>
                        <div class="poi-item-finder">
                            <div class="poi-checkbox">
                                <input type="checkbox" id="poi-malls" name="poi" value="malls">
                                <label for="poi-malls"><i class="fas fa-shopping-bag"></i> Centros Comerciais</label>
                            </div>
                            <select class="importance-select" disabled>
                                <option value="1" selected>Baixa</option>
                                <option value="2">Média</option>
                                <option value="3">Alta</option>
                                <option value="4">Muito Alta</option>
                            </select>
                        </div>
                        <div class="poi-item-finder">
                            <div class="poi-checkbox">
                                <input type="checkbox" id="poi-restaurants" name="poi" value="restaurants">
                                <label for="poi-restaurants"><i class="fas fa-utensils"></i> Restaurantes e Cafés</label>
                            </div>
                            <select class="importance-select" disabled>
                                <option value="1" selected>Baixa</option>
                                <option value="2">Média</option>
                                <option value="3">Alta</option>
                                <option value="4">Muito Alta</option>
                            </select>
                        </div>
                        <div class="poi-item-finder">
                            <div class="poi-checkbox">
                                <input type="checkbox" id="poi-atms" name="poi" value="atms">
                                <label for="poi-atms"><i class="fas fa-money-bill-wave"></i> Caixas de Multibanco</label>
                            </div>
                            <select class="importance-select" disabled>
                                <option value="1">Baixa</option>
                                <option value="2" selected>Média</option>
                                <option value="3">Alta</option>
                                <option value="4">Muito Alta</option>
                            </select>
                        </div>
                    </div>
                </div>


                <!-- Safety & Emergency Category -->
                <div class="poi-category">
                    <div class="category-header">
                        <span><i class="fas fa-shield-alt"></i> Segurança e Serviços Públicos</span>
                        <span class="dropdown-arrow">▼</span>
                    </div>
                    <div class="category-content">
                        <div class="poi-item-finder">
                            <div class="poi-checkbox">
                                <input type="checkbox" id="poi-police" name="poi" value="police">
                                <label for="poi-police"><i class="fas fa-shield-alt"></i> Polícia</label>
                            </div>
                            <select class="importance-select" disabled>
                                <option value="1">Baixa</option>
                                <option value="2" selected>Média</option>
                                <option value="3">Alta</option>
                                <option value="4">Muito Alta</option>
                            </select>
                        </div>
                        <div class="poi-item-finder">
                            <div class="poi-checkbox">
                                <input type="checkbox" id="poi-police_stations" name="poi" value="police_stations">
                                <label for="poi-police_stations"><i class="fas fa-shield-alt"></i> Esquadras de Polícia</label>
                            </div>
                            <select class="importance-select" disabled>
                                <option value="1">Baixa</option>
                                <option value="2" selected>Média</option>
                                <option value="3">Alta</option>
                                <option value="4">Muito Alta</option>
                            </select>
                        </div>
                        <div class="poi-item-finder">
                            <div class="poi-checkbox">
                                <input type="checkbox" id="poi-fire_stations" name="poi" value="fire_stations">
                                <label for="poi-fire_stations"><i class="fas fa-fire"></i> Bombeiros</label>
                            </div>
                            <select class="importance-select" disabled>
                                <option value="1">Baixa</option>
                                <option value="2" selected>Média</option>
                                <option value="3">Alta</option>
                                <option value="4">Muito Alta</option>
                            </select>
                        </div>
                        <div class="poi-item-finder">
                            <div class="poi-checkbox">
                                <input type="checkbox" id="poi-civil_protection" name="poi" value="civil_protection">
                                <label for="poi-civil_protection"><i class="fas fa-building-columns"></i> Serviços Governamentais Públicos</label>
                            </div>
                            <select class="importance-select" disabled>
                                <option value="1" selected>Baixa</option>
                                <option value="2">Média</option>
                                <option value="3">Alta</option>
                                <option value="4">Muito Alta</option>
                            </select>
                        </div>
                    </div>
                </div>

                <!-- Public Administration Category -->
                <div class="poi-category">
                    <div class="category-header">
                        <span><i class="fas fa-landmark"></i> Administração Pública</span>
                        <span class="dropdown-arrow">▼</span>
                    </div>
                    <div class="category-content">
                        <div class="poi-item-finder">
                            <div class="poi-checkbox">
                                <input type="checkbox" id="poi-city_halls" name="poi" value="city_halls">
                                <label for="poi-city_halls"><i class="fas fa-landmark"></i> Câmaras Municipais</label>
                            </div>
                            <select class="importance-select" disabled>
                                <option value="1" selected>Baixa</option>
                                <option value="2">Média</option>
                                <option value="3">Alta</option>
                                <option value="4">Muito Alta</option>
                            </select>
                        </div>
                        <div class="poi-item-finder">
                            <div class="poi-checkbox">
                                <input type="checkbox" id="poi-post_offices" name="poi" value="post_offices">
                                <label for="poi-post_offices"><i class="fas fa-envelope"></i> Correios</label>
                            </div>
                            <select class="importance-select" disabled>
                                <option value="1" selected>Baixa</option>
                                <option value="2">Média</option>
                                <option value="3">Alta</option>
                                <option value="4">Muito Alta</option>
                            </select>
                        </div>
                    </div>
                </div>

                <!-- Culture & Leisure Category -->
                <div class="poi-category">
                    <div class="category-header">
                        <span><i class="fas fa-theater-masks"></i> Cultura e Lazer</span>
                        <span class="dropdown-arrow">▼</span>
                    </div>
                    <div class="category-content">
                        <div class="poi-item-finder">
                            <div class="poi-checkbox">
                                <input type="checkbox" id="poi-museums" name="poi" value="museums">
                                <label for="poi-museums"><i class="fas fa-museum"></i> Museus</label>
                            </div>
                            <select class="importance-select" disabled>
                                <option value="1" selected>Baixa</option>
                                <option value="2">Média</option>
                                <option value="3">Alta</option>
                                <option value="4">Muito Alta</option>
                            </select>
                        </div>
                        <div class="poi-item-finder">
                            <div class="poi-checkbox">
                                <input type="checkbox" id="poi-theaters" name="poi" value="theaters">
                                <label for="poi-theaters"><i class="fas fa-theater-masks"></i> Teatros</label>
                            </div>
                            <select class="importance-select" disabled>
                                <option value="1" selected>Baixa</option>
                                <option value="2">Média</option>
                                <option value="3">Alta</option>
                                <option value="4">Muito Alta</option>
                            </select>
                        </div>
                        <div class="poi-item-finder">
                            <div class="poi-checkbox">
                                <input type="checkbox" id="poi-sports" name="poi" value="sports">
                                <label for="poi-sports"><i class="fas fa-dumbbell"></i> Ginásios e Centros Desportivos</label>
                            </div>
                            <select class="importance-select" disabled>
                                <option value="1" selected>Baixa</option>
                                <option value="2">Média</option>
                                <option value="3">Alta</option>
                                <option value="4">Muito Alta</option>
                            </select>
                        </div>
                        <div class="poi-item-finder">
                            <div class="poi-checkbox">
                                <input type="checkbox" id="poi-parks" name="poi" value="parks">
                                <label for="poi-parks"><i class="fas fa-tree"></i> Parques</label>
                            </div>
                            <select class="importance-select" disabled>
                                <option value="1" selected>Baixa</option>
                                <option value="2">Média</option>
                                <option value="3">Alta</option>
                                <option value="4">Muito Alta</option>
                            </select>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Transport Mode Section -->
        <div class="panel-section">
            <div class="panel-header">
                <span><i class="fas fa-route"></i> Modo de Transporte</span>
            </div>
            <div class="transport-mode">
                <div class="transport-option active" data-mode="foot-walking">
                    <div class="transport-icon"><i class="fas fa-walking"></i></div>
                    <span>A pé</span>
                </div>
                <div class="transport-option" data-mode="cycling-regular">
                    <div class="transport-icon"><i class="fas fa-bicycle"></i></div>
                    <span>Bicicleta</span>
                </div>
                <div class="transport-option" data-mode="driving-car">
                    <div class="transport-icon"><i class="fas fa-car"></i></div>
                    <span>Carro</span>
                </div>
            </div>
        </div>
        
        <!-- Time Settings Section -->
        <div class="panel-section">
            <div class="panel-header">
                <span><i class="fas fa-clock"></i> Tempo Máximo</span>
            </div>
            <input type="range" class="distance-slider" id="max-time" min="5" max="30" value="15">
            <div id="time-display">15 minutos</div>
        </div>
        
        <!-- Map Style Section -->
        <div class="panel-section">
            <div class="panel-header" id="map-style-header">
                <span><i class="fas fa-layer-group"></i> Estilo do Mapa</span>
                <span class="dropdown-arrow">▼</span>
            </div>
            <div class="panel-content" id="map-style-content">
                <div class="map-style-selector">
                    <div class="map-style-option" data-provider="osm">
                        <div class="map-style-icon"><i class="fas fa-map"></i></div>
                        <span>OSM</span>
                    </div>
                    <div class="map-style-option active" data-provider="positron">
                        <div class="map-style-icon"><i class="fas fa-sun"></i></div>
                        <span>Carto Light</span>
                    </div>
                    <div class="map-style-option" data-provider="dark_matter">
                        <div class="map-style-icon"><i class="fas fa-moon"></i></div>
                        <span>Carto Dark</span>
                    </div>
                    <div class="map-style-option" data-provider="topo">
                        <div class="map-style-icon"><i class="fas fa-mountain"></i></div>
                        <span>Topo</span>
                    </div>
                    <div class="map-style-option" data-provider="satellite">
                        <div class="map-style-icon"><i class="fas fa-satellite"></i></div>
                        <span>Satélite</span>
                    </div>
                    <div class="map-style-option" data-provider="esri_gray">
                        <div class="map-style-icon"><i class="fas fa-pencil-alt"></i></div>
                        <span>ESRI Cinza</span>
                    </div>
                    <div class="map-style-option" data-provider="osm_hot">
                        <div class="map-style-icon"><i class="fas fa-hands-helping"></i></div>
                        <span>OSM HOT</span>
                    </div>
                    <div class="map-style-option" data-provider="voyager">
                        <div class="map-style-icon"><i class="fas fa-compass"></i></div>
                        <span>Voyager</span>
                    </div>
                    <div class="map-style-option" data-provider="esri_streets">
                        <div class="map-style-icon"><i class="fas fa-road"></i></div>
                        <span>ESRI Ruas</span>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Analysis Settings Section -->
        <div class="panel-section">
            <div class="panel-header" id="settings-header">
                <span><i class="fas fa-sliders-h"></i> Configurações da Análise</span>
                <span class="dropdown-arrow">▼</span>
            </div>
            <div class="panel-content" id="settings-content">
                <div class="setting-row">
                    <label for="grid-resolution">Resolução da grelha:</label>
                    <select id="grid-resolution">
                        <option value="50">50x50 (Rápida)</option>
                        <option value="75" selected>75x75 (Equilibrada)</option>
                        <option value="100">100x100 (Detalhada)</option>
                        <option value="150">150x150 (Muito Detalhada)</option>
                    </select>
                </div>
                <div class="setting-row">
                    <label for="heatmap-intensity">Intensidade do mapa de calor:</label>
                    <input type="range" id="heatmap-intensity" min="0.3" max="1.0" step="0.1" value="0.6">
                    <span id="intensity-value">0.6</span>
                </div>
                <div class="setting-row">
                    <label for="top-locations">Mostrar melhores locais:</label>
                    <select id="top-locations">
                        <option value="3">Top 3</option>
                        <option value="5" selected>Top 5</option>
                        <option value="10">Top 10</option>
                    </select>
                </div>
            </div>
        </div>
        
        <!-- Analysis Button -->
        <div class="panel-section">
            <button id="analyze-btn" class="calculate-button">
                <i class="fas fa-search"></i>
                Encontrar Locais Ideais
            </button>
        </div>
    </div>

    <!-- Results Panel -->
    <div id="results-panel" class="results-panel">
        <div class="results-header">
            <h3><i class="fas fa-trophy"></i> Melhores Localizações</h3>
            <button id="toggle-results" class="toggle-btn">
                <i class="fas fa-chevron-down"></i>
            </button>
        </div>
        <div class="results-content">
            <div id="results-list" class="results-list">
                <!-- Results will be populated here -->
            </div>
            <div class="heatmap-controls">
                <button id="toggle-heatmap" class="control-btn">
                    <i class="fas fa-eye"></i>
                    <span>Ocultar Mapa de Calor</span>
                </button>
                <button id="reset-view" class="control-btn">
                    <i class="fas fa-home"></i>
                    <span>Centrar Vista</span>
                </button>
            </div>
        </div>
    </div>

    <!-- Loading Overlay -->
    <div id="loading-overlay" class="loading-overlay">
        <div class="loading-content">
            <div class="loading-spinner"></div>
            <h3>Analisando Localizações Ideais</h3>
            <p id="loading-status">Preparando análise...</p>
            <div class="progress-bar">
                <div class="progress-fill" id="progress-fill"></div>
            </div>
            <span id="progress-text">0%</span>
        </div>
    </div>

    <!-- Custom JavaScript -->
    <script src="js/ideal_finder.js"></script>
</body>
</html>
