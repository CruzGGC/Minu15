<?php
/**
 * Fetch Location Data Endpoint
 * Retrieves data for a specific location (freguesia, municipio, or distrito)
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
} else if (isset($_GET['municipio'])) {
    if (isset($_GET['freguesia'])) {
        // If both municipio and freguesia are provided, fetch freguesia data
        $action = 'fetchLocationByFreguesia';
        $freguesia = $_GET['freguesia'];
        $municipio = $_GET['municipio'];
    } else {
        // If only municipio is provided, fetch municipio data
        $action = 'fetchLocationByMunicipio';
        $municipio = $_GET['municipio'];
    }
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
    // Add detailed debug information about the action being executed
    if ($debug) {
        $debug_info['executing_action'] = $action;
        switch ($action) {
            case 'fetchLocationByCoordinates':
                $debug_info['action_parameters'] = ['lat' => $lat, 'lng' => $lng];
                break;
            case 'fetchLocationByFreguesia':
                $debug_info['action_parameters'] = ['freguesia' => $freguesia, 'municipio' => $municipio];
                break;
            case 'fetchLocationByMunicipio':
                $debug_info['action_parameters'] = ['municipio' => $municipio];
                break;
            case 'fetchLocationByDistrito':
                $debug_info['action_parameters'] = ['distrito' => $distrito];
                break;
        }
    }
    
    switch ($action) {
        case 'fetchLocationByCoordinates':
            $locationData = fetchLocationByCoordinates($lat, $lng, $debug);
            break;
        case 'fetchLocationByFreguesia':
            $locationData = fetchLocationByFreguesia($freguesia, $municipio, $debug);
            break;
        case 'fetchLocationByMunicipio':
            $locationData = fetchLocationByMunicipio($municipio, $debug);
            break;
        case 'fetchLocationByDistrito':
            $locationData = fetchLocationByDistrito($distrito, $debug);
            break;
    }
    
    // Check if we got valid location data
    if ($locationData === null) {
        if ($debug) {
            $debug_info['action_result'] = 'No location data returned';
        }
    } else {
        if ($debug) {
            $debug_info['action_result'] = 'Location data retrieved successfully';
            $debug_info['location_data_keys'] = array_keys($locationData);
        }
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
    
    // Create the endpoint URL for reverse geocoding - use the /base/detalhes endpoint
    $endpoint = "gps/{$lat},{$lng}/base/detalhes";
    
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
            
            // Check for JSON parsing errors
            if ($data === null) {
                if ($debug) {
                    $debug_info['coordinates_json_error'] = json_last_error_msg();
                }
                return null;
            }
            
            if ($debug) {
                $debug_info['coordinates_data_structure'] = array_keys($data);
            }
            
            // Extract freguesia data from response
            // The /base/detalhes endpoint returns a different structure with detalhesFreguesia and detalhesMunicipio
            if (isset($data['detalhesFreguesia'])) {
                // New response format from /base/detalhes
                $freguesiaData = $data['detalhesFreguesia'];
                $municipioNome = $data['detalhesMunicipio']['nome'] ?? $data['concelho'] ?? null;
                $freguesiaCode = $freguesiaData['codigo'] ?? null;
                
                if ($freguesiaCode && $municipioNome) {
                    if ($debug) {
                        $debug_info['using_freguesia_code'] = $freguesiaCode;
                        $debug_info['using_municipio_name'] = $municipioNome;
                    }
                    
                    // Fetch detailed data for the freguesia
                    return fetchLocationByCode($freguesiaCode, 'freguesia', $municipioNome, $debug);
                }
            } else if (isset($data['freguesia'])) {
                // Standard response format from old endpoint
                $freguesiaData = $data['freguesia'];
                $municipioNome = $data['municipio']['nome'] ?? null;
                $freguesiaCode = $freguesiaData['codigo'] ?? null;
                
                if ($freguesiaCode && $municipioNome) {
                    if ($debug) {
                        $debug_info['using_freguesia_code'] = $freguesiaCode;
                        $debug_info['using_municipio_name'] = $municipioNome;
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
                        $debug_info['using_freguesia_code'] = $freguesiaCode;
                        $debug_info['using_municipio_name'] = $municipioNome;
                    }
                    
                    // Fetch detailed data for the freguesia
                    return fetchLocationByCode($freguesiaCode, 'freguesia', $municipioNome, $debug);
                }
            }
            
            if ($debug) {
                $debug_info['coordinates_no_freguesia_data'] = true;
            }
            
            return null;
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
    
    // Extract freguesia data from response
    // The /base/detalhes endpoint returns a different structure with detalhesFreguesia and detalhesMunicipio
    if (isset($data['detalhesFreguesia'])) {
        // New response format from /base/detalhes
        $freguesiaData = $data['detalhesFreguesia'];
        $municipioNome = $data['detalhesMunicipio']['nome'] ?? $data['concelho'] ?? null;
        $freguesiaCode = $freguesiaData['codigo'] ?? null;
        
        if ($freguesiaCode && $municipioNome) {
            if ($debug) {
                $debug_info['using_freguesia_code'] = $freguesiaCode;
                $debug_info['using_municipio_name'] = $municipioNome;
            }
            
            // Fetch detailed data for the freguesia
            return fetchLocationByCode($freguesiaCode, 'freguesia', $municipioNome, $debug);
        }
    } else if (isset($data['freguesia'])) {
        // Standard response format from old endpoint
        $freguesiaData = $data['freguesia'];
        $municipioNome = $data['municipio']['nome'] ?? null;
        $freguesiaCode = $freguesiaData['codigo'] ?? null;
        
        if ($freguesiaCode && $municipioNome) {
            if ($debug) {
                $debug_info['using_freguesia_code'] = $freguesiaCode;
                $debug_info['using_municipio_name'] = $municipioNome;
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
                $debug_info['using_freguesia_code'] = $freguesiaCode;
                $debug_info['using_municipio_name'] = $municipioNome;
            }
            
            // Fetch detailed data for the freguesia
            return fetchLocationByCode($freguesiaCode, 'freguesia', $municipioNome, $debug);
        }
    }
    
    if ($debug) {
        $debug_info['coordinates_no_freguesia_data'] = true;
    }
    
    return null;
}

/**
 * Fetch location data by freguesia name
 */
