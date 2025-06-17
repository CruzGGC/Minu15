<?php
/**
 * Ficheiro de Configuração da Base de Dados
 * Contém parâmetros de conexão para a base de dados PostgreSQL com dados OSM
 */

// Parâmetros de conexão da base de dados
$db_host = 'localhost';
$db_port = '5432';
$db_name = 'minu15';
$db_user = 'postgres';
$db_password = '1234';

// Criar diretório de logs se não existir
$logs_dir = __DIR__ . '/../logs';
if (!file_exists($logs_dir)) {
    mkdir($logs_dir, 0777, true);
}

/**
 * Obter uma conexão à base de dados com tratamento de erros
 * @return resource Recurso de conexão PostgreSQL
 * @throws Exception se a conexão falhar
 */
function getDbConnection() {
    global $db_host, $db_port, $db_name, $db_user, $db_password;
    
    // Verificar se a extensão PostgreSQL está carregada
    if (!extension_loaded('pgsql')) {
        $error_message = "PostgreSQL extension is not loaded in PHP";
        error_log($error_message, 3, __DIR__ . "/../logs/db_errors.log");
        throw new Exception($error_message);
    }
    
    // String de conexão
    $conn_string = "host={$db_host} port={$db_port} dbname={$db_name} user={$db_user} password={$db_password}";
    
    // Tentar estabelecer a conexão
    $conn = @pg_connect($conn_string);
    
    // Verificar se a conexão foi bem-sucedida
    if (!$conn) {
        // Registar o erro num ficheiro
        $error_message = "Failed to connect to PostgreSQL database: " . pg_last_error();
        error_log($error_message, 3, __DIR__ . "/../logs/db_errors.log");
        
        // Lançar exceção para tratamento no código chamador
        throw new Exception("Database connection failed: {$error_message}");
    }
    
    // Definir a codificação do cliente para UTF8
    pg_set_client_encoding($conn, "UTF8");
    
    return $conn;
}

/**
 * Executar uma consulta com tratamento de erros
 * @param resource $conn Conexão PostgreSQL
 * @param string $query Consulta SQL a executar
 * @return resource Resultado da consulta
 * @throws Exception se a consulta falhar
 */
function executeQuery($conn, $query) {
    // Verificar se a conexão é válida
    if (!$conn) {
        $error_message = "Invalid database connection";
        error_log($error_message, 3, __DIR__ . "/../logs/db_errors.log");
        throw new Exception($error_message);
    }
    
    // Executar a consulta
    $result = @pg_query($conn, $query);
    
    if (!$result) {
        $error_message = "Query execution failed: " . pg_last_error($conn);
        error_log($error_message, 3, __DIR__ . "/../logs/db_errors.log");
        throw new Exception("Database query failed: " . pg_last_error($conn));
    }
    
    return $result;
}

/**
 * Criar uma conexão de fallback para a base de dados para testes
 * Isto é utilizado quando a base de dados real não está disponível
 * @return array Conexão simulada com funcionalidade limitada
 */
function createFallbackConnection() {
    // Registar a utilização da conexão de fallback
    error_log("Using fallback database connection", 3, __DIR__ . "/../logs/db_errors.log");
    
    // Devolver um objeto de conexão simulado
    return [
        'is_fallback' => true,
        'connected_at' => date('Y-m-d H:i:s')
    ];
}
?>