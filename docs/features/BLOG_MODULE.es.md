# Módulo Blog

Plan funcional para implementar módulo de blog (posts, categorías, tags) siguiendo arquitectura DTO-first.

## Alcance

1. CRUD de posts.
2. CRUD de categorías.
3. Asociación de tags.
4. Publicación/borrador y filtros de consulta.

## Reglas del template

1. Controllers delgados con `handleRequest(...)`.
2. Services puros (sin `ApiResponse`).
3. DTOs obligatorios para Request/Response.
4. `index()` paginado vía contrato base (`PaginatedResponseDTO`).
5. Tests Unit/Feature/Integration por módulo.

## Estado

Planificado (referencia funcional).

## Referencia

Detalle original en inglés: `BLOG_MODULE.md`.
