# Testing Guide - CI4 API Starter

## Philosophy: Test Behavior, Not Implementation

> **Golden Rule:** Ask yourself "What value does this test provide?" before writing any test.

This guide helps you write high-quality tests that prevent regressions without creating maintenance burden.

---

## Quick Decision Tree: Should I Write This Test?

```
┌─ Does this test verify business logic specific to our project?
│  ├─ YES → ✅ Write the test
│  └─ NO → Continue...
│
├─ Does this test verify security (auth, ownership, token validation)?
│  ├─ YES → ✅ Write the test
│  └─ NO → Continue...
│
├─ Is this testing framework behavior (JWT structure, array keys exist)?
│  ├─ YES → ❌ Don't write this test
│  └─ NO → Continue...
│
├─ Is this an impossible edge case (user_id=0, PHP_INT_MAX)?
│  ├─ YES → ❌ Don't write this test
│  └─ NO → Continue...
│
├─ Can this be consolidated with data providers?
│  ├─ YES → ✅ Write as data provider test
│  └─ NO → ✅ Write the test
```

---

## Test Pyramid

Our testing strategy follows a balanced pyramid:

```
           /\
          /  \  Controller Tests (HTTP)
         /----\  ~80 tests - Endpoints, auth, status codes
        /      \
       /  Inte  \ Integration Tests (Services)
      /   gra   \ ~180 tests - Business logic + DB
     /    tion   \
    /------------\
   /              \
  /  Unit + Model \ Unit + Model Tests
 /      Tests      \ ~200 tests - Logic + DB operations
/------------------\
```

### Layer Responsibilities

| Layer | What to Test | What NOT to Test | Example |
|-------|--------------|------------------|---------|
| **Unit** | Business logic isolated with mocks | DB operations, HTTP requests | `testPasswordHashIsSecure()` |
| **Model** | Database queries, constraints, relations | Business logic | `testFindValidTokenExcludesRevoked()` |
| **Integration** | Complete flows, side effects, multi-component | Implementation details | `testRefreshTokenRotationFlow()` |
| **Controller** | HTTP status codes, JSON structure, auth | Service logic (already tested) | `testLoginReturns401WithInvalidCredentials()` |

---

## Test Naming Convention

**Format:** `test[Method][Scenario][ExpectedResult]()`

### ✅ Good Examples

```php
// Clear, specific, describes outcome
testLoginWithValidCredentialsReturnsToken()
testLoginWithInvalidCredentialsThrowsAuthenticationException()
testUpdateUserRequiresAdminRoleForOtherUsers()
testUploadRejectsFilesLargerThan10MB()
testRevokeTokenMarksTokenAsRevokedInDatabase()
```

### ❌ Bad Examples

```php
// Too vague
testLogin()
testUpdate()
testStore()

// Implementation details
testLoginCallsUserModel()
testUpdateUsesTransactions()

// No expected result
testLoginWithInvalidCredentials() // Returns what?
```

---

## What to Test (and What NOT to Test)

### ✅ DO Test

1. **Business Logic Specific to the Project**
   ```php
   // ✅ Tests our token rotation implementation
   testRefreshTokenRotatesAndRevokesOldToken()

   // ✅ Tests our ownership enforcement
   testDownloadFileEnforcesOwnership()
   ```

2. **Security Flows**
   ```php
   // ✅ Critical security test
   testUserCannotEscalateOwnRoleToAdmin()

   // ✅ Prevents unauthorized access
   testRevokedTokenIsRejectedByAuthFilter()
   ```

3. **Realistic Error Scenarios**
   ```php
   // ✅ Can happen in production
   testUploadHandlesStorageFailure()

   // ✅ Common user mistake
   testLoginReturnsErrorForExpiredAccount()
   ```

4. **Edge Cases That Can Actually Occur**
   ```php
   // ✅ User might upload 10.1MB file
   testUploadRejectsFileExceedingMaxSize()

   // ✅ Email might have unusual format
   testRegistrationAcceptsEmailWithPlusSign()
   ```

### ❌ DON'T Test

1. **Framework Behavior**
   ```php
   // ❌ Testing JWT library
   testJwtTokenContainsThreeParts()
   testJwtCanBeBase64Decoded()

   // ❌ Testing CodeIgniter validation
   testValidationReturnsErrorArray()
   ```

2. **Impossible Edge Cases**
   ```php
   // ❌ Database constraints prevent user_id=0
   testUploadHandlesZeroUserId()

   // ❌ PHP_INT_MAX will never occur
   testIssueRefreshTokenHandlesLargeUserId()

   // ❌ Valid tokens are always 64 chars
   testRevokeHandlesLongToken()
   ```

3. **Response Structure Repeatedly**
   ```php
   // ❌ Test ApiResponse once, not in every service
   testUploadReturnsCorrectErrorFormat()
   testDownloadReturnsCorrectSuccessFormat()
   ```

4. **PHP Built-in Functions**
   ```php
   // ❌ PHP handles type coercion
   testDownloadHandlesStringIds()

   // ❌ PHP's password_hash works
   testPasswordHashReturnsString()
   ```

---

## Using Data Providers for Validation Tests

### ❌ Before: 5 Repetitive Tests

```php
public function testUploadRequiresFile(): void
{
    $result = $this->service->upload(['user_id' => 1]);
    $this->assertEquals('error', $result['status']);
    $this->assertArrayHasKey('file', $result['errors']);
}

public function testUploadRequiresUserId(): void
{
    $mockFile = $this->createMock(UploadedFile::class);
    $result = $this->service->upload(['file' => $mockFile]);
    $this->assertEquals('error', $result['status']);
    $this->assertArrayHasKey('user_id', $result['errors']);
}

public function testUploadValidatesFileObject(): void
{
    $result = $this->service->upload(['file' => 'not-a-file', 'user_id' => 1]);
    $this->assertEquals('error', $result['status']);
    $this->assertArrayHasKey('file', $result['errors']);
}

// ... 2 more similar tests
```

### ✅ After: 1 Test with Data Provider

```php
/**
 * @dataProvider invalidUploadDataProvider
 */
public function testUploadValidatesRequiredParameters($data, string $expectedErrorField): void
{
    $result = $this->service->upload($data);
    $this->assertErrorResponse($result, $expectedErrorField);
}

public static function invalidUploadDataProvider(): array
{
    $mockFile = (new \ReflectionClass(UploadedFile::class))->newInstanceWithoutConstructor();

    return [
        'missing file' => [
            ['user_id' => 1],
            'file',
        ],
        'missing user_id' => [
            ['file' => $mockFile],
            'user_id',
        ],
        'empty user_id' => [
            ['file' => $mockFile, 'user_id' => ''],
            'user_id',
        ],
        'invalid file type' => [
            ['file' => 'not-a-file-object', 'user_id' => 1],
            'file',
        ],
    ];
}
```

**Benefits:**
- 5 tests → 1 test method with 4 cases
- Easier to add new validation scenarios
- Clear labeling of each test case
- Reduced code duplication by 80%

---

## Custom Assertions

Use the `CustomAssertionsTrait` for consistent, readable assertions.

### Available Helpers

```php
use Tests\Support\Traits\CustomAssertionsTrait;

class MyServiceTest extends CIUnitTestCase
{
    use CustomAssertionsTrait;

    public function testSomething(): void
    {
        $result = $this->service->someMethod();

        // Assert success response
        $this->assertSuccessResponse($result);
        $this->assertSuccessResponse($result, 'token'); // Check specific data key

        // Assert error response
        $this->assertErrorResponse($result);
        $this->assertErrorResponse($result, 'email'); // Check specific error field

        // Assert validation errors
        $this->assertValidationErrorResponse($result, ['email', 'password']);

        // Assert paginated response
        $this->assertPaginatedResponse($result);

        // Assert error with HTTP code
        $this->assertErrorResponseWithCode($result, 404);

        // Assert response message
        $this->assertResponseMessage($result, 'User created successfully');
        $this->assertResponseMessageContains($result, 'created');

        // Assert data properties
        $this->assertEmptyDataResponse($result);
        $this->assertDataCount($result, 5);
    }
}
```

### Benefits

- **Consistency:** All tests check responses the same way
- **Readability:** `assertSuccessResponse($result)` vs 3 lines of assertions
- **Maintainability:** Change assertion logic in one place
- **Better errors:** Custom messages on failure

---

## Examples: Good vs Bad Tests

### Example 1: Token Validation

