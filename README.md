# CI4 API Starter

A CodeIgniter 4 REST API starter project with layered architecture (Controller → Service → Repository → Entity).

## Features

- Clean layered architecture
- RESTful API with JSON responses
- MySQL database with migrations
- GitHub Actions CI/CD
- CRUD operations for User resource
- Environment-based configuration

## Requirements

- PHP 8.1 or higher
- MySQL 8.0 or higher
- Composer
- PHP Extensions:
  - mysqli
  - mbstring
  - intl
  - json

## Installation

1. Clone the repository:
```bash
git clone <repository-url>
cd ci4-api-starter
```

2. Install dependencies:
```bash
composer install
```

3. Configure environment:
```bash
cp env .env
```

Edit `.env` and configure your database settings:
```
CI_ENVIRONMENT = development

app.baseURL = 'http://localhost:8080'

database.default.hostname = 127.0.0.1
database.default.database = ci4_api
database.default.username = root
database.default.password = root
database.default.DBDriver = MySQLi
database.default.port = 3306
database.default.charset = utf8mb4
database.default.DBCollat = utf8mb4_general_ci
```

4. Create the database:
```bash
php setup_mysql.php
```

Or manually:
```sql
CREATE DATABASE ci4_api;
CREATE DATABASE ci4_test;
```

5. Run migrations:
```bash
php spark migrate
```

6. (Optional) Seed the database with sample data:
```bash
php spark db:seed UserSeeder
```

## Usage

Start the development server:
```bash
php spark serve
```

The API will be available at `http://localhost:8080`

## API Endpoints

### Users

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/api/v1/users` | Get all users |
| GET | `/api/v1/users/{id}` | Get user by ID |
| POST | `/api/v1/users` | Create new user |
| PUT | `/api/v1/users/{id}` | Update user |
| DELETE | `/api/v1/users/{id}` | Delete user |

### Example Requests

**Get all users:**
```bash
curl http://localhost:8080/api/v1/users
```

**Get user by ID:**
```bash
curl http://localhost:8080/api/v1/users/1
```

**Create a new user:**
```bash
curl -X POST http://localhost:8080/api/v1/users \
  -H "Content-Type: application/json" \
  -d '{"username":"john_doe","email":"john@example.com"}'
```

**Update a user:**
```bash
curl -X PUT http://localhost:8080/api/v1/users/1 \
  -H "Content-Type: application/json" \
  -d '{"username":"john_updated","email":"john.updated@example.com"}'
```

**Delete a user:**
```bash
curl -X DELETE http://localhost:8080/api/v1/users/1
```

## Project Structure

```
app/
├── Controllers/
│   └── Api/
│       └── V1/
│           └── UserController.php    # API endpoints
├── Services/
│   └── UserService.php               # Business logic
├── Repositories/
│   └── UserRepository.php            # Data access layer
├── Entities/
│   └── UserEntity.php                # Data model
├── Database/
│   ├── Migrations/
│   │   └── *_CreateUsersTable.php
│   └── Seeds/
│       └── UserSeeder.php
└── Config/
    ├── Database.php                  # Database configuration
    └── Routes.php                    # API routes
```

## Architecture

This project follows a layered architecture pattern:

1. **Controller Layer** (`app/Controllers/Api/V1/`)
   - Handles HTTP requests and responses
   - Validates input
   - Delegates to Service layer

2. **Service Layer** (`app/Services/`)
   - Contains business logic
   - Orchestrates operations
   - Delegates data access to Repository layer

3. **Repository Layer** (`app/Repositories/`)
   - Handles database operations
   - Query builder usage
   - Returns Entity objects

4. **Entity Layer** (`app/Entities/`)
   - Data models
   - Type casting and date handling

## Database Commands

Check migration status:
```bash
php spark migrate:status
```

Run migrations:
```bash
php spark migrate
```

Rollback migrations:
```bash
php spark migrate:rollback
```

View table structure:
```bash
php spark db:table users
```

Seed database:
```bash
php spark db:seed UserSeeder
```

## Testing

Run tests (when configured):
```bash
vendor/bin/phpunit
```

The project includes GitHub Actions CI that automatically:
- Tests on PHP 8.1, 8.2, and 8.3
- Sets up MySQL service
- Runs migrations
- Executes tests

## Development

### Creating New Migrations

```bash
php spark make:migration CreateTableName
```

### Creating New Seeders

```bash
php spark make:seeder TableNameSeeder
```

### Creating New Models

Follow the layered architecture:
1. Create Entity in `app/Entities/`
2. Create Repository in `app/Repositories/`
3. Create Service in `app/Services/`
4. Create Controller in `app/Controllers/Api/V1/`
5. Add routes in `app/Config/Routes.php`

## License

This project is open-sourced software licensed under the MIT license.

## About CodeIgniter

CodeIgniter is a PHP full-stack web framework that is light, fast, flexible and secure.
More information can be found at the [official site](https://codeigniter.com).
