# CodeIgniter 4 API Starter - Development Context

## Project Overview

A production-ready CodeIgniter 4 REST API starter with JWT authentication, layered architecture, and OpenAPI documentation.

## Architecture

### Layer Structure

```
Request → Controller → Service → Model/Repository → Database
                ↓
            Response
```

### Components

1. **ApiController Base** (`app/Controllers/ApiController.php`)
   - Abstract base for standard CRUD controllers
   - Automatic request data aggregation (GET, POST, JSON, files, route params)
   - Automatic exception handling and response formatting
   - Required abstract methods: `getService()`, `getSuccessStatus()`

2. **Controllers** (`app/Controllers/Api/V1/`)
   - `UserController` - Extends ApiController for CRUD operations
   - `AuthController` - Standalone controller for authentication (login, register, me)
   - All documented with OpenAPI attributes

3. **Services** (`app/Services/`)
   - `UserService` - Business logic for users (CRUD + auth methods)
   - `JwtService` - JWT token generation and validation
   - Returns standardized arrays or throws exceptions

4. **Models** (`app/Models/`)
   - `UserModel` - Handles database operations, validation, timestamps
   - Uses soft deletes
   - Returns UserEntity instances

5. **Entities** (`app/Entities/`)
   - `UserEntity` - Data model with type casting
   - Password hidden from toArray() for security

6. **Filters** (`app/Filters/`)
   - `JwtAuthFilter` - Validates JWT tokens on protected routes
   - Injects userId and userRole into request

## Authentication System

### JWT Implementation

**Service**: `app/Services/JwtService.php`
- Uses `firebase/php-jwt` library
- Token expiration: 1 hour (configurable)
- Payload includes: `uid`, `role`, `iat`, `exp`
- Secret key from environment: `JWT_SECRET_KEY`

**Filter**: `app/Filters/JwtAuthFilter.php`
- Validates Bearer token format
- Returns 401 for missing/invalid tokens
- Injects user data into request object

**Configuration**: `app/Config/Filters.php`
- Registered as 'jwtauth' alias
- Applied to protected route groups

### Password Security

- Bcrypt hashing via `password_hash()`
- Password field excluded from all API responses
- Verification via `password_verify()`

## Database Schema

### Users Table

```sql
CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(100) NOT NULL UNIQUE,
    email VARCHAR(255) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    role VARCHAR(50) NOT NULL DEFAULT 'user',
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    deleted_at DATETIME NULL
);
```

**Migrations**:
- `2026-01-28-014712_CreateUsersTable.php` - Initial table
- `2026-01-28-070454_AddPasswordToUsers.php` - Adds password & role fields

## API Endpoints

### Public Endpoints (No Auth Required)
- `POST /api/v1/auth/login` - User login (returns JWT)
- `POST /api/v1/auth/register` - User registration (returns JWT)

### Protected Endpoints (JWT Required)
- `GET /api/v1/auth/me` - Get current user
- `GET /api/v1/users` - List all users
- `GET /api/v1/users/{id}` - Get user by ID
- `POST /api/v1/users` - Create user
- `PUT /api/v1/users/{id}` - Update user
- `DELETE /api/v1/users/{id}` - Soft delete user

## OpenAPI Documentation

### Configuration

**Base Config**: `app/Config/OpenApi.php`
- OpenAPI version: 3.0.0
- API version: 1.0.0
- Security scheme: JWT Bearer
- Tags: Authentication, Users

### Documentation Locations

All controllers use PHP 8 attributes:
```php
#[OA\Post(
    path: '/api/v1/auth/login',
    summary: 'User login',
    tags: ['Authentication'],
)]
```

### Generation Command

**Command**: `app/Commands/GenerateSwagger.php`
```bash
php spark swagger:generate
```

**Output**: `public/swagger.json`
- Accessible at: http://localhost:8080/swagger.json
- 8 endpoints fully documented
- Request/response schemas
- Authentication requirements

## Environment Configuration

### Required Variables

```env
# Environment
CI_ENVIRONMENT = development

# Application
app.baseURL = 'http://localhost:8080'

# Database
database.default.hostname = 127.0.0.1
database.default.database = ci4_api
database.default.username = root
database.default.password = root
database.default.DBDriver = MySQLi
database.default.port = 3306

# JWT Authentication
JWT_SECRET_KEY = 'change-this-to-a-random-secret-key-in-production'

# Encryption (for sessions, etc)
encryption.key = '32-hex-char-string-here'
```

## Development Workflow

### Adding New Endpoints

1. **Add OpenAPI annotations** to controller method
2. **Run swagger generator**: `php spark swagger:generate`
3. **Test endpoint** with curl or Postman
4. **Verify documentation** at `/swagger.json`

### Adding New Resources

