<?php
/**
 * Script to download and update Portugal Geofabrik data on a weekly basis
 * This script can be scheduled using Windows Task Scheduler
 * 
 * Usage:
 *   - php update_geofabrik_data.php           # Download and import data
 *   - php update_geofabrik_data.php download_only  # Only download without importing
 */

// Configuration
$downloadDir = dirname(__DIR__) . '/data/geofabrik/';
$logFile = dirname(__DIR__) . '/logs/geofabrik_update.log';
$geofabrikUrl = 'https://download.geofabrik.de/europe/portugal-latest.osm.pbf';
$osm2pgsqlPath = 'C:/Programs/Programs/osm2pgsql-bin/osm2pgsql.exe'; // Updated path to osm2pgsql

// Check command line arguments
$downloadOnly = false;
if (isset($argv[1]) && $argv[1] === 'download_only') {
    $downloadOnly = true;
    echo "Running in download-only mode\n";
}

// Include database configuration
require_once dirname(__DIR__) . '/config/db_config.php';

// Create directories if they don't exist
if (!file_exists($downloadDir)) {
    mkdir($downloadDir, 0755, true);
}

if (!file_exists(dirname($logFile))) {
    mkdir(dirname($logFile), 0755, true);
}

// Log function
function logMessage($message) {
    global $logFile;
    $timestamp = date('Y-m-d H:i:s');
    $logEntry = "[$timestamp] $message\n";
    file_put_contents($logFile, $logEntry, FILE_APPEND);
    echo $logEntry;
}

// Download the latest data
logMessage("Starting download of Portugal Geofabrik data");

$outputFile = $downloadDir . 'portugal-latest.osm.pbf';
$previousFile = $downloadDir . 'portugal-previous.osm.pbf';

// Backup previous file if it exists
if (file_exists($outputFile)) {
    if (file_exists($previousFile)) {
        unlink($previousFile);
    }
    rename($outputFile, $previousFile);
    logMessage("Previous data backed up");
}

// Download new file
$ch = curl_init($geofabrikUrl);
$fp = fopen($outputFile, 'w');

curl_setopt($ch, CURLOPT_FILE, $fp);
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);

logMessage("Downloading file from: $geofabrikUrl");
$success = curl_exec($ch);

if ($success) {
    logMessage("Download completed successfully");
    
    // Import the data into PostgreSQL using osm2pgsql if not in download-only mode
    if (!$downloadOnly) {
        logMessage("Starting database import using osm2pgsql");
        
        // Create osm2pgsql command
        $cmd = "\"$osm2pgsqlPath\" -c -d " . DB_NAME . " -U " . DB_USER . " -W -H " . DB_HOST . " -P " . DB_PORT . " -S default.style \"$outputFile\"";
        
        // Execute the command
        $descriptorspec = array(
            0 => array("pipe", "r"),  // stdin
            1 => array("pipe", "w"),  // stdout
            2 => array("pipe", "w")   // stderr
        );
        
        $process = proc_open($cmd, $descriptorspec, $pipes);
        
        if (is_resource($process)) {
            // Send password to stdin
            fwrite($pipes[0], DB_PASS . "\n");
            fclose($pipes[0]);
            
            // Get output
            $output = stream_get_contents($pipes[1]);
            $error = stream_get_contents($pipes[2]);
            fclose($pipes[1]);
            fclose($pipes[2]);
            
            // Close process
            $returnCode = proc_close($process);
            
            if ($returnCode === 0) {
                logMessage("Database import successful");
                logMessage("Output: " . $output);
            } else {
                logMessage("Database import failed with code: $returnCode");
                logMessage("Output: " . $output);
                logMessage("Error: " . $error);
            }
        } else {
            logMessage("Failed to execute osm2pgsql command");
        }
    } else {
        logMessage("Skipping database import (download-only mode)");
    }
} else {
    logMessage("Download failed: " . curl_error($ch));
}

curl_close($ch);
fclose($fp);

logMessage("Geofabrik data update process completed");
?>