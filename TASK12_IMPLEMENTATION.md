# Task #12: API Response Library - Implementation Guide

## Overview

This guide shows how to implement the optional `ApiResponse` library for more explicit response formatting.

**Status**: Optional enhancement
**Estimated Time**: 1-2 hours
**Priority**: Low (format already standardized)

---

## Step 1: Create the Library

Create file: `app/Libraries/ApiResponse.php`

```php
<?php

declare(strict_types=1);

namespace App\Libraries;

/**
 * API Response Builder
 *
 * Centralizes API response format for consistency.
 * Provides static methods for building success and error responses.
 *
 * @package App\Libraries
 */
class ApiResponse
{
    /**
     * Build a successful response
     *
     * @param mixed $data Response data (can be array, object, null)
     * @param string|null $message Optional success message
     * @param array $meta Optional metadata (pagination, links, etc.)
     * @return array Formatted success response
     *
     * @example
     * ApiResponse::success(['user' => $userData], 'User retrieved')
     * // Returns: ['status' => 'success', 'message' => '...', 'data' => [...]]
     */
    public static function success(
        mixed $data = null,
        ?string $message = null,
        array $meta = []
    ): array {
        $response = ['status' => 'success'];

        if ($message !== null) {
            $response['message'] = $message;
        }

        if ($data !== null) {
            $response['data'] = $data;
        }

        if (!empty($meta)) {
            $response['meta'] = $meta;
        }

        return $response;
    }

    /**
     * Build an error response
     *
     * @param array|string $errors Error details (array of errors or single error string)
     * @param string $message Main error message
     * @param int|null $code Optional error code
     * @return array Formatted error response
     *
     * @example
     * ApiResponse::error(['email' => 'Invalid email'], 'Validation failed')
     * // Returns: ['status' => 'error', 'message' => '...', 'errors' => [...]]
     */
    public static function error(
        array|string $errors,
        string $message = 'Request failed',
        ?int $code = null
    ): array {
        $response = [
            'status' => 'error',
            'message' => $message,
        ];

        if (is_string($errors)) {
            $response['errors'] = ['general' => $errors];
        } else {
            $response['errors'] = $errors;
        }

        if ($code !== null) {
            $response['code'] = $code;
        }

        return $response;
    }

    /**
     * Build a paginated response
     *
     * @param array $items Items for current page
     * @param int $total Total number of items across all pages
     * @param int $page Current page number
     * @param int $perPage Items per page
     * @return array Formatted paginated response with meta
     *
     * @example
     * ApiResponse::paginated($users, 100, 1, 10)
     * // Includes pagination meta: total, per_page, current_page, etc.
     */
    public static function paginated(
        array $items,
        int $total,
        int $page,
        int $perPage
    ): array {
        return self::success($items, null, [
            'pagination' => [
                'total' => $total,
                'per_page' => $perPage,
                'current_page' => $page,
                'last_page' => (int) ceil($total / $perPage),
                'from' => ($page - 1) * $perPage + 1,
                'to' => min($page * $perPage, $total),
            ],
        ]);
    }

    /**
     * Build a created response (HTTP 201)
     *
     * @param mixed $data Created resource data
     * @param string|null $message Success message
     * @return array Formatted response
     */
    public static function created(
        mixed $data,
        ?string $message = 'Resource created successfully'
    ): array {
        return self::success($data, $message);
    }

    /**
     * Build a deleted response (HTTP 200/204)
     *
     * @param string|null $message Success message
     * @return array Formatted response
     */
    public static function deleted(
        ?string $message = 'Resource deleted successfully'
    ): array {
        return self::success(null, $message);
    }

    /**
     * Build a validation error response (HTTP 422)
     *
     * @param array $errors Validation errors
     * @param string $message Error message
     * @return array Formatted response
     */
    public static function validationError(
        array $errors,
        string $message = 'Validation failed'
    ): array {
        return self::error($errors, $message, 422);
    }

    /**
     * Build a not found response (HTTP 404)
     *
     * @param string $message Error message
     * @return array Formatted response
     */
    public static function notFound(
        string $message = 'Resource not found'
    ): array {
        return self::error([], $message, 404);
    }

    /**
     * Build an unauthorized response (HTTP 401)
     *
     * @param string $message Error message
     * @return array Formatted response
     */
    public static function unauthorized(
        string $message = 'Unauthorized'
    ): array {
        return self::error([], $message, 401);
    }

    /**
     * Build a forbidden response (HTTP 403)
     *
     * @param string $message Error message
     * @return array Formatted response
     */
    public static function forbidden(
        string $message = 'Forbidden'
    ): array {
        return self::error([], $message, 403);
    }

    /**
     * Build a server error response (HTTP 500)
     *
     * @param string $message Error message
     * @return array Formatted response
     */
    public static function serverError(
        string $message = 'Internal server error'
    ): array {
        return self::error([], $message, 500);
    }
}
```

