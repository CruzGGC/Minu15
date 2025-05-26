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
    <!-- Navigation Header -->
    <nav class="top-nav">
        <div class="nav-container">
            <div class="nav-brand">
                <img src="images/Minu15.png" alt="Minu15" class="nav-logo">
                <span class="nav-title">Localizador Ideal</span>
            </div>
            <div class="nav-links">
                <a href="index.php" class="nav-link">
                    <i class="fas fa-home"></i>
                    <span>Início</span>
                </a>
                <a href="app.php" class="nav-link">
                    <i class="fas fa-map"></i>
                    <span>Explorador</span>
                </a>
                <a href="ideal_finder.php" class="nav-link active">
                    <i class="fas fa-search-location"></i>
                    <span>Localizador Ideal</span>
                </a>
            </div>
        </div>
    </nav>

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
            <img src="images/Minu15.png" alt="Minu15 Logo" class="app-logo">
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
                                <input type="checkbox" id="poi-hospital" name="poi" value="hospital">
                                <label for="poi-hospital"><i class="fas fa-hospital"></i> Hospital</label>
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
                                <input type="checkbox" id="poi-clinic" name="poi" value="clinic">
                                <label for="poi-clinic"><i class="fas fa-first-aid-kit"></i> Clínica</label>
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
                                <input type="checkbox" id="poi-pharmacy" name="poi" value="pharmacy">
                                <label for="poi-pharmacy"><i class="fas fa-prescription-bottle-alt"></i> Farmácia</label>
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
                                <input type="checkbox" id="poi-school" name="poi" value="school">
                                <label for="poi-school"><i class="fas fa-school"></i> Escola</label>
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
                                <input type="checkbox" id="poi-university" name="poi" value="university">
                                <label for="poi-university"><i class="fas fa-university"></i> Universidade</label>
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
                                <input type="checkbox" id="poi-kindergarten" name="poi" value="kindergarten">
                                <label for="poi-kindergarten"><i class="fas fa-baby"></i> Creche</label>
                            </div>
                            <select class="importance-select" disabled>
                                <option value="1">Baixa</option>
                                <option value="2">Média</option>
                                <option value="3" selected>Alta</option>
                                <option value="4">Muito Alta</option>
                            </select>
                        </div>
                    </div>
                </div>

                <!-- Commercial Category -->
                <div class="poi-category">
                    <div class="category-header">
                        <span><i class="fas fa-shopping-cart"></i> Comércio</span>
                        <span class="dropdown-arrow">▼</span>
                    </div>
                    <div class="category-content">
                        <div class="poi-item-finder">
                            <div class="poi-checkbox">
                                <input type="checkbox" id="poi-supermarket" name="poi" value="supermarket">
                                <label for="poi-supermarket"><i class="fas fa-shopping-basket"></i> Supermercado</label>
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
                                <input type="checkbox" id="poi-restaurant" name="poi" value="restaurant">
                                <label for="poi-restaurant"><i class="fas fa-utensils"></i> Restaurante</label>
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
                                <input type="checkbox" id="poi-bank" name="poi" value="bank">
                                <label for="poi-bank"><i class="fas fa-university"></i> Banco</label>
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
                                <input type="checkbox" id="poi-shopping_mall" name="poi" value="shopping_mall">
                                <label for="poi-shopping_mall"><i class="fas fa-shopping-bag"></i> Shopping</label>
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

                <!-- Transport & Services Category -->
                <div class="poi-category">
                    <div class="category-header">
                        <span><i class="fas fa-cogs"></i> Transporte & Serviços</span>
                        <span class="dropdown-arrow">▼</span>
                    </div>
                    <div class="category-content">
                        <div class="poi-item-finder">
                            <div class="poi-checkbox">
                                <input type="checkbox" id="poi-bus_stop" name="poi" value="bus_stop">
                                <label for="poi-bus_stop"><i class="fas fa-bus"></i> Parada de Ônibus</label>
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
                                <input type="checkbox" id="poi-subway_station" name="poi" value="subway_station">
                                <label for="poi-subway_station"><i class="fas fa-subway"></i> Estação Metro</label>
                            </div>
                            <select class="importance-select" disabled>
                                <option value="1">Baixa</option>
                                <option value="2">Média</option>
                                <option value="3">Alta</option>
                                <option value="4" selected>Muito Alta</option>
                            </select>
                        </div>
                        <div class="poi-item-finder">
                            <div class="poi-checkbox">
                                <input type="checkbox" id="poi-post_office" name="poi" value="post_office">
                                <label for="poi-post_office"><i class="fas fa-mail-bulk"></i> Correios</label>
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
                                <input type="checkbox" id="poi-fuel" name="poi" value="fuel">
                                <label for="poi-fuel"><i class="fas fa-gas-pump"></i> Posto Combustível</label>
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
