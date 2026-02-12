# API Response Format

**Quick Reference** - For complete details see `../ARCHITECTURE.md` section 11.

## ApiResponse Library

All services MUST return arrays using `ApiResponse` static methods.

## Methods

```php
// Success responses
ApiResponse::success($data, $message = null, $meta = [])
ApiResponse::created($data, $message = null)
ApiResponse::deleted($message = null)

// Paginated
ApiResponse::paginated($items, $total, $page, $perPage)

// Error (rarely used - prefer throwing exceptions)
ApiResponse::error($errors, $message = null, $code = null)
ApiResponse::validationError($errors, $message = null)
ApiResponse::notFound($message = null)
ApiResponse::unauthorized($message = null)
ApiResponse::forbidden($message = null)
```

## Response Structure

### Success (200 OK)
```json
{
  "status": "success",
  "message": "User retrieved successfully",
  "data": { /* ... */ }
}
```

### Created (201 Created)
```json
{
  "status": "success",
  "message": "Resource created successfully",
  "data": { /* ... */ }
}
```

### Paginated (200 OK)
```json
{
  "status": "success",
  "data": [ /* items */ ],
  "meta": {
    "total": 100,
    "perPage": 20,
    "page": 1,
    "lastPage": 5,
    "from": 1,
    "to": 20
  }
}
```

### Error (4xx/5xx)
```json
{
  "status": "error",
  "message": "Validation failed",
  "errors": {
    "email": "Email is required",
    "password": "Password too short"
  },
  "code": 422
}
```

**See `../ARCHITECTURE.md` section 11 for complete response formats.**
