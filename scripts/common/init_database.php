<?php
/**
 * Database Initialization Script
 * Sets up the PostgreSQL database for the 15-minute city application
 */

// Include database configuration
require_once dirname(__DIR__, 2) . '/config/db_config.php';

// Log function
function log_message($message) {
    echo date('Y-m-d H:i:s') . " - $message\n";
}

log_message("Starting database initialization...");

try {
    // First, connect to postgres database to create our GIS database if it doesn't exist
    $conn_string = "host=" . DB_HOST . " port=" . DB_PORT . " dbname=postgres user=" . DB_USER . " password=" . DB_PASS;
    $conn = pg_connect($conn_string);
    
    if (!$conn) {
        throw new Exception("Failed to connect to PostgreSQL: " . pg_last_error());
    }
    
    // Check if our database exists
    $query = "SELECT 1 FROM pg_database WHERE datname = '" . DB_NAME . "'";
    $result = pg_query($conn, $query);
    
    if (!$result) {
        throw new Exception("Failed to query PostgreSQL: " . pg_last_error($conn));
    }
    
    if (pg_num_rows($result) == 0) {
        // Database doesn't exist, create it
        log_message("Creating database " . DB_NAME . "...");
        
        $create_db_query = "CREATE DATABASE " . DB_NAME . " WITH OWNER = " . DB_USER;
        $result = pg_query($conn, $create_db_query);
        
        if (!$result) {
            throw new Exception("Failed to create database: " . pg_last_error($conn));
        }
        
        log_message("Database created successfully.");
    } else {
        log_message("Database already exists.");
    }
    
    // Close connection to postgres database
    pg_close($conn);
    
    // Connect to our database
    $conn = getDbConnection();
    
    // Create necessary PostgreSQL extensions if they don't exist
    log_message("Setting up PostgreSQL extensions...");
    
    // First check if PostGIS is available
    $check_postgis_query = "SELECT 1 FROM pg_available_extensions WHERE name = 'postgis'";
    $check_result = pg_query($conn, $check_postgis_query);
    
    if (!$check_result || pg_num_rows($check_result) == 0) {
        log_message("WARNING: PostGIS extension is not available on this PostgreSQL installation.");
        log_message("Please install PostGIS using one of the following commands:");
        log_message("For Ubuntu/Debian: sudo apt-get install postgresql-17-postgis-3");
        log_message("For CentOS/RHEL: sudo dnf install postgis30_16");
        log_message("For macOS (Homebrew): brew install postgis");
        log_message("After installation, restart PostgreSQL and run this script again.");
    } else {
        // PostGIS extension (for spatial functions)
        $result = pg_query($conn, "CREATE EXTENSION IF NOT EXISTS postgis");
        if (!$result) {
            throw new Exception("Failed to create PostGIS extension: " . pg_last_error($conn));
        }
        log_message("PostGIS extension set up successfully.");
    }
    
    // hstore extension (for key-value pairs in OSM data)
    $result = pg_query($conn, "CREATE EXTENSION IF NOT EXISTS hstore");
    if (!$result) {
        throw new Exception("Failed to create hstore extension: " . pg_last_error($conn));
    }
    
    log_message("PostgreSQL extensions setup completed.");
    
    // Check if OSM tables exist (created by osm2pgsql)
    $check_table_query = "SELECT EXISTS (
        SELECT FROM information_schema.tables 
        WHERE table_schema = 'public' 
        AND table_name = 'planet_osm_point'
    )";
    $result = pg_query($conn, $check_table_query);
    $row = pg_fetch_row($result);
    
    if ($row[0] == 'f') {
        log_message("OSM tables don't exist. Please use osm2pgsql to import OpenStreetMap data.");
        log_message("Example command: C:/Programs/Programs/osm2pgsql-bin/osm2pgsql.exe -c -d " . DB_NAME . " -U " . DB_USER . " -W -H " . DB_HOST . " -P " . DB_PORT . " -S default.style portugal-latest.osm.pbf");
    } else {
        log_message("OSM tables already exist.");
        
        // Count POIs in the database
        $count_query = "
            SELECT
                COUNT(*) as hospitals FROM planet_osm_point WHERE amenity = 'hospital',
                COUNT(*) as schools FROM planet_osm_point WHERE amenity IN ('school', 'university', 'college', 'kindergarten'),
                COUNT(*) as health FROM planet_osm_point WHERE amenity IN ('clinic', 'doctors', 'dentist', 'pharmacy'),
                COUNT(*) as culture FROM planet_osm_point WHERE amenity IN ('theatre', 'cinema', 'library', 'arts_centre', 'community_centre', 'museum'),
                COUNT(*) as shops FROM planet_osm_point WHERE shop IS NOT NULL,
                COUNT(*) as parks FROM planet_osm_point WHERE leisure IN ('park', 'garden', 'playground')
        ";
        
        $result = pg_query($conn, $count_query);
        if ($result) {
            $stats = pg_fetch_assoc($result);
            log_message("Database statistics:");
            foreach ($stats as $key => $value) {
                log_message("  - $key: $value");
            }
        }
    }
    
    // Optional: Create indexes for better performance
    log_message("Creating indexes for better performance...");
    
    $index_queries = [
        "CREATE INDEX IF NOT EXISTS idx_planet_osm_point_amenity ON planet_osm_point USING btree (amenity)",
        "CREATE INDEX IF NOT EXISTS idx_planet_osm_point_shop ON planet_osm_point USING btree (shop)",
        "CREATE INDEX IF NOT EXISTS idx_planet_osm_point_leisure ON planet_osm_point USING btree (leisure)",
        "CREATE INDEX IF NOT EXISTS idx_planet_osm_polygon_building ON planet_osm_polygon USING btree (building)",
        "CREATE INDEX IF NOT EXISTS idx_planet_osm_polygon_admin_level ON planet_osm_polygon USING btree (admin_level)"
    ];
    
    foreach ($index_queries as $query) {
        $result = pg_query($conn, $query);
        if (!$result) {
            log_message("Warning: Failed to create index: " . pg_last_error($conn));
        }
    }
    
    log_message("Indexes created successfully.");
    
    log_message("Database initialization completed successfully.");
    
} catch (Exception $e) {
    log_message("Error: " . $e->getMessage());
    exit(1);
} finally {
    if (isset($conn) && $conn) {
        pg_close($conn);
    }
}
?>