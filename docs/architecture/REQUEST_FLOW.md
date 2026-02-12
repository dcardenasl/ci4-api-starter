# Request Flow

This document walks through a complete HTTP request from start to finish.

---

## Complete Flow Diagram

```
HTTP Request
     │
     ▼
┌─────────────────────────────────────────┐
│ 1. ROUTING                              │
│    - Match URL to controller/method     │
│    - Extract route parameters           │
│    - Assign filters                     │
└─────────────────────────────────────────┘
     │
     ▼
┌─────────────────────────────────────────┐
│ 2. FILTERS (Middleware Pipeline)        │
│    CorsFilter                           │
│         ↓                               │
│    ThrottleFilter (rate limiting)       │
│         ↓                               │
│    JwtAuthFilter (validate token)       │
│         ↓                               │
│    RoleAuthFilter (check permissions)   │
└─────────────────────────────────────────┘
     │
     ▼
┌─────────────────────────────────────────┐
│ 3. CONTROLLER                           │
│    - handleRequest()                    │
│    - collectRequestData()               │
│    - sanitizeInput() (XSS prevention)   │
│    - Delegate to service                │
└─────────────────────────────────────────┘
     │
     ▼
┌─────────────────────────────────────────┐
│ 4. SERVICE                              │
│    - Validate business rules            │
│    - Call model methods                 │
│    - Transform data                     │
│    - Format ApiResponse                 │
└─────────────────────────────────────────┘
     │
     ▼
┌─────────────────────────────────────────┐
│ 5. MODEL                                │
│    - Build query                        │
│    - Execute via query builder          │
│    - Return entities                    │
└─────────────────────────────────────────┘
     │
     ▼
┌─────────────────────────────────────────┐
│ 6. ENTITY                               │
│    - Cast types                         │
│    - Hide sensitive fields              │
│    - Compute properties                 │
└─────────────────────────────────────────┘
     │
     ▼
┌─────────────────────────────────────────┐
│ 7. RESPONSE                             │
│    - ApiResponse formats JSON           │
│    - Controller sets HTTP status        │
│    - Return ResponseInterface           │
└─────────────────────────────────────────┘
     │
     ▼
HTTP Response (JSON)
```

---

## Example: Create User (POST /api/v1/users)

### Request

```bash
POST /api/v1/users HTTP/1.1
Host: localhost:8080
Authorization: Bearer eyJ0eXAiOiJKV1Qi...
Content-Type: application/json

{
  "email": "john@example.com",
  "first_name": "John",
  "last_name": "Doe",
  "password": "SecurePass123!",
  "role": "user"
}
```

### Step-by-Step Execution

#### 1. Routing (app/Config/Routes.php)
```
Match: POST /api/v1/users
Controller: App\Controllers\Api\V1\UserController::create
Filters: ['throttle', 'jwtauth', 'roleauth:admin']
```

#### 2. Filter Pipeline

**ThrottleFilter:**
```php
- Check rate limit: 60 req/min per IP
- Increment counter in cache
- ✅ Pass (45/60 requests used)
```

**JwtAuthFilter:**
```php
- Extract Bearer token from Authorization header
- Decode JWT using JwtService
- Validate signature and expiration
- Check if revoked (blacklist lookup)
- Inject userId=5, userRole='admin' into request
- ✅ Pass
```

**RoleAuthFilter:**
```php
- Required role: 'admin'
- User role: 'admin' (from JWT)
- ✅ Pass (admin >= admin)
```

#### 3. Controller (UserController::create)

```php
public function create(): ResponseInterface
{
    return $this->handleRequest('store');
}

// ApiController::handleRequest()
protected function handleRequest(string $method, ?array $params = null)
{
    try {
        // Collect data
        $data = $this->collectRequestData($params);
        // $data = [
        //     'email' => 'john@example.com',
        //     'first_name' => 'John',
        //     'last_name' => 'Doe',
        //     'password' => 'SecurePass123!',
        //     'role' => 'user',
        //     'user_id' => 5  // From JWT
        // ]

        // Sanitize (XSS prevention)
        $data = $this->sanitizeInput($data);  // strip_tags() on strings

        // Delegate to service
        $result = $this->getService()->store($data);

        // Determine status code
        $status = 201;  // Created

        // Return response
        return $this->respond($result, $status);

    } catch (Exception $e) {
        return $this->handleException($e);
    }
}
```

#### 4. Service (UserService::store)

