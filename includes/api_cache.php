<?php
/**
 * API Cache System
 * Provides a general-purpose caching mechanism for API calls
 * 
 * @version 1.0
 */

class ApiCache {
    private $cacheDir;
    private $defaultExpiry = 31536000; // 365 days in seconds
    
    /**
     * Constructor
     * 
     * @param string $cacheDir The directory to store cache files (without trailing slash)
     * @param int $defaultExpiry Default cache expiry time in seconds
     */
    public function __construct($cacheDir = null, $defaultExpiry = null) {
        // Set cache directory, default to ../cache/api/
        $this->cacheDir = $cacheDir ?: __DIR__ . '/../cache/api/';
        
        // Ensure cache directory exists
        if (!is_dir($this->cacheDir)) {
            mkdir($this->cacheDir, 0755, true);
        }
        
        // Set default expiry if provided
        if ($defaultExpiry !== null) {
            $this->defaultExpiry = $defaultExpiry;
        }
    }
    
    /**
     * Generate a cache key from various parameters
     * 
     * @param string $baseKey Base key (usually the endpoint)
     * @param array $params Additional parameters to include in the key
     * @return string The cache key
     */
    public function generateCacheKey($baseKey, $params = []) {
        $key = $baseKey;
        
        if (!empty($params)) {
            // Sort params to ensure consistent keys regardless of parameter order
            ksort($params);
            $key .= '_' . http_build_query($params);
        }
        
        return md5($key);
    }
    
    /**
     * Get the full path to a cache file
     * 
     * @param string $cacheKey The cache key
     * @return string The full path to the cache file
     */
    public function getCacheFilePath($cacheKey) {
        return $this->cacheDir . $cacheKey . '.json';
    }
    
    /**
     * Check if a cached item exists and is still valid
     * 
     * @param string $cacheKey The cache key
     * @param int $expiry Override the default expiry time (in seconds)
     * @return bool True if the cache exists and is valid, false otherwise
     */
    public function hasValidCache($cacheKey, $expiry = null) {
        $cacheFile = $this->getCacheFilePath($cacheKey);
        
        if (file_exists($cacheFile)) {
            $fileAge = time() - filemtime($cacheFile);
            $expiryTime = $expiry !== null ? $expiry : $this->defaultExpiry;
            
            return $fileAge < $expiryTime;
        }
        
        return false;
    }
    
    /**
     * Get data from cache
     * 
     * @param string $cacheKey The cache key
     * @return mixed The cached data (decoded from JSON), or null if not found
     */
    public function get($cacheKey) {
        $cacheFile = $this->getCacheFilePath($cacheKey);
        
        if (file_exists($cacheFile)) {
            $content = file_get_contents($cacheFile);
            return json_decode($content, true);
        }
        
        return null;
    }
    
    /**
     * Store data in cache
     * 
     * @param string $cacheKey The cache key
     * @param mixed $data The data to store (will be JSON encoded)
     * @return bool True on success, false on failure
     */
    public function set($cacheKey, $data) {
        $cacheFile = $this->getCacheFilePath($cacheKey);
        
        // Ensure we have a valid JSON string
        $jsonData = json_encode($data);
        if ($jsonData === false) {
            error_log("API Cache: Failed to encode data to JSON - " . json_last_error_msg());
            return false;
        }
        
        // Write to cache file
        $result = file_put_contents($cacheFile, $jsonData);
        return $result !== false;
    }
    
    /**
     * Delete a cached item
     * 
     * @param string $cacheKey The cache key
     * @return bool True if the cache was deleted, false otherwise
     */
    public function delete($cacheKey) {
        $cacheFile = $this->getCacheFilePath($cacheKey);
        
        if (file_exists($cacheFile)) {
            return unlink($cacheFile);
        }
        
        return false;
    }
    
    /**
     * Clear all cached items
     * 
     * @return bool True on success, false on failure
     */
    public function clearAll() {
        $files = glob($this->cacheDir . '*.json');
        
        if ($files === false) {
            return false;
        }
        
        $success = true;
        foreach ($files as $file) {
            if (!unlink($file)) {
                $success = false;
            }
        }
        
        return $success;
    }
    
    /**
     * Get or set cached data with a callback function
     * 
     * @param string $cacheKey The cache key
     * @param callable $callback Function to call if cache is not valid
     * @param int $expiry Override the default expiry time (in seconds)
     * @return mixed The cached data or the result of the callback
     */
    public function remember($cacheKey, $callback, $expiry = null) {
        // Check if we have valid cached data
        if ($this->hasValidCache($cacheKey, $expiry)) {
            return $this->get($cacheKey);
        }
        
        // Call the callback to get fresh data
        $data = $callback();
        
        // Store the fresh data in cache
        $this->set($cacheKey, $data);
        
        return $data;
    }
} 