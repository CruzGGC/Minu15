<?php
require_once 'includes/fetch_location_data.php';

// Debug incoming requests
error_log("Request method: " . $_SERVER['REQUEST_METHOD']);
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    error_log("POST data: " . print_r($_POST, true));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $fetcher = new LocationFetcher();
    $response = ['status' => 'error', 'message' => 'Invalid action or parameters.'];

    error_log("Processing action: " . $_POST['action']);

    switch ($_POST['action']) {
        case 'fetchByGps':
            if (isset($_POST['latitude']) && isset($_POST['longitude'])) {
                error_log("Fetching by GPS: " . $_POST['latitude'] . ", " . $_POST['longitude']);
                $response = $fetcher->fetchByGps($_POST['latitude'], $_POST['longitude']);
                error_log("GPS response: " . print_r($response, true));
            }
            break;
        case 'fetchByFreguesiaAndMunicipio':
            if (isset($_POST['freguesia']) && isset($_POST['municipio'])) {
                $response = $fetcher->fetchByFreguesiaAndMunicipio($_POST['freguesia'], $_POST['municipio']);
            }
            break;
        case 'fetchByMunicipio':
            if (isset($_POST['municipio'])) {
                $response = $fetcher->fetchByMunicipio($_POST['municipio']);
            }
            break;
        case 'fetchByDistrito':
            if (isset($_POST['distrito'])) {
                $response = $fetcher->fetchByDistrito($_POST['distrito']);
            }
            break;
        case 'fetchAllDistritos':
            $response = $fetcher->fetchAllDistritos();
            break;
        case 'fetchMunicipiosByDistrito':
            if (isset($_POST['distrito'])) {
                $response = $fetcher->fetchMunicipiosByDistrito($_POST['distrito']);
            }
            break;
        case 'fetchFreguesiasByMunicipio':
            if (isset($_POST['municipio'])) {
                $response = $fetcher->fetchFreguesiasByMunicipio($_POST['municipio']);
            }
            break;
    }

    error_log("Final response to be sent: " . json_encode($response));
    header('Content-Type: application/json');
    echo json_encode($response);
    exit;
}

/**
 * Location Data Explorer
 * Allows users to select a district, municipality, or freguesia in Portugal
 * and view demographic information and infrastructure counts
 * 
 * @version 1.0
 */
?>
<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Minu15 - Explorador de Localização</title>
    
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
    <link rel="stylesheet" href="css/location.css">
</head>
<body class="location-page">
    <!-- Navigation Header -->
    <nav class="top-nav">
        <div class="nav-container">
            <div class="nav-brand">
                <img src="images/Minu15.png" alt="Minu15" class="nav-logo">
                <span class="nav-title">Explorador de Localização</span>
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
                <a href="location.php" class="nav-link active">
                    <i class="fas fa-search-location"></i>
                    <span>Localização</span>
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
            <div class="panel-header">
                <span>Selecionar Localização</span>
            </div>
            
            <div class="location-selector">
                <div class="selector-group">
                    <label for="distrito-select">Distrito:</label>
                    <select id="distrito-select">
                        <option value="">Selecione um distrito...</option>
                    </select>
                </div>
                
                <div class="selector-group">
                    <label for="concelho-select">Concelho:</label>
                    <select id="concelho-select" disabled>
                        <option value="">Selecione um concelho...</option>
                    </select>
                </div>
                
                <div class="selector-group">
                    <label for="freguesia-select">Freguesia:</label>
                    <select id="freguesia-select" disabled>
                        <option value="">Selecione uma freguesia...</option>
                    </select>
                </div>
                
                <div class="selector-info">
                    <p>Ou clique diretamente no mapa para selecionar um local</p>
                </div>
            </div>
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
                        <div><input type="checkbox" id="poi-schools" checked> <label for="poi-schools"><i class="fas fa-school poi-school"></i> Escolas</label></div>
                        <div><input type="checkbox" id="poi-universities" checked> <label for="poi-universities"><i class="fas fa-graduation-cap poi-university"></i> Universidades</label></div>
                        <div><input type="checkbox" id="poi-kindergartens" checked> <label for="poi-kindergartens"><i class="fas fa-baby poi-kindergarten"></i> Jardins de Infância</label></div>
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
                        <div><input type="checkbox" id="poi-restaurants" checked> <label for="poi-restaurants"><i class="fas fa-utensils poi-restaurant"></i> Restaurantes</label></div>
                        <div><input type="checkbox" id="poi-atms" checked> <label for="poi-atms"><i class="fas fa-money-bill-wave poi-atm"></i> Multibanco</label></div>
                    </div>
                </div>
                
                <!-- Outros POIs -->
                <div class="poi-category">
                    <div class="category-header">
                        <span>Outros</span>
                        <span class="dropdown-arrow">▼</span>
                    </div>
                    <div class="category-content">
                        <div><input type="checkbox" id="poi-parks" checked> <label for="poi-parks"><i class="fas fa-tree poi-park"></i> Parques</label></div>
                        <div><input type="checkbox" id="poi-sports" checked> <label for="poi-sports"><i class="fas fa-dumbbell poi-sport"></i> Desporto</label></div>
                        <div><input type="checkbox" id="poi-bus_stops" checked> <label for="poi-bus_stops"><i class="fas fa-bus"></i> Paragens</label></div>
                        <div><input type="checkbox" id="poi-police_stations" checked> <label for="poi-police_stations"><i class="fas fa-shield-alt poi-police"></i> Polícia</label></div>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="panel-section">
            <div class="panel-header">
                <span>Estilo do Mapa</span>
            </div>
            <div class="map-style-selector">
                <div class="map-style-option" data-provider="osm">
                    <div class="map-style-icon"><i class="fas fa-map"></i></div>
                    <span>OSM</span>
                </div>
                <div class="map-style-option active" data-provider="positron">
                    <div class="map-style-icon"><i class="fas fa-sun"></i></div>
                    <span>Light</span>
                </div>
                <div class="map-style-option" data-provider="dark_matter">
                    <div class="map-style-icon"><i class="fas fa-moon"></i></div>
                    <span>Dark</span>
                </div>
            </div>
        </div>
        
        <button class="calculate-button">Carregar Dados</button>
        
        <!-- Added footer attribution to the overlay panel -->
        <div class="panel-section footer-in-overlay">
            <p>&copy; <?php echo date('Y'); ?> Minu15 | Dados de <a href="https://geoapi.pt" target="_blank">GeoAPI.pt</a> e <a href="https://www.geofabrik.de/" target="_blank">Geofabrik</a></p>
        </div>
    </div>

    <div class="location-data-panel">
        <div class="panel-header">
            <span>Dados da Localização</span>
            <span class="close-panel"><i class="fas fa-times"></i></span>
        </div>
        <div class="panel-content" id="location-data">
            <p>Selecione uma localização para ver os dados</p>
        </div>
    </div>

    <!-- Modal overlay for popups -->
    <div class="modal-overlay" id="modal-overlay"></div>
    
    <!-- Custom JS -->
    <script src="js/location.js"></script>
</body>
</html> 