function fetchLocationByFreguesia($freguesiaName, $municipio, $debug = false) {
    global $debug_info;
    
    if ($debug) {
        $debug_info['freguesia_name_received'] = $freguesiaName;
        $debug_info['municipio_received'] = $municipio;
    }

    // Use the correct API endpoint format: /municipio/{municipio}/freguesia/{freguesia}
    $endpoint = "municipio/" . urlencode($municipio) . "/freguesia/" . urlencode($freguesiaName);
    
    if ($debug) {
        $debug_info['freguesia_endpoint'] = $endpoint;
    }
    
    // Use our proxy with caching
    $proxyUrl = "http://localhost:8000/includes/geoapi_proxy.php?endpoint=" . urlencode($endpoint);
    
    if ($debug) {
        $debug_info['freguesia_proxy_url'] = $proxyUrl;
    }
    
    // Initialize cURL
    $ch = curl_init();
    
    // Set cURL options
    curl_setopt($ch, CURLOPT_URL, $proxyUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    curl_setopt($ch, CURLOPT_USERAGENT, 'Minu15-App/1.0');
    
    // Execute the request
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    
    if ($debug) {
        $debug_info['freguesia_http_code'] = $httpCode;
        $debug_info['freguesia_response_size'] = strlen($response);
        if ($response) {
            $debug_info['freguesia_response_sample'] = substr($response, 0, 200) . '...';
        }
    }
    
    // Check for cURL errors
    if (curl_errno($ch)) {
        if ($debug) {
            $debug_info['freguesia_curl_error'] = curl_error($ch);
            $debug_info['freguesia_curl_errno'] = curl_errno($ch);
        }
        curl_close($ch);
        
        // Try the fallback approach
        return tryFreguesiaMunicipioFallback($freguesiaName, $municipio, $debug);
    }
    
    // Close cURL
    curl_close($ch);
    
    // Check for HTTP errors
    if ($httpCode !== 200) {
        if ($debug) {
            $debug_info['freguesia_http_error'] = "HTTP error code: $httpCode";
        }
        
        // Try the fallback approach
        return tryFreguesiaMunicipioFallback($freguesiaName, $municipio, $debug);
    }
    
    // Parse JSON response
    $data = json_decode($response, true);
    
    // Check for JSON parsing errors
    if ($data === null) {
        if ($debug) {
            $debug_info['freguesia_json_error'] = json_last_error_msg();
        }
        
        // Try the fallback approach
        return tryFreguesiaMunicipioFallback($freguesiaName, $municipio, $debug);
    }
    
    if ($debug) {
        $debug_info['freguesia_data_structure'] = array_keys($data);
    }
    
    // Ensure we have a proper municipio structure
    if (!isset($data['municipio']) || !is_array($data['municipio'])) {
        $data['municipio'] = ['nome' => $municipio];
    } else if (is_string($data['municipio'])) {
        $data['municipio'] = ['nome' => $data['municipio']];
    }
    
    // Get geometry data if not already included
    if (!isset($data['geometry']) && !isset($data['geojson'])) {
        $geometryEndpoint = "municipio/" . urlencode($municipio) . "/freguesia/" . urlencode($freguesiaName) . "/geometry";
        $geometryData = fetchGeometryFromEndpoint($geometryEndpoint, $debug);
        
        if ($geometryData) {
            $data['geometry'] = $geometryData;
            $data['geojson'] = $geometryData; // Add both keys for consistency
            
            if ($debug) {
                $debug_info['geometry_data_added'] = true;
            }
            
            // Add POI counts if geometry is available
            $poiCounts = fetchPOICounts($geometryData, $debug);
            if ($poiCounts) {
                $data['poi_counts'] = $poiCounts;
                
                if ($debug) {
                    $debug_info['poi_counts_added'] = true;
                }
            }
        }
    }
    
    return $data;
}

/**
 * Try fallback approach for freguesia lookup
 * Uses the alternate endpoint format: /freguesia/{freguesia}?municipio={municipio}
 */
function tryFreguesiaMunicipioFallback($freguesiaName, $municipio, $debug = false) {
    global $debug_info;
    
    // Fallback: try using the /freguesia endpoint with municipio query param
    $fallbackEndpoint = "freguesia/" . urlencode($freguesiaName) . "?municipio=" . urlencode($municipio);
    
    if ($debug) {
        $debug_info['fallback_endpoint'] = $fallbackEndpoint;
    }
    
    // Use our proxy with caching for fallback
    $fallbackProxyUrl = "http://localhost:8000/includes/geoapi_proxy.php?endpoint=" . urlencode($fallbackEndpoint);
    
    if ($debug) {
        $debug_info['fallback_proxy_url'] = $fallbackProxyUrl;
    }
    
    // Initialize cURL again
    $ch = curl_init();
    
    // Set cURL options
    curl_setopt($ch, CURLOPT_URL, $fallbackProxyUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    curl_setopt($ch, CURLOPT_USERAGENT, 'Minu15-App/1.0');
    
    // Execute the request
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    
    if ($debug) {
        $debug_info['fallback_http_code'] = $httpCode;
        $debug_info['fallback_response_size'] = strlen($response);
        if ($response) {
            $debug_info['fallback_response_sample'] = substr($response, 0, 200) . '...';
        }
    }
    
    // Check for cURL errors
    if (curl_errno($ch)) {
        if ($debug) {
            $debug_info['fallback_curl_error'] = curl_error($ch);
            $debug_info['fallback_curl_errno'] = curl_errno($ch);
        }
        curl_close($ch);
        
        // Create minimal data from available info as last resort
        return createMinimalLocationData($freguesiaName, $municipio, $debug);
    }
    
    // Close cURL
    curl_close($ch);
    
    // Check for HTTP errors
    if ($httpCode !== 200) {
        if ($debug) {
            $debug_info['fallback_http_error'] = "HTTP error code: $httpCode";
        }
        
        // Create minimal data from available info as last resort
        return createMinimalLocationData($freguesiaName, $municipio, $debug);
    }
    
    // Parse JSON response
    $data = json_decode($response, true);
    
    // Check for JSON parsing errors
    if ($data === null) {
        if ($debug) {
            $debug_info['fallback_json_error'] = json_last_error_msg();
        }
        
        // Create minimal data from available info as last resort
        return createMinimalLocationData($freguesiaName, $municipio, $debug);
    }
    
    if ($debug) {
        $debug_info['fallback_data_structure'] = array_keys($data);
    }
    
    // Ensure we have a proper municipio structure
    if (!isset($data['municipio']) || !is_array($data['municipio'])) {
        $data['municipio'] = ['nome' => $municipio];
    } else if (is_string($data['municipio'])) {
        $data['municipio'] = ['nome' => $data['municipio']];
    }
    
    // Get geometry data if not already included
    if (!isset($data['geometry']) && !isset($data['geojson'])) {
        $geometryEndpoint = "freguesia/" . urlencode($freguesiaName) . "/geometry?municipio=" . urlencode($municipio);
        $geometryData = fetchGeometryFromEndpoint($geometryEndpoint, $debug);
        
        if ($geometryData) {
            $data['geometry'] = $geometryData;
            $data['geojson'] = $geometryData; // Add both keys for consistency
            
            if ($debug) {
                $debug_info['geometry_data_added'] = true;
            }
            
            // Add POI counts if geometry is available
            $poiCounts = fetchPOICounts($geometryData, $debug);
            if ($poiCounts) {
                $data['poi_counts'] = $poiCounts;
                
                if ($debug) {
                    $debug_info['poi_counts_added'] = true;
                }
            }
        }
    }
    
    return $data;
}

/**
 * Create minimal location data when API calls fail
 */
function createMinimalLocationData($freguesiaName, $municipio, $debug = false) {
    global $debug_info;
    
    if ($debug) {
        $debug_info['creating_minimal_fallback_data'] = true;
    }
    
    // Create a basic data structure with the information we have
    return [
        'nome' => $freguesiaName,
        'municipio' => [
            'nome' => $municipio
        ],
        'tipo' => 'Freguesia',
        'error' => 'Limited data available - API calls failed'
    ];
}

/**
 * Fetch geometry data from a specific endpoint
 */
function fetchGeometryFromEndpoint($endpoint, $debug = false) {
    global $debug_info;
    
    if ($debug) {
        $debug_info['geometry_endpoint'] = $endpoint;
    }
    
    // Use our proxy with caching
    $proxyUrl = "http://localhost:8000/includes/geoapi_proxy.php?endpoint=" . urlencode($endpoint);
    
    if ($debug) {
        $debug_info['geometry_proxy_url'] = $proxyUrl;
    }
    
    // Initialize cURL
    $ch = curl_init();
    
    // Set cURL options
    curl_setopt($ch, CURLOPT_URL, $proxyUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    curl_setopt($ch, CURLOPT_USERAGENT, 'Minu15-App/1.0');
    
    // Execute the request
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    
    if ($debug) {
        $debug_info['geometry_http_code'] = $httpCode;
        $debug_info['geometry_response_size'] = strlen($response);
        if ($response) {
            $debug_info['geometry_response_sample'] = substr($response, 0, 100) . '...';
        }
    }
    
    // Check for cURL errors
    if (curl_errno($ch)) {
        if ($debug) {
            $debug_info['geometry_curl_error'] = curl_error($ch);
            $debug_info['geometry_curl_errno'] = curl_errno($ch);
        }
        curl_close($ch);
        return null;
    }
    
    // Close cURL
    curl_close($ch);
    
    // Check for HTTP errors
    if ($httpCode !== 200) {
        if ($debug) {
            $debug_info['geometry_http_error'] = "HTTP error code: $httpCode";
        }
        return null;
    }
    
    // Parse JSON response
    $data = json_decode($response, true);
    
    // Check for JSON parsing errors
    if ($data === null) {
        if ($debug) {
            $debug_info['geometry_json_error'] = json_last_error_msg();
        }
        return null;
    }
    
    if ($debug) {
        $debug_info['geometry_data_type'] = gettype($data);
        if (is_array($data)) {
            $debug_info['geometry_data_keys'] = array_keys($data);
        }
    }
    
    return $data;
}

/**
 * Fetch location data by municipio name
 */
function fetchLocationByMunicipio($municipio, $debug = false) {
    global $debug_info;
    
    if ($debug) {
        $debug_info['municipio_name_received'] = $municipio;
    }
    
    // Construct the endpoint URL
    $endpoint = "municipio/" . urlencode($municipio);
    
    if ($debug) {
        $debug_info['municipio_endpoint'] = $endpoint;
    }
    
    // Use our proxy with caching
    $proxyUrl = "http://localhost:8000/includes/geoapi_proxy.php?endpoint=" . urlencode($endpoint);
    
    // Initialize cURL
    $ch = curl_init();
    
    // Set cURL options
    curl_setopt($ch, CURLOPT_URL, $proxyUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    curl_setopt($ch, CURLOPT_USERAGENT, 'Minu15-App/1.0');
    
    // Execute the request
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    
    if ($debug) {
        $debug_info['municipio_http_code'] = $httpCode;
        $debug_info['municipio_response_size'] = strlen($response);
        if ($response) {
            $debug_info['municipio_response_sample'] = substr($response, 0, 200) . '...';
        }
    }
    
    // Check for cURL errors
    if (curl_errno($ch)) {
        if ($debug) {
            $debug_info['municipio_curl_error'] = curl_error($ch);
            $debug_info['municipio_curl_errno'] = curl_errno($ch);
        }
        curl_close($ch);
        return null;
    }
    
    // Close cURL
    curl_close($ch);
    
    // Check for HTTP errors
    if ($httpCode !== 200) {
        if ($debug) {
            $debug_info['municipio_http_error'] = "HTTP error code: $httpCode";
        }
        return null;
    }
    
    // Parse JSON response
    $data = json_decode($response, true);
    
    // Check for JSON parsing errors
    if ($data === null) {
        if ($debug) {
            $debug_info['municipio_json_error'] = json_last_error_msg();
        }
        return null;
    }
    
    if ($debug) {
        $debug_info['municipio_data_structure'] = array_keys($data);
    }
    
    // Get geometry data if not already included
    if (!isset($data['geometry']) && !isset($data['geojson'])) {
        $geometryEndpoint = "municipio/" . urlencode($municipio) . "/geometry";
        $geometryData = fetchGeometryFromEndpoint($geometryEndpoint, $debug);
        
        if ($geometryData) {
            $data['geometry'] = $geometryData;
            $data['geojson'] = $geometryData; // Add both keys for consistency
            
            if ($debug) {
                $debug_info['geometry_data_added'] = true;
            }
            
            // Add POI counts if geometry is available
            $poiCounts = fetchPOICounts($geometryData, $debug);
            if ($poiCounts) {
                $data['poi_counts'] = $poiCounts;
                
                if ($debug) {
                    $debug_info['poi_counts_added'] = true;
                }
            }
        }
    }
    
    return $data;
}

/**
 * Fetch location data by distrito name
 */
function fetchLocationByDistrito($distrito, $debug = false) {
    global $debug_info;
    
    if ($debug) {
        $debug_info['distrito_name_received'] = $distrito;
    }
    
    // Construct the endpoint URL
    $endpoint = "distrito/" . urlencode($distrito);
    
    if ($debug) {
        $debug_info['distrito_endpoint'] = $endpoint;
    }
    
    // Use our proxy with caching
    $proxyUrl = "http://localhost:8000/includes/geoapi_proxy.php?endpoint=" . urlencode($endpoint);
    
    // Initialize cURL
    $ch = curl_init();
    
    // Set cURL options
    curl_setopt($ch, CURLOPT_URL, $proxyUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    curl_setopt($ch, CURLOPT_USERAGENT, 'Minu15-App/1.0');
    
    // Execute the request
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    
    if ($debug) {
        $debug_info['distrito_http_code'] = $httpCode;
        $debug_info['distrito_response_size'] = strlen($response);
        if ($response) {
            $debug_info['distrito_response_sample'] = substr($response, 0, 200) . '...';
        }
    }
    
    // Check for cURL errors
    if (curl_errno($ch)) {
        if ($debug) {
            $debug_info['distrito_curl_error'] = curl_error($ch);
            $debug_info['distrito_curl_errno'] = curl_errno($ch);
        }
        curl_close($ch);
        return null;
    }
    
    // Close cURL
    curl_close($ch);
    
    // Check for HTTP errors
    if ($httpCode !== 200) {
        if ($debug) {
            $debug_info['distrito_http_error'] = "HTTP error code: $httpCode";
        }
        return null;
    }
    
    // Parse JSON response
    $data = json_decode($response, true);
    
    // Check for JSON parsing errors
    if ($data === null) {
        if ($debug) {
            $debug_info['distrito_json_error'] = json_last_error_msg();
        }
        return null;
    }
    
    if ($debug) {
        $debug_info['distrito_data_structure'] = array_keys($data);
    }
    
    // Get geometry data if not already included
    if (!isset($data['geometry']) && !isset($data['geojson'])) {
        $geometryEndpoint = "distrito/" . urlencode($distrito) . "/geometry";
        $geometryData = fetchGeometryFromEndpoint($geometryEndpoint, $debug);
        
        if ($geometryData) {
            $data['geometry'] = $geometryData;
            $data['geojson'] = $geometryData; // Add both keys for consistency
            
            if ($debug) {
                $debug_info['geometry_data_added'] = true;
            }
            
            // Add POI counts if geometry is available
            $poiCounts = fetchPOICounts($geometryData, $debug);
            if ($poiCounts) {
                $data['poi_counts'] = $poiCounts;
                
                if ($debug) {
                    $debug_info['poi_counts_added'] = true;
                }
            }
        }
    }
    
    return $data;
}

/**
 * Fetch location data by code (freguesia, municipio, or distrito)
 */
function fetchLocationByCode($code, $type, $municipioName = null, $debug = false) {
    global $debug_info;
    
    // Validate type
    if (!in_array($type, ['freguesia', 'municipio', 'distrito'])) {
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
            // Use the correct endpoint format: /municipio/{municipio}/freguesia/{freguesia}
            $endpoint = "municipio/" . urlencode($municipioName) . "/freguesia/" . urlencode($code);
            break;
        case 'municipio':
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
        $debug_info['concelho_proxy_url'] = $proxyUrl;
    }
    
    // Initialize cURL
    $ch = curl_init();
    
    // Set cURL options
    curl_setopt($ch, CURLOPT_URL, $proxyUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 60); // Increased timeout
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
        $debug_info['code_http_code'] = $httpCode;
        $debug_info['code_response_size'] = strlen($response);
        $debug_info['code_effective_url'] = $effectiveUrl;
        if ($response) {
            $debug_info['code_raw_response_sample'] = substr($response, 0, 200) . '...';
        }
        
        if (isset($verbose)) {
            rewind($verbose);
            $debug_info['code_curl_verbose'] = stream_get_contents($verbose);
        }
    }
    
    // Check for errors
    if (curl_errno($ch)) {
        if ($debug) {
            $debug_info['code_error'] = curl_error($ch);
            $debug_info['code_curl_errno'] = curl_errno($ch);
        }
        curl_close($ch);
        return null;
    }
    
    // Check for non-200 response
    if ($httpCode !== 200) {
        if ($debug) {
            $debug_info['code_http_error'] = "HTTP error code: $httpCode";
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
    } else if ($type === 'municipio' || $type === 'distrito') {
        // Add census data for municipio and distrito
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
        
        // Add geometry data for municipio and distrito
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
        case 'municipio':
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
        case 'municipio':
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
    $effectiveUrl = curl_getinfo($ch, CURLINFO_EFFECTIVE_URL);
    
    if ($debug) {
        $debug_info['geometry_http_code'] = $httpCode;
        $debug_info['geometry_response_size'] = strlen($response);
        $debug_info['geometry_effective_url'] = $effectiveUrl;
        
        // Add a small sample of the response for debugging
        if ($response) {
            $debug_info['geometry_raw_response_sample'] = substr($response, 0, 200) . '...';
        }
        
        if (isset($verbose)) {
            rewind($verbose);
            $debug_info['geometry_curl_verbose'] = stream_get_contents($verbose);
        }
    }
    
    // Check for errors
    if (curl_errno($ch)) {
        if ($debug) {
            $debug_info['geometry_error'] = curl_error($ch) . ' (cURL error number: ' . curl_errno($ch) . ')';
        }
        curl_close($ch);
        return null;
    }
    
    // Check for non-200 response
    if ($httpCode !== 200) {
        if ($debug) {
            $debug_info['geometry_http_error'] = "HTTP error code: $httpCode";
            $debug_info['geometry_http_error_response'] = $response;
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
                    $debug_info['geometry_root_type'] = $geometryData['type'];
                }
                
                if (isset($geometryData['geometry']) && isset($geometryData['geometry']['type'])) {
                    $debug_info['geometry_nested_type'] = $geometryData['geometry']['type'];
                }
                
                if (isset($geometryData['features']) && is_array($geometryData['features'])) {
                    $debug_info['geometry_features_count'] = count($geometryData['features']);
                }
            }
        } else {
            $debug_info['geometry_error'] = 'Failed to parse geometry JSON';
            $debug_info['geometry_json_error'] = json_last_error_msg();
            $debug_info['geometry_raw_response_unparsable'] = $response;
        }
    }
    
    // For distrito and municipio, we need to extract the actual GeoJSON
    if ($type === 'distrito' || $type === 'municipio' || $type === 'freguesia') {
        // Check if we have a valid GeoJSON response
        if (isset($geometryData['type']) && $geometryData['type'] === 'Feature' && 
            isset($geometryData['geometry'])) {
            
            if ($debug) {
                $debug_info['geometry_valid_feature'] = true;
            }
            
            return $geometryData; // This is already a Feature object
        } else if (isset($geometryData['type']) && $geometryData['type'] === 'FeatureCollection' && 
                   isset($geometryData['features']) && count($geometryData['features']) > 0) {
            
            if ($debug) {
                $debug_info['geometry_valid_feature_collection'] = true;
                $debug_info['geometry_feature_collection_first_feature_type'] = $geometryData['features'][0]['geometry']['type'] ?? 'unknown';
            }
            return $geometryData;
        } else if (isset($geometryData['type']) && ($geometryData['type'] === 'Polygon' || $geometryData['type'] === 'MultiPolygon')) {
            if ($debug) {
                $debug_info['geometry_valid_direct_geometry'] = true;
            }
            // If it's a direct Geometry object, wrap it in a Feature
            return [
                'type' => 'Feature',
                'geometry' => $geometryData,
                'properties' => []
            ];
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