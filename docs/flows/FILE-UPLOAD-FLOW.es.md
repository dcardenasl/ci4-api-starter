# Flujo de subida de archivos

## Explicacion
1. Ruta protegida. La subida entra por `POST /api/v1/files/upload`, dentro del grupo protegido con `jwtauth` en `app/Config/Routes.php`. El filtro JWT agrega `userId` al request.
2. Controller. `FileController::upload()` toma el archivo desde `multipart/form-data` con `getFile('file')` y delega a `handleRequest('upload', ...)`.
3. Recoleccion y saneo. `ApiController::handleRequest()` llama a `collectRequestData()`, que combina query/post/raw/json y agrega `user_id` desde el JWT, luego sanea strings.
4. Validaciones principales (en servicio). `FileService::upload()` valida campos, size y extension permitida.
5. Persistencia en storage. Se genera nombre unico y ruta `Y/m/d/archivo`, se lee el temporal y se guarda via `StorageManager`.
6. Persistencia en DB. Se inserta en la tabla `files` (metadata, url, path, driver). Si falla, hace rollback y borra el archivo del storage.
7. Respuesta. Devuelve `201` con metadata (`id`, `original_name`, `size`, `mime_type`, `url`, `uploaded_at`).

## Validaciones del servicio
- `file` y `user_id` obligatorios.
- Objeto valido (`isValid()`).
- Tamano maximo por `FILE_MAX_SIZE` (default 10MB).
- Extension permitida por `FILE_ALLOWED_TYPES` (default `jpg,jpeg,png,gif,pdf`).

Nota: existen reglas CI4 en `app/Validations/FileValidation.php` (uploaded/max_size), usadas por el servicio de validacion si se invoca `validateOrFail`, pero `FileService` no lo usa actualmente.

## Storage y drivers
- Driver segun `FILE_STORAGE_DRIVER` (`local` o `s3`).
- Local guarda en `FILE_UPLOAD_PATH` (default `writable/uploads/`).

## Diagrama (secuencia)
```text
Cliente
  |
  | POST /api/v1/files/upload (multipart/form-data, file, Authorization: Bearer <token>)
  v
Routes (jwtauth) -> agrega userId al request
  |
  v
FileController::upload()
  |
  v
ApiController::handleRequest('upload')
  |
  v
collectRequestData() + sanitizeInput() + user_id
  |
  v
FileService::upload()
  |  valida: file, user_id, size, extension
  |  genera nombre y path Y/m/d/...
  |  storage->put(path, contents)
  |  inserta metadata en DB
  v
ApiResponse::created (201)
```

## Ejemplo curl
```bash
curl -X POST http://localhost:8080/api/v1/files/upload \
  -H "Authorization: Bearer TU_TOKEN_DE_ACCESO" \
  -F "file=@/ruta/al/archivo.pdf"
```
