# CodeIgniter 4 API Starter Kit

![PHP Version](https://img.shields.io/badge/PHP-8.2%20%7C%208.3-blue)
![CodeIgniter](https://img.shields.io/badge/CodeIgniter-4.6-orange)
![Tests](https://img.shields.io/badge/tests-117%20passing-success)
![License](https://img.shields.io/badge/license-MIT-blue)

[English](README.md) | Español

Una plantilla de API REST lista para produccion con CodeIgniter 4, autenticacion JWT, arquitectura en capas limpia y cobertura de tests completa.

## Caracteristicas

- **Autenticacion JWT** - Tokens de acceso, tokens de refresco y revocacion
- **Control de Acceso por Roles** - Roles admin y user con proteccion por middleware
- **Sistema de Email** - Verificacion, restablecimiento de contrasena, soporte de colas
- **Gestion de Archivos** - Subida/descarga con soporte de almacenamiento en la nube (S3)
- **Consultas Avanzadas** - Paginacion, filtrado, busqueda, ordenamiento
- **Health Checks** - Endpoints listos para Kubernetes (`/health`, `/ready`, `/live`)
- **Auditoria** - Registro automatico de cambios en datos
- **Documentacion OpenAPI** - Swagger docs auto-generados
- **117 Tests** - Tests unitarios, de integracion y funcionales

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
POST /api/v1/auth/verify-email      Verificar email
```

### Autenticacion (Protegido)
```
GET  /api/v1/auth/me           Obtener usuario actual
POST /api/v1/auth/revoke       Revocar token actual
POST /api/v1/auth/revoke-all   Revocar todos los tokens del usuario
```

### Usuarios (Protegido)
```
GET    /api/v1/users           Listar usuarios (paginado, filtrable)
GET    /api/v1/users/{id}      Obtener usuario por ID
POST   /api/v1/users           Crear usuario (solo admin)
PUT    /api/v1/users/{id}      Actualizar usuario (solo admin)
DELETE /api/v1/users/{id}      Eliminar usuario (solo admin)
```

### Archivos (Protegido)
```
GET    /api/v1/files           Listar archivos del usuario
POST   /api/v1/files/upload    Subir archivo
GET    /api/v1/files/{id}      Obtener detalles del archivo
DELETE /api/v1/files/{id}      Eliminar archivo
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

**Iniciar sesion:**
```bash
curl -X POST http://localhost:8080/api/v1/auth/login \
  -H "Content-Type: application/json" \
  -d '{"email":"juan@ejemplo.com","password":"ContrasenaSegura123!"}'
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
# Generar/actualizar swagger.json
php spark swagger:generate

# Levantar Swagger UI con Docker
docker run --rm -p 8081:8080 \
  -e SWAGGER_JSON=/swagger.json \
  -v "$(pwd)/public/swagger.json:/swagger.json" \
  swaggerapi/swagger-ui
```
Abrir `http://localhost:8081`.

**Swagger UI embebido (sin Docker):**
- Archivo: `public/docs/index.html`
- Abrir `http://localhost:8080/docs/`

**Postman:**
- Coleccion: `docs/postman/ci4-auth-flow.postman_collection.json`
- Entorno: `docs/postman/ci4-auth-flow.postman_environment.json`

## Estructura del Proyecto

```
app/
├── Controllers/
│   ├── ApiController.php          # Controlador base
│   └── Api/V1/                    # Controladores API v1
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
├── Unit/                         # 88 tests - Sin base de datos
│   ├── Libraries/                # Tests de ApiResponse
│   └── Services/                 # Tests unitarios de servicios
├── Integration/                  # 19 tests - Requiere base de datos
│   ├── Models/                   # Tests de modelos
│   └── Services/                 # Tests de integracion de servicios
└── Feature/                      # 10 tests - Tests HTTP completos
    └── Controllers/              # Tests de endpoints
```

## Testing

```bash
# Ejecutar todos los tests (117)
vendor/bin/phpunit

# Ejecutar con salida legible
vendor/bin/phpunit --testdox

# Ejecutar suites especificas
vendor/bin/phpunit tests/Unit           # Rapidos, sin BD (88 tests)
vendor/bin/phpunit tests/Integration    # Necesita BD (19 tests)
vendor/bin/phpunit tests/Feature        # Tests HTTP (10 tests)
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
STORAGE_DRIVER=local
FILE_MAX_SIZE=10485760

# Limite de peticiones
THROTTLE_LIMIT=60
THROTTLE_WINDOW=60
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

## Requisitos

- PHP 8.2+
- MySQL 8.0+
- Composer 2.x
- Extensiones: mysqli, mbstring, intl, json

## Documentacion

- **CLAUDE.md** - Guia de desarrollo para asistentes de IA
- **swagger.json** - Documentacion OpenAPI (generar con `php spark swagger:generate`)

## Licencia

Licencia MIT

## Contribuir

1. Haz fork del repositorio
2. Crea una rama de feature (`git checkout -b feature/mejora`)
3. Haz commit de los cambios (`git commit -m 'Agregar mejora'`)
4. Push a la rama (`git push origin feature/mejora`)
5. Abre un Pull Request
