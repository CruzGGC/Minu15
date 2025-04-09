-- README.md - Project documentation
# 15-Minute City GIS Application

This project is a GIS application for visualizing and analyzing the 15-minute city concept, which promotes urban planning where residents can access all essential services within a 15-minute walk, bike ride, or public transit trip.

## Features

- Interactive map interface using OpenStreetMap
- PostgreSQL/PostGIS database integration for spatial data storage and queries
- Selection of transportation modes (walking, cycling, driving)
- Customizable POI (Points of Interest) filtering
- Distance/time slider to adjust the travel radius
- Isochrone visualization showing reachable areas within the selected time
- Search functionality for locations

## Technology Stack

- **Frontend**: HTML, CSS, JavaScript, Leaflet.js
- **Backend**: Node.js, Express
- **Database**: PostgreSQL with PostGIS and pgRouting extensions
- **Data Source**: OpenStreetMap data

## Setup Instructions

### Prerequisites

- Node.js and npm
- PostgreSQL database with PostGIS and pgRouting extensions
- OpenStreetMap data for your area of interest

### Database Setup

1. Create a PostgreSQL database:
   ```
   createdb 15min_city
   ```

2. Enable PostGIS and pgRouting extensions:
   ```
   psql -d 15min_city -c "CREATE EXTENSION postgis; CREATE EXTENSION pgrouting;"
   ```

3. Import OpenStreetMap data:
   ```
   osm2pgsql -d 15min_city -H localhost -U postgres -W -s your_area.osm
   osm2pgrouting -f your_area.osm -d 15min_city -U postgres -W --conf mapconfig.xml
   ```

4. Run the database setup script:
   ```
   psql -d 15min_city -f database-setup.sql
   ```

### Application Setup

1. Clone the repository
2. Install dependencies:
   ```
   npm install
   ```
3. Configure the database connection in `server.js`
4. Start the server:
   ```
   node server.js
   ```
5. Open `index.html` in your browser or serve it using a web server

## Usage

1. Click on the map to set your starting location
2. Select your preferred transportation mode (walking, cycling, or driving)
3. Adjust the time slider to set your maximum travel time
4. Select the points of interest categories you want to display
5. Click the "Calculate" button to visualize the 15-minute city radius and available POIs

## Project Structure

- `index.html`: Main application interface
- `app.js`: Frontend JavaScript code for the interactive map
- `server.js`: Backend Node.js server for handling API requests
- `database-setup.sql`: SQL script for setting up the PostgreSQL/PostGIS database

## Further Development

- Add public transportation options
- Implement more accurate isochrone generation using real-world routing data
- Add time-of-day variation to account for traffic patterns
- Include demographic and statistical analysis tools
- Add ability to compare different neighborhoods