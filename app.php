<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Minu15 - Cidade em 15 Minutos</title>
    
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
    <link rel="stylesheet" href="css/landing.css">
</head>
<body>
    <div id="map"></div>
    
    <!-- Botão para alternar o menu móvel -->
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
            <div class="panel-header" id="poi-header">
                <span>Pontos de Interesse</span>
                <span class="dropdown-arrow">▼</span>
            </div>
            <div class="panel-content" id="poi-content">
                <!-- Categoria Saúde -->
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
                
                <!-- Categoria Educação -->
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
                
                <!-- Categoria Comércio e serviços -->
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
                
                <!-- Categoria Segurança e serviços públicos -->
                <div class="poi-category">
                    <div class="category-header">
                        <span>Segurança e Serviços Públicos</span>
                        <span class="dropdown-arrow">▼</span>
                    </div>
                    <div class="category-content">
                        <div><input type="checkbox" id="poi-police" checked> <label for="poi-police"><i class="fas fa-shield-alt poi-police"></i> Polícia</label></div>
                        <div><input type="checkbox" id="poi-police_stations" checked> <label for="poi-police_stations"><i class="fas fa-shield-alt poi-police"></i> Esquadras de Polícia</label></div>
                        <div><input type="checkbox" id="poi-fire_stations" checked> <label for="poi-fire_stations"><i class="fas fa-fire-extinguisher poi-fire-station"></i> Quartéis de Bombeiros</label></div>
                        <div><input type="checkbox" id="poi-civil_protection" checked> <label for="poi-civil_protection"><i class="fas fa-building-columns poi-civil-protection"></i> Serviços Governamentais Públicos</label></div>
                    </div>
                </div>
                
                <!-- Categoria Administração pública -->
                <div class="poi-category">
                    <div class="category-header">
                        <span>Administração Pública</span>
                        <span class="dropdown-arrow">▼</span>
                    </div>
                    <div class="category-content">
                        <div><input type="checkbox" id="poi-city_halls" checked> <label for="poi-city_halls"><i class="fas fa-landmark poi-city-hall"></i> Câmaras Municipais</label></div>
                        <div><input type="checkbox" id="poi-post_offices" checked> <label for="poi-post_offices"><i class="fas fa-envelope poi-post-office"></i> Correios</label></div>
                    </div>
                </div>
                
                <!-- Categoria Cultura e lazer -->
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
            <div class="panel-header js-custom-handled" id="settings-header">
                <span><i class="fas fa-cog"></i> Configurações</span>
                <span class="dropdown-arrow">▼</span>
            </div>
            <div class="panel-content" id="settings-content">
                <!-- Definições de Dados de Localização -->
                <div class="settings-group">
                    <h4>Dados de Localização</h4>
                    <div class="setting-row">
                        <label>Nível de Detalhe:</label>
                        <select id="location-detail-level">
                            <option value="freguesia">Freguesia</option>
                            <option value="municipio" selected>Município</option>
                            <option value="distrito">Distrito</option>
                        </select>
                    </div>
                </div>
                
                <!-- Definições de Pontuação de Acessibilidade -->
                <div class="settings-group">
                    <h4>Pontuação de Acessibilidade</h4>
                    
                    <!-- Categoria Saúde (maior peso - essencial) -->
                    <div class="weight-category">
                        <div class="weight-header">
                            <span>Saúde</span>
                            <span class="dropdown-arrow">▼</span>
                        </div>
                        <div class="weight-content expanded">
                            <div class="weight-item">
                                <label for="weight-hospitals">Hospitais:</label>
                                <input type="number" id="weight-hospitals" min="1" max="10" value="10" class="weight-input">
                            </div>
                            <div class="weight-item">
                                <label for="weight-health_centers">Centros de Saúde:</label>
                                <input type="number" id="weight-health_centers" min="1" max="10" value="8" class="weight-input">
                            </div>
                            <div class="weight-item">
                                <label for="weight-pharmacies">Farmácias:</label>
                                <input type="number" id="weight-pharmacies" min="1" max="10" value="7" class="weight-input">
                            </div>
                            <div class="weight-item">
                                <label for="weight-dentists">Clínicas Dentárias:</label>
                                <input type="number" id="weight-dentists" min="1" max="10" value="5" class="weight-input">
                            </div>
                        </div>
                    </div>
                    
                    <!-- Categoria Educação -->
                    <div class="weight-category">
                        <div class="weight-header">
                            <span>Educação</span>
                            <span class="dropdown-arrow">▼</span>
                        </div>
                        <div class="weight-content expanded">
                            <div class="weight-item">
                                <label for="weight-schools">Escolas:</label>
                                <input type="number" id="weight-schools" min="1" max="10" value="9" class="weight-input">
                            </div>
                            <div class="weight-item">
                                <label for="weight-universities">Universidades:</label>
                                <input type="number" id="weight-universities" min="1" max="10" value="6" class="weight-input">
                            </div>
                            <div class="weight-item">
                                <label for="weight-kindergartens">Jardins de Infância:</label>
                                <input type="number" id="weight-kindergartens" min="1" max="10" value="7" class="weight-input">
                            </div>
                            <div class="weight-item">
                                <label for="weight-libraries">Bibliotecas:</label>
                                <input type="number" id="weight-libraries" min="1" max="10" value="5" class="weight-input">
                            </div>
                        </div>
                    </div>
                    
                    <!-- Categoria Comércio e Serviços -->
                    <div class="weight-category">
                        <div class="weight-header">
                            <span>Comércio e Serviços</span>
                            <span class="dropdown-arrow">▼</span>
                        </div>
                        <div class="weight-content expanded">
                            <div class="weight-item">
                                <label for="weight-supermarkets">Supermercados:</label>
                                <input type="number" id="weight-supermarkets" min="1" max="10" value="10" class="weight-input">
                            </div>
                            <div class="weight-item">
                                <label for="weight-malls">Centros Comerciais:</label>
                                <input type="number" id="weight-malls" min="1" max="10" value="6" class="weight-input">
                            </div>
                            <div class="weight-item">
                                <label for="weight-restaurants">Restaurantes:</label>
                                <input type="number" id="weight-restaurants" min="1" max="10" value="7" class="weight-input">
                            </div>
                            <div class="weight-item">
                                <label for="weight-atms">Multibancos:</label>
                                <input type="number" id="weight-atms" min="1" max="10" value="6" class="weight-input">
                            </div>
                        </div>
                    </div>
                    
                    <!-- Categoria Segurança e Serviços Públicos -->
                    <div class="weight-category">
                        <div class="weight-header">
                            <span>Segurança e Serviços Públicos</span>
                            <span class="dropdown-arrow">▼</span>
                        </div>
                        <div class="weight-content expanded">
                            <div class="weight-item">
                                <label for="weight-police_stations">Esquadras de Polícia:</label>
                                <input type="number" id="weight-police_stations" min="1" max="10" value="8" class="weight-input">
                            </div>
                            <div class="weight-item">
                                <label for="weight-fire_stations">Bombeiros:</label>
                                <input type="number" id="weight-fire_stations" min="1" max="10" value="7" class="weight-input">
                            </div>
                            <div class="weight-item">
                                <label for="weight-civil_protection">Serviços Públicos:</label>
                                <input type="number" id="weight-civil_protection" min="1" max="10" value="5" class="weight-input">
                            </div>
                        </div>
                    </div>
                    
                    <!-- Categoria Cultura e Lazer -->
                    <div class="weight-category">
                        <div class="weight-header">
                            <span>Cultura e Lazer</span>
                            <span class="dropdown-arrow">▼</span>
                        </div>
                        <div class="weight-content expanded">
                            <div class="weight-item">
                                <label for="weight-parks">Parques:</label>
                                <input type="number" id="weight-parks" min="1" max="10" value="8" class="weight-input">
                            </div>
                            <div class="weight-item">
                                <label for="weight-sports">Ginásios:</label>
                                <input type="number" id="weight-sports" min="1" max="10" value="6" class="weight-input">
                            </div>
                            <div class="weight-item">
                                <label for="weight-museums">Museus:</label>
                                <input type="number" id="weight-museums" min="1" max="10" value="3" class="weight-input">
                            </div>
                            <div class="weight-item">
                                <label for="weight-theaters">Teatros:</label>
                                <input type="number" id="weight-theaters" min="1" max="10" value="3" class="weight-input">
                            </div>
                        </div>
                    </div>
                    
                    <button id="reset-weights" class="reset-btn">
                        <i class="fas fa-undo"></i> Restaurar valores padrão
                    </button>
                </div>
                
                <!-- Definições de Estilo do Mapa -->
                <div class="settings-group">
                    <h4>Estilo do Mapa</h4>
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
        
        <!-- Atribuição do rodapé adicionada ao painel de sobreposição -->
        <div class="panel-section footer-in-overlay">
            <p>&copy; <?php echo date('Y'); ?> Minu15 | Dados de <a href="https://www.geofabrik.de/" target="_blank">Geofabrik</a></p>
        </div>
    </div>

    <!-- Painel de Estatísticas -->
    <div class="statistics-panel" id="statistics-panel">
        <div class="statistics-title">
            <span>Estatísticas da Área</span>
            <span class="close-stats"><i class="fas fa-times"></i></span>
        </div>
        <div class="stats-content" id="stats-content">
            <p>Clique no mapa para ver estatísticas</p>
        </div>
    </div>

    <!-- Sobreposição de Carregamento -->
    <div class="loading-overlay" id="loading-overlay" style="display: none;">
        <div class="loading-spinner-container">
            <div class="loading-spinner"></div>
            <p>Gerando isócrona...</p>
        </div>
    </div>
    
    <!-- Sobreposição de Carregamento de POIs -->
    <div class="poi-loading-overlay" id="poi-loading-overlay" style="display: none;">
        <div class="poi-loading-spinner"></div>
        <span>A carregar pontos de interesse...</span>
    </div>
    
    <!-- Sobreposição modal para popups -->
    <div class="modal-overlay" id="modal-overlay"></div>
    
    <!-- JS Personalizado -->
    <script src="js/map.js"></script>
    <script src="js/controls.js"></script>
    
    <!-- Correção direta baseada em jQuery para o menu de configurações -->
    <script>
        $(document).ready(function() {
            console.log('Correção jQuery para configurações aplicada');

            // Pesos padrão de map.js - usados quando os valores do localStorage não existem
            const defaultWeights = {
                // Saúde (maior peso - essencial)
                hospitals: 10,
                health_centers: 8,
                pharmacies: 7,
                dentists: 5,
                
                // Educação
                schools: 9,
                universities: 6,
                kindergartens: 7,
                libraries: 5,
                
                // Comércio e Serviços
                supermarkets: 10,
                malls: 6,
                restaurants: 7,
                atms: 6,
                
                // Segurança e Serviços Públicos
                police: 8,
                police_stations: 8,
                fire_stations: 7,
                civil_protection: 5,
                
                // Cultura e Lazer
                museums: 3,
                theaters: 3,
                sports: 6,
                parks: 8
            };
            
            // Inicializa os inputs de peso a partir do localStorage ou usa os valores padrão
            $('.weight-input').each(function() {
                const inputId = $(this).attr('id');
                const weightKey = inputId.replace('weight-', '');
                const savedWeight = localStorage.getItem('weight-' + weightKey);
                
                if (savedWeight !== null) {
                    $(this).val(savedWeight);
                } else if (defaultWeights[weightKey]) {
                    $(this).val(defaultWeights[weightKey]);
                }
            });

            // Inicializa o painel de configurações recolhido e todas as categorias de peso expandidas
            $('#settings-content').hide(); // Esconde inicialmente para que o slideToggle funcione corretamente
            $('#settings-header').find('.dropdown-arrow').removeClass('up');
            $('.weight-content').slideDown().addClass('expanded'); // Garante que todas as categorias de peso estão expandidas e visíveis
            $('.weight-header').find('.dropdown-arrow').addClass('up');
            
            // Alternar o painel de configurações
            $('#settings-header').on('click', function() {
                console.log('Cabeçalho das configurações clicado (jQuery)');
                const $settingsContent = $('#settings-content');
                const $arrow = $(this).find('.dropdown-arrow');

                if ($settingsContent.is(':hidden')) {
                    // A expandir
                    $settingsContent.addClass('expanded');
                    $arrow.addClass('up');
                    $settingsContent.slideDown(300);
                } else {
                    // A recolher
                    $settingsContent.slideUp(300, function() {
                        $settingsContent.removeClass('expanded');
                        $arrow.removeClass('up');
                    });
                }
            });
            
            // Alternar as categorias de peso
            $('.weight-header').on('click', function(e) {
                e.stopPropagation(); // Previne a propagação do evento para o pai
                console.log('Cabeçalho da categoria de peso clicado (jQuery)');
                $(this).next('.weight-content').toggleClass('expanded');
                $(this).find('.dropdown-arrow').toggleClass('up');
            });
            
            // Guarda os pesos no localStorage quando alterados
            $('.weight-input').on('change', function() {
                const inputId = $(this).attr('id');
                const weightKey = inputId.replace('weight-', '');
                const value = parseInt($(this).val());
                
                // Valida que o valor está entre 1-10
                if (value < 1) {
                    $(this).val(1);
                    localStorage.setItem('weight-' + weightKey, 1);
                } else if (value > 10) {
                    $(this).val(10);
                    localStorage.setItem('weight-' + weightKey, 10);
                } else {
                    localStorage.setItem('weight-' + weightKey, value);
                }
            });
            
            // Botão para restaurar pesos
            $('#reset-weights').on('click', function() {
                // Aplica os valores padrão
                $.each(defaultWeights, function(key, value) {
                    $('#weight-' + key).val(value);
                    
                    // Também limpa o localStorage para este peso para garantir que usa o padrão no próximo carregamento
                    localStorage.removeItem('weight-' + key);
                });
                
                alert('Pontuações restauradas para valores padrão.');
            });
        });
    </script>
    
    <!-- Correção para o problema da barra lateral desaparecer -->
    <script>
        $(document).ready(function() {
            // Garante que a barra lateral esteja sempre visível no ambiente de trabalho
            function fixSidebar() {
                if (window.innerWidth > 768) {
                    const panel = document.getElementById('overlay-panel');
                    if (panel) {
                        panel.style.display = 'block';
                        panel.style.transform = 'none';
                        panel.style.visibility = 'visible';
                        panel.style.opacity = '1';
                        panel.style.left = '20px';
                    }
                }
            }
            
            // Aplica a correção no carregamento da página
            fixSidebar();
            
            // Ouve eventos de tutorial
            document.addEventListener('tutorialClosed', fixSidebar);
            
            // Aplica a correção quando o mapa é clicado
            $('#map').on('click', function() {
                setTimeout(fixSidebar, 100); // Pequeno atraso para permitir que outros manipuladores de eventos sejam executados
            });
            
            // Observa quaisquer alterações na visibilidade da barra lateral
            const observer = new MutationObserver(function(mutations) {
                mutations.forEach(function(mutation) {
                    if (mutation.type === 'attributes' && 
                        (mutation.attributeName === 'style' || mutation.attributeName === 'class')) {
                        fixSidebar();
                    }
                });
            });
            
            // Começa a observar a barra lateral por alterações
            const panel = document.getElementById('overlay-panel');
            if (panel) {
                observer.observe(panel, { attributes: true });
            }
            
            // Corrige quaisquer problemas quando a janela é redimensionada
            $(window).on('resize', fixSidebar);
            
            // Interceta todos os eventos de clique na página para garantir que a barra lateral permanece visível
            $(document).on('click', function(e) {
                if (window.innerWidth > 768) {
                    setTimeout(fixSidebar, 10);
                    setTimeout(fixSidebar, 100);
                    setTimeout(fixSidebar, 300);
                }
            });
            
            // Adiciona um manipulador de eventos ao botão #got-it-btn quando é criado
            $(document).on('click', '#got-it-btn', function(e) {
                if (window.innerWidth > 768) {
                    // Aplica múltiplas correções com atraso para apanhar quaisquer problemas de temporização
                    for (let i = 1; i <= 10; i++) {
                        setTimeout(fixSidebar, i * 100);
                    }
                }
            });
            
            // Substitui o comportamento de clique padrão do mapa que pode estar a esconder o painel
            try {
                // Espera que o mapa esteja totalmente inicializado
                setTimeout(function() {
                    if (typeof map !== 'undefined' && map.getEvents && map.getEvents().click) {
                        const originalMapClick = map.getEvents().click;
                        if (originalMapClick && originalMapClick.length > 0) {
                            map.off('click');
                            map.on('click', function(e) {
                                // Chama o manipulador original
                                originalMapClick[0].fn(e);
                                
                                // Corrige a barra lateral após um atraso
                                if (window.innerWidth > 768) {
                                    setTimeout(fixSidebar, 50);
                                    setTimeout(fixSidebar, 200);
                                }
                            });
                        }
                    }
                }, 1000); // Espera 1 segundo para o mapa inicializar
            } catch (e) {
                console.log('Erro ao substituir o clique do mapa:', e);
            }
            
            // Define um intervalo para verificar e corrigir periodicamente a barra lateral
            setInterval(fixSidebar, 500);
            
            // Adiciona regra CSS direta para forçar a visibilidade da barra lateral no ambiente de trabalho
            const styleElement = document.createElement('style');
            styleElement.textContent = `
                @media (min-width: 769px) {
                    #overlay-panel {
                        display: block !important;
                        transform: none !important;
                        visibility: visible !important;
                        opacity: 1 !important;
                        left: 20px !important;
                        z-index: 999 !important;
                        position: absolute !important;
                    }
                }
            `;
            document.head.appendChild(styleElement);
        });
    </script>
</body>
</html>