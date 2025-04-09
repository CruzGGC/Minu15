const express = require('express');
const { Pool } = require('pg');
const cors = require('cors');

const app = express();
const port = 3000;

app.use(cors());
app.use(express.json());

// PostgreSQL connection pool with PostGIS
const pool = new Pool({
    user: 'postgres',
    host: 'localhost',
    database: '15min_city',
    password: 'postgres',  // Senha padrão, altere conforme necessário
    port: 5432,
});

// Get POIs within isochrone
app.post('/api/pois', async (req, res) => {
    try {
        const { lat, lng, minutes, transportMode, poiTypes } = req.body;
        
        // Calculate speed in meters per minute based on transport mode
        let speed;
        switch(transportMode) {
            case 'walking':
                speed = 80; // ~4.8 km/h
                break;
            case 'cycling':
                speed = 250; // ~15 km/h
                break;
            case 'driving':
                speed = 400; // ~24 km/h in city
                break;
            default:
                speed = 250;
        }
        
        const radius = minutes * speed;
        
        // Construct the POI type filter
        const poiFilter = poiTypes.map(type => `amenity = '${type}'`).join(' OR ');
        
        // Query POIs within radius using PostGIS
        const result = await pool.query(`
            SELECT 
                id, 
                name, 
                amenity, 
                ST_X(ST_Transform(way, 4326)) as lng,
                ST_Y(ST_Transform(way, 4326)) as lat
            FROM 
                planet_osm_point
            WHERE 
                (${poiFilter})
                AND ST_DWithin(
                    way,
                    ST_Transform(ST_SetSRID(ST_MakePoint($1, $2), 4326), 3857),
                    $3
                )
        `, [lng, lat, radius]);
        
        res.json(result.rows);
    } catch (error) {
        console.error('Error fetching POIs:', error);
        res.status(500).json({ error: 'Failed to fetch POIs' });
    }
});

// Generate isochrone using pgRouting 
app.post('/api/isochrone', async (req, res) => {
    try {
        const { lat, lng, minutes, transportMode } = req.body;
        
        // First, find the nearest road vertex
        const nearestVertex = await pool.query(`
            SELECT 
                id, 
                source,
                target
            FROM ways
            ORDER BY ways.the_geom <-> ST_SetSRID(ST_MakePoint($1, $2), 4326)
            LIMIT 1
        `, [lng, lat]);
        
        if (nearestVertex.rows.length === 0) {
            return res.status(404).json({ error: 'No road network found nearby' });
        }
        
        const sourceVertex = nearestVertex.rows[0].source;
        
        // Set cost column based on transport mode
        let costColumn;
        switch(transportMode) {
            case 'walking':
                costColumn = 'cost_pedestrian';
                break;
            case 'cycling':
                costColumn = 'cost_bicycle';
                break;
            case 'driving':
                costColumn = 'cost';
                break;
            default:
                costColumn = 'cost';
        }
        
        // Calculate the maximum cost (in seconds)
        const maxCost = minutes * 60;
        
        // Generate isochrone using pgRouting Dijkstra algorithm
        const result = await pool.query(`
            WITH dijkstra AS (
                SELECT * FROM pgr_drivingDistance(
                    'SELECT gid as id, source, target, ${costColumn} as cost FROM ways',
                    $1,
                    $2,
                    false
                )
            ),
            points AS (
                SELECT id, ST_X(the_geom) AS lng, ST_Y(the_geom) AS lat
                FROM ways_vertices_pgr
                JOIN dijkstra ON dijkstra.node = ways_vertices_pgr.id
            )
            SELECT ST_AsGeoJSON(ST_ConvexHull(ST_Collect(ST_SetSRID(ST_MakePoint(lng, lat), 4326)))) AS geojson
            FROM points
        `, [sourceVertex, maxCost]);
        
        if (result.rows.length === 0 || !result.rows[0].geojson) {
            return res.status(404).json({ error: 'Could not generate isochrone' });
        }
        
        res.json({ geojson: result.rows[0].geojson });
    } catch (error) {
        console.error('Error generating isochrone:', error);
        res.status(500).json({ error: 'Failed to generate isochrone' });
    }
});

// Get POI details when clicked
app.get('/api/poi/:id', async (req, res) => {
    try {
        const poiId = req.params.id;
        
        const result = await pool.query(`
            SELECT 
                osm_id,
                name,
                amenity,
                shop,
                leisure,
                "addr:street",
                "addr:housenumber",
                "addr:postcode",
                "addr:city",
                phone,
                website,
                opening_hours,
                ST_X(ST_Transform(way, 4326)) as lng,
                ST_Y(ST_Transform(way, 4326)) as lat
            FROM 
                planet_osm_point
            WHERE 
                osm_id = $1
        `, [poiId]);
        
        if (result.rows.length === 0) {
            return res.status(404).json({ error: 'POI not found' });
        }
        
        res.json(result.rows[0]);
    } catch (error) {
        console.error('Error fetching POI details:', error);
        res.status(500).json({ error: 'Failed to fetch POI details' });
    }
});

