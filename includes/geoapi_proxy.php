<?php
/**
 * GeoAPI.pt Proxy
 * Handles requests to the GeoAPI.pt API for Portuguese administrative regions data
 * 
 * @version 1.0
 */

// Prevent any output before JSON response
error_reporting(0);
ini_set('display_errors', 0);

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

// Define GeoAPI.pt base URL
$geoApiBaseUrl = 'https://geoapi.pt/';

// Get the endpoint from the request
$endpoint = isset($_GET['endpoint']) ? $_GET['endpoint'] : '';

// Validate endpoint
if (empty($endpoint)) {
    echo json_encode([
        'success' => false,
        'message' => 'Missing required parameter: endpoint'
    ]);
    exit;
}

// Build the full API URL
$apiUrl = $geoApiBaseUrl . $endpoint;

// Initialize cURL
$ch = curl_init();

// Set cURL options
curl_setopt($ch, CURLOPT_URL, $apiUrl);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 10);

// If it's a POST request, pass along the POST data
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $postData = file_get_contents('php://input');
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);
}

// Execute the request
$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

// Check for errors
if (curl_errno($ch)) {
    echo json_encode([
        'success' => false,
        'message' => 'cURL Error: ' . curl_error($ch)
    ]);
    exit;
}

// Close cURL
curl_close($ch);

// Return the API response with the same HTTP code
http_response_code($httpCode);
echo $response; 