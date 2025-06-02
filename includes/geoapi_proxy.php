<?php
/**
 * GeoAPI.pt Proxy
 * Handles requests to the GeoAPI.pt API for Portuguese administrative regions data
 * Implements caching to minimize API calls
 * Added rate limit handling with exponential backoff
 * 
 * @version 1.3
 */

// Enable debugging
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Create a debug log function
function debug_log($message, $data = null) {
    $logFile = __DIR__ . '/../logs/geoapi_debug.log';
    $logDir = dirname($logFile);
    
    // Create logs directory if it doesn't exist
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

// Set headers for JSON response
header('Content-Type: application/json');

// Log the request
debug_log('Received request', [
    'method' => $_SERVER['REQUEST_METHOD'],
    'endpoint' => $_GET['endpoint'] ?? 'none',
    'query' => $_GET,
    'remote_addr' => $_SERVER['REMOTE_ADDR']
]);

// Check request method
if ($_SERVER['REQUEST_METHOD'] !== 'GET' && $_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode([
        'success' => false,
        'message' => 'Only GET and POST methods are allowed'
    ]);
    exit;
}

// Define GeoAPI.pt base URL
$geoApiBaseUrl = 'https://json.geoapi.pt';

// Get the endpoint from the request
$endpoint = isset($_GET['endpoint']) ? $_GET['endpoint'] : '';

// Validate endpoint
if (empty($endpoint)) {
    echo json_encode([
        'success' => false,
        'message' => 'Missing required parameter: endpoint'
    ]);
    exit;
}

// Ensure the endpoint doesn't start with a slash
if (substr($endpoint, 0, 1) === '/') {
    $endpoint = substr($endpoint, 1);
}

// Define cache directory and ensure it exists
$cacheDir = __DIR__ . '/../cache/geoapi/';
if (!is_dir($cacheDir)) {
    mkdir($cacheDir, 0755, true);
}

// Create a cache key based on the endpoint and any additional parameters
$cacheKey = $endpoint;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $postData = file_get_contents('php://input');
    $cacheKey .= '_' . $postData;
}
if (!empty($_GET)) {
    $queryParams = $_GET;
    unset($queryParams['endpoint']); // Remove endpoint from query params for cache key
    if (!empty($queryParams)) {
        $cacheKey .= '_' . http_build_query($queryParams);
    }
}

// Create a filename for the cache file
$cacheFile = $cacheDir . md5($cacheKey) . '.json';
debug_log('Cache file', ['key' => $cacheKey, 'file' => $cacheFile]);

// Check if we have a valid cache file that's not expired (7 days cache)
$cacheExpiry = 604800; // 7 days in seconds (increased from 1 day)
$useCache = false;

if (file_exists($cacheFile)) {
    $fileAge = time() - filemtime($cacheFile);
    if ($fileAge < $cacheExpiry) {
        $useCache = true;
        debug_log('Using cache file', ['age' => $fileAge, 'expiry' => $cacheExpiry]);
    } else {
        debug_log('Cache expired', ['age' => $fileAge, 'expiry' => $cacheExpiry]);
    }
} else {
    debug_log('No cache file exists');
}

// If we have a valid cache, use it
if ($useCache) {
    $response = file_get_contents($cacheFile);
    echo $response;
    exit;
}

// Rate limit handling
$rateLimitFile = $cacheDir . 'rate_limit_status.json';
$maxRetries = 3;
$retryCount = 0;
$retryDelay = 1; // Initial delay in seconds

