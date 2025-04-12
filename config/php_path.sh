#!/bin/bash
# Configuration file for PHP path
# This allows us to use a consistent PHP path across all scripts

# Set path to PHP executable - update this to your PHP installation path
PHP_PATH="/usr/bin/php"

# If a parameter is passed, execute the PHP script
if [ -n "$1" ]; then
    "$PHP_PATH" "$@"
else
    echo "PHP path is set to: $PHP_PATH"
fi