#!/bin/bash

###############################################################################
# CI4 API Starter - Initialization Script
#
# This script automates the setup of a new CodeIgniter 4 API project.
# Goal: Get your API running in under 10 minutes.
#
# Usage: ./init.sh [--skip-deps] [--skip-db]
#   --skip-deps: Skip composer install (if already installed)
#   --skip-db: Skip database creation (if already exists)
###############################################################################

set -e  # Exit on error

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Options
SKIP_DEPS=false
SKIP_DB=false

# Parse arguments
for arg in "$@"; do
  case $arg in
    --skip-deps)
      SKIP_DEPS=true
      shift
      ;;
    --skip-db)
      SKIP_DB=true
      shift
      ;;
    *)
      echo -e "${RED}Unknown argument: $arg${NC}"
      echo "Usage: ./init.sh [--skip-deps] [--skip-db]"
      exit 1
      ;;
  esac
done

echo -e "${BLUE}"
echo "╔═══════════════════════════════════════════════════════════════╗"
echo "║                                                               ║"
echo "║            CI4 API Starter - Initialization Script           ║"
echo "║                                                               ║"
echo "║  This will set up your CodeIgniter 4 API project with:       ║"
echo "║    • Dependencies installation                                ║"
echo "║    • Environment configuration                                ║"
echo "║    • Secure key generation                                    ║"
echo "║    • Database setup & migrations                              ║"
echo "║                                                               ║"
echo "╚═══════════════════════════════════════════════════════════════╝"
echo -e "${NC}"

# Function to print section headers
print_section() {
  echo ""
  echo -e "${BLUE}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"
  echo -e "${BLUE}  $1${NC}"
  echo -e "${BLUE}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"
}

# Function to print success messages
print_success() {
  echo -e "${GREEN}✓ $1${NC}"
}

# Function to print error messages
print_error() {
  echo -e "${RED}✗ $1${NC}"
}

# Function to print warning messages
print_warning() {
  echo -e "${YELLOW}⚠ $1${NC}"
}

# Function to check command exists
command_exists() {
  command -v "$1" >/dev/null 2>&1
}

###############################################################################
# 1. Check Requirements
###############################################################################

print_section "Checking Requirements"

# Check PHP
if ! command_exists php; then
  print_error "PHP is not installed. Please install PHP 8.1 or higher."
  exit 1
fi

PHP_VERSION=$(php -r 'echo PHP_VERSION;')
PHP_MAJOR=$(php -r 'echo PHP_MAJOR_VERSION;')
PHP_MINOR=$(php -r 'echo PHP_MINOR_VERSION;')

if [ "$PHP_MAJOR" -lt 8 ] || ([ "$PHP_MAJOR" -eq 8 ] && [ "$PHP_MINOR" -lt 1 ]); then
  print_error "PHP 8.1 or higher is required. Current version: $PHP_VERSION"
  exit 1
fi
print_success "PHP $PHP_VERSION detected"

# Check required PHP extensions
REQUIRED_EXTENSIONS=("mysqli" "mbstring" "intl" "json")
MISSING_EXTENSIONS=()

for ext in "${REQUIRED_EXTENSIONS[@]}"; do
  if ! php -m | grep -q "^$ext$"; then
    MISSING_EXTENSIONS+=("$ext")
  fi
done

