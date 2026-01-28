# CI4 API Base Starter

Proyecto base para crear APIs REST con CodeIgniter 4, implementando arquitectura por capas, autenticación JWT, y buenas prácticas.

## 🚀 Características

- ✅ **Arquitectura por Capas**: Controller → Service → Repository
- ✅ **API REST**: Endpoints siguiendo estándares RESTful
- ✅ **Respuesta Estándar**: Formato JSON consistente `{ success, data, error }`
- ✅ **Capa de Servicios**: Lógica de negocio separada
- ✅ **Repositorios**: Abstracción del acceso a datos
- ✅ **Ready para JWT**: Dependencias instaladas
- ✅ **Swagger/OpenAPI**: Listo para documentación
- ✅ **PHPUnit**: Testing framework configurado
- ✅ **Docker Ready**: Configuración para contenerización

## 📋 Requisitos

- PHP 8.1 o superior
- Composer
- MySQL (opcional, para producción)
- Git

## 🛠️ Instalación

### 1. Clonar el proyecto
```bash
git clone <repository-url>
cd ci4-api-starter
```

### 2. Instalar dependencias
```bash
composer install
```

### 3. Configurar entorno
```bash
cp env .env
# Editar .env con tu configuración
```

### 4. Configurar base de datos (opcional)
```bash
# Editar .env
database.default.hostname = localhost
database.default.database = your_database
database.default.username = your_username
database.default.password = your_password
database.default.DBDriver = MySQLi

# Ejecutar migraciones
php spark migrate
```

### 5. Iniciar servidor de desarrollo
```bash
php spark serve --host 0.0.0.0 --port 8080
```

## 📁 Estructura del Proyecto

```
app/
├── Controllers/          # Controladores API
│   ├── BaseController.php
│   ├── Home.php
│   └── UserController.php
├── Services/            # Lógica de negocio
│   └── UserService.php
├── Repositories/         # Acceso a datos
│   └── UserRepository.php
├── Entities/            # Entidades de datos
├── Config/             # Configuración
├── Database/
│   └── Migrations/     # Migraciones de BD
├── Filters/            # Filtros (JWT, CORS, etc.)
└── Helpers/           # Helpers personalizados
```

## 🔗 Endpoints Disponibles

### Users API
| Método | Endpoint | Descripción |
|--------|----------|-------------|
| GET | `/api/v1/users` | Listar todos los usuarios |
| GET | `/api/v1/users/{id}` | Obtener usuario específico |
| POST | `/api/v1/users` | Crear nuevo usuario |
| PUT | `/api/v1/users/{id}` | Actualizar usuario |
| DELETE | `/api/v1/users/{id}` | Eliminar usuario |

### Formato de Respuesta

**Éxito:**
```json
{
    "success": true,
    "data": { ... },
    "error": null
}
```

**Error:**
```json
{
    "success": false,
    "data": null,
    "error": "Mensaje de error"
}
```

## 🧪 Testing

```bash
# Ejecutar todos los tests
php spark test

# Ejecutar tests específicos
php spark test --filter UserServiceTest
```

## 📚 Comandos Útiles

```bash
# Crear controlador
php spark make:controller Api/MyController

# Crear modelo
php spark make:model MyModel

# Crear migración
php spark make:migration create_my_table

# Ejecutar migraciones
php spark migrate

# Generar clave de encriptación
php spark key:generate

# Limpiar caché
php spark cache:clear
```

## 🔐 JWT (Preparado)

El proyecto incluye las dependencias para JWT:

```bash
# Instalado automáticamente
firebase/php-jwt
```

 próximamente en la **Fase 2** del workflow se implementará:
- `JwtService` para generar/validar tokens
- `JwtAuthFilter` para proteger rutas
- `AuthController` para login

## 📖 Swagger (Preparado)

Dependencia para documentación API:

```bash
# Instalado automáticamente  
zircote/swagger-php
```

 próximamente en la **Fase 4** se configurará la generación automática de `swagger.json`.

## 🐳 Docker (Preparado)

Las fases posteriores agregarán:
- `Dockerfile` para la aplicación
- `docker-compose.yml` con app + MySQL
- Configuración de volúmenes

## 🔄 Workflow de Desarrollo

Este proyecto sigue un workflow estructurado por fases:

1. ✅ **Fase 0** - Inicialización del Proyecto
2. ✅ **Fase 1** - Arquitectura por Capas
3. ⏳ **Fase 2** - Autenticación JWT + Roles
4. ⏳ **Fase 3** - Helpers + Respuesta Estándar
5. ⏳ **Fase 4** - Swagger / OpenAPI
6. ⏳ **Fase 5** - Docker + MySQL
7. ⏳ **Fase 6** - Testing
8. ⏳ **Fase 7** - CI con GitHub Actions
9. ⏳ **Fase 8** - Seguridad, CORS, Rate Limiting
10. ⏳ **Fase 9** - Plantilla Reutilizable

Consultar el archivo `plan/v1.0.0/pdr.workflow.md` para detalles completos.

## 🤝 Contribuir

1. Fork del proyecto
2. Crear feature branch: `git checkout -b feature/new-feature`
3. Commits descriptivos
4. Push al branch: `git push origin feature/new-feature`
5. Pull Request

## 📄 Licencia

Este proyecto es software libre. Puedes usarlo bajo los términos de la licencia MIT.

## 🆘 Soporte

- 📖 [CodeIgniter 4 User Guide](https://codeigniter.com/user_guide/)
- 📋 [Issues y Feature Requests](https://github.com/tu-repo/issues)
- 💬 [Discusiones](https://github.com/tu-repo/discussions)

---

**Nota**: Este es un starter kit. Cada fase del workflow agrega componentes específicos para crear una API completa, segura y mantenible.