<?php
/**
 * Fetch Area Statistics Endpoint
 * Calculates statistics for an area defined by an isochrone polygon or radius
 * Now includes both point and polygon POIs for accurate counting
 * 
 * @version 2.1
 */

// Prevent any output before JSON response
error_reporting(0);
ini_set('display_errors', 0);

// Include database configuration
require_once '../config/db_config.php';

// Set headers for JSON response
header('Content-Type: application/json');

// Check request method
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode([
        'success' => false,
        'message' => 'Only POST method is allowed'
    ]);
    exit;
}

// Check if all required parameters are provided
if (!isset($_POST['lat']) || !isset($_POST['lng'])) {
    echo json_encode([
        'success' => false,
        'message' => 'Missing required parameters: lat, lng'
    ]);
    exit;
}

// Get parameters from request
$lat = floatval($_POST['lat']);
$lng = floatval($_POST['lng']);

// Get isochrone JSON (preferred for precise area calculation)
$isochroneJson = isset($_POST['isochrone']) ? $_POST['isochrone'] : null;

// If no isochrone is provided but radius is, use radius for fallback calculation
$radius = isset($_POST['radius']) ? floatval($_POST['radius']) : 0;

// Get selected POI types for statistics if provided
$selectedPOIs = isset($_POST['selected_pois']) ? json_decode($_POST['selected_pois'], true) : null;

// Validate parameters
if (!is_numeric($lat) || !is_numeric($lng) || $lat < -90 || $lat > 90 || $lng < -180 || $lng > 180) {
    echo json_encode([
        'success' => false,
        'message' => 'Invalid latitude or longitude'
    ]);
    exit;
}

// If no isochrone and no radius, cannot proceed
if (empty($isochroneJson) && $radius <= 0) {
    echo json_encode([
        'success' => false,
        'message' => 'Either isochrone GeoJSON or radius is required'
    ]);
    exit;
}

// Get database connection
$conn = getDbConnection();

// Debug information array
$debug_info = [];

// Variables to store the geometry and area information
$areaKm2 = null;
$spatialCondition = "";
$bufferGeometry = null;

// If we have isochrone data, use that for precise area calculation
if ($isochroneJson) {
    try {
        // Parse the GeoJSON
        $isochrone = json_decode($isochroneJson, true);
        $debug_info['isochrone_parsed'] = true;
        
        // Extract the first feature's geometry and properties
        if (isset($isochrone['features']) && isset($isochrone['features'][0])) {
            // Get area directly from the isochrone properties if available
            if (isset($isochrone['features'][0]['properties']) && 
                isset($isochrone['features'][0]['properties']['area'])) {
                $areaKm2 = floatval($isochrone['features'][0]['properties']['area']);
                $debug_info['area_from_isochrone'] = $areaKm2;
            }
            
            // Get the geometry
            if (isset($isochrone['features'][0]['geometry'])) {
                $geometry = json_encode($isochrone['features'][0]['geometry']);
                $bufferGeometry = $geometry;
                $debug_info['geometry_extracted'] = true;
                
                // Create spatial condition for POI counting
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
                
                // If we didn't get the area from properties, calculate it
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
                    
                    $areaResult = pg_query($conn, $areaQuery);
                    if ($areaResult && $areaRow = pg_fetch_assoc($areaResult)) {
                        $areaKm2 = floatval($areaRow['area_km2']);
                        $debug_info['area_calculated'] = $areaKm2;
                    }
                }
            } else {
                throw new Exception("Missing geometry in isochrone GeoJSON");
            }
        } else {
            throw new Exception("Invalid isochrone GeoJSON structure");
        }
    } catch (Exception $e) {
        $debug_info['isochrone_error'] = $e->getMessage();
        
        // If there's an error with the isochrone, fall back to radius buffer
        if ($radius > 0) {
            $debug_info['using_fallback_buffer'] = true;
            useBufferFallback($conn, $lat, $lng, $radius, $spatialCondition, $bufferGeometry, $areaKm2, $debug_info);
        } else {
            echo json_encode([
                'success' => false,
                'message' => 'Error processing isochrone data and no fallback radius provided',
                'debug' => $debug_info
            ]);
            exit;
        }
    }
} else if ($radius > 0) {
    // If no isochrone is provided but we have radius, use a simple buffer
    $debug_info['using_buffer'] = true;
    useBufferFallback($conn, $lat, $lng, $radius, $spatialCondition, $bufferGeometry, $areaKm2, $debug_info);
} else {
    // This shouldn't happen due to earlier validation, but just in case
    echo json_encode([
        'success' => false,
        'message' => 'No spatial filtering method available'
    ]);
    exit;
}

