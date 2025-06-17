<?php
/**
 * Endpoint de Análise de Localização Ideal
 * Realiza análise baseada em grelha para encontrar localizações ótimas com base nos requisitos de POI
 */

// Impede qualquer saída antes da resposta JSON
error_reporting(0);
ini_set('display_errors', 0);

// Inclui a configuração da base de dados
require_once '../config/db_config.php';

// Define os cabeçalhos para a resposta JSON
header('Content-Type: application/json');

// Verifica o método do pedido
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode([
        'success' => false,
        'message' => 'Apenas o método POST é permitido'
    ]);
    exit;
}

// Obtém a entrada JSON
$input = json_decode(file_get_contents('php://input'), true);

if (!$input) {
    echo json_encode([
        'success' => false,
        'message' => 'Entrada JSON inválida'
    ]);
    exit;
}

// Valida os parâmetros obrigatórios
if (!isset($input['location']) || !isset($input['pois']) || empty($input['pois'])) {
    echo json_encode([
        'success' => false,
        'message' => 'Localização e POIs são obrigatórios'
    ]);
    exit;
}

$location = $input['location'];
$pois = $input['pois'];
$transportMode = $input['transport_mode'] ?? 'foot-walking';
$maxTime = $input['max_time'] ?? 15;
$gridResolution = $input['grid_resolution'] ?? 75;
$topLocations = $input['top_locations'] ?? 5;

try {
    // Calculate analysis bounds (approximately 20km radius for analysis area)
    $analysisRadius = 0.18; // degrees (approximately 20km)
    $bounds = [
        'min_lat' => $location['lat'] - $analysisRadius,
        'max_lat' => $location['lat'] + $analysisRadius,
        'min_lng' => $location['lng'] - $analysisRadius,
        'max_lng' => $location['lng'] + $analysisRadius
    ];
    
    // Calculate max distance based on transport mode and time
    $maxDistanceMeters = getMaxDistanceForMode($transportMode, $maxTime);
    
    // Get database connection
    $conn = getDbConnection();
    
    // Perform grid-based analysis
    $analysisResult = performGridAnalysis($conn, $location, $pois, $bounds, $gridResolution, $maxDistanceMeters);
    
    // Find top locations
    $topLocationsList = findTopLocations($analysisResult['grid_scores'], $topLocations, $pois);
    
    // Prepare response
    $response = [
        'success' => true,
        'data' => [
            'heatmap' => $analysisResult['heatmap_data'],
            'top_locations' => $topLocationsList,
            'analysis_info' => [
                'grid_resolution' => $gridResolution,
                'max_distance_meters' => $maxDistanceMeters,
                'poi_count' => count($pois),
                'total_grid_points' => $gridResolution * $gridResolution
            ]
        ]
    ];
    
    echo json_encode($response);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}

function getMaxDistanceForMode($mode, $timeMinutes) {
    // Estimativas de velocidade em metros por minuto
    $speeds = [
        'foot-walking' => 80,    // ~5 km/h
        'cycling-regular' => 250, // ~15 km/h
        'driving-car' => 500     // ~30 km/h na cidade
    ];
    
    $speed = $speeds[$mode] ?? $speeds['foot-walking'];
    return $speed * $timeMinutes;
}

function performGridAnalysis($conn, $centerLocation, $requiredPOIs, $bounds, $gridResolution, $maxDistance) {
    $gridScores = [];
    $heatmapData = [];
    
    // Calcula o tamanho do passo da grelha
    $latStep = ($bounds['max_lat'] - $bounds['min_lat']) / $gridResolution;
    $lngStep = ($bounds['max_lng'] - $bounds['min_lng']) / $gridResolution;
    
    // Obtém os dados de POI para cada tipo
    $poiData = [];
    foreach ($requiredPOIs as $poi) {
        $poiData[$poi['type']] = getPOIData($conn, $poi['type'], $bounds);
    }
    
    // Analisa cada ponto da grelha
    for ($i = 0; $i < $gridResolution; $i++) {
        for ($j = 0; $j < $gridResolution; $j++) {
            $lat = $bounds['min_lat'] + ($i * $latStep);
            $lng = $bounds['min_lng'] + ($j * $lngStep);
            
            $gridPoint = ['lat' => $lat, 'lng' => $lng];
            $score = calculateLocationScore($gridPoint, $requiredPOIs, $poiData, $maxDistance);
            
            $gridScores[] = [
                'lat' => $lat,
                'lng' => $lng,
                'score' => $score['total'],
                'poi_scores' => $score['details']
            ];
            
            // Adiciona ao mapa de calor se a pontuação > 0
            if ($score['total'] > 0) {
                $heatmapData[] = [$lat, $lng, $score['total'] / 100.0]; // Normaliza para 0-1
            }
        }
    }
    
    return [
        'grid_scores' => $gridScores,
        'heatmap_data' => $heatmapData
    ];
}

