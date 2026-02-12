# CI4 API Starter - Arquitectura y Patrones de Diseno

> **📢 NOTICE:** This document has been reorganized for better navigation and learning.
>
> **Recommended reading:**
> - **New to the project?** Start with [`../GETTING_STARTED.md`](../GETTING_STARTED.md)
> - **Want organized docs?** See [`architecture/`](architecture/) directory (13 focused documents)
> - **Need quick reference?** See [`AGENT_QUICK_REFERENCE.md`](AGENT_QUICK_REFERENCE.md)
> - **Full roadmap:** See [`architecture/README.md`](architecture/README.md)
>
> This monolithic document (2,400+ lines) remains available as a comprehensive searchable reference, but the content has been reorganized into smaller, focused documents for easier learning and navigation.

---

## Tabla de Contenidos

1. [Resumen Ejecutivo](#1-resumen-ejecutivo)
2. [Arquitectura General](#2-arquitectura-general)
3. [Flujo de una Peticion HTTP](#3-flujo-de-una-peticion-http)
4. [Capa de Controladores](#4-capa-de-controladores)
5. [Capa de Servicios](#5-capa-de-servicios)
6. [Capa de Modelos](#6-capa-de-modelos)
7. [Capa de Entidades](#7-capa-de-entidades)
8. [Sistema de Filtros (Middleware)](#8-sistema-de-filtros-middleware)
9. [Sistema de Validaciones](#9-sistema-de-validaciones)
10. [Sistema de Excepciones](#10-sistema-de-excepciones)
11. [Sistema de Respuestas API](#11-sistema-de-respuestas-api)
12. [Sistema de Internacionalizacion (i18n)](#12-sistema-de-internacionalizacion-i18n)
13. [Sistema de Queries Avanzadas](#13-sistema-de-queries-avanzadas)
14. [Contenedor de Servicios (IoC)](#14-contenedor-de-servicios-ioc)
15. [Sistema de Autenticacion JWT](#15-sistema-de-autenticacion-jwt)
16. [Patrones de Diseno Utilizados](#16-patrones-de-diseno-utilizados)
17. [Estructura de Directorios](#17-estructura-de-directorios)
18. [Diagramas de Secuencia](#18-diagramas-de-secuencia)
19. [Guia de Extension](#19-guia-de-extension)

---

## 1. Resumen Ejecutivo

Este proyecto es una **REST API empresarial** construida sobre CodeIgniter 4, siguiendo una arquitectura en capas con separacion estricta de responsabilidades:

```
Controller -> Service -> Model -> Entity
```

### Caracteristicas Principales

- **Arquitectura en Capas**: Separacion clara entre presentacion, logica de negocio y acceso a datos
- **Inyeccion de Dependencias**: Contenedor IoC para desacoplamiento y testabilidad
- **Validacion en Multiples Niveles**: Capa de input, capa de modelo, reglas de negocio
- **Autenticacion JWT Stateless**: Con soporte para refresh tokens y revocacion
- **Internacionalizacion Completa**: Soporte multi-idioma (en/es)
- **Sistema de Excepciones Estructurado**: Mapeo automatico a codigos HTTP
- **Query Builder Avanzado**: Filtrado, busqueda FULLTEXT y paginacion

---

## 2. Arquitectura General

### 2.1 Diagrama de Capas

```
┌─────────────────────────────────────────────────────────────────────┐
│                        CAPA DE PRESENTACION                         │
│  ┌───────────────────────────────────────────────────────────────┐  │
│  │                         FILTERS                                │  │
│  │  CorsFilter -> ThrottleFilter -> JwtAuthFilter -> RoleAuthFilter│  │
│  └───────────────────────────────────────────────────────────────┘  │
│  ┌───────────────────────────────────────────────────────────────┐  │
│  │                       CONTROLLERS                              │  │
│  │     ApiController (Base) -> UserController, AuthController     │  │
│  └───────────────────────────────────────────────────────────────┘  │
├─────────────────────────────────────────────────────────────────────┤
│                        CAPA DE NEGOCIO                              │
│  ┌───────────────────────────────────────────────────────────────┐  │
│  │                    INPUT VALIDATION                            │  │
│  │  AuthValidation, UserValidation, FileValidation, etc.          │  │
│  └───────────────────────────────────────────────────────────────┘  │
│  ┌───────────────────────────────────────────────────────────────┐  │
│  │                        SERVICES                                │  │
│  │  UserService, AuthService, JwtService, FileService, etc.       │  │
│  └───────────────────────────────────────────────────────────────┘  │
│  ┌───────────────────────────────────────────────────────────────┐  │
│  │                       LIBRARIES                                │  │
│  │  ApiResponse, QueryBuilder, StorageManager, QueueManager       │  │
│  └───────────────────────────────────────────────────────────────┘  │
├─────────────────────────────────────────────────────────────────────┤
│                        CAPA DE DATOS                                │
│  ┌───────────────────────────────────────────────────────────────┐  │
│  │                         MODELS                                 │  │
│  │  UserModel (Filterable, Searchable), FileModel, AuditLogModel  │  │
│  └───────────────────────────────────────────────────────────────┘  │
│  ┌───────────────────────────────────────────────────────────────┐  │
│  │                        ENTITIES                                │  │
│  │  UserEntity, FileEntity (con propiedades computadas)           │  │
│  └───────────────────────────────────────────────────────────────┘  │
├─────────────────────────────────────────────────────────────────────┤
│                     INFRAESTRUCTURA                                 │
│  ┌───────────────────────────────────────────────────────────────┐  │
│  │  Database (MySQL) | Cache | Queue | Storage (Local/S3)         │  │
│  └───────────────────────────────────────────────────────────────┘  │
└─────────────────────────────────────────────────────────────────────┘
```

### 2.2 Principios de Diseno

| Principio | Implementacion |
|-----------|----------------|
| **Single Responsibility** | Cada clase tiene una unica razon para cambiar |
| **Open/Closed** | Extensible via herencia y traits, cerrado para modificacion |
| **Liskov Substitution** | Servicios implementan interfaces intercambiables |
| **Interface Segregation** | Interfaces especificas por dominio |
| **Dependency Inversion** | Dependencia en abstracciones (interfaces) no implementaciones |

---

## 3. Flujo de una Peticion HTTP

### 3.1 Secuencia Completa

```
HTTP Request
     │
     ▼
┌─────────────────────────────────────────────────────────────────┐
│ 1. ROUTING (app/Config/Routes.php)                              │
│    - Determina controlador y metodo                             │
│    - Extrae parametros de URL (:num, :segment)                  │
│    - Asigna filtros de grupo                                    │
└─────────────────────────────────────────────────────────────────┘
     │
     ▼
┌─────────────────────────────────────────────────────────────────┐
│ 2. FILTERS - Before (app/Filters/)                              │
│    ┌─────────────┐  ┌──────────────┐  ┌────────────────────┐    │
│    │ CorsFilter  │->│ThrottleFilter│->│   JwtAuthFilter    │    │
│    │ (preflight) │  │ (rate limit) │  │ (token validation) │    │
│    └─────────────┘  └──────────────┘  └────────────────────┘    │
│                                              │                   │
│                                              ▼                   │
│                                  ┌─────────────────────────┐    │
│                                  │ RoleAuthorizationFilter │    │
│                                  │ (permission check)      │    │
│                                  └─────────────────────────┘    │
└─────────────────────────────────────────────────────────────────┘
     │
     ▼
┌─────────────────────────────────────────────────────────────────┐
│ 3. CONTROLLER (app/Controllers/)                                │
│    ┌──────────────────────────────────────────────────────────┐ │
│    │ handleRequest($method, $params)                          │ │
│    │   │                                                      │ │
│    │   ├─> collectRequestData()    // GET, POST, JSON, Files  │ │
│    │   │     └─> sanitizeInput()   // XSS prevention          │ │
│    │   │     └─> getUserId()       // From JWT filter         │ │
│    │   │                                                      │ │
│    │   ├─> getService()->$method($data)  // Delegate          │ │
│    │   │                                                      │ │
│    │   └─> respond($result, $status)     // Format response   │ │
│    └──────────────────────────────────────────────────────────┘ │
└─────────────────────────────────────────────────────────────────┘
     │
     ▼
┌─────────────────────────────────────────────────────────────────┐
│ 4. SERVICE (app/Services/)                                      │
│    ┌──────────────────────────────────────────────────────────┐ │
│    │ public function store(array $data): array                │ │
│    │   │                                                      │ │
│    │   ├─> $this->model->validate($data)  // Model validation │ │
│    │   │                                                      │ │
│    │   ├─> $this->validateBusinessRules() // Business rules   │ │
│    │   │                                                      │ │
│    │   ├─> $this->model->insert($data)    // Persist data     │ │
│    │   │                                                      │ │
│    │   └─> return ApiResponse::created()  // Format response  │ │
│    └──────────────────────────────────────────────────────────┘ │
└─────────────────────────────────────────────────────────────────┘
     │
     ▼
┌─────────────────────────────────────────────────────────────────┐
│ 5. MODEL (app/Models/)                                          │
│    ┌──────────────────────────────────────────────────────────┐ │
│    │ - Ejecuta query builder                                  │ │
│    │ - Aplica validationRules si $skipValidation = false      │ │
│    │ - Gestiona timestamps automaticamente                    │ │
│    │ - Retorna Entity objects (UserEntity, etc.)              │ │
│    └──────────────────────────────────────────────────────────┘ │
└─────────────────────────────────────────────────────────────────┘
     │
     ▼
┌─────────────────────────────────────────────────────────────────┐
│ 6. RESPONSE                                                     │
│    - ApiResponse formatea estructura JSON consistente          │
│    - Entity::toArray() oculta campos sensibles                 │
│    - Controller aplica HTTP status code apropiado              │
└─────────────────────────────────────────────────────────────────┘
     │
     ▼
HTTP Response (JSON)
```

### 3.2 Ejemplo Concreto: Crear Usuario

```
POST /api/v1/users
Authorization: Bearer <jwt_token>
Content-Type: application/json

{
  "email": "john@example.com",
  "first_name": "John",
  "last_name": "Doe",
  "password": "SecurePass123!",
  "role": "user"
}
```

**Secuencia de ejecucion:**

1. **Routes.php**: Match `POST users` -> `UserController::create`
2. **ThrottleFilter**: Verifica limite de 60 req/min por IP
3. **JwtAuthFilter**: Decodifica JWT, inyecta `$request->userId = 5`
4. **RoleAuthFilter**: Verifica que rol = 'admin' (requerido para crear usuarios)
5. **UserController::create()**: Llama `handleRequest('store')`
6. **collectRequestData()**: Merge JSON body + params, sanitiza con `strip_tags()`
7. **UserService::store()**:
   - Valida con `UserModel::validate()` (email unico, password regex, etc.)
   - Valida reglas de negocio adicionales
   - Hashea password con `PASSWORD_BCRYPT`
   - Inserta via `UserModel::insert()`
8. **Response**: `201 Created` con datos del usuario (sin password)

---

## 4. Capa de Controladores

### 4.1 ApiController Base

Ubicacion: `app/Controllers/ApiController.php`

El controlador base provee funcionalidad comun para todos los endpoints API:

```php
abstract class ApiController extends Controller
{
    use ResponseTrait;

    // Nombre del servicio a cargar desde Config\Services
    protected string $serviceName = '';

    // Codigos HTTP personalizados por metodo
    protected array $statusCodes = [
        'store'   => 201,  // Created
        'upload'  => 201,  // Created
        'destroy' => 200,  // OK (con mensaje de confirmacion)
    ];
}
```

### 4.2 Metodos Principales

#### handleRequest()

```php
protected function handleRequest(string $method, ?array $params = null): ResponseInterface
{
    try {
        // 1. Recolectar datos de todas las fuentes
        $data = $this->collectRequestData($params);

        // 2. Delegar al servicio correspondiente
        $result = $this->getService()->$method($data);

        // 3. Determinar codigo de estado
        $status = $this->determineStatus($result, $method);

        // 4. Retornar respuesta JSON
        return $this->respond($result, $status);
    } catch (Exception $e) {
        return $this->handleException($e);
    }
}
```

#### collectRequestData()

```php
protected function collectRequestData(?array $params = null): array
{
    $data = array_merge(
        $this->request->getGet() ?? [],      // Query params (?page=1)
        $this->request->getPost() ?? [],     // Form data
        $this->request->getRawInput(),       // Raw POST
        $this->getJsonData(),                // JSON body
        $params ?? []                         // Route params (:num)
    );

    // Inyectar usuario autenticado (desde JwtAuthFilter)
    if ($userId = $this->getUserId()) {
        $data['user_id'] = $userId;
    }

    return $this->sanitizeInput($data);  // XSS prevention
}
```

#### sanitizeInput()

```php
protected function sanitizeInput(array $data): array
{
    return array_map(function ($value) {
        if (is_string($value)) {
            return strip_tags(trim($value));  // Previene XSS
        }
        if (is_array($value)) {
            return $this->sanitizeInput($value);  // Recursivo
        }
        return $value;
    }, $data);
}
```

### 4.3 Controladores Especificos

| Controlador | Ubicacion | Responsabilidad |
|-------------|-----------|-----------------|
| `AuthController` | `Api/V1/AuthController.php` | Login, registro, /me |
| `UserController` | `Api/V1/UserController.php` | CRUD de usuarios |
| `FileController` | `Api/V1/FileController.php` | Upload/download archivos |
| `TokenController` | `Api/V1/TokenController.php` | Refresh/revoke tokens |
| `VerificationController` | `Api/V1/VerificationController.php` | Verificacion email |
| `PasswordResetController` | `Api/V1/PasswordResetController.php` | Reset password |
| `AuditController` | `Api/V1/AuditController.php` | Logs de auditoria |
| `MetricsController` | `Api/V1/MetricsController.php` | Metricas del sistema |
| `HealthController` | `Api/V1/HealthController.php` | Health checks |

### 4.4 Implementacion de un Controlador

```php
// app/Controllers/Api/V1/UserController.php

class UserController extends ApiController
{
    // Solo necesita definir el nombre del servicio
    protected string $serviceName = 'userService';

    // Los metodos CRUD se heredan de ApiController:
    // - index()   -> handleRequest('index')
    // - show($id) -> handleRequest('show', ['id' => $id])
    // - create()  -> handleRequest('store')
    // - update()  -> handleRequest('update', ['id' => $id])
    // - delete()  -> handleRequest('destroy', ['id' => $id])

    // Override solo cuando se necesita logica adicional
}
```

---

## 5. Capa de Servicios

### 5.1 Responsabilidades

Los servicios son el **corazon de la logica de negocio**:

1. **Orquestar operaciones** - Coordinar multiples modelos/servicios
2. **Validar reglas de negocio** - Mas alla de la validacion de datos
3. **Transformar datos** - Preparar para persistencia o respuesta
4. **Lanzar excepciones** - Convertir errores en excepciones apropiadas
5. **Formatear respuestas** - Usar `ApiResponse` para consistencia

### 5.2 Interfaz de Servicio

```php
// app/Interfaces/UserServiceInterface.php

interface UserServiceInterface
{
    public function index(array $data): array;
    public function show(array $data): array;
    public function store(array $data): array;
    public function update(array $data): array;
    public function destroy(array $data): array;
}
```

### 5.3 Implementacion de Servicio

```php
// app/Services/UserService.php

class UserService implements UserServiceInterface
{
    public function __construct(
        protected UserModel $userModel  // Inyeccion via constructor
    ) {}

    public function store(array $data): array
    {
        // 1. Validacion de integridad (Model)
        if (!$this->userModel->validate($data)) {
            throw new ValidationException(
                lang('Api.validationFailed'),
                $this->userModel->errors()
            );
        }

        // 2. Validacion de reglas de negocio
        $businessErrors = $this->validateBusinessRules($data);
        if (!empty($businessErrors)) {
            throw new ValidationException(
                lang('Api.validationFailed'),
                $businessErrors
            );
        }

        // 3. Preparar datos (hash password, defaults)
        $insertData = [
            'email'      => $data['email'],
            'first_name' => $data['first_name'] ?? null,
            'last_name'  => $data['last_name'] ?? null,
            'password'   => password_hash($data['password'], PASSWORD_BCRYPT),
            'role'       => $data['role'] ?? 'user',
        ];

        // 4. Persistir
        $userId = $this->userModel->insert($insertData);

        if (!$userId) {
            throw new ValidationException(
                lang('Api.validationFailed'),
                $this->userModel->errors()
            );
        }

        // 5. Retornar respuesta formateada
        $user = $this->userModel->find($userId);
        return ApiResponse::created($user->toArray());
    }
}
```

### 5.4 Catalogo de Servicios

| Servicio | Interface | Responsabilidad |
|----------|-----------|-----------------|
| `UserService` | `UserServiceInterface` | CRUD usuarios con paginacion/filtros |
| `AuthService` | `AuthServiceInterface` | Login, registro, generacion tokens |
| `JwtService` | `JwtServiceInterface` | Encode/decode JWT con Firebase/JWT |
| `RefreshTokenService` | `RefreshTokenServiceInterface` | Ciclo de vida refresh tokens |
| `TokenRevocationService` | `TokenRevocationServiceInterface` | Blacklist de tokens revocados |
| `FileService` | `FileServiceInterface` | Upload/download con StorageManager |
| `EmailService` | `EmailServiceInterface` | Envio emails via Symfony Mailer |
| `VerificationService` | `VerificationServiceInterface` | Verificacion de email |
| `PasswordResetService` | `PasswordResetServiceInterface` | Flujo de reset password |
| `AuditService` | `AuditServiceInterface` | Logging de auditoria |
| `InputValidationService` | `InputValidationServiceInterface` | Validacion centralizada |

---

## 6. Capa de Modelos

### 6.1 Configuracion de Modelo

```php
// app/Models/UserModel.php

class UserModel extends Model
{
    use Filterable;   // Trait para filtrado avanzado
    use Searchable;   // Trait para busqueda FULLTEXT

    // ========== CONFIGURACION DE TABLA ==========
    protected $table            = 'users';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $returnType       = UserEntity::class;  // Retorna entidades

    // ========== SOFT DELETES ==========
    protected $useSoftDeletes   = true;
    protected $deletedField     = 'deleted_at';

    // ========== MASS ASSIGNMENT PROTECTION ==========
    protected $protectFields    = true;
    protected $allowedFields    = [
        'email', 'first_name', 'last_name', 'password', 'role',
        'oauth_provider', 'oauth_provider_id', 'avatar_url',
        'email_verification_token', 'verification_token_expires',
        'email_verified_at',
    ];

    // ========== TIMESTAMPS ==========
    protected $useTimestamps    = true;
    protected $dateFormat       = 'datetime';
    protected $createdField     = 'created_at';
    protected $updatedField     = 'updated_at';

    // ========== VALIDACION ==========
    protected $validationRules = [
        'email' => [
            'rules'  => 'required|valid_email|max_length[255]|is_unique[users.email,id,{id}]',
            'errors' => [
                'required'    => '{field} is required',
                'valid_email' => 'Please provide a valid email',
                'is_unique'   => 'This email is already registered',
            ],
        ],
        'first_name' => [
            'rules'  => 'permit_empty|string|max_length[100]',
            // ...
        ],
        'last_name' => [
            'rules'  => 'permit_empty|string|max_length[100]',
            // ...
        ],
        'password' => [
            'rules'  => 'required|min_length[8]|max_length[128]|regex_match[/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[\W_]).+$/]',
            // Password debe tener: mayuscula, minuscula, numero, caracter especial
        ],
    ];

    // ========== FILTRADO Y BUSQUEDA ==========
    protected array $searchableFields = ['email', 'first_name', 'last_name'];
    protected array $filterableFields = ['role', 'email', 'created_at', 'id', 'first_name', 'last_name'];
    protected array $sortableFields = ['id', 'email', 'created_at', 'role', 'first_name', 'last_name'];
}
```

### 6.2 Traits de Modelo

#### Filterable Trait

```php
// app/Traits/Filterable.php

trait Filterable
{
    public function applyFilters(array $filters): self
    {
        // Validar campos contra whitelist
        if (!empty($this->filterableFields)) {
            $filters = FilterParser::filterAllowedFields($filters, $this->filterableFields);
        }

        // Parsear filtros (extraer operadores)
        $parsedFilters = FilterParser::parse($filters);

        // Aplicar cada filtro
        foreach ($parsedFilters as $field => $condition) {
            [$operator, $value] = $condition;
            FilterOperatorApplier::apply($this, $field, $operator, $value);
        }

        return $this;
    }
}
```

#### Searchable Trait

```php
// app/Traits/Searchable.php

trait Searchable
{
    public function search(string $query): self
    {
        if (empty($this->searchableFields)) {
            return $this;
        }

        // Usa FULLTEXT si esta habilitado
        if (env('SEARCH_ENABLED', 'true') === 'true') {
            $fields = implode(',', $this->searchableFields);
            $this->where("MATCH({$fields}) AGAINST(? IN BOOLEAN MODE)", [$query . '*']);
        } else {
            // Fallback a LIKE
            $this->groupStart();
            foreach ($this->searchableFields as $i => $field) {
                $method = $i === 0 ? 'like' : 'orLike';
                $this->$method($field, $query);
            }
            $this->groupEnd();
        }

        return $this;
    }
}
```

### 6.3 Validacion en el Modelo

La validacion del modelo se ejecuta automaticamente en `insert()` y `update()`:

```php
// Validacion automatica
$userId = $this->userModel->insert($data);
if (!$userId) {
    // Validacion fallo
    $errors = $this->userModel->errors();
}

// Validacion manual (antes de insert)
if (!$this->userModel->validate($data)) {
    $errors = $this->userModel->errors();
}

// Saltar validacion (usar con cuidado)
$this->userModel->skipValidation(true)->insert($data);
```

---

## 7. Capa de Entidades

### 7.1 Proposito de las Entidades

Las entidades representan **objetos de dominio** con:

1. **Type Casting** - Conversion automatica de tipos
2. **Propiedades Computadas** - Getters para valores derivados
3. **Serializacion Controlada** - Ocultar campos sensibles
4. **Logica de Dominio Encapsulada** - Metodos de utilidad

### 7.2 UserEntity

```php
// app/Entities/UserEntity.php

class UserEntity extends Entity
{
    // ========== TYPE CASTING ==========
    protected $casts = [
        'id'   => 'integer',
        'role' => 'string',
    ];

    // ========== DATE FIELDS ==========
    protected $dates = [
        'created_at',
        'updated_at',
        'deleted_at',
        'email_verified_at',
        'verification_token_expires',
    ];

    // ========== HIDDEN FIELDS ==========
    protected array $hidden = [
        'password',
        'email_verification_token',
        'verification_token_expires',
    ];

    // ========== SERIALIZACION ==========
    public function toArray(bool $onlyChanged = false, bool $cast = true, bool $recursive = false): array
    {
        $data = parent::toArray($onlyChanged, $cast, $recursive);

        // Remover campos ocultos
        foreach ($this->hidden as $field) {
            unset($data[$field]);
        }

        return $data;
    }

    // ========== PROPIEDADES COMPUTADAS ==========
    public function isVerified(): bool
    {
        return $this->email_verified_at !== null;
    }

    public function isAdmin(): bool
    {
        return $this->role === 'admin';
    }

    public function getDisplayName(): string
    {
        $full = trim(($this->first_name ?? '') . ' ' . ($this->last_name ?? ''));
        return $full !== '' ? $full : explode('@', $this->email)[0];
    }

    public function getMaskedEmail(): string
    {
        [$local, $domain] = explode('@', $this->email, 2);
        $masked = substr($local, 0, 2) . str_repeat('*', max(strlen($local) - 2, 0));
        return $masked . '@' . $domain;
    }

    // ========== MUTATORS ==========
    public function setPassword(string $password): self
    {
        // Detectar si ya esta hasheado
        if (preg_match('/^\$2[ayb]\$/', $password)) {
            $this->attributes['password'] = $password;
        } else {
            $this->attributes['password'] = password_hash($password, PASSWORD_BCRYPT);
        }
        return $this;
    }
}
```

### 7.3 Uso de Entidades

```php
// Obtener entidad desde modelo
$user = $this->userModel->find(1);  // Retorna UserEntity

// Acceder propiedades (con casting automatico)
$userId = $user->id;           // integer (aunque DB retorne string)
$createdAt = $user->created_at; // CodeIgniter\I18n\Time object

// Propiedades computadas
$isAdmin = $user->isAdmin();
$display = $user->getDisplayName();

// Serializar para respuesta (sin password)
$userData = $user->toArray();

// Serializar solo campos especificos
$publicData = $user->toArrayOnly(['id', 'email', 'first_name', 'last_name']);
```

---

## 8. Sistema de Filtros (Middleware)

### 8.1 Pipeline de Filtros

Los filtros se ejecutan en orden segun la configuracion de rutas:

```php
// app/Config/Routes.php

$routes->group('api/v1', ['filter' => 'throttle'], function ($routes) {
    // Rutas publicas con throttle estricto para auth
    $routes->group('', ['filter' => 'authThrottle'], function ($routes) {
        $routes->post('auth/login', 'AuthController::login');
    });

    // Rutas protegidas
    $routes->group('', ['filter' => 'jwtauth'], function ($routes) {
        $routes->get('users', 'UserController::index');

        // Admin only
        $routes->group('', ['filter' => 'roleauth:admin'], function ($routes) {
            $routes->post('users', 'UserController::create');
        });
    });
});
```

### 8.2 JwtAuthFilter

```php
// app/Filters/JwtAuthFilter.php

class JwtAuthFilter implements FilterInterface
{
    public function before(RequestInterface $request, $arguments = null)
    {
        $jwtService = Services::jwtService();

        // 1. Verificar header Authorization
        $authHeader = $request->getHeaderLine('Authorization');
        if (empty($authHeader)) {
            return $this->unauthorized(lang('Auth.headerMissing'));
        }

        // 2. Extraer token
        if (!preg_match('/Bearer\s+(.*)$/i', $authHeader, $matches)) {
            return $this->unauthorized(lang('Auth.invalidFormat'));
        }
        $token = $matches[1];

        // 3. Decodificar y validar
        $decoded = $jwtService->decode($token);
        if ($decoded === null) {
            return $this->unauthorized(lang('Auth.invalidToken'));
        }

        // 4. Verificar revocacion (opcional)
        if (env('JWT_REVOCATION_CHECK', 'true') === 'true') {
            $jti = $decoded->jti ?? null;
            if ($jti) {
                $tokenRevocationService = Services::tokenRevocationService();
                if ($tokenRevocationService->isRevoked($jti)) {
                    return $this->unauthorized(lang('Auth.tokenRevoked'));
                }
            }
        }

        // 5. Inyectar datos de usuario en request
        $request->userId = $decoded->uid;
        $request->userRole = $decoded->role;
    }
}
```

### 8.3 RoleAuthorizationFilter

```php
// app/Filters/RoleAuthorizationFilter.php

class RoleAuthorizationFilter implements FilterInterface
{
    // Jerarquia de roles (mayor numero = mas permisos)
    private array $roleHierarchy = [
        'user'  => 0,
        'admin' => 10,
    ];

    public function before(RequestInterface $request, $arguments = null)
    {
        // Rol requerido viene de la ruta: 'filter' => 'roleauth:admin'
        $requiredRole = $arguments[0] ?? 'user';

        // Rol del usuario viene del JwtAuthFilter
        $userRole = $request->userRole ?? 'user';

        // Comparar niveles
        $requiredLevel = $this->roleHierarchy[$requiredRole] ?? 0;
        $userLevel = $this->roleHierarchy[$userRole] ?? 0;

        if ($userLevel < $requiredLevel) {
            return Services::response()
                ->setJSON(ApiResponse::forbidden(lang('Auth.accessDenied')))
                ->setStatusCode(403);
        }
    }
}
```

### 8.4 ThrottleFilter

```php
// app/Filters/ThrottleFilter.php

class ThrottleFilter implements FilterInterface
{
    public function before(RequestInterface $request, $arguments = null)
    {
        $cache = Services::cache();

        // Identificador unico: IP + user_id (si autenticado)
        $ip = $request->getIPAddress();
        $userId = $request->userId ?? 'anonymous';
        $key = "throttle:{$ip}:{$userId}";

        // Configuracion
        $limit = (int) env('RATE_LIMIT_REQUESTS', 60);
        $window = (int) env('RATE_LIMIT_WINDOW', 60);

        // Obtener contador actual
        $current = (int) $cache->get($key);

        if ($current >= $limit) {
            return Services::response()
                ->setJSON(ApiResponse::error([], lang('Api.tooManyRequests'), 429))
                ->setStatusCode(429)
                ->setHeader('X-RateLimit-Limit', (string) $limit)
                ->setHeader('X-RateLimit-Remaining', '0')
                ->setHeader('Retry-After', (string) $window);
        }

        // Incrementar contador
        if ($current === 0) {
            $cache->save($key, 1, $window);
        } else {
            $cache->increment($key);
        }
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
        // Agregar headers de rate limit a todas las respuestas
        $limit = (int) env('RATE_LIMIT_REQUESTS', 60);
        $key = "throttle:{$request->getIPAddress()}:" . ($request->userId ?? 'anonymous');
        $current = (int) Services::cache()->get($key);

        $response->setHeader('X-RateLimit-Limit', (string) $limit);
        $response->setHeader('X-RateLimit-Remaining', (string) max(0, $limit - $current));
    }
}
```

### 8.5 Catalogo de Filtros

| Filtro | Alias | Proposito |
|--------|-------|-----------|
| `CorsFilter` | `cors` | Manejo CORS y preflight OPTIONS |
| `ThrottleFilter` | `throttle` | Rate limiting general (60 req/min) |
| `AuthThrottleFilter` | `authThrottle` | Rate limiting estricto para auth |
| `JwtAuthFilter` | `jwtauth` | Validacion JWT y extraccion de usuario |
| `RoleAuthorizationFilter` | `roleauth` | Control de acceso basado en roles |
| `SecurityHeadersFilter` | `securityHeaders` | Headers de seguridad (CSP, etc.) |
| `RequestLoggingFilter` | `requestLogging` | Logging de requests a DB |
| `LocaleFilter` | `locale` | Establecer idioma de la request |

---

## 9. Sistema de Validaciones

### 9.1 Niveles de Validacion

El proyecto implementa **tres niveles de validacion**:

```
┌─────────────────────────────────────────────────────────────────┐
│ NIVEL 1: INPUT VALIDATION (app/Validations/)                   │
│ - Validacion temprana de formato de entrada                    │
│ - Reglas por accion (login, register, store, update)           │
│ - Mensajes internacionalizados                                  │
└─────────────────────────────────────────────────────────────────┘
                              │
                              ▼
┌─────────────────────────────────────────────────────────────────┐
│ NIVEL 2: MODEL VALIDATION (UserModel::$validationRules)        │
│ - Validacion de integridad de datos                            │
│ - Constraints de DB (unique, format, length)                   │
│ - Se ejecuta automaticamente en insert/update                  │
└─────────────────────────────────────────────────────────────────┘
                              │
                              ▼
┌─────────────────────────────────────────────────────────────────┐
│ NIVEL 3: BUSINESS RULES (Service::validateBusinessRules())     │
│ - Reglas de negocio especificas                                │
│ - Validaciones que requieren consultas a DB                    │
│ - Logica compleja (dominios permitidos, limites, etc.)         │
└─────────────────────────────────────────────────────────────────┘
```

### 9.2 Input Validation Classes

```php
// app/Validations/AuthValidation.php

class AuthValidation extends BaseValidation
{
    public function getRules(string $action): array
    {
        return match ($action) {
            'login' => [
                'email'    => 'required|valid_email_idn|max_length[255]',
                'password' => 'required|string',
            ],

            'register' => [
                'email'      => 'required|valid_email_idn|max_length[255]',
                'first_name' => 'permit_empty|string|max_length[100]',
                'last_name'  => 'permit_empty|string|max_length[100]',
                'password'   => 'required|strong_password',  // Regla custom
            ],

            'reset_password' => [
                'token'    => 'required|valid_token[64]',  // Regla custom
                'email'    => 'required|valid_email_idn|max_length[255]',
                'password' => 'required|strong_password',
            ],

            default => [],
        };
    }

    public function getMessages(string $action): array
    {
        return match ($action) {
            'register' => [
                'email.valid_email_idn'    => lang('InputValidation.common.emailInvalid'),
                'first_name.max_length'    => lang('InputValidation.common.firstNameMaxLength'),
                'last_name.max_length'     => lang('InputValidation.common.lastNameMaxLength'),
                'password.strong_password' => lang('InputValidation.common.passwordStrength'),
            ],
            default => [],
        };
    }
}
```

### 9.3 Reglas de Validacion Personalizadas

```php
// app/Validations/Rules/CustomRules.php

class CustomRules
{
    /**
     * Validar fortaleza de password
     *
     * Requiere: 8+ chars, mayuscula, minuscula, numero, caracter especial
     */
    public function strong_password(string $value): bool
    {
        if (strlen($value) < 8) {
            return false;
        }

        // Al menos una mayuscula, una minuscula, un numero, un caracter especial
        return preg_match('/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[\W_]).+$/', $value) === 1;
    }

    /**
     * Validar formato de token
     *
     * @param string $value Token a validar
     * @param string $length Longitud esperada (hex string)
     */
    public function valid_token(string $value, string $length): bool
    {
        $expectedLength = (int) $length;
        return strlen($value) === $expectedLength && ctype_xdigit($value);
    }
}
```

### 9.4 Uso del Sistema de Validacion

```php
// En un servicio
class AuthService implements AuthServiceInterface
{
    public function register(array $data): array
    {
        // Nivel 1: Input validation
        $validation = new AuthValidation();
        $validator = Services::validation();
        $validator->setRules(
            $validation->getRules('register'),
            $validation->getMessages('register')
        );

        if (!$validator->run($data)) {
            throw new ValidationException(
                lang('Api.validationFailed'),
                $validator->getErrors()
            );
        }

        // Nivel 2: Model validation (automatico en insert)
        // Nivel 3: Business rules
        // ...
    }
}
```

---

## 10. Sistema de Excepciones

### 10.1 Jerarquia de Excepciones

```
Exception
    └── ApiException (abstract)
            ├── NotFoundException         (404)
            ├── AuthenticationException   (401)
            ├── AuthorizationException    (403)
            ├── ValidationException       (422)
            ├── BadRequestException       (400)
            ├── ConflictException         (409)
            ├── TooManyRequestsException  (429)
            └── ServiceUnavailableException (503)
```

### 10.2 ApiException Base

```php
// app/Exceptions/ApiException.php

abstract class ApiException extends Exception
{
    protected int $statusCode = 500;
    protected array $errors = [];

    public function __construct(
        string $message = '',
        array $errors = [],
        ?Throwable $previous = null
    ) {
        parent::__construct($message, 0, $previous);
        $this->errors = $errors;
    }

    public function getStatusCode(): int
    {
        return $this->statusCode;
    }

    public function getErrors(): array
    {
        return $this->errors;
    }

    public function toArray(): array
    {
        return [
            'status' => 'error',
            'message' => $this->getMessage(),
            'errors' => $this->errors,
        ];
    }
}
```

### 10.3 Excepciones Especificas

```php
// app/Exceptions/ValidationException.php
class ValidationException extends ApiException
{
    protected int $statusCode = 422;  // Unprocessable Entity
}

// app/Exceptions/NotFoundException.php
class NotFoundException extends ApiException
{
    protected int $statusCode = 404;
}

// app/Exceptions/AuthenticationException.php
class AuthenticationException extends ApiException
{
    protected int $statusCode = 401;
}

// app/Exceptions/AuthorizationException.php
class AuthorizationException extends ApiException
{
    protected int $statusCode = 403;
}
```

### 10.4 Manejo de Excepciones en ApiController

```php
// app/Controllers/ApiController.php

protected function handleException(Exception $e): ResponseInterface
{
    log_message('error', 'API Exception: ' . $e->getMessage());
    log_message('error', 'Trace: ' . $e->getTraceAsString());

    // Excepciones API personalizadas
    if ($e instanceof \App\Exceptions\ApiException) {
        return $this->respond($e->toArray(), $e->getStatusCode());
    }

    // Excepciones de base de datos
    if ($e instanceof \CodeIgniter\Database\Exceptions\DatabaseException) {
        log_message('critical', 'Database error: ' . $e->getMessage());
        return $this->respond([
            'status' => 'error',
            'message' => lang('Api.databaseError'),
            'errors' => [],
        ], 500);
    }

    // Excepciones genericas
    return $this->respond([
        'status' => 'error',
        'message' => $e->getMessage(),
        'errors' => [],
    ], 500);
}
```

### 10.5 Uso de Excepciones

```php
// En servicios - lanzar excepcion apropiada
public function show(array $data): array
{
    if (!isset($data['id'])) {
        throw new BadRequestException(
            'Invalid request',
            ['id' => lang('Users.idRequired')]
        );
    }

    $user = $this->userModel->find($data['id']);

    if (!$user) {
        throw new NotFoundException(lang('Users.notFound'));
    }

    return ApiResponse::success($user->toArray());
}
```

---

## 11. Sistema de Respuestas API

### 11.1 ApiResponse Library

```php
// app/Libraries/ApiResponse.php

class ApiResponse
{
    /**
     * Respuesta exitosa
     */
    public static function success(
        mixed $data = null,
        ?string $message = null,
        array $meta = []
    ): array {
        $response = ['status' => 'success'];

        if ($message !== null) {
            $response['message'] = $message;
        }

        if ($data !== null) {
            $response['data'] = $data;
        }

        if (!empty($meta)) {
            $response['meta'] = $meta;
        }

        return $response;
    }

    /**
     * Respuesta de error
     */
    public static function error(
        array|string $errors,
        ?string $message = null,
        ?int $code = null
    ): array {
        $response = [
            'status' => 'error',
            'message' => $message ?? lang('Api.requestFailed'),
            'errors' => is_string($errors) ? ['general' => $errors] : $errors,
        ];

        if ($code !== null) {
            $response['code'] = $code;
        }

        return $response;
    }

    /**
     * Respuesta paginada
     */
    public static function paginated(
        array $items,
        int $total,
        int $page,
        int $perPage
    ): array {
        return self::success($items, null, [
            'total' => $total,
            'perPage' => $perPage,
            'page' => $page,
            'lastPage' => (int) ceil($total / $perPage),
            'from' => ($page - 1) * $perPage + 1,
            'to' => min($page * $perPage, $total),
        ]);
    }

    // Metodos de conveniencia
    public static function created(mixed $data, ?string $message = null): array;
    public static function deleted(?string $message = null): array;
    public static function validationError(array $errors, ?string $message = null): array;
    public static function notFound(?string $message = null): array;
    public static function unauthorized(?string $message = null): array;
    public static function forbidden(?string $message = null): array;
    public static function serverError(?string $message = null): array;
}
```

### 11.2 Estructura de Respuestas

#### Respuesta Exitosa (200 OK)

```json
{
  "status": "success",
  "message": "User retrieved successfully",
  "data": {
    "id": 1,
    "email": "john@example.com",
    "first_name": "John",
    "last_name": "Doe",
    "role": "user",
    "created_at": "2026-01-28T10:30:00+00:00"
  }
}
```

#### Respuesta Paginada (200 OK)

```json
{
  "status": "success",
  "data": [
    {"id": 1, "email": "john@example.com"},
    {"id": 2, "email": "jane@example.com"}
  ],
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

#### Respuesta de Error (4xx/5xx)

```json
{
  "status": "error",
  "message": "Validation failed",
  "errors": {
    "email": "This email is already registered",
    "password": "Password must contain at least one uppercase letter"
  },
  "code": 422
}
```

---

## 12. Sistema de Internacionalizacion (i18n)

### 12.1 Estructura de Archivos de Idioma

```
app/Language/
├── en/                         # Ingles (default)
│   ├── Api.php                 # Mensajes generales API
│   ├── Auth.php                # Autenticacion
│   ├── Users.php               # Usuarios
│   ├── Validation.php          # Validacion general
│   ├── InputValidation.php     # Validacion de entrada
│   ├── Email.php               # Emails
│   ├── Verification.php        # Verificacion email
│   ├── PasswordReset.php       # Reset password
│   ├── Tokens.php              # Tokens
│   ├── Files.php               # Archivos
│   ├── Audit.php               # Auditoria
│   └── Exceptions.php          # Excepciones
│
└── es/                         # Espanol
    ├── Api.php
    ├── Auth.php
    ├── Users.php
    └── ... (mismos archivos)
```

### 12.2 Formato de Archivos de Idioma

```php
// app/Language/en/Auth.php

return [
    // Mensajes de autenticacion
    'loginSuccess'      => 'Login successful',
    'loginFailed'       => 'Invalid credentials',
    'headerMissing'     => 'Authorization header is missing',
    'invalidFormat'     => 'Invalid authorization format. Use Bearer token',
    'invalidToken'      => 'Invalid or expired token',
    'tokenRevoked'      => 'Token has been revoked',
    'accessDenied'      => 'Access denied. Insufficient permissions',

    // Registro
    'registerSuccess'   => 'Registration successful',
    'emailExists'       => 'Email is already registered',
    'oauthProviderInvalid' => 'OAuth provider is not supported',
];
```

```php
// app/Language/es/Auth.php

return [
    'loginSuccess'      => 'Inicio de sesion exitoso',
    'loginFailed'       => 'Credenciales invalidas',
    'headerMissing'     => 'Falta el encabezado de autorizacion',
    'invalidFormat'     => 'Formato de autorizacion invalido. Use Bearer token',
    'invalidToken'      => 'Token invalido o expirado',
    'tokenRevoked'      => 'El token ha sido revocado',
    'accessDenied'      => 'Acceso denegado. Permisos insuficientes',

    'registerSuccess'   => 'Registro exitoso',
    'emailExists'       => 'El email ya esta registrado',
    'oauthProviderInvalid' => 'El proveedor OAuth no es compatible',
];
```

### 12.3 Uso de Traducciones

```php
// En cualquier parte del codigo
$message = lang('Auth.loginSuccess');      // "Login successful" o "Inicio de sesion exitoso"
$error = lang('Users.notFound');           // "User not found" o "Usuario no encontrado"

// Con parametros
$message = lang('Validation.min_length', [8]);  // "Must be at least 8 characters"

// En excepciones
throw new NotFoundException(lang('Users.notFound'));

// En servicios
return ApiResponse::success($data, lang('Users.updatedSuccess'));
```

### 12.4 Deteccion de Idioma

El idioma se determina en el siguiente orden de prioridad:

1. **Header `Accept-Language`**: `Accept-Language: es`
2. **Query parameter**: `?lang=es`
3. **Default**: Configurado en `app/Config/App.php`

```php
// app/Filters/LocaleFilter.php

class LocaleFilter implements FilterInterface
{
    public function before(RequestInterface $request, $arguments = null)
    {
        // Prioridad 1: Query parameter
        $locale = $request->getGet('lang');

        // Prioridad 2: Accept-Language header
        if (!$locale) {
            $acceptLanguage = $request->getHeaderLine('Accept-Language');
            $locale = substr($acceptLanguage, 0, 2);
        }

        // Validar que el idioma existe
        $supported = ['en', 'es'];
        if ($locale && in_array($locale, $supported)) {
            service('request')->setLocale($locale);
        }
    }
}
```

---

## 13. Sistema de Queries Avanzadas

### 13.1 QueryBuilder

```php
// app/Libraries/Query/QueryBuilder.php

class QueryBuilder
{
    protected Model $model;

    public function __construct(Model $model)
    {
        $this->model = $model;
    }

    /**
     * Aplicar filtros con operadores
     *
     * Soporta: =, !=, >, <, >=, <=, in, not_in, like, between
     */
    public function filter(array $filters): self
    {
        $parsedFilters = FilterParser::parse($filters);

        foreach ($parsedFilters as $field => $condition) {
            [$operator, $value] = $condition;
            FilterOperatorApplier::apply($this->model, $field, $operator, $value);
        }

        return $this;
    }

    /**
     * Aplicar ordenamiento con validacion
     *
     * Formato: "-created_at,email" (- prefix = DESC)
     * Valida contra whitelist para prevenir SQL injection
     */
    public function sort(string $sort): self
    {
        $sortableFields = $this->model->sortableFields ?? [];
        $parsedSorts = FilterParser::parseSort($sort, $sortableFields);

        foreach ($parsedSorts as [$field, $direction]) {
            $this->model->orderBy($field, $direction);
        }

        return $this;
    }

    /**
     * Busqueda FULLTEXT o LIKE
     */
    public function search(string $query): self
    {
        $searchableFields = $this->model->searchableFields ?? [];
        $useFulltext = env('SEARCH_ENABLED', 'true') === 'true';

        SearchQueryApplier::apply($this->model, $query, $searchableFields, $useFulltext);

        return $this;
    }

    /**
     * Paginacion con limites configurables
     */
    public function paginate(int $page = 1, int $limit = 20): array
    {
        $maxLimit = (int) env('PAGINATION_MAX_LIMIT', 100);
        $limit = min($limit, $maxLimit);
        $page = max($page, 1);

        $total = (int) $this->model->countAllResults(false);
        $offset = ($page - 1) * $limit;
        $data = $this->model->findAll($limit, $offset);

        return [
            'data' => $data,
            'total' => $total,
            'page' => $page,
            'perPage' => $limit,
            'lastPage' => (int) ceil($total / $limit),
        ];
    }
}
```

### 13.2 FilterParser

```php
// app/Libraries/Query/FilterParser.php

class FilterParser
{
    /**
     * Parsear filtros con operadores
     *
     * Input: ['role' => 'admin', 'age' => ['>=', 18], 'status' => ['in', ['active', 'pending']]]
     * Output: ['role' => ['=', 'admin'], 'age' => ['>=', 18], 'status' => ['in', ['active', 'pending']]]
     */
    public static function parse(array $filters): array
    {
        $parsed = [];

        foreach ($filters as $field => $value) {
            if (is_array($value) && count($value) === 2 && is_string($value[0])) {
                // ['operator', 'value'] format
                $parsed[$field] = $value;
            } else {
                // Simple equality
                $parsed[$field] = ['=', $value];
            }
        }

        return $parsed;
    }

    /**
     * Parsear y validar campos de ordenamiento
     *
     * Input: "-created_at,email"
     * Output: [['created_at', 'DESC'], ['email', 'ASC']]
     */
    public static function parseSort(string $sort, array $allowedFields = []): array
    {
        $sorts = [];
        $fields = explode(',', $sort);

        foreach ($fields as $field) {
            $field = trim($field);
            $direction = 'ASC';

            if (str_starts_with($field, '-')) {
                $direction = 'DESC';
                $field = substr($field, 1);
            }

            // Validar contra whitelist (prevencion SQL injection)
            if (!empty($allowedFields) && !in_array($field, $allowedFields)) {
                continue;
            }

            $sorts[] = [$field, $direction];
        }

        return $sorts;
    }
}
```

### 13.3 FilterOperatorApplier

```php
// app/Libraries/Query/FilterOperatorApplier.php

class FilterOperatorApplier
{
    public static function apply(Model $model, string $field, string $operator, mixed $value): void
    {
        match ($operator) {
            '='          => $model->where($field, $value),
            '!='         => $model->where($field . ' !=', $value),
            '>'          => $model->where($field . ' >', $value),
            '<'          => $model->where($field . ' <', $value),
            '>='         => $model->where($field . ' >=', $value),
            '<='         => $model->where($field . ' <=', $value),
            'in'         => $model->whereIn($field, (array) $value),
            'not_in'     => $model->whereNotIn($field, (array) $value),
            'like'       => $model->like($field, $value),
            'between'    => self::applyBetween($model, $field, $value),
            'date_after' => $model->where($field . ' >', $value),
            'date_before'=> $model->where($field . ' <', $value),
            default      => null,
        };
    }
}
```

### 13.4 Uso en Servicios

```php
// UserService::index()

public function index(array $data): array
{
    $builder = new QueryBuilder($this->userModel);

    // Aplicar filtros
    if (!empty($data['filter']) && is_array($data['filter'])) {
        $builder->filter($data['filter']);
    }

    // Aplicar busqueda
    if (!empty($data['search'])) {
        $builder->search($data['search']);
    }

    // Aplicar ordenamiento
    if (!empty($data['sort'])) {
        $builder->sort($data['sort']);
    }

    // Paginar
    $page = max((int) ($data['page'] ?? 1), 1);
    $limit = (int) ($data['limit'] ?? env('PAGINATION_DEFAULT_LIMIT', 20));
    $result = $builder->paginate($page, $limit);

    // Convertir entidades a arrays
    $result['data'] = array_map(fn($user) => $user->toArray(), $result['data']);

    return ApiResponse::paginated(
        $result['data'],
        $result['total'],
        $result['page'],
        $result['perPage']
    );
}
```

### 13.5 Ejemplos de Query Parameters

```bash
# Filtrado simple
GET /api/v1/users?filter[role]=admin

# Filtrado con operadores
GET /api/v1/users?filter[created_at]=[>=,2026-01-01]
GET /api/v1/users?filter[role]=[in,[admin,moderator]]

# Busqueda FULLTEXT
GET /api/v1/users?search=john

# Ordenamiento (- = descendente)
GET /api/v1/users?sort=-created_at,email

# Paginacion
GET /api/v1/users?page=2&limit=50

# Combinado
GET /api/v1/users?filter[role]=admin&search=john&sort=-created_at,email&page=1&limit=20
```

---

## 14. Contenedor de Servicios (IoC)

### 14.1 Registro de Servicios

```php
// app/Config/Services.php

class Services extends BaseService
{
    /**
     * User Service con sus dependencias
     */
    public static function userService(bool $getShared = true)
    {
        if ($getShared) {
            return static::getSharedInstance('userService');
        }

        return new \App\Services\UserService(
            new \App\Models\UserModel()
        );
    }

    /**
     * Auth Service con multiples dependencias
     */
    public static function authService(bool $getShared = true)
    {
        if ($getShared) {
            return static::getSharedInstance('authService');
        }

        return new \App\Services\AuthService(
            new \App\Models\UserModel(),
            static::jwtService(),          // Dependencia de otro servicio
            static::refreshTokenService(),
            static::verificationService()
        );
    }

    /**
     * Token Revocation Service con cache
     */
    public static function tokenRevocationService(bool $getShared = true)
    {
        if ($getShared) {
            return static::getSharedInstance('tokenRevocationService');
        }

        return new \App\Services\TokenRevocationService(
            new \App\Models\TokenBlacklistModel(),
            new \App\Models\RefreshTokenModel(),
            static::cache()  // Cache del framework
        );
    }
}
```

### 14.2 Patron Shared Instance

El patron `getShared` implementa **Singleton por request**:

```php
// Primera llamada: crea instancia nueva
$service1 = Services::userService();

// Segunda llamada: retorna la misma instancia
$service2 = Services::userService();

// $service1 === $service2 (true)

// Forzar nueva instancia
$newService = Services::userService(false);
```

### 14.3 Uso en Controladores

```php
// app/Controllers/ApiController.php

protected function getService(): object
{
    if ($this->service === null) {
        $method = $this->serviceName;
        $this->service = \Config\Services::$method();  // Llama Services::userService()
    }
    return $this->service;
}

// En UserController
protected string $serviceName = 'userService';  // Define que servicio usar
```

### 14.4 Grafo de Dependencias

```
AuthService
    ├── UserModel
    ├── JwtService
    ├── RefreshTokenService
    │       ├── RefreshTokenModel
    │       ├── JwtService
    │       └── UserModel
    └── VerificationService
            ├── UserModel
            └── EmailService

FileService
    ├── FileModel
    └── StorageManager
            └── LocalDriver / S3Driver

TokenRevocationService
    ├── TokenBlacklistModel
    ├── RefreshTokenModel
    └── CacheInterface
```

---

## 15. Sistema de Autenticacion JWT

### 15.1 Flujo de Autenticacion

```
┌─────────────────────────────────────────────────────────────────┐
│ 1. LOGIN                                                        │
│    POST /api/v1/auth/login {email, password}                    │
│    │                                                            │
│    ├─> AuthService::loginWithToken()                            │
│    │      ├─> Validar credenciales                              │
│    │      ├─> JwtService::encode() -> Access Token (1h)         │
│    │      └─> RefreshTokenService::issueRefreshToken() (7d)     │
│    │                                                            │
│    └─> Response: {access_token, refresh_token, user}            │
└─────────────────────────────────────────────────────────────────┘
                              │
                              ▼
┌─────────────────────────────────────────────────────────────────┐
│ 2. ACCESO A RECURSOS PROTEGIDOS                                 │
│    GET /api/v1/users                                            │
│    Authorization: Bearer <access_token>                         │
│    │                                                            │
│    ├─> JwtAuthFilter                                            │
│    │      ├─> Extraer token de header                           │
│    │      ├─> JwtService::decode() -> Validar firma y expiry    │
│    │      ├─> TokenRevocationService::isRevoked() -> Check JTI  │
│    │      └─> Inyectar userId, userRole en request              │
│    │                                                            │
│    └─> Continuar al controlador                                 │
└─────────────────────────────────────────────────────────────────┘
                              │
                              ▼
┌─────────────────────────────────────────────────────────────────┐
│ 3. REFRESH TOKEN (cuando access_token expira)                   │
│    POST /api/v1/auth/refresh {refresh_token}                    │
│    │                                                            │
│    ├─> RefreshTokenService::validate()                          │
│    │      ├─> Verificar token existe en DB                      │
│    │      ├─> Verificar no expirado                             │
│    │      └─> Verificar no revocado                             │
│    │                                                            │
│    ├─> JwtService::encode() -> Nuevo Access Token               │
│    ├─> (Opcional) Rotar refresh token                           │
│    │                                                            │
│    └─> Response: {access_token, [refresh_token]}                │
└─────────────────────────────────────────────────────────────────┘
                              │
                              ▼
┌─────────────────────────────────────────────────────────────────┐
│ 4. LOGOUT / REVOCACION                                          │
│    POST /api/v1/auth/revoke                                     │
│    │                                                            │
│    ├─> TokenRevocationService::revoke(jti)                      │
│    │      ├─> Agregar JTI a blacklist                           │
│    │      └─> Invalidar refresh tokens del usuario              │
│    │                                                            │
│    └─> Response: {message: "Token revoked"}                     │
└─────────────────────────────────────────────────────────────────┘
```

### 15.2 Estructura del JWT

```php
// app/Services/JwtService.php

public function encode(UserEntity $user): string
{
    $issuedAt = time();
    $ttl = (int) env('JWT_ACCESS_TOKEN_TTL', 3600);  // 1 hora

    $payload = [
        'iss' => base_url(),                    // Issuer
        'aud' => base_url(),                    // Audience
        'iat' => $issuedAt,                     // Issued At
        'exp' => $issuedAt + $ttl,              // Expiration
        'jti' => bin2hex(random_bytes(16)),     // JWT ID (unico)
        'uid' => $user->id,                     // User ID
        'role' => $user->role,                  // User role
    ];

    return JWT::encode(
        $payload,
        env('JWT_SECRET_KEY'),
        'HS256'  // Algoritmo
    );
}
```

### 15.3 Almacenamiento de Tokens

| Token | Almacenamiento | TTL | Revocable |
|-------|----------------|-----|-----------|
| Access Token | No (stateless) | 1 hora | Si (via JTI blacklist) |
| Refresh Token | `refresh_tokens` table | 7 dias | Si (soft delete) |
| JTI Blacklist | `token_blacklist` table | Hasta exp | N/A |

---

## 16. Patrones de Diseno Utilizados

### 16.1 Service Layer Pattern

**Proposito**: Separar logica de negocio de la capa de presentacion.

```php
// Controller delega a Service
$result = $this->getService()->store($data);

// Service contiene la logica
class UserService {
    public function store(array $data): array {
        // Validacion, transformacion, persistencia
    }
}
```

### 16.2 Repository Pattern (via Model)

**Proposito**: Abstraer el acceso a datos.

```php
// Model encapsula queries
$user = $this->userModel->find($id);
$users = $this->userModel->where('role', 'admin')->findAll();
```

### 16.3 Data Transfer Object (DTO) via Entity

**Proposito**: Transportar datos entre capas con comportamiento.

```php
// Entity con casting y propiedades computadas
$user = $this->userModel->find(1);  // Retorna UserEntity
$user->isAdmin();       // Metodo de dominio
$user->toArray();       // Serializacion controlada
```

### 16.4 Factory Pattern (Services Container)

**Proposito**: Centralizar la creacion de objetos con dependencias.

```php
// Factory en Services.php
public static function authService(bool $getShared = true) {
    return new AuthService(
        new UserModel(),
        static::jwtService(),
        static::refreshTokenService()
    );
}
```

### 16.5 Template Method Pattern (ApiController)

**Proposito**: Definir el esqueleto de un algoritmo, permitiendo que subclases redefinan pasos.

```php
// Template en ApiController
protected function handleRequest(string $method, ?array $params = null) {
    $data = $this->collectRequestData($params);   // Paso 1
    $result = $this->getService()->$method($data); // Paso 2
    return $this->respond($result, $status);       // Paso 3
}

// Subclases solo definen $serviceName
class UserController extends ApiController {
    protected string $serviceName = 'userService';
}
```

### 16.6 Strategy Pattern (Storage Drivers)

**Proposito**: Definir familia de algoritmos intercambiables.

```php
// Interface
interface StorageDriverInterface {
    public function store(string $path, $content): bool;
    public function retrieve(string $path): ?string;
}

// Implementaciones
class LocalDriver implements StorageDriverInterface { ... }
class S3Driver implements StorageDriverInterface { ... }

// Uso via StorageManager
$driver = env('FILE_STORAGE_DRIVER', 'local');
$storage = new StorageManager($driver);
```

### 16.7 Chain of Responsibility (Filters)

**Proposito**: Pasar request a traves de una cadena de handlers.

```
CorsFilter -> ThrottleFilter -> JwtAuthFilter -> RoleAuthFilter -> Controller
```

### 16.8 Decorator Pattern (Model Traits)

**Proposito**: Agregar responsabilidades dinamicamente.

```php
class UserModel extends Model {
    use Filterable;   // Agrega applyFilters()
    use Searchable;   // Agrega search()
    use Auditable;    // Agrega logging
}
```

---

## 17. Estructura de Directorios

```
ci4-api-starter/
├── app/
│   ├── Config/
│   │   ├── App.php                 # Configuracion general
│   │   ├── Database.php            # Conexion DB
│   │   ├── Routes.php              # Definicion de rutas
│   │   ├── Services.php            # Contenedor IoC
│   │   ├── Filters.php             # Aliases de filtros
│   │   └── ...
│   │
│   ├── Controllers/
│   │   ├── ApiController.php       # Base controller (abstract)
│   │   └── Api/V1/
│   │       ├── AuthController.php
│   │       ├── UserController.php
│   │       ├── FileController.php
│   │       ├── TokenController.php
│   │       └── ...
│   │
│   ├── Services/
│   │   ├── UserService.php
│   │   ├── AuthService.php
│   │   ├── JwtService.php
│   │   ├── FileService.php
│   │   └── ...
│   │
│   ├── Models/
│   │   ├── UserModel.php
│   │   ├── FileModel.php
│   │   ├── AuditLogModel.php
│   │   └── ...
│   │
│   ├── Entities/
│   │   ├── UserEntity.php
│   │   └── FileEntity.php
│   │
│   ├── Interfaces/
│   │   ├── UserServiceInterface.php
│   │   ├── AuthServiceInterface.php
│   │   └── ...
│   │
│   ├── Exceptions/
│   │   ├── ApiException.php        # Base exception (abstract)
│   │   ├── NotFoundException.php
│   │   ├── ValidationException.php
│   │   └── ...
│   │
│   ├── Filters/
│   │   ├── JwtAuthFilter.php
│   │   ├── RoleAuthorizationFilter.php
│   │   ├── ThrottleFilter.php
│   │   ├── CorsFilter.php
│   │   └── ...
│   │
│   ├── Validations/
│   │   ├── BaseValidation.php
│   │   ├── AuthValidation.php
│   │   ├── UserValidation.php
│   │   └── Rules/
│   │       └── CustomRules.php
│   │
│   ├── Libraries/
│   │   ├── ApiResponse.php
│   │   ├── Query/
│   │   │   ├── QueryBuilder.php
│   │   │   ├── FilterParser.php
│   │   │   ├── FilterOperatorApplier.php
│   │   │   └── SearchQueryApplier.php
│   │   ├── Storage/
│   │   │   ├── StorageManager.php
│   │   │   └── Drivers/
│   │   │       ├── LocalDriver.php
│   │   │       └── S3Driver.php
│   │   └── Queue/
│   │       ├── QueueManager.php
│   │       └── Jobs/
│   │
│   ├── Traits/
│   │   ├── Filterable.php
│   │   ├── Searchable.php
│   │   └── Auditable.php
│   │
│   ├── Language/
│   │   ├── en/                     # English
│   │   │   ├── Api.php
│   │   │   ├── Auth.php
│   │   │   └── ...
│   │   └── es/                     # Spanish
│   │       └── ...
│   │
│   ├── Database/
│   │   ├── Migrations/
│   │   │   ├── 2026-01-28-014712_CreateUsersTable.php
│   │   │   └── ...
│   │   └── Seeds/
│   │       └── UserSeeder.php
│   │
│   └── Helpers/
│       ├── validation_helper.php
│       ├── security_helper.php
│       └── ...
│
├── tests/
│   ├── Unit/
│   │   ├── Libraries/
│   │   └── Services/
│   ├── Integration/
│   │   ├── Models/
│   │   └── Services/
│   ├── Feature/
│   │   └── Controllers/
│   └── _support/
│       └── Traits/
│           └── CustomAssertionsTrait.php
│
├── writable/
│   ├── logs/
│   ├── cache/
│   └── uploads/
│
├── public/
│   └── index.php                   # Entry point
│
├── .env                            # Variables de entorno
├── phpunit.xml                     # Config tests
└── composer.json
```

---

## 18. Diagramas de Secuencia

### 18.1 Login Flow

```
Cliente          AuthController       AuthService         JwtService        RefreshTokenService
   │                   │                   │                  │                    │
   │ POST /login       │                   │                  │                    │
   │ {user, pass}      │                   │                  │                    │
   │──────────────────>│                   │                  │                    │
   │                   │                   │                  │                    │
   │                   │ loginWithToken()  │                  │                    │
   │                   │──────────────────>│                  │                    │
   │                   │                   │                  │                    │
   │                   │                   │ validate creds   │                    │
   │                   │                   │─────────────────>│                    │
   │                   │                   │                  │                    │
   │                   │                   │ encode(user)     │                    │
   │                   │                   │──────────────────>                    │
   │                   │                   │ <─ access_token  │                    │
   │                   │                   │                  │                    │
   │                   │                   │ issueRefreshToken()                   │
   │                   │                   │─────────────────────────────────────>│
   │                   │                   │ <──────────────── refresh_token       │
   │                   │                   │                  │                    │
   │                   │ <─ ApiResponse    │                  │                    │
   │                   │                   │                  │                    │
   │ <─────────────────│                   │                  │                    │
   │ {access_token,    │                   │                  │                    │
   │  refresh_token,   │                   │                  │                    │
   │  user}            │                   │                  │                    │
```

### 18.2 Protected Request Flow

```
Cliente         JwtAuthFilter      RoleAuthFilter      UserController      UserService
   │                 │                   │                  │                  │
   │ GET /users      │                   │                  │                  │
   │ Bearer: token   │                   │                  │                  │
   │────────────────>│                   │                  │                  │
   │                 │                   │                  │                  │
   │                 │ decode(token)     │                  │                  │
   │                 │ check revocation  │                  │                  │
   │                 │ inject userId     │                  │                  │
   │                 │                   │                  │                  │
   │                 │──────────────────>│                  │                  │
   │                 │                   │ check role       │                  │
   │                 │                   │ hierarchy        │                  │
   │                 │                   │                  │                  │
   │                 │                   │─────────────────>│                  │
   │                 │                   │                  │ index($data)     │
   │                 │                   │                  │─────────────────>│
   │                 │                   │                  │                  │
   │                 │                   │                  │ <─ ApiResponse   │
   │                 │                   │ <────────────────│                  │
   │                 │ <─────────────────│                  │                  │
   │ <───────────────│                   │                  │                  │
   │ {status, data}  │                   │                  │                  │
```

---

## 19. Guia de Extension

### 19.1 Agregar Nuevo Recurso (Product)

#### Paso 1: Migration

```bash
php spark make:migration CreateProductsTable
```

```php
// app/Database/Migrations/xxxx_CreateProductsTable.php
public function up()
{
    $this->forge->addField([
        'id'          => ['type' => 'INT', 'auto_increment' => true],
        'name'        => ['type' => 'VARCHAR', 'constraint' => 255],
        'price'       => ['type' => 'DECIMAL', 'constraint' => '10,2'],
        'description' => ['type' => 'TEXT', 'null' => true],
        'created_at'  => ['type' => 'DATETIME', 'null' => true],
        'updated_at'  => ['type' => 'DATETIME', 'null' => true],
        'deleted_at'  => ['type' => 'DATETIME', 'null' => true],
    ]);
    $this->forge->addPrimaryKey('id');
    $this->forge->createTable('products');
}
```

#### Paso 2: Entity

```php
// app/Entities/ProductEntity.php
class ProductEntity extends Entity
{
    protected $casts = [
        'id'    => 'integer',
        'price' => 'float',
    ];
    protected $dates = ['created_at', 'updated_at', 'deleted_at'];
}
```

#### Paso 3: Model

```php
// app/Models/ProductModel.php
class ProductModel extends Model
{
    use Filterable, Searchable;

    protected $table = 'products';
    protected $returnType = ProductEntity::class;
    protected $allowedFields = ['name', 'price', 'description'];
    protected $useSoftDeletes = true;
    protected $useTimestamps = true;

    protected array $searchableFields = ['name', 'description'];
    protected array $filterableFields = ['name', 'price', 'created_at'];
    protected array $sortableFields = ['id', 'name', 'price', 'created_at'];

    protected $validationRules = [
        'name'  => 'required|max_length[255]',
        'price' => 'required|numeric|greater_than[0]',
    ];
}
```

#### Paso 4: Interface

```php
// app/Interfaces/ProductServiceInterface.php
interface ProductServiceInterface
{
    public function index(array $data): array;
    public function show(array $data): array;
    public function store(array $data): array;
    public function update(array $data): array;
    public function destroy(array $data): array;
}
```

#### Paso 5: Service

```php
// app/Services/ProductService.php
class ProductService implements ProductServiceInterface
{
    public function __construct(protected ProductModel $productModel) {}

    public function store(array $data): array
    {
        if (!$this->productModel->validate($data)) {
            throw new ValidationException(
                lang('Api.validationFailed'),
                $this->productModel->errors()
            );
        }

        $productId = $this->productModel->insert($data);
        $product = $this->productModel->find($productId);

        return ApiResponse::created($product->toArray());
    }

    // ... otros metodos
}
```

#### Paso 6: Registrar Service

```php
// app/Config/Services.php
public static function productService(bool $getShared = true)
{
    if ($getShared) {
        return static::getSharedInstance('productService');
    }
    return new \App\Services\ProductService(new \App\Models\ProductModel());
}
```

#### Paso 7: Controller

```php
// app/Controllers/Api/V1/ProductController.php
class ProductController extends ApiController
{
    protected string $serviceName = 'productService';
}
```

#### Paso 8: Routes

```php
// app/Config/Routes.php
$routes->group('', ['filter' => 'jwtauth'], function ($routes) {
    $routes->get('products', 'ProductController::index');
    $routes->get('products/(:num)', 'ProductController::show/$1');

    $routes->group('', ['filter' => 'roleauth:admin'], function ($routes) {
        $routes->post('products', 'ProductController::create');
        $routes->put('products/(:num)', 'ProductController::update/$1');
        $routes->delete('products/(:num)', 'ProductController::delete/$1');
    });
});
```

#### Paso 9: Language Files

```php
// app/Language/en/Products.php
return [
    'notFound'       => 'Product not found',
    'createdSuccess' => 'Product created successfully',
    'updatedSuccess' => 'Product updated successfully',
    'deletedSuccess' => 'Product deleted successfully',
];

// app/Language/es/Products.php
return [
    'notFound'       => 'Producto no encontrado',
    'createdSuccess' => 'Producto creado exitosamente',
    'updatedSuccess' => 'Producto actualizado exitosamente',
    'deletedSuccess' => 'Producto eliminado exitosamente',
];
```

### 19.2 Agregar Nuevo Filtro

```php
// app/Filters/NewFilter.php
class NewFilter implements FilterInterface
{
    public function before(RequestInterface $request, $arguments = null)
    {
        // Logica antes del controller
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
        // Logica despues del controller
    }
}

// app/Config/Filters.php
public array $aliases = [
    'newfilter' => \App\Filters\NewFilter::class,
];
```

### 19.3 Agregar Nueva Excepcion

```php
// app/Exceptions/PaymentRequiredException.php
class PaymentRequiredException extends ApiException
{
    protected int $statusCode = 402;  // Payment Required
}
```

---

## Resumen

Esta arquitectura proporciona:

- **Escalabilidad**: Capas desacopladas facilitan crecimiento
- **Mantenibilidad**: Codigo organizado y predecible
- **Testabilidad**: Interfaces y DI permiten mocking facil
- **Seguridad**: Multiples capas de validacion y autenticacion
- **Flexibilidad**: Traits y patrones permiten extension sin modificacion
- **Internacionalizacion**: Soporte multi-idioma desde el diseno

Cada componente tiene una responsabilidad clara, facilitando la comprension y modificacion del sistema sin efectos colaterales no deseados.
