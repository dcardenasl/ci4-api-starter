# Architecture Decision Records (ADR)

This document explains the architectural decisions made in this project and the reasoning behind them. When in doubt about whether something is an inconsistency or a deliberate design choice, consult this document.

## Table of Contents

1. [Controller Architecture](#controller-architecture)
2. [Documentation Strategy](#documentation-strategy)
3. [Testing Strategy](#testing-strategy)
4. [Security Patterns](#security-patterns)
5. [Service Layer Patterns](#service-layer-patterns)
6. [Observability and Governance](#observability-and-governance)

---

## Controller Architecture

### ADR-001: Two Controller Types

**Decision:** The project uses two distinct controller types with different purposes.

#### Business API Controllers

**Pattern:** Extend `ApiController`

**Purpose:** Handle business domain operations (CRUD, business logic)

**Examples:**
- `UserController` - User management
- `FileController` - File uploads/downloads
- `AuthController` - Authentication/registration
- `AuditController` - Audit log queries

**Characteristics:**
```php
class UserController extends ApiController
{
    protected string $serviceName = 'userService';

    public function index(): ResponseInterface {
        return $this->handleRequest('index');
    }
}
```

**Benefits:**
- ✅ Centralized exception handling
- ✅ Automatic request data collection
- ✅ Standardized response format
- ✅ Built-in XSS sanitization
- ✅ Consistent error responses

#### Infrastructure Controllers

**Pattern:** Extend base `Controller`

**Purpose:** System monitoring, health checks, metrics

**Examples:**
- `HealthController` - Health checks (`/health`, `/ping`, `/ready`, `/live`)
- `MetricsController` - System metrics (`/metrics/*`)

**Characteristics:**
```php
class HealthController extends Controller
{
    public function ping(): ResponseInterface {
        return $this->response->setJSON([
            'status' => 'ok',
            'timestamp' => date('Y-m-d H:i:s'),
        ])->setStatusCode(200);
    }
}
```

**Why NOT ApiController?**

1. **Performance Critical**
   - Called every 5-10 seconds by orchestrators
   - Must be ultra-fast (< 50ms)
   - No overhead from service layer, sanitization, etc.

2. **Different Response Contract**
   - Health checks follow Kubernetes/Docker standards
   - Don't use ApiResponse format
   - Status codes have specific meanings (200=healthy, 503=unhealthy)

3. **No Authentication Required**
   - Must be publicly accessible
   - Load balancers need unauthenticated access
   - Monitoring tools can't handle JWT

4. **No Business Logic**
   - Just check system state
   - No data processing
   - No user input

5. **Industry Standards**
   - Spring Boot: `HealthIndicator` (simple, no framework overhead)
   - Express.js: Health middleware (direct response)
   - Django: Simple view functions

**Important:** If you see a controller that doesn't extend `ApiController`, check if it's infrastructure before reporting it as an inconsistency.

---

## Documentation Strategy

### ADR-002: Separated OpenAPI Documentation

**Decision:** OpenAPI documentation lives in separate PHP files in `app/Documentation/`, NOT as annotations in controllers.

**Structure:**
```
app/Documentation/
├── Auth/
│   ├── AuthEndpoints.php       # Endpoint definitions
│   ├── LoginRequest.php        # Request schemas
│   ├── RegisterRequest.php
│   └── AuthTokenSchema.php     # Response schemas
├── Users/
│   ├── UserEndpoints.php
│   ├── UserSchema.php
│   ├── CreateUserRequest.php
│   └── UpdateUserRequest.php
├── Files/
│   ├── FileEndpoints.php
│   └── FileSchema.php
└── Common/
    ├── UnauthorizedResponse.php
    ├── ValidationErrorResponse.php
    └── NotFoundResponse.php
```

**Generation:**
```bash
php spark swagger:generate  # Scans app/Documentation/ and generates swagger.json
```

**Why Separated Files Over Annotations?**

#### 1. Separation of Concerns
```php
// ❌ BAD: Annotation approach (coupled)
class UserController extends ApiController
{
    /**
     * @OA\Get(
     *     path="/api/v1/users/{id}",
     *     summary="Get user by ID",
     *     tags={"Users"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="User found",
     *         @OA\JsonContent(ref="#/components/schemas/User")
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="User not found"
     *     )
     * )
     */
    public function show($id): ResponseInterface {
        return $this->handleRequest('show', ['id' => $id]);
    }
}

// ✅ GOOD: Separated approach (clean)
class UserController extends ApiController
{
    public function show($id): ResponseInterface {
        return $this->handleRequest('show', ['id' => $id]);
    }
}

// Documentation in app/Documentation/Users/UserEndpoints.php
```

#### 2. Reusability
```php
// Common schemas can be reused across endpoints
class ValidationErrorResponse { /* @OA\Schema ... */ }
class UnauthorizedResponse { /* @OA\Schema ... */ }

// Used in UserEndpoints, FileEndpoints, AuthEndpoints, etc.
```

#### 3. Team Collaboration
- Developers focus on code
- Technical writers can update docs without touching code
- Easier code reviews (docs changes don't pollute controller diffs)
- Less merge conflicts

#### 4. Maintainability
- Documentation changes don't trigger controller tests
- Easier to find and update related documentation
- Better organization (grouped by feature)

#### 5. Cleaner Code
Controllers stay focused on their responsibility: routing requests to services.

**References:**
- This is the same pattern used by Symfony API Platform
- Laravel OpenAPI Generator supports both approaches
- NestJS uses decorators but keeps them minimal

---

## Testing Strategy

### ADR-003: Three Test Layers

**Decision:** Tests are organized by integration level, not by file type.

#### Test Organization

```
tests/
├── Unit/                    # Fast, no external dependencies
│   ├── Libraries/           # ApiResponse, QueryBuilder, etc.
│   ├── Services/            # Services with mocked dependencies
│   ├── Helpers/             # Helper functions
│   └── Validations/         # Validation rules
│
├── Integration/             # Real database, real dependencies
│   ├── Models/              # Model CRUD with real DB
│   └── Services/            # Services with real DB/models
│
└── Feature/                 # Full HTTP request/response cycle
    └── Controllers/         # End-to-end controller tests
```

#### Unit Tests (218 tests)

**Characteristics:**
- No database
- Mocked dependencies
- Fast (< 1 second total)

**Example:**
```php
class AuthServiceTest extends CIUnitTestCase
{
    use CustomAssertionsTrait;  // ✅ For services returning ApiResponse

    protected function setUp(): void {
        $this->mockUserModel = $this->createMock(UserModel::class);
        $this->mockJwtService = $this->createMock(JwtServiceInterface::class);
        $this->service = new AuthService($this->mockUserModel, $this->mockJwtService);
    }

    public function testLoginWithValidCredentials(): void {
        $result = $this->service->login(['username' => 'test', 'password' => 'pass']);
        $this->assertSuccessResponse($result);  // Uses CustomAssertionsTrait
    }
}
```

#### Integration Tests

**Characteristics:**
- Real database
- Real models
- Test DB interactions
- Slower than unit tests

**Example:**
```php
class UserModelTest extends CIUnitTestCase
{
    use DatabaseTestTrait;

    protected $migrate = true;
    protected $namespace = 'App';  // ⚠️ CRITICAL: Use app migrations

    public function testInsertUser(): void {
        $userId = $this->userModel->insert(['username' => 'test']);
        $this->assertIsInt($userId);
        $this->seeInDatabase('users', ['username' => 'test']);
    }
}
```

#### Feature Tests

**Characteristics:**
- Full HTTP stack
- Real routes and filters
- Test authentication/authorization
- Test complete user flows

**Example:**
```php
class UserControllerTest extends FeatureTestCase
{
    public function testCreateUserRequiresAdmin(): void {
        $response = $this->withHeaders(['Authorization' => 'Bearer ' . $this->userToken])
            ->post('/api/v1/users', ['username' => 'newuser']);

        $response->assertStatus(403);  // Regular user can't create users
    }
}
```

### ADR-004: CustomAssertionsTrait Usage

**Decision:** Only use `CustomAssertionsTrait` in tests that verify `ApiResponse` structure.

#### When to Use

✅ **Service tests** (they return ApiResponse arrays):
```php
class UserServiceTest extends CIUnitTestCase
{
    use CustomAssertionsTrait;  // ✅ Correct

    public function testStore(): void {
        $result = $this->service->store(['username' => 'test']);
        $this->assertSuccessResponse($result);  // ApiResponse format
    }
}
```

✅ **Feature/Controller tests** (HTTP responses with ApiResponse body):
```php
class UserControllerTest extends FeatureTestCase
{
    use CustomAssertionsTrait;  // ✅ Correct

    public function testIndex(): void {
        $response = $this->get('/api/v1/users');
        $data = json_decode($response->getBody(), true);
        $this->assertPaginatedResponse($data);
    }
}
```

#### When NOT to Use

❌ **ApiResponse library tests** (circular dependency):
```php
class ApiResponseTest extends CIUnitTestCase
{
    // ❌ NO CustomAssertionsTrait

    public function testSuccess(): void {
        $result = ApiResponse::success(['id' => 1]);
        $this->assertEquals('success', $result['status']);  // ✅ Use standard assertions
    }
}
```

❌ **Validation tests** (don't use ApiResponse):
```php
class UserValidationTest extends CIUnitTestCase
{
    // ❌ NO CustomAssertionsTrait

    public function testStrongPassword(): void {
        $result = $this->validation->check($password, 'strong_password');
        $this->assertTrue($result);  // ✅ Standard assertion
    }
}
```

❌ **Helper tests** (don't return ApiResponse):
```php
class ValidationHelperTest extends CIUnitTestCase
{
    // ❌ NO CustomAssertionsTrait

    public function testValidateOrFail(): void {
        $this->expectException(ValidationException::class);
        validateOrFail([], 'user', 'store');
    }
}
```

❌ **Model tests** (return entities, not ApiResponse):
```php
class UserModelTest extends CIUnitTestCase
{
    // ❌ NO CustomAssertionsTrait

    public function testFind(): void {
        $user = $this->userModel->find(1);
        $this->assertInstanceOf(UserEntity::class, $user);  // ✅
    }
}
```

**Rule of Thumb:** If the code under test returns an array with `['status' => 'success/error', 'data' => ...]`, use `CustomAssertionsTrait`. Otherwise, don't.

---

## Security Patterns

### ADR-005: File Upload Validation

**Decision:** Validate file metadata BEFORE moving to permanent storage, but AFTER PHP's temporary upload.

**Upload Flow:**
```
1. User uploads file
2. PHP saves to /tmp/phpXXXXX (temporary)
3. ✅ Validate extension
4. ✅ Validate size
5. ✅ Validate MIME type (future)
6. Read from /tmp/phpXXXXX
7. Write to permanent storage
8. Save metadata to database
9. If DB fails → rollback (delete from storage)
```

**Implementation:**
```php
// app/Services/FileService.php
public function upload(array $data): array
{
    $file = $data['file'];  // Already in /tmp/phpXXXXX

    // ✅ Validate BEFORE moving to permanent storage
    if ($file->getSize() > $maxSize) {
        throw new ValidationException('File too large');
    }

    $allowedTypes = explode(',', env('FILE_ALLOWED_TYPES'));
    if (!in_array(strtolower($file->getExtension()), $allowedTypes)) {
        throw new ValidationException('Invalid file type');
    }

    // Now move to permanent storage
    $contents = file_get_contents($file->getTempName());  // Read from /tmp
    $stored = $this->storage->put($path, $contents);      // Write to storage

    // Save to DB
    $fileId = $this->fileModel->insert($metadata);

    // If DB insert fails, rollback
    if (!$fileId) {
        $this->storage->delete($path);  // ✅ Clean up
        throw new \RuntimeException('Failed to save file metadata');
    }

    return ApiResponse::created($file->toArray());
}
```

**Why This is Secure:**
1. Invalid files NEVER reach permanent storage
2. Temporary files are in PHP's managed /tmp (auto-cleaned)
3. Transaction-like behavior (rollback on DB failure)
4. No race conditions

**Why This Looks Like "Validating After Upload":**
- The file IS already uploaded (by PHP to /tmp)
- But NOT yet in permanent storage
- Validations prevent promotion from /tmp to permanent

---

## Service Layer Patterns

### ADR-006: Explicit Parameters vs Auto-Collection

**Decision:** Both patterns are valid depending on clarity and context.

#### Explicit Parameters (Recommended for Non-Standard Operations)

```php
public function sendResetLink(): ResponseInterface
{
    $email = $this->request->getVar('email') ?? '';
    return $this->handleRequest('sendResetLink', ['email' => $email]);
}
```

**Benefits:**
- ✅ Self-documenting: clearly shows required parameter
- ✅ Type-safe: can cast or validate immediately
- ✅ Testable: easy to see what data is passed
- ✅ Explicit > Implicit (Zen of Python)

**Use for:**
- Password reset flows
- Email verification
- Token operations
- Any non-CRUD operation

#### Auto-Collection (Recommended for Standard CRUD)

```php
public function update($id): ResponseInterface
{
    return $this->handleRequest('update', ['id' => $id]);
}
```

**Benefits:**
- ✅ Less boilerplate
- ✅ Standard CRUD is self-evident
- ✅ `collectRequestData()` handles GET, POST, JSON, etc.

**Use for:**
- index, show, store, update, destroy
- Standard resource operations
- When all data comes from request body

**Both are correct.** Choose based on what makes the code clearer.

### ADR-007: Exception-Based Error Handling

**Decision:** Services throw exceptions; controllers catch them.

**Pattern:**
```php
// ✅ Service
public function show(array $data): array
{
    $user = $this->userModel->find($data['id']);

    if (!$user) {
        throw new NotFoundException('User not found');  // ✅ Throw
    }

    return ApiResponse::success($user->toArray());
}

// ✅ Controller
public function show($id): ResponseInterface
{
    return $this->handleRequest('show', ['id' => $id]);
    // handleRequest() catches exceptions and converts to HTTP responses
}
```

**Why NOT return errors?**
```php
// ❌ BAD: Service returns error response
public function show(array $data): array
{
    if (!$user) {
        return ApiResponse::notFound('User not found');  // ❌ Controller concern
    }
    return ApiResponse::success($user);
}
```

**Problems:**
- Services shouldn't know about HTTP status codes
- Can't distinguish between success and error programmatically
- Makes testing harder
- Breaks single responsibility

**Exception Mapping:**
```php
NotFoundException           → 404
AuthenticationException     → 401
AuthorizationException      → 403
ValidationException         → 422
BadRequestException         → 400
ConflictException           → 409
\Exception                  → 500
```

**Handled in:** `ApiController::handleException()`

---

## Summary of Design Principles

1. **Not all controllers are the same** - Infrastructure ≠ Business API
2. **Documentation is separate** - Code ≠ Docs
3. **Tests match purpose** - Unit/Integration/Feature layers
4. **CustomAssertionsTrait only for ApiResponse** - Not for library tests
5. **File validation happens before permanent storage** - After PHP upload
6. **Explicit parameters when clarity helps** - Both patterns valid
7. **Exceptions for errors** - Not return values

**When evaluating code:**
- ❓ Is this different from the pattern? → Check this document
- ❓ Does it have a valid reason? → Probably intentional
- ❓ Not documented here? → Then it might be inconsistent

---

## References

- **Health Checks**: Kubernetes Liveness/Readiness Probes
- **OpenAPI Separation**: Symfony API Platform, Laravel OpenAPI
- **Testing Pyramid**: Martin Fowler's Test Pyramid
- **Exception Handling**: Clean Architecture (Robert C. Martin)

---

## Observability and Governance

This project tracks reliability through request-level indicators and enforces a consistent review bar in pull requests.

- SLO-oriented indicators are exposed in metrics (`p95`, `p99`, error rate, availability, and status-code breakdown).
- The p95 target is configurable with `SLO_API_P95_TARGET_MS`.
- Pull requests use a checklist template in `.github/pull_request_template.md` to verify:
  - quality gates (`cs-check`, `phpstan`, `phpunit`)
  - security-sensitive changes
  - documentation and rollout notes

See the decision record at `docs/architecture/ADR-004-OBSERVABILITY-GOVERNANCE.md`.
