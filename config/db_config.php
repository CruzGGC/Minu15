<?php
// Database connection configuration
define('DB_HOST', 'localhost');
define('DB_NAME', 'projetosig');
define('DB_USER', 'postgres'); // Change this to your PostgreSQL username
define('DB_PASS', '1234'); // Change this to your PostgreSQL password
define('DB_PORT', '5432');     // Default PostgreSQL port

// Database connection function
function getDbConnection() {
    $connection_string = "host=" . DB_HOST . " port=" . DB_PORT . " dbname=" . DB_NAME . " user=" . DB_USER . " password=" . DB_PASS;
    
    $conn = pg_connect($connection_string);
    
    if (!$conn) {
        die("Connection failed: " . pg_last_error());
    }
    
    return $conn;
}
?>