function getPOIData($conn, $poiType, $bounds) {
    // Mapeia tipos de POI para tags OSM (sincronizado com fetch_pois.php)
    $osmTagMapping = [
        // === Saúde ===
        'hospitals' => "amenity = 'hospital'",
        'health_centers' => "amenity IN ('clinic', 'doctors')",
        'pharmacies' => "amenity = 'pharmacy'",
        'dentists' => "amenity = 'dentist'",
        
        // === Educação ===
        'schools' => "amenity = 'school'",
        'universities' => "amenity = 'university'",
        'kindergartens' => "amenity = 'kindergarten'",
        'libraries' => "amenity = 'library'",
        
        // === Comércio e Serviços ===
        'supermarkets' => "shop = 'supermarket' OR shop = 'convenience' OR shop = 'grocery'",
        'malls' => "shop = 'mall' OR amenity = 'marketplace'",
        'restaurants' => "amenity IN ('restaurant', 'cafe', 'bar', 'fast_food')",
        'atms' => "amenity = 'atm' OR amenity = 'bank'",
        
        // === Segurança ===
        'police_stations' => "amenity = 'police'",
        'fire_stations' => "amenity = 'fire_station'",
        'civil_protection' => "office = 'government' OR amenity = 'rescue_station' OR amenity = 'ambulance_station' OR amenity = 'emergency_service'",
        
        // === Administração Pública ===
        'parish_councils' => "office = 'government' AND admin_level = '9'",
        'city_halls' => "office = 'government' AND admin_level = '8'",
        'post_offices' => "amenity = 'post_office'",
        
        // === Cultura e Lazer ===
        'museums' => "tourism = 'museum' OR amenity = 'arts_centre'",
        'theaters' => "amenity = 'theatre'",
        'sports' => "leisure IN ('sports_centre', 'stadium', 'pitch', 'swimming_pool', 'fitness_centre', 'fitness_station')",
        'parks' => "leisure IN ('park', 'garden', 'playground')",
        
        // === Suporte legado para tipos de POI antigos ===
        'hospital' => "amenity = 'hospital'",
        'clinic' => "amenity IN ('clinic', 'doctors')",
        'pharmacy' => "amenity = 'pharmacy'",
        'school' => "amenity = 'school'",
        'university' => "amenity = 'university'",
        'kindergarten' => "amenity = 'kindergarten'",
        'supermarket' => "shop = 'supermarket'",
        'restaurant' => "amenity = 'restaurant'",
        'bank' => "amenity = 'bank'",
        'shopping_mall' => "shop = 'mall'",
        'bus_stop' => "highway = 'bus_stop'",
        'subway_station' => "railway = 'station'",
        'post_office' => "amenity = 'post_office'",
        'fuel' => "amenity = 'fuel'"
    ];
    
    $condition = $osmTagMapping[$poiType] ?? "amenity = '$poiType'";
    
    try {
        $sql = "
            SELECT ST_Y(ST_Transform(way, 4326)) as lat, ST_X(ST_Transform(way, 4326)) as lng, name
            FROM planet_osm_point 
            WHERE $condition
            AND ST_Y(ST_Transform(way, 4326)) BETWEEN $1 AND $2
            AND ST_X(ST_Transform(way, 4326)) BETWEEN $3 AND $4
            AND way IS NOT NULL
            UNION ALL
            SELECT ST_Y(ST_Transform(ST_Centroid(way), 4326)) as lat, ST_X(ST_Transform(ST_Centroid(way), 4326)) as lng, name
            FROM planet_osm_polygon 
            WHERE $condition
            AND ST_Y(ST_Transform(ST_Centroid(way), 4326)) BETWEEN $1 AND $2
            AND ST_X(ST_Transform(ST_Centroid(way), 4326)) BETWEEN $3 AND $4
            AND way IS NOT NULL
        ";
        
        $result = pg_query_params($conn, $sql, [
            $bounds['min_lat'],
            $bounds['max_lat'], 
            $bounds['min_lng'],
            $bounds['max_lng']
        ]);
        
        if (!$result) {
            error_log("Error fetching POI data for $poiType: " . pg_last_error($conn));
            return [];
        }
        
        $pois = [];
        while ($row = pg_fetch_assoc($result)) {
            $pois[] = [
                'lat' => floatval($row['lat']),
                'lng' => floatval($row['lng']),
                'name' => $row['name'] ?? ''
            ];
        }
        
        return $pois;
        
    } catch (Exception $e) {
        error_log("Erro ao obter dados de POI para $poiType: " . $e->getMessage());
        return [];
    }
}

