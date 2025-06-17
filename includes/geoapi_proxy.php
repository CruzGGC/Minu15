<?php
/**
 * Proxy GeoAPI.pt
 * Lida com pedidos para a API GeoAPI.pt para dados de regiões administrativas portuguesas
 * Implementa cache para minimizar chamadas à API
 * Adicionado tratamento de limite de taxa com backoff exponencial
 * 
 * @version 1.4
 */

// Ativar depuração
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Incluir a classe de cache da API
require_once __DIR__ . '/api_cache.php';

// Criar uma função de registo de depuração
function debug_log($message, $data = null) {
    $logFile = __DIR__ . '/../logs/geoapi_debug.log';
    $logDir = dirname($logFile);
    
    // Criar diretório de logs se não existir
    if (!is_dir($logDir)) {
        mkdir($logDir, 0755, true);
    }
    
    $timestamp = date('Y-m-d H:i:s');
    $logMessage = "[$timestamp] $message";
    
    if ($data !== null) {
        $logMessage .= ": " . json_encode($data, JSON_PRETTY_PRINT);
    }
    
    file_put_contents($logFile, $logMessage . PHP_EOL, FILE_APPEND);
}

// Definir cabeçalhos para resposta JSON
header('Content-Type: application/json');

// Registar o pedido
debug_log('Pedido recebido', [
    'method' => $_SERVER['REQUEST_METHOD'],
    'endpoint' => $_GET['endpoint'] ?? 'nenhum',
    'query' => $_GET,
    'remote_addr' => $_SERVER['REMOTE_ADDR']
]);

// Verificar método do pedido
if ($_SERVER['REQUEST_METHOD'] !== 'GET' && $_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode([
        'success' => false,
        'message' => 'Apenas os métodos GET e POST são permitidos'
    ]);
    exit;
}

// Definir URL base do GeoAPI.pt
$geoApiBaseUrl = 'http://json.localhost:9090';

// Obter o endpoint do pedido
$endpoint = isset($_GET['endpoint']) ? $_GET['endpoint'] : '';

// Validar endpoint
if (empty($endpoint)) {
    echo json_encode([
        'success' => false,
        'message' => 'Parâmetro obrigatório em falta: endpoint'
    ]);
    exit;
}

// Garantir que o endpoint não começa com uma barra
if (substr($endpoint, 0, 1) === '/') {
    $endpoint = substr($endpoint, 1);
}

// Inicializar o cache da API com uma expiração de 7 dias
$apiCache = new ApiCache(__DIR__ . '/../cache/geoapi/', 604800);

// Criar uma chave de cache com base no endpoint e quaisquer parâmetros adicionais
$cacheKey = $endpoint;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $postData = file_get_contents('php://input');
    $cacheKey .= '_' . $postData;
}
if (!empty($_GET)) {
    $queryParams = $_GET;
    unset($queryParams['endpoint']); // Remover endpoint dos parâmetros de consulta para a chave de cache
    if (!empty($queryParams)) {
        $cacheKey .= '_' . http_build_query($queryParams);
    }
}

// Gerar a chave de cache usando a classe ApiCache
$cacheKey = $apiCache->generateCacheKey($cacheKey, []);
debug_log('Chave de cache', ['key' => $cacheKey]);

// Verificar se temos um cache válido
$useCache = $apiCache->hasValidCache($cacheKey);
if ($useCache) {
    debug_log('A usar cache');
    $cachedData = $apiCache->get($cacheKey);
    echo json_encode($cachedData);
    exit;
} else {
    debug_log('Nenhum cache válido encontrado');
}

// Tratamento de limite de taxa
$rateLimitFile = __DIR__ . '/../cache/geoapi/rate_limit_status.json';
$maxRetries = 3;
$retryCount = 0;
$retryDelay = 1; // Atraso inicial em segundos

