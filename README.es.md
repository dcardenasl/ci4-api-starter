# CodeIgniter 4 API Starter Kit

![PHP Version](https://img.shields.io/badge/PHP-8.1%20%7C%208.2%20%7C%208.3-blue)
![CodeIgniter](https://img.shields.io/badge/CodeIgniter-4.6-orange)
![Tests](https://img.shields.io/badge/tests-passing-success)
![License](https://img.shields.io/badge/license-MIT-blue)

[English](README.md) | Español

Una plantilla de API REST lista para produccion con CodeIgniter 4, autenticacion JWT, arquitectura en capas limpia y cobertura de tests completa.

## Caracteristicas

- **Autenticacion JWT** - Tokens de acceso, tokens de refresco y revocacion
- **Control de Acceso por Roles** - Roles user, admin y superadmin con proteccion por middleware
- **Sistema de Email** - Verificacion, restablecimiento de contrasena, soporte de colas
- **Gestion de Archivos** - Subida/descarga con drivers local y S3
- **Consultas Avanzadas** - Paginacion, filtrado, busqueda, ordenamiento
- **Health Checks** - Endpoints listos para Kubernetes (`/health`, `/ready`, `/live`)
- **Auditoria** - Registro automatico de cambios en datos
- **Documentacion OpenAPI** - Swagger docs auto-generados
- **Suite de Tests Completa** - Tests unitarios, de integracion y funcionales

## Inicio Rapido

### Opcion 1: Usar Plantilla de GitHub (Recomendado)

1. Haz clic en **"Use this template"** en la parte superior de esta pagina
2. Clona tu nuevo repositorio
3. Ejecuta el script de inicializacion:

```bash
chmod +x init.sh && ./init.sh
```

Tu API estara corriendo en `http://localhost:8080`

### Opcion 2: Configuracion Manual

```bash
# Instalar dependencias
composer install

# Configurar entorno
cp .env.example .env

# Generar claves de seguridad
openssl rand -base64 64  # Agregar a JWT_SECRET_KEY en .env
php spark key:generate   # Muestra la clave de encriptacion

# Configurar base de datos (configura .env primero)
php spark migrate

# Crear el primer superadmin (ejecutar una sola vez)
php spark users:bootstrap-superadmin --email superadmin@ejemplo.com --password 'ContrasenaFuerte123!' --first-name Super --last-name Admin

# Iniciar servidor
php spark serve
```

## Endpoints de la API

### Autenticacion (Publico)
```
POST /api/v1/auth/register     Registrar nuevo usuario
POST /api/v1/auth/login        Iniciar sesion (devuelve tokens)
POST /api/v1/auth/refresh      Refrescar token de acceso
POST /api/v1/auth/forgot-password   Solicitar reset de contrasena
POST /api/v1/auth/reset-password    Restablecer contrasena
GET  /api/v1/auth/validate-reset-token Validar token de reset
GET  /api/v1/auth/verify-email      Verificar email (token en query)
POST /api/v1/auth/verify-email      Verificar email (token en body/form)
```

### Verificacion de correo (Opcional)

Configura `AUTH_REQUIRE_EMAIL_VERIFICATION` en `.env` para controlar si la verificacion de correo es obligatoria antes de login/refresh/rutas protegidas. El valor por defecto es `true`.

### Autenticacion (Protegido)
```
GET  /api/v1/auth/me           Obtener usuario actual
POST /api/v1/auth/revoke       Revocar token actual
POST /api/v1/auth/revoke-all   Revocar todos los tokens del usuario
POST /api/v1/auth/resend-verification Reenviar correo de verificacion
```

### Usuarios (Protegido)
```
GET    /api/v1/users           Listar usuarios (solo admin; paginado, filtrable)
GET    /api/v1/users/{id}      Obtener usuario por ID (Propio o Admin)
POST   /api/v1/users           Crear usuario (admin/superadmin con restricciones)
PUT    /api/v1/users/{id}      Actualizar usuario (admin/superadmin con restricciones)
DELETE /api/v1/users/{id}      Eliminar usuario (admin/superadmin con restricciones)
POST   /api/v1/users/{id}/approve Aprobar usuario (solo admin)
```
Nota: `admin` solo puede gestionar cuentas con rol `user`. `superadmin` puede gestionar roles privilegiados. Las cuentas `superadmin` no aparecen en listados de `/api/v1/users`.

### Archivos (Protegido)
```
GET    /api/v1/files           Listar archivos del usuario
POST   /api/v1/files/upload    Subir archivo
GET    /api/v1/files/{id}      Descargar archivo (local) o devolver metadata/URL (S3)
DELETE /api/v1/files/{id}      Eliminar archivo
```

### API Keys (Admin)
```
GET    /api/v1/api-keys           Listar API keys
GET    /api/v1/api-keys/{id}      Obtener detalle de API key
POST   /api/v1/api-keys           Crear API key
PUT    /api/v1/api-keys/{id}      Actualizar API key
DELETE /api/v1/api-keys/{id}      Eliminar API key
```

### Metricas y Auditoria (Admin)
```
GET  /api/v1/metrics                 Obtener resumen de metricas
GET  /api/v1/metrics/requests        Obtener metricas recientes de requests
GET  /api/v1/metrics/slow-requests   Obtener requests lentas
GET  /api/v1/metrics/custom/{metric} Obtener valores de metrica personalizada
POST /api/v1/metrics/record          Registrar metrica personalizada
GET  /api/v1/audit                   Listar logs de auditoria
GET  /api/v1/audit/{id}              Obtener detalle de log de auditoria
GET  /api/v1/audit/entity/{type}/{id} Obtener auditoria por entidad
```

### Health (Publico)
```
GET /health    Verificacion completa del sistema
GET /ping      Verificacion simple de disponibilidad
GET /ready     Sonda de readiness para Kubernetes
GET /live      Sonda de liveness para Kubernetes
```

## Ejemplos de Uso

**Registrar:**
```bash
curl -X POST http://localhost:8080/api/v1/auth/register \
  -H "Content-Type: application/json" \
  -d '{"email":"juan@ejemplo.com","first_name":"Juan","last_name":"Perez","password":"ContrasenaSegura123!"}'
```

Nota de respuesta: el auto-registro crea una cuenta `pending_approval`. El login solo es posible después de la aprobación de un administrador.

**Admin crea usuario (flujo de invitación):**
```bash
curl -X POST http://localhost:8080/api/v1/users \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer ADMIN_ACCESS_TOKEN" \
  -d '{"email":"invitado@ejemplo.com","first_name":"Invitado","last_name":"Usuario","role":"user"}'
```
El admin no envía contraseña. El sistema la genera internamente y envía un correo para que el usuario defina la suya.

**Iniciar sesion:**
```bash
curl -X POST http://localhost:8080/api/v1/auth/login \
  -H "Content-Type: application/json" \
  -d '{"email":"juan@ejemplo.com","password":"ContrasenaSegura123!"}'
```

**Refrescar token:**
```bash
curl -X POST http://localhost:8080/api/v1/auth/refresh \
  -H "Content-Type: application/json" \
  -d '{"refresh_token":"TU_REFRESH_TOKEN"}'
```

**Usar endpoint protegido:**
```bash
curl -X GET http://localhost:8080/api/v1/users \
  -H "Authorization: Bearer TU_TOKEN_DE_ACCESO"
```

**Consulta con filtros:**
```bash
curl -X GET "http://localhost:8080/api/v1/users?filter[role][eq]=admin&search=juan&page=1&limit=10" \
  -H "Authorization: Bearer TU_TOKEN_DE_ACCESO"
```

## Documentacion interactiva y Postman

**Swagger UI local:**
```bash
# Generar/actualizar especificacion OpenAPI
php spark swagger:generate

# Levantar Swagger UI con Docker
docker run --rm -p 8081:8080 \
  -e SWAGGER_JSON=/swagger.json \
  -v "$(pwd)/public/swagger.json:/swagger.json" \
  swaggerapi/swagger-ui
```
Abrir `http://localhost:8081`.
Archivo generado: `public/swagger.json` (servido en `http://localhost:8080/swagger.json`)

**Swagger UI embebido (sin Docker):**
- Archivo: `public/docs/index.html`
- Abrir `http://localhost:8080/docs/`

**Postman:**
- Coleccion (API completa): `docs/postman/ci4-api.postman_collection.json`
  Las variables estan en la coleccion (`baseUrl`, `accessToken`, `refreshToken`, `userId`, `fileId`).
- Entorno opcional: `docs/postman/ci4-api.postman_environment.json`

## Estructura del Proyecto

```
app/
├── Controllers/
│   ├── ApiController.php          # Controlador base
│   └── Api/V1/                    # Controladores API v1 agrupados por dominio (Auth, Identity, Users, Files, Admin, System)
├── Services/                      # Logica de negocio
├── Interfaces/                    # Interfaces de servicios
├── Models/                        # Modelos de base de datos
├── Entities/                      # Entidades de datos
├── Filters/                       # Filtros HTTP (auth, throttle, cors)
├── Exceptions/                    # Excepciones personalizadas
├── Libraries/
│   ├── ApiResponse.php           # Respuestas estandarizadas
│   └── Query/                    # Utilidades del query builder
└── Traits/                       # Traits de modelos (Filterable, Searchable)

tests/
├── Unit/                         # Sin base de datos
│   ├── Libraries/                # Tests de ApiResponse
│   └── Services/                 # Tests unitarios de servicios
├── Integration/                  # Requiere base de datos
│   ├── Models/                   # Tests de modelos
│   └── Services/                 # Tests de integracion de servicios
└── Feature/                      # Tests HTTP completos
    └── Controllers/              # Tests de endpoints
```

## Testing

```bash
# Ejecutar todos los tests
vendor/bin/phpunit

# Ejecutar con salida legible
vendor/bin/phpunit --testdox

# Ejecutar suites especificas
vendor/bin/phpunit tests/Unit           # Rapidos, sin BD
vendor/bin/phpunit tests/Integration    # Necesita BD
vendor/bin/phpunit tests/Feature        # Tests HTTP
```

## Funciones de Consulta Avanzada

### Paginacion
```
GET /api/v1/users?page=2&limit=20
```

### Filtrado
```
GET /api/v1/users?filter[role][eq]=admin
GET /api/v1/users?filter[email][like]=%@gmail.com
GET /api/v1/users?filter[created_at][gt]=2024-01-01
```

**Operadores:** `eq`, `neq`, `gt`, `gte`, `lt`, `lte`, `like`, `in`

### Busqueda
```
GET /api/v1/users?search=juan
```

### Ordenamiento
```
GET /api/v1/users?sort=created_at&direction=desc
```

### Combinado
```
GET /api/v1/users?search=juan&filter[role][eq]=user&sort=created_at&direction=desc&page=1&limit=10
```

## Configuracion

### Requerido (.env)
```env
JWT_SECRET_KEY=tu-clave-secreta-min-32-caracteres
encryption.key=hex2bin:tu-clave-de-encriptacion
database.default.hostname=localhost
database.default.database=tu_base_de_datos
database.default.username=root
database.default.password=
```

### Opcional (.env)
```env
# JWT
JWT_ACCESS_TOKEN_TTL=3600
JWT_REFRESH_TOKEN_TTL=604800

# Email
EMAIL_FROM_ADDRESS=noreply@ejemplo.com
EMAIL_SMTP_HOST=smtp.ejemplo.com

# Almacenamiento de archivos
FILE_STORAGE_DRIVER=local
FILE_MAX_SIZE=10485760
FILE_UPLOAD_PATH=writable/uploads/

# Limite de peticiones
RATE_LIMIT_REQUESTS=60
RATE_LIMIT_USER_REQUESTS=100
RATE_LIMIT_WINDOW=60
AUTH_RATE_LIMIT_REQUESTS=5
AUTH_RATE_LIMIT_WINDOW=900

# Valores por defecto para API Keys
API_KEY_RATE_LIMIT_DEFAULT=600
API_KEY_USER_RATE_LIMIT_DEFAULT=60
API_KEY_IP_RATE_LIMIT_DEFAULT=200
API_KEY_WINDOW_DEFAULT=60

# CORS
CORS_ALLOWED_ORIGINS=http://localhost:3000,https://app.example.com
CORS_SUPPORTS_CREDENTIALS=false

# Observabilidad SLO
SLO_API_P95_TARGET_MS=500
```

## Docker

```bash
docker-compose up -d

# API: http://localhost:8080
# MySQL: localhost:3306
# Adminer: http://localhost:8081
```

## Caracteristicas de Seguridad

- JWT con JTI para revocacion individual de tokens
- Hash de contrasenas con Bcrypt
- Proteccion contra ataques de timing en login
- Contrasenas nunca expuestas en respuestas
- Sanitizacion de entrada (prevencion XSS)
- Proteccion contra inyeccion SQL (query builder)
- Limite de peticiones
- Eliminacion suave (soft deletes)

### Rotación de Secretos

Rota los secretos de seguridad regularmente para mantener la postura de seguridad.

**Cuándo Rotar:**
- Después de una brecha de seguridad o sospecha de compromiso
- Cada 90 días (recomendado para secretos JWT)
- Cuando un desarrollador con acceso deja el equipo
- Antes del despliegue inicial en producción

**Cómo Rotar el Secreto JWT:**
```bash
# 1. Generar nuevo secreto (64+ caracteres recomendado)
openssl rand -base64 64

# 2. Actualizar archivo .env
JWT_SECRET_KEY='<pegar-nuevo-secreto-aqui>'

# 3. Reiniciar aplicación
# Todos los tokens existentes serán invalidados - los usuarios deben iniciar sesión nuevamente
```

**Cómo Rotar la Clave de Encriptación:**
```bash
# 1. Generar nueva clave
openssl rand -hex 32

# 2. Actualizar archivo .env
encryption.key=hex2bin:<pegar-nueva-clave-aqui>

# 3. Reiniciar aplicación
# Nota: Los datos encriptados existentes pueden volverse ilegibles
```

**⚠️ Notas Importantes:**
- Rotar el secreto JWT invalida todos los tokens activos inmediatamente
- Rotar la clave de encriptación puede invalidar datos de sesión encriptados
- Siempre prueba la rotación de secretos en el entorno de staging primero
- Mantén los secretos antiguos por 24-48 horas en caso de necesitar revertir
- Documenta la fecha y razón de la rotación para la auditoría

## Requisitos

- PHP 8.1+
- MySQL 8.0+
- Composer 2.x
- Extensiones: mysqli, mbstring, intl, json

## Documentacion

- **ARCHITECTURE.md** - Decisiones arquitectónicas y patrones de diseño explicados
- **CLAUDE.md** - Guia de desarrollo para asistentes de IA (Claude Code)
- **.claude/agents/** - Agente especializado de Claude Code para generación CRUD
- **public/swagger.json** - Documentacion OpenAPI (generar con `php spark swagger:generate`)

**¿Nuevo en el proyecto?** Empieza con `ARCHITECTURE.md` para entender por qué el código está estructurado así.

### Desarrollo Asistido por IA

Esta plantilla incluye un agente especializado de [Claude Code](https://claude.ai/code) que actúa como arquitecto experto para este proyecto. Cuando usas Claude Code, el agente automáticamente te ayuda a:
- Crear recursos CRUD completos siguiendo todos los patrones arquitectónicos
- Generar migraciones, entidades, modelos, servicios, controladores y tests
- Mantener consistencia con las convenciones de código existentes
- Seguir las mejores prácticas de seguridad y testing

Consulta `.claude/README.md` para detalles sobre el uso del agente.

## Licencia

Licencia MIT

## Contribuir

1. Haz fork del repositorio
2. Crea una rama de feature (`git checkout -b feature/mejora`)
3. Haz commit de los cambios (`git commit -m 'Agregar mejora'`)
4. Push a la rama (`git push origin feature/mejora`)
5. Abre un Pull Request
