@echo off
:: Ficheiro de configuração para o caminho do PHP
:: Isto permite-nos usar um caminho PHP consistente em todos os scripts

:: Definir o caminho para o executável PHP - atualize isto para o seu caminho de instalação do PHP
set PHP_PATH=C:\xampp\php\php.exe

:: Se um parâmetro for passado, executar o script PHP
if not "%~1"=="" (
    "%PHP_PATH%" %*
) else (
    echo O caminho do PHP está definido para: %PHP_PATH%
)