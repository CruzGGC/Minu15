<?php
require_once 'includes/fetch_location_data.php';

// Depurar pedidos de entrada
error_log("------------ NOVO PEDIDO ------------");
error_log("Método de pedido: " . $_SERVER['REQUEST_METHOD']);
error_log("URI do pedido: " . $_SERVER['REQUEST_URI']);
error_log("Software do servidor: " . $_SERVER['SERVER_SOFTWARE']);

// Depurar dados POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    error_log("Dados POST: " . print_r($_POST, true));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $fetcher = new LocationFetcher();
    $response = ['status' => 'error', 'message' => 'Ação ou parâmetros inválidos.'];

    error_log("A processar ação: " . $_POST['action']);

    switch ($_POST['action']) {
        case 'fetchByGps':
            if (isset($_POST['latitude']) && isset($_POST['longitude'])) {
                error_log("A obter por GPS: " . $_POST['latitude'] . ", " . $_POST['longitude']);
                $response = $fetcher->fetchByGps($_POST['latitude'], $_POST['longitude']);
                error_log("Resposta GPS: " . print_r($response, true));
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

    error_log("Resposta final a ser enviada: " . json_encode($response));
    header('Content-Type: application/json');
    echo json_encode($response);
    exit;
}

/**
 * Explorador de Dados de Localização
 * Permite aos utilizadores selecionar um distrito, município ou freguesia em Portugal
 * e visualizar informações demográficas e contagens de infraestruturas
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
    
    <!-- jQuery UI para preenchimento automático -->
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="https://code.jquery.com/ui/1.13.2/jquery-ui.min.js"></script>
    <link rel="stylesheet" href="https://code.jquery.com/ui/1.13.2/themes/base/jquery-ui.css">
    
    <!-- CSS e JS do Leaflet -->
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    
    <!-- Turf.js para análise geoespacial -->
    <script src="https://cdn.jsdelivr.net/npm/@turf/turf@6/turf.min.js"></script>
    
    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- Ficheiros de Configuração -->
    <script src="config/api_config.js"></script>
    <script src="config/map_config.js"></script>
    
    <!-- CSS Personalizado -->
    <link rel="stylesheet" href="css/style.css">
    <link rel="stylesheet" href="css/location.css">
</head>
<body class="location-page">
    <!-- Cabeçalho de Navegação removido conforme solicitado -->
    
    <div id="map"></div>
    
    <!-- Botão para alternar menu móvel -->
    <div class="mobile-menu-toggle" id="mobile-menu-toggle">
        <i class="fas fa-bars"></i>
    </div>
    
    <div class="overlay-panel" id="overlay-panel">
        <!-- Botão de fechar para telemóvel -->
        <div class="mobile-panel-close" id="mobile-panel-close">
            <i class="fas fa-times"></i>
        </div>
        
        <div class="logo-header">
            <a href="index.php">
                <img src="images/Minu15.png" alt="Minu15 Logo" class="app-logo">
            </a>
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
                    <span>Claro</span>
                </div>
                <div class="map-style-option" data-provider="dark_matter">
                    <div class="map-style-icon"><i class="fas fa-moon"></i></div>
                    <span>Escuro</span>
                </div>
            </div>
        </div>
        
        <div class="panel-section">
            <div class="panel-header">
                <span>Opções de Visualização</span>
            </div>
            <div class="display-options">
                <div class="option-item">
                    <label class="toggle-switch-label">
                        <span>Mostrar Freguesias</span>
                        <label class="toggle-switch">
                            <input type="checkbox" id="show-freguesias-toggle">
                            <span class="toggle-slider"></span>
                        </label>
                    </label>
                </div>
            </div>
        </div>
        
        <button class="calculate-button">Carregar Dados</button>
        
        <!-- Adicionado botão "Página Completa" na parte inferior do painel de sobreposição -->
        <div class="panel-section">
            <a href="#" id="view-full-data" class="btn full-width-btn" style="display: block; padding: 10px 15px; background-color: #3498db; color: white; text-decoration: none; border-radius: 4px; font-weight: 500; text-align: center; margin-top: 15px;">
                <i class="fas fa-external-link-alt"></i> Página Completa
            </a>
        </div>
        
        <!-- Atribuição de rodapé adicionada ao painel de sobreposição -->
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

    <!-- Sobreposição modal para popups -->
    <div class="modal-overlay" id="modal-overlay"></div>
    
    <!-- Barra lateral de dados do Censo - implementação completamente nova -->
    <div class="census-sidebar" id="census-sidebar">
        <div class="census-header">
            <div class="census-title">
                <h2 id="census-location-name">Nome da Localidade</h2>
                <p id="census-location-type">Distrito / Concelho / Freguesia</p>
            </div>
            <button class="census-close-btn" id="census-close-btn">
                <i class="fas fa-times"></i>
            </button>
        </div>
        
        <div class="census-body">
            <div class="census-card census-highlight">
                <div class="census-year-toggle">
                    <span>2011</span>
                    <label class="toggle-switch">
                        <input type="checkbox" id="census-year-toggle" checked>
                        <span class="toggle-slider"></span>
                    </label>
                    <span>2021</span>
                </div>
                
                <div class="census-main-stat">
                    <div class="stat-value" id="population-value">0</div>
                    <div class="stat-label">Habitantes</div>
                    <div class="stat-change" id="population-change"><i class="fas fa-arrow-up"></i> 0%</div>
                </div>
            </div>
            
            <div class="census-row">
                <div class="census-card">
                    <div class="card-icon"><i class="fas fa-male"></i><i class="fas fa-female"></i></div>
                    <div class="card-content">
                        <div class="mini-chart" id="gender-chart"></div>
                        <div class="stat-label">Género</div>
                    </div>
                </div>
                
                <div class="census-card">
                    <div class="card-icon"><i class="fas fa-calendar-alt"></i></div>
                    <div class="card-content">
                        <div class="stat-value" id="average-age-value">0</div>
                        <div class="stat-label">Idade Média</div>
                    </div>
                </div>
            </div>
            
            <div class="census-row">
                <div class="census-card">
                    <div class="card-icon"><i class="fas fa-building"></i></div>
                    <div class="card-content">
                        <div class="stat-value" id="buildings-value">0</div>
                        <div class="stat-label">Edifícios</div>
                    </div>
                </div>
                
                <div class="census-card">
                    <div class="card-icon"><i class="fas fa-home"></i></div>
                    <div class="card-content">
                        <div class="stat-value" id="dwellings-value">0</div>
                        <div class="stat-label">Alojamentos</div>
                    </div>
                </div>
                
                <div class="census-card">
                    <div class="card-icon"><i class="fas fa-user-friends"></i></div>
                    <div class="card-content">
                        <div class="stat-value" id="density-value">0</div>
                        <div class="stat-label">Densidade</div>
                    </div>
                </div>
            </div>
            
            <div class="census-card full-width">
                <h3>Distribuição Etária</h3>
                <div class="age-bars" id="age-bars">
                    <!-- Age bars will be added here via JavaScript -->
                </div>
            </div>
            
            <div class="census-footer">
                <a href="#" id="census-view-full-data" class="census-button">
                    <i class="fas fa-external-link-alt"></i> Ver Informação Completa
                </a>
            </div>
        </div>
    </div>
    
    <!-- Custom JS -->
    <script src="js/location.js"></script>
</body>
</html> 