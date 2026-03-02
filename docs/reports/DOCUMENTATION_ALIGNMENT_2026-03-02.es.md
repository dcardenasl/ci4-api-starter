# Reporte de Alineación de Documentación (2026-03-02)

## Alcance y Ventana de Fecha

- Fecha de análisis: 2026-03-02
- Ventana de commits revisada: 2026-02-27 a 2026-03-02 (últimos 3 días)
- Total de commits revisados: 51
- Total de archivos Markdown revisados en el repositorio: 341 (`find . -name '*.md'`)

## Resumen del Análisis de Commits

### Volumen

- Churn neto de código en la ventana: `+7088 / -4709`
- Categorías de commit con mayor frecuencia:
  - `refactor(core)` (5)
  - `refactor(dto)` (3)
  - `fix(auth)` (3)
  - `fix(arch)` (3)
  - `docs(openapi)` (3)

### Cambios Arquitectónicos Principales

1. Los controladores ahora resuelven servicios explícitamente con `resolveDefaultService()`.
2. `BaseRequestDTO` se desacopló del enriquecimiento directo del contexto de seguridad.
3. La propagación del contexto de seguridad se movió al borde HTTP en `ApiController` (`withSecurityContext(...)`).
4. Los servicios de dominio se movieron/estandarizaron en subdirectorios (ej. `Services/Tokens/*`, `Services/System/*`).
5. La lógica de negocio de escritura se descompuso en clases `Actions/*` en Auth/Users/Tokens.
6. La configuración de colas ahora selecciona automáticamente conexión DB en testing (`tests` por defecto).

## Evaluación de Brechas de Documentación

### Patrones desactualizados encontrados

1. Referencias a patrón legado de controladores (`$serviceName`, `getDTO()`) en docs guía.
2. Afirmación desactualizada de que `BaseRequestDTO` enriquece contexto de identidad por sí solo.
3. Flujos que describían `collectRequestData()` como si inyectara `user_id` directamente.
4. Docs técnicas referenciando rutas antiguas de servicios (ej. `app/Services/EmailService.php`).
5. Docs de skill con ejemplos obsoletos (`app/Services/UserService.php`, nota de normalización snake_case).

### Contenido que intencionalmente no se reescribió

- `docs/reports/*AUDIT*` y snapshots de auditoría fechados se mantienen como artefactos históricos.
- Esos documentos pueden contener rutas antiguas por diseño, ya que describen estados previos.

## Documentación Actualizada en esta Pasada

### Guías núcleo/proyecto

- `AGENTS.md`
- `ARCHITECTURE.md`
- `GEMINI.md`
- `GETTING_STARTED.md`

### Documentación de arquitectura

- `docs/architecture/REQUEST_FLOW.md`
- `docs/architecture/REQUEST_FLOW.es.md`
- `docs/architecture/SERVICES.md`
- `docs/architecture/SERVICES.es.md`
- `docs/architecture/LAYERS.es.md`

### Documentación de flujos

- `docs/flows/FILE-UPLOAD-FLOW.md`
- `docs/flows/REGISTER-APPROVAL-FLOW.md`
- `docs/flows/REGISTER-APPROVAL-FLOW.es.md`
- `docs/flows/EMAIL-VERIFICATION-FLOW.md`
- `docs/flows/EMAIL-VERIFICATION-FLOW.es.md`

### Documentación técnica

- `docs/tech/QUEUE.md`
- `docs/tech/QUEUE.es.md`
- `docs/tech/audit-logging.md`
- `docs/tech/audit-logging.es.md`
- `docs/tech/email.md`
- `docs/tech/email.es.md`
- `docs/tech/refresh-tokens.md`
- `docs/tech/refresh-tokens.es.md`
- `docs/tech/token-revocation.md`
- `docs/tech/token-revocation.es.md`
- `docs/tech/transactions.md`
- `docs/tech/transactions.es.md`

### Documentación de template y skill

- `docs/template/CONTRIBUTION_RULES.md`
- `docs/template/CONTRIBUTION_RULES.es.md`
- `skills/ci4-api-crud-expert/references/crud-playbook.md`
- `skills/ci4-api-crud-expert/references/crud-snippets.md`

## Notas de Verificación

Después de la actualización, los escaneos globales confirman que la documentación viva (excluyendo reportes históricos) ya no referencia:

- rutas obsoletas de servicios como `app/Services/EmailService.php`
- patrón de controlador deprecado `protected string $serviceName`
- patrón helper deprecado `getDTO()`
- frase desactualizada de que `BaseRequestDTO` enriquece automáticamente el contexto de seguridad
- frase de flujo obsoleta "collectRequestData adds user_id from JWT"

## Resultado

- La documentación quedó alineada con la arquitectura de código vigente introducida en los últimos 3 días.
- Archivo de trazabilidad agregado: este reporte (`DOCUMENTATION_ALIGNMENT_2026-03-02.es.md`).