1. **Create migration**: `php spark make:migration CreateTableName`
2. **Create model** in `app/Models/`
3. **Create entity** in `app/Entities/`
4. **Create service** in `app/Services/` (implement RESTful methods)
5. **Create controller** in `app/Controllers/Api/V1/` (extend ApiController)
6. **Add routes** in `app/Config/Routes.php`
7. **Document with OpenAPI** attributes
8. **Generate docs**: `php spark swagger:generate`

### Testing Authentication

```bash
# Register
curl -X POST http://localhost:8080/api/v1/auth/register \
  -H "Content-Type: application/json" \
  -d '{"username":"test","email":"test@example.com","password":"pass123"}'

# Login
curl -X POST http://localhost:8080/api/v1/auth/login \
  -H "Content-Type: application/json" \
  -d '{"username":"test","password":"pass123"}'

# Access protected endpoint
TOKEN="your-jwt-token-here"
curl -X GET http://localhost:8080/api/v1/users \
  -H "Authorization: Bearer $TOKEN"
```

## Dependencies

### Core
- `codeigniter4/framework`: ^4.5
- `php`: ^8.1

### Authentication
- `firebase/php-jwt`: ^7.0 - JWT token handling

### Documentation
- `zircote/swagger-php`: ^6.0 - OpenAPI annotation parsing

## Common Tasks

### Database
```bash
php spark migrate              # Run migrations
php spark migrate:rollback     # Rollback last batch
php spark migrate:status       # Check migration status
php spark db:seed UserSeeder   # Seed initial data (customize UserSeeder first)
```

### Development
```bash
php spark serve                # Start dev server
php spark swagger:generate     # Regenerate API docs
php spark list                 # List all commands
```

### Debugging
```bash
php spark routes               # List all routes
php spark db:table users       # Show table structure
```

## Security Considerations

1. **JWT Secret**: Change `JWT_SECRET_KEY` in production (use long random string)
2. **Password Hashing**: Uses bcrypt (secure by default)
3. **Password Hiding**: Excluded from all API responses
4. **Token Expiration**: 1 hour default (configurable in JwtService)
5. **HTTPS**: Use in production (configure in `.env`)
6. **Rate Limiting**: Consider adding for auth endpoints
7. **Input Validation**: Models handle validation rules

## Known Limitations

1. No refresh token mechanism (single JWT only)
2. No token blacklisting (logout is client-side)
3. No rate limiting implemented
4. No role-based access control (RBAC) enforcement
5. No password reset functionality
6. No email verification

## Future Enhancements

- [ ] Refresh token mechanism
- [ ] Token blacklist for logout
- [ ] Rate limiting on auth endpoints
- [ ] RBAC with permission checks
- [ ] Password reset flow
- [ ] Email verification
- [ ] API versioning strategy
- [ ] Response pagination
- [ ] Request logging
- [ ] API analytics

## Testing Strategy

### Manual Testing
- Use provided curl commands
- Import swagger.json into Postman
- Test all authentication flows
- Verify protected routes reject invalid tokens

### Automated Testing
- PHPUnit configuration in place
- GitHub Actions CI runs on push
- Tests on PHP 8.1, 8.2, 8.3
- MySQL service in CI

## Troubleshooting

### "Authorization header missing"
- Check Bearer token format: `Authorization: Bearer {token}`
- Ensure filter is registered in `app/Config/Filters.php`

### "Invalid or expired token"
- Token may have expired (1 hour lifetime)
- Check JWT_SECRET_KEY matches between encode/decode
- Verify token isn't corrupted

### "Class not found" errors
- Run `composer dump-autoload`
- Check namespaces match directory structure

### Swagger generation fails
- Check PHP 8.0+ (attributes required)
- Verify all annotations use proper syntax
- Check file permissions on `public/`

## Git Workflow

### Branches
- `main` - Production-ready code
- `cc` - Current development branch

### Commits
Include co-author tag:
```
feat: Add JWT authentication

Co-Authored-By: Claude Sonnet 4.5 <noreply@anthropic.com>
```

## Project Status

### Completed Phases
- ✅ Phase 0: Initialization
- ✅ Phase 1: Architecture (ApiController base)
- ✅ Phase 2: JWT Authentication & Roles
- ✅ Phase 4: Swagger/OpenAPI Documentation

### Pending Phases
- ⏳ Phase 3: Response formatting (standard structure)
- ⏳ Phase 5: Docker configuration
- ⏳ Phase 6: Testing setup
- ⏳ Phase 7: CI/CD pipeline
- ⏳ Phase 8: Security hardening
- ⏳ Phase 9: Release preparation

## Key Files Reference

- `app/Controllers/ApiController.php` - Base controller
- `app/Services/JwtService.php` - JWT operations
- `app/Filters/JwtAuthFilter.php` - Auth filter
- `app/Commands/GenerateSwagger.php` - Doc generator
- `app/Config/OpenApi.php` - OpenAPI config
- `app/Config/Routes.php` - Route definitions
- `app/Config/Filters.php` - Filter configuration
- `public/swagger.json` - Generated API documentation
