#!/usr/bin/env sh
set -e
cd "$(dirname "$0")"
[ -f .env ] || cp .env.example .env
docker info >/dev/null 2>&1 || { echo "Docker is not running. Start Docker Desktop and try again."; exit 1; }
echo "Starting daylog. The first start builds the app and can take a few minutes..."
docker compose up -d --build --remove-orphans
PORT=$(grep -E '^APP_PORT=' .env | cut -d= -f2); PORT=${PORT:-47080}
printf "Waiting for the app"
for i in $(seq 1 60); do
  if curl -fs "http://localhost:$PORT/api/profile" >/dev/null 2>&1; then
    echo; echo "daylog is running at http://localhost:$PORT"
    (open "http://localhost:$PORT" 2>/dev/null || xdg-open "http://localhost:$PORT" 2>/dev/null) &
    exit 0
  fi
  printf "."; sleep 2
done
echo; echo "The app did not respond. Check: docker compose logs app db"; exit 1
