<?php
/**
 * Cache Management Script
 * 
 * This script provides functionality to clear API caches.
 * It can be run from the command line or via a web interface.
 */

// Determine if the script is being run from the command line
$isCli = (php_sapi_name() === 'cli');

// Set up the environment
if (!$isCli) {
    // If running in a web server, set headers and check for admin access
    header('Content-Type: text/html; charset=utf-8');
    
    // Simple authentication check - replace with your actual authentication method
    $isAuthenticated = false;
    
    // Check for admin cookie or session
    if (isset($_COOKIE['admin_authenticated']) || (isset($_SESSION['admin_authenticated']) && $_SESSION['admin_authenticated'] === true)) {
        $isAuthenticated = true;
    }
    
    // If not authenticated, check for basic auth
    if (!$isAuthenticated && isset($_SERVER['PHP_AUTH_USER'])) {
        // Replace with your actual admin credentials check
        if ($_SERVER['PHP_AUTH_USER'] === 'admin' && $_SERVER['PHP_AUTH_PW'] === 'adminpassword') {
            $isAuthenticated = true;
        }
    }
    
    // If still not authenticated, require basic auth
    if (!$isAuthenticated) {
        header('WWW-Authenticate: Basic realm="Cache Management"');
        header('HTTP/1.0 401 Unauthorized');
        echo '<h1>Authentication Required</h1>';
        exit;
    }
}

// Include the API cache class
require_once __DIR__ . '/../../includes/api_cache.php';

// Define cache directories
$cacheDirs = [
    'location_data' => __DIR__ . '/../../cache/location_data/',
    'geoapi' => __DIR__ . '/../../cache/geoapi/',
    'api' => __DIR__ . '/../../cache/api/'
];

// Function to clear a specific cache
function clearCache($cacheDir, $name) {
    if (!is_dir($cacheDir)) {
        return ['success' => false, 'message' => "Cache directory '$name' doesn't exist"];
    }
    
    $cache = new ApiCache($cacheDir);
    $result = $cache->clearAll();
    
    if ($result) {
        return ['success' => true, 'message' => "Successfully cleared '$name' cache"];
    } else {
        return ['success' => false, 'message' => "Failed to clear '$name' cache"];
    }
}

// Function to get cache statistics
function getCacheStats($cacheDir, $name) {
    if (!is_dir($cacheDir)) {
        return ['name' => $name, 'exists' => false, 'count' => 0, 'size' => 0];
    }
    
    $files = glob($cacheDir . '*.json');
    $count = count($files);
    $size = 0;
    
    foreach ($files as $file) {
        $size += filesize($file);
    }
    
    return [
        'name' => $name,
        'exists' => true,
        'count' => $count,
        'size' => formatSize($size),
        'oldest' => $count > 0 ? date('Y-m-d H:i:s', min(array_map('filemtime', $files))) : 'N/A',
        'newest' => $count > 0 ? date('Y-m-d H:i:s', max(array_map('filemtime', $files))) : 'N/A'
    ];
}

// Function to format file size
function formatSize($bytes) {
    $units = ['B', 'KB', 'MB', 'GB', 'TB'];
    $i = 0;
    while ($bytes >= 1024 && $i < count($units) - 1) {
        $bytes /= 1024;
        $i++;
    }
    return round($bytes, 2) . ' ' . $units[$i];
}

// Handle actions
$action = $isCli ? ($argv[1] ?? '') : ($_GET['action'] ?? '');
$target = $isCli ? ($argv[2] ?? '') : ($_GET['target'] ?? '');
$result = null;

if ($action === 'clear') {
    if ($target === 'all') {
        $results = [];
        foreach ($cacheDirs as $name => $dir) {
            $results[$name] = clearCache($dir, $name);
        }
        $result = ['success' => true, 'message' => 'Cleared all caches', 'details' => $results];
    } else if (isset($cacheDirs[$target])) {
        $result = clearCache($cacheDirs[$target], $target);
    } else {
        $result = ['success' => false, 'message' => "Unknown cache target: $target"];
    }
}

// Get cache statistics
$stats = [];
foreach ($cacheDirs as $name => $dir) {
    $stats[$name] = getCacheStats($dir, $name);
}

