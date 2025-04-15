<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Explorador de Cidade em 15 Minutos</title>
    
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
</head>
<body>
    <div id="map"></div>
    
    <div class="overlay-panel">
        <h2>Explorador de Cidade em 15 Minutos</h2>
        
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
                        <div><input type="checkbox" id="poi-hospitals" checked> <label for="poi-hospitals">Hospitais</label></div>
                        <div><input type="checkbox" id="poi-health_centers" checked> <label for="poi-health_centers">Centros de Saúde</label></div>
                        <div><input type="checkbox" id="poi-pharmacies" checked> <label for="poi-pharmacies">Farmácias</label></div>
                        <div><input type="checkbox" id="poi-dentists" checked> <label for="poi-dentists">Clínicas Dentárias</label></div>
                    </div>
                </div>
                
                <!-- Educação -->
                <div class="poi-category">
                    <div class="category-header">
                        <span>Educação</span>
                        <span class="dropdown-arrow">▼</span>
                    </div>
                    <div class="category-content">
                        <div><input type="checkbox" id="poi-schools" checked> <label for="poi-schools">Escolas Primárias e Secundárias</label></div>
                        <div><input type="checkbox" id="poi-universities" checked> <label for="poi-universities">Universidades e Institutos</label></div>
                        <div><input type="checkbox" id="poi-kindergartens" checked> <label for="poi-kindergartens">Jardins de Infância e Creches</label></div>
                        <div><input type="checkbox" id="poi-libraries" checked> <label for="poi-libraries">Bibliotecas</label></div>
                    </div>
                </div>
                
                <!-- Comércio e serviços -->
                <div class="poi-category">
                    <div class="category-header">
                        <span>Comércio e Serviços</span>
                        <span class="dropdown-arrow">▼</span>
                    </div>
                    <div class="category-content">
                        <div><input type="checkbox" id="poi-supermarkets" checked> <label for="poi-supermarkets">Supermercados</label></div>
                        <div><input type="checkbox" id="poi-malls" checked> <label for="poi-malls">Centros Comerciais</label></div>
                        <div><input type="checkbox" id="poi-restaurants" checked> <label for="poi-restaurants">Restaurantes e Cafés</label></div>
                        <div><input type="checkbox" id="poi-atms" checked> <label for="poi-atms">Caixas de Multibanco</label></div>
                    </div>
                </div>
                
                <!-- Segurança e emergência -->
                <div class="poi-category">
                    <div class="category-header">
                        <span>Segurança e Emergência</span>
                        <span class="dropdown-arrow">▼</span>
                    </div>
                    <div class="category-content">
                        <div><input type="checkbox" id="poi-police" checked> <label for="poi-police">Esquadras da Polícia</label></div>
                        <div><input type="checkbox" id="poi-fire_stations" checked> <label for="poi-fire_stations">Quartéis de Bombeiros</label></div>
                        <div><input type="checkbox" id="poi-civil_protection" checked> <label for="poi-civil_protection">Proteção Civil</label></div>
                    </div>
                </div>
                
                <!-- Administração pública -->
                <div class="poi-category">
                    <div class="category-header">
                        <span>Administração Pública</span>
                        <span class="dropdown-arrow">▼</span>
                    </div>
                    <div class="category-content">
                        <div><input type="checkbox" id="poi-parish_councils" checked> <label for="poi-parish_councils">Juntas de Freguesia</label></div>
                        <div><input type="checkbox" id="poi-city_halls" checked> <label for="poi-city_halls">Câmaras Municipais</label></div>
                    </div>
                </div>
                
                <!-- Cultura e lazer -->
                <div class="poi-category">
                    <div class="category-header">
                        <span>Cultura e Lazer</span>
                        <span class="dropdown-arrow">▼</span>
                    </div>
                    <div class="category-content">
                        <div><input type="checkbox" id="poi-museums" checked> <label for="poi-museums">Museus</label></div>
                        <div><input type="checkbox" id="poi-theaters" checked> <label for="poi-theaters">Teatros</label></div>
                        <div><input type="checkbox" id="poi-sports" checked> <label for="poi-sports">Ginásios e Centros Desportivos</label></div>
                        <div><input type="checkbox" id="poi-parks" checked> <label for="poi-parks">Parques</label></div>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="panel-section">
            <div class="panel-header">
                <span>Modo de Transporte</span>
            </div>
            <div class="transport-mode">
                <div class="transport-option" data-mode="walking">
                    <div class="transport-icon"><i class="fas fa-walking"></i></div>
                    <span>A Pé</span>
                </div>
                <div class="transport-option active" data-mode="cycling">
                    <div class="transport-icon"><i class="fas fa-bicycle"></i></div>
                    <span>Bicicleta</span>
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

    <div class="poi-details-panel">
        <div class="poi-details-title">
            <span>Detalhes do POI</span>
            <span class="close-poi-details"><i class="fas fa-times"></i></span>
        </div>
        <div class="poi-details-content" id="poi-info">
            <p>Clique num ponto de interesse para ver detalhes</p>
        </div>
    </div>

    <div class="poi-legend">
        <div class="poi-legend-title"><strong>Legenda</strong></div>
        <!-- Saúde -->
        <div class="poi-legend-item">
            <div class="poi-legend-color poi-hospital"></div>
            <span>Hospitais</span>
        </div>
        <div class="poi-legend-item">
            <div class="poi-legend-color poi-health"></div>
            <span>Centros de Saúde</span>
        </div>
        <div class="poi-legend-item">
            <div class="poi-legend-color poi-pharmacy"></div>
            <span>Farmácias</span>
        </div>
        <div class="poi-legend-item">
            <div class="poi-legend-color poi-dentist"></div>
            <span>Clínicas Dentárias</span>
        </div>
        
        <!-- Educação -->
        <div class="poi-legend-item">
            <div class="poi-legend-color poi-school"></div>
            <span>Escolas</span>
        </div>
        <div class="poi-legend-item">
            <div class="poi-legend-color poi-university"></div>
            <span>Universidades</span>
        </div>
        <div class="poi-legend-item">
            <div class="poi-legend-color poi-kindergarten"></div>
            <span>Jardins de Infância</span>
        </div>
        <div class="poi-legend-item">
            <div class="poi-legend-color poi-library"></div>
            <span>Bibliotecas</span>
        </div>
        
        <!-- Comércio e Serviços -->
        <div class="poi-legend-item">
            <div class="poi-legend-color poi-supermarket"></div>
            <span>Supermercados</span>
        </div>
        <div class="poi-legend-item">
            <div class="poi-legend-color poi-mall"></div>
            <span>Centros Comerciais</span>
        </div>
        <div class="poi-legend-item">
            <div class="poi-legend-color poi-restaurant"></div>
            <span>Restaurantes</span>
        </div>
        <div class="poi-legend-item">
            <div class="poi-legend-color poi-atm"></div>
            <span>Caixas Multibanco</span>
        </div>
        
        <!-- Segurança e Emergência -->
        <div class="poi-legend-item">
            <div class="poi-legend-color poi-police"></div>
            <span>Esquadras da Polícia</span>
        </div>
        <div class="poi-legend-item">
            <div class="poi-legend-color poi-fire-station"></div>
            <span>Bombeiros</span>
        </div>
        <div class="poi-legend-item">
            <div class="poi-legend-color poi-civil-protection"></div>
            <span>Proteção Civil</span>
        </div>
        
        <!-- Administração Pública -->
        <div class="poi-legend-item">
            <div class="poi-legend-color poi-parish"></div>
            <span>Juntas de Freguesia</span>
        </div>
        <div class="poi-legend-item">
            <div class="poi-legend-color poi-city-hall"></div>
            <span>Câmaras Municipais</span>
        </div>
        
        <!-- Cultura e Lazer -->
        <div class="poi-legend-item">
            <div class="poi-legend-color poi-museum"></div>
            <span>Museus</span>
        </div>
        <div class="poi-legend-item">
            <div class="poi-legend-color poi-theater"></div>
            <span>Teatros</span>
        </div>
        <div class="poi-legend-item">
            <div class="poi-legend-color poi-sport"></div>
            <span>Ginásios</span>
        </div>
        <div class="poi-legend-item">
            <div class="poi-legend-color poi-park"></div>
            <span>Parques</span>
        </div>
    </div>

    <!-- Modal overlay for popups -->
    <div class="modal-overlay" id="modal-overlay"></div>
    
    <!-- Footer attribution -->
    <div class="footer-attribution">
        <p>&copy; <?php echo date('Y'); ?> Explorador de Cidade em 15 Minutos | Dados de <a href="https://www.geofabrik.de/" target="_blank">Geofabrik</a></p>
    </div>
    
    <!-- Custom JS -->
    <script src="js/map.js"></script>
    <script src="js/controls.js"></script>
</body>
</html>