---

## Step 2: Update UserService

Replace manual array building with ApiResponse methods:

```php
<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\UserModel;
use App\Entities\UserEntity;
use App\Exceptions\NotFoundException;
use App\Exceptions\ValidationException;
use App\Exceptions\BadRequestException;
use App\Interfaces\UserServiceInterface;
use App\Libraries\ApiResponse; // 👈 Add this

class UserService implements UserServiceInterface
{
    // ... constructor ...

    public function index(array $data): array
    {
        $users = $this->userModel->findAll();

        // BEFORE
        // return [
        //     'status' => 'success',
        //     'data' => array_map(fn($user) => $user->toArray(), $users),
        // ];

        // AFTER
        return ApiResponse::success(
            array_map(fn($user) => $user->toArray(), $users)
        );
    }

    public function show(array $data): array
    {
        if (!isset($data['id'])) {
            // BEFORE
            // return ['errors' => ['id' => lang('Users.idRequired')]];

            // AFTER
            return ApiResponse::error(
                ['id' => lang('Users.idRequired')],
                'Invalid request'
            );
        }

        $user = $this->userModel->find((int) $data['id']);

        if (!$user) {
            throw new NotFoundException(lang('Users.notFound'));
        }

        // BEFORE
        // return [
        //     'status' => 'success',
        //     'data' => $user->toArray(),
        // ];

        // AFTER
        return ApiResponse::success($user->toArray());
    }

    public function store(array $data): array
    {
        $businessErrors = $this->validateBusinessRules($data);
        if (!empty($businessErrors)) {
            // BEFORE
            // return ['errors' => $businessErrors];

            // AFTER
            return ApiResponse::validationError($businessErrors);
        }

        $userId = $this->userModel->insert([
            'email'    => $data['email'] ?? null,
            'username' => $data['username'] ?? null,
        ]);

        if (!$userId) {
            // BEFORE
            // return ['errors' => $this->userModel->errors()];

            // AFTER
            return ApiResponse::validationError(
                $this->userModel->errors()
            );
        }

        $user = $this->userModel->find($userId);

        // BEFORE
        // return [
        //     'status' => 'success',
        //     'data' => $user->toArray(),
        // ];

        // AFTER
        return ApiResponse::created($user->toArray());
    }

    public function update(array $data): array
    {
        if (!isset($data['id'])) {
            return ApiResponse::error(
                ['id' => lang('Users.idRequired')],
                'Invalid request'
            );
        }

        $id = (int) $data['id'];

        if (!$this->userModel->find($id)) {
            throw new NotFoundException(lang('Users.notFound'));
        }

        if (empty($data['email']) && empty($data['username'])) {
            return ApiResponse::error(
                ['fields' => lang('Users.fieldRequired')],
                'Invalid request'
            );
        }

        $updateData = array_filter([
            'email'    => $data['email'] ?? null,
            'username' => $data['username'] ?? null,
        ], fn($value) => $value !== null);

        $success = $this->userModel->update($id, $updateData);

        if (!$success) {
            return ApiResponse::validationError(
                $this->userModel->errors()
            );
        }

        $user = $this->userModel->find($id);

        return ApiResponse::success($user->toArray());
    }

    public function destroy(array $data): array
    {
        if (!isset($data['id'])) {
            return ApiResponse::error(
                ['id' => lang('Users.idRequired')],
                'Invalid request'
            );
        }

        $id = (int) $data['id'];

        if (!$this->userModel->find($id)) {
            throw new NotFoundException(lang('Users.notFound'));
        }

        if (!$this->userModel->delete($id)) {
            throw new \RuntimeException(lang('Users.deleteError'));
        }

        return ApiResponse::deleted(lang('Users.deletedSuccess'));
    }

    // login, register, etc. - similar updates
}
```

