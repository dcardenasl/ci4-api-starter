# Plan de Desarrollo: Módulo Blog (Posts, Categorías, Tags)

## Resumen
Se implementará un módulo de blog completo con posts, categories, tags y relación post_tags, respetando el flujo actual (Routes → Filters → Controllers → Services → Models → Entities → ApiResponse). Lectura
pública, escritura con JWT; edición/borrado sólo autor o admin. Se agregará documentación OpenAPI, validaciones y pruebas.

---

## Cambios/Extensiones de API (públicas)

1. GET /api/v1/posts (público) lista sólo published, con filtros/búsqueda/orden/paginación.
2. GET /api/v1/posts/{id} (público) muestra sólo published.
3. GET /api/v1/posts/slug/{slug} (público) para consumo web.
4. GET /api/v1/categories y GET /api/v1/categories/{id} (público).
5. GET /api/v1/tags y GET /api/v1/tags/{id} (público).

---

## Cambios/Extensiones de API (protegidas)

1. POST /api/v1/posts (JWT) crear post.
2. PUT /api/v1/posts/{id} (JWT) editar post (autor o admin).
3. DELETE /api/v1/posts/{id} (JWT) borrar post (autor o admin, soft delete).
4. POST /api/v1/categories, PUT /api/v1/categories/{id}, DELETE /api/v1/categories/{id} (JWT + roleauth:admin).
5. POST /api/v1/tags, PUT /api/v1/tags/{id}, DELETE /api/v1/tags/{id} (JWT + roleauth:admin).

---

## Tareas Paso a Paso

1. Diseño de Esquema y Reglas de Negocio
   1. Definir campos de posts con SEO: title, slug, excerpt, content, meta_title, meta_description, cover_image, status (draft|published|archived), published_at, author_id, category_id, timestamps y soft
      delete.
   2. Definir categories: name, slug, description, timestamps, soft delete.
   3. Definir tags: name, slug, timestamps, soft delete.
   4. Definir post_tags (pivot): post_id, tag_id, único compuesto.
2. Migraciones
   1. Crear migración para posts con índices en slug, status, author_id, category_id, published_at.
   2. Crear migraciones para categories, tags y post_tags con FKs y índices.
   3. Agregar índice FULLTEXT para posts.title, posts.excerpt, posts.content si SEARCH_ENABLED está activo y el driver lo soporta; fallback a índices normales si no.
3. Modelos y Entidades
   1. Crear app/Models/PostModel.php, CategoryModel.php, TagModel.php, PostTagModel.php con:
      1. useSoftDeletes, useTimestamps.
      2. allowedFields, validationRules y searchableFields, filterableFields, sortableFields.
   2. Crear entidades PostEntity, CategoryEntity, TagEntity con toArray() para ocultar campos internos y exponer relaciones.
4. Validaciones de Input
   1. Agregar validaciones: PostValidation, CategoryValidation, TagValidation en app/Validations/.
   2. Registrar dominios en InputValidationService para post, category, tag.
   3. Añadir strings en app/Language/en y app/Language/es para mensajes y errores.
5. Servicios
   1. Crear PostService:
      1. index() usando QueryBuilder + filtros por status, category_id, author_id y search.
      2. Filtro por tag: tag (slug) o tag_id usando join a post_tags.
      3. show() por id y showBySlug() por slug.
      4. store() con validateOrFail, slug único, status, published_at si published.
      5. update() con ACL (autor o admin), regeneración de slug opcional si cambia title.
      6. destroy() soft delete con ACL.
      7. Manejo de tags: sincronizar pivote (insert/update/delete).
   2. Crear CategoryService y TagService con CRUD admin y listados públicos.
   3. Agregar servicios en app/Config/Services.php.
6. Controladores
   1. Crear app/Controllers/Api/V1/PostController.php extendiendo ApiController con métodos index, show, showBySlug, create, update, delete.
   2. Crear CategoryController y TagController.
   3. Añadir manejo de usuario autenticado usando getUserId() y getUserRole() para ACL.
7. Rutas y Filtros
   1. Añadir rutas públicas para lectura (sin jwtauth).
   2. Añadir rutas protegidas para escritura bajo jwtauth, y roleauth:admin para categorías/tags.
   3. Asegurar coherencia con ThrottleFilter.
8. Documentación OpenAPI
   1. Crear anotaciones en app/Documentation/Blog/ para endpoints de posts, categorías y tags.
   2. Definir schemas Post, Category, Tag, PostListResponse, PostCreateRequest, PostUpdateRequest.
9. Pruebas
   1. Unit tests para servicios:
      1. Slug único y generación automática.
      2. ACL (autor vs no autor).
      3. Estados (draft, published, archived) y published_at.
      4. Sincronización de tags (insert/update/delete).
   2. Feature tests:
      1. Lectura pública sólo de published.
      2. Crear/editar/borrar con JWT.
      3. Admin-only para categorías/tags.
   3. Integration tests con DB:
      1. Filtros por categoría y tag.
      2. Búsqueda con search.
10. Actualizaciones de Documentación
11. Añadir endpoints a README.md y README.es.md.
12. Mencionar nuevos filtros soportados para posts.

---

## Criterios de Aceptación

1. CRUD completo de posts con tags y categoría.
2. Lectura pública sólo de posts published.
3. Edición/borrado restringidos a autor o admin.
4. Categorías y tags administrables sólo por admin.
5. Documentación OpenAPI actualizada y generable.
6. Pruebas unitarias y feature cubren casos principales.

---

## Supuestos y Defaults

1. slug se genera desde title y es único con sufijo incremental si hay colisión.
2. published_at se setea automáticamente cuando status=published.
3. Public read no muestra draft ni archived.
4. Usuarios autenticados no admin solo ven sus drafts cuando consultan GET /posts con JWT; los públicos siempre ven published.
5. Tags se filtran por tag (slug) o tag_id como query param.
