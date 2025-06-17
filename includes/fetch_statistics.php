<?php
/**
 * Endpoint para Obter Estatísticas de Área
 * Calcula estatísticas para uma área definida por um polígono isócrono ou raio
 * Agora inclui POIs de pontos e polígonos para contagem precisa
 * Adicionada integração com GeoAPI.pt para identificação de freguesias e dados demográficos
 * 
 * @version 2.3
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
    if (!isset($_POST['lat']) || !isset($_POST['lng'])) {
        echo json_encode([
            'success' => false,
            'message' => 'Parâmetros obrigatórios em falta: lat, lng'
        ]);
        exit;
    }

    // Obtém os parâmetros do pedido
    $lat = floatval($_POST['lat']);
    $lng = floatval($_POST['lng']);

    // Obtém o JSON da isócrona (preferencial para cálculo preciso de área)
    $isochroneJson = isset($_POST['isochrone']) ? $_POST['isochrone'] : null;

    // Se nenhuma isócrona for fornecida mas o raio for, usa o raio para cálculo de fallback
    $radius = isset($_POST['radius']) ? floatval($_POST['radius']) : 0;

    // Obtém os tipos de POI selecionados para estatísticas, se fornecidos
    $selectedPOIs = isset($_POST['selected_pois']) ? json_decode($_POST['selected_pois'], true) : null;

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

    // Array de informações de depuração
    $debug_info = [];

    // Variáveis para armazenar as informações de geometria e área
    $areaKm2 = null;
    $spatialCondition = "";
    $bufferGeometry = null;

    // Tenta obter a ligação à base de dados
    try {
        $conn = getDbConnection();
        $debug_info['db_connection'] = 'success';
    } catch (Exception $e) {
        // Devolve estatísticas simuladas se a ligação à base de dados falhar
        $mockStats = generateMockStatistics($lat, $lng, $radius);
        
        echo json_encode([
            'success' => true,
            'stats' => $mockStats,
            'message' => 'A usar estatísticas simuladas (a ligação à base de dados falhou): ' . $e->getMessage(),
            'debug' => ['error' => $e->getMessage()]
        ]);
        exit;
    }

    // Se tivermos dados de isócrona, usa-os para cálculo preciso de área
    if ($isochroneJson) {
        try {
            // Analisa o GeoJSON
            $isochrone = json_decode($isochroneJson, true);
            $debug_info['isochrone_parsed'] = true;
            
            // Extrai a geometria e propriedades da primeira feature
            if (isset($isochrone['features']) && isset($isochrone['features'][0])) {
                // Obtém a área diretamente das propriedades da isócrona, se disponível
                if (isset($isochrone['features'][0]['properties']) && 
                    isset($isochrone['features'][0]['properties']['area'])) {
                    $areaKm2 = floatval($isochrone['features'][0]['properties']['area']);
                    $debug_info['area_from_isochrone'] = $areaKm2;
                }
                
                // Obtém a geometria
                if (isset($isochrone['features'][0]['geometry'])) {
                    $geometry = json_encode($isochrone['features'][0]['geometry']);
                    $bufferGeometry = $geometry;
                    $debug_info['geometry_extracted'] = true;
                    
                    // Cria condição espacial para contagem de POIs
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
                    
                    // Se não obtivemos a área das propriedades, calcula-a
                    if ($areaKm2 === null) {
                        $areaQuery = "
                            SELECT 
                                ST_Area(
                                    ST_Transform(
                                        ST_SetSRID(
                                            ST_GeomFromGeoJSON('$geometry'),
                                            4326
                                        ),
                                        3857
                                    )
                                ) / 1000000 as area_km2
                        ";
                        
                        try {
                            $areaResult = executeQuery($conn, $areaQuery);
                            if ($areaResult && $areaRow = pg_fetch_assoc($areaResult)) {
                                $areaKm2 = floatval($areaRow['area_km2']);
                                $debug_info['area_calculated'] = $areaKm2;
                            }
                        } catch (Exception $e) {
                            $debug_info['area_calculation_error'] = $e->getMessage();
                            // Continua com cálculo de área de fallback
                            $areaKm2 = pi() * pow($radius / 1000, 2);
                            $debug_info['area_fallback'] = $areaKm2;
                        }
                    }
                } else {
                    throw new Exception("Geometria em falta no GeoJSON da isócrona");
                }
            } else {
                throw new Exception("Estrutura GeoJSON da isócrona inválida");
            }
        } catch (Exception $e) {
            $debug_info['isochrone_error'] = $e->getMessage();
            
            // Se houver um erro com a isócrona, volta para o buffer do raio
            if ($radius > 0) {
                $debug_info['using_fallback_buffer'] = true;
                useBufferFallback($conn, $lat, $lng, $radius, $spatialCondition, $bufferGeometry, $areaKm2, $debug_info);
            } else {
                echo json_encode([
                    'success' => true,
                    'stats' => createFallbackStats($lat, $lng, $radius),
                    'message' => 'A usar estatísticas de fallback (o processamento da isócrona falhou)',
                    'debug' => $debug_info
                ]);
                exit;
            }
        }
    } else if ($radius > 0) {
        // Se nenhuma isócrona for fornecida mas tivermos raio, usa um buffer simples
        $debug_info['using_buffer'] = true;
        useBufferFallback($conn, $lat, $lng, $radius, $spatialCondition, $bufferGeometry, $areaKm2, $debug_info);
    } else {
        // Isso não deveria acontecer devido à validação anterior, mas por precaução
        echo json_encode([
            'success' => false,
            'message' => 'Nenhum método de filtragem espacial disponível'
        ]);
        exit;
    }

    // Define todas as categorias de POI a contar
    $poiCategories = [
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
        'supermarkets' => "shop IN ('supermarket', 'grocery', 'convenience')",
        'malls' => "shop = 'mall' OR amenity = 'marketplace'",
        'restaurants' => "amenity IN ('restaurant', 'cafe', 'fast_food')",
        'atms' => "amenity = 'atm' OR amenity = 'bank'",
        
        // === Transporte ===
        'bus_stops' => "highway = 'bus_stop'",
        'train_stations' => "railway = 'station' OR railway = 'halt'",
        'subway_stations' => "railway = 'subway_entrance' OR railway = 'station' AND station = 'subway'",
        'parking' => "amenity = 'parking'",
        
        // === Segurança ===
        'police' => "amenity = 'police'",
        'police_stations' => "amenity = 'police'",
        'fire_stations' => "amenity = 'fire_station'",
        'civil_protection' => "office = 'government' OR amenity IN ('public_building', 'social_facility', 'rescue_station', 'ambulance_station', 'emergency_service')",
        
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
        'museums' => "tourism = 'museum' OR amenity = 'arts_centre'",
        'theaters' => "amenity = 'theatre'",
        'sports' => "leisure IN ('sports_centre', 'stadium', 'pitch', 'swimming_pool', 'fitness_centre', 'fitness_station')",
        'parks' => "leisure IN ('park', 'garden', 'playground')"
    ];

    // Filtra para contar apenas os tipos de POI selecionados, se especificado
    if ($selectedPOIs !== null && is_array($selectedPOIs)) {
        $filteredCategories = [];
        foreach ($selectedPOIs as $poiType) {
            if (isset($poiCategories[$poiType])) {
                $filteredCategories[$poiType] = $poiCategories[$poiType];
            }
        }
        
        // Se houver tipos selecionados válidos, usa-os em vez disso
        if (!empty($filteredCategories)) {
            $poiCategories = $filteredCategories;
            $debug_info['using_filtered_pois'] = true;
        }
    }

    // Inicializa o array de estatísticas com informações de área
    $statistics = [
        'area_km2' => (float) $areaKm2
    ];

    // Conta cada categoria de POI dentro da área definida - combina pontos e polígonos
    foreach ($poiCategories as $category => $condition) {
        try {
            // Verifica se a condição é um array (formato avançado) ou uma string (formato simples)
            $conditionSql = is_array($condition) ? $condition['condition'] : $condition;
            
            // Consulta que conta pontos e polígonos
            $countQuery = "
                SELECT 
                    (
                        SELECT COUNT(*) FROM planet_osm_point 
                        WHERE ($conditionSql) AND $spatialCondition
                    ) +
                    (
                        SELECT COUNT(*) FROM planet_osm_polygon 
                        WHERE ($conditionSql) AND $spatialCondition
                    ) as count
            ";
            
            try {
                $countResult = executeQuery($conn, $countQuery);
                
                if (!$countResult) {
                    $statistics[$category] = 0;
                    $debug_info['query_error_' . $category] = pg_last_error($conn);
                } else {
                    $countRow = pg_fetch_assoc($countResult);
                    $statistics[$category] = (int) $countRow['count'];
                }
            } catch (Exception $e) {
                $statistics[$category] = 0;
                $debug_info['query_exception_' . $category] = $e->getMessage();
            }
        } catch (Exception $e) {
            $statistics[$category] = 0;
            $debug_info['exception_' . $category] = $e->getMessage();
        }
    }

    // Conta edifícios para estimativa de população
    try {
        $buildingQuery = "
            SELECT 
                COUNT(*) as count 
            FROM 
                planet_osm_polygon 
            WHERE 
                building IS NOT NULL 
                AND building != 'no' 
                AND $spatialCondition
        ";

        try {
            $buildingResult = executeQuery($conn, $buildingQuery);
            $buildingCount = 0;

            if ($buildingResult && $buildingRow = pg_fetch_assoc($buildingResult)) {
                $buildingCount = (int) $buildingRow['count'];
                // Estimativa aproximada de população: ~2.5 pessoas por edifício
                $statistics['population_estimate'] = round($buildingCount * 2.5);
                $debug_info['building_count'] = $buildingCount;
            }
        } catch (Exception $e) {
            $debug_info['building_count_error'] = $e->getMessage();
        }
    } catch (Exception $e) {
        $debug_info['building_query_error'] = $e->getMessage();
    }

    // Obtém informações de área administrativa (freguesia e município)
    try {
        $adminQuery = "
            SELECT 
                name, admin_level
            FROM 
                planet_osm_polygon 
            WHERE 
                admin_level IN ('8', '9', '10') 
                AND ST_Contains(
                    way,
                    ST_Transform(ST_SetSRID(ST_MakePoint($lng, $lat), 4326), 3857)
                )
            ORDER BY 
                admin_level DESC
        ";

        try {
            $adminResult = executeQuery($conn, $adminQuery);

            if (!$adminResult) {
                $statistics['parish'] = 'Desconhecido';
                $statistics['municipality'] = 'Desconhecido';
                $debug_info['admin_error'] = pg_last_error($conn);
            } else {
                $statistics['parish'] = 'Desconhecido';
                $statistics['municipality'] = 'Desconhecido';
                
                while ($adminRow = pg_fetch_assoc($adminResult)) {
                    if ($adminRow['admin_level'] === '10' || $adminRow['admin_level'] === '9') {
                        $statistics['parish'] = $adminRow['name'];
                    } else if ($adminRow['admin_level'] === '8') {
                        $statistics['municipality'] = $adminRow['name'];
                    }
                }
            }
        } catch (Exception $e) {
            $statistics['parish'] = 'Desconhecido';
            $statistics['municipality'] = 'Desconhecido';
            $debug_info['admin_query_error'] = $e->getMessage();
        }
    } catch (Exception $e) {
        $statistics['parish'] = 'Desconhecido';
        $statistics['municipality'] = 'Desconhecido';
        $debug_info['admin_error'] = $e->getMessage();
    }

    // Obtém informações da freguesia de GeoAPI.pt
    try {
        $geoApiData = fetchFreguesiaDemographics($lat, $lng);

        // Adiciona informações da freguesia às estatísticas, se disponível
        if ($geoApiData !== null) {
            $statistics['freguesia'] = $geoApiData['freguesia'] ?? 'Desconhecido';
            $statistics['concelho'] = $geoApiData['concelho'] ?? 'Desconhecido';
            $statistics['distrito'] = $geoApiData['distrito'] ?? 'Desconhecido';
            
            // Adiciona informações demográficas, se disponível
            if (isset($geoApiData['demographics'])) {
                $statistics['demographics'] = $geoApiData['demographics'];
            }
        }
    } catch (Exception $e) {
        $debug_info['geoapi_error'] = $e->getMessage();
    }

    // Retorna as estatísticas
    echo json_encode([
        'success' => true,
        'stats' => $statistics,
        'debug' => $debug_info
    ]);

    // Fecha a ligação à base de dados
    pg_close($conn);

} catch (Exception $e) {
    // Captura quaisquer exceções não tratadas
    error_log('Exceção não tratada em fetch_statistics.php: ' . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Ocorreu um erro inesperado: ' . $e->getMessage()
    ]);
}

/**
 * Fallback para um buffer simples se a isócrona não estiver disponível
 */
