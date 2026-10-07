#!/usr/bin/env bash
# daylog helper for macOS and Linux: ./daylog.sh start|update|stop
set -uo pipefail
cd "$(dirname "$0")"

fail() { echo; echo "$1" >&2; exit 1; }

port_free() { ! (exec 3<>"/dev/tcp/127.0.0.1/$1") 2>/dev/null; }

find_free_port() {
  local p
  for ((p = $1; p < $1 + 100; p++)); do port_free "$p" && { echo "$p"; return; }; done
  fail "No free port found from $1."
}

env_value() { grep -E "^$1=" .env 2>/dev/null | head -1 | cut -d= -f2-; }

set_env_value() {
  if grep -qE "^$1=" .env; then
    sed -i.bak "s/^$1=.*/$1=$2/" .env && rm -f .env.bak
  else
    echo "$1=$2" >> .env
  fi
}

secret() { LC_ALL=C tr -dc 'A-Za-z0-9' < /dev/urandom | head -c 24; }

data_exists() { [ -n "$(docker volume ls -q --filter 'name=^daylog_db_data$')" ]; }
app_running() { docker compose ps --status running --services 2>/dev/null | grep -qx app; }

assert_docker() { docker info >/dev/null 2>&1 || fail "Docker is not running. Start Docker Desktop and try again."; }

init_env() {
  [ -f .env ] && return
  data_exists && fail "The settings file .env is missing, but daylog data exists. Copy backups/env-backup.txt to .env and try again."
  printf 'APP_PORT=%s\nTZ=Europe/Berlin\nDB_PASSWORD=%s\nDB_ROOT_PASSWORD=%s\n' \
    "$(find_free_port 47080)" "$(secret)" "$(secret)" > .env
  mkdir -p backups && cp .env backups/env-backup.txt
}

resolve_port() {
  local port; port=$(env_value APP_PORT)
  if ! app_running && ! port_free "$port"; then
    local new; new=$(find_free_port $((port + 1)))
    echo "Port $port is in use by another program, switching to $new."
    set_env_value APP_PORT "$new"
    cp .env backups/env-backup.txt
  fi
}

wait_app() {
  local url="http://localhost:$(env_value APP_PORT)"
  printf 'Waiting for daylog'
  for _ in $(seq 1 90); do
    if curl -fs "$url/api/profile" >/dev/null 2>&1; then
      echo; echo "daylog is running at $url"
      (open "$url" 2>/dev/null || xdg-open "$url" 2>/dev/null) &
      return
    fi
    printf '.'; sleep 2
  done
  fail "daylog did not respond. Show details with: docker compose logs app db"
}

backup() {
  echo "Saving a backup of your data..."
  docker compose up -d db >/dev/null
  for _ in $(seq 1 60); do
    docker compose exec -T db mysqladmin ping -h localhost --silent >/dev/null 2>&1 && break
    sleep 2
  done
  docker compose exec -T db sh -c 'MYSQL_PWD=$MYSQL_ROOT_PASSWORD mysqldump -uroot --single-transaction --routines --triggers $MYSQL_DATABASE > /tmp/daylog-backup.sql' \
    || fail "The backup failed, so the update was cancelled. Your data is unchanged."
  mkdir -p backups
  local file="backups/daylog-$(date +%Y-%m-%d_%H%M).sql"
  docker compose cp db:/tmp/daylog-backup.sql "$file" >/dev/null || fail "The backup could not be saved, so the update was cancelled."
  ls -1 backups/daylog-*.sql | sort -r | tail -n +11 | xargs -r rm -f
  echo "Backup saved: $file"
}

case "${1:-start}" in
  start)
    assert_docker; init_env; resolve_port
    echo "Starting daylog. The first start downloads the app and can take a few minutes..."
    docker compose up -d --remove-orphans || fail "daylog could not be started."
    wait_app ;;
  update)
    assert_docker; init_env
    data_exists && backup
    echo "Downloading the latest version..."
    docker compose pull app || fail "The download failed. Check your internet connection."
    resolve_port
    docker compose up -d --remove-orphans || fail "daylog could not be restarted."
    wait_app ;;
  stop)
    docker compose down && echo "daylog stopped. Your data is kept." ;;
  *) echo "Usage: ./daylog.sh start|update|stop"; exit 1 ;;
esac