---

## Step 3: Optional - Update Exceptions

You can optionally update ApiException to use ApiResponse:

```php
<?php

declare(strict_types=1);

namespace App\Exceptions;

use Exception;
use Throwable;
use App\Libraries\ApiResponse;

abstract class ApiException extends Exception
{
    protected int $statusCode = 500;
    protected array $errors = [];

    public function __construct(string $message = '', array $errors = [], ?Throwable $previous = null)
    {
        parent::__construct($message, 0, $previous);
        $this->errors = $errors;
    }

    public function getStatusCode(): int
    {
        return $this->statusCode;
    }

    public function getErrors(): array
    {
        return $this->errors;
    }

    public function toArray(): array
    {
        // Option 1: Keep current implementation
        return [
            'status' => 'error',
            'message' => $this->getMessage(),
            'errors' => $this->errors,
        ];

        // Option 2: Use ApiResponse
        // return ApiResponse::error(
        //     $this->errors,
        //     $this->getMessage(),
        //     $this->statusCode
        // );
    }
}
```

---

## Step 4: Create Tests

Create `tests/unit/Libraries/ApiResponseTest.php`:

```php
<?php

declare(strict_types=1);

namespace Tests\Unit\Libraries;

use CodeIgniter\Test\CIUnitTestCase;
use App\Libraries\ApiResponse;

class ApiResponseTest extends CIUnitTestCase
{
    public function testSuccessWithData(): void
    {
        $result = ApiResponse::success(['user' => 'test']);

        $this->assertEquals('success', $result['status']);
        $this->assertArrayHasKey('data', $result);
        $this->assertEquals(['user' => 'test'], $result['data']);
    }

    public function testSuccessWithMessage(): void
    {
        $result = ApiResponse::success(['id' => 1], 'User created');

        $this->assertEquals('success', $result['status']);
        $this->assertEquals('User created', $result['message']);
    }

    public function testSuccessWithMeta(): void
    {
        $result = ApiResponse::success([], null, ['count' => 10]);

        $this->assertArrayHasKey('meta', $result);
        $this->assertEquals(['count' => 10], $result['meta']);
    }

    public function testError(): void
    {
        $result = ApiResponse::error(['field' => 'error'], 'Failed');

        $this->assertEquals('error', $result['status']);
        $this->assertEquals('Failed', $result['message']);
        $this->assertEquals(['field' => 'error'], $result['errors']);
    }

    public function testErrorWithString(): void
    {
        $result = ApiResponse::error('Something went wrong');

        $this->assertEquals(['general' => 'Something went wrong'], $result['errors']);
    }

    public function testPaginated(): void
    {
        $result = ApiResponse::paginated([1, 2, 3], 30, 2, 10);

        $this->assertEquals('success', $result['status']);
        $this->assertArrayHasKey('meta', $result);
        $this->assertArrayHasKey('pagination', $result['meta']);

        $pagination = $result['meta']['pagination'];
        $this->assertEquals(30, $pagination['total']);
        $this->assertEquals(10, $pagination['per_page']);
        $this->assertEquals(2, $pagination['current_page']);
        $this->assertEquals(3, $pagination['last_page']);
    }

    public function testCreated(): void
    {
        $result = ApiResponse::created(['id' => 1]);

        $this->assertEquals('success', $result['status']);
        $this->assertStringContainsString('created', strtolower($result['message']));
    }

    public function testDeleted(): void
    {
        $result = ApiResponse::deleted();

        $this->assertEquals('success', $result['status']);
        $this->assertArrayHasKey('message', $result);
    }

    public function testValidationError(): void
    {
        $result = ApiResponse::validationError(['email' => 'Invalid']);

        $this->assertEquals('error', $result['status']);
        $this->assertEquals(422, $result['code']);
    }

    public function testNotFound(): void
    {
        $result = ApiResponse::notFound('User not found');

        $this->assertEquals('error', $result['status']);
        $this->assertEquals(404, $result['code']);
    }

    public function testUnauthorized(): void
    {
        $result = ApiResponse::unauthorized();

        $this->assertEquals('error', $result['status']);
        $this->assertEquals(401, $result['code']);
    }

    public function testForbidden(): void
    {
        $result = ApiResponse::forbidden();

        $this->assertEquals('error', $result['status']);
        $this->assertEquals(403, $result['code']);
    }
}
```

