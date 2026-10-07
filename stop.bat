@echo off
cd /d "%~dp0"
docker compose down
echo daylog stopped. Your data is kept.
pause
