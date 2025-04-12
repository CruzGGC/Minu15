@echo off
echo Importing OpenStreetMap data with osm2pgsql...

:: Path to osm2pgsql executable
set OSM2PGSQL_PATH=C:\Programs\Programs\osm2pgsql-bin\osm2pgsql.exe

:: Path to OSM data file (you can change this if needed)
set OSM_FILE=%~dp0..\data\geofabrik\portugal-latest.osm.pbf

:: Path to the style file (now in the scripts directory)
set STYLE_FILE=%~dp0default.style

:: Database connection details
set DB_NAME=projetosig
set DB_USER=postgres
set DB_HOST=localhost
set DB_PORT=5432

:: Check if data directory exists, if not create it
if not exist "%~dp0..\data\geofabrik" mkdir "%~dp0..\data\geofabrik"

:: Check if OSM file exists
if not exist "%OSM_FILE%" (
    echo OSM data file not found at: %OSM_FILE%
    echo Downloading Portugal OSM data from Geofabrik...
    
    :: Run the PHP download script
    "%~dp0..\config\php_path.bat" "%~dp0update_geofabrik_data.php" download_only
    
    if not exist "%OSM_FILE%" (
        echo Failed to download OSM data.
        echo Please download manually from https://download.geofabrik.de/europe/portugal-latest.osm.pbf
        echo and place it in the data\geofabrik folder.
        goto :exit
    )
)

echo.
echo Using osm2pgsql to import OpenStreetMap data...
echo.
echo Database: %DB_NAME%
echo OSM File: %OSM_FILE%
echo Style File: %STYLE_FILE%
echo.
echo This process will take some time, please be patient...
echo.

:: Run osm2pgsql with appropriate parameters
"%OSM2PGSQL_PATH%" -c -d %DB_NAME% -U %DB_USER% -H %DB_HOST% -P %DB_PORT% -W -S "%STYLE_FILE%" "%OSM_FILE%"

echo.
echo Import process completed.
echo.

:exit
pause