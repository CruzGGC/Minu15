#!/bin/bash
echo "A inicializar a Base de Dados do 15-Minute City Explorer (versão compatível com WSL)..."

# Caminho para o executável PHP - usando o caminho PHP configurado
PHP_PATH=$(bash "$(dirname "$0")"/../../config/php_path.sh")

# Verificar se o PostgreSQL está a ser executado no WSL
pg_isready -h localhost -p 5432 > /dev/null 2>&1
if [ $? -ne 0 ]; then
    echo "PostgreSQL não está a ser executado. A tentar iniciar o serviço PostgreSQL..."
    sudo service postgresql start
    sleep 3
    
    # Verificar novamente
    pg_isready -h localhost -p 5432 > /dev/null 2>&1
    if [ $? -ne 0 ]; then
        echo "Não foi possível iniciar o PostgreSQL. Por favor, inicie-o manualmente."
        exit 1
    fi
fi

# Executar o script de inicialização (do diretório comum)
bash "$(dirname "$0")"/../../config/php_path.sh" "$(dirname "$0")"/../common/init_database.php"

echo ""
echo "Inicialização da base de dados concluída."