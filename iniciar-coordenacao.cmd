@echo off
cd /d "%~dp0"
powershell.exe -NoProfile -ExecutionPolicy Bypass -File "%~dp0iniciar-local.ps1" -Rapido
if errorlevel 1 (
  echo Nao foi possivel iniciar. Confira a mensagem acima.
  pause
  exit /b 1
)
start "" "http://127.0.0.1:8088/php_appest/coordenacao/"
