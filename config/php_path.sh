#!/bin/bash
# Configuration file for PHP path in WSL environment
# This allows us to use a consistent PHP path across all scripts

# Function to find PHP executable
find_php_path() {
    # Try common PHP locations in WSL
    local php_locations=(
        "/usr/bin/php"
        "/usr/local/bin/php"
        "/usr/lib/cgi-bin/php"
        "/opt/php/bin/php"
    )
    
    # Check Windows PHP if we're in WSL
    if grep -q Microsoft /proc/version; then
        # Add potential Windows PHP paths
        if [ -n "$WINDIR" ]; then
            php_locations+=("$WINDIR/php/php.exe")
        fi
        
        # Try to detect Windows Program Files PHP
        if [ -d "/mnt/c/Program Files/PHP" ]; then
            for dir in /mnt/c/Program\ Files/PHP/*; do
                if [ -f "$dir/php.exe" ]; then
                    php_locations+=("$dir/php.exe")
                fi
            done
        fi
        
        # Try XAMPP PHP location
        if [ -f "/mnt/c/xampp/php/php.exe" ]; then
            php_locations+=("/mnt/c/xampp/php/php.exe")
        fi
    fi
    
    # Find the first working PHP
    for php_path in "${php_locations[@]}"; do
        if [ -f "$php_path" ]; then
            echo "$php_path"
            return 0
        fi
    done
    
    # If no PHP found, return default and warn user
    echo "/usr/bin/php"
    return 1
}

# Get PHP path
PHP_PATH=$(find_php_path)

# Check if PHP exists at the detected path
if [ ! -f "$PHP_PATH" ]; then
    echo "Warning: PHP not found at $PHP_PATH"
    echo "Please install PHP or update this script with the correct path"
    echo "For WSL, you may need to: sudo apt update && sudo apt install -y php php-cli"
    
    # If called with parameters, exit with error
    if [ -n "$1" ]; then
        exit 1
    fi
fi

# If a parameter is passed, execute the PHP script
if [ -n "$1" ]; then
    "$PHP_PATH" "$@"
else
    echo "$PHP_PATH"
fi