Run tests:
```bash
vendor/bin/phpunit tests/unit/Libraries/ApiResponseTest.php --testdox
```

---

## Step 5: Update Documentation

Add section to OpenAPI config or README:

```markdown
## API Response Format

All API responses follow a consistent format using the `ApiResponse` library.

### Success Response
\`\`\`json
{
  "status": "success",
  "data": { ... },
  "message": "Optional message"
}
\`\`\`

### Error Response
\`\`\`json
{
  "status": "error",
  "message": "Error description",
  "errors": { ... }
}
\`\`\`

### Paginated Response
\`\`\`json
{
  "status": "success",
  "data": [...],
  "meta": {
    "pagination": {
      "total": 100,
      "per_page": 10,
      "current_page": 1,
      "last_page": 10
    }
  }
}
\`\`\`
```

---

## Benefits of Implementation

### Developer Experience
- ✅ IDE autocomplete for response methods
- ✅ No more typos in response structure
- ✅ Self-documenting code
- ✅ Consistent API across all endpoints

### Maintainability
- ✅ Single source of truth for response format
- ✅ Easy to change format globally
- ✅ Clear intent in code (created vs success)
- ✅ Better code reviews (obvious patterns)

### Testing
- ✅ Easy to test response format
- ✅ Mock responses consistently
- ✅ Validate all responses follow standard

---

## Migration Strategy

If you want to implement this gradually:

### Phase 1: Create Library
- Create ApiResponse class
- Write unit tests
- Document usage

### Phase 2: Use in New Code
- Use ApiResponse in all new endpoints
- Keep old code as-is (no breaking changes)

### Phase 3: Migrate Existing Code
- Update one service at a time
- Test after each migration
- Commit incrementally

### Phase 4: Enforce in Code Reviews
- Require ApiResponse in all new PRs
- Eventually deprecate manual array building

---

## Estimated Effort

- **Library Creation**: 30 minutes
- **Unit Tests**: 30 minutes
- **UserService Update**: 30 minutes
- **Documentation**: 30 minutes
- **Testing & Validation**: 30 minutes

**Total**: ~2.5 hours for complete implementation

---

## Conclusion

While the **current implementation is functional**, the ApiResponse library would provide:

1. **Better Developer Experience** - Autocomplete, no typos
2. **Easier Maintenance** - Centralized format
3. **Clearer Intent** - Explicit methods (created, deleted, etc.)
4. **Professional Polish** - Industry standard approach

**Recommendation**: Implement if you plan to:
- Add many new endpoints
- Have multiple developers
- Want explicit documentation
- Prefer strongly-typed responses

**Skip if**:
- Happy with current format
- Small team/solo developer
- Time-constrained
- Current approach works well

---

*The choice is yours! The application is production-ready either way.*
