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
<html lang="pt" class="scrollable">
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
    
    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    
    <!-- Custom CSS -->
    <link rel="stylesheet" href="css/style.css">
    <link rel="stylesheet" href="css/location.css">
    
    <style>
        :root {
            --primary-color: #3498db;
            --secondary-color: #2c3e50;
            --accent-color: #2ecc71;
            --background-color: #f5f8fa;
            --card-background: #ffffff;
            --text-color: #34495e;
            --light-text: #7f8c8d;
            --border-radius: 10px;
            --box-shadow: 0 10px 30px rgba(0, 0, 0, 0.05);
            --transition-speed: 0.3s;
        }
        
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }
        
        html.scrollable, 
        body.scrollable {
            overflow-y: auto !important;
            -webkit-overflow-scrolling: touch;
            height: auto !important;
            min-height: 100%;
            width: 100%;
            position: relative;
            scroll-behavior: smooth;
        }
        
        html, body {
            font-family: 'Poppins', sans-serif;
            background-color: var(--background-color);
            color: var(--text-color);
            line-height: 1.6;
            overflow-x: hidden;
            overflow-y: auto;
            width: 100%;
            height: 100%;
            padding: 0;
            margin: 0;
            position: relative;
        }
        
        body {
            overflow-y: scroll; /* Ensure vertical scrolling is always available */
        }
        
        .container {
            width: 100%;
            max-width: 1400px;
            margin: 0 auto;
            padding: 20px;
            overflow-x: hidden;
            position: relative;
            min-height: 100vh;
        }
        
        .location-card {
            background-color: var(--card-background);
            border-radius: var(--border-radius);
            box-shadow: var(--box-shadow);
            padding: 35px;
            margin-bottom: 30px;
            animation: fadeInUp 0.6s ease-out forwards;
            opacity: 0;
            transform: translateY(20px);
        }
        
        .location-title {
            color: var(--primary-color);
            font-size: 2.2rem;
            margin-top: 0;
            margin-bottom: 25px;
            border-bottom: 1px solid #eee;
            padding-bottom: 15px;
            position: relative;
            animation: fadeIn 0.8s ease-out forwards;
            opacity: 0;
        }
        
        .location-title:after {
            content: '';
            position: absolute;
            bottom: -1px;
            left: 0;
            width: 100px;
            height: 3px;
            background-color: var(--primary-color);
            animation: slideRight 1s ease-out forwards;
            transform: scaleX(0);
            transform-origin: left;
        }
        
        .location-section {
            margin-bottom: 35px;
            animation: fadeIn 0.8s ease-out forwards;
            animation-delay: 0.2s;
            opacity: 0;
        }
        
        .section-title {
            color: var(--primary-color);
            font-size: 1.6rem;
            margin-bottom: 20px;
            position: relative;
            display: inline-block;
            padding-bottom: 8px;
            animation: fadeIn 0.8s ease-out forwards;
            animation-delay: 0.3s;
            opacity: 0;
        }
        
        .section-title:after {
            content: '';
            position: absolute;
            bottom: 0;
            left: 0;
            width: 100%;
            height: 2px;
            background-color: var(--primary-color);
            animation: slideRight 0.8s ease-out forwards;
            animation-delay: 0.5s;
            transform: scaleX(0);
            transform-origin: left;
        }
        
        .subsection-title {
            color: var(--secondary-color);
            font-size: 1.3rem;
            margin-top: 30px;
            margin-bottom: 15px;
            border-bottom: 1px solid #eee;
            padding-bottom: 10px;
            animation: fadeIn 0.8s ease-out forwards;
            animation-delay: 0.4s;
            opacity: 0;
        }
        
        .error-message {
            background-color: #f8d7da;
            color: #721c24;
            padding: 20px;
            border-radius: var(--border-radius);
            margin-bottom: 25px;
            animation: shake 0.5s ease-out;
        }
        
        .navigation {
            margin-bottom: 25px;
            animation: fadeIn 0.6s ease-out forwards;
        }
        
        .navigation a {
            color: var(--primary-color);
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            font-weight: 500;
            padding: 10px 15px;
            border-radius: 30px;
            background-color: rgba(52, 152, 219, 0.1);
            transition: all 0.3s ease;
        }
        
        .navigation a:hover {
            background-color: var(--primary-color);
            color: white;
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(52, 152, 219, 0.3);
        }
        
        .info-row {
            display: flex;
            flex-wrap: wrap;
            gap: 20px;
            margin-bottom: 25px;
            animation: fadeIn 0.8s ease-out forwards;
            animation-delay: 0.5s;
            opacity: 0;
        }
        
        .info-item {
            flex: 1;
            min-width: 250px;
            background-color: #f8f9fa;
            padding: 20px;
            border-radius: var(--border-radius);
            border-left: 5px solid var(--primary-color);
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.03);
            transition: all 0.3s ease;
        }
        
        .info-item:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.08);
        }
        
        .info-value {
            font-size: 1.5em;
            font-weight: 600;
            color: var(--secondary-color);
            margin-top: 8px;
        }
        
        .demographic-highlights {
            background-color: #f0f7ff;
            padding: 25px;
            border-radius: var(--border-radius);
            margin-top: 20px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.03);
            animation: fadeIn 0.8s ease-out forwards;
            animation-delay: 0.6s;
            opacity: 0;
        }
        
        .location-hierarchy {
            background-color: #f5f9fc;
            padding: 20px;
            border-radius: var(--border-radius);
            margin-bottom: 25px;
            border-left: 5px solid var(--primary-color);
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.03);
            animation: fadeIn 0.8s ease-out forwards;
            animation-delay: 0.2s;
            opacity: 0;
        }
        
        .instructions {
            background-color: #e8f4f8;
            padding: 25px;
            border-radius: var(--border-radius);
            margin-bottom: 30px;
            animation: fadeIn 0.8s ease-out forwards;
            opacity: 0;
            animation: fadeInUp 1s ease forwards;
            animation-delay: calc(var(--item-index, 0) * 0.1s + 1.2s);
        }
        
        /* Age distribution and building age styles */
        .age-distribution, .building-age {
            margin-top: 20px;
        }
        
        .age-group {
            margin-bottom: 18px;
            animation: fadeIn 0.8s ease-out forwards;
            animation-delay: calc(0.7s + (var(--item-index, 0) * 0.1s));
            opacity: 0;
        }
        
        .age-label {
            font-weight: 500;
            color: var(--text-color);
            margin-bottom: 6px;
            display: flex;
            justify-content: space-between;
        }
        
        .age-bar {
            height: 15px;
            background-color: #e0e6ed;
            border-radius: 8px;
            overflow: hidden;
            margin-bottom: 6px;
            box-shadow: inset 0 1px 3px rgba(0, 0, 0, 0.1);
        }
        
        .age-bar-fill {
            height: 100%;
            border-radius: 8px;
            background-color: var(--primary-color);
            width: 0;
            animation: growWidth 1.5s ease-out forwards;
            animation-delay: calc(0.8s + (var(--item-index, 0) * 0.1s));
            position: relative;
            overflow: hidden;
        }
        
        .age-bar-fill::after {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: linear-gradient(90deg, 
                rgba(255, 255, 255, 0) 0%, 
                rgba(255, 255, 255, 0.2) 50%, 
                rgba(255, 255, 255, 0) 100%);
            animation: shimmer 2s infinite;
        }
        
        .building-old {
            background-color: #e74c3c;
        }
        
        .building-medium {
            background-color: #f39c12;
        }
        
        .building-new {
            background-color: #2ecc71;
        }
        
        .age-value {
            font-size: 0.95em;
            color: var(--light-text);
            margin-top: 4px;
            text-align: right;
            display: inline-block;
        }
        
        /* Additional formatting */
        h4 {
            color: var(--secondary-color);
            font-size: 1.1rem;
            margin: 25px 0 15px 0;
            animation: fadeIn 0.8s ease-out forwards;
            animation-delay: 0.5s;
            opacity: 0;
        }
        
        /* Animations */
        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }
        
        @keyframes fadeInUp {
            from { 
                opacity: 0;
                transform: translateY(20px);
            }
            to { 
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        @keyframes slideRight {
            from { transform: scaleX(0); }
            to { transform: scaleX(1); }
        }
        
        @keyframes growWidth {
            from { width: 0; }
            to { width: var(--final-width, 100%); }
        }
        
        @keyframes shimmer {
            from { transform: translateX(-100%); }
            to { transform: translateX(100%); }
        }
        
        @keyframes shake {
            0%, 100% { transform: translateX(0); }
            10%, 30%, 50%, 70%, 90% { transform: translateX(-5px); }
            20%, 40%, 60%, 80% { transform: translateX(5px); }
        }
        
        /* Fix for animation issues that might affect scrolling */
        .location-card, 
        .location-title, 
        .location-section, 
        .section-title, 
        .subsection-title, 
        .info-row, 
        .demographic-highlights, 
        .location-hierarchy, 
        .instructions, 
        .age-group, 
        h4 {
            will-change: opacity, transform;
            backface-visibility: hidden;
        }
        
        /* Ensure absolute positioned elements don't break scrolling */
        .location-title:after,
        .section-title:after,
        .age-bar-fill::after {
            pointer-events: none;
        }
        
        /* Responsive design */
        @media (max-width: 1200px) {
            .container {
                max-width: 100%;
                padding: 15px;
            }
        }
        
        @media (max-width: 768px) {
            .location-card {
                padding: 25px;
            }
            
            .location-title {
                font-size: 1.8rem;
            }
            
            .section-title {
                font-size: 1.4rem;
            }
            
            .info-item {
                min-width: 100%;
            }
        }
        
        @media (max-width: 480px) {
            .location-card {
                padding: 20px;
                margin-bottom: 20px;
            }
            
            .location-title {
                font-size: 1.5rem;
            }
            
            .section-title {
                font-size: 1.2rem;
            }
            
            .subsection-title {
                font-size: 1.1rem;
            }
        }
        
        /* Charts section */
        .charts-container {
            display: flex;
            flex-wrap: wrap;
            gap: 20px;
            justify-content: space-between;
            margin-top: 30px;
            margin-bottom: 30px;
        }
        
        .chart-card {
            background-color: var(--card-background);
            border-radius: var(--border-radius);
            box-shadow: var(--box-shadow);
            padding: 20px;
            flex: 1 1 300px;
            max-width: 400px;
            min-height: 350px;
            opacity: 0;
            animation: fadeInUp 1s ease forwards;
            animation-delay: calc(var(--item-index, 0) * 0.2s + 0.8s);
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }
        
        .chart-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 15px 35px rgba(0, 0, 0, 0.1);
        }
        
        .chart-title {
            font-size: 18px;
            font-weight: 600;
            margin-bottom: 15px;
            color: var(--secondary-color);
            text-align: center;
            position: relative;
        }
        
        .chart-title:after {
            content: '';
            display: block;
            width: 50px;
            height: 3px;
            background: var(--primary-color);
            margin: 8px auto;
            border-radius: 2px;
        }
        
        .chart-wrapper {
            height: 250px;
            display: flex;
            justify-content: center;
            align-items: center;
            position: relative;
        }
        
        .chart-wrapper canvas {
            max-width: 100%;
            transition: all 0.3s ease;
        }
        
        .chart-card:hover .chart-wrapper canvas {
            transform: scale(1.05);
        }
        
        .chart-legend {
            margin-top: 15px;
            display: flex;
            flex-wrap: wrap;
            justify-content: center;
            gap: 10px;
        }
        
        .legend-item {
            display: flex;
            align-items: center;
            margin-right: 10px;
            font-size: 14px;
        }
        
        .legend-color {
            width: 12px;
            height: 12px;
            border-radius: 50%;
            margin-right: 5px;
        }
        
        /* Chart tooltip custom styles */
        .chartjs-tooltip {
            background-color: rgba(44, 62, 80, 0.85) !important;
            border-radius: 8px !important;
            padding: 10px 14px !important;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.2) !important;
            font-family: 'Poppins', sans-serif !important;
            font-size: 14px !important;
            color: white !important;
            pointer-events: none !important;
            z-index: 999 !important;
        }
        
        /* Census Comparison Styles */
        .census-comparison-toggle {
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 20px 0;
            padding: 10px;
            background-color: #f8f9fa;
            border-radius: var(--border-radius);
            box-shadow: var(--box-shadow);
            animation: fadeIn 0.8s ease-out forwards;
            animation-delay: 0.3s;
            opacity: 0;
        }
        
        .toggle-label {
            font-weight: 500;
            margin-right: 10px;
            color: var(--secondary-color);
        }
        
        .toggle-switch {
            position: relative;
            display: inline-block;
            width: 60px;
            height: 30px;
            margin: 0 10px;
        }
        
        .toggle-switch input {
            opacity: 0;
            width: 0;
            height: 0;
        }
        
        .toggle-slider {
            position: absolute;
            cursor: pointer;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background-color: #ccc;
            transition: .4s;
            border-radius: 30px;
        }
        
        .toggle-slider:before {
            position: absolute;
            content: "";
            height: 22px;
            width: 22px;
            left: 4px;
            bottom: 4px;
            background-color: white;
            transition: .4s;
            border-radius: 50%;
        }
        
        input:checked + .toggle-slider {
            background-color: var(--primary-color);
        }
        
        input:focus + .toggle-slider {
            box-shadow: 0 0 1px var(--primary-color);
        }
        
        input:checked + .toggle-slider:before {
            transform: translateX(30px);
        }
        
        .toggle-status {
            font-weight: 500;
            color: var(--primary-color);
        }
        
        .comparison-chart-container {
            display: flex;
            flex-direction: column;
            margin-top: 20px;
        }
        
        .comparison-row {
            display: flex;
            flex-wrap: wrap;
            gap: 20px;
            margin-bottom: 20px;
        }
        
        .comparison-card {
            flex: 1 1 300px;
            background-color: var(--card-background);
            border-radius: var(--border-radius);
            box-shadow: var(--box-shadow);
            padding: 20px;
            animation: fadeInUp 0.8s ease-out forwards;
            animation-delay: calc(var(--item-index, 0) * 0.1s + 0.5s);
            opacity: 0;
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }
        
        .comparison-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 15px 35px rgba(0, 0, 0, 0.1);
        }
        
        .comparison-title {
            font-size: 16px;
            font-weight: 600;
            margin-bottom: 15px;
            color: var(--secondary-color);
            text-align: center;
        }
        
        .comparison-value {
            font-size: 28px;
            font-weight: 700;
            text-align: center;
            margin-bottom: 10px;
        }
        
        .comparison-change {
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 14px;
            margin-top: 5px;
        }
        
        .change-positive {
            color: #2ecc71;
        }
        
        .change-negative {
            color: #e74c3c;
        }
        
        .change-neutral {
            color: #95a5a6;
        }
        
        .change-icon {
            margin-right: 5px;
        }
    </style>
