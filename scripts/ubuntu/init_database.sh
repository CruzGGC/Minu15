#!/bin/bash
echo "Initializing 15-Minute City Explorer Database..."

# Path to PHP executable - update this to your PHP installation path
PHP_PATH="/usr/bin/php"

# Run the initialization script (from the common directory)
$PHP_PATH "$(dirname "$0")/../common/init_database.php"

echo ""
echo "Database initialization completed."