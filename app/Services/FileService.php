<?php

declare(strict_types=1);

namespace App\Services;

use App\Exceptions\AuthorizationException;
use App\Exceptions\BadRequestException;
use App\Exceptions\NotFoundException;
use App\Exceptions\ValidationException;
use App\Interfaces\AuditServiceInterface;
use App\Interfaces\FileServiceInterface;
use App\Libraries\ApiResponse;
use App\Libraries\Query\QueryBuilder;
use App\Libraries\Storage\StorageManager;
use App\Models\FileModel;
use App\Traits\AppliesQueryOptions;

/**
 * File Service
 *
 * Handles file upload, download, and deletion with storage abstraction
 */
class FileService implements FileServiceInterface
{
    use AppliesQueryOptions;

    public function __construct(
        protected FileModel $fileModel,
        protected StorageManager $storage,
        protected AuditServiceInterface $auditService
    ) {
    }

    /**
     * Upload a file
     *
     * @param array $data Request data with 'file' and 'user_id'
     * @return array
     */
    public function upload(array $data): array
    {
        // $this->validateInputOrBadRequest($data, 'upload');

        $userId = isset($data['user_id']) ? (int) $data['user_id'] : 0;
        if ($userId === 0) {
            throw new \App\Exceptions\AuthenticationException(lang('Auth.invalidToken'));
        }

        // 1. Try to find the file in the standard 'file' key
        $fileInput = $data['file'] ?? null;

        // 2. If not found, look for any UploadedFile object in the data (Multipart)
        if (!$fileInput) {
            foreach ($data as $value) {
                if ($value instanceof \CodeIgniter\HTTP\Files\UploadedFile) {
                    $fileInput = $value;
                    break;
                }
            }
        }

        // 3. If still not found, look for any string that looks like Base64 (JSON)
        // We look for strings starting with 'data:' or very long strings
        if (!$fileInput) {
            foreach ($data as $key => $value) {
                if (is_string($value) && $key !== 'user_id' && $key !== 'user_role') {
                    if (str_starts_with($value, 'data:') || strlen($value) > 10000) {
                        $fileInput = $value;
                        // If it's base64, we might also need other metadata from this key or others
                        break;
                    }
                }
            }
        }

        if (!$fileInput) {
            throw new BadRequestException(
                lang('Files.invalidRequest'),
                ['file' => lang('Files.invalidFileObject')]
            );
        }

        // Case 1: Standard Multipart Upload (CodeIgniter UploadedFile)
        if ($fileInput instanceof \CodeIgniter\HTTP\Files\UploadedFile) {
            return $this->handleMultipartUpload($fileInput, $userId);
        }

        // Case 2: Base64 Upload (JSON)
        if (is_string($fileInput)) {
            return $this->handleBase64Upload($fileInput, $userId, $data);
        }

        throw new BadRequestException(
            lang('Files.invalidRequest'),
            ['file' => lang('Files.invalidFileObject')]
        );
    }

