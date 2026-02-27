# Referencia Rápida para Agentes - CI4 API Starter (Arquitectura Orientada a Dominios)

**Propósito**: Patrones y restricciones esenciales para que los agentes de IA mantengan la consistencia arquitectónica.

---

## 1. Organización Orientada a Dominios

Cada nuevo componente **debe** residir en un subdirectorio de dominio:
- `app/Services/{Domain}/`
- `app/Interfaces/{Domain}/`
- `app/DTO/Request/{Domain}/`
- `app/DTO/Response/{Domain}/`

---

## 2. Flujo de Solicitud (Inmutable y Descompuesto)

```
Solicitud HTTP → Controlador → [RequestDTO] → Servicio de Dominio (Guards/Handlers) → Modelo → Entidad → [ResponseDTO] → ApiResult → JSON
```

### Innovaciones Clave:
- **`BaseRequestDTO`**: Enriquece automáticamente `user_id` y `role` desde el `ContextHolder`.
- **`ApiResult`**: Estandarización de `body` y `status` entre capas.
- **`ExceptionFormatter`**: Gestión de errores centralizada y consciente del entorno.

---

## 3. Checklist de Implementación

### Paso 0: Scaffold Primero
```bash
php spark make:crud {Nombre} --domain {Dominio} --route {endpoint}
```

### Paso 1: DTOs Inmutables
- Extender de `BaseRequestDTO`.
- Usar **`readonly class`** para todos los DTOs y Servicios.
- Los DTOs de respuesta deben incluir atributos OpenAPI `#[OA\Property]`.

### Paso 2: Servicios Compuestos
- Heredar de `BaseCrudService` para CRUD estándar.
- Descomponer la lógica en componentes `Support/` (Handlers, Mappers, Guards).
- Usar **inyección por constructor** para todas las dependencias (Sin llamadas estáticas).
- Registrar en `app/Config/Services.php`.

### Paso 3: Controlador Declarativo
- Extender de `ApiController`.
- Usar `handleRequest()` para mapeo automático y propagación de contexto.

---

## 4. Referencia de Excepciones (HasStatusCode)

Las excepciones deben implementar `HasStatusCode`:
- `NotFoundException` (404)
- `AuthenticationException` (401)
- `AuthorizationException` (403)
- `ValidationException` (422)
- `BadRequestException` (400)

---

## 5. Seguridad y Estilo

- ✅ **Inmutabilidad:** PHP 8.2 `readonly class` obligatorio.
- ✅ **Atómico:** Usar `HandlesTransactions` para cambios de estado.
- ✅ **Contexto:** Acceder a la identidad vía `SecurityContext` inyectado en los métodos del servicio.
- ✅ **i18n:** Usar el helper `lang()`. Proveer archivos `en` y `es`.

---

## Comandos Rápidos

```bash
php spark make:crud {Nombre} --domain {Dominio} --route {endpoint}
php spark swagger:generate
composer quality
vendor/bin/phpunit
```
