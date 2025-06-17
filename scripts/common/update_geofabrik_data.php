<?php
/**
 * Script para descarregar e atualizar dados do Geofabrik de Portugal semanalmente
 * Este script pode ser agendado usando o Windows Task Scheduler
 * 
 * Uso:
 *   - php update_geofabrik_data.php           # Descarregar e importar dados
 *   - php update_geofabrik_data.php download_only  # Apenas descarregar sem importar
 */

// Configuração
$downloadDir = dirname(dirname(__DIR__)) . '/data/geofabrik/';
$logFile = dirname(dirname(__DIR__)) . '/logs/geofabrik_update.log';
$geofabrikUrl = 'https://download.geofabrik.de/europe/portugal-latest.osm.pbf';
$osm2pgsqlPath = 'C:/Programs/Programs/osm2pgsql-bin/osm2pgsql.exe'; // Caminho atualizado para osm2pgsql

// Verificar argumentos da linha de comandos
$downloadOnly = false;
if (isset($argv[1]) && $argv[1] === 'download_only') {
    $downloadOnly = true;
    echo "A executar no modo apenas descarregar\n";
}

// Incluir configuração da base de dados
require_once dirname(dirname(__DIR__)) . '/config/db_config.php';

// Criar diretórios se não existirem
if (!file_exists($downloadDir)) {
    mkdir($downloadDir, 0755, true);
}

if (!file_exists(dirname($logFile))) {
    mkdir(dirname($logFile), 0755, true);
}

// Função de log
function logMessage($message) {
    global $logFile;
    $timestamp = date('Y-m-d H:i:s');
    $logEntry = "[$timestamp] $message\n";
    file_put_contents($logFile, $logEntry, FILE_APPEND);
    echo $logEntry;
}

// Descarregar os dados mais recentes
logMessage("A iniciar o descarregamento dos dados do Geofabrik de Portugal");

$outputFile = $downloadDir . 'portugal-latest.osm.pbf';
$previousFile = $downloadDir . 'portugal-previous.osm.pbf';

// Fazer backup do ficheiro anterior se existir
if (file_exists($outputFile)) {
    if (file_exists($previousFile)) {
        unlink($previousFile);
    }
    rename($outputFile, $previousFile);
    logMessage("Dados anteriores salvaguardados");
}

// Descarregar novo ficheiro
$ch = curl_init($geofabrikUrl);
$fp = fopen($outputFile, 'w');

curl_setopt($ch, CURLOPT_FILE, $fp);
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);

logMessage("A descarregar ficheiro de: $geofabrikUrl");
$success = curl_exec($ch);

if ($success) {
    logMessage("Descarregamento concluído com sucesso");
    
    // Importar os dados para PostgreSQL usando osm2pgsql se não estiver no modo apenas descarregar
    if (!$downloadOnly) {
        logMessage("A iniciar a importação da base de dados usando osm2pgsql");
        
        // Criar comando osm2pgsql
        $styleFile = dirname(__FILE__) . "/default.style";
        $cmd = "\"$osm2pgsqlPath\" -c -d " . DB_NAME . " -U " . DB_USER . " -W -H " . DB_HOST . " -P " . DB_PORT . " -S \"$styleFile\" \"$outputFile\"";
        
        // Executar o comando
        $descriptorspec = array(
            0 => array("pipe", "r"),  // stdin
            1 => array("pipe", "w"),  // stdout
            2 => array("pipe", "w")   // stderr
        );
        
        $process = proc_open($cmd, $descriptorspec, $pipes);
        
        if (is_resource($process)) {
            // Enviar palavra-passe para stdin
            fwrite($pipes[0], DB_PASS . "\n");
            fclose($pipes[0]);
            
            // Obter saída
            $output = stream_get_contents($pipes[1]);
            $error = stream_get_contents($pipes[2]);
            fclose($pipes[1]);
            fclose($pipes[2]);
            
            // Fechar processo
            $returnCode = proc_close($process);
            
            if ($returnCode === 0) {
                logMessage("Importação da base de dados bem-sucedida");
                logMessage("Saída: " . $output);
            } else {
                logMessage("A importação da base de dados falhou com o código: $returnCode");
                logMessage("Saída: " . $output);
                logMessage("Erro: " . $error);
            }
        } else {
            logMessage("Falha ao executar o comando osm2pgsql");
        }
    } else {
        logMessage("A ignorar a importação da base de dados (modo apenas descarregar)");
    }
} else {
    logMessage("O descarregamento falhou: " . curl_error($ch));
}

curl_close($ch);
fclose($fp);

logMessage("Processo de atualização de dados do Geofabrik concluído");
?>