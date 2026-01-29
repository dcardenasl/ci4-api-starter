#!/bin/bash

# CodeIgniter 4 API Starter - Initialization Script
# This script automates the setup of a new API project

set -e  # Exit on error

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Flags
SKIP_DEPS=false
SKIP_DB=false
SKIP_SERVER=false

# Parse arguments
while [[ $# -gt 0 ]]; do
    case $1 in
        --skip-deps)
            SKIP_DEPS=true
            shift
            ;;
        --skip-db)
            SKIP_DB=true
            shift
            ;;
        --skip-server)
            SKIP_SERVER=true
            shift
            ;;
        --help)
            echo "Usage: ./init.sh [OPTIONS]"
            echo ""
            echo "Options:"
            echo "  --skip-deps     Skip composer install"
            echo "  --skip-db       Skip database creation"
            echo "  --skip-server   Don't start development server"
            echo "  --help          Show this help message"
            exit 0
            ;;
        *)
            echo -e "${RED}Unknown option: $1${NC}"
            exit 1
            ;;
    esac
done

echo -e "${BLUE}╔════════════════════════════════════════════════════════╗${NC}"
echo -e "${BLUE}║   CodeIgniter 4 API Starter - Initialization Script   ║${NC}"
echo -e "${BLUE}╚════════════════════════════════════════════════════════╝${NC}"
echo ""

# Function to print section headers
print_header() {
    echo ""
    echo -e "${BLUE}▶ $1${NC}"
    echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
}

# Function to print success messages
print_success() {
    echo -e "${GREEN}✓ $1${NC}"
}

# Function to print error messages
print_error() {
    echo -e "${RED}✗ $1${NC}"
}

# Function to print warnings
print_warning() {
    echo -e "${YELLOW}⚠ $1${NC}"
}

# Check if command exists
command_exists() {
    command -v "$1" >/dev/null 2>&1
}

# 1. Check Requirements
print_header "Checking Requirements"

# Check PHP
if command_exists php; then
    PHP_VERSION=$(php -r "echo PHP_VERSION;")
    if php -r "exit(version_compare(PHP_VERSION, '8.1.0', '>=') ? 0 : 1);"; then
        print_success "PHP $PHP_VERSION detected"
    else
        print_error "PHP 8.1 or higher required. Found: $PHP_VERSION"
        exit 1
    fi
else
    print_error "PHP not found. Please install PHP 8.1 or higher"
    exit 1
fi

# Check Composer
if command_exists composer; then
    COMPOSER_VERSION=$(composer --version | sed -E 's/.*version ([0-9]+\.[0-9]+\.[0-9]+).*/\1/')
    print_success "Composer $COMPOSER_VERSION detected"
else
    print_error "Composer not found. Please install Composer"
    exit 1
fi

# Check MySQL
if command_exists mysql; then
    print_success "MySQL detected"
else
    print_warning "MySQL client not found in PATH (not critical if using remote MySQL)"
fi

# Check OpenSSL
if command_exists openssl; then
    print_success "OpenSSL detected"
else
    print_error "OpenSSL not found. Required for generating secure keys"
    exit 1
fi

# 2. Install Dependencies
if [ "$SKIP_DEPS" = false ]; then
    print_header "Installing Dependencies"

    if [ -d "vendor" ]; then
        print_warning "vendor/ directory already exists. Running composer update..."
        composer update --no-interaction
    else
        composer install --no-interaction
    fi

    print_success "Dependencies installed"
else
    print_warning "Skipping dependency installation (--skip-deps)"
fi

# 3. Configure Environment
print_header "Configuring Environment"

if [ -f ".env" ]; then
    print_warning ".env file already exists"
    read -p "Overwrite existing .env file? (y/N): " -n 1 -r
    echo
    if [[ ! $REPLY =~ ^[Yy]$ ]]; then
        print_warning "Keeping existing .env file"
        ENV_EXISTS=true
    else
        cp .env.example .env
        print_success "Created new .env file"
        ENV_EXISTS=false
    fi
else
    cp .env.example .env
    print_success "Created .env file from .env.example"
    ENV_EXISTS=false
fi

# 4. Generate Secure Keys
if [ "$ENV_EXISTS" = false ] || [ "$ENV_EXISTS" != true ]; then
    print_header "Generating Secure Keys"

    # Generate JWT Secret
    echo -n "Generating JWT secret key... "
    JWT_SECRET=$(openssl rand -base64 64 | tr -d '\n')

    # Generate Encryption Key
    echo -n "Generating encryption key... "
    ENCRYPTION_KEY=$(openssl rand -hex 16)

    # Update .env file
    if [[ "$OSTYPE" == "darwin"* ]]; then
        # macOS
        sed -i '' "s|^JWT_SECRET_KEY = .*|JWT_SECRET_KEY = '${JWT_SECRET}'|" .env
        sed -i '' "s|^encryption.key = .*|encryption.key = hex2bin:${ENCRYPTION_KEY}|" .env
    else
        # Linux
        sed -i "s|^JWT_SECRET_KEY = .*|JWT_SECRET_KEY = '${JWT_SECRET}'|" .env
        sed -i "s|^encryption.key = .*|encryption.key = hex2bin:${ENCRYPTION_KEY}|" .env
    fi

    print_success "Secure keys generated and saved to .env"