function calculateLocationScore($location, $requiredPOIs, $poiData, $maxDistance) {
    $totalScore = 0;
    $maxTotalScore = 0;
    $poiScores = [];
    
    foreach ($requiredPOIs as $poi) {
        $poiType = $poi['type'];
        $importance = $poi['importance'];
        $maxPOIScore = $importance * 25; // Max 25 points per importance level
        $maxTotalScore += $maxPOIScore;
        
        $pois = $poiData[$poiType] ?? [];
        $nearestDistance = findNearestDistance($location, $pois);
        
        // Calcula a pontuação de acessibilidade (0-25 pontos com base na importância)
        $accessibilityScore = 0;
        if ($nearestDistance !== null && $nearestDistance <= $maxDistance) {
            // Decaimento linear: mais perto = melhor pontuação
            $distanceRatio = max(0, ($maxDistance - $nearestDistance) / $maxDistance);
            $accessibilityScore = $distanceRatio * $maxPOIScore;
        }
        
        $totalScore += $accessibilityScore;
        $poiScores[] = [
            'type' => $poiType,
            'score' => $accessibilityScore,
            'max_score' => $maxPOIScore,
            'nearest_distance' => $nearestDistance
        ];
    }
    
    // Normaliza para uma escala de 0-100
    $normalizedScore = $maxTotalScore > 0 ? ($totalScore / $maxTotalScore) * 100 : 0;
    
    return [
        'total' => $normalizedScore,
        'details' => $poiScores
    ];
}

function findNearestDistance($location, $pois) {
    if (empty($pois)) {
        return null;
    }
    
    $minDistance = PHP_FLOAT_MAX;
    
    foreach ($pois as $poi) {
        $distance = haversineDistance(
            $location['lat'], $location['lng'],
            $poi['lat'], $poi['lng']
        );
        $minDistance = min($minDistance, $distance);
    }
    
    return $minDistance === PHP_FLOAT_MAX ? null : $minDistance;
}

function haversineDistance($lat1, $lng1, $lat2, $lng2) {
    $earthRadius = 6371000; // meters
    
    $dLat = deg2rad($lat2 - $lat1);
    $dLng = deg2rad($lng2 - $lng1);
    
    $a = sin($dLat/2) * sin($dLat/2) +
         cos(deg2rad($lat1)) * cos(deg2rad($lat2)) *
         sin($dLng/2) * sin($dLng/2);
    
    $c = 2 * atan2(sqrt($a), sqrt(1-$a));
    
    return $earthRadius * $c;
}

function findTopLocations($gridScores, $topCount, $requiredPOIs) {
    // Ordena por pontuação decrescente
    usort($gridScores, function($a, $b) {
        return $b['score'] <=> $a['score'];
    });
    
    // Filtra pontuações zero e obtém as principais localizações
    $validScores = array_filter($gridScores, function($point) {
        return $point['score'] > 0;
    });
    
    $topLocations = array_slice($validScores, 0, $topCount);
    
    // Formata para o frontend
    return array_map(function($location) {
        return [
            'lat' => $location['lat'],
            'lng' => $location['lng'],
            'total_score' => $location['score'],
            'poi_scores' => $location['poi_scores']
        ];
    }, $topLocations);
}
?>
