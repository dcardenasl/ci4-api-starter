# OpenAPI and Swagger

OpenAPI documentation is generated from annotations and written to `public/swagger.json`.

Key files:
- `app/Config/OpenApi.php`
- `app/Documentation/`
- `public/swagger.json`

Generate docs:
- `php spark swagger:generate`

Notes:
- Swagger UI can be served using the Docker example in `README.md`.
- Postman collection is derived from `public/swagger.json`.
  Import the file in Postman and export the collection to `docs/postman/ci4-api.postman_collection.json`.
  Variables live at the collection level; an optional environment file lives at
  `docs/postman/ci4-api.postman_environment.json`.
