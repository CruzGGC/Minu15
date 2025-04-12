# 15-Minute City Explorer

A GIS application exploring the concept of the "15-minute city" - a vision of urban planning where all necessities of daily life are accessible within a 15-minute trip from any location, whether by walking, cycling, or driving.

## Project Overview

This application allows users to:

- Explore what's accessible within a 15-minute range from any point in Portugal
- Visualize different travel modes (walking, cycling, driving)
- Discover various points of interest (hospitals, schools, health centers, etc.)
- View statistics about the selected area
- Access details about each point of interest

## Features

- **Interactive Map**: Click anywhere to see what's accessible within your chosen time frame
- **Multiple Transportation Modes**: Toggle between walking, cycling, and driving
- **Adjustable Travel Time**: Slide to adjust the maximum travel time (5-30 minutes)
- **POI Filtering**: Show or hide specific types of points of interest
- **Area Statistics**: View statistics about the selected area, including POI counts and estimated population
- **POI Details**: Click on any point of interest to see detailed information about it

## Technical Architecture

- **Frontend**: HTML, CSS, JavaScript with Leaflet.js and Turf.js
- **Backend**: PHP
- **Database**: PostgreSQL with PostGIS extension
- **Data Source**: OpenStreetMap data via Geofabrik

## Installation and Setup

### Prerequisites

- Web server with PHP 7.4+ (e.g., Apache, Nginx with XAMPP/WAMP)
- PostgreSQL 12+ with PostGIS extension
- Osmosis tool for importing OSM data

### Installation Steps

1. **Set up the database**:
   - Install PostgreSQL and PostGIS
   - Run the initialization script: `scripts/init_database.bat`

2. **Import OpenStreetMap data**:
   - Download Portugal OSM data from Geofabrik manually or use the provided script
   - Run the update script: `php scripts/update_geofabrik_data.php`
   - Wait for the import to complete (this may take some time)
   
3. **Schedule weekly data updates** (optional):
   - Run `scripts/schedule_geofabrik_update.bat` to create a scheduled task
   - This will download fresh data from Geofabrik every Sunday at 3:00 AM

4. **Configure your web server**:
   - Point your web server document root to the project directory
   - Make sure PHP is configured to work with PostgreSQL

5. **Access the application**:
   - Open your web browser and navigate to your web server's URL
   - Start exploring the 15-minute city concept!

## Usage Guide

1. **Select a location**: Click anywhere on the map to set your starting point
2. **Choose transportation mode**: Select walking, cycling, or driving from the dropdown
3. **Adjust travel time**: Move the slider to change the maximum travel time
4. **Filter POIs**: Use the checkboxes to show or hide specific types of points of interest
5. **View statistics**: Check the statistics panel for information about the selected area
6. **Explore POI details**: Click on any point of interest marker for more information

## Data Structure

The application uses OpenStreetMap data imported into PostgreSQL using the following structure:

- **planet_osm_point**: Contains point features like hospitals, schools, shops
- **planet_osm_line**: Contains linear features like roads, railways
- **planet_osm_polygon**: Contains area features like buildings, parks
- **planet_osm_roads**: Contains major roads network

## Project Structure

```
/
├── assets/             # Images and other assets
├── config/             # Configuration files
│   └── db_config.php   # Database connection settings
├── css/                # Stylesheets
│   └── style.css       # Main CSS file
├── includes/           # PHP include files
│   ├── fetch_pois.php  # API endpoint to get POIs
│   └── fetch_statistics.php # API endpoint to get area statistics
├── js/                 # JavaScript files
│   ├── controls.js     # UI control functionality
│   └── map.js          # Map and geospatial functionality
├── scripts/            # Utility scripts
│   ├── init_database.php        # Database initialization
│   ├── init_database.bat        # Windows batch for database init
│   ├── update_geofabrik_data.php # Data update script
│   └── schedule_geofabrik_update.bat # Task scheduler
└── index.php           # Main application entry point
```

## Developer Notes

- The isochrone generation currently uses basic buffer circles based on travel speed. For more accurate results, consider implementing actual routing algorithms.
- Travel speeds are estimated as: walking (5 km/h), cycling (15 km/h), driving (60 km/h).
- Population estimates are rough approximations based on building counts.

## Future Enhancements

- Implement routing algorithms for more accurate travel time polygons
- Add more detailed POI information
- Integrate public transport options
- Add ability to save and share locations
- Include demographic data for better population estimates
- Expand beyond Portugal to other regions

## License

This project is for educational purposes.

## Credits

- OpenStreetMap data from [Geofabrik](https://download.geofabrik.de/)
- Map visualization with [Leaflet](https://leafletjs.com/)
- Geospatial calculations with [Turf.js](https://turfjs.org/)