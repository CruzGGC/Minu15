<?php
/**
 * Mock Data Provider
 * 
 * This file provides sample data for testing when the database is not available.
 * It contains functions to generate realistic mock data for POIs and statistics.
 */

/**
 * Generate mock POI data for a given type and location
 * 
 * @param string $type The POI type (e.g., 'hospitals', 'schools')
 * @param float $lat Latitude of the center point
 * @param float $lng Longitude of the center point
 * @param float $radius Radius in meters
 * @return array Array of POI objects
 */
function generateMockPOIs($type, $lat, $lng, $radius) {
    // Convert radius from meters to degrees (approximate)
    $radiusDeg = $radius / 111000; // ~111km per degree at the equator
    
    // Define count ranges for different POI types
    $countRanges = [
        'hospitals' => [1, 3],
        'health_centers' => [2, 5],
        'pharmacies' => [3, 8],
        'dentists' => [2, 6],
        'schools' => [2, 7],
        'universities' => [0, 2],
        'kindergartens' => [1, 4],
        'libraries' => [1, 3],
        'supermarkets' => [2, 6],
        'malls' => [0, 2],
        'restaurants' => [5, 15],
        'atms' => [3, 8],
        'police_stations' => [0, 2],
        'fire_stations' => [0, 2],
        'civil_protection' => [0, 1],
        'parish_councils' => [0, 1],
        'city_halls' => [0, 1],
        'post_offices' => [1, 3],
        'museums' => [0, 3],
        'theaters' => [0, 2],
        'sports' => [1, 5],
        'parks' => [1, 6],
        'bus_stops' => [5, 15],
        'train_stations' => [0, 2],
        'subway_stations' => [0, 3],
        'parking' => [2, 8],
        'default' => [1, 5]
    ];
    
    // Get the count range for this POI type
    $range = isset($countRanges[$type]) ? $countRanges[$type] : $countRanges['default'];
    
    // Generate a random count within the range
    $count = rand($range[0], $range[1]);
    
    // Define name prefixes for different POI types
    $namePrefixes = [
        'hospitals' => ['Hospital ', 'Centro Hospitalar ', 'Hospital Privado '],
        'health_centers' => ['Centro de Saúde ', 'Clínica ', 'Consultório Médico '],
        'pharmacies' => ['Farmácia ', 'Farmácia Popular ', 'Farmácia Central '],
        'dentists' => ['Clínica Dentária ', 'Dentista Dr. ', 'Consultório Odontológico '],
        'schools' => ['Escola Básica ', 'Escola Secundária ', 'Colégio ', 'Agrupamento Escolar '],
        'universities' => ['Universidade ', 'Instituto Superior ', 'Faculdade de '],
        'kindergartens' => ['Jardim de Infância ', 'Creche ', 'Infantário '],
        'libraries' => ['Biblioteca Municipal ', 'Biblioteca ', 'Centro de Documentação '],
        'supermarkets' => ['Supermercado ', 'Mini-Mercado ', 'Mercearia '],
        'malls' => ['Centro Comercial ', 'Shopping ', 'Galeria Comercial '],
        'restaurants' => ['Restaurante ', 'Café ', 'Pastelaria ', 'Snack-Bar '],
        'atms' => ['Multibanco ', 'Caixa Automática ', 'Terminal de Pagamento '],
        'police_stations' => ['Esquadra da Polícia ', 'PSP ', 'GNR '],
        'fire_stations' => ['Bombeiros ', 'Quartel de Bombeiros '],
        'civil_protection' => ['Serviços Governamentais Públicos ', 'Repartição de Finanças ', 'Segurança Social ', 'Instituto de Emprego e Formação Profissional ', 'Conservatória do Registo Civil '],
        'parish_councils' => ['Junta de Freguesia ', 'Freguesia de '],
        'city_halls' => ['Câmara Municipal de ', 'Município de ', 'Paços do Concelho de '],
        'post_offices' => ['CTT ', 'Correios ', 'Posto de Correios '],
        'museums' => ['Museu ', 'Casa-Museu ', 'Galeria '],
        'theaters' => ['Teatro ', 'Auditório ', 'Sala de Espetáculos '],
        'sports' => ['Ginásio ', 'Centro Desportivo ', 'Campo de Jogos ', 'Piscina Municipal '],
        'parks' => ['Parque ', 'Jardim ', 'Espaço Verde ', 'Parque Infantil '],
        'bus_stops' => ['Paragem de Autocarro ', 'Terminal Rodoviário '],
        'train_stations' => ['Estação Ferroviária ', 'Apeadeiro '],
        'subway_stations' => ['Estação de Metro ', 'Metro '],
        'parking' => ['Parque de Estacionamento ', 'Estacionamento '],
        'default' => ['Local ', 'Ponto de Interesse ']
    ];
    
    // Get the name prefixes for this POI type
    $prefixes = isset($namePrefixes[$type]) ? $namePrefixes[$type] : $namePrefixes['default'];
    
    // Generate POIs
    $pois = [];
    for ($i = 0; $i < $count; $i++) {
        // Generate a random position within the radius
        $angle = rand(0, 360) * M_PI / 180;
        $distance = sqrt(rand(0, 100) / 100) * $radiusDeg; // Square root for more realistic distribution
        
        $poiLat = $lat + $distance * cos($angle);
        $poiLng = $lng + $distance * sin($angle);
        
        // Generate a name
        $prefix = $prefixes[array_rand($prefixes)];
        $suffix = getRandomPortugueseName();
        
        $pois[] = [
            'latitude' => $poiLat,
            'longitude' => $poiLng,
            'name' => $prefix . $suffix,
            'type' => $type,
            'geometry_type' => rand(0, 1) ? 'point' : 'polygon'
        ];
    }
    
    return $pois;
}

