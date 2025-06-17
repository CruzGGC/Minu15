@echo off
echo A inicializar a Base de Dados do 15-Minute City Explorer...

:: Caminho para o executável PHP - atualize isto para o seu caminho de instalação do PHP
set PHP_PATH="C:\xampp\php\php.exe"

:: Executar o script de inicialização (do diretório comum)
%PHP_PATH% "%~dp0..\common\init_database.php"

echo.
echo Inicialização da base de dados concluída.
echo Prima qualquer tecla para sair...
pause > nul