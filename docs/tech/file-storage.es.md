# Almacenamiento de archivos

Las cargas de archivos usan una abstraccion de storage con drivers local y S3.

Archivos clave:
- `app/Services/FileService.php`
- `app/Libraries/Storage/StorageManager.php`
- `app/Libraries/Storage/Drivers/LocalDriver.php`
- `app/Libraries/Storage/Drivers/S3Driver.php`

Variables de entorno:
- `FILE_STORAGE_DRIVER` (local, s3)
- `FILE_MAX_SIZE`
- `FILE_ALLOWED_TYPES`
- `FILE_UPLOAD_PATH`
- `AWS_ACCESS_KEY_ID`, `AWS_SECRET_ACCESS_KEY`, `AWS_DEFAULT_REGION`, `AWS_BUCKET`, `AWS_URL`

Notas:
- El storage local usa `writable/uploads/`.
- S3 usa Flysystem y los valores AWS en `.env`.
