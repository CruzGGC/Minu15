<?php
/**
 * GeoAPI.pt Proxy
 * Handles requests to the GeoAPI.pt API for Portuguese administrative regions data
 * Implements caching to minimize API calls
 * Added rate limit handling with exponential backoff
 * 
 * @version 1.2
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
$geoApiBaseUrl = 'https://json.geoapi.pt/';

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

// Define cache directory and ensure it exists
$cacheDir = __DIR__ . '/../cache/geoapi/';
if (!is_dir($cacheDir)) {
    mkdir($cacheDir, 0755, true);
}

// Create a cache key based on the endpoint and any additional parameters
$cacheKey = $endpoint;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $postData = file_get_contents('php://input');
    $cacheKey .= '_' . $postData;
}
if (!empty($_GET)) {
    $queryParams = $_GET;
    unset($queryParams['endpoint']); // Remove endpoint from query params for cache key
    if (!empty($queryParams)) {
        $cacheKey .= '_' . http_build_query($queryParams);
    }
}

// Create a filename for the cache file
$cacheFile = $cacheDir . md5($cacheKey) . '.json';

// Check if we have a valid cache file that's not expired (7 days cache)
$cacheExpiry = 604800; // 7 days in seconds (increased from 1 day)
$useCache = false;

if (file_exists($cacheFile)) {
    $fileAge = time() - filemtime($cacheFile);
    if ($fileAge < $cacheExpiry) {
        $useCache = true;
    }
}

// If we have a valid cache, use it
if ($useCache) {
    $response = file_get_contents($cacheFile);
    echo $response;
    exit;
}

// Rate limit handling
$rateLimitFile = $cacheDir . 'rate_limit_status.json';
$maxRetries = 3;
$retryCount = 0;
$retryDelay = 1; // Initial delay in seconds

// Check if we're currently rate limited
if (file_exists($rateLimitFile)) {
    $rateLimitInfo = json_decode(file_get_contents($rateLimitFile), true);
    
    // If the rate limit reset time hasn't passed yet
    if (isset($rateLimitInfo['reset_time']) && time() < $rateLimitInfo['reset_time']) {
        // If we have a cached response for this endpoint, use it even if expired
        if (file_exists($cacheFile)) {
            $response = file_get_contents($cacheFile);
            echo $response;
            exit;
        }
        
        // Otherwise, return a rate limit error
        http_response_code(429);
        echo json_encode([
            'success' => false,
            'message' => 'Rate limit exceeded. Please try again later.',
            'reset_in_seconds' => $rateLimitInfo['reset_time'] - time()
        ]);
        exit;
    }
}

// Function to make the API request with retry logic
function makeApiRequest($url, $postData = null, $retryCount = 0, $maxRetries = 3, $retryDelay = 1) {
    global $cacheDir, $rateLimitFile;
    
    // Initialize cURL
    $ch = curl_init();
    
    // Set cURL options
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30); // Increased timeout for larger responses
    
    // If it's a POST request, pass along the POST data
    if ($postData !== null) {
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);
    }
    
    // Execute the request
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    
    // Check for errors
    if (curl_errno($ch)) {
        $error = curl_error($ch);
        curl_close($ch);
        
        // If we haven't reached max retries, try again
        if ($retryCount < $maxRetries) {
            // Exponential backoff
            $sleepTime = $retryDelay * pow(2, $retryCount);
            sleep($sleepTime);
            return makeApiRequest($url, $postData, $retryCount + 1, $maxRetries, $retryDelay);
        }
        
        return [
            'success' => false,
            'code' => 0,
            'message' => 'cURL Error: ' . $error,
            'response' => null
        ];
    }
    
    // Handle rate limiting
    if ($httpCode === 429) {
        // Save rate limit info
        $rateLimitInfo = [
            'reset_time' => time() + 3600, // Default to 1 hour if no header is provided
            'last_error' => time()
        ];
        
        // Try to get rate limit reset time from response
        $responseData = json_decode($response, true);
        if (isset($responseData['msg']) && strpos($responseData['msg'], 'limit of free requests') !== false) {
            // This is a GeoAPI.pt rate limit message
            file_put_contents($rateLimitFile, json_encode($rateLimitInfo));
        }
        
        // If we haven't reached max retries, try again
        if ($retryCount < $maxRetries) {
            // Exponential backoff
            $sleepTime = $retryDelay * pow(2, $retryCount);
            sleep($sleepTime);
            return makeApiRequest($url, $postData, $retryCount + 1, $maxRetries, $retryDelay);
        }
    }
    
    curl_close($ch);
    
    return [
        'success' => $httpCode >= 200 && $httpCode < 300,
        'code' => $httpCode,
        'message' => 'HTTP Code: ' . $httpCode,
        'response' => $response
    ];
}

// Build the full API URL
$apiUrl = $geoApiBaseUrl . $endpoint;

// Make the API request
$result = makeApiRequest(
    $apiUrl, 
    $_SERVER['REQUEST_METHOD'] === 'POST' ? file_get_contents('php://input') : null,
    $retryCount,
    $maxRetries,
    $retryDelay
);

// If the request was successful, cache the response
if ($result['success']) {
    // Check if the response is valid JSON
    $jsonCheck = json_decode($result['response']);
    if ($jsonCheck !== null) {
        // Save to cache
        file_put_contents($cacheFile, $result['response']);
    }
    
    // Return the API response with the same HTTP code
    http_response_code($result['code']);
    echo $result['response'];
} else {
    // If we have a cached response for this endpoint, use it even if expired
    if (file_exists($cacheFile)) {
        $response = file_get_contents($cacheFile);
        echo $response;
        exit;
    }
    
    // Otherwise, return the error
    http_response_code($result['code']);
    echo json_encode([
        'success' => false,
        'message' => $result['message']
    ]);
} 