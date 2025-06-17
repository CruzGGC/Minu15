<?php
/**
 * Script de Inicialização da Base de Dados
 * Configura a base de dados PostgreSQL para a aplicação 15-minute city
 */

// Incluir configuração da base de dados
require_once dirname(__DIR__, 2) . '/config/db_config.php';

// Função de log
function log_message($message) {
    echo date('Y-m-d H:i:s') . " - $message\n";
}

log_message("A iniciar a inicialização da base de dados...");

try {
    // Primeiro, conectar à base de dados postgres para criar a nossa base de dados GIS se não existir
    $conn_string = "host=" . DB_HOST . " port=" . DB_PORT . " dbname=postgres user=" . DB_USER . " password=" . DB_PASS;
    $conn = pg_connect($conn_string);
    
    if (!$conn) {
        throw new Exception("Falha ao conectar ao PostgreSQL: " . pg_last_error());
    }
    
    // Verificar se a nossa base de dados existe
    $query = "SELECT 1 FROM pg_database WHERE datname = '" . DB_NAME . "'";
    $result = pg_query($conn, $query);
    
    if (!$result) {
        throw new Exception("Falha ao consultar o PostgreSQL: " . pg_last_error($conn));
    }
    
    if (pg_num_rows($result) == 0) {
        // A base de dados não existe, criá-la
        log_message("A criar a base de dados " . DB_NAME . "...");
        
        $create_db_query = "CREATE DATABASE " . DB_NAME . " WITH OWNER = " . DB_USER;
        $result = pg_query($conn, $create_db_query);
        
        if (!$result) {
            throw new Exception("Falha ao criar a base de dados: " . pg_last_error($conn));
        }
        
        log_message("Base de dados criada com sucesso.");
    } else {
        log_message("A base de dados já existe.");
    }
    
    // Fechar conexão à base de dados postgres
    pg_close($conn);
    
    // Conectar à nossa base de dados
    $conn = getDbConnection();
    
    // Criar extensões PostgreSQL necessárias se não existirem
    log_message("A configurar extensões PostgreSQL...");
    
    // Primeiro verificar se o PostGIS está disponível
    $check_postgis_query = "SELECT 1 FROM pg_available_extensions WHERE name = 'postgis'";
    $check_result = pg_query($conn, $check_postgis_query);
    
    if (!$check_result || pg_num_rows($check_result) == 0) {
        log_message("AVISO: A extensão PostGIS não está disponível nesta instalação PostgreSQL.");
        log_message("Por favor, instale o PostGIS usando um dos seguintes comandos:");
        log_message("Para Ubuntu/Debian: sudo apt-get install postgresql-17-postgis-3");
        log_message("Para CentOS/RHEL: sudo dnf install postgis30_16");
        log_message("Para macOS (Homebrew): brew install postgis");
        log_message("Após a instalação, reinicie o PostgreSQL e execute este script novamente.");
    } else {
        // Extensão PostGIS (para funções espaciais)
        $result = pg_query($conn, "CREATE EXTENSION IF NOT EXISTS postgis");
        if (!$result) {
            throw new Exception("Falha ao criar a extensão PostGIS: " . pg_last_error($conn));
        }
        log_message("Extensão PostGIS configurada com sucesso.");
    }
    
    // Extensão hstore (para pares chave-valor em dados OSM)
    $result = pg_query($conn, "CREATE EXTENSION IF NOT EXISTS hstore");
    if (!$result) {
        throw new Exception("Falha ao criar a extensão hstore: " . pg_last_error($conn));
    }
    
    log_message("Configuração das extensões PostgreSQL concluída.");
    
    // Verificar se as tabelas OSM existem (criadas por osm2pgsql)
    $check_table_query = "SELECT EXISTS (
        SELECT FROM information_schema.tables 
        WHERE table_schema = 'public' 
        AND table_name = 'planet_osm_point'
    )";
    $result = pg_query($conn, $check_table_query);
    $row = pg_fetch_row($result);
    
    if ($row[0] == 'f') {
        log_message("As tabelas OSM não existem. Por favor, use o osm2pgsql para importar dados OpenStreetMap.");
        log_message("Comando de exemplo: C:/Programs/Programs/osm2pgsql-bin/osm2pgsql.exe -c -d " . DB_NAME . " -U " . DB_USER . " -W -H " . DB_HOST . " -P " . DB_PORT . " -S default.style portugal-latest.osm.pbf");
    } else {
        log_message("As tabelas OSM já existem.");
        
        // Contar POIs na base de dados
        $count_query = "
            SELECT
                COUNT(*) as hospitals FROM planet_osm_point WHERE amenity = 'hospital',
                COUNT(*) as schools FROM planet_osm_point WHERE amenity IN ('school', 'university', 'college', 'kindergarten'),
                COUNT(*) as health FROM planet_osm_point WHERE amenity IN ('clinic', 'doctors', 'dentist', 'pharmacy'),
                COUNT(*) as culture FROM planet_osm_point WHERE amenity IN ('theatre', 'cinema', 'library', 'arts_centre', 'community_centre', 'museum'),
                COUNT(*) as shops FROM planet_osm_point WHERE shop IS NOT NULL,
                COUNT(*) as parks FROM planet_osm_point WHERE leisure IN ('park', 'garden', 'playground')
        ";
        
        $result = pg_query($conn, $count_query);
        if ($result) {
            $stats = pg_fetch_assoc($result);
            log_message("Estatísticas da base de dados:");
            foreach ($stats as $key => $value) {
                log_message("  - $key: $value");
            }
        }
    }
    
    // Opcional: Criar índices para melhor desempenho
    log_message("A criar índices para melhor desempenho...");
    
    $index_queries = [
        "CREATE INDEX IF NOT EXISTS idx_planet_osm_point_amenity ON planet_osm_point USING btree (amenity)",
        "CREATE INDEX IF NOT EXISTS idx_planet_osm_point_shop ON planet_osm_point USING btree (shop)",
        "CREATE INDEX IF NOT EXISTS idx_planet_osm_point_leisure ON planet_osm_point USING btree (leisure)",
        "CREATE INDEX IF NOT EXISTS idx_planet_osm_polygon_building ON planet_osm_polygon USING btree (building)",
        "CREATE INDEX IF NOT EXISTS idx_planet_osm_polygon_admin_level ON planet_osm_polygon USING btree (admin_level)"
    ];
    
    foreach ($index_queries as $query) {
        $result = pg_query($conn, $query);
        if (!$result) {
            log_message("Aviso: Falha ao criar índice: " . pg_last_error($conn));
        }
    }
    
    log_message("Índices criados com sucesso.");
    
    log_message("Inicialização da base de dados concluída com sucesso.");
    
} catch (Exception $e) {
    log_message("Erro: " . $e->getMessage());
    exit(1);
} finally {
    if (isset($conn) && $conn) {
        pg_close($conn);
    }
}
?>