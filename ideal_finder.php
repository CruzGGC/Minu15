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

    <!-- Main Container with Sidebar Layout -->
    <div class="finder-container">
        <!-- Left Sidebar with Controls -->
        <div class="finder-sidebar">
            <div class="sidebar-header">
                <h2><i class="fas fa-bullseye"></i> Configurar Análise</h2>
                <p>Especifique os pontos de interesse que precisa regularmente</p>
            </div>

            <!-- Location Input -->
            <div class="control-section">
                <h3><i class="fas fa-map-marker-alt"></i> Local de Referência</h3>
                <div class="input-group">
                    <input type="text" id="location-input" placeholder="Digite o endereço ou clique no mapa">
                    <button id="my-location-btn" title="Usar minha localização">
                        <i class="fas fa-crosshairs"></i>
                    </button>
                </div>
            </div>

            <!-- POI Requirements -->
            <div class="control-section">
                <h3><i class="fas fa-list-check"></i> Necessidades</h3>
                
                <!-- Health Category -->
                <div class="poi-category">
                    <h4><i class="fas fa-heart-pulse"></i> Saúde</h4>
                    <div class="poi-grid">
                        <div class="poi-item">
                            <label>
                                <input type="checkbox" name="poi" value="hospital">
                                <span class="checkmark"></span>
                                Hospital
                            </label>
                            <select class="importance-select" disabled>
                                <option value="1">Baixa</option>
                                <option value="2">Média</option>
                                <option value="3" selected>Alta</option>
                                <option value="4">Muito Alta</option>
                            </select>
                        </div>
                        <div class="poi-item">
                            <label>
                                <input type="checkbox" name="poi" value="clinic">
                                <span class="checkmark"></span>
                                Clínica
                            </label>
                            <select class="importance-select" disabled>
                                <option value="1">Baixa</option>
                                <option value="2" selected>Média</option>
                                <option value="3">Alta</option>
                                <option value="4">Muito Alta</option>
                            </select>
                        </div>
                        <div class="poi-item">
                            <label>
                                <input type="checkbox" name="poi" value="pharmacy">
                                <span class="checkmark"></span>
                                Farmácia
                            </label>
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
                    <h4><i class="fas fa-graduation-cap"></i> Educação</h4>
                    <div class="poi-grid">
                        <div class="poi-item">
                            <label>
                                <input type="checkbox" name="poi" value="school">
                                <span class="checkmark"></span>
                                Escola
                            </label>
                            <select class="importance-select" disabled>
                                <option value="1">Baixa</option>
                                <option value="2">Média</option>
                                <option value="3" selected>Alta</option>
                                <option value="4">Muito Alta</option>
                            </select>
                        </div>
                        <div class="poi-item">
                            <label>
                                <input type="checkbox" name="poi" value="university">
                                <span class="checkmark"></span>
                                Universidade
                            </label>
                            <select class="importance-select" disabled>
                                <option value="1">Baixa</option>
                                <option value="2" selected>Média</option>
                                <option value="3">Alta</option>
                                <option value="4">Muito Alta</option>
                            </select>
                        </div>
                        <div class="poi-item">
                            <label>
                                <input type="checkbox" name="poi" value="kindergarten">
                                <span class="checkmark"></span>
                                Creche
                            </label>
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
                    <h4><i class="fas fa-shopping-cart"></i> Comércio</h4>
                    <div class="poi-grid">
                        <div class="poi-item">
                            <label>
                                <input type="checkbox" name="poi" value="supermarket">
                                <span class="checkmark"></span>
                                Supermercado
                            </label>
                            <select class="importance-select" disabled>
                                <option value="1">Baixa</option>
                                <option value="2">Média</option>
                                <option value="3" selected>Alta</option>
                                <option value="4">Muito Alta</option>
                            </select>
                        </div>
                        <div class="poi-item">
                            <label>
                                <input type="checkbox" name="poi" value="restaurant">
                                <span class="checkmark"></span>
                                Restaurante
                            </label>
                            <select class="importance-select" disabled>
                                <option value="1" selected>Baixa</option>
                                <option value="2">Média</option>
                                <option value="3">Alta</option>
                                <option value="4">Muito Alta</option>
                            </select>
                        </div>
                        <div class="poi-item">
                            <label>
                                <input type="checkbox" name="poi" value="bank">
                                <span class="checkmark"></span>
                                Banco
                            </label>
                            <select class="importance-select" disabled>
                                <option value="1">Baixa</option>
                                <option value="2" selected>Média</option>
                                <option value="3">Alta</option>
                                <option value="4">Muito Alta</option>
                            </select>
                        </div>
                        <div class="poi-item">
                            <label>
                                <input type="checkbox" name="poi" value="shopping_mall">
                                <span class="checkmark"></span>
                                Shopping
                            </label>
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
                    <h4><i class="fas fa-cogs"></i> Transporte & Serviços</h4>
                    <div class="poi-grid">
                        <div class="poi-item">
                            <label>
                                <input type="checkbox" name="poi" value="bus_stop">
                                <span class="checkmark"></span>
                                Parada de Ônibus
                            </label>
                            <select class="importance-select" disabled>
                                <option value="1">Baixa</option>
                                <option value="2">Média</option>
                                <option value="3" selected>Alta</option>
                                <option value="4">Muito Alta</option>
                            </select>
                        </div>
                        <div class="poi-item">
                            <label>
                                <input type="checkbox" name="poi" value="subway_station">
                                <span class="checkmark"></span>
                                Estação Metro
                            </label>
                            <select class="importance-select" disabled>
                                <option value="1">Baixa</option>
                                <option value="2">Média</option>
                                <option value="3">Alta</option>
                                <option value="4" selected>Muito Alta</option>
                            </select>
                        </div>
                        <div class="poi-item">
                            <label>
                                <input type="checkbox" name="poi" value="post_office">
                                <span class="checkmark"></span>
                                Correios
                            </label>
                            <select class="importance-select" disabled>
                                <option value="1" selected>Baixa</option>
                                <option value="2">Média</option>
                                <option value="3">Alta</option>
                                <option value="4">Muito Alta</option>
                            </select>
                        </div>
                        <div class="poi-item">
                            <label>
                                <input type="checkbox" name="poi" value="fuel">
                                <span class="checkmark"></span>
                                Posto Combustível
                            </label>
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

            <!-- Transport Mode -->
            <div class="control-section">
                <h3><i class="fas fa-route"></i> Modo de Transporte</h3>
                <div class="transport-grid">
                    <button class="transport-btn active" data-mode="foot-walking">
                        <i class="fas fa-walking"></i>
                        <span>A pé</span>
                    </button>
                    <button class="transport-btn" data-mode="cycling-regular">
                        <i class="fas fa-bicycle"></i>
                        <span>Bicicleta</span>
                    </button>
                    <button class="transport-btn" data-mode="driving-car">
                        <i class="fas fa-car"></i>
                        <span>Carro</span>
                    </button>
                </div>
                <div class="time-control">
                    <label for="max-time">Tempo máximo: <span id="time-display">15</span> min</label>
                    <input type="range" id="max-time" min="5" max="30" value="15">
                </div>
            </div>

            <!-- Analysis Settings -->
            <div class="control-section">
                <h3><i class="fas fa-sliders-h"></i> Configurações</h3>
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

            <!-- Analysis Button -->
            <div class="control-section">
                <button id="analyze-btn" class="analyze-button">
                    <i class="fas fa-search"></i>
                    <span>Encontrar Locais Ideais</span>
                </button>
            </div>
        </div>

        <!-- Right Side: Map and Results -->
        <div class="finder-main">
            <!-- Map Container -->
            <div class="map-container">
                <div id="map"></div>
                
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
        </div>
    </div>

    <!-- Custom JavaScript -->
    <script src="js/ideal_finder.js"></script>
</body>
</html>
