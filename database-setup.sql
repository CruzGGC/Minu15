-- Create the database extension for PostGIS
CREATE EXTENSION IF NOT EXISTS postgis;
CREATE EXTENSION IF NOT EXISTS pgrouting;

-- OpenStreetMap import tables (osm2pgsql format)
-- These tables are typically created by osm2pgsql when importing OSM data
-- planet_osm_point: Points of interest (nodes)
-- planet_osm_line: Roads, paths, etc.
-- planet_osm_polygon: Areas like building footprints, parks, etc.
-- planet_osm_roads: Major roads extracted from planet_osm_line

-- Create a table for road network (if using pgRouting)
CREATE TABLE IF NOT EXISTS ways (
    gid SERIAL PRIMARY KEY,
    osm_id BIGINT,
    name TEXT,
    source INTEGER,
    target INTEGER,
    cost FLOAT, -- Time in seconds for cars
    cost_bicycle FLOAT, -- Time in seconds for bicycles
    cost_pedestrian FLOAT, -- Time in seconds for pedestrians
    one_way INTEGER,
    maxspeed_forward INTEGER,
    maxspeed_backward INTEGER,
    the_geom GEOMETRY(LINESTRING, 4326)
);

-- Create vertices table for pgRouting
CREATE TABLE IF NOT EXISTS ways_vertices_pgr (
    id SERIAL PRIMARY KEY,
    the_geom GEOMETRY(POINT, 4326)
);

-- Create spatial indexes
CREATE INDEX IF NOT EXISTS ways_geom_idx ON ways USING GIST(the_geom);
CREATE INDEX IF NOT EXISTS ways_vertices_geom_idx ON ways_vertices_pgr USING GIST(the_geom);
CREATE INDEX IF NOT EXISTS planet_osm_point_way_idx ON planet_osm_point USING GIST(way);

-- Import script for OpenStreetMap data
-- Note: You would typically use osm2pgsql and osm2pgrouting tools to import data
-- Example commands (to be run from shell, not SQL):
-- osm2pgsql -d 15min_city -H localhost -U postgres -W -s aveiro.osm
-- osm2pgrouting -f aveiro.osm -d 15min_city -U postgres -W --conf mapconfig.xml

-- Create POI categories view for easier querying
CREATE OR REPLACE VIEW poi_categories AS
SELECT 
    osm_id,
    name,
    'healthcare' AS category,
    amenity AS subcategory,
    way
FROM planet_osm_point
WHERE amenity IN ('hospital', 'pharmacy', 'dentist', 'clinic', 'doctors')

UNION ALL

SELECT 
    osm_id,
    name,
    'food' AS category,
    amenity AS subcategory,
    way
FROM planet_osm_point
WHERE amenity IN ('restaurant', 'cafe', 'bar', 'fast_food')

UNION ALL

SELECT 
    osm_id,
    name,
    'shopping' AS category,
    shop AS subcategory,
    way
FROM planet_osm_point
WHERE shop IN ('supermarket', 'convenience', 'bakery', 'butcher', 'greengrocer')

UNION ALL

SELECT 
    osm_id,
    name,
    'education' AS category,
    amenity AS subcategory,
    way
FROM planet_osm_point
WHERE amenity IN ('school', 'university', 'kindergarten', 'library')

UNION ALL

SELECT 
    osm_id,
    name,
    'leisure' AS category,
    COALESCE(leisure, amenity) AS subcategory,
    way
FROM planet_osm_point
WHERE leisure IN ('park', 'playground', 'sports_centre', 'swimming_pool') 
   OR amenity IN ('theatre', 'cinema');