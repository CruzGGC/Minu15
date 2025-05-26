<?php
/**
 * Fetch Points of Interest (POIs) Endpoint
 * Gets OSM points of interest that are strictly contained within the isochrone polygon
 * Now queries both point and polygon OSM data
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

// Get database connection
$conn = getDbConnection();

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
        'condition' => "office = 'government' OR amenity = 'rescue_station' OR amenity = 'ambulance_station' OR amenity = 'emergency_service'",
        'icon' => 'hard-hat',
        'category' => 'safety'
    ],
    
    // === Public Administration ===
    'parish_councils' => [
        'condition' => "office = 'government' AND admin_level = '9'",
        'icon' => 'city',
        'category' => 'administration'
    ],
    'city_halls' => [
        'condition' => "office = 'government' AND admin_level = '8'",
        'icon' => 'landmark',
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
            $debug_info['using_fallback_buffer'] = true;
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
    // If no isochrone is provided but we have radius, use it as a fallback
    $spatialCondition = "ST_DWithin(
        way, 
        ST_Transform(ST_SetSRID(ST_MakePoint($lng, $lat), 4326), 3857), 
        $radius
    )";
    $debug_info['using_buffer'] = true;
} else {
    // This shouldn't happen due to earlier validation, but just in case
    echo json_encode([
        'success' => false,
        'message' => 'No spatial filtering method available'
    ]);
    exit;
}

// Get the query condition for this POI type
$poiCondition = $poiTypes[$type]['condition'];

// Build and execute the spatial query for POINTS
$pointQuery = "
    SELECT 
        osm_id,
        name,
        amenity,
        shop,
        leisure,
        tourism,
        office,
        highway,
        railway,
        ST_X(ST_Transform(way, 4326)) AS longitude,
        ST_Y(ST_Transform(way, 4326)) AS latitude,
        CASE 
            WHEN amenity IS NOT NULL THEN amenity
            WHEN shop IS NOT NULL THEN shop
            WHEN leisure IS NOT NULL THEN leisure
            WHEN tourism IS NOT NULL THEN tourism
            WHEN office IS NOT NULL THEN office
            WHEN highway IS NOT NULL THEN highway
            WHEN railway IS NOT NULL THEN railway
            ELSE 'unknown'
        END AS type_value,
        'point' AS geometry_type
    FROM 
        planet_osm_point 
    WHERE 
        ($poiCondition)
        AND $spatialCondition
";

// Additional query for POLYGONS - convert to points using centroid
$polygonQuery = "
    SELECT 
        osm_id,
        name,
        amenity,
        shop,
        leisure,
        tourism,
        office,
        highway,
        railway,
        ST_X(ST_Transform(ST_Centroid(way), 4326)) AS longitude,
        ST_Y(ST_Transform(ST_Centroid(way), 4326)) AS latitude,
        CASE 
            WHEN amenity IS NOT NULL THEN amenity
            WHEN shop IS NOT NULL THEN shop
            WHEN leisure IS NOT NULL THEN leisure
            WHEN tourism IS NOT NULL THEN tourism
            WHEN office IS NOT NULL THEN office
            WHEN highway IS NOT NULL THEN highway
            WHEN railway IS NOT NULL THEN railway
            ELSE 'unknown'
        END AS type_value,
        'polygon' AS geometry_type
    FROM 
        planet_osm_polygon 
    WHERE 
        ($poiCondition)
        AND $spatialCondition
";

// Combine the queries with UNION
$combinedQuery = "
    ($pointQuery)
    UNION
    ($polygonQuery)
    LIMIT 1000
";

// Execute the combined query
$result = pg_query($conn, $combinedQuery);

if (!$result) {
    echo json_encode([
        'success' => false,
        'message' => 'Database query error: ' . pg_last_error($conn),
        'debug' => $debug_info
    ]);
    exit;
}

// Process results
$pois = [];
while ($row = pg_fetch_assoc($result)) {
    // Create a properties object for additional POI data
    $properties = [];
    
    // Include all non-spatial fields in properties
    foreach ($row as $key => $value) {
        if (!in_array($key, ['osm_id', 'latitude', 'longitude', 'name', 'type_value', 'geometry_type']) 
            && !is_null($value) && $value !== '') {
            $properties[$key] = $value;
        }
    }
    
    // Add geometry_type to properties
    $properties['geometry_type'] = $row['geometry_type'];
    
    // Add POI to results array
    $pois[] = [
        'osm_id' => $row['osm_id'],
        'name' => $row['name'] ? $row['name'] : ucfirst($row['type_value']),
        'type' => ucfirst($row['type_value']),
        'latitude' => (float)$row['latitude'],
        'longitude' => (float)$row['longitude'],
        'address' => '', // Empty address as we don't have the columns
        'properties' => $properties,
        'category' => $poiTypes[$type]['category']
    ];
}

// Return the POIs as JSON
echo json_encode([
    'success' => true,
    'pois' => $pois,
    'count' => count($pois),
    'debug' => $debug_info
]);

// Close the database connection
pg_close($conn);
?>