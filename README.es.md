# CodeIgniter 4 API Starter Kit

![Versión PHP](https://img.shields.io/badge/PHP-8.1%20%7C%208.2%20%7C%208.3-blue)
![CodeIgniter](https://img.shields.io/badge/CodeIgniter-4.6-orange)
![Tests](https://img.shields.io/badge/tests-188%20passed-success)
![Licencia](https://img.shields.io/badge/license-MIT-blue)

[English](README.md) | Español

Una plantilla REST API lista para producción en CodeIgniter 4 con autenticación JWT, documentación OpenAPI modular y arquitectura limpia por capas.

**Perfecto para:** Iniciar nuevos proyectos API, construir microservicios o aprender desarrollo moderno de APIs en PHP.

## ✨ Características

### Características Principales
- 🔐 **Autenticación JWT** - Autenticación segura basada en tokens con refresh tokens y revocación
- 📧 **Sistema de Email** - Verificación de email, recuperación de contraseña, infraestructura de colas
- 📁 **Gestión de Archivos** - Carga/gestión de archivos con soporte para almacenamiento en nube
- 🔍 **Consultas Avanzadas** - Paginación, filtrado, búsqueda, ordenamiento
- 📊 **Monitoreo** - Checks de salud, métricas, registro de peticiones, auditoría
- 🌍 **Internacionalización** - Detección de locale desde cabecera Accept-Language

### Arquitectura y Experiencia de Desarrollo
- 📚 **Documentación OpenAPI Modular** - Documentación basada en esquemas, 60% menos código repetitivo
- 🏗️ **Arquitectura Limpia** - Patrón Controller → Service → Repository → Entity
- 🎯 **ApiController Base** - Manejo automático de peticiones, 62% menos código
- 🔌 **Interfaces de Servicio** - Diseño basado en interfaces para mejor testabilidad
- ✅ **188 Tests** - Cobertura completa de tests con PHPUnit
- 🚀 **CI/CD Listo** - GitHub Actions configurado para PHP 8.1, 8.2, 8.3
- 🔒 **Seguro por Defecto** - Hashing bcrypt, protección timing-attack, validación de entrada
- 🐳 **Soporte Docker** - Containerización lista para producción incluida

## 🚀 Inicio Rápido (1 minuto)

### Usando Plantilla de GitHub (Recomendado)

1. **Haz clic en el botón "Use this template"** en la parte superior de esta página
2. **Clona tu nuevo repositorio:**
   ```bash
   git clone https://github.com/TU-USUARIO/TU-NUEVO-REPO.git
   cd TU-NUEVO-REPO
   ```

3. **Ejecuta el script de inicialización:**
   ```bash
   chmod +x init.sh
   ./init.sh
   ```

¡Eso es todo! El script:
- ✓ Instalará dependencias
- ✓ Generará claves seguras (JWT + encriptación)
- ✓ Configurará el entorno
- ✓ Creará la base de datos
- ✓ Ejecutará las migraciones
- ✓ Generará la documentación API
- ✓ Iniciará el servidor de desarrollo

Tu API estará corriendo en `http://localhost:8080` 🎉

### Configuración Manual

```bash
# 1. Instalar dependencias
composer install

# 2. Configurar entorno
cp .env.example .env

# 3. Generar claves seguras
openssl rand -base64 64  # Agregar a JWT_SECRET_KEY en .env
php spark key:generate   # Agregar a encryption.key en .env

# 4. Configurar base de datos en .env, luego:
php setup_mysql.php      # Crear bases de datos
php spark migrate        # Ejecutar migraciones

# 5. Iniciar servidor
php spark serve
```

## 📖 Endpoints de la API

### Autenticación (Público)
```bash
POST /api/v1/auth/register           # Registrar nuevo usuario
POST /api/v1/auth/login              # Login (devuelve JWT + refresh token)
POST /api/v1/auth/refresh            # Refrescar access token
POST /api/v1/auth/verify-email       # Verificar dirección de email
POST /api/v1/auth/forgot-password    # Solicitar recuperación de contraseña
GET  /api/v1/auth/validate-reset-token  # Validar token de recuperación
POST /api/v1/auth/reset-password     # Restablecer contraseña
```

### Autenticación (Protegido)
```bash
GET  /api/v1/auth/me                 # Obtener usuario actual
POST /api/v1/auth/resend-verification # Reenviar email de verificación
POST /api/v1/auth/revoke             # Revocar token actual
POST /api/v1/auth/revoke-all         # Revocar todos los tokens del usuario
```

### Usuarios (Protegido - Requiere JWT)
```bash
GET    /api/v1/users              # Listar usuarios (soporta paginación, filtrado, búsqueda)
GET    /api/v1/users/{id}         # Obtener usuario por ID
POST   /api/v1/users              # Crear usuario (solo admin)
PUT    /api/v1/users/{id}         # Actualizar usuario (solo admin)
DELETE /api/v1/users/{id}         # Eliminar usuario (solo admin, soft delete)
```

### Archivos (Protegido - Requiere JWT)
```bash
GET    /api/v1/files              # Listar archivos subidos
POST   /api/v1/files/upload       # Subir archivo
GET    /api/v1/files/{id}         # Obtener detalles del archivo
DELETE /api/v1/files/{id}         # Eliminar archivo
```

### Checks de Salud (Público, Sin Rate Limiting)
```bash
GET /health                        # Check de salud completo del sistema
GET /ping                          # Check simple de disponibilidad
GET /ready                         # Readiness probe (Kubernetes)
GET /live                          # Liveness probe (Kubernetes)
```

### Métricas (Solo Admin)
```bash
GET  /api/v1/metrics               # Resumen de métricas del sistema
GET  /api/v1/metrics/requests      # Métricas de peticiones
GET  /api/v1/metrics/slow-requests # Log de peticiones lentas
GET  /api/v1/metrics/custom/{name} # Métrica personalizada
POST /api/v1/metrics/record        # Registrar métrica personalizada
```

### Auditoría (Solo Admin)
```bash
GET /api/v1/audit                  # Listar todos los logs de auditoría
GET /api/v1/audit/{id}             # Obtener entrada específica de auditoría
GET /api/v1/audit/entity/{type}/{id} # Obtener auditorías para entidad específica
```

### Ejemplos de Uso

**Registrarse:**
```bash
curl -X POST http://localhost:8080/api/v1/auth/register \
  -H "Content-Type: application/json" \
  -d '{"username":"juan","email":"juan@ejemplo.com","password":"Pass123!"}'
```

**Login con refresh token:**
```bash
curl -X POST http://localhost:8080/api/v1/auth/login \
  -H "Content-Type: application/json" \
  -d '{"username":"juan","password":"Pass123!"}'
# Devuelve: {"status":"success","data":{"token":"...","refreshToken":"..."}}
```

**Refrescar access token:**
```bash
curl -X POST http://localhost:8080/api/v1/auth/refresh \
  -H "Content-Type: application/json" \
  -d '{"refreshToken":"tu-refresh-token"}'
```

**Usar endpoint protegido con filtrado:**
```bash
TOKEN="tu-jwt-token-aqui"
curl -X GET "http://localhost:8080/api/v1/users?filter[role][eq]=admin&search=juan&page=1&perPage=10" \
  -H "Authorization: Bearer $TOKEN"
```

**Subir archivo:**
```bash
curl -X POST http://localhost:8080/api/v1/files/upload \
  -H "Authorization: Bearer $TOKEN" \
  -F "file=@/ruta/al/archivo.pdf"
```

**Verificar salud del sistema:**
```bash
curl http://localhost:8080/health
# Devuelve: {"status":"healthy","checks":{"database":"ok","cache":"ok","storage":"ok"}}
```

**Ver Documentación de la API:**
- Swagger JSON: http://localhost:8080/swagger.json
- Importar en [Swagger UI](https://editor.swagger.io/) o [Postman](https://www.postman.com/)

## 🏗️ Estructura del Proyecto

```
app/
├── Commands/
│   └── GenerateSwagger.php         # Generador de documentación OpenAPI
├── Config/
│   ├── OpenApi.php                 # Configuración documentación API
│   └── Routes.php                  # Definición de rutas
├── Controllers/
│   ├── ApiController.php           # Controlador base (auto request/response)
│   └── Api/V1/
│       ├── AuthController.php      # Autenticación (login, register, me)
│       ├── UserController.php      # CRUD de usuarios
│       ├── TokenController.php     # Refresh y revocación de tokens
│       ├── VerificationController.php  # Verificación de email
│       ├── PasswordResetController.php # Recuperación de contraseña
│       ├── FileController.php      # Gestión de archivos
│       ├── HealthController.php    # Checks de salud
│       ├── MetricsController.php   # Métricas de monitoreo
│       └── AuditController.php     # Auditoría
├── Documentation/                  # Esquemas OpenAPI modulares
│   ├── Schemas/                    # Modelos de datos reutilizables
│   ├── Responses/                  # Respuestas de error estándar
│   └── RequestBodies/              # Payloads de petición
├── Services/
│   ├── JwtService.php              # Operaciones JWT
│   ├── UserService.php             # Lógica de negocio de usuarios
│   ├── RefreshTokenService.php     # Refresh de tokens
│   ├── TokenRevocationService.php  # Revocación de tokens
│   ├── EmailService.php            # Envío de emails
│   ├── VerificationService.php     # Verificación de email
│   ├── PasswordResetService.php    # Recuperación de contraseña
│   ├── FileService.php             # Operaciones de archivos
│   └── AuditService.php            # Registro de auditoría
├── Interfaces/                     # Interfaces de servicios
│   ├── UserServiceInterface.php
│   ├── JwtServiceInterface.php
│   ├── RefreshTokenServiceInterface.php
│   ├── TokenRevocationServiceInterface.php
│   ├── FileServiceInterface.php
│   └── AuditServiceInterface.php
├── Filters/
│   ├── CorsFilter.php              # Manejo de CORS
│   ├── ThrottleFilter.php          # Rate limiting
│   ├── JwtAuthFilter.php           # Validación JWT
│   ├── RoleAuthorizationFilter.php # Acceso basado en roles
│   ├── LocaleFilter.php            # Detección de locale i18n
│   └── RequestLoggingFilter.php    # Registro de peticiones
├── Traits/
│   ├── Auditable.php               # Auto registro de auditoría
│   ├── Filterable.php              # Filtrado avanzado
│   └── Searchable.php              # Búsqueda de texto completo
├── Models/
│   ├── UserModel.php               # Operaciones de base de datos
│   ├── RefreshTokenModel.php
│   ├── RevokedTokenModel.php
│   ├── FileModel.php
│   └── AuditLogModel.php
└── Entities/
    ├── UserEntity.php              # Modelos de datos
    ├── RefreshTokenEntity.php
    ├── FileEntity.php
    └── AuditLogEntity.php
```

## 🔍 Características de Consulta Avanzadas

La API soporta capacidades de consulta potentes en endpoints de listado:

### Paginación
```bash
GET /api/v1/users?page=1&perPage=20
```

### Filtrado
Usa operadores de campo para filtrar resultados:
```bash
# Igual
GET /api/v1/users?filter[role][eq]=admin

# Similar (coincidencia parcial)
GET /api/v1/users?filter[email][like]=%@gmail.com

# Mayor que
GET /api/v1/users?filter[created_at][gt]=2025-01-01

# Múltiples filtros (lógica AND)
GET /api/v1/users?filter[role][eq]=admin&filter[email][like]=%@empresa.com
```

**Operadores soportados:** `eq`, `neq`, `gt`, `gte`, `lt`, `lte`, `like`, `in`

### Búsqueda
Búsqueda de texto completo en campos configurados:
```bash
GET /api/v1/users?search=juan
# Busca en username, email, first_name, last_name
```

### Ordenamiento
```bash
GET /api/v1/users?sort=created_at&direction=desc
GET /api/v1/users?sort=email&direction=asc
```

### Combinando Características
```bash
GET /api/v1/users?search=juan&filter[role][eq]=user&sort=created_at&direction=desc&page=1&perPage=10
```

## 🎯 Agregando Nuevos Recursos

Crear un nuevo recurso es rápido con los patrones incluidos:

```bash
# 1. Crear migración
php spark make:migration CreateProductsTable

# 2. Crear archivos siguiendo el patrón:
app/Entities/ProductEntity.php       # Modelo de datos
app/Models/ProductModel.php          # Capa de base de datos
app/Services/ProductService.php      # Lógica de negocio
app/Controllers/Api/V1/ProductController.php  # Endpoints API
app/Documentation/Schemas/ProductSchema.php   # Esquema OpenAPI

# 3. Agregar rutas en app/Config/Routes.php
$routes->resource('api/v1/products', ['controller' => 'Api\V1\ProductController']);

# 4. Generar documentación
php spark swagger:generate
```

**Controlador de Ejemplo (extiende ApiController):**
```php
class ProductController extends ApiController
{
    protected ProductService $productService;

    protected function getService(): object
    {
        return $this->productService;
    }

    protected function getSuccessStatus(string $method): int
    {
        return match($method) {
            'store' => 201,
            default => 200,
        };
    }

    public function index(): ResponseInterface
    {
        return $this->handleRequest('index');  // ¡Eso es todo!
    }
}
```

**Resultado:** Recurso CRUD completo en ~30 minutos en lugar de 2-3 horas.

## 📚 Documentación

- **[DEVELOPMENT.md](DEVELOPMENT.md)** - Guía completa de arquitectura, patrones y mejores prácticas
- **[TESTING.md](TESTING.md)** - Guía de testing con ejemplos
- **[SECURITY.md](SECURITY.md)** - Directrices de seguridad y mejores prácticas
- **[CI_CD.md](CI_CD.md)** - Configuración CI/CD y despliegue
- **[TEMPLATE_SETUP.md](TEMPLATE_SETUP.md)** - Cómo configurar como plantilla de GitHub

## ⚙️ Requisitos

- **PHP** 8.1+ (8.2 o 8.3 recomendado)
- **MySQL** 8.0+
- **Composer** 2.x
- **Extensiones**: mysqli, mbstring, intl, json

## 🔒 Características de Seguridad

- ✅ Autenticación JWT con Bearer tokens
- ✅ Refresh tokens con rotación segura
- ✅ Revocación de tokens (individual y todos los tokens del usuario)
- ✅ Hashing de contraseñas con Bcrypt
- ✅ Protección contra timing-attack en login
- ✅ Contraseñas nunca expuestas en respuestas
- ✅ Expiración de tokens (1 hora, configurable)
- ✅ Verificación de email requerida
- ✅ Flujo seguro de recuperación de contraseña
- ✅ Validación de entrada en capa de modelo
- ✅ Protección contra inyección SQL (query builder)
- ✅ Rate limiting en todos los endpoints API
- ✅ Registro de peticiones para monitoreo de seguridad
- ✅ Auditoría para operaciones sensibles
- ✅ Protección CSRF disponible
- ✅ Soft deletes para recuperación de datos

**Importante:** Antes de producción:
1. Cambiar `JWT_SECRET_KEY` a un valor aleatorio fuerte
2. Configurar servicio de email (configuración SMTP)
3. Configurar almacenamiento en nube (compatible con S3)
4. Usar solo HTTPS
5. Revisar [SECURITY.md](SECURITY.md) para checklist completo

## 🧪 Testing

Ejecutar la suite completa de tests:

```bash
vendor/bin/phpunit           # Todos los 188 tests
vendor/bin/phpunit --testdox # Salida legible
```

**Cobertura de Tests:**
- ✅ 188 tests con assertions comprensivas
- ✅ Controllers (endpoints API)
- ✅ Services (lógica de negocio)
- ✅ Models (operaciones de base de datos)
- ✅ Autenticación JWT y gestión de tokens
- ✅ Verificación de email y recuperación de contraseña
- ✅ Carga y gestión de archivos
- ✅ Auditoría y métricas

CI ejecuta automáticamente los tests en PHP 8.1, 8.2 y 8.3.

## 🐳 Soporte Docker

```bash
# Configuración lista para producción
docker-compose up -d

# Tu API corre en http://localhost:8080
# MySQL en localhost:3306
# Adminer en http://localhost:8081
```

Ver `docker-compose.yml` para configuración.

## 🛠️ Comandos Comunes

```bash
# Desarrollo
php spark serve                   # Iniciar servidor dev
php spark routes                  # Listar todas las rutas
php spark swagger:generate        # Regenerar documentación API

# Base de datos
php spark migrate                 # Ejecutar migraciones
php spark migrate:rollback        # Revertir migraciones
php spark db:seed UserSeeder      # Sembrar datos

# Testing
vendor/bin/phpunit                # Ejecutar todos los tests
composer audit                    # Check de seguridad
```

## 📦 Qué Está Incluido

### Dependencias Principales
- `codeigniter4/framework` ^4.5 - Framework principal
- `firebase/php-jwt` ^7.0 - Autenticación JWT
- `zircote/swagger-php` ^6.0 - Documentación OpenAPI

### Dependencias de Desarrollo
- `phpunit/phpunit` - Framework de testing
- `fakerphp/faker` - Generación de datos de prueba
- `php-cs-fixer` - Cumplimiento de estilo de código
- `phpstan` - Análisis estático
- Configuración Docker

### Características Incluidas
- Autenticación JWT con refresh tokens y revocación
- Verificación de email y recuperación de contraseña
- Carga de archivos con soporte de almacenamiento en nube
- Paginación, filtrado, búsqueda avanzados
- Checks de salud para Kubernetes/monitoreo
- Métricas y seguimiento de rendimiento
- Registro de auditoría
- Registro de peticiones y rate limiting
- Internacionalización (i18n)
- Documentación OpenAPI completa

## 🔄 Mantenerse Actualizado

Esta es una plantilla starter, no un paquete. Después de crear tu proyecto:

1. **Personaliza según tus necesidades** - Este es tu codebase ahora
2. **Elimina características no utilizadas** - Borra lo que no necesites
3. **Agrega tus recursos** - Sigue los patrones establecidos
4. **Verifica actualizaciones** - Ocasionalmente revisa la plantilla original

## 🤝 Contribuir

¡Las contribuciones para mejorar el starter kit son bienvenidas!

1. Fork el repositorio
2. Crea rama de característica (`git checkout -b feature/mejora`)
3. Commit de cambios (`git commit -m 'Agregar mejora'`)
4. Push a la rama (`git push origin feature/mejora`)
5. Abrir Pull Request

## 📄 Licencia

Licencia MIT - úsala para proyectos personales o comerciales.

## 🙏 Reconocimientos

Construido con:
- [CodeIgniter 4](https://codeigniter.com/)
- [firebase/php-jwt](https://github.com/firebase/php-jwt)
- [swagger-php](https://github.com/zircote/swagger-php)

## 💬 Soporte

- **Issues:** [GitHub Issues](https://github.com/dcardenasl/ci4-api-starter/issues)
- **Discusiones:** [GitHub Discussions](https://github.com/dcardenasl/ci4-api-starter/discussions)
- **Documentación:** Ver la carpeta `/docs`

---

**¿Listo para construir tu API?** ¡Haz clic en "Use this template" arriba para comenzar! 🚀
