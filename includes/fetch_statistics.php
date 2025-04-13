<?php
/**
 * Fetch Statistics endpoint - Gets area statistics within the isochrone
 * Ensures that only POIs within the isochrone area are counted
 */

// Prevent any output before JSON
error_reporting(0);
ini_set('display_errors', 0);

// Include database configuration
require_once '../config/db_config.php';

// Set headers for JSON response
header('Content-Type: application/json');

// Check if all required parameters are provided
if (!isset($_POST['lat']) || !isset($_POST['lng']) || !isset($_POST['radius'])) {
    echo json_encode([
        'success' => false,
        'message' => 'Missing required parameters'
    ]);
    exit;
}

// Get parameters from request
$lat = floatval($_POST['lat']);
$lng = floatval($_POST['lng']);
$radius = floatval($_POST['radius']); // Radius in meters
$isochroneJson = isset($_POST['isochrone']) ? $_POST['isochrone'] : null;

// Validate parameters
if (!is_numeric($lat) || !is_numeric($lng) || !is_numeric($radius) || $radius <= 0) {
    echo json_encode([
        'success' => false,
        'message' => 'Invalid parameters'
    ]);
    exit;
}

// Get database connection
$conn = getDbConnection();

// Variables to store the geometry
$bufferGeoJSON = null;
$areaKm2 = null;
$spatialCondition = "";
$debug_info = [];

// If we have an isochrone polygon, use it for more accurate statistics
if ($isochroneJson) {
    try {
        // Decode the GeoJSON
        $isochrone = json_decode($isochroneJson, true);
        $debug_info['isochrone_parsed'] = true;
        
        // Extract the first feature's geometry (the isochrone polygon)
        if (isset($isochrone['features']) && isset($isochrone['features'][0])) {
            // Get the area from the isochrone properties if available
            if (isset($isochrone['features'][0]['properties']) && isset($isochrone['features'][0]['properties']['area'])) {
                $areaKm2 = $isochrone['features'][0]['properties']['area'];
                $debug_info['area_from_isochrone'] = $areaKm2;
            }
            
            // Get the geometry
            if (isset($isochrone['features'][0]['geometry'])) {
                $geometry = json_encode($isochrone['features'][0]['geometry']);
                $bufferGeoJSON = $geometry;
                $debug_info['geometry_extracted'] = true;
                
                // Define spatial condition using the isochrone polygon
                // This ensures POIs are strictly inside the isochrone
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
            }
        } else {
            $debug_info['invalid_features'] = true;
        }
    } catch (Exception $e) {
        // If there's an error, log it and fallback to buffer
        error_log("Error parsing isochrone GeoJSON: " . $e->getMessage());
        $debug_info['exception'] = $e->getMessage();
        $isochroneJson = null;
    }
}

// If we don't have a valid isochrone or there was an error, use traditional buffer
if (!$isochroneJson || empty($spatialCondition)) {
    $debug_info['using_buffer'] = true;
    
    // Create a buffer polygon around the point
    $bufferQuery = "
        SELECT 
            ST_AsGeoJSON(ST_Buffer(
                ST_Transform(ST_SetSRID(ST_MakePoint($lng, $lat), 4326), 3857), 
                $radius
            )) as buffer_geom,
            ST_Area(ST_Buffer(
                ST_Transform(ST_SetSRID(ST_MakePoint($lng, $lat), 4326), 3857), 
                $radius
            )) / 1000000 as area_km2
    ";

    // Execute buffer query
    $bufferResult = pg_query($conn, $bufferQuery);

    if (!$bufferResult) {
        echo json_encode([
            'success' => false,
            'message' => 'Database query error: ' . pg_last_error($conn)
        ]);
        exit;
    }

    $bufferData = pg_fetch_assoc($bufferResult);
    $bufferGeoJSON = $bufferData['buffer_geom'];
    $areaKm2 = $bufferData['area_km2'];
    $debug_info['area_from_buffer'] = $areaKm2;
    
    // Define spatial condition using buffer
    // This ensures points are strictly inside the buffer
    $spatialCondition = "ST_DWithin(
        way, 
        ST_Transform(ST_SetSRID(ST_MakePoint($lng, $lat), 4326), 3857), 
        $radius
    )";
}

