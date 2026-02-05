# Revisión Arquitectónica Profunda - CI4 API Starter

**Fecha:** 2026-02-04
**Versión Analizada:** dev (commit e77f8ea)

---

## Resumen Ejecutivo

Este proyecto es una **API REST bien arquitecturada** construida sobre CodeIgniter 4, siguiendo un patrón de **arquitectura por capas limpia**. Demuestra prácticas de ingeniería de software maduras con buena separación de responsabilidades y características de seguridad robustas.

| Aspecto | Calificación | Notas |
|---------|-------------|-------|
| Separación de responsabilidades | ⭐⭐⭐⭐ | Clara separación Controller→Service→Model |
| Seguridad | ⭐⭐⭐⭐⭐ | Protección timing-attack, JWT con revocación, sanitización |
| Testabilidad | ⭐⭐⭐⭐ | 533 tests, buena cobertura, aunque DI podría mejorar |
| Mantenibilidad | ⭐⭐⭐⭐ | Código limpio, pero algunos acoplamientos |
| Escalabilidad | ⭐⭐⭐ | Funcional pero con limitaciones estructurales |
| Documentación | ⭐⭐⭐⭐ | OpenAPI modular, guías completas |

---

## 1. Patrones de Diseño Identificados

### 1.1 Patrones Correctamente Implementados

#### ✅ Template Method Pattern (ApiController)
```
Ubicación: app/Controllers/ApiController.php
```

El `ApiController` define el esqueleto del algoritmo (`handleRequest`) y delega pasos específicos a las subclases:

```php
abstract class ApiController {
    abstract protected function getService(): object;
    abstract protected function getSuccessStatus(string $method): int;

    protected function handleRequest(string $method, ?array $item = null): ResponseInterface {
        // Algoritmo fijo: collect → delegate → respond
        $requestData = $this->collectRequestData($item);
        $result = $this->getService()->$method($requestData);
        return $this->respond($result, $status);
    }
}
```

**Beneficios:**
- Elimina duplicación en controladores hijos
- Centraliza manejo de excepciones
- Garantiza consistencia en el flujo de request/response

---

#### ✅ Strategy Pattern (Traits de Modelo)
```
Ubicación: app/Traits/Filterable.php, Searchable.php, Auditable.php
```

Los traits encapsulan algoritmos intercambiables que los modelos pueden usar selectivamente:

```php
class UserModel extends Model {
    use Filterable, Searchable;  // Estrategias de filtrado y búsqueda
}

class FileModel extends Model {
    use Auditable;  // Estrategia de auditoría
}
```

**Beneficios:**
- Comportamiento composable
- Evita herencia múltiple
- Reutilización sin acoplamiento

---

#### ✅ Chain of Responsibility (Filters/Middleware)
```
Ubicación: app/Filters/*
```

Los filtros HTTP forman una cadena que procesa requests secuencialmente:

```
Request → LocaleFilter → CorsFilter → ThrottleFilter → JwtAuthFilter → RoleAuthFilter → Controller
```

**Beneficios:**
- Concerns separados (autenticación, autorización, rate limiting)
- Fácil de agregar/remover filtros
- Cada filtro puede detener la cadena

---

#### ✅ Factory Method (Config\Services)
```
Ubicación: app/Config/Services.php
```

El Service Locator actúa como factory para crear servicios:

```php
public static function userService(bool $getShared = true) {
    if ($getShared) {
        return static::getSharedInstance('userService');
    }
    return new UserService(new UserModel());
}
```

**Beneficios:**
- Punto centralizado de creación
- Soporte para singletons
- Abstracción de dependencias

---

#### ✅ Data Transfer Object (ApiResponse)
```
Ubicación: app/Libraries/ApiResponse.php
```

Estructura consistente para transferir datos entre capas:

```php
ApiResponse::success($data, $message, $meta);
ApiResponse::error($errors, $message, $code);
ApiResponse::paginated($items, $total, $page, $perPage);
```

**Beneficios:**
- Formato de respuesta estandarizado
- Separación entre datos y presentación
- Facilita testing y documentación

---

#### ✅ Custom Exception Hierarchy
```
Ubicación: app/Exceptions/*
```

Jerarquía de excepciones que mapea directamente a códigos HTTP:

```
ApiException (abstract)
├── NotFoundException (404)
├── AuthenticationException (401)
├── AuthorizationException (403)
├── BadRequestException (400)
├── ConflictException (409)
├── ValidationException (422)
└── TooManyRequestsException (429)
```

**Beneficios:**
- Manejo de errores semántico
- Conversión automática a HTTP status
- Serialización a JSON consistente

