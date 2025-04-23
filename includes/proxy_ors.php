<?php
/**
 * OpenRouteService API Proxy
 * Handles requests to OpenRouteService API to avoid CORS issues
 */

// Prevent any output before JSON response
error_reporting(0);
ini_set('display_errors', 0);

// Set headers for JSON response
header('Content-Type: application/json');

// Include API configuration to get API key
require_once '../config/api_config.php';

// Check if request method is POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode([
        'success' => false,
        'message' => 'Only POST method is allowed'
    ]);
    exit;
}

// Get the OpenRouteService endpoint from the request
$endpoint = isset($_POST['endpoint']) ? $_POST['endpoint'] : null;

// Get the request data
$requestData = isset($_POST['data']) ? $_POST['data'] : null;

// Check if all required parameters are provided
if (empty($endpoint) || empty($requestData)) {
    echo json_encode([
        'success' => false,
        'message' => 'Missing required parameters: endpoint, data'
    ]);
    exit;
}

// Try to decode the request data if it's a string
if (is_string($requestData)) {
    $requestData = json_decode($requestData, true);
    
    // Check if JSON was invalid
    if (json_last_error() !== JSON_ERROR_NONE) {
        echo json_encode([
            'success' => false,
            'message' => 'Invalid JSON in request data: ' . json_last_error_msg()
        ]);
        exit;
    }
}

// Construct the full URL for the API request
$url = ORS_API_URL . $endpoint;

// Debug info to help troubleshoot issues
$debug = [
    'requested_url' => $url,
    'request_data' => $requestData
];

// Initialize cURL session
$ch = curl_init($url);

// Set cURL options
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($requestData));
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Accept: application/json, application/geo+json',
    'Content-Type: application/json',
    'Authorization: ' . ORS_API_KEY
]);

// Execute the request
$response = curl_exec($ch);
$statusCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$contentType = curl_getinfo($ch, CURLINFO_CONTENT_TYPE);

// Get more info about the request for debugging
$debug['status_code'] = $statusCode;
$debug['content_type'] = $contentType;
$debug['curl_error'] = curl_error($ch);
$debug['curl_errno'] = curl_errno($ch);

// Close cURL session
curl_close($ch);

// Check if we got a valid response
if ($response === false) {
    echo json_encode([
        'success' => false,
        'message' => 'Failed to connect to the API server',
        'debug' => $debug
    ]);
    exit;
}

// Validate the response format is valid JSON
$decodedResponse = json_decode($response, true);
if (json_last_error() !== JSON_ERROR_NONE) {
    echo json_encode([
        'success' => false,
        'message' => 'Invalid JSON in API response: ' . json_last_error_msg(),
        'debug' => $debug,
        'raw_response' => substr($response, 0, 1000) // Include first 1000 chars of raw response
    ]);
    exit;
}

// If error occurs, provide information about it
if ($statusCode >= 400) {
    echo json_encode([
        'success' => false,
        'status' => $statusCode,
        'message' => isset($decodedResponse['error']) ? $decodedResponse['error'] : 'API request failed',
        'details' => $decodedResponse,
        'debug' => $debug
    ]);
    exit;
}

// Make sure the response contains a valid GeoJSON structure
if (!isset($decodedResponse['type']) || !isset($decodedResponse['features'])) {
    echo json_encode([
        'success' => false,
        'message' => 'Response is not a valid GeoJSON object',
        'debug' => $debug,
        'response_data' => $decodedResponse
    ]);
    exit;
}

// Ensure features array exists and is not empty
if (!is_array($decodedResponse['features']) || count($decodedResponse['features']) === 0) {
    echo json_encode([
        'success' => false,
        'message' => 'GeoJSON features array is empty or invalid',
        'debug' => $debug,
        'response_data' => $decodedResponse
    ]);
    exit;
}

// Check if the features contain valid geometries
if (!isset($decodedResponse['features'][0]['geometry']) || 
    !isset($decodedResponse['features'][0]['geometry']['coordinates'])) {
    echo json_encode([
        'success' => false,
        'message' => 'Invalid geometry in GeoJSON response',
        'debug' => $debug,
        'response_data' => $decodedResponse
    ]);
    exit;
}

// Return the API response as is
echo $response;
?>