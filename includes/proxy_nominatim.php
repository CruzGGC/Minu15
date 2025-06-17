<?php
/**
 * Proxy da API Nominatim
 * Lida com pedidos para a API de geocodificação Nominatim para evitar problemas de CORS
 * Usado para a funcionalidade de autocompletar
 */

// Impede qualquer saída antes da resposta JSON
error_reporting(0);
ini_set('display_errors', 0);

// Define os cabeçalhos para a resposta JSON
header('Content-Type: application/json');

// Adiciona um atraso para evitar sobrecarregar a API Nominatim
usleep(300000); // Atraso de 300ms

// Obtém o termo de pesquisa do pedido
$searchTerm = isset($_GET['term']) ? $_GET['term'] : null;

// Verifica se o termo de pesquisa foi fornecido
if (empty($searchTerm)) {
    echo json_encode([
        'success' => false,
        'message' => 'Parâmetro obrigatório em falta: term'
    ]);
    exit;
}

// Garante que o termo de pesquisa se foca em Portugal e está devidamente codificado
$encodedSearchTerm = urlencode($searchTerm . ', Portugal');

// Constrói o URL para o pedido da API Nominatim
$url = "https://nominatim.openstreetmap.org/search?format=json&q={$encodedSearchTerm}&limit=10&countrycodes=pt";

// Inicializa a sessão cURL
$ch = curl_init($url);

// Define as opções cURL
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Accept: application/json',
    'User-Agent: Minu15/1.0' // Identifica a tua aplicação de acordo com a política de utilização do Nominatim
]);

// Executa o pedido
$response = curl_exec($ch);
$statusCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

// Verifica erros cURL
if ($response === false) {
    echo json_encode([
        'success' => false,
        'message' => 'Falha ao conectar ao servidor da API Nominatim: ' . curl_error($ch)
    ]);
    curl_close($ch);
    exit;
}

// Fecha a sessão cURL
curl_close($ch);

// Verifica se obtivemos uma resposta válida
if ($statusCode != 200) {
    echo json_encode([
        'success' => false,
        'message' => 'O pedido à API falhou com o código de estado: ' . $statusCode
    ]);
    exit;
}

// Descodifica a resposta JSON
$results = json_decode($response, true);

// Verifica se a descodificação JSON falhou
if (json_last_error() !== JSON_ERROR_NONE) {
    echo json_encode([
        'success' => false,
        'message' => 'JSON inválido na resposta da API: ' . json_last_error_msg()
    ]);
    exit;
}

// Formata os resultados para o Autocomplete do jQuery UI
$formattedResults = [];
foreach ($results as $place) {
    // Ignora resultados sem nome de exibição ou coordenadas
    if (empty($place['display_name']) || !isset($place['lat']) || !isset($place['lon'])) {
        continue;
    }
    
    // Formata os resultados para o Autocomplete do jQuery UI
    $formattedResults[] = [
        'label' => $place['display_name'],
        'value' => $place['display_name'],
        'lat' => $place['lat'],
        'lon' => $place['lon'],
        'type' => isset($place['type']) ? $place['type'] : '',
        'osm_id' => isset($place['osm_id']) ? $place['osm_id'] : 0
    ];
}

// Retorna os resultados formatados
echo json_encode($formattedResults);
?> 