---

### 1.2 Patrones Parcialmente Implementados

#### ⚠️ Interface Segregation (Parcial)
```
Ubicación: app/Interfaces/*
```

Las interfaces existen pero son demasiado amplias:

```php
interface UserServiceInterface {
    public function index(array $data): array;
    public function show(array $data): array;
    public function store(array $data): array;
    public function update(array $data): array;
    public function destroy(array $data): array;
    public function login(array $data): array;
    public function register(array $data): array;
    public function loginWithToken(array $data): array;
    public function registerWithToken(array $data): array;
}
```

**Problema:** La interfaz mezcla CRUD con autenticación. Un cliente que solo necesita autenticación depende de métodos CRUD.

---

#### ⚠️ Dependency Injection (Parcial)
```
Ubicación: app/Config/Services.php
```

Se usa Service Locator en lugar de DI real:

```php
// En UserService (antipatrón - acoplamiento directo)
$jwtService = \Config\Services::jwtService();
$refreshTokenService = new RefreshTokenService(new \App\Models\RefreshTokenModel());
```

**Problema:** Los servicios crean sus propias dependencias en lugar de recibirlas inyectadas.

---

## 2. Antipatrones Detectados

### 2.1 🔴 Service Locator Antipattern
```
Severidad: ALTA
Ubicación: app/Services/UserService.php:314, 318, 349, 353, 358
```

**Problema:**
```php
public function loginWithToken(array $data): array {
    // ...
    $jwtService = \Config\Services::jwtService();  // Service Locator
    $refreshTokenService = new RefreshTokenService(new \App\Models\RefreshTokenModel());  // Creación directa
}
```

**Por qué es malo:**
1. **Dependencias ocultas**: No se ve qué necesita el servicio sin leer el código
2. **Difícil de testear**: Requiere mockear llamadas estáticas
3. **Acoplamiento temporal**: La dependencia se resuelve en runtime, no en construcción
4. **Violación de SRP**: El servicio gestiona sus propias dependencias

**Impacto:**
- Los tests unitarios requieren mocks complejos
- Cambiar una dependencia requiere modificar código interno
- No hay garantía de que las dependencias existan al construir el objeto

---

### 2.2 🔴 Mixed Return Types (Inconsistencia)
```
Severidad: MEDIA-ALTA
Ubicación: Todos los servicios
```

**Problema:**
```php
// A veces retorna array con error
if (!isset($data['id'])) {
    return ApiResponse::error(['id' => 'Required'], 'Invalid request');
}

// A veces lanza excepción
if (!$this->userModel->find($id)) {
    throw new NotFoundException('User not found');
}
```

**Por qué es malo:**
1. **Comportamiento impredecible**: El consumidor no sabe si esperar excepción o array de error
2. **Código defensivo**: Obliga a verificar tanto `isset($result['errors'])` como try-catch
3. **Documentación confusa**: Difícil documentar el contrato del método

---

### 2.3 🟡 God Service (UserService)
```
Severidad: MEDIA
Ubicación: app/Services/UserService.php (373 líneas)
```

**Problema:** UserService tiene demasiadas responsabilidades:
- CRUD de usuarios
- Autenticación (login)
- Registro
- Generación de tokens
- Envío de emails de verificación
- Validación de reglas de negocio

**Violaciones:**
- Single Responsibility Principle
- Un cambio en autenticación afecta el mismo archivo que un cambio en CRUD

---

### 2.4 🟡 Primitive Obsession
```
Severidad: MEDIA
Ubicación: Todos los métodos de servicios
```

**Problema:** Todo se pasa como `array $data`:

```php
public function show(array $data): array;
public function store(array $data): array;
public function login(array $data): array;
```

**Por qué es malo:**
1. **Sin type safety**: Cualquier cosa puede estar en el array
2. **Sin autocompletado**: El IDE no puede ayudar
3. **Documentación implícita**: Hay que leer la implementación para saber qué campos necesita
4. **Validación dispersa**: Cada método valida manualmente su input

---

### 2.5 🟡 Missing Repository Layer
```
Severidad: MEDIA
Ubicación: Arquitectura general
```

**Problema:** Los servicios acceden directamente a los modelos de CI4:

```
Controller → Service → Model (CI4)
```

**Por qué es malo:**
1. **Acoplamiento al framework**: Cambiar ORM requiere modificar servicios
2. **Lógica de queries en servicios**: `$this->userModel->where(...)->first()`
3. **Difícil de testear**: Requiere base de datos real o mocks complejos

---

