<?php
/**
 * Fornecedor de Dados Simulados
 * 
 * Este ficheiro fornece dados de exemplo para testes quando a base de dados não está disponível.
 * Contém funções para gerar dados simulados realistas para POIs e estatísticas.
 */

/**
 * Gera dados de POI simulados para um dado tipo e localização
 * 
 * @param string $type O tipo de POI (ex: 'hospitals', 'schools')
 * @param float $lat Latitude do ponto central
 * @param float $lng Longitude do ponto central
 * @param float $radius Raio em metros
 * @return array Array de objetos POI
 */
function generateMockPOIs($type, $lat, $lng, $radius) {
    // Converte o raio de metros para graus (aproximado)
    $radiusDeg = $radius / 111000; // ~111km por grau no equador
    
    // Define os intervalos de contagem para diferentes tipos de POI
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
    
    // Obtém o intervalo de contagem para este tipo de POI
    $range = isset($countRanges[$type]) ? $countRanges[$type] : $countRanges['default'];
    
    // Gera uma contagem aleatória dentro do intervalo
    $count = rand($range[0], $range[1]);
    
    // Define prefixos de nome para diferentes tipos de POI
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
    
    // Obtém os prefixos de nome para este tipo de POI
    $prefixes = isset($namePrefixes[$type]) ? $namePrefixes[$type] : $namePrefixes['default'];
    
    // Gera POIs
    $pois = [];
    for ($i = 0; $i < $count; $i++) {
        // Gera uma posição aleatória dentro do raio
        $angle = rand(0, 360) * M_PI / 180;
        $distance = sqrt(rand(0, 100) / 100) * $radiusDeg; // Raiz quadrada para distribuição mais realista
        
        $poiLat = $lat + $distance * cos($angle);
        $poiLng = $lng + $distance * sin($angle);
        
        // Gera um nome
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
 * Gera dados de estatísticas simulados para uma área
 * 
 * @param float $lat Latitude do ponto central
 * @param float $lng Longitude do ponto central
 * @param float $radius Raio em metros
 * @return array Dados de estatísticas
 */
function generateMockStatistics($lat, $lng, $radius) {
    // Calcula a área em quilómetros quadrados
    $areaKm2 = M_PI * pow($radius / 1000, 2);
    
    // Gera população com base na área (assumindo densidade urbana)
    $populationDensity = rand(1000, 5000); // pessoas por km²
    $populationEstimate = round($areaKm2 * $populationDensity);
    
    // Estatísticas base
    $stats = [
        'area_km2' => round($areaKm2, 2),
        'population_estimate' => $populationEstimate,
        'parish' => getRandomPortugueseParish(),
        'municipality' => getRandomPortugueseMunicipality(),
        'is_mock' => true
    ];
    
    // Adiciona contagens para diferentes tipos de POI
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
 * Obtém um nome português aleatório
 * 
 * @return string Um nome português aleatório
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
 * Obtém um nome de freguesia portuguesa aleatório
 * 
 * @return string Um nome de freguesia portuguesa aleatório
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
 * Obtém um nome de município português aleatório
 * 
 * @return string Um nome de município português aleatório
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