<?php
/**
 * Fetch Location Data Endpoint
 * Retrieves data for a specific location (freguesia, concelho, or distrito)
 * Combines data from GeoAPI.pt and Geofabrik database
 * 
 * @version 1.1
 */

// Prevent any output before JSON response
error_reporting(0);
ini_set('display_errors', 0);

// Include database configuration
require_once '../config/db_config.php';

// Set headers for JSON response
header('Content-Type: application/json');

// Initialize debug info array
$debug_info = [];

// Get parameters from the request
$action = '';
$debug = isset($_GET['debug']) && $_GET['debug'] === 'true';

if ($debug) {
    $debug_info['request_params'] = $_GET;
}

// Determine action based on parameters
if (isset($_GET['lat']) && isset($_GET['lng'])) {
    $action = 'fetchLocationByCoordinates';
    $lat = $_GET['lat'];
    $lng = $_GET['lng'];
} else if (isset($_GET['freguesia']) && isset($_GET['concelho'])) {
    $action = 'fetchLocationByFreguesia';
    $freguesia = $_GET['freguesia'];
    $concelho = $_GET['concelho'];
} else if (isset($_GET['concelho']) && !isset($_GET['freguesia'])) {
    $action = 'fetchLocationByConcelho';
    $concelho = $_GET['concelho'];
} else if (isset($_GET['distrito'])) {
    $action = 'fetchLocationByDistrito';
    $distrito = $_GET['distrito'];
} else {
    // No valid parameters provided
    echo json_encode([
        'success' => false,
        'message' => 'Missing required parameters'
    ]);
    exit;
}

if ($debug) {
    $debug_info['action'] = $action;
    
    // Enable error reporting for debugging
    ini_set('display_errors', 1);
    ini_set('display_startup_errors', 1);
    error_reporting(E_ALL);
}

// Execute the requested action
$locationData = null;

try {
    switch ($action) {
        case 'fetchLocationByCoordinates':
            $locationData = fetchLocationByCoordinates($lat, $lng, $debug);
            break;
        case 'fetchLocationByFreguesia':
            $locationData = fetchLocationByFreguesia($freguesia, $concelho, $debug);
            break;
        case 'fetchLocationByConcelho':
            $locationData = fetchLocationByConcelho($concelho, $debug);
            break;
        case 'fetchLocationByDistrito':
            $locationData = fetchLocationByDistrito($distrito, $debug);
            break;
    }
} catch (Exception $e) {
    if ($debug) {
        $debug_info['exception'] = $e->getMessage();
        $debug_info['trace'] = $e->getTraceAsString();
    }
    
    $locationData = null;
}

// Return the response
if ($locationData) {
    echo json_encode([
        'success' => true,
        'message' => 'Location data retrieved successfully',
        'data' => $locationData,
        'debug' => $debug ? $debug_info : null
    ]);
} else {
    echo json_encode([
        'success' => false,
        'message' => 'Failed to retrieve location data for ' . str_replace('fetchLocationBy', '', $action),
        'data' => null,
        'debug' => $debug ? $debug_info : null
    ]);
}

/**
 * Fetch location data by coordinates using GeoAPI.pt
 */
