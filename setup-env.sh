#!/bin/bash
# DEPRECATED: This script is superseded by init.sh, which handles environment
# setup with better validation and cross-platform compatibility.
#
#   Use: ./init.sh
#   Or for Docker environments: ./init.sh --skip-db
#
# This file is kept for backwards compatibility and will be removed in a future
# version. It only manages Docker-specific .env.docker secrets; for local
# development, run init.sh instead.

# Environment Setup Script for CI4 API Starter
# This script helps you set up secure environment files

set -euo pipefail

# Colors for output — defined first so all messages can use them
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

echo "================================================"
echo "  CI4 API Starter - Environment Setup"
echo "================================================"
echo ""

# Validate required template files exist
for f in .env.example .env.docker.example; do
  if [ ! -f "$f" ]; then
    echo -e "${RED}Error: $f not found. Run this script from the project root.${NC}"
    exit 1
  fi
done

# Check if .env exists
if [ -f ".env" ]; then
    echo -e "${YELLOW}Warning: .env already exists${NC}"
    read -p "Do you want to overwrite it? (y/N): " -n 1 -r
    echo
    if [[ ! $REPLY =~ ^[Yy]$ ]]; then
        echo "Skipping .env creation"
    else
        cp .env.example .env
        echo -e "${GREEN}✓${NC} Created .env from .env.example"
    fi
else
    cp .env.example .env
    echo -e "${GREEN}✓${NC} Created .env from .env.example"
fi

# Check if .env.docker exists
if [ -f ".env.docker" ]; then
    echo -e "${YELLOW}Warning: .env.docker already exists${NC}"
    read -p "Do you want to overwrite it? (y/N): " -n 1 -r
    echo
    if [[ ! $REPLY =~ ^[Yy]$ ]]; then
        echo "Skipping .env.docker creation"
    else
        cp .env.docker.example .env.docker
        echo -e "${GREEN}✓${NC} Created .env.docker from .env.docker.example"
    fi
else
    cp .env.docker.example .env.docker
    echo -e "${GREEN}✓${NC} Created .env.docker from .env.docker.example"
fi

echo ""
echo "================================================"
echo "  Generating Secure Keys"
echo "================================================"
echo ""

