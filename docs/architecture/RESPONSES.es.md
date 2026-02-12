# Formato de Respuesta API

**Referencia Rápida** - Para detalles completos ver `../ARCHITECTURE.md` sección 11.

## Librería ApiResponse

Todos los servicios DEBEN retornar arrays usando métodos estáticos de `ApiResponse`.

## Métodos

```php
// Respuestas de éxito
ApiResponse::success($data, $message = null, $meta = [])
ApiResponse::created($data, $message = null)
ApiResponse::deleted($message = null)

// Paginado
ApiResponse::paginated($items, $total, $page, $perPage)

// Error (raramente usado - preferir lanzar excepciones)
ApiResponse::error($errors, $message = null, $code = null)
ApiResponse::validationError($errors, $message = null)
ApiResponse::notFound($message = null)
ApiResponse::unauthorized($message = null)
ApiResponse::forbidden($message = null)
```

## Estructura de Respuesta

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

**Ver `../ARCHITECTURE.md` sección 11 para formatos completos de respuesta.**