/**
 * Generate mock statistics data for an area
 * 
 * @param float $lat Latitude of the center point
 * @param float $lng Longitude of the center point
 * @param float $radius Radius in meters
 * @return array Statistics data
 */
function generateMockStatistics($lat, $lng, $radius) {
    // Calculate area in square kilometers
    $areaKm2 = M_PI * pow($radius / 1000, 2);
    
    // Generate population based on area (assuming urban density)
    $populationDensity = rand(1000, 5000); // people per km²
    $populationEstimate = round($areaKm2 * $populationDensity);
    
    // Base statistics
    $stats = [
        'area_km2' => round($areaKm2, 2),
        'population_estimate' => $populationEstimate,
        'parish' => getRandomPortugueseParish(),
        'municipality' => getRandomPortugueseMunicipality(),
        'is_mock' => true
    ];
    
    // Add counts for different POI types
    $poiTypes = [
        'hospitals', 'health_centers', 'pharmacies', 'dentists',
        'schools', 'universities', 'kindergartens', 'libraries',
        'supermarkets', 'malls', 'restaurants', 'atms',
        'police_stations', 'fire_stations', 'civil_protection',
        'parish_councils', 'city_halls', 'post_offices',
        'museums', 'theaters', 'sports', 'parks',
        'bus_stops', 'train_stations', 'subway_stations', 'parking'
    ];
    
    foreach ($poiTypes as $type) {
        $mockPois = generateMockPOIs($type, $lat, $lng, $radius);
        $stats[$type] = count($mockPois);
    }
    
    return $stats;
}

/**
 * Get a random Portuguese name
 * 
 * @return string A random Portuguese name
 */
function getRandomPortugueseName() {
    $names = [
        'Silva', 'Santos', 'Ferreira', 'Pereira', 'Oliveira',
        'Costa', 'Rodrigues', 'Martins', 'Jesus', 'Sousa',
        'Fernandes', 'Gonçalves', 'Gomes', 'Lopes', 'Marques',
        'Alves', 'Almeida', 'Ribeiro', 'Pinto', 'Carvalho',
        'Teixeira', 'Moreira', 'Correia', 'Mendes', 'Nunes',
        'Soares', 'Vieira', 'Monteiro', 'Cardoso', 'Rocha',
        'Raposo', 'Neves', 'Coelho', 'Cruz', 'Cunha',
        'Pires', 'Ramos', 'Reis', 'Simões', 'Antunes',
        'Matos', 'Fonseca', 'Machado', 'Araújo', 'Barbosa',
        'Tavares', 'Lourenço', 'Castro', 'Figueiredo', 'Azevedo'
    ];
    
    return $names[array_rand($names)];
}

/**
 * Get a random Portuguese parish name
 * 
 * @return string A random Portuguese parish name
 */
function getRandomPortugueseParish() {
    $parishes = [
        'São João', 'Santa Maria', 'Santo António', 'São Pedro',
        'São Miguel', 'Nossa Senhora da Conceição', 'São Martinho',
        'Santa Eulália', 'São Salvador', 'São Vicente',
        'Santa Catarina', 'São Sebastião', 'Santa Isabel',
        'São Nicolau', 'São José', 'Santa Justa', 'São Tiago'
    ];
    
    return $parishes[array_rand($parishes)];
}

/**
 * Get a random Portuguese municipality name
 * 
 * @return string A random Portuguese municipality name
 */
function getRandomPortugueseMunicipality() {
    $municipalities = [
        'Lisboa', 'Porto', 'Coimbra', 'Braga', 'Aveiro',
        'Faro', 'Évora', 'Setúbal', 'Viseu', 'Guarda',
        'Bragança', 'Vila Real', 'Viana do Castelo', 'Leiria',
        'Santarém', 'Castelo Branco', 'Portalegre', 'Beja'
    ];
    
    return $municipalities[array_rand($municipalities)];
}
?> 