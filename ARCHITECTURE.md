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
        $dto = $this->getDTO(UserIndexRequestDTO::class);
        return $this->handleRequest(fn() => $this->getService()->index($dto));
    }
}
```

**Benefits:**
- ✅ Centralized exception handling
- ✅ Automatic request-to-DTO mapping and validation
- ✅ Standardized response format with recursive DTO normalization
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

### ADR-002: Integrated Living Documentation

**Decision:** OpenAPI schemas live directly within DTO classes as PHP 8 attributes. Endpoint definitions remain in `app/Documentation/`.

**Structure:**
```
app/DTO/
├── Request/
│   └── Auth/
│       └── LoginRequestDTO.php    # Contains #[OA\Schema] for request
└── Response/
    └── Users/
        └── UserResponseDTO.php    # Contains #[OA\Schema] for response

app/Documentation/
├── Auth/
│   └── AuthEndpoints.php          # References schemas in DTOs
└── Users/
    └── UserEndpoints.php          # References schemas in DTOs
```

**Generation:**
```bash
php spark swagger:generate  # Scans app/DTO/ and app/Documentation/
```

**Why Integrated DTO Documentation?**

#### 1. Single Source of Truth
The code *is* the documentation. If you add a property to a `UserResponseDTO`, you add the `#[OA\Property]` next to it. They can never go out of sync.

#### 2. Automatic Validation
The `swagger:generate` command will fail if a DTO references a schema that doesn't exist or if property types mismatch, acting as a compile-time check for documentation.

#### 3. Cleaner Architecture
We eliminated "phantom classes" in `app/Documentation/` that only existed to hold annotations. Documentation is now an intrinsic part of the data contract.

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

#### Unit Tests (470 tests)

**Characteristics:**
- No database
- Mocked dependencies
- Fast (< 1 second total)

**Example:**
```php
class AuthServiceTest extends CIUnitTestCase
{
    protected function setUp(): void {
        $this->mockUserModel = $this->createMock(UserModel::class);
        $this->mockJwtService = $this->createMock(JwtServiceInterface::class);
        $this->service = new AuthService($this->mockUserModel, $this->mockJwtService);
    }

    public function testLoginWithValidCredentials(): void {
        $dto = new LoginRequestDTO(['email' => 'test@example.com', 'password' => 'pass']);
        $result = $this->service->login($dto);
        
        $this->assertInstanceOf(LoginResponseDTO::class, $result);
        $this->assertEquals('test@example.com', $result->user['email']);
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

**Decision:** Only use `CustomAssertionsTrait` in **Feature/Integration tests** that verify the final `ApiResponse` structure returned by the controller. **Service unit tests** should use standard object/type assertions since they return DTOs.

#### When to Use

✅ **Feature/Controller tests** (HTTP responses with ApiResponse body):
```php
class UserControllerTest extends FeatureTestCase
{
    use CustomAssertionsTrait;

    public function testIndex(): void {
        $response = $this->get('/api/v1/users');
        $data = json_decode($response->getBody(), true);
        $this->assertPaginatedResponse($data);
    }
}
```

#### When NOT to Use

❌ **Service unit tests** (they return DTO objects):
```php
class UserServiceTest extends CIUnitTestCase
{
    // ❌ NO CustomAssertionsTrait
    public function testShow(): void {
        $result = $this->service->show(['id' => 1]);
        $this->assertInstanceOf(UserResponseDTO::class, $result);
    }
}
```

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
public function upload(FileUploadRequestDTO $request): FileResponseDTO
{
    $file = $request->file;

    // ✅ Validate BEFORE moving to permanent storage
    if ($file->getSize() > $maxSize) {
        throw new ValidationException('File too large');
    }

    $allowedTypes = explode(',', env('FILE_ALLOWED_TYPES'));
    if (!in_array(strtolower($file->getExtension()), $allowedTypes)) {
        throw new ValidationException('Invalid file type');
    }

    // Now move to permanent storage
    $contents = file_get_contents($file->getTempName());
    $stored = $this->storage->put($path, $contents);

    // Save to DB
    $fileId = $this->fileModel->insert($metadata);

    // If DB insert fails, rollback
    if (!$fileId) {
        $this->storage->delete($path);  // ✅ Clean up
        throw new \RuntimeException('Failed to save file metadata');
    }

    return FileResponseDTO::fromArray($this->fileModel->find($fileId)->toArray());
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

### ADR-006: Data Transfer Objects (DTOs)

**Decision:** Use PHP 8.2 `readonly classes` for all data transfer between Controllers and Services.

**Pattern:**
```php
// Request DTO (Auto-validated)
readonly class RegisterRequestDTO {
    public function __construct(array $data) {
        validateOrFail($data, 'auth', 'register');
        $this->email = $data['email'];
        // ...
    }
}

// Service Method
public function register(RegisterRequestDTO $request): RegisterResponseDTO {
    // Business logic...
}
```

**Benefits:**
- ✅ **Type Safety:** Eliminate string keys and array guessing.
- ✅ **Immutability:** Data cannot be modified after validation.
- ✅ **Early Validation:** The service never receives invalid data because the DTO fails in its constructor.
- ✅ **Contract Clarity:** Response DTOs explicitly define what fields the frontend receives.

**When to use:**
- Every API endpoint that receives or returns structured data.
- List operations (use QueryDTOs for filters/pagination).

### ADR-007: Pure Services & Exception-Based Error Handling

**Decision:** Services are "pure" (decoupled from HTTP/API concerns). They return DTOs/Entities and throw exceptions for errors.

**Pattern:**
```php
// ✅ Service
public function show(array $data): UserResponseDTO
{
    $user = $this->userModel->find($data['id']);

    if (!$user) {
        throw new NotFoundException('User not found');
    }

    return UserResponseDTO::fromArray($user->toArray());
}

// ✅ Controller
public function show($id): ResponseInterface
{
    return $this->handleRequest(fn() => $this->getService()->show(['id' => $id]));
}
```

**Why Decouple from ApiResponse?**
- Services shouldn't know about `status` codes or JSON wrapping.
- Higher reusability (CLI commands can use the same service).
- Cleaner testing (no need to parse `ApiResponse` arrays).

**Automatic Normalization:**
The `ApiController` automatically wraps DTOs/Arrays in `ApiResponse::success()` and recursively converts DTOs to arrays before final JSON encoding.

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
