<?php
require_once 'includes/fetch_location_data.php';

// Verificar se há um parâmetro de tipo e ID na URL
$locationType = isset($_GET['type']) ? $_GET['type'] : null;
$locationId = isset($_GET['id']) ? $_GET['id'] : null;

// Preparar o container para os dados
$locationData = null;
$error = null;

// Buscar dados se os parâmetros foram fornecidos
if ($locationType && $locationId) {
    $fetcher = new LocationFetcher();
    
    try {
        switch ($locationType) {
            case 'distrito':
                $result = $fetcher->fetchByDistrito($locationId);
                break;
            case 'municipio':
                $result = $fetcher->fetchByMunicipio($locationId);
                break;
            case 'freguesia':
                // Freguesia precisa de um município também
                $municipio = isset($_GET['municipio']) ? $_GET['municipio'] : null;
                if ($municipio) {
                    $result = $fetcher->fetchByFreguesiaAndMunicipio($locationId, $municipio);
                } else {
                    $error = "Para visualizar dados de uma freguesia, é necessário especificar o município.";
                }
                break;
            case 'gps':
                // Coordenadas GPS - o ID é lat,lng
                $coords = explode(',', $locationId);
                if (count($coords) == 2) {
                    $result = $fetcher->fetchByGps($coords[0], $coords[1]);
                } else {
                    $error = "Formato de coordenadas inválido. Use: latitude,longitude";
                }
                break;
            default:
                $error = "Tipo de localização não suportado.";
        }
        
        // Se temos um resultado, verificar se foi bem-sucedido
        if (isset($result) && $result['success']) {
            $locationData = $result['data'];
        } else if (isset($result)) {
            $error = "Erro ao buscar dados: " . ($result['message'] ?? 'Erro desconhecido');
        }
    } catch (Exception $e) {
        $error = "Erro ao processar a solicitação: " . $e->getMessage();
    }
}

