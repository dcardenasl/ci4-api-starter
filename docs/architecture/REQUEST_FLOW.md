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
│    - Assign filters                     │
└─────────────────────────────────────────┘
     │
     ▼
┌─────────────────────────────────────────┐
│ 2. FILTERS (Middleware Pipeline)        │
│    Throttle → JwtAuth → RoleAuth        │
└─────────────────────────────────────────┘
     │
     ▼
┌─────────────────────────────────────────┐
│ 3. CONTROLLER (Mapping)                 │
│    - getDTO() instantiates DTO          │
│    - DTO constructor validates input    │
│    - handleRequest() uses closure       │
└─────────────────────────────────────────┘
     │
     ▼
┌─────────────────────────────────────────┐
│ 4. SERVICE (Pure Business Logic)        │
│    - Operates on typed DTOs             │
│    - Coordinates Models                 │
│    - Returns Entity or ResponseDTO      │
└─────────────────────────────────────────┘
     │
     ▼
┌─────────────────────────────────────────┐
│ 5. MODEL & ENTITY                       │
│    - Standard DB operations             │
│    - Return hydrated Entities           │
└─────────────────────────────────────────┘
     │
     ▼
┌─────────────────────────────────────────┐
│ 6. RESPONSE NORMALIZATION               │
│    - ApiController::respond()           │
│    - Recursively convert DTO to array   │
│    - ApiResponse wraps in 'data'        │
└─────────────────────────────────────────┘
     │
     ▼
HTTP Response (JSON)
```

---

## Controller Invariants (Mandatory)

These rules are mandatory for API controllers extending `ApiController`.

1. Use `getDTO()` to capture and validate input data early.
2. Use `handleRequest(fn() => ...)` to delegate to services.
3. Services must remain "pure" (no HTTP knowledge).
4. For success status that depends on payload, override `resolveSuccessStatus($method, $result)`.
5. Use `handleException()` for consistent error mapping.

---

## Example: Create User (POST /api/v1/users)

### 1. Controller Mapping

```php
public function create(): ResponseInterface
{
    // DTO instantiation fails if input is invalid
    $dto = new UserCreateRequestDTO($this->collectRequestData());

    return $this->handleRequest(
        fn() => $this->getService()->store($dto)
    );
}
```

### 2. Service Logic (Pure)

```php
public function store(UserCreateRequestDTO $request): UserResponseDTO
{
    // Business logic using typed $request->email, etc.
    $userId = $this->userModel->insert($request->toArray());
    $user = $this->userModel->find($userId);

    return UserResponseDTO::fromArray($user->toArray());
}
```

### 3. Automatic Normalization

The `ApiController` detects that the service returned a `UserResponseDTO`.
1. Calls `ApiResponse::convertDataToArrays()` recursively.
2. Property `firstName` (camelCase) is mapped to `first_name` (snake_case).
3. Wraps the result in `ApiResponse::success()`.
4. Sends JSON response.

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
