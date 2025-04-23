#!/bin/bash
echo "Setting up weekly scheduled task for Geofabrik data update (WSL-compatible version)..."

# Path to the update script
SCRIPT_PATH="$(dirname "$0")/../common/update_geofabrik_data.php"
ABS_SCRIPT_PATH=$(realpath "$SCRIPT_PATH")

# Path to PHP executable - using the configured PHP path
PHP_EXEC="$(dirname "$0")/../../config/php_path.sh"
ABS_PHP_EXEC=$(realpath "$PHP_EXEC")

# Check if we're running in WSL
if grep -q Microsoft /proc/version; then
    echo "WSL environment detected."
    
    # Create service directory for the user if it doesn't exist
    SYSTEMD_DIR="$HOME/.config/systemd/user"
    mkdir -p "$SYSTEMD_DIR"
    
    # Create systemd timer and service files
    SERVICE_FILE="$SYSTEMD_DIR/geofabrik-update.service"
    TIMER_FILE="$SYSTEMD_DIR/geofabrik-update.timer"
    
    # Write service file
    cat > "$SERVICE_FILE" << EOF
[Unit]
Description=15-Minute City Geofabrik Update Service
After=network.target

[Service]
Type=oneshot
ExecStart=$ABS_PHP_EXEC $ABS_SCRIPT_PATH
WorkingDirectory=$(dirname "$ABS_SCRIPT_PATH")

[Install]
WantedBy=default.target
EOF

    # Write timer file (runs weekly on Sunday at 3:00 AM)
    cat > "$TIMER_FILE" << EOF
[Unit]
Description=Weekly 15-Minute City Geofabrik Update Timer

[Timer]
OnCalendar=Sun 03:00:00
Persistent=true

[Install]
WantedBy=timers.target
EOF

    # Enable and start the timer
    systemctl --user daemon-reload
    systemctl --user enable geofabrik-update.timer
    systemctl --user start geofabrik-update.timer
    
    echo "Timer has been set using systemd user timer."
    echo "The update will run every Sunday at 3:00 AM."
    echo "You can check the status with: systemctl --user status geofabrik-update.timer"
    
else
    # Path to PHP executable - using the configured PHP path
    PHP_PATH=$(bash "$PHP_EXEC")
    
    # Create crontab entry to run weekly on Sunday at 3:00 AM
    CRON_JOB="0 3 * * 0 $PHP_PATH $SCRIPT_PATH"

    # Check if the cron job already exists
    EXISTING_CRON=$(crontab -l 2>/dev/null | grep -F "$SCRIPT_PATH")

    if [ -z "$EXISTING_CRON" ]; then
        # Add the new cron job
        (crontab -l 2>/dev/null; echo "$CRON_JOB") | crontab -
        echo "Task has been scheduled to run every Sunday at 3:00 AM using crontab."
        echo "You can modify this schedule using crontab -e command."
    else
        echo "Task already exists in crontab."
    fi
fi