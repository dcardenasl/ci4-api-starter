#!/bin/bash

# Environment Setup Script for CI4 API Starter
# This script helps you set up secure environment files

set -e

echo "================================================"
echo "  CI4 API Starter - Environment Setup"
echo "================================================"
echo ""

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

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

    # Update .env
    if [ -f ".env" ]; then
        # For macOS (BSD sed)
        if [[ "$OSTYPE" == "darwin"* ]]; then
            sed -i '' "s|JWT_SECRET_KEY = ''|JWT_SECRET_KEY = '$JWT_SECRET'|g" .env
            sed -i '' "s|encryption.key = ''|encryption.key = 'hex:$ENCRYPTION_KEY'|g" .env
        else
            # For Linux (GNU sed)
            sed -i "s|JWT_SECRET_KEY = ''|JWT_SECRET_KEY = '$JWT_SECRET'|g" .env
            sed -i "s|encryption.key = ''|encryption.key = 'hex:$ENCRYPTION_KEY'|g" .env
        fi
        echo -e "${GREEN}✓${NC} Updated .env with secure keys"
    fi

    # Update .env.docker
    if [ -f ".env.docker" ]; then
        if [[ "$OSTYPE" == "darwin"* ]]; then
            sed -i '' "s|JWT_SECRET_KEY = ''|JWT_SECRET_KEY = '$JWT_SECRET'|g" .env.docker
            sed -i '' "s|encryption.key = ''|encryption.key = 'hex:$ENCRYPTION_KEY'|g" .env.docker
            sed -i '' "s|MYSQL_ROOT_PASSWORD = CHANGE_THIS_ROOT_PASSWORD|MYSQL_ROOT_PASSWORD = $MYSQL_ROOT_PASSWORD|g" .env.docker
            sed -i '' "s|MYSQL_PASSWORD = CHANGE_THIS_PASSWORD|MYSQL_PASSWORD = $MYSQL_PASSWORD|g" .env.docker
            sed -i '' "s|database.default.password = CHANGE_THIS_PASSWORD|database.default.password = $MYSQL_PASSWORD|g" .env.docker
            sed -i '' "s|database.tests.password = CHANGE_THIS_PASSWORD|database.tests.password = $MYSQL_PASSWORD|g" .env.docker
        else
            sed -i "s|JWT_SECRET_KEY = ''|JWT_SECRET_KEY = '$JWT_SECRET'|g" .env.docker
            sed -i "s|encryption.key = ''|encryption.key = 'hex:$ENCRYPTION_KEY'|g" .env.docker
            sed -i "s|MYSQL_ROOT_PASSWORD = CHANGE_THIS_ROOT_PASSWORD|MYSQL_ROOT_PASSWORD = $MYSQL_ROOT_PASSWORD|g" .env.docker
            sed -i "s|MYSQL_PASSWORD = CHANGE_THIS_PASSWORD|MYSQL_PASSWORD = $MYSQL_PASSWORD|g" .env.docker
            sed -i "s|database.default.password = CHANGE_THIS_PASSWORD|database.default.password = $MYSQL_PASSWORD|g" .env.docker
            sed -i "s|database.tests.password = CHANGE_THIS_PASSWORD|database.tests.password = $MYSQL_PASSWORD|g" .env.docker
        fi
        echo -e "${GREEN}✓${NC} Updated .env.docker with secure keys and passwords"
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
