<?php
/**
 * Fetch POIs endpoint - Gets points of interest around a specific location
 * Ensures that POIs are strictly contained within the isochrone or buffer area
 */

// Prevent any output before JSON
error_reporting(0);
ini_set('display_errors', 0);

// Include database configuration
require_once '../config/db_config.php';

// Set headers for JSON response
header('Content-Type: application/json');

// Check if all required parameters are provided
if (!isset($_POST['type']) || !isset($_POST['lat']) || !isset($_POST['lng']) || !isset($_POST['radius'])) {
    echo json_encode([
        'success' => false,
        'message' => 'Missing required parameters'
    ]);
    exit;
}

// Get parameters from request
$type = $_POST['type'];
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

// POI type definitions - must match with the ones in map.js
$poiTypes = [
    // Saúde
    'hospitals' => [
        'table' => 'planet_osm_point',
        'condition' => "amenity = 'hospital'"
    ],
    'health_centers' => [
        'table' => 'planet_osm_point',
        'condition' => "amenity = 'clinic' OR amenity = 'doctors'"
    ],
    'pharmacies' => [
        'table' => 'planet_osm_point',
        'condition' => "amenity = 'pharmacy'"
    ],
    'dentists' => [
        'table' => 'planet_osm_point',
        'condition' => "amenity = 'dentist'"
    ],
    
    // Educação
    'schools' => [
        'table' => 'planet_osm_point',
        'condition' => "amenity = 'school'"
    ],
    'universities' => [
        'table' => 'planet_osm_point',
        'condition' => "amenity IN ('university', 'college')"
    ],
    'kindergartens' => [
        'table' => 'planet_osm_point',
        'condition' => "amenity = 'kindergarten'"
    ],
    'libraries' => [
        'table' => 'planet_osm_point',
        'condition' => "amenity = 'library'"
    ],
    
    // Comércio e serviços
    'supermarkets' => [
        'table' => 'planet_osm_point',
        'condition' => "shop IN ('supermarket', 'grocery', 'convenience')"
    ],
    'malls' => [
        'table' => 'planet_osm_point',
        'condition' => "shop = 'mall' OR amenity = 'marketplace'"
    ],
    'restaurants' => [
        'table' => 'planet_osm_point',
        'condition' => "amenity IN ('restaurant', 'fast_food', 'cafe', 'bar', 'pub')"
    ],
    'atms' => [
        'table' => 'planet_osm_point',
        'condition' => "amenity = 'atm' OR amenity = 'bank'"
    ],
    'banks' => [
        'table' => 'planet_osm_point',
        'condition' => "amenity = 'bank'"
    ],
    
    // Segurança e emergência
    'police' => [
        'table' => 'planet_osm_point', 
        'condition' => "amenity = 'police'"
    ],
    'fire_stations' => [
        'table' => 'planet_osm_point',
        'condition' => "amenity = 'fire_station'"
    ],
    'civil_protection' => [
        'table' => 'planet_osm_point',
        'condition' => "amenity = 'ranger_station' OR (office = 'government' AND name ILIKE '%proteção civil%')"
    ],
    
    // Transporte
    'bus_stops' => [
        'table' => 'planet_osm_point',
        'condition' => "highway = 'bus_stop'"
    ],
    'subway_stations' => [
        'table' => 'planet_osm_point',
        'condition' => "railway = 'station' OR railway = 'subway_entrance'"
    ],
    'train_stations' => [
        'table' => 'planet_osm_point',
        'condition' => "railway = 'station'"
    ],
    'bike_parkings' => [
        'table' => 'planet_osm_point',
        'condition' => "amenity = 'bicycle_parking'"
    ],
    
    // Administração 
    'police_stations' => [
        'table' => 'planet_osm_point',
        'condition' => "amenity = 'police'"
    ],
    'parish_councils' => [
        'table' => 'planet_osm_point',
        'condition' => "office = 'government' AND name ILIKE '%junta de freguesia%'"
    ],
    'parishes' => [
        'table' => 'planet_osm_point',
        'condition' => "office = 'government' AND name ILIKE '%junta de freguesia%'"
    ],
    'city_halls' => [
        'table' => 'planet_osm_point',
        'condition' => "office = 'government' AND (name ILIKE '%câmara municipal%' OR name ILIKE '%camara municipal%')"
    ],
    'post_offices' => [
        'table' => 'planet_osm_point',
        'condition' => "amenity = 'post_office'"
    ],
    
    // Cultura e lazer
    'museums' => [
        'table' => 'planet_osm_point',
        'condition' => "tourism = 'museum' OR amenity = 'museum'"
    ],
    'theaters' => [
        'table' => 'planet_osm_point',
        'condition' => "amenity = 'theatre'"
    ],
    'sports' => [
        'table' => 'planet_osm_point',
        'condition' => "leisure IN ('sports_centre', 'stadium', 'pitch', 'swimming_pool', 'fitness_centre', 'fitness_station')"
    ],
    'parks' => [
        'table' => 'planet_osm_point',
        'condition' => "leisure IN ('park', 'garden', 'playground')"
    ]
];

