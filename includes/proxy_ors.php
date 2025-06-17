<?php
/**
 * Proxy da API OpenRouteService
 * Lida com pedidos para a API OpenRouteService para evitar problemas de CORS
 */

// Impede qualquer saída antes da resposta JSON
error_reporting(0);
ini_set('display_errors', 0);

// Define os cabeçalhos para a resposta JSON
header('Content-Type: application/json');

// Inclui a configuração da API para obter a chave da API
require_once '../config/api_config.php';

// Verifica se o método do pedido é POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode([
        'success' => false,
        'message' => 'Apenas o método POST é permitido'
    ]);
    exit;
}

// Obtém o endpoint do OpenRouteService do pedido
$endpoint = isset($_POST['endpoint']) ? $_POST['endpoint'] : null;

// Obtém os dados do pedido
$requestData = isset($_POST['data']) ? $_POST['data'] : null;

// Verifica se todos os parâmetros obrigatórios foram fornecidos
if (empty($endpoint) || empty($requestData)) {
    echo json_encode([
        'success' => false,
        'message' => 'Parâmetros obrigatórios em falta: endpoint, data'
    ]);
    exit;
}

// Tenta descodificar os dados do pedido se for uma string
if (is_string($requestData)) {
    $requestData = json_decode($requestData, true);
    
    // Verifica se o JSON era inválido
    if (json_last_error() !== JSON_ERROR_NONE) {
        echo json_encode([
            'success' => false,
            'message' => 'JSON inválido nos dados do pedido: ' . json_last_error_msg()
        ]);
        exit;
    }
}

// Constrói o URL completo para o pedido da API
$url = ORS_API_URL . $endpoint;

// Informações de depuração para ajudar a solucionar problemas
$debug = [
    'requested_url' => $url,
    'request_data' => $requestData
];

// Inicializa a sessão cURL
$ch = curl_init($url);

// Define as opções cURL
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($requestData));
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Accept: application/json, application/geo+json',
    'Content-Type: application/json',
    'Authorization: ' . ORS_API_KEY
]);

// Executa o pedido
$response = curl_exec($ch);
$statusCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$contentType = curl_getinfo($ch, CURLINFO_CONTENT_TYPE);

// Obtém mais informações sobre o pedido para depuração
$debug['status_code'] = $statusCode;
$debug['content_type'] = $contentType;
$debug['curl_error'] = curl_error($ch);
$debug['curl_errno'] = curl_errno($ch);

// Fecha a sessão cURL
curl_close($ch);

// Verifica se obtivemos uma resposta válida
if ($response === false) {
    echo json_encode([
        'success' => false,
        'message' => 'Falha ao conectar ao servidor da API',
        'debug' => $debug
    ]);
    exit;
}

// Valida se o formato da resposta é JSON válido
$decodedResponse = json_decode($response, true);
if (json_last_error() !== JSON_ERROR_NONE) {
    echo json_encode([
        'success' => false,
        'message' => 'JSON inválido na resposta da API: ' . json_last_error_msg(),
        'debug' => $debug,
        'raw_response' => substr($response, 0, 1000) // Inclui os primeiros 1000 caracteres da resposta raw
    ]);
    exit;
}

// Se ocorrer um erro, fornece informações sobre ele
if ($statusCode >= 400) {
    echo json_encode([
        'success' => false,
        'status' => $statusCode,
        'message' => isset($decodedResponse['error']) ? $decodedResponse['error'] : 'O pedido à API falhou',
        'details' => $decodedResponse,
        'debug' => $debug
    ]);
    exit;
}

// Garante que a resposta contém uma estrutura GeoJSON válida
if (!isset($decodedResponse['type']) || !isset($decodedResponse['features'])) {
    echo json_encode([
        'success' => false,
        'message' => 'A resposta não é um objeto GeoJSON válido',
        'debug' => $debug,
        'response_data' => $decodedResponse
    ]);
    exit;
}

// Garante que o array de features existe e não está vazio
if (!is_array($decodedResponse['features']) || count($decodedResponse['features']) === 0) {
    echo json_encode([
        'success' => false,
        'message' => 'O array de features GeoJSON está vazio ou é inválido',
        'debug' => $debug,
        'response_data' => $decodedResponse
    ]);
    exit;
}

// Verifica se as features contêm geometrias válidas
if (!isset($decodedResponse['features'][0]['geometry']) || 
    !isset($decodedResponse['features'][0]['geometry']['coordinates'])) {
    echo json_encode([
        'success' => false,
        'message' => 'Geometria inválida na resposta GeoJSON',
        'debug' => $debug,
        'response_data' => $decodedResponse
    ]);
    exit;
}

// Retorna a resposta da API tal como está
echo $response;
?>