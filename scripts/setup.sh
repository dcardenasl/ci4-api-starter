#!/usr/bin/env bash
# Shared setup library for install.sh and init.sh.
# Source this file — do not execute it directly.
#
# Step functions expect these globals to be set before calling them:
#   DB_HOST  DB_PORT  DB_USER  DB_PASS  DB_NAME  TEST_DB_NAME

# ---------------------------------------------------------------------------
# Output helpers
# ---------------------------------------------------------------------------

GREEN='\033[0;32m'
YELLOW='\033[1;33m'
RED='\033[0;31m'
BLUE='\033[0;34m'
NC='\033[0m'

print_header() { printf "\n${BLUE}==> %s${NC}\n" "$1"; }
print_ok()     { printf "${GREEN}OK${NC} %s\n" "$1"; }
print_warn()   { printf "${YELLOW}WARN${NC} %s\n" "$1"; }
print_error()  { printf "${RED}ERROR${NC} %s\n" "$1"; }

require_cmd() {
  if ! command -v "$1" >/dev/null 2>&1; then
    print_error "Required command not found: $1"
    exit 1
  fi
}

# ---------------------------------------------------------------------------
# Input helpers
# ---------------------------------------------------------------------------

trim() {
  local value="$1"
  value="${value#"${value%%[![:space:]]*}"}"
  value="${value%"${value##*[![:space:]]}"}"
  printf "%s" "$value"
}

slugify() {
  local input
  input="$(printf "%s" "$1" | tr '[:upper:]' '[:lower:]')"
  input="$(printf "%s" "$input" | sed -E 's/[^a-z0-9._-]+/-/g; s/^-+//; s/-+$//')"
  printf "%s" "$input"
}

ask_with_default() {
  local prompt="$1" default="$2" answer
  read -r -p "$prompt [$default]: " answer
  answer="$(trim "$answer")"
  printf "%s" "${answer:-$default}"
}

ask_required() {
  local prompt="$1" answer=""
  while [ -z "$answer" ]; do
    read -r -p "$prompt: " answer
    answer="$(trim "$answer")"
  done
  printf "%s" "$answer"
}

ask_hidden() {
  local prompt="$1" answer=""
  while [ -z "$answer" ]; do
    read -r -s -p "$prompt: " answer
    printf "\n"
    answer="$(trim "$answer")"
  done
  printf "%s" "$answer"
}

validate_db_name() {
  local name="$1"
  if [[ ! "$name" =~ ^[A-Za-z0-9_]+$ ]]; then
    print_error "Invalid database name '$name'. Use only letters, numbers, and underscores."
    exit 1
  fi
}

# ---------------------------------------------------------------------------
# MySQL mode detection
# Uses :-  defaults so sourcing this file after detect_mysql_mode has already
# run (e.g. in install.sh pre-clone phase) won't reset the chosen mode.
# ---------------------------------------------------------------------------

MYSQL_MODE="${MYSQL_MODE:-local}"
DOCKER_CONTAINER="${DOCKER_CONTAINER:-}"

detect_mysql_mode() {
  if command -v mysql >/dev/null 2>&1; then
    MYSQL_MODE="local"
    print_ok "MySQL client found (local)"
    return
  fi

  print_warn "mysql CLI not found. Checking for Docker..."

  if ! command -v docker >/dev/null 2>&1; then
    print_warn "Neither mysql CLI nor docker found."
    print_warn "Database creation will be skipped — create DBs manually."
    MYSQL_MODE="skip"
    return
  fi

  printf "Running containers:\n"
  docker ps --format "  {{.Names}}\t{{.Image}}" 2>/dev/null | grep -i mysql \
    || printf "  (none found with 'mysql' in image name)\n"

  DOCKER_CONTAINER="$(ask_required "Docker container name with MySQL")"
  MYSQL_MODE="docker"
  print_ok "Will use docker exec on container: $DOCKER_CONTAINER"
}

run_mysql_sql() {
  local sql="$1"
  case "$MYSQL_MODE" in
    local)
      local cmd=(mysql -h"$DB_HOST" -P"$DB_PORT" -u"$DB_USER")
      [ -n "$DB_PASS" ] && cmd+=("-p$DB_PASS")
      "${cmd[@]}" -e "$sql"
      ;;
    docker)
      local cmd=(docker exec -i "$DOCKER_CONTAINER" mysql -u"$DB_USER")
      [ -n "$DB_PASS" ] && cmd+=("-p$DB_PASS")
      "${cmd[@]}" -e "$sql"
      ;;
    skip)
      return 1
      ;;
  esac
}