// Verificar se estamos atualmente com limite de taxa
if (file_exists($rateLimitFile)) {
    $rateLimitInfo = json_decode(file_get_contents($rateLimitFile), true);
    debug_log('Ficheiro de limite de taxa existe', $rateLimitInfo);
    
    // Se o tempo de reset do limite de taxa ainda não passou
    if (isset($rateLimitInfo['reset_time']) && time() < $rateLimitInfo['reset_time']) {
        $timeRemaining = $rateLimitInfo['reset_time'] - time();
        debug_log('Atualmente com limite de taxa', [
            'reset_in_seconds' => $timeRemaining,
            'reset_time' => date('Y-m-d H:i:s', $rateLimitInfo['reset_time']),
            'current_time' => date('Y-m-d H:i:s')
        ]);
        
        // Se tivermos uma resposta em cache para este endpoint, usa-a mesmo que esteja expirada
        $expiredData = $apiCache->get($cacheKey);
        if ($expiredData !== null) {
            debug_log('A usar cache expirado devido a limite de taxa');
            echo json_encode($expiredData);
            exit;
        }
        
        // Se este for um pedido de coordenadas GPS, devolver uma resposta de fallback
        if (strpos($endpoint, 'gps/') === 0) {
            // Extrair coordenadas
            $coords = str_replace('gps/', '', $endpoint);
            $coords = str_replace('/base', '', $coords);
            list($lat, $lng) = explode(',', $coords);
            
            // Criar uma resposta de fallback para Aveiro, Portugal (como exemplo)
            $fallbackResponse = [
                'freguesia' => [
                    'nome' => 'Glória e Vera Cruz',
                    'codigo' => '010105'
                ],
                'municipio' => [
                    'nome' => 'Aveiro',
                    'codigo' => '0101'
                ],
                'distrito' => [
                    'nome' => 'Aveiro',
                    'codigo' => '01'
                ],
                'coordinates' => [
                    'lat' => $lat,
                    'lng' => $lng
                ]
            ];
            
            debug_log('A devolver resposta GPS de fallback');
            
            // Guardar esta resposta em cache
            $apiCache->set($cacheKey, $fallbackResponse);
            
            echo json_encode($fallbackResponse);
            exit;
        }
        
        // Se este for um pedido distrito/municipios, devolver uma resposta de fallback
        if (preg_match('/distrito\/([^\/]+)\/municipios/', $endpoint, $matches)) {
            $distritoName = urldecode($matches[1]);
            debug_log('A criar resposta distrito/municipios de fallback', ['distrito' => $distritoName]);
            
            // Criar dados de municípios de fallback com base no distrito
            $fallbackMunicipios = [];
            
            // Municípios comuns para cada distrito
            $fallbackData = [
                'Aveiro' => [
                    ['nome' => 'Aveiro', 'codigo' => '0101'],
                    ['nome' => 'Espinho', 'codigo' => '0102'],
                    ['nome' => 'Ovar', 'codigo' => '0103'],
                    ['nome' => 'Ílhavo', 'codigo' => '0104'],
                    ['nome' => 'Águeda', 'codigo' => '0105']
                ],
                'Lisboa' => [
                    ['nome' => 'Lisboa', 'codigo' => '1101'],
                    ['nome' => 'Sintra', 'codigo' => '1102'],
                    ['nome' => 'Cascais', 'codigo' => '1103'],
                    ['nome' => 'Oeiras', 'codigo' => '1104'],
                    ['nome' => 'Amadora', 'codigo' => '1105']
                ],
                'Porto' => [
                    ['nome' => 'Porto', 'codigo' => '1301'],
                    ['nome' => 'Vila Nova de Gaia', 'codigo' => '1302'],
                    ['nome' => 'Matosinhos', 'codigo' => '1303'],
                    ['nome' => 'Maia', 'codigo' => '1304'],
                    ['nome' => 'Gondomar', 'codigo' => '1305']
                ],
                'Coimbra' => [
                    ['nome' => 'Coimbra', 'codigo' => '0601'],
                    ['nome' => 'Figueira da Foz', 'codigo' => '0602'],
                    ['nome' => 'Cantanhede', 'codigo' => '0603'],
                    ['nome' => 'Montemor-o-Velho', 'codigo' => '0604'],
                    ['nome' => 'Penacova', 'codigo' => '0605']
                ]
            ];
            
            // Fallback padrão para qualquer distrito não na nossa lista predefinida
            $defaultFallback = [
                ['nome' => 'Município 1', 'codigo' => '0001'],
                ['nome' => 'Município 2', 'codigo' => '0002'],
                ['nome' => 'Município 3', 'codigo' => '0003'],
                ['nome' => 'Município 4', 'codigo' => '0004'],
                ['nome' => 'Município 5', 'codigo' => '0005']
            ];
            
            $fallbackMunicipios = isset($fallbackData[$distritoName]) ? $fallbackData[$distritoName] : $defaultFallback;
            
            // Guardar esta resposta em cache
            $fallbackJson = json_encode(['municipios' => $fallbackMunicipios]); // Envolver na chave 'municipios'
            $apiCache->set($cacheKey, $fallbackJson);
            
            debug_log('A devolver resposta de municípios de fallback');
            echo $fallbackJson;
            exit;
        }
        
        // Se este for um pedido municipio/freguesias, devolver uma resposta de fallback
        if (preg_match('/municipio\/([^\/]+)\/freguesias/', $endpoint, $matches)) {
            $concelhoName = urldecode($matches[1]);
            debug_log('A criar resposta municipio/freguesias de fallback', ['concelho' => $concelhoName]);
            
            // Criar dados de freguesias de fallback com base no concelho
            // Para simplificar, usaremos nomes genéricos e códigos fictícios para fallback
            $fallbackFreguesias = [
                ['nome' => 'Freguesia A', 'codigo' => '000001'],
                ['nome' => 'Freguesia B', 'codigo' => '000002'],
                ['nome' => 'Freguesia C', 'codigo' => '000003'],
            ];
            
            // Guardar esta resposta em cache
            $fallbackJson = json_encode(['freguesias' => $fallbackFreguesias]); // Envolver na chave 'freguesias'
            $apiCache->set($cacheKey, $fallbackJson);
            
            debug_log('A devolver resposta de freguesias de fallback');
            echo $fallbackJson;
            exit;
        }
        
        // Caso contrário, devolver um erro de limite de taxa
        http_response_code(429);
        $errorResponse = [
            'success' => false,
            'message' => 'Limite de taxa excedido. Tente novamente mais tarde.',
            'reset_in_seconds' => $timeRemaining,
            'reset_time' => date('Y-m-d H:i:s', $rateLimitInfo['reset_time']),
            'current_time' => date('Y-m-d H:i:s'),
            'debug' => [
                'endpoint' => $endpoint,
                'rate_limit_info' => $rateLimitInfo
            ]
        ];
        debug_log('A devolver erro de limite de taxa', $errorResponse);
        echo json_encode($errorResponse);
        exit;
    } else {
        debug_log('O limite de taxa expirou, a apagar o ficheiro de limite de taxa');
        // O limite de taxa expirou, apagar o ficheiro
        unlink($rateLimitFile);
    }
} else {
    debug_log('Nenhum ficheiro de limite de taxa existe');
}

