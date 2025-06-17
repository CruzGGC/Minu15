#!/bin/bash
echo "A configurar tarefa agendada semanalmente para atualização de dados Geofabrik (versão compatível com WSL)..."

# Caminho para o script de atualização
SCRIPT_PATH="$(dirname "$0")"/../common/update_geofabrik_data.php"
ABS_SCRIPT_PATH=$(realpath "$SCRIPT_PATH")

# Caminho para o executável PHP - usando o caminho PHP configurado
PHP_EXEC="$(dirname "$0")"/../../config/php_path.sh"
ABS_PHP_EXEC=$(realpath "$PHP_EXEC")

# Verificar se estamos a correr no WSL
if grep -q Microsoft /proc/version; then
    echo "Ambiente WSL detetado."
    
    # Criar diretório de serviço para o utilizador se não existir
    SYSTEMD_DIR="$HOME/.config/systemd/user"
    mkdir -p "$SYSTEMD_DIR"
    
    # Criar ficheiros de timer e serviço do systemd
    SERVICE_FILE="$SYSTEMD_DIR/geofabrik-update.service"
    TIMER_FILE="$SYSTEMD_DIR/geofabrik-update.timer"
    
    # Escrever ficheiro de serviço
    cat > "$SERVICE_FILE" << EOF
[Unit]
Description=Serviço de Atualização Geofabrik da Cidade de 15 Minutos
After=network.target

[Service]
Type=oneshot
ExecStart=$ABS_PHP_EXEC $ABS_SCRIPT_PATH
WorkingDirectory=$(dirname "$ABS_SCRIPT_PATH")

[Install]
WantedBy=default.target
EOF

    # Escrever ficheiro de timer (executa semanalmente no Domingo às 3:00 AM)
    cat > "$TIMER_FILE" << EOF
[Unit]
Description=Timer Semanal de Atualização Geofabrik da Cidade de 15 Minutos

[Timer]
OnCalendar=Sun 03:00:00
Persistent=true

[Install]
WantedBy=timers.target
EOF

    # Ativar e iniciar o timer
    systemctl --user daemon-reload
    systemctl --user enable geofabrik-update.timer
    systemctl --user start geofabrik-update.timer
    
    echo "O timer foi definido usando o timer de utilizador do systemd."
    echo "A atualização será executada todos os Domingos às 3:00 da manhã."
    echo "Pode verificar o estado com: systemctl --user status geofabrik-update.timer"
    
else
    # Caminho para o executável PHP - usando o caminho PHP configurado
    PHP_PATH=$(bash "$PHP_EXEC")
    
    # Criar entrada crontab para executar semanalmente no Domingo às 3:00 AM
    CRON_JOB="0 3 * * 0 $PHP_PATH $SCRIPT_PATH"

    # Verificar se a tarefa cron já existe
    EXISTING_CRON=$(crontab -l 2>/dev/null | grep -F "$SCRIPT_PATH")

    if [ -z "$EXISTING_CRON" ]; then
        # Adicionar a nova tarefa cron
        (crontab -l 2>/dev/null; echo "$CRON_JOB") | crontab -
        echo "A tarefa foi agendada para ser executada todos os Domingos às 3:00 da manhã usando crontab."
        echo "Pode modificar este agendamento usando o comando crontab -e."
    else
        echo "A tarefa já existe no crontab."
    fi
fi