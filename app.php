<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Minu15 - Cidade em 15 Minutos</title>
    
    <!-- jQuery UI for autocomplete -->
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="https://code.jquery.com/ui/1.13.2/jquery-ui.min.js"></script>
    <link rel="stylesheet" href="https://code.jquery.com/ui/1.13.2/themes/base/jquery-ui.css">
    
    <!-- Leaflet CSS and JS -->
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    
    <!-- Turf.js for geospatial analysis -->
    <script src="https://cdn.jsdelivr.net/npm/@turf/turf@6/turf.min.js"></script>
    
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
<body>
    <!-- Navigation Header -->
    <nav class="top-nav">
        <div class="nav-container">
            <div class="nav-brand">
                <img src="images/Minu15.png" alt="Minu15" class="nav-logo">
                <span class="nav-title">Explorador da Cidade</span>
            </div>
            <div class="nav-links">
                <a href="index.php" class="nav-link">
                    <i class="fas fa-home"></i>
                    <span>Início</span>
                </a>
                <a href="app.php" class="nav-link active">
                    <i class="fas fa-map"></i>
                    <span>Explorador</span>
                </a>
                <a href="ideal_finder.php" class="nav-link">
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
        </div>
        
        <div class="panel-section">
            <div class="panel-header" id="poi-header">
                <span>Pontos de Interesse</span>
                <span class="dropdown-arrow">▼</span>
            </div>
            <div class="panel-content" id="poi-content">
                <!-- Saúde -->
                <div class="poi-category">
                    <div class="category-header">
                        <span>Saúde</span>
                        <span class="dropdown-arrow">▼</span>
                    </div>
                    <div class="category-content">
                        <div><input type="checkbox" id="poi-hospitals" checked> <label for="poi-hospitals"><i class="fas fa-hospital poi-hospital"></i> Hospitais</label></div>
                        <div><input type="checkbox" id="poi-health_centers" checked> <label for="poi-health_centers"><i class="fas fa-first-aid-kit poi-health"></i> Centros de Saúde</label></div>
                        <div><input type="checkbox" id="poi-pharmacies" checked> <label for="poi-pharmacies"><i class="fas fa-prescription-bottle-alt poi-pharmacy"></i> Farmácias</label></div>
                        <div><input type="checkbox" id="poi-dentists" checked> <label for="poi-dentists"><i class="fas fa-tooth poi-dentist"></i> Clínicas Dentárias</label></div>
                    </div>
                </div>
                
                <!-- Educação -->
                <div class="poi-category">
                    <div class="category-header">
                        <span>Educação</span>
                        <span class="dropdown-arrow">▼</span>
                    </div>
                    <div class="category-content">
                        <div><input type="checkbox" id="poi-schools" checked> <label for="poi-schools"><i class="fas fa-school poi-school"></i> Escolas Primárias e Secundárias</label></div>
                        <div><input type="checkbox" id="poi-universities" checked> <label for="poi-universities"><i class="fas fa-graduation-cap poi-university"></i> Universidades e Institutos</label></div>
                        <div><input type="checkbox" id="poi-kindergartens" checked> <label for="poi-kindergartens"><i class="fas fa-baby poi-kindergarten"></i> Jardins de Infância e Creches</label></div>
                        <div><input type="checkbox" id="poi-libraries" checked> <label for="poi-libraries"><i class="fas fa-book poi-library"></i> Bibliotecas</label></div>
                    </div>
                </div>
                
                <!-- Comércio e serviços -->
                <div class="poi-category">
                    <div class="category-header">
                        <span>Comércio e Serviços</span>
                        <span class="dropdown-arrow">▼</span>
                    </div>
                    <div class="category-content">
                        <div><input type="checkbox" id="poi-supermarkets" checked> <label for="poi-supermarkets"><i class="fas fa-shopping-basket poi-supermarket"></i> Supermercados</label></div>
                        <div><input type="checkbox" id="poi-malls" checked> <label for="poi-malls"><i class="fas fa-shopping-bag poi-mall"></i> Centros Comerciais</label></div>
                        <div><input type="checkbox" id="poi-restaurants" checked> <label for="poi-restaurants"><i class="fas fa-utensils poi-restaurant"></i> Restaurantes e Cafés</label></div>
                        <div><input type="checkbox" id="poi-atms" checked> <label for="poi-atms"><i class="fas fa-money-bill-wave poi-atm"></i> Caixas de Multibanco</label></div>
                    </div>
                </div>
                
                <!-- Segurança e emergência -->
                <div class="poi-category">
                    <div class="category-header">
                        <span>Segurança e Emergência</span>
                        <span class="dropdown-arrow">▼</span>
                    </div>
                    <div class="category-content">
                        <div><input type="checkbox" id="poi-police" checked> <label for="poi-police"><i class="fas fa-shield-alt poi-police"></i> Esquadras da Polícia</label></div>
                        <div><input type="checkbox" id="poi-fire_stations" checked> <label for="poi-fire_stations"><i class="fas fa-fire-extinguisher poi-fire-station"></i> Quartéis de Bombeiros</label></div>
                        <div><input type="checkbox" id="poi-civil_protection" checked> <label for="poi-civil_protection"><i class="fas fa-hard-hat poi-civil-protection"></i> Proteção Civil</label></div>
                    </div>
                </div>
                
                <!-- Administração pública -->
                <div class="poi-category">
                    <div class="category-header">
                        <span>Administração Pública</span>
                        <span class="dropdown-arrow">▼</span>
                    </div>
                    <div class="category-content">
                        <div><input type="checkbox" id="poi-parish_councils" checked> <label for="poi-parish_councils"><i class="fas fa-city poi-parish"></i> Juntas de Freguesia</label></div>
                        <div><input type="checkbox" id="poi-city_halls" checked> <label for="poi-city_halls"><i class="fas fa-landmark poi-city-hall"></i> Câmaras Municipais</label></div>
                    </div>
                </div>
                
                <!-- Cultura e lazer -->
                <div class="poi-category">
                    <div class="category-header">
                        <span>Cultura e Lazer</span>
                        <span class="dropdown-arrow">▼</span>
                    </div>
                    <div class="category-content">
                        <div><input type="checkbox" id="poi-museums" checked> <label for="poi-museums"><i class="fas fa-museum poi-museum"></i> Museus</label></div>
                        <div><input type="checkbox" id="poi-theaters" checked> <label for="poi-theaters"><i class="fas fa-theater-masks poi-theater"></i> Teatros</label></div>
                        <div><input type="checkbox" id="poi-sports" checked> <label for="poi-sports"><i class="fas fa-dumbbell poi-sport"></i> Ginásios e Centros Desportivos</label></div>
                        <div><input type="checkbox" id="poi-parks" checked> <label for="poi-parks"><i class="fas fa-tree poi-park"></i> Parques</label></div>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="panel-section">
            <div class="panel-header">
                <span>Modo de Transporte</span>
            </div>
            <div class="transport-mode">
                <div class="transport-option" data-mode="cycling">
                    <div class="transport-icon"><i class="fas fa-bicycle"></i></div>
                    <span>Bicicleta</span>
                </div>
                <div class="transport-option active" data-mode="walking">
                    <div class="transport-icon"><i class="fas fa-walking"></i></div>
                    <span>A Pé</span>
                </div>
                <div class="transport-option" data-mode="driving">
                    <div class="transport-icon"><i class="fas fa-car"></i></div>
                    <span>Carro</span>
                </div>
            </div>
        </div>
        
        <div class="panel-section">
            <div class="panel-header">
                <span>Distância (minutos)</span>
            </div>
            <input type="range" class="distance-slider" id="max-distance" min="5" max="30" step="5" value="15">
            <div id="distance-value">15 minutos</div>
        </div>
        
        <div class="panel-section">
            <div class="search-container">
                <input type="text" class="search-box" placeholder="Pesquisar local...">
                <button class="search-button"><i class="fas fa-search"></i></button>
            </div>
        </div>
        
        <button class="calculate-button">Calcular</button>
        
        <div class="panel-section">
            <div class="panel-header" id="map-style-header">
                <span>Estilo do Mapa</span>
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
        
        <!-- Added footer attribution to the overlay panel -->
        <div class="panel-section footer-in-overlay">
            <p>&copy; <?php echo date('Y'); ?> Minu15 | Dados de <a href="https://www.geofabrik.de/" target="_blank">Geofabrik</a></p>
        </div>
    </div>

    <div class="statistics-panel">
        <div class="statistics-title">
            <span>Estatísticas da Área</span>
            <span class="close-stats"><i class="fas fa-times"></i></span>
        </div>
        <div class="stats-content" id="area-stats">
            <p>Clique no mapa para ver estatísticas</p>
        </div>
    </div>

    <!-- Modal overlay for popups -->
    <div class="modal-overlay" id="modal-overlay"></div>
    
    <!-- Custom JS -->
    <script src="js/map.js"></script>
    <script src="js/controls.js"></script>
</body>
</html>