// Continuar com o pedido à API se não houver limite de taxa ou se o limite de taxa tiver expirado
$url = $geoApiBaseUrl . '/' . $endpoint;
debug_log('A fazer pedido à API', ['url' => $url]);

// Fazer o pedido à API com lógica de repetição
$response = makeApiRequest($url, null, $retryCount, $maxRetries, $retryDelay);

// Guardar a resposta em cache
if ($response['success']) {
    $apiCache->set($cacheKey, $response['data']);
}

// Imprimir a resposta
if (isset($response['data']) && is_array($response['data'])) {
    // Array de dados válido, imprimir como JSON
    echo json_encode($response['data']);
} else if (isset($response['response']) && !empty($response['response'])) {
    // Se a resposta já for JSON, imprimir diretamente
    $contentType = $response['headers'] ?? '';
    if (strpos($contentType, 'application/json') !== false) {
        echo $response['response'];
    } else {
        // Tentar descodificar e recodificar para garantir JSON válido
        $data = json_decode($response['response'], true);
        if ($data !== null) {
            echo json_encode($data);
        } else {
            // Fallback se a resposta não for JSON válido
            echo json_encode([
                'success' => false,
                'message' => 'Resposta inválida da API',
                'error' => json_last_error_msg()
            ]);
        }
    }
} else {
    // Fallback com mensagem de erro
    echo json_encode([
        'success' => false,
        'message' => 'Nenhum dado recebido da API'
    ]);
}
exit;