// Função para formatar os dados demográficos para exibição
function formatDemographicData($data) {
    $html = '';
    
    // Adicionar população
    if (isset($data['N_INDIVIDUOS_RESIDENT']) || isset($data['N_INDIVIDUOS'])) {
        $population = $data['N_INDIVIDUOS_RESIDENT'] ?? $data['N_INDIVIDUOS'];
        $html .= "<p><strong>População:</strong> " . number_format($population, 0, ',', '.') . " habitantes</p>";
    }
    
    // Adicionar edifícios
    if (isset($data['N_EDIFICIOS_CLASSICOS']) || isset($data['N_EDIFICIOS'])) {
        $buildings = $data['N_EDIFICIOS_CLASSICOS'] ?? $data['N_EDIFICIOS'];
        $html .= "<p><strong>Edifícios:</strong> " . number_format($buildings, 0, ',', '.') . "</p>";
    }
    
    // Adicionar alojamentos
    if (isset($data['N_ALOJAMENTOS_TOTAL']) || isset($data['N_ALOJAMENTOS'])) {
        $dwellings = $data['N_ALOJAMENTOS_TOTAL'] ?? $data['N_ALOJAMENTOS'];
        $html .= "<p><strong>Alojamentos:</strong> " . number_format($dwellings, 0, ',', '.') . "</p>";
    }
    
    // Faixas etárias
    if (isset($data['N_INDIVIDUOS_0_14']) || isset($data['N_INDIVIDUOS_RESIDENT_0A4'])) {
        $youngPopulation = $data['N_INDIVIDUOS_0_14'] ?? (
            ($data['N_INDIVIDUOS_RESIDENT_0A4'] ?? 0) + 
            ($data['N_INDIVIDUOS_RESIDENT_5A9'] ?? 0) + 
            ($data['N_INDIVIDUOS_RESIDENT_10A13'] ?? 0)
        );
        $html .= "<p><strong>População jovem (0-14):</strong> " . number_format($youngPopulation, 0, ',', '.') . "</p>";
    }
    
    // População em idade ativa
    if (isset($data['N_IND_RESID_EMPREGADOS']) || isset($data['N_INDIVIDUOS_25_64'])) {
        $workingPopulation = $data['N_IND_RESID_EMPREGADOS'] ?? $data['N_INDIVIDUOS_25_64'] ?? $data['N_INDIVIDUOS_RESIDENT_25A64'] ?? 0;
        $html .= "<p><strong>População em idade ativa:</strong> " . number_format($workingPopulation, 0, ',', '.') . "</p>";
    }
    
    // População idosa
    if (isset($data['N_INDIVIDUOS_65_OU_MAIS']) || isset($data['N_INDIVIDUOS_RESIDENT_65'])) {
        $elderlyPopulation = $data['N_INDIVIDUOS_65_OU_MAIS'] ?? $data['N_INDIVIDUOS_RESIDENT_65'];
        $html .= "<p><strong>População idosa (65+):</strong> " . number_format($elderlyPopulation, 0, ',', '.') . "</p>";
    }
    
    return $html;
}
?>
<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dados da Localização - Minu15</title>
    
    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- Custom CSS -->
    <link rel="stylesheet" href="css/style.css">
    <link rel="stylesheet" href="css/location.css">
    
    <style>
        body {
            font-family: 'Poppins', sans-serif;
            margin: 0;
            padding: 0;
            background-color: #f5f7fa;
        }
        .container {
            max-width: 800px;
            margin: 0 auto;
            padding: 20px;
        }
        .location-card {
            background-color: #fff;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            padding: 25px;
            margin-bottom: 20px;
        }
        .location-title {
            color: #2980b9;
            margin-top: 0;
            margin-bottom: 20px;
            border-bottom: 1px solid #eee;
            padding-bottom: 10px;
        }
        .location-section {
            margin-bottom: 25px;
        }
        .section-title {
            color: #3498db;
            font-size: 18px;
            margin-bottom: 15px;
        }
        .error-message {
            background-color: #f8d7da;
            color: #721c24;
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 20px;
        }
        .navigation {
            margin-bottom: 20px;
        }
        .navigation a {
            color: #3498db;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 5px;
        }
        .navigation a:hover {
            text-decoration: underline;
        }
        .info-row {
            display: flex;
            flex-wrap: wrap;
            gap: 15px;
            margin-bottom: 15px;
        }
        .info-item {
            flex: 1;
            min-width: 200px;
            background-color: #f8f9fa;
            padding: 15px;
            border-radius: 8px;
            border-left: 4px solid #3498db;
        }
        .info-value {
            font-size: 1.2em;
            font-weight: 600;
            color: #2c3e50;
            margin-top: 5px;
        }
        .demographic-highlights {
            background-color: #f0f7ff;
            padding: 15px;
            border-radius: 8px;
            margin-top: 15px;
        }
        .location-hierarchy {
            background-color: #f5f9fc;
            padding: 12px 15px;
            border-radius: 8px;
            margin-bottom: 15px;
            border-left: 4px solid #3498db;
        }
        .instructions {
            background-color: #e8f4f8;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
        }
        @media (max-width: 600px) {
            .info-item {
                min-width: 100%;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="navigation">
            <a href="location.php"><i class="fas fa-arrow-left"></i> Voltar ao Explorador</a>
        </div>
        
        <?php if (!$locationType || !$locationId): ?>
            <div class="location-card">
                <h1 class="location-title">Visualizador de Dados de Localização</h1>
                
                <div class="instructions">
                    <h3><i class="fas fa-info-circle"></i> Como usar</h3>
                    <p>Este visualizador mostra informações detalhadas sobre localidades em Portugal. Para ver os dados, use um dos seguintes formatos de URL:</p>
                    <ul>
                        <li><strong>Distrito:</strong> <code>location_data.php?type=distrito&id=Lisboa</code></li>
                        <li><strong>Município:</strong> <code>location_data.php?type=municipio&id=Lisboa</code></li>
                        <li><strong>Freguesia:</strong> <code>location_data.php?type=freguesia&id=Alvalade&municipio=Lisboa</code></li>
                        <li><strong>Coordenadas GPS:</strong> <code>location_data.php?type=gps&id=38.736946,-9.142685</code></li>
                    </ul>
                    <p>Ou use o <a href="location.php">Explorador de Localização</a> para selecionar uma localidade no mapa.</p>
                </div>
            </div>
        <?php elseif ($error): ?>
            <div class="error-message">
                <i class="fas fa-exclamation-triangle"></i> <?php echo $error; ?>
            </div>
        <?php elseif ($locationData): ?>
            <div class="location-card">
                <h1 class="location-title">
                    <?php if (isset($locationData['nome'])): ?>
                        <?php echo $locationData['nome']; ?>
                    <?php elseif (isset($locationData['distrito'])): ?>
                        Distrito de <?php echo $locationData['distrito']; ?>
                    <?php elseif (isset($locationData['municipio']) || isset($locationData['concelho'])): ?>
                        Município de <?php echo $locationData['municipio'] ?? $locationData['concelho']; ?>
                    <?php elseif (isset($locationData['freguesia'])): ?>
                        Freguesia de <?php echo $locationData['freguesia']; ?>
                    <?php else: ?>
                        Localização
                    <?php endif; ?>
                </h1>
                
                <!-- Hierarquia administrativa -->
                <?php if (isset($locationData['freguesia']) || isset($locationData['municipio']) || isset($locationData['concelho']) || isset($locationData['distrito'])): ?>
                    <div class="location-hierarchy">
                        <?php if (isset($locationData['freguesia'])): ?>
                            <p><strong>Freguesia:</strong> <?php echo $locationData['freguesia']; ?></p>
                        <?php endif; ?>
                        
                        <?php if (isset($locationData['municipio']) || isset($locationData['concelho'])): ?>
                            <p><strong>Concelho:</strong> <?php echo $locationData['municipio'] ?? $locationData['concelho']; ?></p>
                        <?php endif; ?>
                        
                        <?php if (isset($locationData['distrito'])): ?>
                            <p><strong>Distrito:</strong> <?php echo $locationData['distrito']; ?></p>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
                
                <!-- Dados demográficos -->
                <?php if (isset($locationData['censos2021']) || isset($locationData['censos2011'])): ?>
                    <div class="location-section">
                        <h2 class="section-title">Dados Demográficos</h2>
                        
                        <?php 
                        // Usar dados mais recentes (2021) se disponíveis, caso contrário usar 2011
                        $censusData = isset($locationData['censos2021']) ? $locationData['censos2021'] : $locationData['censos2011'];
                        $censusYear = isset($locationData['censos2021']) ? 2021 : 2011;
                        
                        // População, edifícios e alojamentos
                        $population = isset($censusData['N_INDIVIDUOS_RESIDENT']) ? $censusData['N_INDIVIDUOS_RESIDENT'] : 
                                      (isset($censusData['N_INDIVIDUOS']) ? $censusData['N_INDIVIDUOS'] : null);
                        
                        $buildings = isset($censusData['N_EDIFICIOS_CLASSICOS']) ? $censusData['N_EDIFICIOS_CLASSICOS'] : 
                                     (isset($censusData['N_EDIFICIOS']) ? $censusData['N_EDIFICIOS'] : null);
                        
                        $dwellings = isset($censusData['N_ALOJAMENTOS_TOTAL']) ? $censusData['N_ALOJAMENTOS_TOTAL'] : 
                                     (isset($censusData['N_ALOJAMENTOS']) ? $censusData['N_ALOJAMENTOS'] : null);
                        
                        // Área em hectares e km²
                        $areaHa = isset($locationData['area_ha']) ? $locationData['area_ha'] : 
                                  (isset($locationData['areaha']) ? $locationData['areaha'] : null);
                        
                        $areaKm2 = $areaHa ? ($areaHa / 100) : null;
                        
                        // Densidade populacional
                        $density = ($population && $areaKm2) ? round($population / $areaKm2) : null;
                        ?>
                        
                        <div class="info-row">
                            <?php if ($population): ?>
                                <div class="info-item">
                                    <div><strong>População</strong></div>
                                    <div class="info-value"><?php echo number_format($population, 0, ',', '.'); ?></div>
                                    <div><small>Censos <?php echo $censusYear; ?></small></div>
                                </div>
                            <?php endif; ?>
                            
                            <?php if ($buildings): ?>
                                <div class="info-item">
                                    <div><strong>Edifícios</strong></div>
                                    <div class="info-value"><?php echo number_format($buildings, 0, ',', '.'); ?></div>
                                    <div><small>Censos <?php echo $censusYear; ?></small></div>
                                </div>
                            <?php endif; ?>
                            
                            <?php if ($dwellings): ?>
                                <div class="info-item">
                                    <div><strong>Alojamentos</strong></div>
                                    <div class="info-value"><?php echo number_format($dwellings, 0, ',', '.'); ?></div>
                                    <div><small>Censos <?php echo $censusYear; ?></small></div>
                                </div>
                            <?php endif; ?>
                        </div>
                        
                        <div class="info-row">
                            <?php if ($areaKm2): ?>
                                <div class="info-item">
                                    <div><strong>Área</strong></div>
                                    <div class="info-value"><?php echo number_format($areaKm2, 1, ',', '.'); ?> km²</div>
                                </div>
                            <?php endif; ?>
                            
                            <?php if ($density): ?>
                                <div class="info-item">
                                    <div><strong>Densidade Populacional</strong></div>
                                    <div class="info-value"><?php echo number_format($density, 0, ',', '.'); ?> hab/km²</div>
                                </div>
                            <?php endif; ?>
                        </div>
                        
                        <!-- Destaques demográficos -->
                        <div class="demographic-highlights">
                            <h3>Destaques</h3>
                            <?php 
                            // Obter dados de faixas etárias
                            $youngPopulation = isset($censusData['N_INDIVIDUOS_0_14']) ? $censusData['N_INDIVIDUOS_0_14'] : 
                                              (isset($censusData['N_INDIVIDUOS_RESIDENT_0A4']) ? 
                                               $censusData['N_INDIVIDUOS_RESIDENT_0A4'] + 
                                               $censusData['N_INDIVIDUOS_RESIDENT_5A9'] + 
                                               $censusData['N_INDIVIDUOS_RESIDENT_10A13'] : null);
                            
                            $workingPopulation = isset($censusData['N_IND_RESID_EMPREGADOS']) ? $censusData['N_IND_RESID_EMPREGADOS'] : 
                                                (isset($censusData['N_INDIVIDUOS_25_64']) ? $censusData['N_INDIVIDUOS_25_64'] : 
                                                 (isset($censusData['N_INDIVIDUOS_RESIDENT_25A64']) ? $censusData['N_INDIVIDUOS_RESIDENT_25A64'] : null));
                            
                            $elderlyPopulation = isset($censusData['N_INDIVIDUOS_65_OU_MAIS']) ? $censusData['N_INDIVIDUOS_65_OU_MAIS'] : 
                                                (isset($censusData['N_INDIVIDUOS_RESIDENT_65']) ? $censusData['N_INDIVIDUOS_RESIDENT_65'] : null);
                            ?>
                            
                            <?php if ($youngPopulation): ?>
                                <p><strong>População jovem (0-14):</strong> <?php echo number_format($youngPopulation, 0, ',', '.'); ?> 
                                <?php if ($population): ?>
                                    (<?php echo round(($youngPopulation / $population) * 100, 1); ?>%)
                                <?php endif; ?>
                                </p>
                            <?php endif; ?>
                            
                            <?php if ($workingPopulation): ?>
                                <p><strong>População em idade ativa:</strong> <?php echo number_format($workingPopulation, 0, ',', '.'); ?>
                                <?php if ($population): ?>
                                    (<?php echo round(($workingPopulation / $population) * 100, 1); ?>%)
                                <?php endif; ?>
                                </p>
                            <?php endif; ?>
                            
                            <?php if ($elderlyPopulation): ?>
                                <p><strong>População idosa (65+):</strong> <?php echo number_format($elderlyPopulation, 0, ',', '.'); ?>
                                <?php if ($population): ?>
                                    (<?php echo round(($elderlyPopulation / $population) * 100, 1); ?>%)
                                <?php endif; ?>
                                </p>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endif; ?>
                
                <!-- Dados administrativos para municípios -->
                <?php if (isset($locationData['nif']) || isset($locationData['codigo']) || isset($locationData['email'])): ?>
                    <div class="location-section">
                        <h2 class="section-title">Dados Administrativos</h2>
                        
                        <?php if (isset($locationData['codigo'])): ?>
                            <p><strong>Código:</strong> <?php echo $locationData['codigo']; ?></p>
                        <?php endif; ?>
                        
                        <?php if (isset($locationData['nif'])): ?>
                            <p><strong>NIF:</strong> <?php echo $locationData['nif']; ?></p>
                        <?php endif; ?>
                        
                        <?php if (isset($locationData['rua']) || isset($locationData['localidade']) || isset($locationData['codigopostal'])): ?>
                            <p>
                                <strong>Morada:</strong> 
                                <?php echo isset($locationData['rua']) ? $locationData['rua'] . ', ' : ''; ?>
                                <?php echo isset($locationData['localidade']) ? $locationData['localidade'] . ' ' : ''; ?>
                                <?php echo isset($locationData['codigopostal']) ? $locationData['codigopostal'] . ' ' : ''; ?>
                                <?php echo isset($locationData['descrpostal']) ? $locationData['descrpostal'] : ''; ?>
                            </p>
                        <?php endif; ?>
                        
                        <?php if (isset($locationData['telefone'])): ?>
                            <p><strong>Telefone:</strong> <?php echo $locationData['telefone']; ?></p>
                        <?php endif; ?>
                        
                        <?php if (isset($locationData['fax'])): ?>
                            <p><strong>Fax:</strong> <?php echo $locationData['fax']; ?></p>
                        <?php endif; ?>
                        
                        <?php if (isset($locationData['email'])): ?>
                            <p><strong>Email:</strong> <?php echo $locationData['email']; ?></p>
                        <?php endif; ?>
                        
                        <?php if (isset($locationData['sitio'])): ?>
                            <p><strong>Website:</strong> <a href="<?php echo strpos($locationData['sitio'], 'http') === 0 ? $locationData['sitio'] : 'http://' . $locationData['sitio']; ?>" target="_blank"><?php echo $locationData['sitio']; ?></a></p>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
                
                <!-- Link para o explorador de localização -->
                <div class="location-section">
                    <p>
                        <a href="location.php" class="btn">
                            <i class="fas fa-map-marker-alt"></i> Ver no Explorador de Localização
                        </a>
                    </p>
                </div>
            </div>
        <?php else: ?>
            <div class="error-message">
                <i class="fas fa-exclamation-triangle"></i> Não foi possível encontrar dados para a localização especificada.
            </div>
        <?php endif; ?>
    </div>
</body>
</html> 