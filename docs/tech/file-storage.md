# File Storage

File uploads use a storage abstraction with local and S3 drivers.

Key files:
- `app/Services/FileService.php`
- `app/Libraries/Storage/StorageManager.php`
- `app/Libraries/Storage/Drivers/LocalDriver.php`
- `app/Libraries/Storage/Drivers/S3Driver.php`

Environment variables:
- `FILE_STORAGE_DRIVER` (local, s3)
- `FILE_MAX_SIZE`
- `FILE_ALLOWED_TYPES`
- `FILE_UPLOAD_PATH`
- `AWS_ACCESS_KEY_ID`, `AWS_SECRET_ACCESS_KEY`, `AWS_DEFAULT_REGION`, `AWS_BUCKET`, `AWS_URL`

Notes:
- Local storage defaults to `writable/uploads/`.
- S3 uses Flysystem and the AWS config values from `.env`.