// Check if the requested type exists
if (!array_key_exists($type, $poiTypes)) {
    echo json_encode([
        'success' => false,
        'message' => 'Invalid POI type'
    ]);
    exit;
}

// Get the POI definition
$poiDef = $poiTypes[$type];

// Variables to store geometry references
$geometry = null;
$spatialCondition = "";
$debug_info = [];

// If we have an isochrone polygon, use it for precise containment
if ($isochroneJson) {
    try {
        // Decode the GeoJSON
        $isochrone = json_decode($isochroneJson, true);
        $debug_info['isochrone_parsed'] = true;
        
        // Extract the first feature's geometry (the isochrone polygon)
        if (isset($isochrone['features']) && 
            isset($isochrone['features'][0]) && 
            isset($isochrone['features'][0]['geometry'])) {
            
            $geometry = json_encode($isochrone['features'][0]['geometry']);
            $debug_info['geometry_extracted'] = true;
            
            // Create a PostgreSQL geometry from the GeoJSON polygon
            // Use ST_Contains to filter POIs that are strictly inside the polygon
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
            // Fallback if the GeoJSON structure is invalid
            $debug_info['invalid_geometry'] = true;
            $spatialCondition = "ST_DWithin(
                way, 
                ST_Transform(ST_SetSRID(ST_MakePoint($lng, $lat), 4326), 3857), 
                $radius
            )";
        }
    } catch (Exception $e) {
        // If there's any error processing the GeoJSON, fall back to radius
        $debug_info['exception'] = $e->getMessage();
        $spatialCondition = "ST_DWithin(
            way, 
            ST_Transform(ST_SetSRID(ST_MakePoint($lng, $lat), 4326), 3857), 
            $radius
        )";
    }
} else {
    // If no isochrone is provided, use a simple buffer around the point
    $debug_info['using_buffer'] = true;
    $spatialCondition = "ST_DWithin(
        way, 
        ST_Transform(ST_SetSRID(ST_MakePoint($lng, $lat), 4326), 3857), 
        $radius
    )";
}

// Build and execute the spatial query
$query = "
    SELECT 
        osm_id,
        name,
        amenity,
        shop,
        leisure,
        tourism,
        office,
        \"addr:street\" AS street,
        \"addr:housenumber\" AS housenumber,
        ST_X(ST_Transform(way, 4326)) AS longitude,
        ST_Y(ST_Transform(way, 4326)) AS latitude,
        CASE 
            WHEN amenity IS NOT NULL THEN amenity
            WHEN shop IS NOT NULL THEN shop
            WHEN leisure IS NOT NULL THEN leisure
            WHEN tourism IS NOT NULL THEN tourism
            WHEN office IS NOT NULL THEN office
            ELSE 'unknown'
        END AS type
    FROM 
        " . $poiDef['table'] . " 
    WHERE 
        " . $poiDef['condition'] . " 
        AND " . $spatialCondition . "
    LIMIT 500";

// Execute the query
$result = pg_query($conn, $query);

if (!$result) {
    echo json_encode([
        'success' => false,
        'message' => 'Database query error: ' . pg_last_error($conn),
        'debug' => $debug_info,
        'query' => $query
    ]);
    exit;
}

// Process results
$pois = [];
while ($row = pg_fetch_assoc($result)) {
    // Create a properties array for additional POI data
    $properties = [];
    foreach ($row as $key => $value) {
        if (!in_array($key, ['osm_id', 'latitude', 'longitude', 'name', 'type']) && !is_null($value)) {
            $properties[$key] = $value;
        }
    }
    
    // Create address from street and housenumber if available
    $address = '';
    if (!empty($row['street'])) {
        $address = $row['street'];
        if (!empty($row['housenumber'])) {
            $address .= ' ' . $row['housenumber'];
        }
    }
    
    // Add POI to results
    $pois[] = [
        'osm_id' => $row['osm_id'],
        'name' => $row['name'] ? $row['name'] : ucfirst($row['type']),
        'type' => ucfirst($row['type']),
        'latitude' => (float)$row['latitude'],
        'longitude' => (float)$row['longitude'],
        'address' => $address,
        'properties' => $properties
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