function useBufferFallback($conn, $lat, $lng, $radius, &$spatialCondition, &$bufferGeometry, &$areaKm2, &$debug_info) {
    try {
        // Cria um polígono de buffer em torno do ponto
        $bufferQuery = "
            SELECT 
                ST_AsGeoJSON(
                    ST_Buffer(
                        ST_Transform(ST_SetSRID(ST_MakePoint($lng, $lat), 4326), 3857), 
                        $radius
                    )
                ) as buffer_geom,
                ST_Area(
                    ST_Buffer(
                        ST_Transform(ST_SetSRID(ST_MakePoint($lng, $lat), 4326), 3857), 
                        $radius
                    )
                ) / 1000000 as area_km2
        ";

        // Executa a consulta do buffer
        try {
            $bufferResult = executeQuery($conn, $bufferQuery);

            if (!$bufferResult) {
                throw new Exception('Geração do buffer falhou: ' . pg_last_error($conn));
            }

            $bufferData = pg_fetch_assoc($bufferResult);
            $bufferGeometry = $bufferData['buffer_geom'];
            $areaKm2 = $bufferData['area_km2'];
            
            // Define a condição espacial usando o buffer
            $spatialCondition = "ST_DWithin(
                way, 
                ST_Transform(ST_SetSRID(ST_MakePoint($lng, $lat), 4326), 3857), 
                $radius
            )";
            
            $debug_info['buffer_area'] = $areaKm2;
        } catch (Exception $e) {
            // Se a execução da consulta falhar, usa um fallback simples
            $debug_info['buffer_query_error'] = $e->getMessage();
            
            // Fallback simples para a condição espacial
            $spatialCondition = "ST_DWithin(
                way, 
                ST_Transform(ST_SetSRID(ST_MakePoint($lng, $lat), 4326), 3857), 
                $radius
            )";
            
            // Cálculo de área simples
            $areaKm2 = pi() * pow($radius / 1000, 2);
            $debug_info['buffer_area_fallback'] = $areaKm2;
        }
    } catch (Exception $e) {
        throw $e;
    }
}

