@echo off
echo Initializing 15-Minute City Explorer Database...

:: Path to PHP executable - update this to your PHP installation path
set PHP_PATH="C:\xampp\php\php.exe"

:: Run the initialization script
%PHP_PATH% "%~dp0init_database.php"

echo.
echo Database initialization completed.
echo Press any key to exit...
pause > nul