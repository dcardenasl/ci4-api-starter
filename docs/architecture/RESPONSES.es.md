# Formato de Respuesta API

La API sigue un formato de respuesta estricto y predecible. Todos los endpoints de negocio retornan JSON envuelto en una estructura estándar.

---

## Normalización Automática

El `ApiController` maneja la estructura final de la respuesta. Los servicios retornan datos puros (DTOs, Entidades, arreglos u `OperationResult`), y el controlador automáticamente:
1. Envuelve el resultado en `ApiResponse::success()`.
2. Convierte recursivamente todos los DTOs en arreglos asociativos.
3. Mapea los nombres de propiedades de camelCase (backend) a snake_case (contrato frontend).

## Mapeo de `OperationResult`

Para flujos tipo comando, los servicios pueden retornar `App\Support\OperationResult`.

`ApiController` mapea explícitamente:

1. `OperationResult::success(...)` -> éxito HTTP (por defecto `200` o override explícito).
2. `OperationResult::accepted(...)` -> HTTP `202` por defecto.
3. `OperationResult::error(...)` -> envoltura estándar de error con status explícito.

El comportamiento accepted/pending no se infiere desde texto de mensajes.

---

## Librería ApiResponse

Aunque el `ApiController` maneja los casos estándar, `ApiResponse` puede usarse explícitamente para necesidades personalizadas.

### Estructura Core

```json
{
  "status": "success",
  "message": "Mensaje opcional para humanos",
  "data": { /* Payload principal */ },
  "meta": { /* Metadatos, ej. paginación */ }
}
```

### Métodos

```php
// Respuestas de éxito
ApiResponse::success($data, $message = null, $meta = [])
ApiResponse::created($data, $message = null)
ApiResponse::deleted($message = null)

// Paginado
ApiResponse::paginated($items, $total, $page, $perPage)
```

---

## Ejemplos de Contrato

### Éxito Estándar (200 OK)
```json
{
  "status": "success",
  "data": {
    "id": 1,
    "email": "user@example.com",
    "first_name": "Juan"
  }
}
```

### Resultado Paginado (200 OK)
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

## Excepciones a Este Contrato

- Los endpoints operativos de health (`GET /health`, `GET /ping`) usan un payload plano de monitoreo.
- Las descargas o streams de archivos retornan datos binarios con el `Content-Type` adecuado.
- Las respuestas de rate limit (`429`) incluyen un campo `retry_after` en el nivel raíz.
