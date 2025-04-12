@echo off
echo Setting up weekly scheduled task for Geofabrik data update...

:: Path to PHP executable - update this to your PHP installation path
set PHP_PATH="C:\xampp\php\php.exe"
:: Path to the update script (corrected path to common directory)
set SCRIPT_PATH="%~dp0..\common\update_geofabrik_data.php"

:: Create a scheduled task that runs weekly
schtasks /create /tn "15MinCity_GeofabrikUpdate" /tr "%PHP_PATH% %SCRIPT_PATH%" /sc weekly /d SUN /st 03:00 /ru SYSTEM

echo Task has been scheduled to run every Sunday at 3:00 AM.
echo You can modify this schedule in Windows Task Scheduler.
pause