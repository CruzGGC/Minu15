<?php
/**
 * Fetch Location Data Endpoint
 * Retrieves data for a specific location (freguesia, concelho, or distrito)
 * Combines data from GeoAPI.pt and Geofabrik database
 * 
 * @version 1.0
 */

// Prevent any output before JSON response
error_reporting(0);
ini_set('display_errors', 0);

// Include database configuration
require_once '../config/db_config.php';

// Set headers for JSON response
header('Content-Type: application/json');

// Check request method
if ($_SERVER['REQUEST_METHOD'] !== 'GET' && $_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode([
        'success' => false,
        'message' => 'Only GET and POST methods are allowed'
    ]);
    exit;
}

// Get parameters from request
$lat = isset($_REQUEST['lat']) ? floatval($_REQUEST['lat']) : null;
$lng = isset($_REQUEST['lng']) ? floatval($_REQUEST['lng']) : null;
$freguesia = isset($_REQUEST['freguesia']) ? $_REQUEST['freguesia'] : null;
$concelho = isset($_REQUEST['concelho']) ? $_REQUEST['concelho'] : null;
$distrito = isset($_REQUEST['distrito']) ? $_REQUEST['distrito'] : null;
$code = isset($_REQUEST['code']) ? $_REQUEST['code'] : null;
$type = isset($_REQUEST['type']) ? $_REQUEST['type'] : null;

// Debug information array
$debug_info = [];

// Initialize response data
$responseData = [
    'success' => false,
    'message' => 'Invalid request parameters',
    'data' => null,
    'debug' => $debug_info
];

// If we have coordinates, use reverse geocoding
if ($lat !== null && $lng !== null) {
    $locationData = fetchLocationByCoordinates($lat, $lng);
    
    if ($locationData) {
        $responseData['success'] = true;
        $responseData['message'] = 'Location data retrieved successfully';
        $responseData['data'] = $locationData;
    } else {
        $responseData['message'] = 'Failed to retrieve location data for coordinates';
    }
}
// If we have a freguesia name and concelho, fetch by freguesia
else if ($freguesia && $concelho) {
    $locationData = fetchLocationByFreguesia($freguesia, $concelho);
    
    if ($locationData) {
        $responseData['success'] = true;
        $responseData['message'] = 'Location data retrieved successfully';
        $responseData['data'] = $locationData;
    } else {
        $responseData['message'] = 'Failed to retrieve location data for freguesia';
    }
}
// If we have a concelho name, fetch by concelho
else if ($concelho) {
    $locationData = fetchLocationByConcelho($concelho);
    
    if ($locationData) {
        $responseData['success'] = true;
        $responseData['message'] = 'Location data retrieved successfully';
        $responseData['data'] = $locationData;
    } else {
        $responseData['message'] = 'Failed to retrieve location data for concelho';
    }
}
// If we have a distrito name, fetch by distrito
else if ($distrito) {
    $locationData = fetchLocationByDistrito($distrito);
    
    if ($locationData) {
        $responseData['success'] = true;
        $responseData['message'] = 'Location data retrieved successfully';
        $responseData['data'] = $locationData;
    } else {
        $responseData['message'] = 'Failed to retrieve location data for distrito';
    }
}
// If we have a code and type, fetch by code
else if ($code && $type) {
    $locationData = fetchLocationByCode($code, $type);
    
    if ($locationData) {
        $responseData['success'] = true;
        $responseData['message'] = 'Location data retrieved successfully';
        $responseData['data'] = $locationData;
    } else {
        $responseData['message'] = 'Failed to retrieve location data for code';
    }
}

// Return the response
echo json_encode($responseData);

/**
 * Fetch location data by coordinates using GeoAPI.pt
 */
function fetchLocationByCoordinates($lat, $lng) {
    // Create the endpoint URL for reverse geocoding
    $endpoint = "gps/{$lat},{$lng}";
    
    // Use our proxy with caching
    $proxyUrl = "../includes/geoapi_proxy.php?endpoint=" . urlencode($endpoint);
    
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
    
    // Check for errors
    if (curl_errno($ch) || $httpCode !== 200) {
        curl_close($ch);
        return null;
    }
    
    // Close cURL
    curl_close($ch);
    
    // Parse the response
    $data = json_decode($response, true);
    
    if (!$data || !isset($data['freguesia'])) {
        return null;
    }
    
    // Get the freguesia code
    $freguesiaCode = $data['freguesia']['codigo'] ?? null;
    $municipioNome = $data['municipio']['nome'] ?? null;
    
    if (!$freguesiaCode || !$municipioNome) {
        return null;
    }
    
    // Fetch detailed data for the freguesia
    return fetchLocationByCode($freguesiaCode, 'freguesia', $municipioNome);
}

/**
 * Fetch location data by freguesia name
 */
function fetchLocationByFreguesia($freguesia, $concelho) {
    // Create the endpoint URL for freguesia search
    $endpoint = "municipio/" . urlencode($concelho) . "/freguesia/" . urlencode($freguesia);
    
    // Use our proxy with caching
    $proxyUrl = "../includes/geoapi_proxy.php?endpoint=" . urlencode($endpoint);
    
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
    
    // Check for errors
    if (curl_errno($ch) || $httpCode !== 200) {
        curl_close($ch);
        return null;
    }
    
    // Close cURL
    curl_close($ch);
    
    // Parse the response
    $data = json_decode($response, true);
    
    if (!$data || !isset($data['codigo'])) {
        return null;
    }
    
    // Get the freguesia code
    $freguesiaCode = $data['codigo'] ?? null;
    
    if (!$freguesiaCode) {
        return null;
    }
    
    // Fetch detailed data for the freguesia
    return fetchLocationByCode($freguesiaCode, 'freguesia', $concelho);
}

