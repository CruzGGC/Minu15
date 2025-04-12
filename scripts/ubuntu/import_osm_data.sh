#!/bin/bash
echo "Importing OpenStreetMap data with osm2pgsql..."

# Path to osm2pgsql executable (assuming it's installed via apt)
OSM2PGSQL_PATH="/usr/bin/osm2pgsql"

# Path to OSM data file (corrected path)
OSM_FILE="$(dirname "$0")/../../data/geofabrik/portugal-latest.osm.pbf"

# Path to the style file (in the common directory)
STYLE_FILE="$(dirname "$0")/../common/default.style"

# Database connection details
DB_NAME="projetosig"
DB_USER="postgres"
DB_HOST="localhost"
DB_PORT="5432"

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
"$OSM2PGSQL_PATH" -c -d "$DB_NAME" -U "$DB_USER" -H "$DB_HOST" -P "$DB_PORT" -W -S "$STYLE_FILE" "$OSM_FILE"

echo ""
echo "Import process completed."
echo ""