// Define the POI categories to count
// These must match the POI types in fetch_pois.php
$poiCategories = [
    // Saúde
    'hospitals' => "amenity = 'hospital'",
    'health_centers' => "amenity = 'clinic' OR amenity = 'doctors'",
    'pharmacies' => "amenity = 'pharmacy'",
    'dentists' => "amenity = 'dentist'",
    
    // Educação
    'schools' => "amenity = 'school'",
    'universities' => "amenity IN ('university', 'college')",
    'kindergartens' => "amenity = 'kindergarten'",
    'libraries' => "amenity = 'library'",
    
    // Comércio e serviços
    'supermarkets' => "shop IN ('supermarket', 'grocery', 'convenience')",
    'malls' => "shop = 'mall' OR amenity = 'marketplace'",
    'restaurants' => "amenity IN ('restaurant', 'fast_food', 'cafe', 'bar', 'pub')",
    'atms' => "amenity = 'atm' OR amenity = 'bank'",
    'banks' => "amenity = 'bank'",
    
    // Segurança e emergência
    'police' => "amenity = 'police'",
    'fire_stations' => "amenity = 'fire_station'",
    'civil_protection' => "amenity = 'ranger_station' OR (office = 'government' AND name ILIKE '%proteção civil%')",
    
    // Transporte
    'bus_stops' => "highway = 'bus_stop'",
    'subway_stations' => "railway = 'station' OR railway = 'subway_entrance'",
    'train_stations' => "railway = 'station'",
    'bike_parkings' => "amenity = 'bicycle_parking'",
    
    // Administração
    'police_stations' => "amenity = 'police'",
    'parish_councils' => "office = 'government' AND name ILIKE '%junta de freguesia%'",
    'parishes' => "office = 'government' AND name ILIKE '%junta de freguesia%'",
    'city_halls' => "office = 'government' AND (name ILIKE '%câmara municipal%' OR name ILIKE '%camara municipal%')",
    'post_offices' => "amenity = 'post_office'",
    
    // Cultura e lazer
    'museums' => "tourism = 'museum' OR amenity = 'museum'",
    'theaters' => "amenity = 'theatre'",
    'sports' => "leisure IN ('sports_centre', 'stadium', 'pitch', 'swimming_pool', 'fitness_centre', 'fitness_station')",
    'parks' => "leisure IN ('park', 'garden', 'playground')"
];

// Initialize statistics array
$statistics = [
    'area_km2' => (float) $areaKm2
];

// Count POIs within the defined area for each category
foreach ($poiCategories as $category => $condition) {
    $countQuery = "
        SELECT 
            COUNT(*) as count 
        FROM 
            planet_osm_point 
        WHERE 
            $condition 
            AND $spatialCondition
    ";
    
    $countResult = pg_query($conn, $countQuery);
    
    if (!$countResult) {
        echo json_encode([
            'success' => false,
            'message' => "Error counting $category: " . pg_last_error($conn)
        ]);
        exit;
    }
    
    $countData = pg_fetch_assoc($countResult);
    $statistics[$category] = (int) $countData['count'];
}

// Estimate population based on residential buildings and average people per building
// This is a rough estimation and should be refined with actual data if available
$populationQuery = "
    SELECT 
        COUNT(*) as building_count 
    FROM 
        planet_osm_polygon 
    WHERE 
        building IN ('residential', 'apartments', 'house', 'detached') 
        AND $spatialCondition
";

$populationResult = pg_query($conn, $populationQuery);

if (!$populationResult) {
    $statistics['population_estimate'] = 'Not available';
} else {
    $populationData = pg_fetch_assoc($populationResult);
    $buildingCount = (int) $populationData['building_count'];
    
    // Rough estimate: 2.5 people per residential building
    $statistics['population_estimate'] = round($buildingCount * 2.5);
}

// Get parish information if available
$parishQuery = "
    SELECT 
        name,
        admin_level
    FROM 
        planet_osm_polygon 
    WHERE 
        admin_level IN ('9', '10') 
        AND ST_Contains(
            way,
            ST_Transform(ST_SetSRID(ST_MakePoint($lng, $lat), 4326), 3857)
        )
    ORDER BY 
        admin_level DESC
    LIMIT 1
";

$parishResult = pg_query($conn, $parishQuery);

if ($parishResult && pg_num_rows($parishResult) > 0) {
    $parishData = pg_fetch_assoc($parishResult);
    $statistics['parish'] = $parishData['name'];
} else {
    $statistics['parish'] = 'Unknown';
}

// Get municipality information if available
$municipalityQuery = "
    SELECT 
        name,
        admin_level
    FROM 
        planet_osm_polygon 
    WHERE 
        admin_level = '8' 
        AND ST_Contains(
            way,
            ST_Transform(ST_SetSRID(ST_MakePoint($lng, $lat), 4326), 3857)
        )
    LIMIT 1
";

$municipalityResult = pg_query($conn, $municipalityQuery);

if ($municipalityResult && pg_num_rows($municipalityResult) > 0) {
    $municipalityData = pg_fetch_assoc($municipalityResult);
    $statistics['municipality'] = $municipalityData['name'];
} else {
    $statistics['municipality'] = 'Unknown';
}

// Return the statistics
echo json_encode([
    'success' => true,
    'stats' => $statistics,
    'debug' => $debug_info
]);

// Close the database connection
pg_close($conn);
?>