```php
public function store(array $data): array
{
    // 1. Model validation
    if (!$this->userModel->validate($data)) {
        throw new ValidationException(
            'Validation failed',
            $this->userModel->errors()
        );
    }

    // 2. Business rule: check email uniqueness (already in model rules)

    // 3. Transform data
    $insertData = [
        'email' => $data['email'],
        'first_name' => $data['first_name'],
        'last_name' => $data['last_name'],
        'password' => password_hash($data['password'], PASSWORD_BCRYPT),
        'role' => $data['role'] ?? 'user',
    ];

    // 4. Persist
    $userId = $this->userModel->insert($insertData);

    // 5. Retrieve entity
    $user = $this->userModel->find($userId);

    // 6. Format response
    return ApiResponse::created($user->toArray());
}
```

#### 5. Model (UserModel::insert)

```php
// Automatic validation runs
$validationPassed = $this->validate($insertData);

// Build query
$query = "INSERT INTO users (email, first_name, last_name, password, role, created_at)
          VALUES (?, ?, ?, ?, ?, NOW())";

// Execute via query builder (CodeIgniter does this)
$this->db->query($query, [
    $insertData['email'],
    $insertData['first_name'],
    $insertData['last_name'],
    $insertData['password'],
    $insertData['role'],
]);

// Return insert ID
return $this->db->insertID();  // 42
```

#### 6. Model (UserModel::find)

```php
// Query with soft delete check
$query = "SELECT * FROM users
          WHERE id = ? AND deleted_at IS NULL";

$row = $this->db->query($query, [42])->getRow();

// Convert to entity
$entity = new UserEntity($row);
return $entity;
```

#### 7. Entity (UserEntity::toArray)

```php
public function toArray(...): array
{
    $data = [
        'id' => 42,
        'email' => 'john@example.com',
        'first_name' => 'John',
        'last_name' => 'Doe',
        'role' => 'user',
        'created_at' => '2026-02-11T23:45:00+00:00',
        'updated_at' => '2026-02-11T23:45:00+00:00',
        // 'password' => ... ← HIDDEN
    ];

    // Remove hidden fields
    unset($data['password']);

    return $data;
}
```

#### 8. Service Returns

```php
return ApiResponse::created([
    'id' => 42,
    'email' => 'john@example.com',
    'first_name' => 'John',
    'last_name' => 'Doe',
    'role' => 'user',
    'created_at' => '2026-02-11T23:45:00+00:00',
]);

// ApiResponse::created() returns:
[
    'status' => 'success',
    'message' => 'Resource created successfully',
    'data' => [ /* user data */ ]
]
```

#### 9. Controller Returns

```php
return $this->respond($result, 201);

// ResponseTrait::respond() outputs:
HTTP/1.1 201 Created
Content-Type: application/json

{
  "status": "success",
  "message": "Resource created successfully",
  "data": {
    "id": 42,
    "email": "john@example.com",
    "first_name": "John",
    "last_name": "Doe",
    "role": "user",
    "created_at": "2026-02-11T23:45:00+00:00"
  }
}
```

---

## Timing Breakdown

Typical request timing (development):

| Step | Operation | Time |
|------|-----------|------|
| 1 | Routing | ~1ms |
| 2a | CorsFilter | ~0.5ms |
| 2b | ThrottleFilter (cache lookup) | ~2ms |
| 2c | JwtAuthFilter (decode + blacklist) | ~3ms |
| 2d | RoleAuthFilter | ~0.5ms |
| 3 | Controller (collect + sanitize) | ~1ms |
| 4 | Service (validation + logic) | ~5ms |
| 5 | Model (insert query) | ~8ms |
| 6 | Model (select query) | ~5ms |
| 7 | Entity (toArray) | ~0.5ms |
| 8 | ApiResponse formatting | ~0.5ms |
| **Total** | | **~27ms** |

Production (optimized cache, OpCache enabled): **~15-20ms**

---

## Error Flow

If validation fails at step 4:

```php
// Service throws
throw new ValidationException('Validation failed', [
    'email' => 'Email is already registered'
]);

// Controller catches
catch (Exception $e) {
    return $this->handleException($e);
}

// handleException() returns
HTTP/1.1 422 Unprocessable Entity
Content-Type: application/json

{
  "status": "error",
  "message": "Validation failed",
  "errors": {
    "email": "Email is already registered"
  }
}
```

Exception → HTTP status mapping:
- `ValidationException` → 422
- `NotFoundException` → 404
- `AuthenticationException` → 401
- `AuthorizationException` → 403
- `BadRequestException` → 400
- Others → 500

---

## Key Takeaways

1. **Linear flow** - Request flows through layers in order
2. **Fail fast** - Filters stop bad requests early
3. **Separation** - Each layer has one job
4. **Exceptions for control flow** - Errors bubble up to controller
5. **Consistent responses** - ApiResponse ensures format
6. **Security built-in** - Auth, sanitization, validation at every level
7. **Fast** - Typical requests complete in 15-30ms

---

**Next:** Learn about [FILTERS.md](FILTERS.md) to understand the middleware pipeline in depth.