/**
 * Fetch location data by concelho name
 */
function fetchLocationByConcelho($concelho) {
    // Create the endpoint URL for concelho search
    $endpoint = "municipio/" . urlencode($concelho);
    
    // Use our proxy with caching
    $proxyUrl = "../includes/geoapi_proxy.php?endpoint=" . urlencode($endpoint);
    
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
    
    // Check for errors
    if (curl_errno($ch) || $httpCode !== 200) {
        curl_close($ch);
        return null;
    }
    
    // Close cURL
    curl_close($ch);
    
    // Parse the response
    $data = json_decode($response, true);
    
    if (!$data || !isset($data['codigo'])) {
        return null;
    }
    
    // Get the concelho code
    $concelhoCode = $data['codigo'] ?? null;
    
    if (!$concelhoCode) {
        return null;
    }
    
    // Fetch detailed data for the concelho
    return fetchLocationByCode($concelhoCode, 'concelho');
}

/**
 * Fetch location data by distrito name
 */
function fetchLocationByDistrito($distrito) {
    // Create the endpoint URL for distrito search
    $endpoint = "distrito/" . urlencode($distrito);
    
    // Use our proxy with caching
    $proxyUrl = "../includes/geoapi_proxy.php?endpoint=" . urlencode($endpoint);
    
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
    
    // Check for errors
    if (curl_errno($ch) || $httpCode !== 200) {
        curl_close($ch);
        return null;
    }
    
    // Close cURL
    curl_close($ch);
    
    // Parse the response
    $data = json_decode($response, true);
    
    if (!$data || !isset($data['codigo'])) {
        return null;
    }
    
    // Get the distrito code
    $distritoCode = $data['codigo'] ?? null;
    
    if (!$distritoCode) {
        return null;
    }
    
    // Fetch detailed data for the distrito
    return fetchLocationByCode($distritoCode, 'distrito');
}

/**
 * Fetch location data by code (freguesia, concelho, or distrito)
 */
function fetchLocationByCode($code, $type, $municipioName = null) {
    // Validate type
    if (!in_array($type, ['freguesia', 'concelho', 'distrito'])) {
        return null;
    }
    
    // Create the endpoint URL based on type
    switch ($type) {
        case 'freguesia':
            if (!$municipioName) {
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
    
    // Use our proxy with caching
    $proxyUrl = "../includes/geoapi_proxy.php?endpoint=" . urlencode($endpoint);
    
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
    
    // Check for errors
    if (curl_errno($ch) || $httpCode !== 200) {
        curl_close($ch);
        return null;
    }
    
    // Close cURL
    curl_close($ch);
    
    // Parse the response
    $locationData = json_decode($response, true);
    
    if (!$locationData) {
        return null;
    }
    
    // Add additional data based on type
    if ($type === 'freguesia') {
        // Add census data
        $censusData = fetchCensusData($code, $type, $municipioName);
        if ($censusData) {
            $locationData['censos2021'] = isset($censusData['censos2021']) ? $censusData['censos2021'] : null;
            $locationData['censos2011'] = isset($censusData['censos2011']) ? $censusData['censos2011'] : null;
        }
        
        // Add geometry data
        $geometryData = fetchGeometryData($code, $type, $municipioName);
        if ($geometryData) {
            $locationData['geometry'] = $geometryData;
            $locationData['geojson'] = $geometryData; // Add both keys for consistency
            
            // Add POI counts if geometry is available
            $poiCounts = fetchPOICounts($geometryData);
            if ($poiCounts) {
                $locationData['poi_counts'] = $poiCounts;
            }
        }
    } else if ($type === 'concelho' || $type === 'distrito') {
        // Add census data for concelho and distrito
        $censusData = fetchCensusData($code, $type, $municipioName);
        if ($censusData) {
            $locationData['censos2021'] = isset($censusData['censos2021']) ? $censusData['censos2021'] : null;
            $locationData['censos2011'] = isset($censusData['censos2011']) ? $censusData['censos2011'] : null;
        }
        
        // Add geometry data for concelho and distrito
        $geometryData = fetchGeometryData($code, $type, $municipioName);
        if ($geometryData) {
            $locationData['geometry'] = $geometryData;
            $locationData['geojson'] = $geometryData; // Add both keys for consistency
        }
    }
    
    return $locationData;
}

/**
 * Fetch census data for a location
 */
function fetchCensusData($code, $type, $municipioName = null) {
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
    $proxyUrl = "../includes/geoapi_proxy.php?endpoint=" . urlencode($endpoint);
    
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
function fetchGeometryData($code, $type, $municipioName = null) {
    // Create the endpoint URL based on type
    switch ($type) {
        case 'freguesia':
            if (!$municipioName) {
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
            return null;
    }
    
    // Use our proxy with caching
    $proxyUrl = "../includes/geoapi_proxy.php?endpoint=" . urlencode($endpoint);
    
    // Initialize cURL
    $ch = curl_init();
    
    // Set cURL options
    curl_setopt($ch, CURLOPT_URL, $proxyUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 60); // Increased timeout for geometry data
    
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
    $geometryData = json_decode($response, true);
    
    // For distrito and concelho, we need to extract the actual GeoJSON
    if ($type === 'distrito' || $type === 'concelho') {
        // Check if we have a valid GeoJSON response
        if (isset($geometryData['type']) && $geometryData['type'] === 'Feature' && 
            isset($geometryData['geometry'])) {
            return $geometryData;
        }
    }
    
    return $geometryData;
}

/**
 * Fetch POI counts from Geofabrik database
 */
function fetchPOICounts($geometryData) {
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