// Output the result
if ($isCli) {
    // Command-line output
    if ($result) {
        echo $result['message'] . PHP_EOL;
        if (isset($result['details'])) {
            foreach ($result['details'] as $name => $detail) {
                echo "  $name: " . $detail['message'] . PHP_EOL;
            }
        }
    }
    
    echo PHP_EOL . "Cache Statistics:" . PHP_EOL;
    foreach ($stats as $name => $stat) {
        echo "  $name: " . ($stat['exists'] ? "{$stat['count']} files ({$stat['size']})" : "not found") . PHP_EOL;
    }
} else {
    // Web interface output
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>API Cache Management</title>
        <style>
            body {
                font-family: Arial, sans-serif;
                margin: 0;
                padding: 20px;
                background-color: #f5f5f5;
                color: #333;
            }
            .container {
                max-width: 800px;
                margin: 0 auto;
                background-color: white;
                padding: 20px;
                border-radius: 5px;
                box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
            }
            h1 {
                color: #2c3e50;
                margin-top: 0;
            }
            .message {
                padding: 10px;
                margin-bottom: 20px;
                border-radius: 4px;
            }
            .success {
                background-color: #d4edda;
                color: #155724;
            }
            .error {
                background-color: #f8d7da;
                color: #721c24;
            }
            table {
                width: 100%;
                border-collapse: collapse;
                margin-bottom: 20px;
            }
            th, td {
                padding: 10px;
                text-align: left;
                border-bottom: 1px solid #ddd;
            }
            th {
                background-color: #f2f2f2;
            }
            .actions {
                margin-top: 20px;
            }
            .btn {
                display: inline-block;
                padding: 8px 16px;
                background-color: #3498db;
                color: white;
                text-decoration: none;
                border-radius: 4px;
                margin-right: 10px;
                margin-bottom: 10px;
            }
            .btn:hover {
                background-color: #2980b9;
            }
            .btn-danger {
                background-color: #e74c3c;
            }
            .btn-danger:hover {
                background-color: #c0392b;
            }
            .btn-warning {
                background-color: #f39c12;
            }
            .btn-warning:hover {
                background-color: #d35400;
            }
        </style>
    </head>
    <body>
        <div class="container">
            <h1>API Cache Management</h1>
            
            <?php if ($result): ?>
                <div class="message <?php echo $result['success'] ? 'success' : 'error'; ?>">
                    <?php echo htmlspecialchars($result['message']); ?>
                    <?php if (isset($result['details'])): ?>
                        <ul>
                            <?php foreach ($result['details'] as $name => $detail): ?>
                                <li><?php echo htmlspecialchars("$name: {$detail['message']}"); ?></li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
            
            <h2>Cache Statistics</h2>
            <table>
                <thead>
                    <tr>
                        <th>Cache</th>
                        <th>Files</th>
                        <th>Size</th>
                        <th>Oldest Entry</th>
                        <th>Newest Entry</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($stats as $name => $stat): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($name); ?></td>
                            <td><?php echo $stat['exists'] ? htmlspecialchars($stat['count']) : 'N/A'; ?></td>
                            <td><?php echo $stat['exists'] ? htmlspecialchars($stat['size']) : 'N/A'; ?></td>
                            <td><?php echo $stat['exists'] ? htmlspecialchars($stat['oldest']) : 'N/A'; ?></td>
                            <td><?php echo $stat['exists'] ? htmlspecialchars($stat['newest']) : 'N/A'; ?></td>
                            <td>
                                <?php if ($stat['exists'] && $stat['count'] > 0): ?>
                                    <a href="?action=clear&target=<?php echo urlencode($name); ?>" class="btn btn-warning" onclick="return confirm('Are you sure you want to clear the <?php echo htmlspecialchars($name); ?> cache?');">Clear</a>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            
            <div class="actions">
                <a href="?action=clear&target=all" class="btn btn-danger" onclick="return confirm('Are you sure you want to clear ALL caches?');">Clear All Caches</a>
                <a href="?" class="btn">Refresh</a>
            </div>
        </div>
    </body>
    </html>
    <?php
}
?> 