/**
 * Obtém informações da freguesia e dados demográficos com base nas coordenadas
 */
function fetchFreguesiaDemographics($lat, $lng) {
    try {
        // Cria o URL do endpoint para geocodificação inversa
        $endpoint = "gps/{$lat},{$lng}";
        
        // Usa o nosso proxy com cache
        $proxyUrl = "../includes/geoapi_proxy.php?endpoint=" . urlencode($endpoint);
        
        // Inicializa cURL
        $ch = curl_init();
        
        // Define as opções cURL
        curl_setopt($ch, CURLOPT_URL, $proxyUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        
        // Executa o pedido
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        
        // Verifica erros
        if (curl_errno($ch) || $httpCode !== 200) {
            curl_close($ch);
            return null;
        }
        
        // Fecha cURL
        curl_close($ch);
        
        // Analisa a resposta
        $data = json_decode($response, true);
        
        if (!$data || !isset($data['freguesia'])) {
            return null;
        }
        
        // Obtém os dados da freguesia
        $freguesia = $data['freguesia'];
        $freguesiaCode = $freguesia['codigo'] ?? null;
        $municipioName = $data['municipio']['nome'] ?? null;
        
        if (!$freguesiaCode || !$municipioName) {
            return null;
        }
        
        // Obtém dados demográficos detalhados para a freguesia
        $demographics = fetchDemographicData($freguesiaCode, $municipioName);
        
        if (!$demographics) {
            return [
                'freguesia' => $freguesia['nome'],
                'concelho' => $data['municipio']['nome'] ?? null,
                'distrito' => $data['distrito']['nome'] ?? null,
                'demographics' => null
            ];
        }
        
        return [
            'freguesia' => $freguesia['nome'],
            'concelho' => $data['municipio']['nome'] ?? null,
            'distrito' => $data['distrito']['nome'] ?? null,
            'demographics' => $demographics
        ];
    } catch (Exception $e) {
        error_log('Erro em fetchFreguesiaDemographics: ' . $e->getMessage());
        return null;
    }
}

/**
 * Obtém dados demográficos para uma freguesia por código
 */
function fetchDemographicData($freguesiaCode, $municipioName) {
    try {
        // Cria o URL do endpoint para dados demográficos da freguesia
        $endpoint = "municipio/" . urlencode($municipioName) . "/freguesia/" . urlencode($freguesiaCode) . "/censos";
        
        // Usa o nosso proxy com cache
        $proxyUrl = "../includes/geoapi_proxy.php?endpoint=" . urlencode($endpoint);
        
        // Inicializa cURL
        $ch = curl_init();
        
        // Define as opções cURL
        curl_setopt($ch, CURLOPT_URL, $proxyUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        
        // Executa o pedido
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        
        // Verifica erros
        if (curl_errno($ch) || $httpCode !== 200) {
            curl_close($ch);
            return null;
        }
        
        // Fecha cURL
        curl_close($ch);
        
        // Analisa a resposta
        $data = json_decode($response, true);
        
        if (!$data) {
            return null;
        }
        
        return $data;
    } catch (Exception $e) {
        error_log('Erro em fetchDemographicData: ' . $e->getMessage());
        return null;
    }
}

// Cria estatísticas de fallback quando as consultas à base de dados falham
function createFallbackStats($lat, $lng, $radius) {
    // Calcula a área aproximada com base no raio
    $areaKm2 = $radius > 0 ? pi() * pow($radius / 1000, 2) : 1.0;
    
    // Gera população com base na área (assumindo densidade urbana)
    $populationDensity = rand(1000, 5000); // pessoas por km²
    $populationEstimate = round($areaKm2 * $populationDensity);
    
    // Estatísticas base
    return [
        'area_km2' => round($areaKm2, 2),
        'population_estimate' => $populationEstimate,
        'parish' => 'Desconhecido',
        'municipality' => 'Desconhecido',
        'is_fallback' => true
    ];
}
?>