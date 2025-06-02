<?php
/**
 * Database Configuration File
 * Contains connection parameters for PostgreSQL database with OSM data
 */

// Database connection parameters
$db_host = 'localhost';
$db_port = '5432';
$db_name = 'gis';
$db_user = 'postgres';
$db_password = 'postgres';

/**
 * Get a database connection with error handling
 * @return resource PostgreSQL connection resource
 * @throws Exception if connection fails
 */
function getDbConnection() {
    global $db_host, $db_port, $db_name, $db_user, $db_password;
    
    // Connection string
    $conn_string = "host={$db_host} port={$db_port} dbname={$db_name} user={$db_user} password={$db_password}";
    
    // Try to establish connection
    $conn = @pg_connect($conn_string);
    
    // Check if connection was successful
    if (!$conn) {
        // Log the error to a file
        $error_message = "Failed to connect to PostgreSQL database: " . pg_last_error();
        error_log($error_message, 3, "../logs/db_errors.log");
        
        // Throw exception for handling in the calling code
        throw new Exception("Database connection failed. Check server configuration.");
    }
    
    // Set client encoding to UTF8
    pg_set_client_encoding($conn, "UTF8");
    
    return $conn;
}

/**
 * Execute a query with error handling
 * @param resource $conn PostgreSQL connection
 * @param string $query SQL query to execute
 * @return resource Query result
 * @throws Exception if query fails
 */
function executeQuery($conn, $query) {
    $result = @pg_query($conn, $query);
    
    if (!$result) {
        $error_message = "Query execution failed: " . pg_last_error($conn);
        error_log($error_message, 3, "../logs/db_errors.log");
        throw new Exception("Database query failed: " . pg_last_error($conn));
    }
    
    return $result;
}
?>