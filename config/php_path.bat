@echo off
:: Configuration file for PHP path
:: This allows us to use a consistent PHP path across all scripts

:: Set path to PHP executable - update this to your PHP installation path
set PHP_PATH=C:\xampp\php\php.exe

:: If a parameter is passed, execute the PHP script
if not "%~1"=="" (
    "%PHP_PATH%" %*
) else (
    echo PHP path is set to: %PHP_PATH%
)