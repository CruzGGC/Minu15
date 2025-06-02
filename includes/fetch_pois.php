<?php
/**
 * Fetch Points of Interest (POIs) Endpoint
 * Gets OSM points of interest that are strictly contained within the isochrone polygon
 * Now queries both point and polygon OSM data
 * 
 * @version 2.2
 */

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);
ini_set('error_log', '../logs/db_errors.log');

// Include database configuration
require_once '../config/db_config.php';

// Include mock data provider
require_once 'mock_data.php';

// Set headers for JSON response
header('Content-Type: application/json');

try {
    // Check request method
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        echo json_encode([
            'success' => false,
            'message' => 'Only POST method is allowed'
        ]);
        exit;
    }

    // Check if all required parameters are provided
    if (empty($_POST['type']) || !isset($_POST['lat']) || !isset($_POST['lng'])) {
        echo json_encode([
            'success' => false,
            'message' => 'Missing required parameters: type, lat, lng'
        ]);
        exit;
    }

    // Get parameters from request
    $type = $_POST['type'];
    $lat = floatval($_POST['lat']);
    $lng = floatval($_POST['lng']);

    // Get isochrone JSON (required for precise POI filtering)
    $isochroneJson = isset($_POST['isochrone']) ? $_POST['isochrone'] : null;

    // If no isochrone is provided but radius is, get radius for fallback buffer
    $radius = isset($_POST['radius']) ? floatval($_POST['radius']) : 0;

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

    // Define POI types with their PostgreSQL query conditions
    $poiTypes = [
        // === Health ===
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
        
        // === Education ===
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
        
        // === Commercial & Services ===
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
        
        // === Safety ===
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
        
        // === Public Administration ===
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
        
        // === Culture & Leisure ===
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

    // Check if the requested POI type exists
    if (!array_key_exists($type, $poiTypes)) {
        echo json_encode([
            'success' => false,
            'message' => 'Invalid POI type: ' . $type
        ]);
        exit;
    }

    // Debug information for monitoring query execution
    $debug_info = [];

    // Try to get database connection
    try {
        $conn = getDbConnection();
        $debug_info['db_connection'] = 'success';
    } catch (Exception $e) {
        // Return mock POI data if database connection fails
        $mockPois = generateMockPOIs($type, $lat, $lng, $radius);
        
        echo json_encode([
            'success' => true,
            'pois' => $mockPois,
            'count' => count($mockPois),
            'is_mock' => true,
            'message' => 'Using mock data (database connection failed): ' . $e->getMessage(),
            'debug' => ['error' => $e->getMessage()]
        ]);
        exit;
    }

    // Prepare spatial condition based on isochrone or radius
    $spatialCondition = "";

    // If we have isochrone data, use that (preferred method)
    if ($isochroneJson) {
        try {
            // Parse the GeoJSON
            $isochrone = json_decode($isochroneJson, true);
            $debug_info['isochrone_parsed'] = true;
            
            // Extract the geometry from the first feature
            if (isset($isochrone['features']) && 
                isset($isochrone['features'][0]) && 
                isset($isochrone['features'][0]['geometry'])) {
                
                $geometry = json_encode($isochrone['features'][0]['geometry']);
                $debug_info['geometry_extracted'] = true;
                
                // Create a PostgreSQL spatial condition that uses ST_Contains to only
                // include POIs that are strictly inside the isochrone polygon
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
                throw new Exception("Invalid isochrone GeoJSON structure");
            }
        } catch (Exception $e) {
            $debug_info['isochrone_error'] = $e->getMessage();
            
            // If there's an error with the isochrone, fall back to radius buffer
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
                    'message' => 'Error processing isochrone and no fallback radius provided',
                    'debug' => $debug_info
                ]);
                exit;
            }
        }
    } else if ($radius > 0) {
        // If no isochrone is provided but we have radius, use a simple buffer
        $spatialCondition = "ST_DWithin(
            way, 
            ST_Transform(ST_SetSRID(ST_MakePoint($lng, $lat), 4326), 3857), 
            $radius
        )";
        $debug_info['using_radius'] = true;
    } else {
        // This shouldn't happen due to earlier validation, but just in case
        echo json_encode([
            'success' => false,
            'message' => 'No spatial filtering method available'
        ]);
        exit;
    }

    // Get the condition for the requested POI type
    $poiCondition = $poiTypes[$type]['condition'];

    // Prepare the query to get POIs from both point and polygon tables
    // Using UNION to combine results from both tables
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

    // Execute the query
    try {
        $result = executeQuery($conn, $query);
        $debug_info['query_executed'] = true;
    } catch (Exception $e) {
        echo json_encode([
            'success' => false,
            'message' => 'Query execution failed: ' . $e->getMessage(),
            'debug' => array_merge($debug_info, ['query_error' => $e->getMessage()])
        ]);
        exit;
    }

    // Process the results
    $pois = [];
    while ($row = pg_fetch_assoc($result)) {
        // Only add POIs that have coordinates
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

    // Close the database connection
    pg_close($conn);

    // Return the POIs as JSON
    echo json_encode([
        'success' => true,
        'pois' => $pois,
        'count' => count($pois),
        'debug' => $debug_info
    ]);

} catch (Exception $e) {
    // Catch any uncaught exceptions
    error_log('Uncaught exception in fetch_pois.php: ' . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'An unexpected error occurred: ' . $e->getMessage()
    ]);
}
?>