<?php
/**
 * Nominatim API Proxy
 * Handles requests to Nominatim geocoding API to avoid CORS issues
 * Used for autocompletion feature
 */

// Prevent any output before JSON response
error_reporting(0);
ini_set('display_errors', 0);

// Set headers for JSON response
header('Content-Type: application/json');

// Add a delay to avoid overloading Nominatim API
usleep(300000); // 300ms delay

// Get the search term from the request
$searchTerm = isset($_GET['term']) ? $_GET['term'] : null;

// Check if search term is provided
if (empty($searchTerm)) {
    echo json_encode([
        'success' => false,
        'message' => 'Missing required parameter: term'
    ]);
    exit;
}

// Ensure search term focuses on Portugal and is properly encoded
$encodedSearchTerm = urlencode($searchTerm . ', Portugal');

// Construct the URL for the Nominatim API request
$url = "https://nominatim.openstreetmap.org/search?format=json&q={$encodedSearchTerm}&limit=10&countrycodes=pt";

// Initialize cURL session
$ch = curl_init($url);

// Set cURL options
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Accept: application/json',
    'User-Agent: Minu15/1.0' // Identify your application as per Nominatim usage policy
]);

// Execute the request
$response = curl_exec($ch);
$statusCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

// Check for cURL errors
if ($response === false) {
    echo json_encode([
        'success' => false,
        'message' => 'Failed to connect to the Nominatim API server: ' . curl_error($ch)
    ]);
    curl_close($ch);
    exit;
}

// Close cURL session
curl_close($ch);

// Check if we got a valid response
if ($statusCode != 200) {
    echo json_encode([
        'success' => false,
        'message' => 'API request failed with status code: ' . $statusCode
    ]);
    exit;
}

// Decode the JSON response
$results = json_decode($response, true);

// Check if JSON decoding failed
if (json_last_error() !== JSON_ERROR_NONE) {
    echo json_encode([
        'success' => false,
        'message' => 'Invalid JSON in API response: ' . json_last_error_msg()
    ]);
    exit;
}

// Format the results for jQuery UI Autocomplete
$formattedResults = [];
foreach ($results as $place) {
    // Skip results with no display name or coordinates
    if (empty($place['display_name']) || !isset($place['lat']) || !isset($place['lon'])) {
        continue;
    }
    
    // Format the results for jQuery UI Autocomplete
    $formattedResults[] = [
        'label' => $place['display_name'],
        'value' => $place['display_name'],
        'lat' => $place['lat'],
        'lon' => $place['lon'],
        'type' => isset($place['type']) ? $place['type'] : '',
        'osm_id' => isset($place['osm_id']) ? $place['osm_id'] : 0
    ];
}

// Return the formatted results
echo json_encode($formattedResults);
?> 