# Plan Genérico para Nuevas Funcionalidades (Plantilla Reusable)

## Resumen
Plan estándar para analizar, diseñar e implementar nuevas funcionalidades en este proyecto CI4 API. Cubre arquitectura en capas, rutas, validaciones, seguridad, documentación y pruebas. Debe adaptarse al dominio
específico.

---

## Cambios/Extensiones de API (públicas)

1. Definir endpoints públicos de lectura.
2. Definir filtros, búsqueda, orden y paginación soportados.
3. Determinar qué estados o condiciones son visibles públicamente.

---

## Cambios/Extensiones de API (protegidas)

1. Definir endpoints de escritura (crear/editar/borrar).
2. Establecer reglas de autorización (JWT, rol, ownership).
3. Determinar límites de rate limiting y filtros aplicables.

---

## Tareas Paso a Paso

1. Análisis del Dominio
   1. Definir entidades principales y relaciones.
   2. Identificar reglas de negocio y estados.
   3. Determinar casos de uso clave (lectura, escritura, administración).
2. Diseño de Esquema y Reglas de Negocio
   1. Definir campos de cada entidad.
   2. Definir índices, constraints y soft delete si aplica.
   3. Establecer valores por defecto y reglas de transición de estados.
3. Migraciones
   1. Crear migraciones de tablas necesarias.
   2. Agregar índices y constraints.
   3. Considerar compatibilidad con búsqueda (FULLTEXT si aplica).
4. Modelos y Entidades
   1. Crear modelos con allowedFields, validationRules, useSoftDeletes, useTimestamps.
   2. Configurar searchableFields, filterableFields, sortableFields.
   3. Crear entidades con toArray() y ocultar campos sensibles.
5. Validaciones de Input
   1. Crear validaciones por dominio (acciones: store/update).
   2. Registrar en InputValidationService.
   3. Agregar mensajes en Language/en y Language/es.
6. Servicios
   1. Implementar servicios con lógica de negocio.
   2. Usar QueryBuilder para paginación, filtros, búsqueda.
   3. Implementar ACL (owner/admin) en operaciones críticas.
   4. Manejar errores con excepciones de dominio.
7. Controladores
   1. Crear controladores extendiendo ApiController.
   2. Mapear métodos CRUD a servicios.
   3. Usar helpers de auth (getUserId, getUserRole).
8. Rutas y Filtros
   1. Añadir rutas públicas vs protegidas.
   2. Aplicar jwtauth, roleauth, throttle según necesidad.
   3. Mantener versión en /api/v1.
9. Documentación OpenAPI
   1. Crear anotaciones de endpoints y schemas.
   2. Definir request/response comunes.
   3. Actualizar swagger.json.
10. Pruebas
11. Unit tests para servicios (reglas de negocio y edge cases).
12. Feature tests para endpoints críticos.
13. Integration tests con DB en casos de filtros/búsqueda.
14. Documentación de Proyecto
15. Actualizar README.md y README.es.md con endpoints y ejemplos.
16. Documentar parámetros de búsqueda/filtros/orden.

---

## Criterios de Aceptación

1. Funcionalidad completa end-to-end.
2. Validaciones y reglas de negocio cubiertas.
3. Autorización correcta por rol/ownership.
4. Documentación actualizada.
5. Pruebas esenciales implementadas.

---

## Supuestos y Defaults

1. Se respeta la arquitectura en capas (Controller → Service → Model → Entity).
2. Respuestas uniformes con ApiResponse.
3. Soft delete cuando aplique.
4. i18n en en y es.
