#!/bin/bash
echo "Setting up weekly scheduled task for Geofabrik data update..."

# Path to PHP executable - update this to your PHP installation path
PHP_PATH="/usr/bin/php"
# Path to the update script (corrected path to common directory)
SCRIPT_PATH="$(dirname "$0")/../common/update_geofabrik_data.php"

# Create crontab entry to run weekly on Sunday at 3:00 AM
CRON_JOB="0 3 * * 0 $PHP_PATH $SCRIPT_PATH"

# Check if the cron job already exists
EXISTING_CRON=$(crontab -l 2>/dev/null | grep -F "$SCRIPT_PATH")

if [ -z "$EXISTING_CRON" ]; then
    # Add the new cron job
    (crontab -l 2>/dev/null; echo "$CRON_JOB") | crontab -
    echo "Task has been scheduled to run every Sunday at 3:00 AM."
    echo "You can modify this schedule using crontab -e command."
else
    echo "Task already exists in crontab."
fi