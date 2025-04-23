/**
 * API Configuration for 15-Minute City Explorer
 * Contains API keys and endpoints for external services
 * 
 * IMPORTANT: This file is gitignored to protect API keys.
 * Create a copy of this file named api_config.js.example with placeholder values for versioning.
 */

// OpenRouteService API Configuration
const ORS_API_URL = 'https://api.openrouteservice.org';
// Updated API key - previous key was disallowed (403 error)
const ORS_API_KEY = '5b3ce3597851110001cf624850f24527ef9a4022921696fb1ba0e525';

// Tile server configuration (Optional - currently using default OSM tiles)
const MAP_TILES_URL = 'https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png';
const MAP_TILES_ATTRIBUTION = '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors';