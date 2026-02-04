# Error Handling Strategy Guide

This guide defines the **consistent error handling strategy** for the CI4 API Starter project. It establishes clear rules for when to use exceptions vs. ApiResponse, ensuring consistency across all services and controllers.

## Table of Contents

- [Core Philosophy](#core-philosophy)
- [Decision Tree](#decision-tree)
- [Exception Types](#exception-types)
- [Service Patterns](#service-patterns)
- [Testing Patterns](#testing-patterns)
- [Common Scenarios](#common-scenarios)
- [Migration Guide](#migration-guide)

---

## Core Philosophy

The error handling strategy follows a **hybrid approach** with clear rules:

### When to Use Exceptions

**Exceptions represent exceptional conditions** - situations where the normal flow of execution cannot continue:

- Resource not found (404)
- Authentication failure (401)
- Authorization denial (403)
- State conflicts (409)
- System errors (500)
- Malformed requests (400)

**Key principle**: If the error prevents the service method from completing its intended operation, throw an exception.

### When to Use ApiResponse

**ApiResponse represents expected business outcomes** - situations where validation fails but the system handled it gracefully:

- Model validation failures (422)
- Business rule violations (400)
- Missing required fields (400)
- Invalid data formats (handled by validation)

**Key principle**: If the service can validate and reject the request without an exceptional condition, return ApiResponse.

### Where to Handle Exceptions

- **Services**: Throw exceptions freely according to the rules
- **Controllers**: NEVER use try-catch (ApiController handles all exceptions)
- **ApiController**: Single point of exception handling with `handleException()`

---

## Decision Tree

Use this decision tree when determining how to handle an error:

```
Is this an error?
  │
  ├─ Is it a programming/system error?
  │   └─ YES → THROW Exception
  │      Examples:
  │      - Database connection failed
  │      - File system error
  │      - Configuration missing
  │      - Unexpected null value
  │
  └─ NO → Is it a business/request condition?
      │
      ├─ Does the resource not exist?
      │   └─ YES → THROW NotFoundException (404)
      │
      ├─ Is authentication missing/invalid?
      │   └─ YES → THROW AuthenticationException (401)
      │
      ├─ Does user lack permissions?
      │   └─ YES → THROW AuthorizationException (403)
      │
      ├─ Is there a state conflict?
      │   └─ YES → THROW ConflictException (409)
      │      Examples:
      │      - Email already verified
      │      - Resource already exists
      │      - Cannot transition from current state
      │
      ├─ Is the request structurally invalid?
      │   └─ YES → THROW BadRequestException (400)
      │      Examples:
      │      - Wrong data type (expected int, got string)
      │      - Invalid JSON format
      │      - Missing content-type header
      │
      ├─ Did model validation fail?
      │   └─ YES → RETURN ApiResponse::validationError() (422)
      │      Examples:
      │      - Email format invalid
      │      - Password too short
      │      - Unique constraint violation
      │
      └─ Did business rule validation fail?
          └─ YES → RETURN ApiResponse::error() (400)
             Examples:
             - Domain not allowed
             - File size exceeds limit
             - Account inactive
```

---

## Exception Types

### Available Exceptions

| Exception | HTTP Status | Use Case |
|-----------|-------------|----------|
| `BadRequestException` | 400 | Request is structurally invalid or malformed |
| `AuthenticationException` | 401 | Invalid credentials or missing authentication |
| `AuthorizationException` | 403 | User lacks required permissions |
| `NotFoundException` | 404 | Requested resource does not exist |
| `ConflictException` | 409 | Request conflicts with current resource state |
| `ValidationException` | 422 | Request validation failed (optional - prefer ApiResponse) |
| `TooManyRequestsException` | 429 | Rate limit exceeded |
| `RuntimeException` | 500 | Unexpected system error |
| `ServiceUnavailableException` | 503 | Service temporarily unavailable |
| `DatabaseException` | 500 | Database operation failed |

### Creating Custom Exceptions

All custom exceptions should extend `ApiException`:

```php
<?php
namespace App\Exceptions;

class CustomException extends ApiException
{
    protected int $statusCode = 400;

    public function __construct(string $message = 'Custom error', array $errors = [])
    {
        parent::__construct($message, $errors);
    }
}
```

---

## Service Patterns

### Standard Service Method Pattern

Follow this pattern for consistent error handling:

```php
public function methodName(array $data): array
{
    // 1. Validate required fields (ApiResponse for missing fields)
    if (empty($data['field'])) {
        return ApiResponse::error(
            ['field' => 'This field is required'],
            'Invalid request'
        );
    }

    // 2. Verify resource exists (Exception)
    $resource = $this->model->find($data['id']);
    if (!$resource) {
        throw new NotFoundException('Resource not found');
    }

    // 3. Check authorization (Exception)
    if (!$this->canAccess($resource, $data['user_id'])) {
        throw new AuthorizationException('You do not have permission to access this resource');
    }

    // 4. Validate business rules (ApiResponse)
    if (!$this->meetsBusinessRules($data)) {
        return ApiResponse::error(
            ['field' => 'Does not meet business requirements'],
            'Validation failed'
        );
    }

    // 5. Perform database operation
    $result = $this->model->insert($data);

    // 6. Handle model validation failures (ApiResponse)
    if (!$result) {
        return ApiResponse::validationError($this->model->errors());
    }

    // 7. Handle critical side-effects (Exception on failure)
    if (!$this->storage->put($path, $contents)) {
        $this->model->delete($result); // Rollback
        throw new \RuntimeException('Storage operation failed');
    }

    // 8. Handle non-critical side-effects (Log, don't throw)
    try {
        $this->emailService->send($to, $subject, $message);
    } catch (\Throwable $e) {
        log_message('error', 'Email failed: ' . $e->getMessage());
        // Continue - email failure shouldn't break the flow
    }

    // 9. Return success
    return ApiResponse::created($result->toArray());
}
```

### Real-World Examples

#### Example 1: User Registration

```php
public function registerWithToken(array $data): array
{
    // Validate required fields
    if (empty($data['email']) || empty($data['password'])) {
        return ApiResponse::error(
            ['email' => 'Email and password are required'],
            'Invalid registration data'
        );
    }

    // Attempt to insert user
    $userId = $this->userModel->insert($data);

    // Handle model validation failure (unique email, format, etc.)
    if (!$userId) {
        return ApiResponse::validationError($this->userModel->errors());
    }

    // Generate tokens (throws exception on system failure)
    $token = $this->jwtService->encode(['uid' => $userId, 'role' => 'user']);
    $refreshToken = $this->refreshTokenService->generate($userId);

    // Send verification email (non-critical)
    try {
        $this->verificationService->sendVerificationEmail($userId);
    } catch (\Throwable $e) {
        log_message('error', 'Verification email failed: ' . $e->getMessage());
    }

    return ApiResponse::created([
        'user' => $this->userModel->find($userId)->toArray(),
        'token' => $token,
        'refreshToken' => $refreshToken['data']['token'],
    ]);
}
```

#### Example 2: File Download

```php
public function download(array $data): array
{
    // Validate required field
    if (empty($data['id'])) {
        return ApiResponse::error(['id' => 'File ID is required'], 'Invalid request');
    }

    // Check file exists
    $file = $this->fileModel->find($data['id']);
    if (!$file) {
        throw new NotFoundException('File not found');
    }

    // Check authorization
    if ($file->user_id !== $data['user_id']) {
        throw new AuthorizationException('You do not have permission to download this file');
    }

    // Check file exists in storage
    if (!$this->storage->exists($file->path)) {
        log_message('error', "File missing in storage: {$file->path}");
        throw new NotFoundException('File data not found in storage');
    }

    // Return file data
    return ApiResponse::success([
        'file' => $file->toArray(),
        'url' => $this->storage->url($file->path),
    ]);
}
```

#### Example 3: Email Verification

```php
public function verifyEmail(array $data): array
{
    // Validate required fields
    if (empty($data['token'])) {
        return ApiResponse::error(['token' => 'Verification token is required'], 'Invalid request');
    }

    // Find user by token
    $user = $this->userModel->where('verification_token', $data['token'])->first();
    if (!$user) {
        throw new NotFoundException('Invalid verification token');
    }

    // Check if already verified
    if ($user->email_verified_at !== null) {
        throw new ConflictException('Email address is already verified');
    }

    // Check token expiration
    if (strtotime($user->verification_expires_at) < time()) {
        throw new BadRequestException('Verification token has expired');
    }

    // Update user
    $this->userModel->update($user->id, [
        'email_verified_at' => date('Y-m-d H:i:s'),
        'verification_token' => null,
        'verification_expires_at' => null,
    ]);

    return ApiResponse::success(['message' => 'Email verified successfully']);
}
```

---

## Testing Patterns

### Testing Exception Throwing

```php
public function testShowThrowsNotFoundExceptionWhenUserDoesNotExist()
{
    // Arrange
    $this->mockModel->method('find')->willReturn(null);

    // Assert
    $this->expectException(NotFoundException::class);
    $this->expectExceptionMessage('User not found');

    // Act
    $this->service->show(['id' => 999]);
}

public function testDownloadThrowsAuthorizationExceptionWhenUnauthorized()
{
    // Arrange
    $file = new FileEntity(['id' => 1, 'user_id' => 999]);
    $this->mockModel->method('find')->willReturn($file);

    // Assert
    $this->expectException(AuthorizationException::class);
    $this->expectExceptionMessage('permission');

    // Act
    $this->service->download(['id' => 1, 'user_id' => 1]);
}
```

### Testing ApiResponse Returns

```php
public function testStoreReturnsValidationErrorOnModelFailure()
{
    // Arrange
    $this->mockModel->method('insert')->willReturn(false);
    $this->mockModel->method('errors')->willReturn([
        'email' => 'Email already exists'
    ]);

    // Act
    $result = $this->service->store(['email' => 'test@test.com']);

    // Assert
    $this->assertEquals('error', $result['status']);
    $this->assertEquals(422, $result['code']);
    $this->assertArrayHasKey('errors', $result);
    $this->assertArrayHasKey('email', $result['errors']);
}

public function testUploadReturnsErrorWhenFileFieldMissing()
{
    // Act
    $result = $this->service->upload(['user_id' => 1]);

    // Assert
    $this->assertEquals('error', $result['status']);
    $this->assertArrayHasKey('errors', $result);
    $this->assertArrayHasKey('file', $result['errors']);
}
```

### Testing Non-Critical Operations

```php
public function testRegisterContinuesWhenEmailServiceFails()
{
    // Arrange
    $this->mockUserModel->method('insert')->willReturn(1);
    $this->mockEmailService->method('send')
        ->willThrowException(new \RuntimeException('SMTP error'));

    // Act
    $result = $this->service->registerWithToken([
        'email' => 'test@test.com',
        'password' => 'Password123',
    ]);

    // Assert - Registration still succeeds despite email failure
    $this->assertEquals('success', $result['status']);
    $this->assertArrayHasKey('user', $result['data']);
}
```

---

## Common Scenarios

### Scenario 1: Resource Not Found

**Rule**: Always throw `NotFoundException`

```php
// ✅ CORRECT
$user = $this->userModel->find($id);
if (!$user) {
    throw new NotFoundException('User not found');
}

// ❌ WRONG
$user = $this->userModel->find($id);
if (!$user) {
    return ApiResponse::error(['id' => 'User not found'], 'Not found');
}
```

### Scenario 2: Unique Constraint Violation

**Rule**: Let model validation handle it, return `ApiResponse::validationError()`

```php
// ✅ CORRECT
$result = $this->userModel->insert($data);
if (!$result) {
    return ApiResponse::validationError($this->userModel->errors());
}

// ❌ WRONG
$existing = $this->userModel->where('email', $data['email'])->first();
if ($existing) {
    throw new ConflictException('Email already exists');
}
```

### Scenario 3: Missing Required Field

**Rule**: Return `ApiResponse::error()` for application-level requirements

```php
// ✅ CORRECT
if (empty($data['file'])) {
    return ApiResponse::error(
        ['file' => 'File is required'],
        'Invalid request'
    );
}

// ❌ WRONG
if (empty($data['file'])) {
    throw new BadRequestException('File is required');
}
```

### Scenario 4: Authorization Check

**Rule**: Always throw `AuthorizationException`

```php
// ✅ CORRECT
if ($resource->user_id !== $currentUserId) {
    throw new AuthorizationException('You do not have permission to access this resource');
}

// ❌ WRONG
if ($resource->user_id !== $currentUserId) {
    return ApiResponse::error(['auth' => 'Forbidden'], 'Access denied');
}
```

### Scenario 5: State Conflict

**Rule**: Throw `ConflictException` for state-related conflicts

```php
// ✅ CORRECT
if ($user->email_verified_at !== null) {
    throw new ConflictException('Email address is already verified');
}

// ❌ WRONG
if ($user->email_verified_at !== null) {
    return ApiResponse::error(['email' => 'Already verified'], 'Conflict');
}
```

### Scenario 6: External Service Failure (Critical)

**Rule**: Throw exception if the operation cannot complete without it

```php
// ✅ CORRECT - Storage is critical
if (!$this->storage->put($path, $contents)) {
    $this->model->delete($fileId); // Rollback
    throw new \RuntimeException('Failed to store file');
}

// ✅ CORRECT - Email is non-critical
try {
    $this->emailService->send($to, $subject, $message);
} catch (\Throwable $e) {
    log_message('error', 'Email failed: ' . $e->getMessage());
    // Continue - don't throw
}
```

### Scenario 7: Database Query Failure

**Rule**: Let database exceptions bubble up (handled by ApiController)

```php
// ✅ CORRECT
$result = $this->model->insert($data); // Let DatabaseException propagate

// ❌ WRONG
try {
    $result = $this->model->insert($data);
} catch (DatabaseException $e) {
    return ApiResponse::error(['db' => 'Database error'], 'Error');
}
```

---

## Migration Guide

### Identifying Code to Update

Look for these patterns that need updating:

1. **Services returning error arrays**:
```php
// Old pattern
return ['status' => 'error', 'message' => 'Not found'];

// New pattern
throw new NotFoundException('Resource not found');
```

2. **Try-catch in services** (except for non-critical operations):
```php
// Old pattern
try {
    $result = $this->model->insert($data);
} catch (Exception $e) {
    return ApiResponse::error([], $e->getMessage());
}

// New pattern - let exception propagate
$result = $this->model->insert($data);
```

3. **Error responses for missing resources**:
```php
// Old pattern
if (!$user) {
    return ApiResponse::error(['id' => 'Not found'], 'User not found');
}

// New pattern
if (!$user) {
    throw new NotFoundException('User not found');
}
```

### Step-by-Step Migration

1. **Identify the error condition** using the decision tree
2. **Determine if it's exceptional or expected validation**
3. **Replace return statement or add exception**
4. **Update tests** to expect exceptions or ApiResponse
5. **Run tests** to verify behavior
6. **Test manually** if needed

### Priority Order

Migrate services in this order (highest impact first):

1. **UserService** - Most commonly used
2. **VerificationService** - Authentication flow
3. **PasswordResetService** - Authentication flow
4. **FileService** - File operations
5. **RefreshTokenService** - Token management
6. **AuditService** - Admin functionality

---

## Best Practices

### Do's ✅

- **Do** throw exceptions for resource not found (404)
- **Do** throw exceptions for auth failures (401, 403)
- **Do** throw exceptions for state conflicts (409)
- **Do** return ApiResponse for validation failures (422)
- **Do** return ApiResponse for business rule violations (400)
- **Do** let database exceptions propagate to ApiController
- **Do** log non-critical failures and continue
- **Do** write tests that verify exception types

### Don'ts ❌

- **Don't** use try-catch in services (except non-critical operations)
- **Don't** use try-catch in controllers (ApiController handles it)
- **Don't** return ApiResponse for 404/401/403 errors
- **Don't** throw exceptions for model validation failures
- **Don't** catch and convert exceptions to ApiResponse in services
- **Don't** throw exceptions for missing required fields
- **Don't** let non-critical operations break the flow

---

## Summary

### Quick Reference

| Condition | Action | HTTP Status |
|-----------|--------|-------------|
| Resource not found | `throw NotFoundException` | 404 |
| Invalid credentials | `throw AuthenticationException` | 401 |
| Insufficient permissions | `throw AuthorizationException` | 403 |
| State conflict | `throw ConflictException` | 409 |
| Model validation failed | `return ApiResponse::validationError()` | 422 |
| Business rule violated | `return ApiResponse::error()` | 400 |
| Required field missing | `return ApiResponse::error()` | 400 |
| Malformed request | `throw BadRequestException` | 400 |
| System error | `throw RuntimeException` | 500 |
| Database error | Let `DatabaseException` propagate | 500 |

### Key Takeaways

1. **Exceptions are for exceptional conditions** - situations where normal flow cannot continue
2. **ApiResponse is for expected validation** - business rules and model validation
3. **Controllers never catch exceptions** - ApiController handles all exceptions centrally
4. **Services throw freely** - follow the decision tree
5. **Test both paths** - verify exceptions are thrown and ApiResponse is returned correctly

---

## Questions?

If you're unsure whether to throw an exception or return ApiResponse:

1. **Consult the decision tree** at the top of this document
2. **Look at similar code** in existing services
3. **Ask**: "Can the service method complete its intended operation?"
   - **No** → Throw exception
   - **Yes, but validation failed** → Return ApiResponse

Remember: **Consistency is key**. When in doubt, prefer exceptions for truly exceptional conditions.