</head>
<body class="scrollable">
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
                        // Verificar se existem dados de ambos os censos
                        $hasCenso2021 = isset($locationData['censos2021']);
                        $hasCenso2011 = isset($locationData['censos2011']);
                        $hasBothCensus = $hasCenso2021 && $hasCenso2011;
                        
                        // Dados principais (2021 se disponível, caso contrário 2011)
                        $censusData = $hasCenso2021 ? $locationData['censos2021'] : $locationData['censos2011'];
                        $censusYear = $hasCenso2021 ? 2021 : 2011;
                        
                        // Dados para comparação (quando ambos disponíveis)
                        $censusData2021 = $hasCenso2021 ? $locationData['censos2021'] : null;
                        $censusData2011 = $hasCenso2011 ? $locationData['censos2011'] : null;
                        
                        // Mostrar toggle de comparação apenas se ambos os censos estiverem disponíveis
                        if ($hasBothCensus): ?>
                        <div class="census-comparison-toggle">
                            <span class="toggle-label">Censo 2011</span>
                            <label class="toggle-switch">
                                <input type="checkbox" id="censusToggle" checked>
                                <span class="toggle-slider"></span>
                            </label>
                            <span class="toggle-status" id="toggleStatus">Censo 2021</span>
                        </div>
                        <?php endif;
                        
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

                        // Dados por género
                        $males = isset($censusData['N_INDIVIDUOS_H']) ? $censusData['N_INDIVIDUOS_H'] : null;
                        $females = isset($censusData['N_INDIVIDUOS_M']) ? $censusData['N_INDIVIDUOS_M'] : null;
                        
                        // Famílias e agregados
                        $households = isset($censusData['N_AGREGADOS_DOMESTICOS_PRIVADOS']) ? $censusData['N_AGREGADOS_DOMESTICOS_PRIVADOS'] : null;
                        $smallHouseholds = isset($censusData['N_ADP_1_OU_2_PESSOAS']) ? $censusData['N_ADP_1_OU_2_PESSOAS'] : null;
                        $largeHouseholds = isset($censusData['N_ADP_3_OU_MAIS_PESSOAS']) ? $censusData['N_ADP_3_OU_MAIS_PESSOAS'] : null;
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

                            <?php if ($households): ?>
                                <div class="info-item">
                                    <div><strong>Agregados Familiares</strong></div>
                                    <div class="info-value"><?php echo number_format($households, 0, ',', '.'); ?></div>
                                </div>
                            <?php endif; ?>
                        </div>
                        
                        <!-- Distribuição por género -->
                        <?php if ($males && $females): ?>
                            <h3 class="subsection-title">Distribuição por Género</h3>
                            <div class="info-row">
                                <div class="info-item">
                                    <div><strong>Homens</strong></div>
                                    <div class="info-value">
                                        <?php echo number_format($males, 0, ',', '.'); ?> 
                                        (<?php echo round(($males / $population) * 100, 1); ?>%)
                                    </div>
                                </div>
                                <div class="info-item">
                                    <div><strong>Mulheres</strong></div>
                                    <div class="info-value">
                                        <?php echo number_format($females, 0, ',', '.'); ?>
                                        (<?php echo round(($females / $population) * 100, 1); ?>%)
                                    </div>
                                </div>
                            </div>
                        <?php endif; ?>
                        
                        <!-- Destaques demográficos - faixas etárias -->
                        <h3 class="subsection-title">Distribuição por Idade</h3>
                        <div class="demographic-highlights">
                            <?php 
                            // Obter dados de faixas etárias
                            $youngPopulation = isset($censusData['N_INDIVIDUOS_0_14']) ? $censusData['N_INDIVIDUOS_0_14'] : 
                                              (isset($censusData['N_INDIVIDUOS_RESIDENT_0A4']) ? 
                                               $censusData['N_INDIVIDUOS_RESIDENT_0A4'] + 
                                               $censusData['N_INDIVIDUOS_RESIDENT_5A9'] + 
                                               $censusData['N_INDIVIDUOS_RESIDENT_10A13'] : null);
                            
                            $youngAdults = isset($censusData['N_INDIVIDUOS_15_24']) ? $censusData['N_INDIVIDUOS_15_24'] : null;
                            
                            $workingPopulation = isset($censusData['N_INDIVIDUOS_25_64']) ? $censusData['N_INDIVIDUOS_25_64'] : 
                                                (isset($censusData['N_INDIVIDUOS_RESIDENT_25A64']) ? $censusData['N_INDIVIDUOS_RESIDENT_25A64'] : null);
                            
                            $elderlyPopulation = isset($censusData['N_INDIVIDUOS_65_OU_MAIS']) ? $censusData['N_INDIVIDUOS_65_OU_MAIS'] : 
                                                (isset($censusData['N_INDIVIDUOS_RESIDENT_65']) ? $censusData['N_INDIVIDUOS_RESIDENT_65'] : null);
                            ?>
                            
                            <!-- Add pie charts for demographics -->
                            <div class="charts-container">
                                <?php if ($males && $females): ?>
                                <div class="chart-card" style="--item-index: 0;">
                                    <h4 class="chart-title">Distribuição por Género</h4>
                                    <div class="chart-wrapper">
                                        <canvas id="genderChart"></canvas>
                                    </div>
                                </div>
                                <?php endif; ?>
                                
                                <?php if ($youngPopulation && $youngAdults && $workingPopulation && $elderlyPopulation): ?>
                                <div class="chart-card" style="--item-index: 1;">
                                    <h4 class="chart-title">Distribuição por Idade</h4>
                                    <div class="chart-wrapper">
                                        <canvas id="ageChart"></canvas>
                                    </div>
                                </div>
                                <?php endif; ?>
                            </div>
                            
                            <div class="age-distribution">
                            <?php if ($youngPopulation): ?>
                                    <div class="age-group">
                                        <div class="age-label">0-14 anos</div>
                                        <div class="age-bar">
                                            <div class="age-bar-fill" style="width: <?php echo ($youngPopulation / $population) * 100; ?>%;"></div>
                                        </div>
                                        <div class="age-value">
                                            <?php echo number_format($youngPopulation, 0, ',', '.'); ?> 
                                    (<?php echo round(($youngPopulation / $population) * 100, 1); ?>%)
                                        </div>
                                    </div>
                                <?php endif; ?>
                                
                                <?php if ($youngAdults): ?>
                                    <div class="age-group">
                                        <div class="age-label">15-24 anos</div>
                                        <div class="age-bar">
                                            <div class="age-bar-fill" style="width: <?php echo ($youngAdults / $population) * 100; ?>%;"></div>
                                        </div>
                                        <div class="age-value">
                                            <?php echo number_format($youngAdults, 0, ',', '.'); ?>
                                            (<?php echo round(($youngAdults / $population) * 100, 1); ?>%)
                                        </div>
                                    </div>
                            <?php endif; ?>
                            
                            <?php if ($workingPopulation): ?>
                                    <div class="age-group">
                                        <div class="age-label">25-64 anos</div>
                                        <div class="age-bar">
                                            <div class="age-bar-fill" style="width: <?php echo ($workingPopulation / $population) * 100; ?>%;"></div>
                                        </div>
                                        <div class="age-value">
                                            <?php echo number_format($workingPopulation, 0, ',', '.'); ?>
                                    (<?php echo round(($workingPopulation / $population) * 100, 1); ?>%)
                                        </div>
                                    </div>
                            <?php endif; ?>
                            
                            <?php if ($elderlyPopulation): ?>
                                    <div class="age-group">
                                        <div class="age-label">65+ anos</div>
                                        <div class="age-bar">
                                            <div class="age-bar-fill" style="width: <?php echo ($elderlyPopulation / $population) * 100; ?>%;"></div>
                                        </div>
                                        <div class="age-value">
                                            <?php echo number_format($elderlyPopulation, 0, ',', '.'); ?>
                                    (<?php echo round(($elderlyPopulation / $population) * 100, 1); ?>%)
                                        </div>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>

                        <!-- Informações sobre habitação -->
                        <?php if (isset($censusData['N_ALOJAMENTOS_FAMILIARES']) || 
                                  isset($censusData['N_ALOJAMENTOS_FAM_CLASS_RHABITUAL']) || 
                                  isset($censusData['N_ALOJAMENTOS_FAM_CLASS_VAGOS_OU_RESID_SECUNDARIA'])): ?>
                        <h3 class="subsection-title">Características da Habitação</h3>
                        <div class="demographic-highlights">
                            <?php
                            $familyDwellings = isset($censusData['N_ALOJAMENTOS_FAMILIARES']) ? $censusData['N_ALOJAMENTOS_FAMILIARES'] : null;
                            $primaryResidences = isset($censusData['N_ALOJAMENTOS_FAM_CLASS_RHABITUAL']) ? $censusData['N_ALOJAMENTOS_FAM_CLASS_RHABITUAL'] : null;
                            $secondaryOrVacant = isset($censusData['N_ALOJAMENTOS_FAM_CLASS_VAGOS_OU_RESID_SECUNDARIA']) ? $censusData['N_ALOJAMENTOS_FAM_CLASS_VAGOS_OU_RESID_SECUNDARIA'] : null;
                            $ownedHomes = isset($censusData['N_RHABITUAL_PROP_OCUP']) ? $censusData['N_RHABITUAL_PROP_OCUP'] : null;
                            $rentedHomes = isset($censusData['N_RHABITUAL_ARRENDADOS']) ? $censusData['N_RHABITUAL_ARRENDADOS'] : null;
                            $accessibleHomes = isset($censusData['N_RHABITUAL_ACESSIVEL_CADEIRAS_RODAS']) ? $censusData['N_RHABITUAL_ACESSIVEL_CADEIRAS_RODAS'] : null;
                            $withParking = isset($censusData['N_RHABITUAL_COM_ESTACIONAMENTO']) ? $censusData['N_RHABITUAL_COM_ESTACIONAMENTO'] : null;
                            ?>

                            <div class="info-row">
                                <?php if ($familyDwellings): ?>
                                    <div class="info-item">
                                        <div><strong>Alojamentos Familiares</strong></div>
                                        <div class="info-value"><?php echo number_format($familyDwellings, 0, ',', '.'); ?></div>
                                    </div>
                                <?php endif; ?>
                                
                                <?php if ($primaryResidences): ?>
                                    <div class="info-item">
                                        <div><strong>Residências Habituais</strong></div>
                                        <div class="info-value">
                                            <?php echo number_format($primaryResidences, 0, ',', '.'); ?>
                                            <?php if ($dwellings): ?>
                                                (<?php echo round(($primaryResidences / $dwellings) * 100, 1); ?>%)
                            <?php endif; ?>
                        </div>
                                    </div>
                                <?php endif; ?>
                                
                                <?php if ($secondaryOrVacant): ?>
                                    <div class="info-item">
                                        <div><strong>Residências Secundárias ou Vagas</strong></div>
                                        <div class="info-value">
                                            <?php echo number_format($secondaryOrVacant, 0, ',', '.'); ?>
                                            <?php if ($dwellings): ?>
                                                (<?php echo round(($secondaryOrVacant / $dwellings) * 100, 1); ?>%)
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                <?php endif; ?>
                            </div>

                            <div class="info-row">
                                <?php if ($ownedHomes && $rentedHomes): ?>
                                    <div class="info-item">
                                        <div><strong>Habitações Próprias</strong></div>
                                        <div class="info-value">
                                            <?php echo number_format($ownedHomes, 0, ',', '.'); ?>
                                            <?php if ($primaryResidences): ?>
                                                (<?php echo round(($ownedHomes / $primaryResidences) * 100, 1); ?>%)
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                    <div class="info-item">
                                        <div><strong>Habitações Arrendadas</strong></div>
                                        <div class="info-value">
                                            <?php echo number_format($rentedHomes, 0, ',', '.'); ?>
                                            <?php if ($primaryResidences): ?>
                                                (<?php echo round(($rentedHomes / $primaryResidences) * 100, 1); ?>%)
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                <?php endif; ?>
                            </div>

                            <div class="info-row">
                                <?php if ($accessibleHomes): ?>
                                    <div class="info-item">
                                        <div><strong>Acessíveis a Cadeiras de Rodas</strong></div>
                                        <div class="info-value">
                                            <?php echo number_format($accessibleHomes, 0, ',', '.'); ?>
                                            <?php if ($primaryResidences): ?>
                                                (<?php echo round(($accessibleHomes / $primaryResidences) * 100, 1); ?>%)
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                <?php endif; ?>
                                
                                <?php if ($withParking): ?>
                                    <div class="info-item">
                                        <div><strong>Com Estacionamento</strong></div>
                                        <div class="info-value">
                                            <?php echo number_format($withParking, 0, ',', '.'); ?>
                                            <?php if ($primaryResidences): ?>
                                                (<?php echo round(($withParking / $primaryResidences) * 100, 1); ?>%)
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                <?php endif; ?>
                            </div>
                            
                            <!-- Housing Charts -->
                            <?php if ($primaryResidences && $secondaryOrVacant && $ownedHomes && $rentedHomes): ?>
                            <div class="charts-container">
                                <div class="chart-card" style="--item-index: 0;">
                                    <h4 class="chart-title">Tipo de Residência</h4>
                                    <div class="chart-wrapper">
                                        <canvas id="residenceTypeChart"></canvas>
                                    </div>
                                </div>
                                
                                <div class="chart-card" style="--item-index: 1;">
                                    <h4 class="chart-title">Regime de Propriedade</h4>
                                    <div class="chart-wrapper">
                                        <canvas id="ownershipChart"></canvas>
                                    </div>
                                </div>
                            </div>
                            <?php endif; ?>
                        </div>
                        <?php endif; ?>

                        <!-- Informações sobre edifícios -->
                        <?php if (isset($censusData['N_EDIFICIOS_1_OU_2_PISOS']) || 
                                  isset($censusData['N_EDIFICIOS_3_OU_MAIS_PISOS']) ||
                                  isset($censusData['N_EDIFICIOS_CONSTR_ANTES_1945']) ||
                                  isset($censusData['N_EDIFICIOS_CONSTR_1946_1980']) ||
                                  isset($censusData['N_EDIFICIOS_CONSTR_1981_2000']) ||
                                  isset($censusData['N_EDIFICIOS_CONSTR_2001_2010']) ||
                                  isset($censusData['N_EDIFICIOS_CONSTR_2011_2021'])): ?>
                        <h3 class="subsection-title">Características dos Edifícios</h3>
                        <div class="demographic-highlights">
                            <?php
                            $lowBuildings = isset($censusData['N_EDIFICIOS_1_OU_2_PISOS']) ? $censusData['N_EDIFICIOS_1_OU_2_PISOS'] : null;
                            $highBuildings = isset($censusData['N_EDIFICIOS_3_OU_MAIS_PISOS']) ? $censusData['N_EDIFICIOS_3_OU_MAIS_PISOS'] : null;
                            $needsRepair = isset($censusData['N_EDIFICIOS_COM_NECESSIDADES_REPARACAO']) ? $censusData['N_EDIFICIOS_COM_NECESSIDADES_REPARACAO'] : null;
                            
                            // Idade dos edifícios
                            $veryOldBuildings = isset($censusData['N_EDIFICIOS_CONSTR_ANTES_1945']) ? $censusData['N_EDIFICIOS_CONSTR_ANTES_1945'] : null;
                            $oldBuildings = isset($censusData['N_EDIFICIOS_CONSTR_1946_1980']) ? $censusData['N_EDIFICIOS_CONSTR_1946_1980'] : null;
                            $mediumBuildings = isset($censusData['N_EDIFICIOS_CONSTR_1981_2000']) ? $censusData['N_EDIFICIOS_CONSTR_1981_2000'] : null;
                            $newBuildings = isset($censusData['N_EDIFICIOS_CONSTR_2001_2010']) ? $censusData['N_EDIFICIOS_CONSTR_2001_2010'] : null;
                            $veryNewBuildings = isset($censusData['N_EDIFICIOS_CONSTR_2011_2021']) ? $censusData['N_EDIFICIOS_CONSTR_2011_2021'] : null;
                            ?>

                            <div class="info-row">
                                <?php if ($lowBuildings && $highBuildings): ?>
                                    <div class="info-item">
                                        <div><strong>Edifícios com 1-2 Pisos</strong></div>
                                        <div class="info-value">
                                            <?php echo number_format($lowBuildings, 0, ',', '.'); ?>
                                            <?php if ($buildings): ?>
                                                (<?php echo round(($lowBuildings / $buildings) * 100, 1); ?>%)
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                    <div class="info-item">
                                        <div><strong>Edifícios com 3+ Pisos</strong></div>
                                        <div class="info-value">
                                            <?php echo number_format($highBuildings, 0, ',', '.'); ?>
                                            <?php if ($buildings): ?>
                                                (<?php echo round(($highBuildings / $buildings) * 100, 1); ?>%)
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                <?php endif; ?>
                                
                                <?php if ($needsRepair): ?>
                                    <div class="info-item">
                                        <div><strong>Necessitam Reparação</strong></div>
                                        <div class="info-value">
                                            <?php echo number_format($needsRepair, 0, ',', '.'); ?>
                                            <?php if ($buildings): ?>
                                                (<?php echo round(($needsRepair / $buildings) * 100, 1); ?>%)
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                <?php endif; ?>
                            </div>

                            <h4>Idade dos Edifícios</h4>
                            <div class="building-age">
                                <?php if ($veryOldBuildings): ?>
                                    <div class="age-group">
                                        <div class="age-label">Antes de 1945</div>
                                        <div class="age-bar">
                                            <div class="age-bar-fill building-old" style="width: <?php echo ($veryOldBuildings / $buildings) * 100; ?>%;"></div>
                                        </div>
                                        <div class="age-value">
                                            <?php echo number_format($veryOldBuildings, 0, ',', '.'); ?> 
                                            (<?php echo round(($veryOldBuildings / $buildings) * 100, 1); ?>%)
                                        </div>
                                    </div>
                                <?php endif; ?>
                                
                                <?php if ($oldBuildings): ?>
                                    <div class="age-group">
                                        <div class="age-label">1946-1980</div>
                                        <div class="age-bar">
                                            <div class="age-bar-fill building-medium" style="width: <?php echo ($oldBuildings / $buildings) * 100; ?>%;"></div>
                                        </div>
                                        <div class="age-value">
                                            <?php echo number_format($oldBuildings, 0, ',', '.'); ?>
                                            (<?php echo round(($oldBuildings / $buildings) * 100, 1); ?>%)
                                        </div>
                                    </div>
                                <?php endif; ?>
                                
                                <?php if ($mediumBuildings): ?>
                                    <div class="age-group">
                                        <div class="age-label">1981-2000</div>
                                        <div class="age-bar">
                                            <div class="age-bar-fill building-medium" style="width: <?php echo ($mediumBuildings / $buildings) * 100; ?>%;"></div>
                                        </div>
                                        <div class="age-value">
                                            <?php echo number_format($mediumBuildings, 0, ',', '.'); ?>
                                            (<?php echo round(($mediumBuildings / $buildings) * 100, 1); ?>%)
                                        </div>
                                    </div>
                                <?php endif; ?>
                                
                                <?php if ($newBuildings): ?>
                                    <div class="age-group">
                                        <div class="age-label">2001-2010</div>
                                        <div class="age-bar">
                                            <div class="age-bar-fill building-new" style="width: <?php echo ($newBuildings / $buildings) * 100; ?>%;"></div>
                                        </div>
                                        <div class="age-value">
                                            <?php echo number_format($newBuildings, 0, ',', '.'); ?>
                                            (<?php echo round(($newBuildings / $buildings) * 100, 1); ?>%)
                                        </div>
                                    </div>
                                <?php endif; ?>
                                
                                <?php if ($veryNewBuildings): ?>
                                    <div class="age-group">
                                        <div class="age-label">2011-2021</div>
                                        <div class="age-bar">
                                            <div class="age-bar-fill building-new" style="width: <?php echo ($veryNewBuildings / $buildings) * 100; ?>%;"></div>
                                        </div>
                                        <div class="age-value">
                                            <?php echo number_format($veryNewBuildings, 0, ',', '.'); ?>
                                            (<?php echo round(($veryNewBuildings / $buildings) * 100, 1); ?>%)
                                        </div>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>

                        <!-- Building Charts -->
                        <?php if ($veryOldBuildings && $oldBuildings && $mediumBuildings && $newBuildings && $veryNewBuildings): ?>
                        <div class="charts-container">
                            <div class="chart-card" style="--item-index: 0;">
                                <h4 class="chart-title">Altura dos Edifícios</h4>
                                <div class="chart-wrapper">
                                    <canvas id="buildingHeightChart"></canvas>
                                </div>
                            </div>
                            
                            <div class="chart-card" style="--item-index: 1;">
                                <h4 class="chart-title">Idade dos Edifícios</h4>
                                <div class="chart-wrapper">
                                    <canvas id="buildingAgeChart"></canvas>
                                </div>
                            </div>
                        </div>
                        <?php endif; ?>
                        <?php endif; ?>
                        
                        <!-- Comparison Section - Only shown when both census data are available -->
                        <?php if ($hasBothCensus): ?>
                        <h3 class="subsection-title">Comparação Censos 2011-2021</h3>
                        
                        <?php
                        // Extrair dados comparáveis de ambos os censos
                        // População
                        $population2021 = isset($censusData2021['N_INDIVIDUOS_RESIDENT']) ? $censusData2021['N_INDIVIDUOS_RESIDENT'] : 
                                        (isset($censusData2021['N_INDIVIDUOS']) ? $censusData2021['N_INDIVIDUOS'] : null);
                        
                        $population2011 = isset($censusData2011['N_INDIVIDUOS_RESIDENT']) ? $censusData2011['N_INDIVIDUOS_RESIDENT'] : 
                                        (isset($censusData2011['N_INDIVIDUOS']) ? $censusData2011['N_INDIVIDUOS'] : null);
                        
                        // Edifícios
                        $buildings2021 = isset($censusData2021['N_EDIFICIOS_CLASSICOS']) ? $censusData2021['N_EDIFICIOS_CLASSICOS'] : 
                                        (isset($censusData2021['N_EDIFICIOS']) ? $censusData2021['N_EDIFICIOS'] : null);
                        
                        $buildings2011 = isset($censusData2011['N_EDIFICIOS_CLASSICOS']) ? $censusData2011['N_EDIFICIOS_CLASSICOS'] : 
                                        (isset($censusData2011['N_EDIFICIOS']) ? $censusData2011['N_EDIFICIOS'] : null);
                        
                        // Alojamentos
                        $dwellings2021 = isset($censusData2021['N_ALOJAMENTOS_TOTAL']) ? $censusData2021['N_ALOJAMENTOS_TOTAL'] : 
                                        (isset($censusData2021['N_ALOJAMENTOS']) ? $censusData2021['N_ALOJAMENTOS'] : null);
                        
                        $dwellings2011 = isset($censusData2011['N_ALOJAMENTOS_TOTAL']) ? $censusData2011['N_ALOJAMENTOS_TOTAL'] : 
                                        (isset($censusData2011['N_ALOJAMENTOS']) ? $censusData2011['N_ALOJAMENTOS'] : null);
                        
                        // Género
                        $males2021 = isset($censusData2021['N_INDIVIDUOS_H']) ? $censusData2021['N_INDIVIDUOS_H'] : null;
                        $males2011 = isset($censusData2011['N_INDIVIDUOS_H']) ? $censusData2011['N_INDIVIDUOS_H'] : null;
                        
                        $females2021 = isset($censusData2021['N_INDIVIDUOS_M']) ? $censusData2021['N_INDIVIDUOS_M'] : null;
                        $females2011 = isset($censusData2011['N_INDIVIDUOS_M']) ? $censusData2011['N_INDIVIDUOS_M'] : null;
                        
                        // Faixas etárias
                        $young2021 = isset($censusData2021['N_INDIVIDUOS_0_14']) ? $censusData2021['N_INDIVIDUOS_0_14'] : null;
                        $young2011 = isset($censusData2011['N_INDIVIDUOS_0_14']) ? $censusData2011['N_INDIVIDUOS_0_14'] : null;
                        
                        $workingAge2021 = isset($censusData2021['N_INDIVIDUOS_25_64']) ? $censusData2021['N_INDIVIDUOS_25_64'] : null;
                        $workingAge2011 = isset($censusData2011['N_INDIVIDUOS_25_64']) ? $censusData2011['N_INDIVIDUOS_25_64'] : null;
                        
                        $elderly2021 = isset($censusData2021['N_INDIVIDUOS_65_OU_MAIS']) ? $censusData2021['N_INDIVIDUOS_65_OU_MAIS'] : null;
                        $elderly2011 = isset($censusData2011['N_INDIVIDUOS_65_OU_MAIS']) ? $censusData2011['N_INDIVIDUOS_65_OU_MAIS'] : null;
                        
                        // Função para calcular a mudança percentual
                        function calculateChange($new, $old) {
                            if ($old == 0) return null;
                            return (($new - $old) / $old) * 100;
                        }
                        
                        // Calcular mudanças percentuais
                        $populationChange = ($population2021 && $population2011) ? calculateChange($population2021, $population2011) : null;
                        $buildingsChange = ($buildings2021 && $buildings2011) ? calculateChange($buildings2021, $buildings2011) : null;
                        $dwellingsChange = ($dwellings2021 && $dwellings2011) ? calculateChange($dwellings2021, $dwellings2011) : null;
                        $malesChange = ($males2021 && $males2011) ? calculateChange($males2021, $males2011) : null;
                        $femalesChange = ($females2021 && $females2011) ? calculateChange($females2021, $females2011) : null;
                        $youngChange = ($young2021 && $young2011) ? calculateChange($young2021, $young2011) : null;
                        $workingAgeChange = ($workingAge2021 && $workingAge2011) ? calculateChange($workingAge2021, $workingAge2011) : null;
                        $elderlyChange = ($elderly2021 && $elderly2011) ? calculateChange($elderly2021, $elderly2011) : null;
                        ?>
                        
                        <!-- Principais métricas de comparação -->
                        <div class="comparison-row" style="--item-index: 0;">
                            <?php if ($populationChange !== null): ?>
                            <div class="comparison-card">
                                <div class="comparison-title">População</div>
                                <div class="comparison-value">
                                    <?php echo number_format($population2021, 0, ',', '.'); ?>
                                </div>
                                <div class="comparison-change <?php echo $populationChange > 0 ? 'change-positive' : ($populationChange < 0 ? 'change-negative' : 'change-neutral'); ?>">
                                    <i class="fas fa-<?php echo $populationChange > 0 ? 'arrow-up' : ($populationChange < 0 ? 'arrow-down' : 'minus'); ?> change-icon"></i>
                                    <?php echo number_format(abs($populationChange), 1, ',', '.'); ?>% desde 2011
                                </div>
                            </div>
                            <?php endif; ?>
                            
                            <?php if ($buildingsChange !== null): ?>
                            <div class="comparison-card">
                                <div class="comparison-title">Edifícios</div>
                                <div class="comparison-value">
                                    <?php echo number_format($buildings2021, 0, ',', '.'); ?>
                                </div>
                                <div class="comparison-change <?php echo $buildingsChange > 0 ? 'change-positive' : ($buildingsChange < 0 ? 'change-negative' : 'change-neutral'); ?>">
                                    <i class="fas fa-<?php echo $buildingsChange > 0 ? 'arrow-up' : ($buildingsChange < 0 ? 'arrow-down' : 'minus'); ?> change-icon"></i>
                                    <?php echo number_format(abs($buildingsChange), 1, ',', '.'); ?>% desde 2011
                                </div>
                            </div>
                            <?php endif; ?>
                            
                            <?php if ($dwellingsChange !== null): ?>
                            <div class="comparison-card">
                                <div class="comparison-title">Alojamentos</div>
                                <div class="comparison-value">
                                    <?php echo number_format($dwellings2021, 0, ',', '.'); ?>
                                </div>
                                <div class="comparison-change <?php echo $dwellingsChange > 0 ? 'change-positive' : ($dwellingsChange < 0 ? 'change-negative' : 'change-neutral'); ?>">
                                    <i class="fas fa-<?php echo $dwellingsChange > 0 ? 'arrow-up' : ($dwellingsChange < 0 ? 'arrow-down' : 'minus'); ?> change-icon"></i>
                                    <?php echo number_format(abs($dwellingsChange), 1, ',', '.'); ?>% desde 2011
                                </div>
                            </div>
                            <?php endif; ?>
                        </div>
                        
                        <!-- Comparação por idade e gênero -->
                        <?php if ($youngChange !== null || $workingAgeChange !== null || $elderlyChange !== null): ?>
                        <div class="comparison-row" style="--item-index: 1;">
                            <?php if ($youngChange !== null): ?>
                            <div class="comparison-card">
                                <div class="comparison-title">População Jovem (0-14)</div>
                                <div class="comparison-value">
                                    <?php echo number_format($young2021, 0, ',', '.'); ?>
                                </div>
                                <div class="comparison-change <?php echo $youngChange > 0 ? 'change-positive' : ($youngChange < 0 ? 'change-negative' : 'change-neutral'); ?>">
                                    <i class="fas fa-<?php echo $youngChange > 0 ? 'arrow-up' : ($youngChange < 0 ? 'arrow-down' : 'minus'); ?> change-icon"></i>
                                    <?php echo number_format(abs($youngChange), 1, ',', '.'); ?>% desde 2011
                                </div>
                            </div>
                            <?php endif; ?>
                            
                            <?php if ($elderlyChange !== null): ?>
                            <div class="comparison-card">
                                <div class="comparison-title">População Idosa (65+)</div>
                                <div class="comparison-value">
                                    <?php echo number_format($elderly2021, 0, ',', '.'); ?>
                                </div>
                                <div class="comparison-change <?php echo $elderlyChange > 0 ? 'change-positive' : ($elderlyChange < 0 ? 'change-negative' : 'change-neutral'); ?>">
                                    <i class="fas fa-<?php echo $elderlyChange > 0 ? 'arrow-up' : ($elderlyChange < 0 ? 'arrow-down' : 'minus'); ?> change-icon"></i>
                                    <?php echo number_format(abs($elderlyChange), 1, ',', '.'); ?>% desde 2011
                                </div>
                            </div>
                            <?php endif; ?>
                            
                            <?php if ($malesChange !== null || $femalesChange !== null): ?>
                            <div class="comparison-card">
                                <div class="comparison-title">Proporção Homens/Mulheres</div>
                                <div class="comparison-value">
                                    <?php 
                                    $ratio2021 = round(($males2021 / $females2021) * 100);
                                    $ratio2011 = round(($males2011 / $females2011) * 100);
                                    $ratioChange = calculateChange($ratio2021, $ratio2011);
                                    echo $ratio2021; ?>%
                                </div>
                                <div class="comparison-change <?php echo $ratioChange > 0 ? 'change-positive' : ($ratioChange < 0 ? 'change-negative' : 'change-neutral'); ?>">
                                    <i class="fas fa-<?php echo $ratioChange > 0 ? 'arrow-up' : ($ratioChange < 0 ? 'arrow-down' : 'minus'); ?> change-icon"></i>
                                    <?php echo number_format(abs($ratioChange), 1, ',', '.'); ?>% desde 2011
                                </div>
                            </div>
                            <?php endif; ?>
                        </div>
                        <?php endif; ?>
                        
                        <!-- Gráfico de comparação entre os censos -->
                        <div class="chart-card" style="--item-index: 2; max-width: 100%;">
                            <h4 class="chart-title">Comparação População 2011-2021</h4>
                            <div class="chart-wrapper" style="height: 300px;">
                                <canvas id="censusComparisonChart"></canvas>
                            </div>
                        </div>
                        <?php endif; ?>
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

    <!-- Add JavaScript for animations -->
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Ensure scrolling is enabled
            document.body.classList.add('scrollable');
            document.documentElement.classList.add('scrollable');
            
            // Reduce animation impact on scrolling
            const animatedElements = document.querySelectorAll('.age-bar-fill, .location-title:after, .section-title:after');
            animatedElements.forEach(el => {
                el.style.willChange = 'transform';
            });
            
            // Make sure scrolling is always available
            document.documentElement.style.overflowY = 'auto';
            document.body.style.overflowY = 'scroll';
            
            // Set the animation variables for age bars
            const ageBars = document.querySelectorAll('.age-bar-fill');
            ageBars.forEach((bar, index) => {
                // Get the width from the inline style and set it as a CSS variable
                const style = bar.getAttribute('style') || '';
                const widthMatch = style.match(/width:\s*([^;]+);/);
                if (widthMatch && widthMatch[1]) {
                    // Set the final width as a CSS variable
                    bar.style.setProperty('--final-width', widthMatch[1]);
                    // Reset the inline width to 0 to allow animation
                    bar.style.width = '0';
                }
                
                // Set item index for staggered animations
                bar.style.setProperty('--item-index', index);
            });
            
            // Set animation delay for age groups
            const ageGroups = document.querySelectorAll('.age-group');
            ageGroups.forEach((group, index) => {
                group.style.setProperty('--item-index', index);
            });
            
            // Initialize charts if they exist
            const initCharts = () => {
                // Chart.js Global Configuration
                Chart.defaults.font.family = "'Poppins', sans-serif";
                Chart.defaults.font.size = 14;
                Chart.defaults.plugins.tooltip.backgroundColor = 'rgba(44, 62, 80, 0.9)';
                Chart.defaults.plugins.tooltip.padding = 12;
                Chart.defaults.plugins.tooltip.cornerRadius = 8;
                Chart.defaults.plugins.tooltip.titleFont = { weight: 'bold', size: 14 };
                Chart.defaults.plugins.tooltip.bodyFont = { size: 14 };
                Chart.defaults.plugins.legend.position = 'bottom';
                Chart.defaults.plugins.legend.labels.padding = 15;
                Chart.defaults.animation.duration = 1500;
                Chart.defaults.animation.easing = 'easeOutQuart';
                
                // Color palettes
                const mainColors = [
                    '#3498db', '#2ecc71', '#e74c3c', '#f39c12', '#9b59b6', 
                    '#1abc9c', '#34495e', '#d35400', '#c0392b', '#16a085'
                ];
                
                const buildingAgeColors = [
                    '#c0392b', '#e67e22', '#f39c12', '#27ae60', '#2ecc71'
                ];
                
                // Gender Chart
                const genderChart = document.getElementById('genderChart');
                if (genderChart) {
                    <?php if ($males && $females): ?>
                    new Chart(genderChart, {
                        type: 'pie',
                        data: {
                            labels: ['Homens', 'Mulheres'],
                            datasets: [{
                                data: [<?php echo $males; ?>, <?php echo $females; ?>],
                                backgroundColor: ['#3498db', '#e74c3c'],
                                borderWidth: 1,
                                borderColor: '#ffffff'
                            }]
                        },
                        options: {
                            plugins: {
                                tooltip: {
                                    callbacks: {
                                        label: function(context) {
                                            const value = context.raw;
                                            const percentage = ((value / <?php echo $population; ?>) * 100).toFixed(1);
                                            return `${context.label}: ${value.toLocaleString('pt-PT')} (${percentage}%)`;
                                        }
                                    }
                                }
                            },
                            responsive: true,
                            maintainAspectRatio: false
                        }
                    });
                    <?php endif; ?>
                }
                
                // Age Distribution Chart
                const ageChart = document.getElementById('ageChart');
                if (ageChart) {
                    <?php if ($youngPopulation && $youngAdults && $workingPopulation && $elderlyPopulation): ?>
                    new Chart(ageChart, {
                        type: 'pie',
                        data: {
                            labels: ['0-14 anos', '15-24 anos', '25-64 anos', '65+ anos'],
                            datasets: [{
                                data: [
                                    <?php echo $youngPopulation; ?>, 
                                    <?php echo $youngAdults; ?>, 
                                    <?php echo $workingPopulation; ?>, 
                                    <?php echo $elderlyPopulation; ?>
                                ],
                                backgroundColor: ['#3498db', '#2ecc71', '#f39c12', '#9b59b6'],
                                borderWidth: 1,
                                borderColor: '#ffffff'
                            }]
                        },
                        options: {
                            plugins: {
                                tooltip: {
                                    callbacks: {
                                        label: function(context) {
                                            const value = context.raw;
                                            const percentage = ((value / <?php echo $population; ?>) * 100).toFixed(1);
                                            return `${context.label}: ${value.toLocaleString('pt-PT')} (${percentage}%)`;
                                        }
                                    }
                                }
                            },
                            responsive: true,
                            maintainAspectRatio: false
                        }
                    });
                    <?php endif; ?>
                }
                
                // Housing Type Chart
                const residenceTypeChart = document.getElementById('residenceTypeChart');
                if (residenceTypeChart) {
                    <?php if (isset($primaryResidences) && isset($secondaryOrVacant)): ?>
                    new Chart(residenceTypeChart, {
                        type: 'pie',
                        data: {
                            labels: ['Residências Habituais', 'Residências Secundárias ou Vagas'],
                            datasets: [{
                                data: [<?php echo $primaryResidences; ?>, <?php echo $secondaryOrVacant; ?>],
                                backgroundColor: ['#2ecc71', '#f39c12'],
                                borderWidth: 1,
                                borderColor: '#ffffff'
                            }]
                        },
                        options: {
                            plugins: {
                                tooltip: {
                                    callbacks: {
                                        label: function(context) {
                                            const value = context.raw;
                                            const percentage = ((value / <?php echo $dwellings; ?>) * 100).toFixed(1);
                                            return `${context.label}: ${value.toLocaleString('pt-PT')} (${percentage}%)`;
                                        }
                                    }
                                }
                            },
                            responsive: true,
                            maintainAspectRatio: false
                        }
                    });
                    <?php endif; ?>
                }
                
                // Ownership Chart
                const ownershipChart = document.getElementById('ownershipChart');
                if (ownershipChart) {
                    <?php if (isset($ownedHomes) && isset($rentedHomes)): ?>
                    new Chart(ownershipChart, {
                        type: 'pie',
                        data: {
                            labels: ['Habitações Próprias', 'Habitações Arrendadas', 'Outros'],
                            datasets: [{
                                data: [
                                    <?php echo $ownedHomes; ?>, 
                                    <?php echo $rentedHomes; ?>, 
                                    <?php echo $primaryResidences - $ownedHomes - $rentedHomes; ?>
                                ],
                                backgroundColor: ['#3498db', '#e74c3c', '#95a5a6'],
                                borderWidth: 1,
                                borderColor: '#ffffff'
                            }]
                        },
                        options: {
                            plugins: {
                                tooltip: {
                                    callbacks: {
                                        label: function(context) {
                                            const value = context.raw;
                                            const percentage = ((value / <?php echo $primaryResidences; ?>) * 100).toFixed(1);
                                            return `${context.label}: ${value.toLocaleString('pt-PT')} (${percentage}%)`;
                                        }
                                    }
                                }
                            },
                            responsive: true,
                            maintainAspectRatio: false
                        }
                    });
                    <?php endif; ?>
                }
                
                // Building Height Chart
                const buildingHeightChart = document.getElementById('buildingHeightChart');
                if (buildingHeightChart) {
                    <?php if (isset($lowBuildings) && isset($highBuildings)): ?>
                    new Chart(buildingHeightChart, {
                        type: 'pie',
                        data: {
                            labels: ['1-2 Pisos', '3+ Pisos'],
                            datasets: [{
                                data: [<?php echo $lowBuildings; ?>, <?php echo $highBuildings; ?>],
                                backgroundColor: ['#3498db', '#9b59b6'],
                                borderWidth: 1,
                                borderColor: '#ffffff'
                            }]
                        },
                        options: {
                            plugins: {
                                tooltip: {
                                    callbacks: {
                                        label: function(context) {
                                            const value = context.raw;
                                            const percentage = ((value / <?php echo $buildings; ?>) * 100).toFixed(1);
                                            return `${context.label}: ${value.toLocaleString('pt-PT')} (${percentage}%)`;
                                        }
                                    }
                                }
                            },
                            responsive: true,
                            maintainAspectRatio: false
                        }
                    });
                    <?php endif; ?>
                }
                
                // Building Age Chart
                const buildingAgeChart = document.getElementById('buildingAgeChart');
                if (buildingAgeChart) {
                    <?php if (isset($veryOldBuildings) && isset($oldBuildings) && isset($mediumBuildings) && isset($newBuildings) && isset($veryNewBuildings)): ?>
                    new Chart(buildingAgeChart, {
                        type: 'pie',
                        data: {
                            labels: ['Antes de 1945', '1946-1980', '1981-2000', '2001-2010', '2011-2021'],
                            datasets: [{
                                data: [
                                    <?php echo $veryOldBuildings; ?>, 
                                    <?php echo $oldBuildings; ?>, 
                                    <?php echo $mediumBuildings; ?>, 
                                    <?php echo $newBuildings; ?>, 
                                    <?php echo $veryNewBuildings; ?>
                                ],
                                backgroundColor: buildingAgeColors,
                                borderWidth: 1,
                                borderColor: '#ffffff'
                            }]
                        },
                        options: {
                            plugins: {
                                tooltip: {
                                    callbacks: {
                                        label: function(context) {
                                            const value = context.raw;
                                            const percentage = ((value / <?php echo $buildings; ?>) * 100).toFixed(1);
                                            return `${context.label}: ${value.toLocaleString('pt-PT')} (${percentage}%)`;
                                        }
                                    }
                                }
                            },
                            responsive: true,
                            maintainAspectRatio: false
                        }
                    });
                    <?php endif; ?>
                }
            };
            
            // Use a more performant way to handle animations
            const observerOptions = {
                root: null,
                rootMargin: '50px 0px',
                threshold: 0.1
            };
            
            const observer = new IntersectionObserver((entries) => {
                entries.forEach(entry => {
                    if (entry.isIntersecting) {
                        // Use requestAnimationFrame to not block scrolling
                        requestAnimationFrame(() => {
                            entry.target.style.animationPlayState = 'running';
                        });
                        observer.unobserve(entry.target);
                    }
                });
            }, observerOptions);
            
            // Observe all animated elements with a small delay to prioritize scrolling
            setTimeout(() => {
                const animatedElements = document.querySelectorAll('.location-card, .location-title, .location-section, .section-title, .subsection-title, .info-row, .demographic-highlights, .location-hierarchy, .instructions, .age-group, h4, .chart-card');
                animatedElements.forEach(el => {
                    // Pause the animation initially
                    el.style.animationPlayState = 'paused';
                    observer.observe(el);
                });
                
                // Initialize charts after a small delay
                setTimeout(initCharts, 300);
                
                // Initialize the comparison chart if available
                initComparisonChart();
                
                // Set up census toggle functionality
                setupCensusToggle();
            }, 100); // Small delay to ensure page is ready
            
            // Add scroll event listener to ensure scrolling is never blocked
            window.addEventListener('scroll', function() {
                document.body.style.overflowY = 'auto';
            }, {passive: true});
            
            // Function to initialize the census comparison chart
            function initComparisonChart() {
                const comparisonChart = document.getElementById('censusComparisonChart');
                if (!comparisonChart) return;
                
                <?php if ($hasBothCensus): ?>
                // Data for comparison chart
                const censusComparisonData = {
                    labels: ['População', 'Edifícios', 'Alojamentos', 'População Jovem', 'População Idosa'],
                    datasets: [
                        {
                            label: 'Censo 2011',
                            backgroundColor: 'rgba(52, 152, 219, 0.5)',
                            borderColor: 'rgba(52, 152, 219, 1)',
                            borderWidth: 1,
                            data: [
                                <?php echo $population2011 ?: 0; ?>,
                                <?php echo $buildings2011 ?: 0; ?>,
                                <?php echo $dwellings2011 ?: 0; ?>,
                                <?php echo $young2011 ?: 0; ?>,
                                <?php echo $elderly2011 ?: 0; ?>
                            ]
                        },
                        {
                            label: 'Censo 2021',
                            backgroundColor: 'rgba(46, 204, 113, 0.5)',
                            borderColor: 'rgba(46, 204, 113, 1)',
                            borderWidth: 1,
                            data: [
                                <?php echo $population2021 ?: 0; ?>,
                                <?php echo $buildings2021 ?: 0; ?>,
                                <?php echo $dwellings2021 ?: 0; ?>,
                                <?php echo $young2021 ?: 0; ?>,
                                <?php echo $elderly2021 ?: 0; ?>
                            ]
                        }
                    ]
                };
                
                new Chart(comparisonChart, {
                    type: 'bar',
                    data: censusComparisonData,
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        scales: {
                            y: {
                                beginAtZero: true,
                                ticks: {
                                    callback: function(value) {
                                        if (value >= 1000) {
                                            return (value / 1000).toLocaleString('pt-PT') + 'k';
                                        }
                                        return value.toLocaleString('pt-PT');
                                    }
                                }
                            }
                        },
                        plugins: {
                            tooltip: {
                                callbacks: {
                                    label: function(context) {
                                        const value = context.raw;
                                        let change = 0;
                                        
                                        if (context.datasetIndex === 1) { // 2021 data
                                            const value2011 = censusComparisonData.datasets[0].data[context.dataIndex];
                                            if (value2011 > 0) {
                                                change = ((value - value2011) / value2011) * 100;
                                            }
                                            return `${context.dataset.label}: ${value.toLocaleString('pt-PT')} (${change > 0 ? '+' : ''}${change.toFixed(1)}%)`;
                                        }
                                        
                                        return `${context.dataset.label}: ${value.toLocaleString('pt-PT')}`;
                                    }
                                }
                            }
                        }
                    }
                });
                <?php endif; ?>
            }
            
            // Function to set up census toggle functionality
            function setupCensusToggle() {
                const censusToggle = document.getElementById('censusToggle');
                const toggleStatus = document.getElementById('toggleStatus');
                
                if (!censusToggle) return;
                
                censusToggle.addEventListener('change', function() {
                    const year = this.checked ? 2021 : 2011;
                    if (toggleStatus) {
                        toggleStatus.textContent = `Censo ${year}`;
                    }
                    
                    // Update display of all comparison elements
                    document.querySelectorAll('.comparison-value, .comparison-change').forEach(el => {
                        el.dataset.year = year;
                        // Add a fade transition
                        el.style.opacity = '0';
                        setTimeout(() => {
                            el.style.opacity = '1';
                        }, 200);
                    });
                    
                    // Highlight active datasets in the chart
                    const charts = Chart.getChart('censusComparisonChart');
                    if (charts) {
                        const activeIndex = year === 2021 ? 1 : 0;
                        const inactiveIndex = year === 2021 ? 0 : 1;
                        
                        charts.data.datasets[activeIndex].backgroundColor = year === 2021 ? 
                            'rgba(46, 204, 113, 0.7)' : 'rgba(52, 152, 219, 0.7)';
                        charts.data.datasets[inactiveIndex].backgroundColor = year === 2021 ? 
                            'rgba(52, 152, 219, 0.2)' : 'rgba(46, 204, 113, 0.2)';
                            
                        charts.update();
                    }
                    
                    // Update values in the demographic-highlights section based on selected year
                    updateValuesBasedOnYear(year);
                    
                    // Update all pie charts with data from the selected year
                    updatePieCharts(year);
                });
            }
            
            // Function to update pie charts based on selected census year
            function updatePieCharts(year) {
                <?php if ($hasBothCensus): ?>
                // Gender data
                const genderChart = Chart.getChart('genderChart');
                if (genderChart) {
                    const malesData = {
                        2011: <?php echo $males2011 ?: 0; ?>,
                        2021: <?php echo $males2021 ?: 0; ?>
                    };
                    const femalesData = {
                        2011: <?php echo $females2011 ?: 0; ?>,
                        2021: <?php echo $females2021 ?: 0; ?>
                    };
                    
                    genderChart.data.datasets[0].data = [malesData[year], femalesData[year]];
                    genderChart.update();
                }
                
                // Age distribution data
                const ageChart = Chart.getChart('ageChart');
                if (ageChart) {
                    const youngData = {
                        2011: <?php echo $young2011 ?: 0; ?>,
                        2021: <?php echo $young2021 ?: 0; ?>
                    };
                    const youngAdultsData = {
                        2011: <?php echo isset($censusData2011['N_INDIVIDUOS_15_24']) ? $censusData2011['N_INDIVIDUOS_15_24'] : 0; ?>,
                        2021: <?php echo isset($censusData2021['N_INDIVIDUOS_15_24']) ? $censusData2021['N_INDIVIDUOS_15_24'] : 0; ?>
                    };
                    const workingAgeData = {
                        2011: <?php echo $workingAge2011 ?: 0; ?>,
                        2021: <?php echo $workingAge2021 ?: 0; ?>
                    };
                    const elderlyData = {
                        2011: <?php echo $elderly2011 ?: 0; ?>,
                        2021: <?php echo $elderly2021 ?: 0; ?>
                    };
                    
                    ageChart.data.datasets[0].data = [
                        youngData[year],
                        youngAdultsData[year],
                        workingAgeData[year],
                        elderlyData[year]
                    ];
                    ageChart.update();
                }
                
                // Building height data
                const buildingHeightChart = Chart.getChart('buildingHeightChart');
                if (buildingHeightChart) {
                    const lowBuildingsData = {
                        2011: <?php echo isset($censusData2011['N_EDIFICIOS_1_OU_2_PISOS']) ? $censusData2011['N_EDIFICIOS_1_OU_2_PISOS'] : 0; ?>,
                        2021: <?php echo isset($censusData2021['N_EDIFICIOS_1_OU_2_PISOS']) ? $censusData2021['N_EDIFICIOS_1_OU_2_PISOS'] : 0; ?>
                    };
                    const highBuildingsData = {
                        2011: <?php echo isset($censusData2011['N_EDIFICIOS_3_OU_MAIS_PISOS']) ? $censusData2011['N_EDIFICIOS_3_OU_MAIS_PISOS'] : 0; ?>,
                        2021: <?php echo isset($censusData2021['N_EDIFICIOS_3_OU_MAIS_PISOS']) ? $censusData2021['N_EDIFICIOS_3_OU_MAIS_PISOS'] : 0; ?>
                    };
                    
                    buildingHeightChart.data.datasets[0].data = [lowBuildingsData[year], highBuildingsData[year]];
                    buildingHeightChart.update();
                }
                
                // Building age data
                const buildingAgeChart = Chart.getChart('buildingAgeChart');
                if (buildingAgeChart) {
                    const veryOldData = {
                        2011: <?php echo isset($censusData2011['N_EDIFICIOS_CONSTR_ANTES_1945']) ? $censusData2011['N_EDIFICIOS_CONSTR_ANTES_1945'] : 0; ?>,
                        2021: <?php echo isset($censusData2021['N_EDIFICIOS_CONSTR_ANTES_1945']) ? $censusData2021['N_EDIFICIOS_CONSTR_ANTES_1945'] : 0; ?>
                    };
                    const oldData = {
                        2011: <?php echo isset($censusData2011['N_EDIFICIOS_CONSTR_1946_1980']) ? $censusData2011['N_EDIFICIOS_CONSTR_1946_1980'] : 0; ?>,
                        2021: <?php echo isset($censusData2021['N_EDIFICIOS_CONSTR_1946_1980']) ? $censusData2021['N_EDIFICIOS_CONSTR_1946_1980'] : 0; ?>
                    };
                    const mediumData = {
                        2011: <?php echo isset($censusData2011['N_EDIFICIOS_CONSTR_1981_2000']) ? $censusData2011['N_EDIFICIOS_CONSTR_1981_2000'] : 0; ?>,
                        2021: <?php echo isset($censusData2021['N_EDIFICIOS_CONSTR_1981_2000']) ? $censusData2021['N_EDIFICIOS_CONSTR_1981_2000'] : 0; ?>
                    };
                    const newData = {
                        2011: <?php echo isset($censusData2011['N_EDIFICIOS_CONSTR_2001_2010']) ? $censusData2011['N_EDIFICIOS_CONSTR_2001_2010'] : 0; ?>,
                        2021: <?php echo isset($censusData2021['N_EDIFICIOS_CONSTR_2001_2010']) ? $censusData2021['N_EDIFICIOS_CONSTR_2001_2010'] : 0; ?>
                    };
                    // This category only exists in 2021 census
                    const veryNewData = {
                        2011: 0,
                        2021: <?php echo isset($censusData2021['N_EDIFICIOS_CONSTR_2011_2021']) ? $censusData2021['N_EDIFICIOS_CONSTR_2011_2021'] : 0; ?>
                    };
                    
                    buildingAgeChart.data.datasets[0].data = [
                        veryOldData[year],
                        oldData[year],
                        mediumData[year],
                        newData[year],
                        veryNewData[year]
                    ];
                    
                    // Update visibility of the 2011-2021 category for 2011 census
                    if (year === 2011 && buildingAgeChart.data.labels.length === 5) {
                        // Hide the last label visually for 2011 data (it's not applicable)
                        buildingAgeChart.data.labels[4] = '';
                    } else if (year === 2021 && buildingAgeChart.data.labels.length === 5) {
                        // Restore the label for 2021 data
                        buildingAgeChart.data.labels[4] = '2011-2021';
                    }
                    
                    buildingAgeChart.update();
                }
                
                // Housing type data
                const residenceTypeChart = Chart.getChart('residenceTypeChart');
                if (residenceTypeChart) {
                    const primaryResidencesData = {
                        2011: <?php echo isset($censusData2011['N_ALOJAMENTOS_FAM_CLASS_RHABITUAL']) ? $censusData2011['N_ALOJAMENTOS_FAM_CLASS_RHABITUAL'] : 0; ?>,
                        2021: <?php echo isset($censusData2021['N_ALOJAMENTOS_FAM_CLASS_RHABITUAL']) ? $censusData2021['N_ALOJAMENTOS_FAM_CLASS_RHABITUAL'] : 0; ?>
                    };
                    const secondaryResidencesData = {
                        2011: <?php echo isset($censusData2011['N_ALOJAMENTOS_FAM_CLASS_VAGOS_OU_RESID_SECUNDARIA']) ? $censusData2011['N_ALOJAMENTOS_FAM_CLASS_VAGOS_OU_RESID_SECUNDARIA'] : 0; ?>,
                        2021: <?php echo isset($censusData2021['N_ALOJAMENTOS_FAM_CLASS_VAGOS_OU_RESID_SECUNDARIA']) ? $censusData2021['N_ALOJAMENTOS_FAM_CLASS_VAGOS_OU_RESID_SECUNDARIA'] : 0; ?>
                    };
                    
                    residenceTypeChart.data.datasets[0].data = [primaryResidencesData[year], secondaryResidencesData[year]];
                    residenceTypeChart.update();
                }
                
                // Ownership data
                const ownershipChart = Chart.getChart('ownershipChart');
                if (ownershipChart) {
                    const ownedHomesData = {
                        2011: <?php echo isset($censusData2011['N_RHABITUAL_PROP_OCUP']) ? $censusData2011['N_RHABITUAL_PROP_OCUP'] : 0; ?>,
                        2021: <?php echo isset($censusData2021['N_RHABITUAL_PROP_OCUP']) ? $censusData2021['N_RHABITUAL_PROP_OCUP'] : 0; ?>
                    };
                    const rentedHomesData = {
                        2011: <?php echo isset($censusData2011['N_RHABITUAL_ARRENDADOS']) ? $censusData2011['N_RHABITUAL_ARRENDADOS'] : 0; ?>,
                        2021: <?php echo isset($censusData2021['N_RHABITUAL_ARRENDADOS']) ? $censusData2021['N_RHABITUAL_ARRENDADOS'] : 0; ?>
                    };
                    const primaryResidencesData = {
                        2011: <?php echo isset($censusData2011['N_ALOJAMENTOS_FAM_CLASS_RHABITUAL']) ? $censusData2011['N_ALOJAMENTOS_FAM_CLASS_RHABITUAL'] : 0; ?>,
                        2021: <?php echo isset($censusData2021['N_ALOJAMENTOS_FAM_CLASS_RHABITUAL']) ? $censusData2021['N_ALOJAMENTOS_FAM_CLASS_RHABITUAL'] : 0; ?>
                    };
                    
                    const othersData = {
                        2011: primaryResidencesData[2011] - ownedHomesData[2011] - rentedHomesData[2011],
                        2021: primaryResidencesData[2021] - ownedHomesData[2021] - rentedHomesData[2021]
                    };
                    
                    ownershipChart.data.datasets[0].data = [
                        ownedHomesData[year], 
                        rentedHomesData[year], 
                        othersData[year]
                    ];
                    ownershipChart.update();
                }
                <?php endif; ?>
            }
            
            // Function to update values based on selected census year
            function updateValuesBasedOnYear(year) {
                <?php if ($hasBothCensus): ?>
                const data = {
                    population: {
                        2011: <?php echo $population2011 ?: 0; ?>,
                        2021: <?php echo $population2021 ?: 0; ?>
                    },
                    buildings: {
                        2011: <?php echo $buildings2011 ?: 0; ?>,
                        2021: <?php echo $buildings2021 ?: 0; ?>
                    },
                    dwellings: {
                        2011: <?php echo $dwellings2011 ?: 0; ?>,
                        2021: <?php echo $dwellings2021 ?: 0; ?>
                    },
                    males: {
                        2011: <?php echo $males2011 ?: 0; ?>,
                        2021: <?php echo $males2021 ?: 0; ?>
                    },
                    females: {
                        2011: <?php echo $females2011 ?: 0; ?>,
                        2021: <?php echo $females2021 ?: 0; ?>
                    },
                    young: {
                        2011: <?php echo $young2011 ?: 0; ?>,
                        2021: <?php echo $young2021 ?: 0; ?>
                    },
                    elderly: {
                        2011: <?php echo $elderly2011 ?: 0; ?>,
                        2021: <?php echo $elderly2021 ?: 0; ?>
                    }
                };
                
                // Format number with thousands separator
                const formatNumber = (num) => {
                    return num.toLocaleString('pt-PT');
                };
                
                // Update main info items
                document.querySelectorAll('.info-item .info-value').forEach(el => {
                    const infoType = el.closest('.info-item').querySelector('strong').textContent.toLowerCase();
                    
                    if (infoType.includes('população')) {
                        el.innerHTML = `${formatNumber(data.population[year])}`;
                    } else if (infoType.includes('edifícios')) {
                        el.innerHTML = `${formatNumber(data.buildings[year])}`;
                    } else if (infoType.includes('alojamentos')) {
                        el.innerHTML = `${formatNumber(data.dwellings[year])}`;
                    }
                    
                    // Update small text showing census year
                    const smallEl = el.nextElementSibling;
                    if (smallEl && smallEl.tagName === 'DIV' && smallEl.querySelector('small')) {
                        smallEl.querySelector('small').textContent = `Censos ${year}`;
                    }
                });
                
                // Update gender info if present
                const malesItem = findElementWithText('.info-item strong', 'Homens');
                const femalesItem = findElementWithText('.info-item strong', 'Mulheres');
                
                if (malesItem) {
                    const malesValue = malesItem.closest('.info-item').querySelector('.info-value');
                    const malesPercentage = Math.round((data.males[year] / data.population[year]) * 100);
                    malesValue.innerHTML = `${formatNumber(data.males[year])} (${malesPercentage}%)`;
                }
                
                if (femalesItem) {
                    const femalesValue = femalesItem.closest('.info-item').querySelector('.info-value');
                    const femalesPercentage = Math.round((data.females[year] / data.population[year]) * 100);
                    femalesValue.innerHTML = `${formatNumber(data.females[year])} (${femalesPercentage}%)`;
                }
                
                // Also update age group bars if present
                const youngBar = findElementWithText('.age-label', '0-14');
                const elderlyBar = findElementWithText('.age-label', '65+');
                
                if (youngBar) {
                    const youngBarFill = youngBar.closest('.age-group').querySelector('.age-bar-fill');
                    const youngValue = youngBar.closest('.age-group').querySelector('.age-value');
                    const youngPercentage = Math.round((data.young[year] / data.population[year]) * 100);
                    
                    youngBarFill.style.width = `${youngPercentage}%`;
                    youngValue.textContent = `${formatNumber(data.young[year])} (${youngPercentage}%)`;
                }
                
                if (elderlyBar) {
                    const elderlyBarFill = elderlyBar.closest('.age-group').querySelector('.age-bar-fill');
                    const elderlyValue = elderlyBar.closest('.age-group').querySelector('.age-value');
                    const elderlyPercentage = Math.round((data.elderly[year] / data.population[year]) * 100);
                    
                    elderlyBarFill.style.width = `${elderlyPercentage}%`;
                    elderlyValue.textContent = `${formatNumber(data.elderly[year])} (${elderlyPercentage}%)`;
                }
                <?php endif; ?>
            }
            
            // Helper to find element by text content
            function findElementWithText(selector, text) {
                const elements = document.querySelectorAll(selector);
                for (let el of elements) {
                    if (el.textContent.includes(text)) {
                        return el;
                    }
                }
                return null;
            };
        });
    </script>
</body>
</html> 