### 2.6 🟡 Static Helper Methods
```
Severidad: BAJA-MEDIA
Ubicación: app/Libraries/ApiResponse.php
```

**Problema:** `ApiResponse` usa métodos estáticos exclusivamente:

```php
return ApiResponse::success($data);
return ApiResponse::error($errors);
```

**Por qué es malo:**
1. **Difícil de extender**: No se puede inyectar una implementación diferente
2. **Testing**: No se puede mockear sin herramientas especiales
3. **Hidden dependency**: No aparece en constructor

---

### 2.7 🟢 Magic Properties en Request
```
Severidad: BAJA
Ubicación: app/Filters/JwtAuthFilter.php:53-54
```

**Problema:**
```php
$request->userId = $decoded->uid;
$request->userRole = $decoded->role;
```

**Por qué es malo:**
1. **Sin type hints**: Las propiedades no están declaradas
2. **Frágil**: Si el filtro no se ejecuta, las propiedades no existen
3. **No IDE-friendly**: Sin autocompletado

---

## 3. Fortalezas de la Arquitectura

### 3.1 ✅ Seguridad Robusta

| Feature | Implementación |
|---------|---------------|
| Timing-attack protection | `password_verify()` siempre ejecutado |
| XSS prevention | `strip_tags()` en sanitizeInput |
| Mass assignment protection | `$protectFields = true` en modelos |
| SQL injection | Query builder exclusivo |
| RBAC | RoleAuthorizationFilter con jerarquía |
| Token revocation | Blacklist con JTI tracking |
| Rate limiting | Por IP y por usuario |
| CORS | Configuración por entorno |

### 3.2 ✅ Testing Comprehensivo

- **533 tests** organizados en 3 niveles
- **Custom assertions trait** para consistencia
- **Data providers** para pruebas paramétricas
- **Separación clara** unit/integration/controller

### 3.3 ✅ API Consistente

- Formato de respuesta estandarizado
- Códigos HTTP semánticos
- Paginación uniforme
- OpenAPI documentation modular

### 3.4 ✅ Manejo de Errores Centralizado

- Excepciones tipadas por escenario
- Conversión automática a JSON
- Logging estructurado
- No exposición de detalles internos

---

## 4. Plan de Mejoras

### Fase 1: Dependency Injection (Prioridad: CRÍTICA)

**Objetivo:** Eliminar Service Locator y acoplamientos directos.

#### 1.1 Inyectar dependencias en constructores de servicios

**Antes:**
```php
class UserService {
    public function __construct(UserModel $userModel) {
        $this->userModel = $userModel;
    }

    public function loginWithToken(array $data): array {
        $jwtService = \Config\Services::jwtService();  // ❌
        $refreshTokenService = new RefreshTokenService(...);  // ❌
    }
}
```

**Después:**
```php
class UserService {
    public function __construct(
        protected UserModel $userModel,
        protected JwtServiceInterface $jwtService,
        protected RefreshTokenServiceInterface $refreshTokenService,
        protected VerificationServiceInterface $verificationService
    ) {}

    public function loginWithToken(array $data): array {
        // Usar $this->jwtService, $this->refreshTokenService
    }
}
```

#### 1.2 Actualizar Services.php para inyectar dependencias

```php
public static function userService(bool $getShared = true) {
    if ($getShared) {
        return static::getSharedInstance('userService');
    }

    return new UserService(
        new UserModel(),
        static::jwtService(),
        static::refreshTokenService(),
        static::verificationService()
    );
}
```

**Archivos a modificar:**
- `app/Services/UserService.php`
- `app/Config/Services.php`

---

### Fase 2: Interface Segregation (Prioridad: ALTA)

**Objetivo:** Dividir interfaces grandes en interfaces cohesivas.

#### 2.1 Dividir UserServiceInterface

**Antes:**
```php
interface UserServiceInterface {
    // CRUD + Auth mezclados
    public function index(array $data): array;
    public function show(array $data): array;
    public function store(array $data): array;
    public function update(array $data): array;
    public function destroy(array $data): array;
    public function login(array $data): array;
    public function register(array $data): array;
    public function loginWithToken(array $data): array;
    public function registerWithToken(array $data): array;
}
```

**Después:**
```php
interface UserCrudServiceInterface {
    public function index(array $data): array;
    public function show(array $data): array;
    public function store(array $data): array;
    public function update(array $data): array;
    public function destroy(array $data): array;
}

interface AuthenticationServiceInterface {
    public function login(array $data): array;
    public function loginWithToken(array $data): array;
}

interface RegistrationServiceInterface {
    public function register(array $data): array;
    public function registerWithToken(array $data): array;
}

// UserService puede implementar todas si es necesario
class UserService implements
    UserCrudServiceInterface,
    AuthenticationServiceInterface,
    RegistrationServiceInterface
```

