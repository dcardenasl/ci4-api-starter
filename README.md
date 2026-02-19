# CodeIgniter 4 API Starter Kit

![PHP Version](https://img.shields.io/badge/PHP-8.1%20%7C%208.2%20%7C%208.3-blue)
![CodeIgniter](https://img.shields.io/badge/CodeIgniter-4.6-orange)
![Tests](https://img.shields.io/badge/tests-passing-success)
![License](https://img.shields.io/badge/license-MIT-blue)

English | [Español](README.es.md)

A production-ready REST API starter template for CodeIgniter 4 with JWT authentication, clean layered architecture, and comprehensive test coverage.

## Features

- **JWT Authentication** - Access tokens, refresh tokens, and revocation
- **Role-Based Access** - Admin and user roles with middleware protection
- **Email System** - Verification, password reset, queue support
- **File Management** - Upload/download with cloud storage support (S3)
- **Advanced Querying** - Pagination, filtering, searching, sorting
- **Health Checks** - Kubernetes-ready endpoints (`/health`, `/ready`, `/live`)
- **Audit Trail** - Automatic logging of data changes
- **OpenAPI Documentation** - Auto-generated Swagger docs
- **Comprehensive Test Suite** - Unit, integration, and feature tests

## Quick Start

### Option 1: Use GitHub Template (Recommended)

1. Click **"Use this template"** at the top of this page
2. Clone your new repository
3. Run the initialization script:

```bash
chmod +x init.sh && ./init.sh
```

Your API will be running at `http://localhost:8080`

### Option 2: Manual Setup

```bash
# Install dependencies
composer install

# Configure environment
cp .env.example .env

# Generate security keys
openssl rand -base64 64  # Add to JWT_SECRET_KEY in .env
php spark key:generate   # Shows encryption key

# Setup database (configure .env first)
php spark migrate

# Start server
php spark serve
```

## API Endpoints

### Authentication (Public)
```
POST /api/v1/auth/register     Register new user
POST /api/v1/auth/login        Login (returns tokens)
POST /api/v1/auth/refresh      Refresh access token
POST /api/v1/auth/forgot-password   Request password reset
POST /api/v1/auth/reset-password    Reset password
GET  /api/v1/auth/validate-reset-token Validate reset token
GET  /api/v1/auth/verify-email      Verify email address (token in query)
```

### Email Verification (Optional)

Set `AUTH_REQUIRE_EMAIL_VERIFICATION` in `.env` to control whether email verification is required before login/refresh/protected routes. Default is `true`.

### Authentication (Protected)
```
GET  /api/v1/auth/me           Get current user
POST /api/v1/auth/revoke       Revoke current token
POST /api/v1/auth/revoke-all   Revoke all user tokens
POST /api/v1/auth/resend-verification Resend verification email
```

### Users (Protected)
```
GET    /api/v1/users           List users (paginated, filterable)
GET    /api/v1/users/{id}      Get user by ID
POST   /api/v1/users           Create user (admin only)
PUT    /api/v1/users/{id}      Update user (admin only)
DELETE /api/v1/users/{id}      Soft delete user (admin only)
POST   /api/v1/users/{id}/approve Approve user (admin only)
```

### Files (Protected)
```
GET    /api/v1/files           List user's files
POST   /api/v1/files/upload    Upload file
GET    /api/v1/files/{id}      Get file details
DELETE /api/v1/files/{id}      Delete file
```

### API Keys (Admin)
```
GET    /api/v1/api-keys           List API keys
GET    /api/v1/api-keys/{id}      Get API key details
POST   /api/v1/api-keys           Create API key
PUT    /api/v1/api-keys/{id}      Update API key
DELETE /api/v1/api-keys/{id}      Delete API key
```

### Metrics and Audit (Admin)
```
GET  /api/v1/metrics                 Get metrics overview
GET  /api/v1/metrics/requests        Get recent request metrics
GET  /api/v1/metrics/slow-requests   Get slow requests
GET  /api/v1/metrics/custom/{metric} Get custom metric values
POST /api/v1/metrics/record          Record custom metric
GET  /api/v1/audit                   List audit logs
GET  /api/v1/audit/{id}              Get audit log detail
GET  /api/v1/audit/entity/{type}/{id} Get audit by entity
```

### Health (Public)
```
GET /health    Full system health check
GET /ping      Simple uptime check
GET /ready     Kubernetes readiness probe
GET /live      Kubernetes liveness probe
```

## Usage Examples

**Register:**
```bash
curl -X POST http://localhost:8080/api/v1/auth/register \
  -H "Content-Type: application/json" \
  -d '{"email":"john@example.com","first_name":"John","last_name":"Doe","password":"SecurePass123!"}'
```

Response note: self-registration creates a `pending_approval` account. Login is available only after admin approval.

**Admin creates user (invitation flow):**
```bash
curl -X POST http://localhost:8080/api/v1/users \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer ADMIN_ACCESS_TOKEN" \
  -d '{"email":"invitee@example.com","first_name":"Invitee","last_name":"User","role":"user"}'
```
Admin does not provide a password. The system generates it internally and sends an invitation email so the user can set their own password.

**Login:**
```bash
curl -X POST http://localhost:8080/api/v1/auth/login \
  -H "Content-Type: application/json" \
  -d '{"email":"john@example.com","password":"SecurePass123!"}'
```

**Use protected endpoint:**
```bash
curl -X GET http://localhost:8080/api/v1/users \
  -H "Authorization: Bearer YOUR_ACCESS_TOKEN"
```

**Query with filters:**
```bash
curl -X GET "http://localhost:8080/api/v1/users?filter[role][eq]=admin&search=john&page=1&limit=10" \
  -H "Authorization: Bearer YOUR_ACCESS_TOKEN"
```

## Interactive Docs and Postman

**Local Swagger UI:**
```bash
# Generate/update OpenAPI spec
php spark swagger:generate

# Run Swagger UI with Docker
docker run --rm -p 8081:8080 \
  -e SWAGGER_JSON=/swagger.json \
  -v "$(pwd)/public/swagger.json:/swagger.json" \
  swaggerapi/swagger-ui
```
Open `http://localhost:8081`.
Generated file: `public/swagger.json` (served at `http://localhost:8080/swagger.json`)

**Embedded Swagger UI (no Docker):**
- File: `public/docs/index.html`
- Open `http://localhost:8080/docs/`

**Postman:**
- Collection (full API): `docs/postman/ci4-api.postman_collection.json`
  Variables are stored at the collection level (`baseUrl`, `accessToken`, `refreshToken`, `userId`, `fileId`).
- Optional environment: `docs/postman/ci4-api.postman_environment.json`

## Project Structure

```
app/
├── Controllers/
│   ├── ApiController.php          # Base controller
│   └── Api/V1/                    # API v1 controllers
├── Services/                      # Business logic
├── Interfaces/                    # Service interfaces
├── Models/                        # Database models
├── Entities/                      # Data entities
├── Filters/                       # HTTP filters (auth, throttle, cors)
├── Exceptions/                    # Custom exceptions
├── Libraries/
│   ├── ApiResponse.php           # Standardized responses
│   └── Query/                    # Query builder utilities
└── Traits/                       # Model traits (Filterable, Searchable)

tests/
├── Unit/                         # No database required
│   ├── Libraries/                # ApiResponse tests
│   └── Services/                 # Service unit tests
├── Integration/                  # Database required
│   ├── Models/                   # Model tests
│   └── Services/                 # Service integration tests
└── Feature/                      # Full HTTP tests
    └── Controllers/              # Endpoint tests
```

## Testing

```bash
# Run all tests
vendor/bin/phpunit

# Run with readable output
vendor/bin/phpunit --testdox

# Run specific suites
vendor/bin/phpunit tests/Unit           # Fast, no DB
vendor/bin/phpunit tests/Integration    # Needs DB
vendor/bin/phpunit tests/Feature        # HTTP tests
```

## Advanced Query Features

### Pagination
```
GET /api/v1/users?page=2&limit=20
```

### Filtering
```
GET /api/v1/users?filter[role][eq]=admin
GET /api/v1/users?filter[email][like]=%@gmail.com
GET /api/v1/users?filter[created_at][gt]=2024-01-01
```

**Operators:** `eq`, `neq`, `gt`, `gte`, `lt`, `lte`, `like`, `in`

### Searching
```
GET /api/v1/users?search=john
```

### Sorting
```
GET /api/v1/users?sort=created_at&direction=desc
```

### Combined
```
GET /api/v1/users?search=john&filter[role][eq]=user&sort=created_at&direction=desc&page=1&limit=10
```

## Configuration

### Required (.env)
```env
JWT_SECRET_KEY=your-secret-key-min-32-chars
encryption.key=hex2bin:your-encryption-key
database.default.hostname=localhost
database.default.database=your_database
database.default.username=root
database.default.password=
```

### Optional (.env)
```env
# JWT
JWT_ACCESS_TOKEN_TTL=3600
JWT_REFRESH_TOKEN_TTL=604800

# Email
EMAIL_FROM_ADDRESS=noreply@example.com
EMAIL_SMTP_HOST=smtp.example.com

# File Storage
STORAGE_DRIVER=local
FILE_MAX_SIZE=10485760

# Rate Limiting
THROTTLE_LIMIT=60
THROTTLE_WINDOW=60

# CORS
CORS_ALLOWED_ORIGINS=http://localhost:3000,https://app.example.com
CORS_SUPPORTS_CREDENTIALS=false

# Observability SLO
SLO_API_P95_TARGET_MS=500
```

## Docker

```bash
docker-compose up -d

# API: http://localhost:8080
# MySQL: localhost:3306
# Adminer: http://localhost:8081
```

## Security Features

- JWT with JTI for individual token revocation
- Bcrypt password hashing
- Timing-attack protection on login
- Passwords never exposed in responses
- Input sanitization (XSS prevention)
- SQL injection protection (query builder)
- Rate limiting
- Soft deletes

### Secret Rotation

Rotate security secrets regularly to maintain security posture.

**When to Rotate:**
- After a security breach or suspected compromise
- Every 90 days (recommended for JWT secrets)
- When a developer with access leaves the team
- Before initial production deployment

**How to Rotate JWT Secret:**
```bash
# 1. Generate new secret (64+ characters recommended)
openssl rand -base64 64

# 2. Update .env file
JWT_SECRET_KEY='<paste-new-secret-here>'

# 3. Restart application
# All existing tokens will be invalidated - users must login again
```

**How to Rotate Encryption Key:**
```bash
# 1. Generate new key
openssl rand -hex 32

# 2. Update .env file
encryption.key=hex2bin:<paste-new-key-here>

# 3. Restart application
# Note: Existing encrypted data may become unreadable
```

**⚠️ Important Notes:**
- Rotating JWT secret invalidates all active tokens immediately
- Rotating encryption key may invalidate encrypted session data
- Always test secret rotation in staging environment first
- Keep old secrets for 24-48 hours in case of rollback needs
- Document rotation date and reason for audit trail

## Requirements

- PHP 8.1+
- MySQL 8.0+
- Composer 2.x
- Extensions: mysqli, mbstring, intl, json

## Documentation

- **ARCHITECTURE.md** - Architectural decisions and design patterns explained
- **CLAUDE.md** - Development guide for AI assistants (Claude Code)
- **.claude/agents/** - Specialized Claude Code agent for CRUD generation
- **public/swagger.json** - OpenAPI documentation (generate with `php spark swagger:generate`)

**New to the project?** Start with `ARCHITECTURE.md` to understand why code is structured the way it is.

### AI-Assisted Development

This template includes a specialized [Claude Code](https://claude.ai/code) agent that acts as an expert architect for this project. When using Claude Code, the agent automatically helps you:
- Create complete CRUD resources following all architectural patterns
- Generate migrations, entities, models, services, controllers, and tests
- Maintain consistency with existing code conventions
- Follow security and testing best practices

See `.claude/README.md` for details on using the agent.

## License

MIT License

## Contributing

1. Fork the repository
2. Create feature branch (`git checkout -b feature/improvement`)
3. Commit changes (`git commit -m 'Add improvement'`)
4. Push to branch (`git push origin feature/improvement`)
5. Open Pull Request
