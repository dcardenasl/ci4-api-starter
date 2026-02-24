# Guía de Implementación de Funcionalidades (Playbook Reusable)

## Propósito

Esta guía estandariza cómo diseñar e implementar nuevas funcionalidades en este API CI4. Se enfoca en consistencia arquitectónica, seguridad, documentación, pruebas y mantenibilidad.

## Alcance

- Nuevas entidades y módulos (CRUD, reportes, integraciones).
- Cambios a endpoints existentes.
- Mejoras de rendimiento y seguridad.

---

## Principios

1. Separación clara de capas: `Controller → Service → Model → Entity`.
1. Validación en 2 niveles: input (dominio) + modelo (integridad).
1. Seguridad por defecto: JWT + roles + ownership cuando aplique.
1. Respuestas consistentes con `ApiResponse`.
1. i18n: mensajes en `en` y `es` con paridad estricta de claves y reglas por capa.

---

## Estructura de Archivos (referencia)

- `app/Controllers/Api/V1/` controladores API.
- `app/Services/` lógica de negocio.
- `app/Models/` acceso a datos.
- `app/Entities/` transformación y ocultación de campos.
- `app/Validations/` reglas de input por dominio.
- `app/Documentation/` anotaciones OpenAPI.
- `app/Filters/` middleware.
- `tests/Unit/`, `tests/Integration/`, `tests/Feature/`.

---

## Checklist General (flujo end-to-end)

**1. Análisis y Diseño**
1. Definir entidades y relaciones.
2. Identificar reglas de negocio y estados.
3. Definir endpoints públicos y protegidos.
4. Definir permisos por rol y ownership.

**2. Esquema y Migraciones**
1. Crear migraciones con índices y constraints.
2. Definir soft delete si aplica.
3. Definir índices de búsqueda si aplica.

**3. Modelos y Entidades**
1. Modelos con `allowedFields`, `validationRules`.
1. `searchableFields`, `filterableFields`, `sortableFields`.
1. Entidades con `toArray()` que oculten datos sensibles.

**4. Validaciones**
1. Crear `XValidation` con reglas por acción (`store`, `update`).
1. Registrar en `InputValidationService`.
1. Agregar mensajes a `app/Language/en` y `app/Language/es`.
1. En Models, los mensajes de validación deben usar solo `InputValidation.*`.

**5. Servicios**
1. Implementar CRUD con reglas de negocio.
1. Usar `QueryBuilder` para búsqueda/filtros/paginación.
1. Manejar ACL (autor/admin).
1. Disparar excepciones con mensajes claros.

**6. Controladores**
1. Extender `ApiController`.
1. Mapear métodos a servicios.
1. Usar `getUserId()` y `getUserRole()`.

**7. Rutas**
1. Rutas públicas vs protegidas (`jwtauth`).
1. `roleauth` para admin.
1. Usar `/api/v1`.

**8. Documentación OpenAPI**
1. Añadir endpoints y schemas en `app/Documentation/`.
1. Mantener request/response consistentes.

**9. Tests**

1. Unit tests de reglas de negocio.
1. Feature tests para endpoints críticos.
1. Integration tests si hay filtros/búsqueda/joins.

**10. Documentación del Repo**

1. Actualizar `README.md` y `README.es.md`.
1. Documentar filtros/params soportados.
1. Ejecutar `composer i18n-check` (obligatorio).

---

## Ejemplo Mínimo (estructura de archivos)

```
app/
  Controllers/Api/V1/PostController.php
  Services/PostService.php
  Models/PostModel.php
  Entities/PostEntity.php
  Validations/PostValidation.php
  Documentation/Blog/PostEndpoints.php
tests/
  Unit/Services/PostServiceTest.php
  Feature/Controllers/PostControllerTest.php
  Integration/Services/PostServiceTest.php
```

---

## Do (sí hacer)

- Usar `ApiResponse` para respuestas.
- Validar input con `validateOrFail`.
- Asegurar autorización en servicios (no solo en rutas).
- Usar `QueryBuilder` y whitelists en filtros/sort.
- Actualizar documentación y pruebas.

## Don’t (no hacer)

- No saltarse `Services` y usar `Model` directo desde el controller.
- No exponer campos sensibles en `toArray()`.
- No dejar endpoints sin protección cuando requieren auth.
- No asumir que el frontend valida: todo input debe validarse.
- No agregar lógica de negocio en el controller.

---

## Plantilla de Endpoint (pseudo)

```
POST /api/v1/resource
Headers: Authorization: Bearer <token>

Controller -> handleRequest('store')
Service -> validateOrFail() -> business rules -> model->insert()
ApiResponse::created()
```

---

## Criterios de Aceptación (base)

1. Endpoints implementados y documentados.
1. ACL aplicada correctamente.
1. Validaciones correctas.
1. Tests cubren casos principales.
1. No rompe contratos existentes.

---

## Supuestos y Defaults

1. Se respeta la arquitectura en capas (Controller → Service → Model → Entity).
1. Respuestas uniformes con `ApiResponse`.
1. Soft delete cuando aplique.
1. i18n en `en` y `es`.
