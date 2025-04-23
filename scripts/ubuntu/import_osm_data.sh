#!/bin/bash
echo "Importing OpenStreetMap data with osm2pgsql (WSL-compatible version)..."

# Path to osm2pgsql executable (assuming it's installed via apt)
OSM2PGSQL_PATH="/usr/bin/osm2pgsql"

# Check if osm2pgsql is installed
if [ ! -f "$OSM2PGSQL_PATH" ]; then
    echo "osm2pgsql not found. Installing..."
    sudo apt-get update && sudo apt-get install -y osm2pgsql
fi

# Path to OSM data file (with WSL path handling)
OSM_FILE="$(dirname "$0")/../../data/geofabrik/portugal-latest.osm.pbf"

# Path to the style file (in the common directory)
STYLE_FILE="$(dirname "$0")/../common/default.style"

# Database connection details
DB_NAME="projetosig"
DB_USER="postgres"
DB_HOST="localhost"
DB_PORT="5432"

# Check if PostgreSQL is running in WSL
pg_isready -h $DB_HOST -p $DB_PORT > /dev/null 2>&1
if [ $? -ne 0 ]; then
    echo "PostgreSQL is not running. Trying to start PostgreSQL service..."
    sudo service postgresql start
    sleep 3
    
    # Check again
    pg_isready -h $DB_HOST -p $DB_PORT > /dev/null 2>&1
    if [ $? -ne 0 ]; then
        echo "Could not start PostgreSQL. Please start it manually."
        exit 1
    fi
fi

# Check if data directory exists, if not create it
if [ ! -d "$(dirname "$0")/../../data/geofabrik" ]; then
    mkdir -p "$(dirname "$0")/../../data/geofabrik"
fi

# Check if OSM file exists
if [ ! -f "$OSM_FILE" ]; then
    echo "OSM data file not found at: $OSM_FILE"
    echo "Downloading Portugal OSM data from Geofabrik..."
    
    # Run the PHP download script from common directory
    "$(dirname "$0")/../../config/php_path.sh" "$(dirname "$0")/../common/update_geofabrik_data.php" download_only
    
    if [ ! -f "$OSM_FILE" ]; then
        echo "Failed to download OSM data."
        echo "Please download manually from https://download.geofabrik.de/europe/portugal-latest.osm.pbf"
        echo "and place it in the data/geofabrik folder."
        exit 1
    fi
fi

echo ""
echo "Using osm2pgsql to import OpenStreetMap data..."
echo ""
echo "Database: $DB_NAME"
echo "OSM File: $OSM_FILE"
echo "Style File: $STYLE_FILE"
echo ""
echo "This process will take some time, please be patient..."
echo ""

# Run osm2pgsql with appropriate parameters
# Using --slim and --drop to optimize for WSL's potentially limited memory
"$OSM2PGSQL_PATH" -c -d "$DB_NAME" -U "$DB_USER" -H "$DB_HOST" -P "$DB_PORT" -W -S "$STYLE_FILE" --slim --drop "$OSM_FILE"

echo ""
echo "Import process completed."
echo ""