    /**
     * Handle standard multipart file upload
     */
    protected function handleMultipartUpload(\CodeIgniter\HTTP\Files\UploadedFile $file, int $userId): array
    {
        // Check if file is valid
        if (!$file->isValid()) {
            throw new BadRequestException(
                lang('Files.uploadFailed', [$file->getErrorString()]),
                ['file' => lang('Files.uploadFailed', [$file->getErrorString()])]
            );
        }

        // Validate file size
        $maxSize = (int) env('FILE_MAX_SIZE', 20971520); // Default 20MB
        $fileSize = $file->getSize();

        log_message('debug', "FileService DEBUG: Multipart upload. Size: $fileSize bytes, MaxSize: $maxSize bytes");

        if ($fileSize > $maxSize) {
            log_message('warning', "File Upload Rejected: File too large (Multipart). User ID: $userId, Filename: {$file->getName()}, Size: $fileSize bytes, Max: $maxSize");
            throw new ValidationException(
                lang('Files.fileTooLarge'),
                ['file' => lang('Files.fileTooLarge')]
            );
        }

        // Validate file type
        $allowedTypes = explode(',', env('FILE_ALLOWED_TYPES', 'jpg,jpeg,png,gif,pdf'));
        $extension = $file->getExtension();

        if (!in_array(strtolower($extension), $allowedTypes, true)) {
            log_message('warning', "File Upload Rejected: Invalid file type (Multipart). User ID: $userId, Filename: {$file->getName()}, Extension: $extension");
            throw new ValidationException(
                lang('Files.invalidFileType'),
                ['file' => lang('Files.invalidFileType')]
            );
        }

        // Generate unique filename with numeric series support
        $datePath = date('Y/m/d');
        $storedName = $this->generateUniqueFilename($file->getName(), $extension, $datePath);
        $path = $datePath . '/' . $storedName;

        // Use stream to avoid memory exhaustion when handling large files
        $stream = fopen($file->getTempName(), 'rb');

        try {
            return $this->storeAndSaveMetadata([
                'userId' => $userId,
                'originalName' => $file->getName(),
                'storedName' => $storedName,
                'path' => $path,
                'contents' => $stream, // Pass the resource directly
                'mimeType' => $file->getMimeType(),
                'size' => $file->getSize(),
                'extension' => $extension,
            ]);
        } finally {
            if (is_resource($stream)) {
                fclose($stream);
            }
        }
    }

    /**
     * Handle base64 encoded file upload
     */
    protected function handleBase64Upload(string $base64String, int $userId, array $allData): array
    {
        // Check if we received a PHP resource string instead of real data
        if (str_contains($base64String, 'Resource id #')) {
            throw new BadRequestException(
                lang('Files.invalidFileObject'),
                ['file' => 'Received a PHP resource identifier instead of file content. Make sure to send base64_encode(file_get_contents($path)) or a real Multipart file.']
            );
        }

        // Detect Data URI format: data:image/png;base64,iVBORw...
        if (preg_match('/^data:(\w+\/[-+.\w]+);base64,(.+)$/', $base64String, $matches)) {
            $mimeType = $matches[1];
            $base64Data = $matches[2];
        } else {
            // Assume raw base64 if no prefix
            $base64Data = $base64String;
            $mimeType = $allData['mime_type'] ?? 'application/octet-stream';
        }

        $contents = base64_decode($base64Data, true);
        if ($contents === false) {
            throw new BadRequestException(lang('Files.invalidFileObject'), ['file' => 'Invalid base64 encoding']);
        }

        // Detect real MIME type from binary contents if it's generic
        if ($mimeType === 'application/octet-stream') {
            $finfo = new \finfo(FILEINFO_MIME_TYPE);
            $mimeType = $finfo->buffer($contents);
        }

        $size = strlen($contents);
        $maxSize = (int) env('FILE_MAX_SIZE', 20971520); // Default 20MB

        log_message('debug', "FileService DEBUG: Base64 upload. Decoded size: $size bytes, MaxSize: $maxSize bytes");

        if ($size > $maxSize) {
            log_message('error', "FileService: File too large (Base64). Size: $size, Max: $maxSize");
            throw new ValidationException(lang('Files.fileTooLarge'), ['file' => lang('Files.fileTooLarge')]);
        }

        // Determine extension from mime type using CI4 Mimes config
        $extension = \Config\Mimes::guessExtensionFromType($mimeType) ?? 'bin';
        $allowedTypes = explode(',', env('FILE_ALLOWED_TYPES', 'jpg,jpeg,png,gif,pdf'));

        if (!in_array(strtolower($extension), $allowedTypes, true)) {
            throw new ValidationException(lang('Files.invalidFileType'), ['file' => lang('Files.invalidFileType')]);
        }

        $originalName = $allData['filename'] ?? ('image.' . $extension);
        $datePath = date('Y/m/d');
        $storedName = $this->generateUniqueFilename($originalName, $extension, $datePath);
        $path = $datePath . '/' . $storedName;

        // Use a temporary stream to avoid passing large strings to storage
        $stream = fopen('php://temp', 'r+b');
        fwrite($stream, $contents);
        rewind($stream);

        try {
            return $this->storeAndSaveMetadata([
                'userId' => $userId,
                'originalName' => $originalName,
                'storedName' => $storedName,
                'path' => $path,
                'contents' => $stream, // Pass the resource directly
                'mimeType' => $mimeType,
                'size' => $size,
                'extension' => $extension,
            ]);
        } finally {
            if (is_resource($stream)) {
                fclose($stream);
            }
        }
    }