```php
// ❌ BAD: Tests JWT library behavior
public function testJwtTokenHasThreeParts(): void
{
    $token = $this->jwtService->encode(1, 'user');
    $parts = explode('.', $token);
    $this->assertCount(3, $parts);
}

// ✅ GOOD: Tests our business logic
public function testJwtTokenContainsCorrectUserData(): void
{
    $userId = 42;
    $role = 'admin';

    $token = $this->jwtService->encode($userId, $role);
    $payload = $this->jwtService->decode($token);

    $this->assertEquals($userId, $payload['uid']);
    $this->assertEquals($role, $payload['role']);
}
```

### Example 2: Validation Testing

```php
// ❌ BAD: 4 separate tests for individual fields
public function testRegisterRequiresUsername() { /* ... */ }
public function testRegisterRequiresEmail() { /* ... */ }
public function testRegisterRequiresPassword() { /* ... */ }
public function testRegisterValidatesEmailFormat() { /* ... */ }

// ✅ GOOD: 1 test with data provider
/**
 * @dataProvider invalidRegistrationDataProvider
 */
public function testRegisterValidatesRequiredFields($data, $expectedErrors): void
{
    $result = $this->service->register($data);
    $this->assertValidationErrorResponse($result, $expectedErrors);
}

public static function invalidRegistrationDataProvider(): array
{
    return [
        'missing username' => [
            ['email' => 'test@example.com', 'password' => 'Pass123'],
            ['username'],
        ],
        'missing email' => [
            ['username' => 'testuser', 'password' => 'Pass123'],
            ['email'],
        ],
        'invalid email format' => [
            ['username' => 'testuser', 'email' => 'invalid', 'password' => 'Pass123'],
            ['email'],
        ],
    ];
}
```

### Example 3: Edge Cases

```php
// ❌ BAD: Impossible edge case
public function testUploadHandlesZeroUserId(): void
{
    $mockFile = $this->createMock(UploadedFile::class);
    $result = $this->service->upload(['file' => $mockFile, 'user_id' => 0]);
    // user_id=0 violates DB constraints - this can never happen
}

// ✅ GOOD: Realistic edge case
public function testUploadRejectsFileExceedingMaxSize(): void
{
    $mockFile = $this->createMock(UploadedFile::class);
    $mockFile->method('getSize')->willReturn(11 * 1024 * 1024); // 11MB

    $result = $this->service->upload(['file' => $mockFile, 'user_id' => 1]);

    $this->assertErrorResponse($result, 'file');
    $this->assertResponseMessageContains($result, 'too large');
}
```

### Example 4: Security Testing

```php
// ❌ BAD: Implementation detail
public function testDeleteCallsModelWithCorrectId(): void
{
    $this->mockModel->expects($this->once())
        ->method('delete')
        ->with(1);

    $this->service->delete(['id' => 1, 'user_id' => 1]);
}

// ✅ GOOD: Security enforcement
public function testDeleteEnforcesFileOwnership(): void
{
    // User 2 trying to delete User 1's file
    $this->mockFileModel->method('getByIdAndUser')
        ->with(1, 2) // fileId=1, userId=2
        ->willReturn(null); // Ownership check fails

    $result = $this->service->delete(['id' => 1, 'user_id' => 2]);

    $this->assertErrorResponseWithCode($result, 404);
}
```

---

## Running Tests Efficiently

### By Layer (Fast Feedback)

```bash
# Unit tests only (fastest, no DB)
vendor/bin/phpunit tests/unit/

# Model tests (with DB)
vendor/bin/phpunit tests/Models/

# Integration tests (full stack)
vendor/bin/phpunit tests/Services/

# Controller tests (HTTP)
vendor/bin/phpunit tests/Controllers/
```

### Specific Test or Method

```bash
# Single test file
vendor/bin/phpunit tests/unit/Services/RefreshTokenServiceTest.php

# Single test method
vendor/bin/phpunit --filter testIssueRefreshTokenGeneratesValidToken

# Test class
vendor/bin/phpunit --filter RefreshTokenServiceTest
```

### Human-Readable Output

```bash
# With descriptions
vendor/bin/phpunit --testdox

# Specific file with descriptions
vendor/bin/phpunit tests/unit/Services/FileServiceTest.php --testdox
```

### Coverage Reports

```bash
# HTML coverage report
vendor/bin/phpunit --coverage-html tests/coverage/html

# Text coverage for specific directory
vendor/bin/phpunit --coverage-text --coverage-filter app/Services
```

---

## Test Organization Best Practices

### File Structure

```
tests/
├── unit/                    # Fast, isolated tests with mocks
│   └── Services/           # Service logic without DB
├── Models/                  # Database operations
├── Services/                # Integration tests (service + DB)
├── Controllers/             # HTTP endpoint tests
├── _support/
│   ├── Traits/             # Reusable test helpers
│   │   ├── CustomAssertionsTrait.php
│   │   └── AuthenticationTrait.php
│   └── Database/
│       └── Seeds/          # Test data seeders
```

### Test Class Structure

```php
class UserServiceTest extends CIUnitTestCase
{
    use CustomAssertionsTrait;

    protected UserService $service;
    protected UserModel $mockModel;

    protected function setUp(): void
    {
        parent::setUp();
        // Initialize mocks and service
    }

    // ==================== CREATE TESTS ====================
    public function testCreateWithValidData(): void { /* ... */ }

    /**
     * @dataProvider invalidCreateDataProvider
     */
    public function testCreateValidatesRequiredFields(): void { /* ... */ }

    // ==================== UPDATE TESTS ====================
    public function testUpdateWithValidData(): void { /* ... */ }

    // ==================== SECURITY TESTS ====================
    public function testUpdateEnforcesOwnership(): void { /* ... */ }

    // ==================== DATA PROVIDERS ====================
    public static function invalidCreateDataProvider(): array { /* ... */ }
}
```

---

## Common Pitfalls to Avoid

### 1. Over-Mocking

```php
// ❌ BAD: Mocking everything
$this->mockModel->expects($this->once())->method('find')->willReturn($user);
$this->mockModel->expects($this->once())->method('save')->willReturn(true);
// ... 10 more expectations

// ✅ GOOD: Use integration test instead
// Let the real model interact with test database
```

**Rule:** If you're mocking more than 2-3 dependencies, consider an integration test.

### 2. Testing Implementation Instead of Behavior

```php
// ❌ BAD: Coupled to implementation
public function testLoginCallsPasswordVerify(): void
{
    // This breaks if we change how we verify passwords
}

// ✅ GOOD: Tests outcome
public function testLoginWithValidCredentialsReturnsToken(): void
{
    $result = $this->service->login(['username' => 'test', 'password' => 'Pass123']);
    $this->assertSuccessResponse($result, 'token');
}
```

### 3. Assertions Without Context

```php
// ❌ BAD: Unclear what's being tested
$this->assertTrue($result);

// ✅ GOOD: Clear assertion message
$this->assertTrue($result, 'Token should be valid for authenticated user');
```

### 4. Not Using setUp() and tearDown()

```php
// ❌ BAD: Repeated initialization
public function testMethod1(): void
{
    $this->mockModel = $this->createMock(UserModel::class);
    $this->service = new UserService($this->mockModel);
    // ... test code
}

public function testMethod2(): void
{
    $this->mockModel = $this->createMock(UserModel::class); // Duplicate
    $this->service = new UserService($this->mockModel); // Duplicate
    // ... test code
}

// ✅ GOOD: Use setUp()
protected function setUp(): void
{
    parent::setUp();
    $this->mockModel = $this->createMock(UserModel::class);
    $this->service = new UserService($this->mockModel);
}
```

---

## Quick Reference Checklist

Before committing, ask yourself:

- [ ] Does this test verify business logic or security?
- [ ] Would removing this test reduce confidence in the code?
- [ ] Is the test name clear about what it's testing?
- [ ] Did I use data providers for validation tests?
- [ ] Did I use custom assertions for cleaner code?
- [ ] Is this testing behavior, not implementation?
- [ ] Would this test fail if the feature broke?
- [ ] Am I testing realistic scenarios?

---

## Further Reading

- **CLAUDE.md** - Project-specific testing patterns and conventions
- **docs/ERROR_HANDLING_GUIDE.md** - When to throw exceptions vs return errors
- **docs/TEST_AUDIT.md** - Comprehensive audit of current test suite
- **PHPUnit Documentation** - https://phpunit.de/documentation.html

---

## Getting Help

If you're unsure whether to write a test:
1. Check this guide's decision tree
2. Review similar existing tests
3. Ask: "What bug would this test catch?"
4. When in doubt, prefer testing security and business logic over validation
