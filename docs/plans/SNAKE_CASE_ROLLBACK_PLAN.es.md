He concluido el análisis técnico de ambos proyectos. La situación es clara: la migración previa a camelCase se implementó de forma profunda en todas las capas (DTOs, validaciones, vistas, controladores y lógica
de frontend). Para revertir esto a snake_case de manera profesional y robusta, he diseñado un plan de ejecución por fases.

Puntos Críticos Identificados

1.  API (`ci4-api-starter`):
    - Request DTOs: Las propiedades, el mapeo en map() y las claves en rules() están en camelCase.
    - Response DTOs: El método toArray() fuerza la salida a camelCase.
    - Contexto de Seguridad: ApiController inyecta userId y userRole en los DTOs.
    - Metadatos de Respuesta: La paginación en ApiResponse usa perPage, lastPage, etc.

2.  Admin (`ci4-admin-starter`):
    - Form Requests: Los campos y reglas de validación coinciden con los inputs camelCase.
    - Vistas: Los atributos name e id de los inputs HTML son camelCase.
    - Normalización de Errores: Existe un mapa manual en BaseWebController::normalizeErrorKey que traduce de snake_case a camelCase.
    - Frontend (JS): app.js y los componentes Alpine.js acceden a propiedades como row.firstName o nested.currentPage.
    - Tablas: Las definiciones de columnas en los controladores del Admin usan camelCase.

---

Plan de Implementación Detallado

Fase 1: API - Estandarización del "Source of Truth"

1.  Refactorizar DTOs de Solicitud: Cambiar todas las propiedades y claves de validación a snake_case.
2.  Refactorizar DTOs de Respuesta: Modificar toArray() para que devuelva claves en snake_case.
3.  Ajustar `ApiController` y `SecurityContext`: Cambiar la inyección de contexto a user_id y user_role.
4.  Actualizar `ApiResponse`: Normalizar los metadatos de paginación (p. ej., per_page, last_page, next_cursor).
5.  Pruebas de Integración: Actualizar los Feature Tests para validar que el JSON de salida y los errores de validación sigan el nuevo estándar.

Fase 2: Admin - Capa de Comunicación y Validación

1.  Refactorizar `FormRequest`s: Cambiar fields(), rules() y payload() para que operen totalmente en snake_case.
2.  Simplificar `BaseWebController`: Eliminar o neutralizar normalizeErrorKey, ya que el Admin y la API hablarán el mismo idioma, eliminando la necesidad de traducción.

Fase 3: Admin - Interfaz de Usuario y Lógica de Cliente

1.  Actualizar Vistas: Renombrar todos los inputs en los formularios y ajustar las llamadas a has_field_error y render_field_error.
2.  Refactorizar `app.js`: Actualizar el acceso a propiedades en los objetos de respuesta (p. ej., row.first_name, row.created_at) y la lógica de paginación.
3.  Controladores de Tabla: Actualizar las listas de columnas permitidas y de ordenación en los métodos data().

Fase 4: Validación y Documentación

1.  Regeneración de Swagger: Ejecutar php spark swagger:generate para que la documentación OpenAPI refleje los nuevos contratos.
2.  Smoke Testing: Verificación manual de los flujos críticos (Login, CRUD de Usuarios, Carga de Archivos).

---

Preguntas para Clarificación
Antes de proceder en el futuro, necesito confirmar lo siguiente:

1.  Base de Datos: ¿Los campos de la base de datos ya están en snake_case? (Mi análisis de los DTO de respuesta sugiere que sí, pero es vital confirmarlo).
2.  Casos Especiales: ¿Existen otros consumidores de la API (Apps móviles, otros servicios) que debamos considerar antes de romper la compatibilidad con camelCase?
3.  Preferencias de Transición: ¿Prefieres que hagamos los cambios proyecto por proyecto (primero API, luego Admin) o por dominios funcionales (ej. primero todo lo relacionado a Usuarios en ambos, luego
    Auditoría, etc.)?

Este plan asegura que ambos proyectos no solo funcionen correctamente, sino que sigan las mejores prácticas de la industria, facilitando el mantenimiento y la integración con otras herramientas. 

▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄

Recomendación de Orden de Ejecución

1. API: El Contrato de Seguridad e Infraestructura (Cimientos)

- Seguridad: Cambiar SecurityContext y ApiController para usar user_id y user_role. Es el cambio más "peligroso" porque afecta a todos los controladores.
- Base DTOs: Actualizar BaseRequestDTO y ApiResponse (paginación) para estandarizar per_page, last_page, etc.

2. API: Dominio de Identidad (Auth y Users)

- Refactorizar todos los DTOs de Auth y Users (Store, Update, Response).
- Esto arregla el flujo de Login y el CRUD principal del sistema.

3. Admin: Infraestructura de Comunicación (Traductor)

- Simplificación: Eliminar el mapa de normalización en BaseWebController.
- Frontend Core: Actualizar app.js (Alpine.js) para que consuma snake_case globalmente (paginación y atributos de fila).

4. Admin: Dominio de Identidad (Vistas y Requests)

- Actualizar formularios de Login, Perfil y Gestión de Usuarios.
- Renombrar los name e id de los inputs HTML a snake_case.

5. API & Admin: Dominios Secundarios (Migración en Masa)

- Migrar Audit, Files, ApiKeys y Catalog. Al tener la infraestructura lista, estos cambios serán repetitivos y de bajo riesgo.

6. Calidad y Cierre (El Toque Final)

- Swagger: Regenerar toda la documentación técnica.
- Tests: Ajustar las aserciones de los tests para reflejar el nuevo estándar.
- Eliminación de Legacy: Borrar cualquier helper o método que se haya creado para "parchear" la convivencia entre camelCase y snake_case.