**Archivos a crear:**
- `app/Interfaces/UserCrudServiceInterface.php`
- `app/Interfaces/AuthenticationServiceInterface.php`
- `app/Interfaces/RegistrationServiceInterface.php`

---

### Fase 3: Request/Response Objects (Prioridad: ALTA)

**Objetivo:** Reemplazar `array $data` con DTOs tipados.

#### 3.1 Crear DTOs para requests

```php
// app/DTO/Request/LoginRequest.php
final class LoginRequest {
    public function __construct(
        public readonly string $username,
        public readonly string $password
    ) {}

    public static function fromArray(array $data): self {
        return new self(
            username: $data['username'] ?? '',
            password: $data['password'] ?? ''
        );
    }

    public function validate(): array {
        $errors = [];
        if (empty($this->username)) {
            $errors['username'] = 'Username is required';
        }
        if (empty($this->password)) {
            $errors['password'] = 'Password is required';
        }
        return $errors;
    }
}
```

#### 3.2 Crear DTOs para responses

```php
// app/DTO/Response/LoginResponse.php
final class LoginResponse {
    public function __construct(
        public readonly string $accessToken,
        public readonly string $refreshToken,
        public readonly int $expiresIn,
        public readonly UserDTO $user
    ) {}

    public function toArray(): array {
        return [
            'access_token' => $this->accessToken,
            'refresh_token' => $this->refreshToken,
            'expires_in' => $this->expiresIn,
            'user' => $this->user->toArray(),
        ];
    }
}
```

**Archivos a crear:**
- `app/DTO/Request/LoginRequest.php`
- `app/DTO/Request/RegisterRequest.php`
- `app/DTO/Request/CreateUserRequest.php`
- `app/DTO/Request/UpdateUserRequest.php`
- `app/DTO/Response/LoginResponse.php`
- `app/DTO/Response/UserResponse.php`
- `app/DTO/Response/PaginatedResponse.php`

---

### Fase 4: Consistencia en Manejo de Errores (Prioridad: ALTA)

**Objetivo:** Unificar el patrón de manejo de errores.

#### 4.1 Regla clara: Excepciones para todo

**Opción recomendada:** Convertir todos los errores a excepciones:

```php
// Antes (inconsistente)
if (!isset($data['id'])) {
    return ApiResponse::error(['id' => 'Required']);
}

// Después (consistente)
if (!isset($data['id'])) {
    throw new BadRequestException('ID is required', ['id' => 'ID is required']);
}
```

#### 4.2 Crear ValidationException mejorada

```php
class ValidationException extends ApiException {
    protected int $statusCode = 422;

    public function __construct(array $errors, ?string $message = null) {
        parent::__construct(
            $message ?? 'Validation failed',
            $errors
        );
    }
}

// Uso
if (!$this->userModel->validate($data)) {
    throw new ValidationException($this->userModel->errors());
}
```

---

### Fase 5: Separación de UserService (Prioridad: MEDIA)

**Objetivo:** Dividir el God Service en servicios cohesivos.

#### 5.1 Extraer AuthService

```php
// app/Services/AuthService.php
class AuthService implements AuthenticationServiceInterface {
    public function __construct(
        protected UserModel $userModel,
        protected JwtServiceInterface $jwtService,
        protected RefreshTokenServiceInterface $refreshTokenService
    ) {}

    public function login(LoginRequest $request): LoginResponse {
        // Lógica de autenticación
    }

    public function loginWithToken(LoginRequest $request): LoginResponse {
        // Lógica con token
    }
}
```

#### 5.2 Mantener UserService solo para CRUD

```php
// app/Services/UserService.php (reducido)
class UserService implements UserCrudServiceInterface {
    public function __construct(
        protected UserModel $userModel
    ) {}

    // Solo CRUD
    public function index(PaginationRequest $request): PaginatedResponse;
    public function show(int $id): UserResponse;
    public function store(CreateUserRequest $request): UserResponse;
    public function update(int $id, UpdateUserRequest $request): UserResponse;
    public function destroy(int $id): void;
}
```

**Archivos a crear/modificar:**
- `app/Services/AuthService.php` (nuevo)
- `app/Services/UserService.php` (simplificado)
- `app/Controllers/Api/V1/AuthController.php` (usar AuthService)

---

### Fase 6: Repository Pattern (Prioridad: MEDIA)

**Objetivo:** Desacoplar servicios del ORM de CodeIgniter.

#### 6.1 Crear interfaces de repositorio

