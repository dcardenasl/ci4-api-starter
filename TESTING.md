# Testing Guide

## Overview

This project includes comprehensive PHPUnit tests covering Controllers, Services, and Models. All tests follow CodeIgniter 4 testing best practices.

## Test Suite Summary

Total: **49 tests** with **166 assertions** - All Passing ✓

### Test Coverage by Layer

1. **AuthControllerTest** (12 tests)
   - Register endpoint (success, validation errors, duplicate users)
   - Login endpoint (success, invalid credentials, missing fields, email login)
   - Me endpoint (authenticated, missing token, invalid token)
   - JWT token payload verification

2. **UserServiceTest** (20 tests)
   - CRUD operations (index, show, store, update, destroy)
   - Login/Register with password hashing
   - Validation error handling
   - Missing field detection

3. **UserModelTest** (17 tests)
   - Database CRUD operations
   - Validation rules enforcement
   - Soft deletes verification
   - Timestamps management
   - Field protection (allowedFields)
   - Entity conversion

## Running Tests

### Run All Tests
```bash
vendor/bin/phpunit
```

### Run Without Coverage
```bash
vendor/bin/phpunit --no-coverage
```

### Run Specific Test Suite
```bash
vendor/bin/phpunit tests/Controllers/AuthControllerTest.php
vendor/bin/phpunit tests/Services/UserServiceTest.php
vendor/bin/phpunit tests/Models/UserModelTest.php
```

### Run Specific Test Method
```bash
vendor/bin/phpunit --filter testLoginSuccess tests/Controllers/AuthControllerTest.php
```

## Test Structure

```
tests/
├── Controllers/
│   └── AuthControllerTest.php       # API endpoint tests
├── Services/
│   └── UserServiceTest.php          # Business logic tests
├── Models/
│   └── UserModelTest.php            # Database layer tests
└── _support/
    ├── Database/
    │   └── Seeds/
    │       └── TestUserSeeder.php   # Test data seeder
    └── Traits/
        ├── AuthenticationTrait.php  # JWT token helpers
        └── DatabaseTestTrait.php    # Database assertions
```

## Test Helpers

### AuthenticationTrait

Provides JWT authentication helpers for testing protected endpoints:

```php
// Generate JWT token
$token = $this->getJwtToken($userId = 1, $role = 'user');

// Get authorization headers
$headers = $this->getAuthHeaders($userId = 1, $role = 'user');

// Login user and get token
$token = $this->loginUser('testuser', 'testpass123');
```

### DatabaseTestTrait

Provides database assertion helpers:

```php
// Assert record exists
$this->assertDatabaseHas('users', ['username' => 'testuser']);

// Assert record doesn't exist
$this->assertDatabaseMissing('users', ['username' => 'deleteduser']);

// Seed database
$this->seedDatabase();
```

## Test Users

The `TestUserSeeder` creates two test users:

1. **Regular User**
   - Username: `testuser`
   - Email: `test@example.com`
   - Password: `testpass123`
   - Role: `user`

2. **Admin User**
   - Username: `adminuser`
   - Email: `admin@example.com`
   - Password: `adminpass123`
   - Role: `admin`

## Configuration

Tests use a separate database configured in `phpunit.xml`:

```xml
<env name="database.tests.DBDriver" value="MySQLi"/>
<env name="database.tests.database" value="ci4_test"/>
```

Key test settings:
- Environment: `testing`
- Migrations: Run automatically before each test
- Database: Reset and seeded for each test class
- JWT Secret: Test-specific key (not production key)

## Writing New Tests

### Controller Tests

```php
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\FeatureTestTrait;
use CodeIgniter\Test\DatabaseTestTrait;
use Tests\Support\Traits\AuthenticationTrait;

class YourControllerTest extends CIUnitTestCase
{
    use FeatureTestTrait;
    use DatabaseTestTrait;
    use AuthenticationTrait;

    protected $migrate = true;
    protected $refresh = true;

    public function testYourEndpoint()
    {
        $response = $this->withBodyFormat('json')
            ->post('/api/v1/endpoint', ['data' => 'value']);

        $response->assertStatus(200);
        $response->assertJSONFragment(['success' => true]);
    }
}
```

### Service Tests

```php
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;

class YourServiceTest extends CIUnitTestCase
{
    use DatabaseTestTrait;

    protected $migrate = true;
    protected $refresh = true;

    public function testServiceMethod()
    {
        $service = new YourService(new YourModel());
        $result = $service->yourMethod(['param' => 'value']);

        $this->assertArrayHasKey('status', $result);
        $this->assertEquals('success', $result['status']);
    }
}
```

### Model Tests

```php
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;

class YourModelTest extends CIUnitTestCase
{
    use DatabaseTestTrait;

    protected $migrate = true;
    protected $refresh = true;

    public function testModelMethod()
    {
        $model = new YourModel();
        $result = $model->find(1);

        $this->assertInstanceOf(YourEntity::class, $result);
    }
}
```

## Important Notes

### JSON Response Parsing

CodeIgniter's `$response->getJSON()` returns a string. To access as object:

```php
$json = json_decode($response->getJSON());
$this->assertEquals('value', $json->data->field);
```

### Validation Rules

Username validation requires `alpha_numeric` (letters and numbers only, no underscores or special characters):

```php
// Valid
$data = ['username' => 'testuser123'];

// Invalid - contains underscore
$data = ['username' => 'test_user'];
```

### Code Coverage

To generate code coverage reports, install Xdebug or PCOV:

```bash
# Install Xdebug (development only)
pecl install xdebug

# Generate coverage report
vendor/bin/phpunit --coverage-html coverage
```

## Continuous Integration

Tests can be integrated into CI/CD pipelines:

```yaml
# Example GitHub Actions workflow
- name: Run Tests
  run: vendor/bin/phpunit --no-coverage
```

## Next Steps

- Add integration tests for API workflows
- Implement performance/load tests
- Add mutation testing with Infection PHP
- Set up code coverage monitoring
