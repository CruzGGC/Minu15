@echo off
echo A configurar tarefa agendada semanalmente para atualização de dados Geofabrik...

:: Caminho para o executável PHP - atualize isto para o seu caminho de instalação do PHP
set PHP_PATH="C:\xampp\php\php.exe"
:: Caminho para o script de atualização (caminho corrigido para o diretório comum)
set SCRIPT_PATH="%~dp0..\common\update_geofabrik_data.php"

:: Criar uma tarefa agendada que é executada semanalmente
schtasks /create /tn "15MinCity_GeofabrikUpdate" /tr "%PHP_PATH% %SCRIPT_PATH%" /sc weekly /d SUN /st 03:00 /ru SYSTEM

echo A tarefa foi agendada para ser executada todos os Domingos às 3:00 da manhã.
echo Pode modificar este agendamento no Windows Task Scheduler.
pause