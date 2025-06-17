/**
 * Configuração da API para o 15-Minute City Explorer
 * Contém chaves de API e pontos de extremidade para serviços externos
 * 
 * IMPORTANTE: Este ficheiro é ignorado pelo Git para proteger as chaves de API.
 * Crie uma cópia deste ficheiro com o nome api_config.js.example com valores de espaço reservado para versionamento.
 */

// Configuração da API OpenRouteService
const ORS_API_URL = 'https://api.openrouteservice.org';
const ORS_API_KEY = '5b3ce3597851110001cf624850f24527ef9a4022921696fb1ba0e525';

// Configuração do servidor de tiles (Opcional - atualmente a usar tiles OSM padrão)
const MAP_TILES_URL = 'https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png';
const MAP_TILES_ATTRIBUTION = '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors';