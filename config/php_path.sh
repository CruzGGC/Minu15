#!/bin/bash
# Ficheiro de configuração para o caminho do PHP no ambiente WSL
# Isto permite-nos usar um caminho PHP consistente em todos os scripts

# Função para encontrar o executável PHP
find_php_path() {
    # Tentar localizações comuns do PHP no WSL
    local php_locations=(
        "/usr/bin/php"
        "/usr/local/bin/php"
        "/usr/lib/cgi-bin/php"
        "/opt/php/bin/php"
    )
    
    # Verificar PHP do Windows se estivermos no WSL
    if grep -q Microsoft /proc/version; then
        # Adicionar potenciais caminhos PHP do Windows
        if [ -n "$WINDIR" ]; then
            php_locations+=("$WINDIR/php/php.exe")
        fi
        
        # Tentar detetar PHP do Windows Program Files
        if [ -d "/mnt/c/Program Files/PHP" ]; then
            for dir in /mnt/c/Program\ Files/PHP/*; do
                if [ -f "$dir/php.exe" ]; then
                    php_locations+=("$dir/php.exe")
                fi
            done
        fi
        
        # Tentar localização do PHP do XAMPP
        if [ -f "/mnt/c/xampp/php/php.exe" ]; then
            php_locations+=("/mnt/c/xampp/php/php.exe")
        fi
    fi
    
    # Encontrar o primeiro PHP funcional
    for php_path in "${php_locations[@]}"; do
        if [ -f "$php_path" ]; then
            echo "$php_path"
            return 0
        fi
    done
    
    # Se nenhum PHP for encontrado, retornar o padrão e avisar o utilizador
    echo "/usr/bin/php"
    return 1
}

# Obter o caminho do PHP
PHP_PATH=$(find_php_path)

# Verificar se o PHP existe no caminho detetado
if [ ! -f "$PHP_PATH" ]; then
    echo "Aviso: PHP não encontrado em $PHP_PATH"
    echo "Por favor, instale o PHP ou atualize este script com o caminho correto"
    echo "Para WSL, poderá precisar de: sudo apt update && sudo apt install -y php php-cli"
    
    # Se chamado com parâmetros, sair com erro
    if [ -n "$1" ]; then
        exit 1
    fi
fi

# Se um parâmetro for passado, executar o script PHP
if [ -n "$1" ]; then
    "$PHP_PATH" "$@"
else
    echo "$PHP_PATH"
fi