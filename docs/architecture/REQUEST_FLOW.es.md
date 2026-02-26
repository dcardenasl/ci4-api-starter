# Flujo de Peticiones

Este documento recorre una petición HTTP completa de principio a fin.

---

## Diagrama de Flujo Completo

```
HTTP Request
     │
     ▼
┌─────────────────────────────────────────┐
│ 1. ROUTING                              │
│    - Coincidir URL con controller/método│
│    - Extraer parámetros de ruta         │
│    - Asignar filtros                    │
└─────────────────────────────────────────┘
     │
     ▼
┌─────────────────────────────────────────┐
│ 2. FILTERS (Pipeline de Middleware)     │
│    Throttle → JwtAuth → RoleAuth        │
└─────────────────────────────────────────┘
     │
     ▼
┌─────────────────────────────────────────┐
│ 3. CONTROLLER (Mapeo)                   │
│    - getDTO() instancia el DTO          │
│    - Constructor DTO valida entrada     │
│    - handleRequest() usa closure        │
└─────────────────────────────────────────┘
     │
     ▼
┌─────────────────────────────────────────┐
│ 4. SERVICE (Lógica Pura de Negocio)     │
│    - Opera sobre DTOs tipados           │
│    - Coordina Modelos                   │
│    - Retorna Entity o ResponseDTO       │
└─────────────────────────────────────────┘
     │
     ▼
┌─────────────────────────────────────────┐
│ 5. MODEL & ENTITY                       │
│    - Operaciones estándar de BD         │
│    - Retorna Entities hidratadas        │
└─────────────────────────────────────────┘
     │
     ▼
┌─────────────────────────────────────────┐
│ 6. NORMALIZACIÓN DE RESPUESTA           │
│    - ApiController::respond()           │
│    - Convierte DTO a array recursivo    │
│    - ApiResponse envuelve en 'data'     │
└─────────────────────────────────────────┘
     │
     ▼
HTTP Response (JSON)
```

---

## Invariantes de Controllers (Obligatorio)

Estas reglas son obligatorias para controllers API que extienden `ApiController`.

1. Usar `getDTO()` para capturar y validar los datos de entrada temprano.
2. Usar `handleRequest(fn() => ...)` para delegar a los servicios.
3. Los servicios deben permanecer "puros" (sin conocimiento de HTTP).
4. Si el status de éxito depende del payload, sobrescribir `resolveSuccessStatus($method, $result)`.
5. Usar `handleException()` para mantener el mapeo de errores consistente.

---

## Ejemplo: Crear Usuario (POST /api/v1/users)

### 1. Mapeo en el Controller

```php
public function create(): ResponseInterface
{
    // La instanciación del DTO falla si la entrada es inválida
    $dto = new UserCreateRequestDTO($this->collectRequestData());

    return $this->handleRequest(
        fn() => $this->getService()->store($dto)
    );
}
```

### 2. Lógica del Servicio (Pura)

```php
public function store(UserCreateRequestDTO $request): UserResponseDTO
{
    // Lógica de negocio usando $request->email tipado, etc.
    $userId = $this->userModel->insert($request->toArray());
    $user = $this->userModel->find($userId);

    return UserResponseDTO::fromArray($user->toArray());
}
```

### 3. Normalización Automática

El `ApiController` detecta que el servicio retornó un `UserResponseDTO`.
1. Llama a `ApiResponse::convertDataToArrays()` recursivamente.
2. La propiedad `firstName` (camelCase) se mapea a `first_name` (snake_case).
3. Envuelve el resultado en `ApiResponse::success()`.
4. Envía la respuesta JSON.

---

## Desglose de Tiempos

Tiempos típicos de petición (desarrollo):

| Paso | Operación | Tiempo |
|------|-----------|------|
| 1 | Routing | ~1ms |
| 2a | CorsFilter | ~0.5ms |
| 2b | ThrottleFilter (búsqueda en cache) | ~2ms |
| 2c | JwtAuthFilter (decodificar + blacklist) | ~3ms |
| 2d | RoleAuthFilter | ~0.5ms |
| 3 | Controller (recopilar + sanitizar) | ~1ms |
| 4 | Service (validación + lógica) | ~5ms |
| 5 | Model (consulta insert) | ~8ms |
| 6 | Model (consulta select) | ~5ms |
| 7 | Entity (toArray) | ~0.5ms |
| 8 | Formateo ApiResponse | ~0.5ms |
| **Total** | | **~27ms** |

Producción (cache optimizado, OpCache habilitado): **~15-20ms**

---

## Flujo de Errores

Si la validación falla en el paso 4:

```php
// Service lanza
throw new ValidationException('Validation failed', [
    'email' => 'Email is already registered'
]);

// Controller captura
catch (Exception $e) {
    return $this->handleException($e);
}

// handleException() retorna
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

Mapeo Exception → estado HTTP:
- `ValidationException` → 422
- `NotFoundException` → 404
- `AuthenticationException` → 401
- `AuthorizationException` → 403
- `BadRequestException` → 400
- Otras → 500

---

## Puntos Clave

1. **Flujo lineal** - La petición fluye a través de las capas en orden
2. **Fallar rápido** - Los filtros detienen peticiones malas temprano
3. **Separación** - Cada capa tiene un trabajo
4. **Excepciones para flujo de control** - Los errores suben en cascada al controller
5. **Respuestas consistentes** - ApiResponse asegura el formato
6. **Seguridad integrada** - Autenticación, sanitización, validación en todos los niveles
7. **Rápido** - Las peticiones típicas se completan en 15-30ms

---

**Siguiente:** Aprende sobre [FILTERS.es.md](FILTERS.es.md) para entender el pipeline de middleware en profundidad.
