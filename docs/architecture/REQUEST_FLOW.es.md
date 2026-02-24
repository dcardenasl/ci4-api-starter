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
│    CorsFilter                           │
│         ↓                               │
│    ThrottleFilter (rate limiting)       │
│         ↓                               │
│    JwtAuthFilter (validar token)        │
│         ↓                               │
│    RoleAuthFilter (verificar permisos)  │
└─────────────────────────────────────────┘
     │
     ▼
┌─────────────────────────────────────────┐
│ 3. CONTROLLER                           │
│    - handleRequest()                    │
│    - collectRequestData()               │
│    - sanitizeInput() (prevención XSS)   │
│    - Delegar al service                 │
└─────────────────────────────────────────┘
     │
     ▼
┌─────────────────────────────────────────┐
│ 4. SERVICE                              │
│    - Validar reglas de negocio          │
│    - Llamar métodos del modelo          │
│    - Transformar datos                  │
│    - Formatear ApiResponse              │
└─────────────────────────────────────────┘
     │
     ▼
┌─────────────────────────────────────────┐
│ 5. MODEL                                │
│    - Construir consulta                 │
│    - Ejecutar mediante query builder    │
│    - Retornar entities                  │
└─────────────────────────────────────────┘
     │
     ▼
┌─────────────────────────────────────────┐
│ 6. ENTITY                               │
│    - Castear tipos                      │
│    - Ocultar campos sensibles           │
│    - Computar propiedades               │
└─────────────────────────────────────────┘
     │
     ▼
┌─────────────────────────────────────────┐
│ 7. RESPONSE                             │
│    - ApiResponse formatea JSON          │
│    - Controller establece estado HTTP   │
│    - Retornar ResponseInterface         │
└─────────────────────────────────────────┘
     │
     ▼
HTTP Response (JSON)
```

---

## Invariantes de Controllers (Obligatorio)

Estas reglas son obligatorias para controllers API que extienden `ApiController`.

1. Usar `handleRequest()` en endpoints JSON.
2. No duplicar `collectRequestData()`, `sanitizeInput()`, `try/catch` ni invocación directa al service en cada método.
3. Si el status de éxito depende del payload (por ejemplo `200` vs `202`), sobrescribir `resolveSuccessStatus($method, $result)` en el controller.
4. Mantener decisiones de negocio en services y decisiones HTTP/transporte en controllers.
5. Usar `handleException()` para mantener el mapeo de errores consistente.

### Patrón correcto para status dinámico

```php
// Controller
public function googleLogin(): ResponseInterface
{
    return $this->handleRequest('loginWithGoogleToken');
}

protected function resolveSuccessStatus(string $method, array $result): int
{
    if ($method === 'loginWithGoogleToken') {
        $pending = ($result['data']['user']['status'] ?? null) === 'pending_approval';
        $hasToken = isset($result['data']['access_token']);
        if ($pending && ! $hasToken) {
            return 202;
        }
    }

    return parent::resolveSuccessStatus($method, $result);
}
```

### Anti-patrón (no hacer)

```php
// ❌ Reimplementar el pipeline de handleRequest en cada método
public function someAction(): ResponseInterface
{
    try {
        $data = $this->collectRequestData();
        $result = $this->getService()->someMethod($data);
        return $this->respond($result, 200);
    } catch (Exception $e) {
        return $this->handleException($e);
    }
}
```

### Excepciones explícitas

Las únicas excepciones aceptadas son:
- Endpoints que deben devolver respuestas de transporte no JSON (descarga/stream de archivos).
- Controllers de infraestructura que intencionalmente no extienden `ApiController` (por ejemplo métricas/observabilidad).

---

## Ejemplo: Crear Usuario (POST /api/v1/users)

### Petición

```bash
POST /api/v1/users HTTP/1.1
Host: localhost:8080
Authorization: Bearer eyJ0eXAiOiJKV1Qi...
Content-Type: application/json

