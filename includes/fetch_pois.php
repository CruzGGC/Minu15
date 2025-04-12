<?php
/**
 * Fetch POIs endpoint - Gets points of interest around a specific location
 */

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
    'hospitals' => [
        'table' => 'planet_osm_point',
        'condition' => "amenity = 'hospital'"
    ],
    'schools' => [
        'table' => 'planet_osm_point',
        'condition' => "amenity IN ('school', 'university', 'college', 'kindergarten')"
    ],
    'health' => [
        'table' => 'planet_osm_point',
        'condition' => "amenity IN ('clinic', 'doctors', 'dentist', 'pharmacy')"
    ],
    'culture' => [
        'table' => 'planet_osm_point',
        'condition' => "amenity IN ('theatre', 'cinema', 'library', 'arts_centre', 'community_centre', 'museum')"
    ],
    'shops' => [
        'table' => 'planet_osm_point',
        'condition' => "shop IS NOT NULL"
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

// Build the spatial query
// IMPORTANT: We need to transform coordinates from WGS84 (EPSG:4326) to the projection used by OSM (EPSG:3857)
$query = "
    SELECT 
        osm_id,
        name,
        amenity,
        shop,
        leisure,
        'addr:street' AS street,
        'addr:housenumber' AS housenumber,
        ST_X(ST_Transform(way, 4326)) AS longitude,
        ST_Y(ST_Transform(way, 4326)) AS latitude,
        CASE 
            WHEN amenity IS NOT NULL THEN amenity
            WHEN shop IS NOT NULL THEN shop
            WHEN leisure IS NOT NULL THEN leisure
            ELSE 'unknown'
        END AS type
    FROM 
        " . $poiDef['table'] . " 
    WHERE 
        " . $poiDef['condition'] . " 
        AND ST_DWithin(
            way, 
            ST_Transform(ST_SetSRID(ST_MakePoint($lng, $lat), 4326), 3857), 
            $radius
        )
    LIMIT 500";

// Execute the query
$result = pg_query($conn, $query);

if (!$result) {
    echo json_encode([
        'success' => false,
        'message' => 'Database query error: ' . pg_last_error($conn)
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
    'count' => count($pois)
]);

// Close the database connection
pg_close($conn);
?>