else
    print_warning "Using existing .env configuration"
fi

# 5. Database Configuration
print_header "Database Configuration"

if [ "$SKIP_DB" = false ]; then
    echo ""
    echo "Enter your MySQL database credentials:"
    read -p "MySQL Host [127.0.0.1]: " DB_HOST
    DB_HOST=${DB_HOST:-127.0.0.1}

    read -p "MySQL Port [3306]: " DB_PORT
    DB_PORT=${DB_PORT:-3306}

    read -p "MySQL Username [root]: " DB_USER
    DB_USER=${DB_USER:-root}

    read -s -p "MySQL Password: " DB_PASS
    echo

    read -p "Database Name [ci4_api]: " DB_NAME
    DB_NAME=${DB_NAME:-ci4_api}

    read -p "Test Database Name [ci4_test]: " DB_TEST
    DB_TEST=${DB_TEST:-ci4_test}

    # Update .env with database credentials
    if [[ "$OSTYPE" == "darwin"* ]]; then
        sed -i '' "s|^database.default.hostname = .*|database.default.hostname = ${DB_HOST}|" .env
        sed -i '' "s|^database.default.database = .*|database.default.database = ${DB_NAME}|" .env
        sed -i '' "s|^database.default.username = .*|database.default.username = ${DB_USER}|" .env
        sed -i '' "s|^database.default.password = .*|database.default.password = ${DB_PASS}|" .env
        sed -i '' "s|^database.default.port = .*|database.default.port = ${DB_PORT}|" .env
    else
        sed -i "s|^database.default.hostname = .*|database.default.hostname = ${DB_HOST}|" .env
        sed -i "s|^database.default.database = .*|database.default.database = ${DB_NAME}|" .env
        sed -i "s|^database.default.username = .*|database.default.username = ${DB_USER}|" .env
        sed -i "s|^database.default.password = .*|database.default.password = ${DB_PASS}|" .env
        sed -i "s|^database.default.port = .*|database.default.port = ${DB_PORT}|" .env
    fi

    print_success "Database configuration saved to .env"

    # Create databases
    print_header "Creating Databases"

    echo "Attempting to create databases..."
    mysql -h"$DB_HOST" -P"$DB_PORT" -u"$DB_USER" -p"$DB_PASS" <<EOF 2>/dev/null || true
CREATE DATABASE IF NOT EXISTS \`${DB_NAME}\`;
CREATE DATABASE IF NOT EXISTS \`${DB_TEST}\`;
EOF

    if [ $? -eq 0 ]; then
        print_success "Databases created: $DB_NAME, $DB_TEST"
    else
        print_warning "Could not create databases automatically. Please create them manually:"
        echo "  CREATE DATABASE ${DB_NAME};"
        echo "  CREATE DATABASE ${DB_TEST};"
    fi
else
    print_warning "Skipping database setup (--skip-db)"
fi

# 6. Run Migrations
if [ "$SKIP_DB" = false ]; then
    print_header "Running Migrations"

    if php spark migrate --all 2>/dev/null; then
        print_success "Migrations completed"
    else
        print_warning "Migrations failed. You may need to run 'php spark migrate' manually"
    fi
else
    print_warning "Skipping migrations (--skip-db)"
fi

# 7. Generate API Documentation
print_header "Generating API Documentation"

if php spark swagger:generate 2>/dev/null; then
    print_success "API documentation generated: public/swagger.json"
else
    print_warning "Swagger generation failed. Run 'php spark swagger:generate' manually"
fi

# 8. Summary
print_header "Setup Complete!"

echo ""
echo -e "${GREEN}✓ Your CodeIgniter 4 API is ready!${NC}"
echo ""
echo "Next steps:"
echo ""

if [ "$SKIP_SERVER" = false ]; then
    echo "1. Start the development server:"
    echo -e "   ${YELLOW}php spark serve${NC}"
    echo ""
    echo "2. Test the API:"
    echo -e "   ${YELLOW}curl -X POST http://localhost:8080/api/v1/auth/register \\${NC}"
    echo -e "   ${YELLOW}  -H \"Content-Type: application/json\" \\${NC}"
    echo -e "   ${YELLOW}  -d '{\"username\":\"admin\",\"email\":\"admin@example.com\",\"password\":\"Admin123!\"}'${NC}"
    echo ""
    echo "3. View API documentation:"
    echo -e "   ${YELLOW}http://localhost:8080/swagger.json${NC}"
    echo ""
else
    echo "1. Start the development server:"
    echo -e "   ${YELLOW}php spark serve${NC}"
    echo ""
fi

echo -e "${BLUE}═══════════════════════════════════════════════════════${NC}"
echo ""

# Start server
if [ "$SKIP_SERVER" = false ]; then
    read -p "Start development server now? (Y/n): " -n 1 -r
    echo
    if [[ ! $REPLY =~ ^[Nn]$ ]]; then
        echo ""
        print_header "Starting Development Server"
        echo ""
        echo -e "${GREEN}Server starting at http://localhost:8080${NC}"
        echo -e "${YELLOW}Press Ctrl+C to stop${NC}"
        echo ""
        php spark serve
    fi
fi
