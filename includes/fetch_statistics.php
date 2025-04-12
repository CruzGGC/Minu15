<?php
/**
 * Fetch Statistics endpoint - Gets area statistics within the isochrone
 */

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

// Define the POI categories to count
$poiCategories = [
    'hospitals' => "amenity = 'hospital'",
    'schools' => "amenity IN ('school', 'university', 'college', 'kindergarten')",
    'health' => "amenity IN ('clinic', 'doctors', 'dentist', 'pharmacy')",
    'culture' => "amenity IN ('theatre', 'cinema', 'library', 'arts_centre', 'community_centre', 'museum')",
    'shops' => "shop IS NOT NULL",
    'parks' => "leisure IN ('park', 'garden', 'playground')"
];

// Initialize statistics array
$statistics = [
    'area_km2' => (float) $areaKm2
];

// Count POIs within the buffer for each category
foreach ($poiCategories as $category => $condition) {
    $countQuery = "
        SELECT 
            COUNT(*) as count 
        FROM 
            planet_osm_point 
        WHERE 
            $condition 
            AND ST_DWithin(
                way, 
                ST_Transform(ST_SetSRID(ST_MakePoint($lng, $lat), 4326), 3857), 
                $radius
            )
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
        AND ST_DWithin(
            way, 
            ST_Transform(ST_SetSRID(ST_MakePoint($lng, $lat), 4326), 3857), 
            $radius
        )
";

$populationResult = pg_query($conn, $populationQuery);

if (!$populationResult) {
    $statistics['population_estimate'] = 'Not available';
} else {
    $populationData = pg_fetch_assoc($populationResult);
    $buildingCount = (int) $populationData['building_count'];
    
    // Rough estimate: 2.5 people per residential building
    $statistics['population_estimate'] = $buildingCount * 2.5;
}

// Get parish information if available
$parishQuery = "
    SELECT 
        name,
        admin_level
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
    LIMIT 1
";

$parishResult = pg_query($conn, $parishQuery);

if ($parishResult && pg_num_rows($parishResult) > 0) {
    $parishData = pg_fetch_assoc($parishResult);
    $statistics['parish'] = $parishData['name'];
} else {
    $statistics['parish'] = 'Unknown';
}

// Return the statistics as JSON
echo json_encode([
    'success' => true,
    'stats' => $statistics,
    'buffer_geojson' => json_decode($bufferGeoJSON)
]);

// Close the database connection
pg_close($conn);
?>