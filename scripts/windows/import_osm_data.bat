@echo off
echo A importar dados OpenStreetMap com osm2pgsql...

:: Caminho para o executável osm2pgsql
set OSM2PGSQL_PATH=C:\Programs\Programs\osm2pgsql-bin\osm2pgsql.exe

:: Caminho para o ficheiro de dados OSM (caminho corrigido)
set OSM_FILE=%~dp0..\..\data\geofabrik\portugal-latest.osm.pbf

:: Caminho para o ficheiro de estilo (no diretório comum)
set STYLE_FILE=%~dp0..\common\default.style

:: Detalhes de conexão da base de dados
set DB_NAME=projetosig
set DB_USER=postgres
set DB_HOST=localhost
set DB_PORT=5432

:: Verificar se o diretório de dados existe, caso contrário, criá-lo
if not exist "%~dp0..\..\data\geofabrik" mkdir "%~dp0..\..\data\geofabrik"

:: Verificar se o ficheiro OSM existe
if not exist "%OSM_FILE%" (
    echo Ficheiro de dados OSM não encontrado em: %OSM_FILE%
    echo A descarregar dados OSM de Portugal do Geofabrik...
    
    :: Executar o script de descarregamento PHP do diretório comum
    "%~dp0..\..\config\php_path.bat" "%~dp0..\common\update_geofabrik_data.php" download_only
    
    if not exist "%OSM_FILE%" (
        echo Falha ao descarregar dados OSM.
        echo Por favor, descarregue manualmente de https://download.geofabrik.de/europe/portugal-latest.osm.pbf
        echo e coloque-o na pasta data\geofabrik.
        goto :exit
    )
)

echo.
echo A usar osm2pgsql para importar dados OpenStreetMap...
echo.
echo Base de Dados: %DB_NAME%
echo Ficheiro OSM: %OSM_FILE%
echo Ficheiro de Estilo: %STYLE_FILE%
echo.
echo Este processo levará algum tempo, por favor, seja paciente...
echo.

:: Executar osm2pgsql com os parâmetros apropriados
"%OSM2PGSQL_PATH%" -c -d %DB_NAME% -U %DB_USER% -H %DB_HOST% -P %DB_PORT% -W -S "%STYLE_FILE%" "%OSM_FILE%"

echo.
echo Processo de importação concluído.
echo.

:exit
pause