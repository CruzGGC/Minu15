#!/bin/bash
echo "Initializing 15-Minute City Explorer Database (WSL-compatible version)..."

# Path to PHP executable - using the configured PHP path
PHP_PATH=$(bash "$(dirname "$0")/../../config/php_path.sh")

# Check if PostgreSQL is running in WSL
pg_isready -h localhost -p 5432 > /dev/null 2>&1
if [ $? -ne 0 ]; then
    echo "PostgreSQL is not running. Trying to start PostgreSQL service..."
    sudo service postgresql start
    sleep 3
    
    # Check again
    pg_isready -h localhost -p 5432 > /dev/null 2>&1
    if [ $? -ne 0 ]; then
        echo "Could not start PostgreSQL. Please start it manually."
        exit 1
    fi
fi

# Run the initialization script (from the common directory)
bash "$(dirname "$0")/../../config/php_path.sh" "$(dirname "$0")/../common/init_database.php"

echo ""
echo "Database initialization completed."