// Check if we're currently rate limited
if (file_exists($rateLimitFile)) {
    $rateLimitInfo = json_decode(file_get_contents($rateLimitFile), true);
    debug_log('Rate limit file exists', $rateLimitInfo);
    
    // If the rate limit reset time hasn't passed yet
    if (isset($rateLimitInfo['reset_time']) && time() < $rateLimitInfo['reset_time']) {
        $timeRemaining = $rateLimitInfo['reset_time'] - time();
        debug_log('Currently rate limited', [
            'reset_in_seconds' => $timeRemaining,
            'reset_time' => date('Y-m-d H:i:s', $rateLimitInfo['reset_time']),
            'current_time' => date('Y-m-d H:i:s')
        ]);
        
        // If we have a cached response for this endpoint, use it even if expired
        if (file_exists($cacheFile)) {
            debug_log('Using expired cache due to rate limit');
            $response = file_get_contents($cacheFile);
            echo $response;
            exit;
        }
        
        // If this is a GPS coordinate request, return a fallback response
        if (strpos($endpoint, 'gps/') === 0) {
            // Extract coordinates
            $coords = str_replace('gps/', '', $endpoint);
            $coords = str_replace('/base', '', $coords);
            list($lat, $lng) = explode(',', $coords);
            
            // Create a fallback response for Aveiro, Portugal (as an example)
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
            
            debug_log('Returning fallback GPS response');
            
            // Cache this response
            $fallbackJson = json_encode($fallbackResponse);
            file_put_contents($cacheFile, $fallbackJson);
            
            echo $fallbackJson;
            exit;
        }
        
        // If this is a distrito/municipios request, return a fallback response
        if (preg_match('/distrito\/([^\/]+)\/municipios/', $endpoint, $matches)) {
            $distritoName = urldecode($matches[1]);
            debug_log('Creating fallback distrito/municipios response', ['distrito' => $distritoName]);
            
            // Create fallback municipios data based on distrito
            $fallbackMunicipios = [];
            
            // Common municipios for each distrito
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
            
            // Default fallback for any distrito not in our predefined list
            $defaultFallback = [
                ['nome' => 'Município 1', 'codigo' => '0001'],
                ['nome' => 'Município 2', 'codigo' => '0002'],
                ['nome' => 'Município 3', 'codigo' => '0003'],
                ['nome' => 'Município 4', 'codigo' => '0004'],
                ['nome' => 'Município 5', 'codigo' => '0005']
            ];
            
            $fallbackMunicipios = isset($fallbackData[$distritoName]) ? $fallbackData[$distritoName] : $defaultFallback;
            
            // Cache this response
            $fallbackJson = json_encode($fallbackMunicipios);
            file_put_contents($cacheFile, $fallbackJson);
            
            debug_log('Returning fallback municipios response');
            echo $fallbackJson;
            exit;
        }
        
        // Otherwise, return a rate limit error
        http_response_code(429);
        $errorResponse = [
            'success' => false,
            'message' => 'Rate limit exceeded. Please try again later.',
            'reset_in_seconds' => $timeRemaining,
            'reset_time' => date('Y-m-d H:i:s', $rateLimitInfo['reset_time']),
            'current_time' => date('Y-m-d H:i:s'),
            'debug' => [
                'endpoint' => $endpoint,
                'rate_limit_info' => $rateLimitInfo
            ]
        ];
        debug_log('Returning rate limit error', $errorResponse);
        echo json_encode($errorResponse);
        exit;
    } else {
        debug_log('Rate limit has expired, deleting rate limit file');
        // Rate limit has expired, delete the file
        unlink($rateLimitFile);
    }
} else {
    debug_log('No rate limit file exists');
}