```php
// app/Repositories/UserRepositoryInterface.php
interface UserRepositoryInterface {
    public function find(int $id): ?UserEntity;
    public function findByEmail(string $email): ?UserEntity;
    public function findByUsername(string $username): ?UserEntity;
    public function save(UserEntity $user): UserEntity;
    public function delete(int $id): bool;
    public function paginate(int $page, int $limit, array $filters = []): PaginatedResult;
}
```

#### 6.2 Implementar con CI4 Model

```php
// app/Repositories/Eloquent/UserRepository.php
class UserRepository implements UserRepositoryInterface {
    public function __construct(
        protected UserModel $model
    ) {}

    public function find(int $id): ?UserEntity {
        return $this->model->find($id);
    }

    public function findByEmail(string $email): ?UserEntity {
        return $this->model->where('email', $email)->first();
    }

    // ...
}
```

**Archivos a crear:**
- `app/Repositories/UserRepositoryInterface.php`
- `app/Repositories/CI4/UserRepository.php`
- (Repetir para otros modelos según necesidad)

---

### Fase 7: ApiResponse como Instancia (Prioridad: BAJA)

**Objetivo:** Permitir extensibilidad de respuestas.

```php
// app/Libraries/ApiResponse.php
class ApiResponse implements ApiResponseInterface {
    public function success(mixed $data, ?string $message = null): array;
    public function error(array $errors, ?string $message = null): array;
    // ...
}

// En Services.php
public static function apiResponse(): ApiResponseInterface {
    return new ApiResponse();
}

// Inyectar en servicios
class UserService {
    public function __construct(
        protected ApiResponseInterface $response
    ) {}

    public function show(int $id): array {
        return $this->response->success($user->toArray());
    }
}
```

---

### Fase 8: Typed Request Context (Prioridad: BAJA)

**Objetivo:** Reemplazar magic properties con objeto tipado.

```php
// app/Context/AuthContext.php
final class AuthContext {
    public function __construct(
        public readonly int $userId,
        public readonly string $userRole,
        public readonly string $tokenId
    ) {}
}

// En JwtAuthFilter
$authContext = new AuthContext($decoded->uid, $decoded->role, $decoded->jti);
$request->setContextObject('auth', $authContext);

// En Controller
$authContext = $this->request->getContextObject('auth');
$userId = $authContext->userId;  // Con type hints
```

---

## 5. Priorización de Mejoras

| Fase | Prioridad | Esfuerzo | Impacto | ROI |
|------|-----------|----------|---------|-----|
| 1. Dependency Injection | CRÍTICA | Medio | Alto | ⭐⭐⭐⭐⭐ |
| 2. Interface Segregation | Alta | Bajo | Medio | ⭐⭐⭐⭐ |
| 3. Request/Response DTOs | Alta | Alto | Alto | ⭐⭐⭐⭐ |
| 4. Consistencia de Errores | Alta | Medio | Alto | ⭐⭐⭐⭐⭐ |
| 5. Separar UserService | Media | Medio | Medio | ⭐⭐⭐ |
| 6. Repository Pattern | Media | Alto | Medio | ⭐⭐⭐ |
| 7. ApiResponse Instancia | Baja | Bajo | Bajo | ⭐⭐ |
| 8. Typed Request Context | Baja | Bajo | Bajo | ⭐⭐ |

---

## 6. Métricas de Éxito

### Antes de las mejoras:
- Servicios con dependencias ocultas
- Tests unitarios requieren mocks complejos
- Inconsistencia en manejo de errores
- Interfaces demasiado amplias

### Después de las mejoras (objetivos):
- [ ] 100% de dependencias inyectadas en constructores
- [ ] Tests unitarios sin acceso a Services:: estático
- [ ] Un único patrón para errores (excepciones)
- [ ] Interfaces con máximo 5 métodos cada una
- [ ] DTOs para todos los endpoints públicos
- [ ] Cobertura de tests >90%

---

## 7. Conclusión

El proyecto tiene una **base arquitectónica sólida** con buenas prácticas de seguridad y testing. Los principales puntos de mejora son:

1. **Dependency Injection real** en lugar de Service Locator
2. **Consistencia en manejo de errores** (todo excepciones o todo arrays, no mixto)
3. **DTOs tipados** en lugar de arrays genéricos
4. **Interfaces segregadas** por responsabilidad

Estas mejoras incrementarán la **testabilidad**, **mantenibilidad** y **claridad** del código sin requerir cambios fundamentales en la arquitectura existente.

---

*Documento generado para revisión arquitectónica del proyecto CI4 API Starter*
