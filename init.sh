#!/usr/bin/env bash
# CodeIgniter 4 API Starter — environment initializer.
# Run this script from the project root after cloning the repo.
# Usage: ./init.sh [--skip-deps] [--skip-db] [--skip-server]

set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
# shellcheck source=scripts/setup.sh
source "$SCRIPT_DIR/scripts/setup.sh"

# ---------------------------------------------------------------------------
# Flags
# ---------------------------------------------------------------------------

SKIP_DEPS=false
SKIP_DB=false
SKIP_SERVER=false

while [ $# -gt 0 ]; do
  case $1 in
    --skip-deps)   SKIP_DEPS=true; shift ;;
    --skip-db)     SKIP_DB=true; shift ;;
    --skip-server) SKIP_SERVER=true; shift ;;
    --help)
      printf "Usage: ./init.sh [OPTIONS]\n\n"
      printf "Options:\n"
      printf "  --skip-deps     Skip composer install\n"
      printf "  --skip-db       Skip database creation and migrations\n"
      printf "  --skip-server   Do not offer to start the development server\n"
      printf "  --help          Show this help message\n"
      exit 0
      ;;
    *)
      print_error "Unknown option: $1"
      exit 1
      ;;
  esac
done

LOG_FILE="$(pwd)/init.log"
exec > >(tee -a "$LOG_FILE") 2>&1
printf "Init log: %s\n" "$LOG_FILE"

print_header "CI4 Project — Environment Setup"

# ---------------------------------------------------------------------------
# Requirements
# ---------------------------------------------------------------------------

print_header "Checking requirements"
require_cmd php
require_cmd composer
detect_mysql_mode

if ! php -r 'exit(version_compare(PHP_VERSION, "8.2.0", ">=") ? 0 : 1);'; then
  print_error "PHP 8.2+ is required (found: $(php -r 'echo PHP_VERSION;'))."
  printf "  macOS:  brew install php@8.2\n"
  printf "  Ubuntu: sudo apt install php8.2\n"
  printf "  See: https://www.php.net/downloads\n"
  exit 1
fi
print_ok "Dependencies found (php, composer)"

# ---------------------------------------------------------------------------
# Database credentials
# ---------------------------------------------------------------------------

DB_HOST="127.0.0.1"
DB_PORT="3306"
DB_USER="root"
DB_PASS=""
DB_NAME="ci4_api"
TEST_DB_NAME="ci4_api_test"

# Use detected Docker port as default if available
[ -n "$DETECTED_DOCKER_PORT" ] && DB_PORT="$DETECTED_DOCKER_PORT"

if [ "$SKIP_DB" = false ]; then
  print_header "Database configuration"
  DB_HOST="$(ask_with_default "MySQL host" "$DB_HOST")"
  DB_PORT="$(ask_with_default "MySQL port" "$DB_PORT")"
  DB_USER="$(ask_with_default "MySQL user" "$DB_USER")"
  read -r -s -p "MySQL password (can be empty): " DB_PASS
  printf "\n"
  DB_NAME="$(ask_with_default "Database name" "$DB_NAME")"
  TEST_DB_NAME="$(ask_with_default "Test database name" "$TEST_DB_NAME")"
  validate_db_name "$DB_NAME"
  validate_db_name "$TEST_DB_NAME"
fi

# ---------------------------------------------------------------------------
# Steps
# ---------------------------------------------------------------------------

if [ "$SKIP_DEPS" = false ]; then
  ci4_install_deps
fi

print_header "Environment configuration"
if [ -f ".env" ]; then
  print_warn ".env already exists."
  read -r -p "Overwrite? (y/N): " OVERWRITE_ENV
  OVERWRITE_ENV="$(trim "$OVERWRITE_ENV")"
  case "$OVERWRITE_ENV" in
    [Yy]) ci4_configure_env ;;
    *)    print_warn "Keeping existing .env — skipping key generation." ;;
  esac
else
  ci4_configure_env
fi

if [ "$SKIP_DB" = false ]; then
  ci4_prepare_databases
  ci4_verify_database
  ci4_run_migrations
  ci4_seed_rbac
fi

ci4_generate_swagger

# ---------------------------------------------------------------------------
# Superadmin (optional)
# ---------------------------------------------------------------------------

read -r -p "Bootstrap a superadmin account? (y/N): " BOOTSTRAP_SA
BOOTSTRAP_SA="$(trim "$BOOTSTRAP_SA")"
case "$BOOTSTRAP_SA" in
  [Yy])
    print_header "Superadmin"
    SA_EMAIL="$(ask_with_default "Email" "superadmin@example.com")"
    SA_PASSWORD="$(ask_hidden "Password (min 8 chars)")"
    while [ "${#SA_PASSWORD}" -lt 8 ]; do
      print_warn "Password must be at least 8 characters. Try again." >&2
      SA_PASSWORD="$(ask_hidden "Password (min 8 chars)")"
    done
    SA_FIRST_NAME="$(ask_with_default "First name" "Super")"
    SA_LAST_NAME="$(ask_with_default "Last name" "Admin")"
    php spark users:bootstrap-superadmin \
      --email "$SA_EMAIL" \
      --password "$SA_PASSWORD" \
      --first-name "$SA_FIRST_NAME" \
      --last-name "$SA_LAST_NAME"
    print_ok "Superadmin created/updated"
    ;;
esac

# ---------------------------------------------------------------------------
# Done
# ---------------------------------------------------------------------------

print_header "Done"
printf "Project ready at: %s\n" "$(pwd)"

if [ "$SKIP_SERVER" = false ]; then
  read -r -p "Start development server now? (y/N): " START_SERVER
  START_SERVER="$(trim "$START_SERVER")"
  case "$START_SERVER" in
    [Yy])
      print_header "Starting development server"
      printf "Server at http://localhost:8080 — press Ctrl+C to stop.\n\n"
      php spark serve
      ;;
    *)
      printf "Start server: php spark serve\n"
      ;;
  esac
fi