    /**
     * Common logic to store file and save to database
     */
    protected function storeAndSaveMetadata(array $fileInfo): array
    {
        $stored = $this->storage->put($fileInfo['path'], $fileInfo['contents']);

        if (!$stored) {
            throw new \RuntimeException(lang('Files.storageError'));
        }

        $fileData = [
            'user_id' => $fileInfo['userId'],
            'original_name' => sanitize_filename($fileInfo['originalName'], false),
            'stored_name' => $fileInfo['storedName'],
            'mime_type' => $fileInfo['mimeType'],
            'size' => $fileInfo['size'],
            'storage_driver' => $this->storage->getDriverName(),
            'path' => $fileInfo['path'],
            'url' => $this->storage->url($fileInfo['path']),
            'metadata' => json_encode([
                'extension' => $fileInfo['extension'],
                'uploaded_by' => $fileInfo['userId'],
            ]),
            'uploaded_at' => date('Y-m-d H:i:s'),
        ];

        $fileId = $this->fileModel->insert($fileData);

        if (!$fileId) {
            $this->storage->delete($fileInfo['path']);
            throw new ValidationException(
                lang('Files.saveFailed'),
                $this->fileModel->errors() ?: ['file' => lang('Files.saveFailed')]
            );
        }

        $savedFile = $this->fileModel->find($fileId);

        return ApiResponse::created([
            'id' => $savedFile->id,
            'original_name' => $savedFile->original_name,
            'size' => $savedFile->size,
            'human_size' => $savedFile->getHumanSize(),
            'mime_type' => $savedFile->mime_type,
            'url' => $savedFile->url,
            'uploaded_at' => $savedFile->uploaded_at,
        ]);
    }

    /**
     * List user's files
     *
     * @param array $data Request data with 'user_id'
     * @return array
     */
    public function index(array $data): array
    {
        $this->validateInputOrBadRequest($data, 'index');

        $builder = new QueryBuilder($this->fileModel);

        // SEGURIDAD: Siempre filtrar por user_id del usuario actual
        $builder->filter(['user_id' => (int) $data['user_id']]);

        $this->applyQueryOptions($builder, $data, function (): void {
            $this->fileModel->orderBy('uploaded_at', 'DESC');
        });

        [$page, $limit] = $this->resolvePagination(
            $data,
            (int) env('PAGINATION_DEFAULT_LIMIT', 20)
        );

        $result = $builder->paginate($page, $limit);

        // Convertir entidades a arrays con metadata completa
        $result['data'] = array_map(function ($file) {
            return [
                'id' => $file->id,
                'original_name' => $file->original_name,
                'size' => $file->size,
                'human_size' => $file->getHumanSize(),
                'mime_type' => $file->mime_type,
                'url' => $file->url,
                'uploaded_at' => $file->uploaded_at,
                'is_image' => $file->isImage(),
            ];
        }, $result['data']);

        return ApiResponse::paginated(
            $result['data'],
            $result['total'],
            $result['page'],
            $result['perPage']
        );
    }

    /**
     * Download a file
     *
     * @param array $data Request data with 'id' and 'user_id'
     * @return array
     */
    public function download(array $data): array
    {
        $this->validateInputOrBadRequest($data, 'show');

        // First check if file exists
        $file = $this->fileModel->find((int) $data['id']);
        if (!$file) {
            throw new NotFoundException(lang('Files.fileNotFound'));
        }

        // Then check authorization
        if ($file->user_id !== (int) $data['user_id']) {
            $this->auditService->log(
                'unauthorized_file_download',
                'files',
                $file->id,
                [],
                ['requested_by' => $data['user_id']],
                (int) $data['user_id']
            );
            throw new AuthorizationException(lang('Files.unauthorized'));
        }

        // For local storage, return file path for download
        // For S3, return pre-signed URL
        return ApiResponse::success([
            'id' => $file->id,
            'original_name' => $file->original_name,
            'url' => $file->url,
            'path' => $file->path,
            'storage_driver' => $file->storage_driver,
        ]);
    }