function fetchLocationByCoordinates($lat, $lng, $debug = false) {
    global $debug_info;
    
    // Create the endpoint URL for reverse geocoding - use the faster /base endpoint
    $endpoint = "gps/{$lat},{$lng}/base";
    
    if ($debug) {
        $debug_info['coordinates_endpoint'] = $endpoint;
        $debug_info['coordinates_lat_lng'] = "$lat,$lng";
    }
    
    // Check if we have a cached response for this endpoint
    $cacheDir = __DIR__ . '/../cache/geoapi/';
    $cacheFile = $cacheDir . md5($endpoint) . '.json';
    
    if (file_exists($cacheFile)) {
        if ($debug) {
            $debug_info['using_cached_response'] = true;
            $debug_info['cache_file'] = $cacheFile;
        }
        
        $response = file_get_contents($cacheFile);
        $data = json_decode($response, true);
        
        if ($data) {
            if ($debug) {
                $debug_info['cache_data_valid'] = true;
            }
            
            // Extract freguesia data from cached response
            if (isset($data['freguesia'])) {
                // Standard response format
                $freguesiaData = $data['freguesia'];
                $municipioNome = $data['municipio']['nome'] ?? null;
                $freguesiaCode = $freguesiaData['codigo'] ?? null;
                
                if ($freguesiaCode && $municipioNome) {
                    if ($debug) {
                        $debug_info['using_cached_freguesia'] = $freguesiaCode;
                        $debug_info['using_cached_municipio'] = $municipioNome;
                    }
                    
                    // Fetch detailed data for the freguesia
                    return fetchLocationByCode($freguesiaCode, 'freguesia', $municipioNome, $debug);
                }
            } else if (isset($data['local']) && isset($data['local']['freguesia'])) {
                // Alternative response format
                $freguesiaData = $data['local']['freguesia'];
                $municipioNome = $data['local']['concelho']['nome'] ?? null;
                $freguesiaCode = $freguesiaData['codigo'] ?? null;
                
                if ($freguesiaCode && $municipioNome) {
                    if ($debug) {
                        $debug_info['using_cached_freguesia'] = $freguesiaCode;
                        $debug_info['using_cached_municipio'] = $municipioNome;
                    }
                    
                    // Fetch detailed data for the freguesia
                    return fetchLocationByCode($freguesiaCode, 'freguesia', $municipioNome, $debug);
                }
            }
        }
    }
    
    // Use our proxy with caching - ensure the path is correct
    $proxyUrl = "http://localhost:8000/includes/geoapi_proxy.php?endpoint=" . urlencode($endpoint);
    
    if ($debug) {
        $debug_info['coordinates_proxy_url'] = $proxyUrl;
    }
    
    // Initialize cURL
    $ch = curl_init();
    
    // Set cURL options
    curl_setopt($ch, CURLOPT_URL, $proxyUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    curl_setopt($ch, CURLOPT_USERAGENT, 'Minu15-App/1.0');
    
    // Enable verbose output for debugging
    if ($debug) {
        $verbose = fopen('php://temp', 'w+');
        curl_setopt($ch, CURLOPT_VERBOSE, true);
        curl_setopt($ch, CURLOPT_STDERR, $verbose);
    }
    
    // Execute the request
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $effectiveUrl = curl_getinfo($ch, CURLINFO_EFFECTIVE_URL);
    
    if ($debug) {
        $debug_info['coordinates_http_code'] = $httpCode;
        $debug_info['coordinates_response_size'] = strlen($response);
        $debug_info['coordinates_effective_url'] = $effectiveUrl;
        
        // Add a sample of the response for debugging
        if ($response) {
            $debug_info['coordinates_response_sample'] = substr($response, 0, 200) . '...';
        }
        
        // Get verbose information if enabled
        if (isset($verbose)) {
            rewind($verbose);
            $debug_info['coordinates_curl_verbose'] = stream_get_contents($verbose);
        }
    }
    
    // Check for errors
    if (curl_errno($ch)) {
        if ($debug) {
            $debug_info['coordinates_curl_error'] = curl_error($ch);
            $debug_info['coordinates_curl_errno'] = curl_errno($ch);
        }
        curl_close($ch);
        return null;
    }
    
    // Close cURL
    curl_close($ch);
    
    // Check for non-200 response
    if ($httpCode !== 200) {
        if ($debug) {
            $debug_info['coordinates_http_error'] = "HTTP error code: $httpCode";
        }
        return null;
    }
    
    // Parse the response
    $data = json_decode($response, true);
    
    // Check for JSON parsing errors
    if ($data === null) {
        if ($debug) {
            $debug_info['coordinates_json_error'] = json_last_error_msg();
            $debug_info['coordinates_raw_response'] = $response;
        }
        return null;
    }
    
    if ($debug) {
        $debug_info['coordinates_response'] = $data;
    }
    
    // Check for error message in response (rate limit message)
    if (isset($data['msg']) && strpos($data['msg'], 'limit of free requests') !== false) {
        if ($debug) {
            $debug_info['coordinates_rate_limit'] = $data['msg'];
        }
        return null;
    }
    
    // Check for different possible response structures
    if (isset($data['freguesia'])) {
        // Standard response format
        $freguesiaData = $data['freguesia'];
        $municipioNome = $data['municipio']['nome'] ?? null;
        $freguesiaCode = $freguesiaData['codigo'] ?? null;
    } else if (isset($data['local']) && isset($data['local']['freguesia'])) {
        // Alternative response format
        $freguesiaData = $data['local']['freguesia'];
        $municipioNome = $data['local']['concelho']['nome'] ?? null;
        $freguesiaCode = $freguesiaData['codigo'] ?? null;
    } else {
        // No freguesia data found
        if ($debug) {
            $debug_info['coordinates_error'] = 'No freguesia data in response';
            $debug_info['coordinates_keys'] = is_array($data) ? array_keys($data) : 'not_array';
        }
        return null;
    }
    
    if ($debug) {
        $debug_info['freguesia_code'] = $freguesiaCode;
        $debug_info['municipio_nome'] = $municipioNome;
        $debug_info['freguesia_data'] = $freguesiaData;
    }
    
    if (!$freguesiaCode || !$municipioNome) {
        if ($debug) {
            $debug_info['coordinates_error'] = 'Missing freguesia code or municipio name';
        }
        return null;
    }
    
    // Fetch detailed data for the freguesia
    return fetchLocationByCode($freguesiaCode, 'freguesia', $municipioNome, $debug);
}

/**
 * Fetch location data by freguesia name
 */
function fetchLocationByFreguesia($freguesia, $concelho, $debug = false) {
    // Create the endpoint URL for freguesia search
    $endpoint = "municipio/" . urlencode($concelho) . "/freguesia/" . urlencode($freguesia);
    
    if ($debug) {
        $debug_info['freguesia_endpoint'] = $endpoint;
    }
    
    // Use our proxy with caching
    $proxyUrl = "http://localhost:8000/includes/geoapi_proxy.php?endpoint=" . urlencode($endpoint);
    
    if ($debug) {
        $debug_info['freguesia_endpoint'] = $endpoint;
        $debug_info['freguesia_proxy_url'] = $proxyUrl;
    }
    
    // Initialize cURL
    $ch = curl_init();
    
    // Set cURL options
    curl_setopt($ch, CURLOPT_URL, $proxyUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    
    // Execute the request
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    
    if ($debug) {
        $debug_info['freguesia_http_code'] = $httpCode;
    }
    
    // Check for errors
    if (curl_errno($ch) || $httpCode !== 200) {
        if ($debug) {
            $debug_info['freguesia_error'] = curl_error($ch);
        }
        curl_close($ch);
        return null;
    }
    
    // Close cURL
    curl_close($ch);
    
    // Parse the response
    $data = json_decode($response, true);
    
    if ($debug) {
        $debug_info['freguesia_response'] = $data;
    }
    
    if (!$data || !isset($data['codigo'])) {
        if ($debug) {
            $debug_info['freguesia_error'] = 'No freguesia code in response';
        }
        return null;
    }
    
    // Get the freguesia code
    $freguesiaCode = $data['codigo'] ?? null;
    
    if (!$freguesiaCode) {
        if ($debug) {
            $debug_info['freguesia_error'] = 'Missing freguesia code';
        }
        return null;
    }
    
    // Fetch detailed data for the freguesia
    return fetchLocationByCode($freguesiaCode, 'freguesia', $concelho, $debug);
}

/**
 * Fetch location data by concelho name
 */
function fetchLocationByConcelho($concelho, $debug = false) {
    // Create the endpoint URL for concelho search
    $endpoint = "municipio/" . urlencode($concelho);
    
    if ($debug) {
        $debug_info['concelho_endpoint'] = $endpoint;
    }
    
    // Use our proxy with caching
    $proxyUrl = "http://localhost:8000/includes/geoapi_proxy.php?endpoint=" . urlencode($endpoint);
    
    if ($debug) {
        $debug_info['concelho_endpoint'] = $endpoint;
        $debug_info['concelho_proxy_url'] = $proxyUrl;
    }
    
    // Initialize cURL
    $ch = curl_init();
    
    // Set cURL options
    curl_setopt($ch, CURLOPT_URL, $proxyUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    
    // Execute the request
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    
    if ($debug) {
        $debug_info['concelho_http_code'] = $httpCode;
    }
    
    // Check for errors
    if (curl_errno($ch) || $httpCode !== 200) {
        if ($debug) {
            $debug_info['concelho_error'] = curl_error($ch);
        }
        curl_close($ch);
        return null;
    }
    
    // Close cURL
    curl_close($ch);
    
    // Parse the response
    $data = json_decode($response, true);
    
    if ($debug) {
        $debug_info['concelho_response'] = $data;
    }
    
    if (!$data || !isset($data['codigo'])) {
        if ($debug) {
            $debug_info['concelho_error'] = 'No concelho code in response';
        }
        return null;
    }
    
    // Get the concelho code
    $concelhoCode = $data['codigo'] ?? null;
    
    if (!$concelhoCode) {
        if ($debug) {
            $debug_info['concelho_error'] = 'Missing concelho code';
        }
        return null;
    }
    
    // Fetch detailed data for the concelho
    return fetchLocationByCode($concelhoCode, 'concelho', null, $debug);
}

/**
 * Fetch location data by distrito name
 */
function fetchLocationByDistrito($distrito, $debug = false) {
    // Create the endpoint URL for distrito search
    $endpoint = "distrito/" . urlencode($distrito);
    
    if ($debug) {
        $debug_info['distrito_endpoint'] = $endpoint;
    }
    
    // Use our proxy with caching
    $proxyUrl = "http://localhost:8000/includes/geoapi_proxy.php?endpoint=" . urlencode($endpoint);
    
    if ($debug) {
        $debug_info['distrito_endpoint'] = $endpoint;
        $debug_info['distrito_proxy_url'] = $proxyUrl;
    }
    
    // Initialize cURL
    $ch = curl_init();
    
    // Set cURL options
    curl_setopt($ch, CURLOPT_URL, $proxyUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    
    // Execute the request
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    
    if ($debug) {
        $debug_info['distrito_http_code'] = $httpCode;
    }
    
    // Check for errors
    if (curl_errno($ch) || $httpCode !== 200) {
        if ($debug) {
            $debug_info['distrito_error'] = curl_error($ch);
        }
        curl_close($ch);
        return null;
    }
    
    // Close cURL
    curl_close($ch);
    
    // Parse the response
    $data = json_decode($response, true);
    
    if ($debug) {
        $debug_info['distrito_response'] = $data;
    }
    
    if (!$data || !isset($data['codigo'])) {
        if ($debug) {
            $debug_info['distrito_error'] = 'No distrito code in response';
        }
        return null;
    }
    
    // Get the distrito code
    $distritoCode = $data['codigo'] ?? null;
    
    if (!$distritoCode) {
        if ($debug) {
            $debug_info['distrito_error'] = 'Missing distrito code';
        }
        return null;
    }
    
    // Fetch detailed data for the distrito
    return fetchLocationByCode($distritoCode, 'distrito', null, $debug);
}

/**
 * Fetch location data by code (freguesia, concelho, or distrito)
 */
function fetchLocationByCode($code, $type, $municipioName = null, $debug = false) {
    global $debug_info;
    
    // Validate type
    if (!in_array($type, ['freguesia', 'concelho', 'distrito'])) {
        if ($debug) {
            $debug_info['code_error'] = 'Invalid location type: ' . $type;
        }
        return null;
    }
    
    // Create the endpoint URL based on type
    switch ($type) {
        case 'freguesia':
            if (!$municipioName) {
                if ($debug) {
                    $debug_info['code_error'] = 'Missing municipio name for freguesia';
                }
                return null;
            }
            $endpoint = "municipio/" . urlencode($municipioName) . "/freguesia/" . urlencode($code);
            break;
        case 'concelho':
            $endpoint = "municipio/" . urlencode($code);
            break;
        case 'distrito':
            $endpoint = "distrito/" . urlencode($code);
            break;
    }
    
    if ($debug) {
        $debug_info['code_endpoint'] = $endpoint;
    }
    
    // Use our proxy with caching
    $proxyUrl = "http://localhost:8000/includes/geoapi_proxy.php?endpoint=" . urlencode($endpoint);
    
    if ($debug) {
        $debug_info['code_endpoint'] = $endpoint;
        $debug_info['code_proxy_url'] = $proxyUrl;
    }
    
    // Initialize cURL
    $ch = curl_init();
    
    // Set cURL options
    curl_setopt($ch, CURLOPT_URL, $proxyUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 60); // Increased timeout
    curl_setopt($ch, CURLOPT_USERAGENT, 'Minu15-App/1.0');
    
    // Execute the request
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    
    if ($debug) {
        $debug_info['code_http_code'] = $httpCode;
    }
    
    // Check for errors
    if (curl_errno($ch) || $httpCode !== 200) {
        if ($debug) {
            $debug_info['code_error'] = curl_error($ch);
        }
        curl_close($ch);
        return null;
    }
    
    // Close cURL
    curl_close($ch);
    
    // Parse the response
    $locationData = json_decode($response, true);
    
    if ($debug) {
        $debug_info['code_response'] = $locationData;
    }
    
    if (!$locationData) {
        if ($debug) {
            $debug_info['code_error'] = 'Failed to parse location data JSON';
        }
        return null;
    }
    
    // Add additional data based on type
    if ($type === 'freguesia') {
        // Add census data
        if ($debug) {
            $debug_info['fetching_census_data'] = true;
        }
        
        $censusData = fetchCensusData($code, $type, $municipioName, $debug);
        if ($censusData) {
            $locationData['censos2021'] = isset($censusData['censos2021']) ? $censusData['censos2021'] : null;
            $locationData['censos2011'] = isset($censusData['censos2011']) ? $censusData['censos2011'] : null;
            
            if ($debug) {
                $debug_info['census_data_added'] = true;
            }
        } else if ($debug) {
            $debug_info['census_data_error'] = 'Failed to fetch census data';
        }
        
        // Add geometry data
        if ($debug) {
            $debug_info['fetching_geometry_data'] = true;
        }
        
        $geometryData = fetchGeometryData($code, $type, $municipioName, $debug);
        if ($geometryData) {
            $locationData['geometry'] = $geometryData;
            $locationData['geojson'] = $geometryData; // Add both keys for consistency
            
            if ($debug) {
                $debug_info['geometry_data_added'] = true;
                $debug_info['geometry_type'] = is_array($geometryData) && isset($geometryData['type']) ? $geometryData['type'] : 'unknown';
            }
            
            // Add POI counts if geometry is available
            $poiCounts = fetchPOICounts($geometryData, $debug);
            if ($poiCounts) {
                $locationData['poi_counts'] = $poiCounts;
                
                if ($debug) {
                    $debug_info['poi_counts_added'] = true;
                }
            } else if ($debug) {
                $debug_info['poi_counts_error'] = 'Failed to fetch POI counts';
            }
        } else if ($debug) {
            $debug_info['geometry_data_error'] = 'Failed to fetch geometry data';
        }
    } else if ($type === 'concelho' || $type === 'distrito') {
        // Add census data for concelho and distrito
        if ($debug) {
            $debug_info['fetching_census_data_' . $type] = true;
        }
        
        $censusData = fetchCensusData($code, $type, $municipioName, $debug);
        if ($censusData) {
            $locationData['censos2021'] = isset($censusData['censos2021']) ? $censusData['censos2021'] : null;
            $locationData['censos2011'] = isset($censusData['censos2011']) ? $censusData['censos2011'] : null;
            
            if ($debug) {
                $debug_info['census_data_added_' . $type] = true;
            }
        } else if ($debug) {
            $debug_info['census_data_error_' . $type] = 'Failed to fetch census data';
        }
        
        // Add geometry data for concelho and distrito
        if ($debug) {
            $debug_info['fetching_geometry_data_' . $type] = true;
        }
        
        $geometryData = fetchGeometryData($code, $type, $municipioName, $debug);
        if ($geometryData) {
            $locationData['geometry'] = $geometryData;
            $locationData['geojson'] = $geometryData; // Add both keys for consistency
            
            if ($debug) {
                $debug_info['geometry_data_added_' . $type] = true;
                $debug_info['geometry_type_' . $type] = is_array($geometryData) && isset($geometryData['type']) ? $geometryData['type'] : 'unknown';
            }
        } else if ($debug) {
            $debug_info['geometry_data_error_' . $type] = 'Failed to fetch geometry data';
        }
    }
    
    return $locationData;
}

/**
 * Fetch census data for a location
 */
function fetchCensusData($code, $type, $municipioName = null, $debug = false) {
    // Create the endpoint URL based on type
    switch ($type) {
        case 'freguesia':
            if (!$municipioName) {
                return null;
            }
            $endpoint = "municipio/" . urlencode($municipioName) . "/freguesia/" . urlencode($code) . "/censos";
            break;
        case 'concelho':
            $endpoint = "municipio/" . urlencode($code) . "/censos";
            break;
        case 'distrito':
            $endpoint = "distrito/" . urlencode($code) . "/censos";
            break;
        default:
            return null;
    }
    
    // Use our proxy with caching
    $proxyUrl = "http://localhost:8000/includes/geoapi_proxy.php?endpoint=" . urlencode($endpoint);
    
    if ($debug) {
        $debug_info['census_endpoint'] = $endpoint;
        $debug_info['census_proxy_url'] = $proxyUrl;
    }
    
    // Initialize cURL
    $ch = curl_init();
    
    // Set cURL options
    curl_setopt($ch, CURLOPT_URL, $proxyUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 60); // Increased timeout
    curl_setopt($ch, CURLOPT_USERAGENT, 'Minu15-App/1.0');
    
    // Execute the request
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    
    // Check for errors
    if (curl_errno($ch) || $httpCode !== 200) {
        curl_close($ch);
        return null;
    }
    
    // Close cURL
    curl_close($ch);
    
    // Parse the response
    return json_decode($response, true);
}

/**
 * Fetch geometry data for a location
 */
function fetchGeometryData($code, $type, $municipioName = null, $debug = false) {
    global $debug_info;
    
    // Create the endpoint URL based on type
    switch ($type) {
        case 'freguesia':
            if (!$municipioName) {
                if ($debug) {
                    $debug_info['geometry_error'] = 'Missing municipio name for freguesia geometry';
                }
                return null;
            }
            $endpoint = "municipio/" . urlencode($municipioName) . "/freguesia/" . urlencode($code) . "/geometry";
            break;
        case 'concelho':
            $endpoint = "municipio/" . urlencode($code) . "/geometry";
            break;
        case 'distrito':
            $endpoint = "distrito/" . urlencode($code) . "/geometry";
            break;
        default:
            if ($debug) {
                $debug_info['geometry_error'] = 'Invalid location type for geometry: ' . $type;
            }
            return null;
    }
    
    if ($debug) {
        $debug_info['geometry_endpoint'] = $endpoint;
    }
    
    // Use our proxy with caching
    $proxyUrl = "http://localhost:8000/includes/geoapi_proxy.php?endpoint=" . urlencode($endpoint);
    
    if ($debug) {
        $debug_info['geometry_endpoint'] = $endpoint;
        $debug_info['geometry_proxy_url'] = $proxyUrl;
    }
    
    // Initialize cURL
    $ch = curl_init();
    
    // Set cURL options
    curl_setopt($ch, CURLOPT_URL, $proxyUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 90); // Increased timeout for geometry data
    curl_setopt($ch, CURLOPT_USERAGENT, 'Minu15-App/1.0');
    
    // Execute the request
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    
    if ($debug) {
        $debug_info['geometry_http_code'] = $httpCode;
        $debug_info['geometry_response_size'] = strlen($response);
        
        // Add a small sample of the response for debugging
        if ($response) {
            $debug_info['geometry_response_sample'] = substr($response, 0, 200) . '...';
        }
    }
    
    // Check for errors
    if (curl_errno($ch) || $httpCode !== 200) {
        if ($debug) {
            $debug_info['geometry_error'] = curl_error($ch) ?: 'HTTP error: ' . $httpCode;
        }
        curl_close($ch);
        return null;
    }
    
    // Close cURL
    curl_close($ch);
    
    // Parse the response
    $geometryData = json_decode($response, true);
    
    if ($debug) {
        if ($geometryData) {
            $debug_info['geometry_parsed'] = true;
            $debug_info['geometry_data_type'] = is_array($geometryData) && isset($geometryData['type']) ? $geometryData['type'] : 'unknown';
            
            if (is_array($geometryData)) {
                if (isset($geometryData['type'])) {
                    $debug_info['geometry_type'] = $geometryData['type'];
                }
                
                if (isset($geometryData['geometry']) && isset($geometryData['geometry']['type'])) {
                    $debug_info['geometry_geometry_type'] = $geometryData['geometry']['type'];
                }
                
                if (isset($geometryData['features']) && is_array($geometryData['features'])) {
                    $debug_info['geometry_features_count'] = count($geometryData['features']);
                }
            }
        } else {
            $debug_info['geometry_error'] = 'Failed to parse geometry JSON';
            $debug_info['geometry_json_error'] = json_last_error_msg();
        }
    }
    
    // For distrito and concelho, we need to extract the actual GeoJSON
    if ($type === 'distrito' || $type === 'concelho') {
        // Check if we have a valid GeoJSON response
        if (isset($geometryData['type']) && $geometryData['type'] === 'Feature' && 
            isset($geometryData['geometry'])) {
            
            if ($debug) {
                $debug_info['geometry_valid_feature'] = true;
            }
            
            return $geometryData;
        } else {
            if ($debug) {
                $debug_info['geometry_invalid_feature'] = true;
                $debug_info['geometry_keys'] = is_array($geometryData) ? array_keys($geometryData) : 'not_array';
            }
        }
    }
    
    return $geometryData;
}

/**
 * Fetch POI counts from Geofabrik database
 */
function fetchPOICounts($geometryData, $debug = false) {
    // If no geometry data, return empty counts
    if (!$geometryData || !isset($geometryData['geometry'])) {
        return [
            'hospitals' => 0,
            'health_centers' => 0,
            'pharmacies' => 0,
            'schools' => 0,
            'universities' => 0,
            'supermarkets' => 0,
            'restaurants' => 0,
            'parks' => 0,
            'bus_stops' => 0,
            'police_stations' => 0
        ];
    }
    
    // Get database connection
    $conn = getDbConnection();
    
    // Convert GeoJSON to PostGIS geometry
    $geometry = json_encode($geometryData['geometry']);
    
    // Define POI types to count
    $poiTypes = [
        'hospitals' => "amenity = 'hospital'",
        'health_centers' => "amenity IN ('clinic', 'doctors')",
        'pharmacies' => "amenity = 'pharmacy'",
        'schools' => "amenity = 'school'",
        'universities' => "amenity = 'university'",
        'supermarkets' => "shop IN ('supermarket', 'convenience')",
        'restaurants' => "amenity IN ('restaurant', 'cafe', 'bar')",
        'parks' => "leisure IN ('park', 'garden')",
        'bus_stops' => "highway = 'bus_stop'",
        'police_stations' => "amenity = 'police'"
    ];
    
    $poiCounts = [];
    
    // Count each POI type
    foreach ($poiTypes as $type => $condition) {
        // Query that counts both points and polygons
        $countQuery = "
            SELECT 
                (
                    SELECT COUNT(*) FROM planet_osm_point 
                    WHERE ($condition) AND ST_Contains(
                        ST_SetSRID(ST_GeomFromGeoJSON('$geometry'), 4326),
                        ST_Transform(way, 4326)
                    )
                ) +
                (
                    SELECT COUNT(*) FROM planet_osm_polygon 
                    WHERE ($condition) AND ST_Contains(
                        ST_SetSRID(ST_GeomFromGeoJSON('$geometry'), 4326),
                        ST_Transform(way, 4326)
                    )
                ) as count
        ";
        
        $countResult = pg_query($conn, $countQuery);
        
        if (!$countResult) {
            $poiCounts[$type] = 0;
        } else {
            $countRow = pg_fetch_assoc($countResult);
            $poiCounts[$type] = (int) $countRow['count'];
        }
    }
    
    // Close the database connection
    pg_close($conn);
    
    return $poiCounts;
} 