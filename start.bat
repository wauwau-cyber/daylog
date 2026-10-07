@echo off
setlocal
cd /d "%~dp0"

if not exist .env copy .env.example .env >nul

docker info >nul 2>&1
if errorlevel 1 (
  echo Docker Desktop is not running. Start it, wait until it is ready, then run this again.
  pause
  exit /b 1
)

echo Starting daylog. The first start builds the app and can take a few minutes...
docker compose up -d --build --remove-orphans
if errorlevel 1 (
  pause
  exit /b 1
)

set PORT=47080
for /f "usebackq tokens=1,* delims==" %%a in (".env") do if "%%a"=="APP_PORT" set PORT=%%b

echo Waiting for the app on port %PORT%...
set /a TRIES=0
:wait
powershell -NoProfile -Command "try { Invoke-WebRequest -UseBasicParsing http://localhost:%PORT%/api/profile -TimeoutSec 3 | Out-Null; exit 0 } catch { exit 1 }"
if not errorlevel 1 goto ready
set /a TRIES+=1
if %TRIES% GEQ 60 (
  echo The app did not respond. Check the logs with: docker compose logs app db
  pause
  exit /b 1
)
timeout /t 2 /nobreak >nul
goto wait

:ready
start "" http://localhost:%PORT%
echo daylog is running at http://localhost:%PORT%