    /**
     * Delete a file
     *
     * @param array $data Request data with 'id' and 'user_id'
     * @return array
     */
    public function delete(array $data): array
    {
        $this->validateInputOrBadRequest($data, 'delete');

        // First check if file exists
        $file = $this->fileModel->find((int) $data['id']);
        if (!$file) {
            throw new NotFoundException(lang('Files.fileNotFound'));
        }

        // Then check authorization
        if ($file->user_id !== (int) $data['user_id']) {
            $this->auditService->log(
                'unauthorized_file_delete',
                'files',
                $file->id,
                [],
                ['requested_by' => $data['user_id']],
                (int) $data['user_id']
            );
            throw new AuthorizationException(lang('Files.unauthorized'));
        }

        // Delete from storage
        $deleted = $this->storage->delete($file->path);

        if (!$deleted) {
            log_message('warning', "Failed to delete file from storage: {$file->path}");
        }

        // Delete from database
        $this->fileModel->delete($file->id);

        return ApiResponse::deleted(lang('Files.deleteSuccess'));
    }

    /**
     * Destroy a file (alias for delete)
     *
     * @param array $data Request data with 'id' and 'user_id'
     * @return array
     */
    public function destroy(array $data): array
    {
        return $this->delete($data);
    }

    private function validateInputOrBadRequest(array $data, string $action): void
    {
        $validation = getValidationRules('file', $action);
        $errors = validateInputs($data, $validation['rules'], $validation['messages']);

        if ($errors !== []) {
            throw new BadRequestException(lang('Files.invalidRequest'), $errors);
        }
    }

    /**
     * Generate unique filename by checking existence in storage
     *
     * @param string $originalName Original filename
     * @param string $extension File extension
     * @param string $datePath Base path for the day (Y/m/d)
     * @return string
     */
    protected function generateUniqueFilename(string $originalName, string $extension, string $datePath = ''): string
    {
        $basename = pathinfo($originalName, PATHINFO_FILENAME);

        // 1. Remove common temp prefixes like 'upload_65da..._' or similar hashes
        // This handles cases like 'upload_699e505bebdf92.24327053_Captura09'
        if (preg_match('/^upload_[a-f0-9.]+_+(.+)$/i', $basename, $matches)) {
            $basename = $matches[1];
        } elseif (preg_match('/^upload_[a-f0-9]+_+(.+)$/i', $basename, $matches)) {
            $basename = $matches[1];
        }

        // 2. Remove aggressive hexadecimal hash prefixes (16, 32 or 40 chars)
        // This handles cases like '8605b9b8f03a7a1f_Captura02'
        if (preg_match('/^[a-f0-9]{16,40}_+(.+)$/i', $basename, $matches)) {
            $basename = $matches[1];
        }

        // Clean filename: only alphanumeric, underscore, and dash
        $basename = preg_replace('/[^a-zA-Z0-9_-]/', '', $basename);
        $basename = substr($basename, 0, 80); // Limit length

        if (empty($basename)) {
            $basename = 'file';
        }

        $extension = strtolower($extension);
        $filename = "{$basename}.{$extension}";
        $fullPath = $datePath ? "{$datePath}/{$filename}" : $filename;

        // If file doesn't exist, use original name
        if (!$this->storage->exists($fullPath)) {
            return $filename;
        }

        // Handle collision with numeric series (e.g., photo_1.jpg, photo_2.jpg)
        $counter = 1;
        while ($counter <= 20) {
            $filename = "{$basename}_{$counter}.{$extension}";
            $fullPath = $datePath ? "{$datePath}/{$filename}" : $filename;

            if (!$this->storage->exists($fullPath)) {
                return $filename;
            }
            $counter++;
        }

        // Fallback for massive collisions: append a unique ID
        return "{$basename}_" . uniqid() . ".{$extension}";
    }
}
