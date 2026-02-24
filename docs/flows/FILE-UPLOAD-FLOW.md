# File Upload Flow

## Explanation
1. Protected route. The upload enters via `POST /api/v1/files/upload`, inside the `jwtauth` protected group in `app/Config/Routes.php`. The JWT filter adds `userId` to the request.
2. Controller. `FileController::upload()` reads the file from `multipart/form-data` using `getFile('file')` and delegates to `handleRequest('upload', ...)`.
3. Collection and sanitization. `ApiController::handleRequest()` calls `collectRequestData()`, which merges query/post/raw/json and adds `user_id` from JWT, then sanitizes strings.
4. Main validations (service). `FileService::upload()` validates required fields, size, and allowed extension.
5. Storage persistence. It generates a unique name and a `Y/m/d/filename` path, reads the temp file, and stores it via `StorageManager`.
6. DB persistence. It inserts metadata into the `files` table (metadata, url, path, driver). If it fails, it rolls back and deletes the file from storage.
7. Response. Returns `201` with metadata (`id`, `original_name`, `size`, `mime_type`, `url`, `uploaded_at`).

## Service validations
- `file` and `user_id` are required.
- Valid object (`isValid()`).
- Max size via `FILE_MAX_SIZE` (default 10MB).
- Allowed extension via `FILE_ALLOWED_TYPES` (default `jpg,jpeg,png,gif,pdf`).

Note: Request-shape validation is centralized through `app/Validations/FileValidation.php` using `getValidationRules('file', <action>) + validateInputs(...)` inside `FileService`. Runtime checks (`isValid()`, max size, allowed extension list) are still enforced in the service.

## Storage and drivers
- Driver via `FILE_STORAGE_DRIVER` (`local` or `s3`).
- Local stores in `FILE_UPLOAD_PATH` (default `writable/uploads/`).

## Diagram (sequence)
```text
Client
  |
  | POST /api/v1/files/upload (multipart/form-data, file, Authorization: Bearer <token>)
  v
Routes (jwtauth) -> adds userId to request
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
  |  validate: file, user_id, size, extension
  |  generate name and path Y/m/d/...
  |  storage->put(path, contents)
  |  insert metadata in DB
  v
ApiResponse::created (201)
```

## curl example
```bash
curl -X POST http://localhost:8080/api/v1/files/upload \
  -H "Authorization: Bearer YOUR_ACCESS_TOKEN" \
  -F "file=@/path/to/file.pdf"
```