# ---------------------------------------------------------------------------
# Shared setup steps
# ---------------------------------------------------------------------------

# Install or update Composer dependencies.
ci4_install_deps() {
  print_header "Installing dependencies"
  if [ -d "vendor" ]; then
    print_warn "vendor/ already exists. Running composer update..."
    composer update --no-interaction
  else
    composer install --no-interaction --prefer-dist
  fi
  print_ok "Composer dependencies installed"
}

# Write .env from .env.example and configure it via bootstrap_env.php.
# Globals required: DB_HOST DB_PORT DB_USER DB_PASS DB_NAME TEST_DB_NAME
ci4_configure_env() {
  print_header "Configuring .env"
  cp .env.example .env
  php scripts/bootstrap_env.php \
    --file .env \
    --set "database.default.hostname=${DB_HOST}" \
    --set "database.default.database=${DB_NAME}" \
    --set "database.default.username=${DB_USER}" \
    --set "database.default.password=${DB_PASS}" \
    --set "database.default.port=${DB_PORT}" \
    --set "database.tests.hostname=${DB_HOST}" \
    --set "database.tests.database=${TEST_DB_NAME}" \
    --set "database.tests.username=${DB_USER}" \
    --set "database.tests.password=${DB_PASS}" \
    --set "database.tests.port=${DB_PORT}" \
    --generate-jwt
  php spark key:generate --force >/dev/null
  print_ok ".env configured and keys generated"
}

# Create the main and test databases.
# Globals required: DB_NAME TEST_DB_NAME MYSQL_MODE (+ DB_* for local/docker modes)
ci4_prepare_databases() {
  print_header "Preparing databases"
  local sql="CREATE DATABASE IF NOT EXISTS \`${DB_NAME}\`; CREATE DATABASE IF NOT EXISTS \`${TEST_DB_NAME}\`;"
  if [ "$MYSQL_MODE" = "skip" ]; then
    print_warn "Database creation skipped — create them manually:"
    printf "  CREATE DATABASE IF NOT EXISTS \`%s\`;\n" "$DB_NAME"
    printf "  CREATE DATABASE IF NOT EXISTS \`%s\`;\n" "$TEST_DB_NAME"
  elif run_mysql_sql "$sql"; then
    print_ok "Databases ensured: $DB_NAME, $TEST_DB_NAME"
  else
    print_warn "Could not create databases automatically. Run manually:"
    printf "  CREATE DATABASE IF NOT EXISTS \`%s\`;\n" "$DB_NAME"
    printf "  CREATE DATABASE IF NOT EXISTS \`%s\`;\n" "$TEST_DB_NAME"
  fi
}

# Run database migrations.
ci4_run_migrations() {
  print_header "Running migrations"
  if php spark migrate; then
    print_ok "Migrations completed"
  else
    print_warn "Migrations failed. Run 'php spark migrate' manually."
  fi
}

# Generate the OpenAPI / Swagger schema.
ci4_generate_swagger() {
  print_header "Generating OpenAPI schema"
  if php spark swagger:generate 2>/dev/null; then
    print_ok "OpenAPI schema generated"
  else
    print_warn "Swagger generation failed. Run 'php spark swagger:generate' manually."
  fi
}
