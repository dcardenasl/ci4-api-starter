# API Response Format

The API follows a strict, predictable response format. All business endpoints return JSON wrapped in a standard structure.

---

## Automatic Normalization

The `ApiController` handles the final response structure. Services return pure data (DTOs, Entities, arrays, or `OperationResult`), and the controller automatically:
1. Wraps the result in `ApiResponse::success()`.
2. Recursively converts all DTOs to associative arrays.
3. Maps property names from camelCase (backend) to snake_case (frontend contract).

For CRUD services extending `BaseCrudService`, `index()` returns a `PaginatedResponseDTO`. The controller detects this DTO shape and emits the canonical paginated envelope (`data` + `meta`).

## OperationResult Mapping

For command-like flows, services can return `App\Support\OperationResult`.

`ApiController` maps states explicitly:

1. `OperationResult::success(...)` -> HTTP success (default `200`, or explicit override).
2. `OperationResult::accepted(...)` -> HTTP `202` by default.
3. `OperationResult::error(...)` -> standard error envelope with explicit status.

Accepted/pending behavior is not inferred from message text.

---

## ApiResponse Library

While `ApiController` handles standard cases, `ApiResponse` can be used explicitly for custom needs.

### Core Structure

```json
{
  "status": "success",
  "message": "Optional human-readable message",
  "data": { /* Main payload */ },
  "meta": { /* Metadata, e.g., pagination */ }
}
```

### Methods

```php
// Success responses
ApiResponse::success($data, $message = null, $meta = [])
ApiResponse::created($data, $message = null)
ApiResponse::deleted($message = null)

// Paginated
ApiResponse::paginated($items, $total, $page, $perPage)
```

---

## Contract Examples

### Standard Success (200 OK)
```json
{
  "status": "success",
  "data": {
    "id": 1,
    "email": "user@example.com",
    "first_name": "John"
  }
}
```

### Paginated Result (200 OK)
```json
{
  "status": "success",
  "data": [ /* items */ ],
  "meta": {
    "total": 100,
    "per_page": 20,
    "page": 1,
    "last_page": 5
  }
}
```

### Error (4xx/5xx)
```json
{
  "status": "error",
  "message": "Validation failed",
  "errors": {
    "email": "Email is already registered"
  },
  "code": 422
}
```

## Exceptions to This Contract

- Operational health endpoints (`GET /health`, `GET /ping`) use a flat monitoring payload.
- File downloads or streams return raw binary data with appropriate `Content-Type`.
- Rate limit responses (`429`) include a top-level `retry_after` field.
