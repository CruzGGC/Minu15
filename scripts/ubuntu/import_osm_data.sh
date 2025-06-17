#!/bin/bash
echo "A importar dados OpenStreetMap com osm2pgsql (versão compatível com WSL)..."

# Caminho para o executável osm2pgsql (assumindo que está instalado via apt)
OSM2PGSQL_PATH="/usr/bin/osm2pgsql"

# Verificar se o osm2pgsql está instalado
if [ ! -f "$OSM2PGSQL_PATH" ]; then
    echo "osm2pgsql não encontrado. A instalar..."
    sudo apt-get update && sudo apt-get install -y osm2pgsql
fi

# Caminho para o ficheiro de dados OSM (com tratamento de caminho WSL)
OSM_FILE="$(dirname "$0")"/../../data/geofabrik/portugal-latest.osm.pbf"

# Caminho para o ficheiro de estilo (no diretório comum)
STYLE_FILE="$(dirname "$0")"/../common/default.style"

# Detalhes de conexão da base de dados
DB_NAME="minu15"
DB_USER="postgres"
DB_HOST="localhost"
DB_PORT="5432"

# Verificar se o PostgreSQL está a ser executado no WSL
pg_isready -h $DB_HOST -p $DB_PORT > /dev/null 2>&1
if [ $? -ne 0 ]; then
    echo "PostgreSQL não está a ser executado. A tentar iniciar o serviço PostgreSQL..."
    sudo service postgresql start
    sleep 3
    
    # Verificar novamente
    pg_isready -h $DB_HOST -p $DB_PORT > /dev/null 2>&1
    if [ $? -ne 0 ]; then
        echo "Não foi possível iniciar o PostgreSQL. Por favor, inicie-o manualmente."
        exit 1
    fi
fi

# Verificar se o diretório de dados existe, caso contrário, criá-lo
if [ ! -d "$(dirname "$0")"/../../data/geofabrik" ]; then
    mkdir -p "$(dirname "$0")"/../../data/geofabrik"
fi

# Verificar se o ficheiro OSM existe
if [ ! -f "$OSM_FILE" ]; then
    echo "Ficheiro de dados OSM não encontrado em: $OSM_FILE"
    echo "A descarregar dados OSM de Portugal do Geofabrik..."
    
    # Executar o script de descarregamento PHP do diretório comum
    "$(dirname "$0")"/../../config/php_path.sh" "$(dirname "$0")"/../common/update_geofabrik_data.php" download_only
    
    if [ ! -f "$OSM_FILE" ]; then
        echo "Falha ao descarregar dados OSM."
        echo "Por favor, descarregue manualmente de https://download.geofabrik.de/europe/portugal-latest.osm.pbf"
        echo "e coloque-o na pasta data/geofabrik."
        exit 1
    fi
fi

echo ""
echo "A usar osm2pgsql para importar dados OpenStreetMap..."
echo ""
echo "Base de Dados: $DB_NAME"
echo "Ficheiro OSM: $OSM_FILE"
echo "Ficheiro de Estilo: $STYLE_FILE"
echo ""
echo "Este processo levará algum tempo, por favor, seja paciente..."
echo ""

# Executar osm2pgsql com os parâmetros apropriados
# Usando --slim e --drop para otimizar para a memória potencialmente limitada do WSL
"$OSM2PGSQL_PATH" -c -d "$DB_NAME" -U "$DB_USER" -H "$DB_HOST" -P "$DB_PORT" -W -S "$STYLE_FILE" --slim --drop "$OSM_FILE"

echo ""
echo "Processo de importação concluído."
echo ""