{
  "email": "john@example.com",
  "first_name": "John",
  "last_name": "Doe",
  "role": "user"
}
```

### Ejecución Paso a Paso

#### 1. Routing (app/Config/Routes.php)
```
Coincidencia: POST /api/v1/users
Controller: App\Controllers\Api\V1\UserController::create
Filters: ['throttle', 'jwtauth', 'roleauth:admin']
```

#### 2. Pipeline de Filtros

**ThrottleFilter:**
```php
// Verificar límite de tasa: 60 req/min por IP
// Incrementar contador en cache
// ✅ Pasar (45/60 peticiones usadas)
```

**JwtAuthFilter:**
```php
// Extraer token Bearer del header Authorization
// Decodificar JWT usando JwtService
// Validar firma y expiración
// Verificar si está revocado (búsqueda en blacklist)
// Inyectar userId=5, userRole='admin' en la petición
// ✅ Pasar
```

**RoleAuthFilter:**
```php
// Rol requerido: 'admin'
// Rol del usuario: 'admin' (del JWT)
// ✅ Pasar (admin >= admin)
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
        // Recopilar datos
        $data = $this->collectRequestData($params);
        // $data = [
        //     'email' => 'john@example.com',
        //     'first_name' => 'John',
        //     'last_name' => 'Doe',
        //     'role' => 'user',
        //     'user_id' => 5  // Del JWT
        // ]

        // Sanitizar (prevención XSS)
        $data = $this->sanitizeInput($data);  // strip_tags() en strings

        // Delegar al service
        $result = $this->getService()->store($data);

        // Determinar código de estado
        $status = 201;  // Created

        // Retornar respuesta
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
    // 1. Validación del modelo
    if (!$this->userModel->validate($data)) {
        throw new ValidationException(
            'Validation failed',
            $this->userModel->errors()
        );
    }

    // 2. Regla de negocio: el admin no puede enviar contraseña en el request

    // 3. Transformar datos
    $generatedPassword = bin2hex(random_bytes(24)) . 'Aa1!';
    $insertData = [
        'email' => $data['email'],
        'first_name' => $data['first_name'],
        'last_name' => $data['last_name'],
        'password' => password_hash($generatedPassword, PASSWORD_BCRYPT),
        'role' => $data['role'] ?? 'user',
        'status' => 'invited',
        'approved_at' => date('Y-m-d H:i:s'),
        'invited_at' => date('Y-m-d H:i:s'),
        'email_verified_at' => date('Y-m-d H:i:s'),
    ];

    // 4. Persistir
    $userId = $this->userModel->insert($insertData);

    // 5. Recuperar entity
    $user = $this->userModel->find($userId);

    // 6. Formatear respuesta
    return ApiResponse::created($user->toArray());
}
```

#### 5. Model (UserModel::insert)

```php
// La validación automática se ejecuta
$validationPassed = $this->validate($insertData);

// Construir consulta
$query = "INSERT INTO users (email, first_name, last_name, password, role, created_at)
          VALUES (?, ?, ?, ?, ?, NOW())";

// Ejecutar mediante query builder (CodeIgniter hace esto)
$this->db->query($query, [
    $insertData['email'],
    $insertData['first_name'],
    $insertData['last_name'],
    $insertData['password'],
    $insertData['role'],
]);

// Retornar ID insertado
return $this->db->insertID();  // 42
```

#### 6. Model (UserModel::find)

```php
// Consulta con verificación de soft delete
$query = "SELECT * FROM users
          WHERE id = ? AND deleted_at IS NULL";

$row = $this->db->query($query, [42])->getRow();

// Convertir a entity
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
        // 'password' => ... ← OCULTO
    ];

    // Remover campos ocultos
    unset($data['password']);

    return $data;
}
```

#### 8. Service Retorna

```php
return ApiResponse::created([
    'id' => 42,
    'email' => 'john@example.com',
    'first_name' => 'John',
    'last_name' => 'Doe',
    'role' => 'user',
    'created_at' => '2026-02-11T23:45:00+00:00',
]);

// ApiResponse::created() retorna:
[
    'status' => 'success',
    'message' => 'Resource created successfully',
    'data' => [ /* datos del usuario */ ]
]
```

#### 9. Controller Retorna

```php
return $this->respond($result, 201);

// ResponseTrait::respond() genera:
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