// Get statistics for parish/freguesia
app.get('/api/statistics/:parishId', async (req, res) => {
    try {
        const parishId = req.params.parishId;
        
        // First get the parish geometry
        const parishQuery = await pool.query(`
            SELECT 
                osm_id, 
                name,
                way
            FROM 
                planet_osm_polygon
            WHERE 
                osm_id = $1
                AND admin_level = '8' -- Parish level in Portugal
            LIMIT 1
        `, [parishId]);
        
        if (parishQuery.rows.length === 0) {
            return res.status(404).json({ error: 'Parish not found' });
        }
        
        const parish = parishQuery.rows[0];
        
        // Count POIs by category within the parish
        const statsQuery = await pool.query(`
            SELECT 
                CASE
                    WHEN amenity IN ('hospital', 'pharmacy', 'dentist', 'clinic', 'doctors') THEN 'healthcare'
                    WHEN amenity IN ('restaurant', 'cafe', 'bar', 'fast_food') THEN 'food'
                    WHEN shop IN ('supermarket', 'convenience', 'bakery', 'butcher', 'greengrocer') THEN 'shopping'
                    WHEN amenity IN ('school', 'university', 'kindergarten', 'library') THEN 'education'
                    WHEN leisure IN ('park', 'playground', 'sports_centre', 'swimming_pool') 
                        OR amenity IN ('theatre', 'cinema') THEN 'leisure'
                    ELSE 'other'
                END as category,
                COUNT(*) as count
            FROM 
                planet_osm_point
            WHERE 
                ST_Contains(
                    $1,
                    way
                )
                AND (
                    amenity IN ('hospital', 'pharmacy', 'dentist', 'clinic', 'doctors', 
                                'restaurant', 'cafe', 'bar', 'fast_food',
                                'school', 'university', 'kindergarten', 'library',
                                'theatre', 'cinema')
                    OR shop IN ('supermarket', 'convenience', 'bakery', 'butcher', 'greengrocer')
                    OR leisure IN ('park', 'playground', 'sports_centre', 'swimming_pool')
                )
            GROUP BY 
                category
        `, [parish.way]);
        
        // Get area in square kilometers
        const areaQuery = await pool.query(`
            SELECT 
                ST_Area(ST_Transform($1, 3857))/1000000 as area_sqkm
            FROM 
                planet_osm_polygon
            WHERE 
                osm_id = $2
            LIMIT 1
        `, [parish.way, parishId]);
        
        // Calculate road length in kilometers
        const roadLengthQuery = await pool.query(`
            SELECT 
                SUM(ST_Length(ST_Transform(way, 3857)))/1000 as road_length_km
            FROM 
                planet_osm_line
            WHERE 
                highway IS NOT NULL
                AND ST_Intersects(way, $1)
        `, [parish.way]);
        
        const result = {
            parish: {
                id: parish.osm_id,
                name: parish.name,
                area_sqkm: areaQuery.rows[0].area_sqkm
            },
            poi_counts: statsQuery.rows,
            infrastructure: {
                road_length_km: roadLengthQuery.rows[0].road_length_km
            }
        };
        
        res.json(result);
    } catch (error) {
        console.error('Error generating parish statistics:', error);
        res.status(500).json({ error: 'Failed to generate parish statistics' });
    }
});

// Get parish/freguesia by coordinates
app.get('/api/parish', async (req, res) => {
    try {
        const { lat, lng } = req.query;
        
        if (!lat || !lng) {
            return res.status(400).json({ error: 'Missing latitude or longitude parameters' });
        }
        
        const result = await pool.query(`
            SELECT 
                osm_id, 
                name,
                admin_level
            FROM 
                planet_osm_polygon
            WHERE 
                admin_level = '8'  -- Parish level in Portugal
                AND ST_Contains(
                    way,
                    ST_Transform(ST_SetSRID(ST_MakePoint($1, $2), 4326), 3857)
                )
            LIMIT 1
        `, [lng, lat]);
        
        if (result.rows.length === 0) {
            return res.status(404).json({ error: 'No parish found at these coordinates' });
        }
        
        res.json(result.rows[0]);
    } catch (error) {
        console.error('Error finding parish:', error);
        res.status(500).json({ error: 'Failed to find parish' });
    }
});

// Get all POI categories and subcategories
app.get('/api/poi-categories', async (req, res) => {
    try {
        const result = await pool.query(`
            SELECT 
                category,
                subcategory,
                COUNT(*) as count
            FROM 
                poi_categories
            GROUP BY 
                category, subcategory
            ORDER BY 
                category, subcategory
        `);
        
        const categories = {};
        
        result.rows.forEach(row => {
            if (!categories[row.category]) {
                categories[row.category] = [];
            }
            
            categories[row.category].push({
                name: row.subcategory,
                count: row.count
            });
        });
        
        res.json(categories);
    } catch (error) {
        console.error('Error fetching POI categories:', error);
        res.status(500).json({ error: 'Failed to fetch POI categories' });
    }
});

app.listen(port, () => {
    console.log(`15-Minute City API server running on port ${port}`);
});