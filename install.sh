#!/usr/bin/env bash

set -euo pipefail

TEMPLATE_REPO_URL="${TEMPLATE_REPO_URL:-https://github.com/dcardenasl/ci4-api-starter.git}"
TEMPLATE_BRANCH="${TEMPLATE_BRANCH:-main}"

GREEN='\033[0;32m'
YELLOW='\033[1;33m'
RED='\033[0;31m'
BLUE='\033[0;34m'
NC='\033[0m'

print_header() {
  printf "\n${BLUE}==> %s${NC}\n" "$1"
}

print_ok() {
  printf "${GREEN}OK${NC} %s\n" "$1"
}

print_warn() {
  printf "${YELLOW}WARN${NC} %s\n" "$1"
}

print_error() {
  printf "${RED}ERROR${NC} %s\n" "$1"
}

require_cmd() {
  if ! command -v "$1" >/dev/null 2>&1; then
    print_error "Required command not found: $1"
    exit 1
  fi
}

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
  local prompt="$1"
  local default="$2"
  local answer
  read -r -p "$prompt [$default]: " answer
  answer="$(trim "$answer")"
  if [ -z "$answer" ]; then
    answer="$default"
  fi
  printf "%s" "$answer"
}

ask_required() {
  local prompt="$1"
  local answer=""
  while [ -z "$answer" ]; do
    read -r -p "$prompt: " answer
    answer="$(trim "$answer")"
  done
  printf "%s" "$answer"
}

ask_hidden() {
  local prompt="$1"
  local answer=""
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

print_header "CI4 Project Bootstrapper"
printf "Template repo: %s (%s)\n" "$TEMPLATE_REPO_URL" "$TEMPLATE_BRANCH"

print_header "Checking requirements"
require_cmd git
require_cmd php
require_cmd composer
require_cmd mysql

if ! php -r 'exit(version_compare(PHP_VERSION, "8.2.0", ">=") ? 0 : 1);'; then
  print_error "PHP 8.2+ is required."
  exit 1
fi
print_ok "Dependencies found (git, php, composer, mysql)"

print_header "Collecting project data"
PROJECT_NAME_RAW="$(ask_required "Project name")"
PROJECT_NAME="$(slugify "$PROJECT_NAME_RAW")"

if [ -z "$PROJECT_NAME" ]; then
  print_error "Project name produced an empty folder slug."
  exit 1
fi

if [ -e "$PROJECT_NAME" ]; then
  print_error "Target folder '$PROJECT_NAME' already exists."
  exit 1
fi

DB_HOST="$(ask_with_default "MySQL host" "localhost")"
DB_PORT="$(ask_with_default "MySQL port" "3306")"
DB_USER="$(ask_with_default "MySQL user" "root")"
read -r -s -p "MySQL password (can be empty): " DB_PASS
printf "\n"

SUGGESTED_DB_NAME="$(printf "%s" "$PROJECT_NAME" | tr '-' '_')"
DB_NAME="$(ask_with_default "MySQL database name" "$SUGGESTED_DB_NAME")"
TEST_DB_NAME="$(ask_with_default "MySQL test database name" "${DB_NAME}_test")"
validate_db_name "$DB_NAME"
validate_db_name "$TEST_DB_NAME"

SUPERADMIN_EMAIL="$(ask_with_default "Superadmin email" "superadmin@example.com")"
SUPERADMIN_PASSWORD="$(ask_hidden "Superadmin password")"
SUPERADMIN_FIRST_NAME="$(ask_with_default "Superadmin first name" "Super")"
SUPERADMIN_LAST_NAME="$(ask_with_default "Superadmin last name" "Admin")"

print_header "Cloning template"
git clone --depth=1 --branch "$TEMPLATE_BRANCH" "$TEMPLATE_REPO_URL" "$PROJECT_NAME"
cd "$PROJECT_NAME"
print_ok "Project cloned into $PROJECT_NAME"

print_header "Installing dependencies"
composer install --no-interaction --prefer-dist
print_ok "Composer dependencies installed"

print_header "Configuring .env"
cp .env.example .env

php scripts/bootstrap_env.php \
  --file .env \
  --set "app.appName='${PROJECT_NAME_RAW//\'/}'" \
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

if ! grep -q '^app\.appName[[:space:]]*=' .env; then
  printf "\napp.appName = '%s'\n" "${PROJECT_NAME_RAW//\'/}" >> .env
fi

php spark key:generate --force >/dev/null
print_ok ".env configured and keys generated"

print_header "Preparing databases"
MYSQL_CMD=(mysql -h"$DB_HOST" -P"$DB_PORT" -u"$DB_USER")
if [ -n "$DB_PASS" ]; then
  MYSQL_CMD+=("-p$DB_PASS")
fi

if "${MYSQL_CMD[@]}" -e "CREATE DATABASE IF NOT EXISTS \`$DB_NAME\`; CREATE DATABASE IF NOT EXISTS \`$TEST_DB_NAME\`;"; then
  print_ok "Databases ensured: $DB_NAME, $TEST_DB_NAME"
else
  print_warn "Could not create databases automatically."
  printf "Run manually:\n"
  printf "  CREATE DATABASE IF NOT EXISTS `%s`;\n" "$DB_NAME"
  printf "  CREATE DATABASE IF NOT EXISTS `%s`;\n" "$TEST_DB_NAME"
fi

print_header "Running migrations"
php spark migrate
print_ok "Migrations completed"

print_header "Bootstrapping superadmin"
php spark users:bootstrap-superadmin \
  --email "$SUPERADMIN_EMAIL" \
  --password "$SUPERADMIN_PASSWORD" \
  --first-name "$SUPERADMIN_FIRST_NAME" \
  --last-name "$SUPERADMIN_LAST_NAME"
print_ok "Superadmin created/updated"

print_header "Git reset"
read -r -p "Reset git history for this new project? (y/N): " RESET_GIT
RESET_GIT="$(trim "$RESET_GIT")"
if [[ "$RESET_GIT" =~ ^[Yy]$ ]]; then
  rm -rf .git
  git init >/dev/null
  git add .
  if git commit -m "Initial commit from ci4-api-starter template" >/dev/null 2>&1; then
    print_ok "Git repository reset with initial commit"
  else
    print_warn "Git initialized, but commit failed (configure git user.name/user.email and commit manually)."
  fi
else
  print_warn "Keeping template git history as requested."
fi

print_header "Done"
printf "%s\n" "Project ready at: $(pwd)"
printf "Start server:\n"
printf "  cd %s\n" "$PROJECT_NAME"
printf "  php spark serve\n"