// Define all POI categories to count
$poiCategories = [
    // === Health ===
    'hospitals' => "amenity = 'hospital'",
    'health_centers' => "amenity IN ('clinic', 'doctors')",
    'pharmacies' => "amenity = 'pharmacy'",
    'dentists' => "amenity = 'dentist'",
    
    // === Education ===
    'schools' => "amenity = 'school'",
    'universities' => "amenity = 'university'",
    'kindergartens' => "amenity = 'kindergarten'",
    'libraries' => "amenity = 'library'",
    
    // === Safety ===
    'police_stations' => "amenity = 'police'",
    'fire_stations' => "amenity = 'fire_station'",
    'civil_protection' => "office = 'government' OR emergency = 'ambulance' OR emergency = 'disaster_response'",
    
    // === Public Administration ===
    'parish_councils' => "office = 'government' AND admin_level = '9'",
    'city_halls' => "office = 'government' AND admin_level = '8'",
    'post_offices' => "amenity = 'post_office'",
    
    // === Culture & Leisure ===
    'museums' => "tourism = 'museum' OR amenity = 'arts_centre'",
    'theaters' => "amenity = 'theatre'",
    'sports' => "leisure IN ('sports_centre', 'stadium', 'pitch', 'swimming_pool', 'fitness_centre', 'fitness_station')",
    'parks' => "leisure IN ('park', 'garden', 'playground')"
];

// Filter to only count selected POI types if specified
if ($selectedPOIs !== null && is_array($selectedPOIs)) {
    $filteredCategories = [];
    foreach ($selectedPOIs as $poiType) {
        if (isset($poiCategories[$poiType])) {
            $filteredCategories[$poiType] = $poiCategories[$poiType];
        }
    }
    
    // If there are valid selected types, use those instead
    if (!empty($filteredCategories)) {
        $poiCategories = $filteredCategories;
        $debug_info['using_filtered_pois'] = true;
    }
}

// Initialize statistics array with area information
$statistics = [
    'area_km2' => (float) $areaKm2
];

// Count each POI category within the defined area - combine points and polygons
foreach ($poiCategories as $category => $condition) {
    // Query that counts both points and polygons
    $countQuery = "
        SELECT 
            (
                SELECT COUNT(*) FROM planet_osm_point 
                WHERE ($condition) AND $spatialCondition
            ) +
            (
                SELECT COUNT(*) FROM planet_osm_polygon 
                WHERE ($condition) AND $spatialCondition
            ) as count
    ";
    
    $countResult = pg_query($conn, $countQuery);
    
    if (!$countResult) {
        $statistics[$category] = 0;
        $debug_info['query_error_' . $category] = pg_last_error($conn);
    } else {
        $countRow = pg_fetch_assoc($countResult);
        $statistics[$category] = (int) $countRow['count'];
    }
}

// Count buildings for population estimation
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

$buildingResult = pg_query($conn, $buildingQuery);
$buildingCount = 0;

if ($buildingResult && $buildingRow = pg_fetch_assoc($buildingResult)) {
    $buildingCount = (int) $buildingRow['count'];
    // Rough estimate of population: ~2.5 people per building
    $statistics['population_estimate'] = round($buildingCount * 2.5);
    $debug_info['building_count'] = $buildingCount;
}

// Get administrative area information (parish and municipality)
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

$adminResult = pg_query($conn, $adminQuery);

if (!$adminResult) {
    $statistics['parish'] = 'Unknown';
    $statistics['municipality'] = 'Unknown';
    $debug_info['admin_error'] = pg_last_error($conn);
} else {
    $statistics['parish'] = 'Unknown';
    $statistics['municipality'] = 'Unknown';
    
    while ($adminRow = pg_fetch_assoc($adminResult)) {
        if ($adminRow['admin_level'] === '10' || $adminRow['admin_level'] === '9') {
            $statistics['parish'] = $adminRow['name'];
        } else if ($adminRow['admin_level'] === '8') {
            $statistics['municipality'] = $adminRow['name'];
        }
    }
}

// Return the statistics
echo json_encode([
    'success' => true,
    'stats' => $statistics,
    'debug' => $debug_info
]);

// Close the database connection
pg_close($conn);

// Helper function to use the buffer fallback method
function useBufferFallback($conn, $lat, $lng, $radius, &$spatialCondition, &$bufferGeometry, &$areaKm2, &$debug_info) {
    // Create a buffer polygon around the point
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

    // Execute buffer query
    $bufferResult = pg_query($conn, $bufferQuery);

    if (!$bufferResult) {
        throw new Exception('Buffer generation failed: ' . pg_last_error($conn));
    }

    $bufferData = pg_fetch_assoc($bufferResult);
    $bufferGeometry = $bufferData['buffer_geom'];
    $areaKm2 = $bufferData['area_km2'];
    
    // Define spatial condition using buffer
    $spatialCondition = "ST_DWithin(
        way, 
        ST_Transform(ST_SetSRID(ST_MakePoint($lng, $lat), 4326), 3857), 
        $radius
    )";
    
    $debug_info['buffer_area'] = $areaKm2;
}
?>