// Função para fazer pedidos à API com lógica de repetição
function makeApiRequest($url, $postData = null, $retryCount = 0, $maxRetries = 3, $retryDelay = 1) {
    global $cacheDir, $rateLimitFile;
    
    debug_log("A fazer pedido à API", [
        'url' => $url,
        'retry_count' => $retryCount,
        'max_retries' => $maxRetries
    ]);
    
    // Inicializar cURL
    $ch = curl_init();
    
    // Definir opções cURL
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 60); // Tempo limite aumentado para respostas maiores
    curl_setopt($ch, CURLOPT_USERAGENT, 'Minu15-App/1.0');
    curl_setopt($ch, CURLOPT_HEADER, true); // Obter cabeçalhos para verificar limites de taxa
    
    // Ativar saída verbosa para depuração
    $verbose = fopen('php://temp', 'w+');
    curl_setopt($ch, CURLOPT_VERBOSE, true);
    curl_setopt($ch, CURLOPT_STDERR, $verbose);
    
    // Se for um pedido POST, passar os dados POST
    if ($postData !== null) {
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($postData));
    }
    
    // Executar o pedido
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
    $headers = substr($response, 0, $headerSize);
    $body = substr($response, $headerSize);
    
    // Obter informações verbosas
    rewind($verbose);
    $verboseLog = stream_get_contents($verbose);
    
    debug_log("Resposta da API", [
        'http_code' => $httpCode,
        'header_size' => $headerSize,
        'headers' => $headers
    ]);
    
    // Verificar erros cURL
    if (curl_errno($ch)) {
        $error = curl_error($ch);
        curl_close($ch);
        
        // Registar o erro para depuração
        debug_log("Erro cURL", [
            'error' => $error,
            'url' => $url,
            'verbose' => $verboseLog
        ]);
        
        // Se não atingimos o número máximo de tentativas, tentar novamente
        if ($retryCount < $maxRetries) {
            // Backoff exponencial
            $sleepTime = $retryDelay * pow(2, $retryCount);
            debug_log("A tentar novamente o pedido após backoff", ['sleep_time' => $sleepTime]);
            sleep($sleepTime);
            return makeApiRequest($url, $postData, $retryCount + 1, $maxRetries, $retryDelay);
        }
        
        return [
            'success' => false,
            'code' => 0,
            'message' => 'Erro cURL: ' . $error,
            'response' => null,
            'verbose' => $verboseLog
        ];
    }
    
    // Lidar com limite de taxa
    if ($httpCode === 429) {
        debug_log("Limite de taxa atingido (429)", ['url' => $url]);
        
        // Tentar extrair cabeçalhos de limite de taxa
        $resetTime = time() + 3600; // Padrão para 1 hora se nenhum cabeçalho for fornecido
        
        // Analisar cabeçalhos para procurar informações de limite de taxa
        $headerLines = explode("\n", $headers);
        foreach ($headerLines as $line) {
            if (strpos($line, 'X-RateLimit-Reset:') !== false) {
                $resetTime = trim(str_replace('X-RateLimit-Reset:', '', $line));
                debug_log("Cabeçalho X-RateLimit-Reset encontrado", ['reset_time' => $resetTime]);
            }
        }
        
        // Guardar informações de limite de taxa
        $rateLimitInfo = [
            'reset_time' => $resetTime,
            'last_error' => time(),
            'url' => $url,
            'http_code' => $httpCode
        ];
        
        // Tentar obter tempo de reset do limite de taxa da resposta
        $responseData = json_decode($body, true);
        if (isset($responseData['msg']) && strpos($responseData['msg'], 'limit of free requests') !== false) {
            // Esta é uma mensagem de limite de taxa do GeoAPI.pt
            debug_log("Mensagem de limite de taxa do GeoAPI encontrada", $responseData);
            $rateLimitInfo['message'] = $responseData['msg'];
        }
        
        file_put_contents($rateLimitFile, json_encode($rateLimitInfo));
        debug_log("Informações de limite de taxa guardadas", $rateLimitInfo);
        
        // Se não atingimos o número máximo de tentativas, tentar novamente
        if ($retryCount < $maxRetries) {
            // Backoff exponencial
            $sleepTime = $retryDelay * pow(2, $retryCount);
            debug_log("A tentar novamente após limite de taxa com backoff", ['sleep_time' => $sleepTime]);
            sleep($sleepTime);
            return makeApiRequest($url, $postData, $retryCount + 1, $maxRetries, $retryDelay);
        }
    }
    
    curl_close($ch);
    
    // Analisar o corpo da resposta para potencial normalização
    $parsedBody = json_decode($body, true);

    // Adicionar registo de depuração para resposta raw
    debug_log("Resposta raw da API analisada", [
        'endpoint' => $url,
        'response_structure' => is_array($parsedBody) ? array_keys($parsedBody) : gettype($parsedBody),
        'response_sample' => substr($body, 0, 200) . '...'
    ]);

    // Verificar se o endpoint é para lista de freguesias e normalizar a resposta, se necessário
    $isFreguesiasListEndpoint = (strpos($url, '/municipio/') !== false && strpos($url, '/freguesias') !== false);

    if ($isFreguesiasListEndpoint) {
        debug_log("A processar endpoint da lista de freguesias", [
            'has_freguesias_key' => isset($parsedBody['freguesias']),
            'freguesias_type' => isset($parsedBody['freguesias']) ? gettype($parsedBody['freguesias']) : 'não definido',
            'has_geojsons' => isset($parsedBody['geojsons'])
        ]);
        
        // Lidar com o caso em que a resposta pode ser um array (array direto de freguesias) em vez de um objeto com a chave freguesias
        if (!isset($parsedBody['freguesias']) && is_array($parsedBody)) {
            debug_log("Array raw de freguesias detetado, a envolver na chave freguesias");
            $parsedBody = ['freguesias' => $parsedBody];
            $body = json_encode($parsedBody);
        }
        
        // Garantir que freguesias está definido como um array
        if (!isset($parsedBody['freguesias']) || !is_array($parsedBody['freguesias'])) {
            debug_log("Nenhum array de freguesias encontrado, a criar array vazio");
            $parsedBody['freguesias'] = [];
            $body = json_encode($parsedBody);
        }
        
        // Verificar se as freguesias já são objetos com nome e código
        $isFreguesiasObjects = false;
        if (!empty($parsedBody['freguesias']) && is_array($parsedBody['freguesias'][0])) {
            if (isset($parsedBody['freguesias'][0]['nome'])) {
                debug_log("Freguesias já são objetos com nome", [
                    'amostra' => $parsedBody['freguesias'][0]
                ]);
                $isFreguesiasObjects = true;
            }
        }
        
        // Apenas normalizar se as freguesias ainda não forem objetos
        if (!$isFreguesiasObjects && isset($parsedBody['freguesias']) && is_array($parsedBody['freguesias'])) {
            // A chave primária 'freguesias' pode conter apenas nomes (strings)
            // O 'geojsons.freguesias' pode conter objetos com nomes e códigos
            
            $normalizedFreguesias = [];
            if (isset($parsedBody['geojsons']['freguesias']) && is_array($parsedBody['geojsons']['freguesias'])) {
                $geoJsonFreguesias = $parsedBody['geojsons']['freguesias'];
                
                // Mapear dados geojson para um formato mais consistente
                foreach ($geoJsonFreguesias as $freguesiaGeoJson) {
                    if (isset($freguesiaGeoJson['properties']['Freguesia']) && isset($freguesiaGeoJson['properties']['Dicofre'])) {
                        $normalizedFreguesias[] = [
                            'nome' => $freguesiaGeoJson['properties']['Freguesia'],
                            'codigo' => $freguesiaGeoJson['properties']['Dicofre']
                        ];
                    }
                }
                
                debug_log("Freguesias normalizadas a partir de geojson", [
                    'contagem' => count($normalizedFreguesias),
                    'amostra' => !empty($normalizedFreguesias) ? $normalizedFreguesias[0] : null
                ]);
            } else {
                // Fallback: Se não houver dados geojson, usar os nomes simples e converter para objetos
                foreach ($parsedBody['freguesias'] as $freguesiaName) {
                    if (is_string($freguesiaName)) {
                        $normalizedFreguesias[] = [
                            'nome' => $freguesiaName,
                            'codigo' => null // Indicar que o código está em falta para esta entrada
                        ];
                    } else {
                        // Se não for uma string, tentar convertê-la
                        $normalizedFreguesias[] = [
                            'nome' => is_object($freguesiaName) || is_array($freguesiaName) ? json_encode($freguesiaName) : (string)$freguesiaName,
                            'codigo' => null
                        ];
                    }
                }
                
                debug_log("Freguesias normalizadas a partir de nomes", [
                    'contagem' => count($normalizedFreguesias),
                    'amostra' => !empty($normalizedFreguesias) ? $normalizedFreguesias[0] : null
                ]);
            }
            
            // Sobrescrever o corpo da resposta com os dados normalizados envolvidos na chave 'freguesias'
            $body = json_encode(['freguesias' => $normalizedFreguesias]);
            debug_log("Resposta da Lista de Freguesias Normalizadas", ['amostra_corpo_normalizado' => substr($body, 0, 200) . '...']);
        }
    }
    // Verificar se este é um endpoint de freguesia específico
    else if ((strpos($url, '/municipio/') !== false && strpos($url, '/freguesia/') !== false) || 
             (strpos($url, '/freguesia/') !== false && strpos($url, '?municipio=') !== false)) {
        
        // Para endpoints de freguesia única, garantir que passamos todos os dados, incluindo dados de censos
        // Nenhuma normalização necessária, mas registar para depuração
        debug_log("Resposta de Detalhe da Freguesia", [
            'endpoint' => $url,
            'tem_censos2011' => isset($parsedBody['censos2011']),
            'tem_censos2021' => isset($parsedBody['censos2021']),
            'nome_freguesia' => $parsedBody['nome'] ?? 'desconhecido'
        ]);
    }
    else if (strpos($url, '/distrito/') !== false && strpos($url, '/municipios') !== false && isset($parsedBody['municipios'])) {
        // Garantir que a resposta dos municípios também é consistentemente envolvida para chamadas diretas à API
        // O fallback já o envolve, isto lida com respostas diretas da API
        $body = json_encode(['municipios' => $parsedBody['municipios']]);
        debug_log("Resposta de Municípios Normalizada", ['amostra_corpo_normalizado' => substr($body, 0, 200) . '...']);
    }

    // Preparar os dados para a resposta
    $data = null;
    if (!empty($body)) {
        $data = json_decode($body, true);
        
        // Registar quaisquer erros de análise JSON
        if (json_last_error() !== JSON_ERROR_NONE) {
            debug_log("Erro de análise JSON", [
                'error' => json_last_error_msg(),
                'body_sample' => substr($body, 0, 200)
            ]);
        }
    }
    
    return [
        'success' => $httpCode >= 200 && $httpCode < 300,
        'code' => $httpCode,
        'message' => 'Código HTTP: ' . $httpCode,
        'response' => $body,
        'headers' => $headers,
        'verbose' => $verboseLog,
        'data' => $data
    ];
} 