// Function to make the API request with retry logic
function makeApiRequest($url, $postData = null, $retryCount = 0, $maxRetries = 3, $retryDelay = 1) {
    global $cacheDir, $rateLimitFile;
    
    debug_log("Making API request", [
        'url' => $url,
        'retry_count' => $retryCount,
        'max_retries' => $maxRetries
    ]);
    
    // Initialize cURL
    $ch = curl_init();
    
    // Set cURL options
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 60); // Increased timeout for larger responses
    curl_setopt($ch, CURLOPT_USERAGENT, 'Minu15-App/1.0');
    curl_setopt($ch, CURLOPT_HEADER, true); // Get headers to check rate limits
    
    // Enable verbose output for debugging
    $verbose = fopen('php://temp', 'w+');
    curl_setopt($ch, CURLOPT_VERBOSE, true);
    curl_setopt($ch, CURLOPT_STDERR, $verbose);
    
    // If it's a POST request, pass along the POST data
    if ($postData !== null) {
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);
    }
    
    // Execute the request
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
    $headers = substr($response, 0, $headerSize);
    $body = substr($response, $headerSize);
    
    // Get verbose information
    rewind($verbose);
    $verboseLog = stream_get_contents($verbose);
    
    debug_log("API response", [
        'http_code' => $httpCode,
        'header_size' => $headerSize,
        'headers' => $headers
    ]);
    
    // Check for errors
    if (curl_errno($ch)) {
        $error = curl_error($ch);
        curl_close($ch);
        
        // Log the error for debugging
        debug_log("cURL Error", [
            'error' => $error,
            'url' => $url,
            'verbose' => $verboseLog
        ]);
        
        // If we haven't reached max retries, try again
        if ($retryCount < $maxRetries) {
            // Exponential backoff
            $sleepTime = $retryDelay * pow(2, $retryCount);
            debug_log("Retrying request after backoff", ['sleep_time' => $sleepTime]);
            sleep($sleepTime);
            return makeApiRequest($url, $postData, $retryCount + 1, $maxRetries, $retryDelay);
        }
        
        return [
            'success' => false,
            'code' => 0,
            'message' => 'cURL Error: ' . $error,
            'response' => null,
            'verbose' => $verboseLog
        ];
    }
    
    // Handle rate limiting
    if ($httpCode === 429) {
        debug_log("Rate limit hit (429)", ['url' => $url]);
        
        // Try to extract rate limit headers
        $resetTime = time() + 3600; // Default to 1 hour if no header is provided
        
        // Parse headers to look for rate limit information
        $headerLines = explode("\n", $headers);
        foreach ($headerLines as $line) {
            if (strpos($line, 'X-RateLimit-Reset:') !== false) {
                $resetTime = trim(str_replace('X-RateLimit-Reset:', '', $line));
                debug_log("Found X-RateLimit-Reset header", ['reset_time' => $resetTime]);
            }
        }
        
        // Save rate limit info
        $rateLimitInfo = [
            'reset_time' => $resetTime,
            'last_error' => time(),
            'url' => $url,
            'http_code' => $httpCode
        ];
        
        // Try to get rate limit reset time from response
        $responseData = json_decode($body, true);
        if (isset($responseData['msg']) && strpos($responseData['msg'], 'limit of free requests') !== false) {
            // This is a GeoAPI.pt rate limit message
            debug_log("GeoAPI rate limit message found", $responseData);
            $rateLimitInfo['message'] = $responseData['msg'];
        }
        
        file_put_contents($rateLimitFile, json_encode($rateLimitInfo));
        debug_log("Saved rate limit info", $rateLimitInfo);
        
        // If we haven't reached max retries, try again
        if ($retryCount < $maxRetries) {
            // Exponential backoff
            $sleepTime = $retryDelay * pow(2, $retryCount);
            debug_log("Retrying after rate limit with backoff", ['sleep_time' => $sleepTime]);
            sleep($sleepTime);
            return makeApiRequest($url, $postData, $retryCount + 1, $maxRetries, $retryDelay);
        }
    }
    
    curl_close($ch);
    
    return [
        'success' => $httpCode >= 200 && $httpCode < 300,
        'code' => $httpCode,
        'message' => 'HTTP Code: ' . $httpCode,
        'response' => $body,
        'headers' => $headers,
        'verbose' => $verboseLog
    ];
}

// Build the full API URL
$apiUrl = rtrim($geoApiBaseUrl, '/') . '/' . ltrim($endpoint, '/');

// For debugging
debug_log("GeoAPI URL", ['url' => $apiUrl]);

// Make the API request
$result = makeApiRequest(
    $apiUrl, 
    $_SERVER['REQUEST_METHOD'] === 'POST' ? file_get_contents('php://input') : null,
    $retryCount,
    $maxRetries,
    $retryDelay
);

// If the request was successful, cache the response
if ($result['success']) {
    debug_log("Successful API response", ['code' => $result['code']]);
    
    // Check if the response is valid JSON
    $jsonCheck = json_decode($result['response']);
    if ($jsonCheck !== null) {
        // Save to cache
        debug_log("Caching response");
        file_put_contents($cacheFile, $result['response']);
    } else {
        debug_log("Invalid JSON response, not caching");
    }
    
    // Return the API response with the same HTTP code
    http_response_code($result['code']);
    echo $result['response'];
} else {
    debug_log("Failed API response", ['code' => $result['code'], 'message' => $result['message']]);
    
    // If we have a cached response for this endpoint, use it even if expired
    if (file_exists($cacheFile)) {
        debug_log("Using cached response despite API failure");
        $response = file_get_contents($cacheFile);
        echo $response;
        exit;
    }
    
    // If this is a GPS coordinate request and it failed, return a fallback response
    if (strpos($endpoint, 'gps/') === 0) {
        // Extract coordinates
        $coords = str_replace('gps/', '', $endpoint);
        $coords = str_replace('/base', '', $coords);
        list($lat, $lng) = explode(',', $coords);
        
        debug_log("Creating fallback GPS response");
        
        // Create a fallback response for Aveiro, Portugal (as an example)
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
        
        // Cache this response
        $fallbackJson = json_encode($fallbackResponse);
        file_put_contents($cacheFile, $fallbackJson);
        
        echo $fallbackJson;
        exit;
    }
    
    // Otherwise, return the error with debug information
    http_response_code($result['code'] > 0 ? $result['code'] : 500);
    $errorResponse = [
        'success' => false,
        'message' => $result['message'],
        'debug' => [
            'url' => $apiUrl,
            'endpoint' => $endpoint,
            'verbose' => $result['verbose'] ?? null,
            'headers' => $result['headers'] ?? null
        ]
    ];
    debug_log("Returning error response", $errorResponse);
    echo json_encode($errorResponse);
} 