if [ ${#MISSING_EXTENSIONS[@]} -gt 0 ]; then
  print_error "Missing required PHP extensions: ${MISSING_EXTENSIONS[*]}"
  echo "Please install them before continuing."
  exit 1
fi
print_success "All required PHP extensions are installed"

# Check Composer
if ! command_exists composer; then
  print_error "Composer is not installed. Please install Composer 2.x"
  exit 1
fi

COMPOSER_VERSION=$(composer --version 2>&1 | grep -oP '\d+\.\d+\.\d+' | head -1)
print_success "Composer $COMPOSER_VERSION detected"

# Check MySQL
if ! command_exists mysql; then
  print_warning "MySQL client not found in PATH. Database setup may require manual steps."
else
  print_success "MySQL client detected"
fi

# Check OpenSSL for key generation
if ! command_exists openssl; then
  print_error "OpenSSL is not installed. Required for generating JWT secret."
  exit 1
fi
print_success "OpenSSL detected"

###############################################################################
# 2. Install Dependencies
###############################################################################

if [ "$SKIP_DEPS" = false ]; then
  print_section "Installing Dependencies"

  if [ ! -f "composer.json" ]; then
    print_error "composer.json not found. Are you in the project root?"
    exit 1
  fi

  echo "Running: composer install..."
  composer install --no-interaction --optimize-autoloader
  print_success "Dependencies installed"
else
  print_warning "Skipping dependency installation (--skip-deps)"
fi

###############################################################################
# 3. Setup Environment File
###############################################################################

print_section "Configuring Environment"

if [ -f ".env" ]; then
  print_warning ".env file already exists"
  read -p "Do you want to overwrite it? (y/N): " -n 1 -r
  echo
  if [[ ! $REPLY =~ ^[Yy]$ ]]; then
    print_warning "Keeping existing .env file"
    ENV_SETUP_NEEDED=false
  else
    cp .env.example .env
    print_success "Created new .env file from template"
    ENV_SETUP_NEEDED=true
  fi
else
  if [ ! -f ".env.example" ]; then
    print_error ".env.example not found"
    exit 1
  fi

  cp .env.example .env
  print_success "Created .env file from template"
  ENV_SETUP_NEEDED=true
fi

###############################################################################
# 4. Generate Secure Keys
###############################################################################

if [ "$ENV_SETUP_NEEDED" = true ]; then
  print_section "Generating Secure Keys"

  # Generate JWT secret key
  echo "Generating JWT secret key..."
  JWT_SECRET=$(openssl rand -base64 64 | tr -d '\n')

  # Update JWT_SECRET_KEY in .env
  if [[ "$OSTYPE" == "darwin"* ]]; then
    # macOS
    sed -i '' "s|^JWT_SECRET_KEY = .*|JWT_SECRET_KEY = '$JWT_SECRET'|" .env
  else
    # Linux
    sed -i "s|^JWT_SECRET_KEY = .*|JWT_SECRET_KEY = '$JWT_SECRET'|" .env
  fi
  print_success "JWT secret key generated"

  # Generate encryption key using CodeIgniter command
  echo "Generating encryption key..."
  php spark key:generate --force >/dev/null 2>&1 || {
    # If command fails, generate manually
    ENC_KEY=$(openssl rand -hex 16)
    if [[ "$OSTYPE" == "darwin"* ]]; then
      sed -i '' "s|^encryption.key = .*|encryption.key = '$ENC_KEY'|" .env
    else
      sed -i "s|^encryption.key = .*|encryption.key = '$ENC_KEY'|" .env
    fi
  }
  print_success "Encryption key generated"

  print_warning "IMPORTANT: Keep your .env file secure and NEVER commit it to git!"
fi

###############################################################################
# 5. Configure Database
###############################################################################

print_section "Database Configuration"

# Read current .env values
source <(grep -v '^#' .env | grep 'database.default' | sed 's/^/export /')

DB_HOST=${database_default_hostname:-127.0.0.1}
DB_NAME=${database_default_database:-ci4_api}
DB_USER=${database_default_username:-root}
DB_PASS=${database_default_password:-root}
DB_PORT=${database_default_port:-3306}

echo "Current database settings:"
echo "  Host: $DB_HOST"
echo "  Database: $DB_NAME"
echo "  Username: $DB_USER"
echo "  Port: $DB_PORT"
echo ""

read -p "Do you want to change these settings? (y/N): " -n 1 -r
echo
if [[ $REPLY =~ ^[Yy]$ ]]; then
  read -p "Database host [$DB_HOST]: " NEW_HOST
  read -p "Database name [$DB_NAME]: " NEW_NAME
  read -p "Database username [$DB_USER]: " NEW_USER
  read -p "Database password: " -s NEW_PASS
  echo
  read -p "Database port [$DB_PORT]: " NEW_PORT

  # Update values if provided
  DB_HOST=${NEW_HOST:-$DB_HOST}
  DB_NAME=${NEW_NAME:-$DB_NAME}
  DB_USER=${NEW_USER:-$DB_USER}
  DB_PASS=${NEW_PASS:-$DB_PASS}
  DB_PORT=${NEW_PORT:-$DB_PORT}

  # Update .env file
  if [[ "$OSTYPE" == "darwin"* ]]; then
    sed -i '' "s|^database.default.hostname = .*|database.default.hostname = $DB_HOST|" .env
    sed -i '' "s|^database.default.database = .*|database.default.database = $DB_NAME|" .env
    sed -i '' "s|^database.default.username = .*|database.default.username = $DB_USER|" .env
    sed -i '' "s|^database.default.password = .*|database.default.password = $DB_PASS|" .env
    sed -i '' "s|^database.default.port = .*|database.default.port = $DB_PORT|" .env
  else
    sed -i "s|^database.default.hostname = .*|database.default.hostname = $DB_HOST|" .env
    sed -i "s|^database.default.database = .*|database.default.database = $DB_NAME|" .env
    sed -i "s|^database.default.username = .*|database.default.username = $DB_USER|" .env
    sed -i "s|^database.default.password = .*|database.default.password = $DB_PASS|" .env
    sed -i "s|^database.default.port = .*|database.default.port = $DB_PORT|" .env
  fi

  print_success "Database configuration updated"
fi

###############################################################################
# 6. Create Databases
###############################################################################

if [ "$SKIP_DB" = false ]; then
  print_section "Creating Databases"

  if command_exists mysql; then
    echo "Attempting to create databases..."

    # Try to create main database
    mysql -h"$DB_HOST" -P"$DB_PORT" -u"$DB_USER" -p"$DB_PASS" -e "CREATE DATABASE IF NOT EXISTS $DB_NAME;" 2>/dev/null && {
      print_success "Database '$DB_NAME' created"
    } || {
      print_warning "Could not create database '$DB_NAME' - it may already exist or credentials may be incorrect"
      echo "You can create it manually with:"
      echo "  CREATE DATABASE $DB_NAME;"
    }

    # Try to create test database
    TEST_DB_NAME="${DB_NAME}_test"
    mysql -h"$DB_HOST" -P"$DB_PORT" -u"$DB_USER" -p"$DB_PASS" -e "CREATE DATABASE IF NOT EXISTS $TEST_DB_NAME;" 2>/dev/null && {
      print_success "Test database '$TEST_DB_NAME' created"
    } || {
      print_warning "Could not create test database '$TEST_DB_NAME'"
    }
  else
    print_warning "MySQL client not available. Please create databases manually:"
    echo "  CREATE DATABASE $DB_NAME;"
    echo "  CREATE DATABASE ${DB_NAME}_test;"
    read -p "Press Enter once databases are created..."
  fi
else
  print_warning "Skipping database creation (--skip-db)"
fi

###############################################################################
# 7. Run Migrations
###############################################################################

print_section "Running Database Migrations"

if php spark migrate 2>&1; then
  print_success "Migrations completed successfully"
else
  print_error "Migration failed. Check your database configuration."
  exit 1
fi

###############################################################################
# 8. Setup Initial Admin User (Optional)
###############################################################################

print_section "Initial User Setup"

echo "Would you like to create an initial admin user?"
read -p "Create admin user? (Y/n): " -n 1 -r
echo

if [[ ! $REPLY =~ ^[Nn]$ ]]; then
  read -p "Admin username: " ADMIN_USER
  read -p "Admin email: " ADMIN_EMAIL
  read -p "Admin password: " -s ADMIN_PASS
  echo

  # Create a temporary seeder
  TEMP_SEEDER=$(mktemp)
  cat > "$TEMP_SEEDER" << EOF
<?php
namespace App\Database\Seeds;
use CodeIgniter\Database\Seeder;

class TempAdminSeeder extends Seeder
{
    public function run()
    {
        \$data = [
            'username' => '$ADMIN_USER',
            'email' => '$ADMIN_EMAIL',
            'password' => password_hash('$ADMIN_PASS', PASSWORD_BCRYPT),
            'role' => 'admin',
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ];

        \$this->db->table('users')->insert(\$data);
        echo "Admin user created successfully!\n";
    }
}
EOF

  # Copy to Seeds directory
  cp "$TEMP_SEEDER" app/Database/Seeds/TempAdminSeeder.php

  # Run the seeder
  php spark db:seed TempAdminSeeder

  # Clean up
  rm -f "$TEMP_SEEDER" app/Database/Seeds/TempAdminSeeder.php

  print_success "Admin user created"
else
  print_warning "Skipping admin user creation"
  echo "You can create users via the API: POST /api/v1/auth/register"
fi

###############################################################################
# 9. Generate API Documentation
###############################################################################

print_section "Generating API Documentation"

if php spark swagger:generate 2>&1; then
  print_success "API documentation generated at public/swagger.json"
else
  print_warning "Could not generate Swagger documentation"
fi

###############################################################################
# 10. Verify Installation
###############################################################################

print_section "Verifying Installation"

# Check critical files exist
CRITICAL_FILES=(".env" "vendor/autoload.php" "public/swagger.json")
ALL_EXIST=true

for file in "${CRITICAL_FILES[@]}"; do
  if [ -f "$file" ]; then
    print_success "$file exists"
  else
    print_error "$file missing"
    ALL_EXIST=false
  fi
done

if [ "$ALL_EXIST" = false ]; then
  print_error "Some files are missing. Installation may be incomplete."
  exit 1
fi

###############################################################################
# 11. Final Instructions
###############################################################################

echo ""
echo -e "${GREEN}"
echo "╔═══════════════════════════════════════════════════════════════╗"
echo "║                                                               ║"
echo "║                  🎉 Installation Complete! 🎉                 ║"
echo "║                                                               ║"
echo "╚═══════════════════════════════════════════════════════════════╝"
echo -e "${NC}"

echo ""
echo -e "${BLUE}Next Steps:${NC}"
echo ""
echo "1. Start the development server:"
echo -e "   ${GREEN}php spark serve${NC}"
echo ""
echo "2. Test your API:"
echo -e "   ${GREEN}curl -X POST http://localhost:8080/api/v1/auth/register \\${NC}"
echo -e "   ${GREEN}  -H \"Content-Type: application/json\" \\${NC}"
echo -e "   ${GREEN}  -d '{\"username\":\"test\",\"email\":\"test@example.com\",\"password\":\"test123\"}'${NC}"
echo ""
echo "3. View API documentation:"
echo -e "   ${GREEN}http://localhost:8080/swagger.json${NC}"
echo ""
echo -e "${BLUE}Documentation:${NC}"
echo "  • README.md - Getting started guide"
echo "  • SECURITY.md - Security best practices"
echo "  • TESTING.md - Testing guide"
echo "  • DOCKER.md - Docker deployment"
echo ""
echo -e "${YELLOW}Security Reminders:${NC}"
echo "  ⚠ Never commit .env to git"
echo "  ⚠ Change default keys in production"
echo "  ⚠ Use HTTPS in production"
echo "  ⚠ Review SECURITY.md before deployment"
echo ""
echo -e "${GREEN}Happy coding! 🚀${NC}"
echo ""

# Ask if user wants to start the server now
read -p "Start development server now? (Y/n): " -n 1 -r
echo
if [[ ! $REPLY =~ ^[Nn]$ ]]; then
  echo ""
  echo -e "${GREEN}Starting server at http://localhost:8080${NC}"
  echo -e "${YELLOW}Press Ctrl+C to stop${NC}"
  echo ""
  php spark serve
fi
