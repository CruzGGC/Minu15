<?php
/**
 * Endpoint para Obter Pontos de Interesse (POIs)
 * Obtém pontos de interesse OSM que estão estritamente contidos no polígono da isócrona
 * Agora consulta dados OSM de pontos e polígonos
 * 
 * @version 2.2
 */

// Ativa o registo de erros para depuração
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);
ini_set('error_log', '../logs/db_errors.log');

// Inclui a configuração da base de dados
require_once '../config/db_config.php';

// Inclui o fornecedor de dados simulados
require_once 'mock_data.php';

// Define os cabeçalhos para a resposta JSON
header('Content-Type: application/json');

try {
    // Verifica o método do pedido
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        echo json_encode([
            'success' => false,
            'message' => 'Apenas o método POST é permitido'
        ]);
        exit;
    }

    // Verifica se todos os parâmetros obrigatórios são fornecidos
    if (empty($_POST['type']) || !isset($_POST['lat']) || !isset($_POST['lng'])) {
        echo json_encode([
            'success' => false,
            'message' => 'Parâmetros obrigatórios em falta: type, lat, lng'
        ]);
        exit;
    }

    // Obtém os parâmetros do pedido
    $type = $_POST['type'];
    $lat = floatval($_POST['lat']);
    $lng = floatval($_POST['lng']);

    // Obtém o JSON da isócrona (obrigatório para filtragem precisa de POIs)
    $isochroneJson = isset($_POST['isochrone']) ? $_POST['isochrone'] : null;

    // Se nenhuma isócrona for fornecida mas o raio for, obtém o raio para o buffer de fallback
    $radius = isset($_POST['radius']) ? floatval($_POST['radius']) : 0;

    // Valida os parâmetros
    if (!is_numeric($lat) || !is_numeric($lng) || $lat < -90 || $lat > 90 || $lng < -180 || $lng > 180) {
        echo json_encode([
            'success' => false,
            'message' => 'Latitude ou longitude inválida'
        ]);
        exit;
    }

    // Se não houver isócrona e nem raio, não é possível continuar
    if (empty($isochroneJson) && $radius <= 0) {
        echo json_encode([
            'success' => false,
            'message' => 'É necessário o GeoJSON da isócrona ou o raio'
        ]);
        exit;
    }

    // Define os tipos de POI com as suas condições de consulta PostgreSQL
    $poiTypes = [
        // === Saúde ===
        'hospitals' => [
            'condition' => "amenity = 'hospital'",
            'icon' => 'hospital',
            'category' => 'health'
        ],
        'health_centers' => [
            'condition' => "amenity IN ('clinic', 'doctors')",
            'icon' => 'first-aid-kit',
            'category' => 'health'
        ],
        'pharmacies' => [
            'condition' => "amenity = 'pharmacy'",
            'icon' => 'prescription-bottle-alt',
            'category' => 'health'
        ],
        'dentists' => [
            'condition' => "amenity = 'dentist'",
            'icon' => 'tooth',
            'category' => 'health'
        ],
        
        // === Educação ===
        'schools' => [
            'condition' => "amenity = 'school'",
            'icon' => 'school',
            'category' => 'education'
        ],
        'universities' => [
            'condition' => "amenity = 'university'",
            'icon' => 'graduation-cap',
            'category' => 'education'
        ],
        'kindergartens' => [
            'condition' => "amenity = 'kindergarten'",
            'icon' => 'child',
            'category' => 'education'
        ],
        'libraries' => [
            'condition' => "amenity = 'library'",
            'icon' => 'book',
            'category' => 'education'
        ],
        
        // === Comércio e Serviços ===
        'supermarkets' => [
            'condition' => "shop = 'supermarket' OR shop = 'convenience' OR shop = 'grocery'",
            'icon' => 'shopping-basket',
            'category' => 'commercial'
        ],
        'malls' => [
            'condition' => "shop = 'mall' OR amenity = 'marketplace'",
            'icon' => 'shopping-bag',
            'category' => 'commercial'
        ],
        'restaurants' => [
            'condition' => "amenity IN ('restaurant', 'cafe', 'bar', 'fast_food')",
            'icon' => 'utensils',
            'category' => 'commercial'
        ],
        'atms' => [
            'condition' => "amenity = 'atm' OR amenity = 'bank'",
            'icon' => 'money-bill-wave',
            'category' => 'commercial'
        ],
        
        // === Segurança ===
        'police' => [
            'condition' => "amenity = 'police'",
            'icon' => 'shield-alt',
            'category' => 'safety'
        ],
        'police_stations' => [
            'condition' => "amenity = 'police'",
            'icon' => 'shield-alt',
            'category' => 'safety'
        ],
        'fire_stations' => [
            'condition' => "amenity = 'fire_station'",
            'icon' => 'fire',
            'category' => 'safety'
        ],
        'civil_protection' => [
            'condition' => "office = 'government' OR amenity IN ('public_building', 'rescue_station', 'ambulance_station', 'emergency_service')",
            'icon' => 'building-columns',
            'category' => 'safety'
        ],
        
        // === Administração Pública ===
        'city_halls' => [
            'condition' => "amenity = 'townhall' OR (office = 'government' AND admin_level IN ('8', '9'))",
            'icon' => 'landmark',
            'category' => 'administration'
        ],
        'post_offices' => [
            'condition' => "amenity = 'post_office'",
            'icon' => 'envelope',
            'category' => 'administration'
        ],
        
        // === Cultura e Lazer ===
        'museums' => [
            'condition' => "tourism = 'museum' OR amenity = 'arts_centre'",
            'icon' => 'museum',
            'category' => 'culture'
        ],
        'theaters' => [
            'condition' => "amenity = 'theatre'",
            'icon' => 'theater-masks',
            'category' => 'culture'
        ],
        'sports' => [
            'condition' => "leisure IN ('sports_centre', 'stadium', 'pitch', 'swimming_pool', 'fitness_centre', 'fitness_station')",
            'icon' => 'dumbbell',
            'category' => 'culture'
        ],
        'parks' => [
            'condition' => "leisure IN ('park', 'garden', 'playground')",
            'icon' => 'tree',
            'category' => 'culture'
        ]
    ];

    // Verifica se o tipo de POI solicitado existe
    if (!array_key_exists($type, $poiTypes)) {
        echo json_encode([
            'success' => false,
            'message' => 'Tipo de POI inválido: ' . $type
        ]);
        exit;
    }

    // Informações de depuração para monitorizar a execução da consulta
    $debug_info = [];

    // Tenta obter a ligação à base de dados
    try {
        $conn = getDbConnection();
        $debug_info['db_connection'] = 'success';
    } catch (Exception $e) {
        // Devolve dados de POI simulados se a ligação à base de dados falhar
        $mockPois = generateMockPOIs($type, $lat, $lng, $radius);
        
        echo json_encode([
            'success' => true,
            'pois' => $mockPois,
            'count' => count($mockPois),
            'is_mock' => true,
            'message' => 'A usar dados simulados (a ligação à base de dados falhou): ' . $e->getMessage(),
            'debug' => ['error' => $e->getMessage()]
        ]);
        exit;
    }

    // Prepara a condição espacial com base na isócrona ou raio
    $spatialCondition = "";

    // Se tivermos dados de isócrona, usa-os (método preferido)
    if ($isochroneJson) {
        try {
            // Analisa o GeoJSON
            $isochrone = json_decode($isochroneJson, true);
            $debug_info['isochrone_parsed'] = true;
            
            // Extrai a geometria da primeira feature
            if (isset($isochrone['features']) && 
                isset($isochrone['features'][0]) && 
                isset($isochrone['features'][0]['geometry'])) {
                
                $geometry = json_encode($isochrone['features'][0]['geometry']);
                $debug_info['geometry_extracted'] = true;
                
                // Cria uma condição espacial PostgreSQL que usa ST_Contains para apenas
                // incluir POIs que estão estritamente dentro do polígono da isócrona
                $spatialCondition = "ST_Contains(
                    ST_Transform(
                        ST_SetSRID(
                            ST_GeomFromGeoJSON('$geometry'),
                            4326
                        ),
                        3857
                    ),
                    way
                )";
                $debug_info['using_isochrone'] = true;
            } else {
                throw new Exception("Estrutura GeoJSON da isócrona inválida");
            }
        } catch (Exception $e) {
            $debug_info['isochrone_error'] = $e->getMessage();
            
            // Se houver um erro com a isócrona, volta para o buffer do raio
            if ($radius > 0) {
                $spatialCondition = "ST_DWithin(
                    way, 
                    ST_Transform(ST_SetSRID(ST_MakePoint($lng, $lat), 4326), 3857), 
                    $radius
                )";
                $debug_info['using_radius_fallback'] = true;
            } else {
                echo json_encode([
                    'success' => false,
                    'message' => 'Erro ao processar isócrona e nenhum raio de fallback fornecido',
                    'debug' => $debug_info
                ]);
                exit;
            }
        }
    } else if ($radius > 0) {
        // Se nenhuma isócrona for fornecida mas tivermos raio, usa um buffer simples
        $spatialCondition = "ST_DWithin(
            way, 
            ST_Transform(ST_SetSRID(ST_MakePoint($lng, $lat), 4326), 3857), 
            $radius
        )";
        $debug_info['using_radius'] = true;
    } else {
        // Isso não deveria acontecer devido à validação anterior, mas por precaução
        echo json_encode([
            'success' => false,
            'message' => 'Nenhum método de filtragem espacial disponível'
        ]);
        exit;
    }

    // Obtém a condição para o tipo de POI solicitado
    $poiCondition = $poiTypes[$type]['condition'];

    // Prepara a consulta para obter POIs de ambas as tabelas de pontos e polígonos
    // Usando UNION para combinar resultados de ambas as tabelas
    $query = "
        (
            SELECT 
                ST_X(ST_Transform(way, 4326)) as longitude,
                ST_Y(ST_Transform(way, 4326)) as latitude,
                name,
                'point' as geometry_type,
                '$type' as type
            FROM 
                planet_osm_point
            WHERE 
                ($poiCondition)
                AND $spatialCondition
        )
        UNION
        (
            SELECT 
                ST_X(ST_Transform(ST_Centroid(way), 4326)) as longitude,
                ST_Y(ST_Transform(ST_Centroid(way), 4326)) as latitude,
                name,
                'polygon' as geometry_type,
                '$type' as type
            FROM 
                planet_osm_polygon
            WHERE 
                ($poiCondition)
                AND $spatialCondition
        )
    ";

    // Executa a consulta
    try {
        $result = executeQuery($conn, $query);
        $debug_info['query_executed'] = true;
    } catch (Exception $e) {
        echo json_encode([
            'success' => false,
            'message' => 'A execução da consulta falhou: ' . $e->getMessage(),
            'debug' => array_merge($debug_info, ['query_error' => $e->getMessage()])
        ]);
        exit;
    }

    // Processa os resultados
    $pois = [];
    while ($row = pg_fetch_assoc($result)) {
        // Apenas adiciona POIs que têm coordenadas
        if (!empty($row['longitude']) && !empty($row['latitude'])) {
            $pois[] = [
                'longitude' => (float) $row['longitude'],
                'latitude' => (float) $row['latitude'],
                'name' => !empty($row['name']) ? $row['name'] : $poiTypes[$type]['icon'],
                'type' => $type,
                'geometry_type' => $row['geometry_type']
            ];
        }
    }

    // Fecha a ligação à base de dados
    pg_close($conn);

    // Devolve os POIs como JSON
    echo json_encode([
        'success' => true,
        'pois' => $pois,
        'count' => count($pois),
        'debug' => $debug_info
    ]);

} catch (Exception $e) {
    // Captura quaisquer exceções não tratadas
    error_log('Exceção não tratada em fetch_pois.php: ' . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Ocorreu um erro inesperado: ' . $e->getMessage()
    ]);
}
?>