# Check if openssl is available
if command -v openssl &> /dev/null; then
    # Generate JWT secret
    JWT_SECRET=$(openssl rand -base64 64 | tr -d '\n')
    echo -e "${GREEN}✓${NC} Generated JWT_SECRET_KEY"

    # Generate encryption key
    ENCRYPTION_KEY=$(openssl rand -hex 32)
    echo -e "${GREEN}✓${NC} Generated encryption.key"

    # Generate MySQL passwords
    MYSQL_ROOT_PASSWORD=$(openssl rand -base64 24 | tr -d '\n')
    MYSQL_PASSWORD=$(openssl rand -base64 24 | tr -d '\n')
    echo -e "${GREEN}✓${NC} Generated MySQL passwords"

    echo ""
    echo "================================================"
    echo "  Updating Configuration Files"
    echo "================================================"
    echo ""

    # Prefer bootstrap_env.php (robust, no sed delimiter issues) if PHP is available.
    # Falls back to sed if PHP is not installed.
    if command -v php &> /dev/null && [ -f "scripts/bootstrap_env.php" ]; then
        if [ -f ".env" ]; then
            php scripts/bootstrap_env.php \
                --file .env \
                --set "JWT_SECRET_KEY=${JWT_SECRET}" \
                --set "encryption.key=hex:${ENCRYPTION_KEY}"
            echo -e "${GREEN}✓${NC} Updated .env with secure keys"
        fi

        if [ -f ".env.docker" ]; then
            php scripts/bootstrap_env.php \
                --file .env.docker \
                --set "JWT_SECRET_KEY=${JWT_SECRET}" \
                --set "encryption.key=hex:${ENCRYPTION_KEY}" \
                --set "MYSQL_ROOT_PASSWORD=${MYSQL_ROOT_PASSWORD}" \
                --set "MYSQL_PASSWORD=${MYSQL_PASSWORD}" \
                --set "database.default.password=${MYSQL_PASSWORD}" \
                --set "database.tests.password=${MYSQL_PASSWORD}"
            echo -e "${GREEN}✓${NC} Updated .env.docker with secure keys and passwords"
        fi
    else
        # Fallback: sed-based replacement (macOS/Linux compatible, safe for base64 values)
        if [ -f ".env" ]; then
            if [[ "$OSTYPE" == "darwin"* ]]; then
                sed -i '' "s|JWT_SECRET_KEY = ''|JWT_SECRET_KEY = '${JWT_SECRET}'|g" .env
                sed -i '' "s|encryption.key = ''|encryption.key = 'hex:${ENCRYPTION_KEY}'|g" .env
            else
                sed -i "s|JWT_SECRET_KEY = ''|JWT_SECRET_KEY = '${JWT_SECRET}'|g" .env
                sed -i "s|encryption.key = ''|encryption.key = 'hex:${ENCRYPTION_KEY}'|g" .env
            fi
            echo -e "${GREEN}✓${NC} Updated .env with secure keys (sed fallback)"
        fi

        if [ -f ".env.docker" ]; then
            if [[ "$OSTYPE" == "darwin"* ]]; then
                sed -i '' "s|JWT_SECRET_KEY = ''|JWT_SECRET_KEY = '${JWT_SECRET}'|g" .env.docker
                sed -i '' "s|encryption.key = ''|encryption.key = 'hex:${ENCRYPTION_KEY}'|g" .env.docker
                sed -i '' "s|MYSQL_ROOT_PASSWORD = CHANGE_THIS_ROOT_PASSWORD|MYSQL_ROOT_PASSWORD = ${MYSQL_ROOT_PASSWORD}|g" .env.docker
                sed -i '' "s|MYSQL_PASSWORD = CHANGE_THIS_PASSWORD|MYSQL_PASSWORD = ${MYSQL_PASSWORD}|g" .env.docker
                sed -i '' "s|database.default.password = CHANGE_THIS_PASSWORD|database.default.password = ${MYSQL_PASSWORD}|g" .env.docker
                sed -i '' "s|database.tests.password = CHANGE_THIS_PASSWORD|database.tests.password = ${MYSQL_PASSWORD}|g" .env.docker
            else
                sed -i "s|JWT_SECRET_KEY = ''|JWT_SECRET_KEY = '${JWT_SECRET}'|g" .env.docker
                sed -i "s|encryption.key = ''|encryption.key = 'hex:${ENCRYPTION_KEY}'|g" .env.docker
                sed -i "s|MYSQL_ROOT_PASSWORD = CHANGE_THIS_ROOT_PASSWORD|MYSQL_ROOT_PASSWORD = ${MYSQL_ROOT_PASSWORD}|g" .env.docker
                sed -i "s|MYSQL_PASSWORD = CHANGE_THIS_PASSWORD|MYSQL_PASSWORD = ${MYSQL_PASSWORD}|g" .env.docker
                sed -i "s|database.default.password = CHANGE_THIS_PASSWORD|database.default.password = ${MYSQL_PASSWORD}|g" .env.docker
                sed -i "s|database.tests.password = CHANGE_THIS_PASSWORD|database.tests.password = ${MYSQL_PASSWORD}|g" .env.docker
            fi
            echo -e "${GREEN}✓${NC} Updated .env.docker with secure keys and passwords (sed fallback)"
        fi
    fi

    echo ""
    echo "================================================"
    echo "  Setup Complete!"
    echo "================================================"
    echo ""
    echo -e "${GREEN}Your environment files are now configured with secure credentials.${NC}"
    echo ""
    echo "Next steps:"
    echo "1. Review .env for local development settings"
    echo "2. Review .env.docker for Docker settings"
    echo "3. Update database.default.password in .env with your local MySQL password"
    echo "4. Run: php spark migrate"
    echo "   Or: docker compose up -d && docker exec ci4-api-app php spark migrate"
    echo ""
    echo -e "${YELLOW}⚠️  IMPORTANT: Never commit .env or .env.docker to git!${NC}"
    echo ""

else
    echo -e "${RED}Error: openssl not found${NC}"
    echo "Please install openssl to generate secure keys"
    echo ""
    echo "Manual setup:"
    echo "1. Edit .env and .env.docker"
    echo "2. Generate JWT_SECRET_KEY with: openssl rand -base64 64"
    echo "3. Generate encryption.key with: openssl rand -hex 32"
    echo "4. Set strong MySQL passwords in .env.docker"
    exit 1
fi
