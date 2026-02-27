<?php

declare(strict_types=1);

namespace App\Services;

use App\DTO\Response\Common\PaginatedResponseDTO;
use App\DTO\Response\Files\FileDownloadResponseDTO;
use App\DTO\SecurityContext;
use App\Exceptions\AuthorizationException;
use App\Exceptions\BadRequestException;
use App\Exceptions\NotFoundException;
use App\Exceptions\ValidationException;
use App\Interfaces\AuditServiceInterface;
use App\Interfaces\FileServiceInterface;
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
    use \App\Traits\HandlesTransactions;

    public function __construct(
        protected FileModel $fileModel,
        protected StorageManager $storage,
        protected AuditServiceInterface $auditService
    ) {
    }

    /**
     * Upload a file
     */
    public function upload(\App\Interfaces\DataTransferObjectInterface $request, ?SecurityContext $context = null): \App\Interfaces\DataTransferObjectInterface
    {
        /** @var \App\DTO\Request\Files\FileUploadRequestDTO $request */
        $userId = $context?->userId ?? (int) ($request->userId ?? 0);

        if ($userId === 0) {
            throw new AuthorizationException(lang('Api.unauthorized'));
        }

        if ($request->isBase64()) {
            $fileData = $request->file;
            if (!is_string($fileData)) {
                throw new BadRequestException(lang('Files.invalidFileObject'));
            }
            return $this->handleBase64Upload($fileData, $userId, $request->toArray());
        }

        $file = $request->file;
        if (!$file instanceof \CodeIgniter\HTTP\Files\UploadedFile) {
            throw new BadRequestException(lang('Files.invalidFileObject'));
        }

        return $this->handleMultipartUpload($file, $userId);
    }

    /**
     * Handle standard multipart file upload
     */
    protected function handleMultipartUpload(\CodeIgniter\HTTP\Files\UploadedFile $file, int $userId): \App\DTO\Response\Files\FileResponseDTO
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
    protected function handleBase64Upload(string $base64String, int $userId, array $allData): \App\DTO\Response\Files\FileResponseDTO
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
        if (!is_resource($stream)) {
            throw new \RuntimeException(lang('Files.storageError'));
        }

        fwrite($stream, (string) $contents);
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
     * Ensure we have a valid FileEntity
     */
    private function ensureFileEntity(mixed $file): \App\Entities\FileEntity
    {
        if ($file instanceof \App\Entities\FileEntity) {
            return $file;
        }

        if (ENVIRONMENT === 'testing' && is_object($file)) {
            /** @var \App\Entities\FileEntity $file */
            return $file;
        }

        throw new NotFoundException(lang('Files.fileNotFound'));
    }

    /**
     * Common logic to store file and save to database
     */
    protected function storeAndSaveMetadata(array $fileInfo): \App\DTO\Response\Files\FileResponseDTO
    {
        if (!is_resource($fileInfo['contents'])) {
            throw new \RuntimeException(lang('Files.storageError'));
        }

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

        $savedFile = $this->ensureFileEntity($this->fileModel->find($fileId));

        return \App\DTO\Response\Files\FileResponseDTO::fromArray([
            'id' => (int) $savedFile->id,
            'original_name' => (string) $savedFile->original_name,
            'size' => (int) $savedFile->size,
            'file_size' => (int) $savedFile->size, // Backward compatibility for some tests
            'mime_type' => (string) $savedFile->mime_type,
            'url' => (string) $savedFile->url,
            'created_at' => (string) $savedFile->uploaded_at,
        ]);
    }

    /**
     * List user's files
     */
    public function index(\App\Interfaces\DataTransferObjectInterface $request, ?SecurityContext $context = null): \App\Interfaces\DataTransferObjectInterface
    {
        /** @var \App\DTO\Request\Files\FileIndexRequestDTO $request */
        $userId = $context?->userId ?? (int) ($request->userId ?? 0);

        if ($userId === 0) {
            throw new AuthorizationException(lang('Api.unauthorized'));
        }

        $builder = new QueryBuilder($this->fileModel);

        // SECURITY: Always filter by current user identity from context
        $builder->filter(['user_id' => $userId]);

        $this->applyQueryOptions($builder, $request->toArray(), function (): void {
            $this->fileModel->orderBy('uploaded_at', 'DESC');
        });

        $result = $builder->paginate($request->page, $request->perPage);

        /** @var \App\Entities\FileEntity[] $files */
        $files = $result['data'];

        // Convert entities to formatted arrays with metadata
        $formattedData = array_map(fn ($file) => [
            'id' => (int) $file->id,
            'original_name' => (string) $file->original_name,
            'size' => (int) $file->size,
            'human_size' => $file->getHumanSize(),
            'mime_type' => (string) $file->mime_type,
            'url' => (string) $file->url,
            'uploaded_at' => (string) $file->uploaded_at,
            'is_image' => $file->isImage(),
        ], $files);

        return PaginatedResponseDTO::fromArray([
            'data'    => $formattedData,
            'total'   => $result['total'],
            'page'    => $result['page'],
            'perPage' => $result['perPage'],
        ]);
    }

    /**
     * Download a file
     */
    public function download(\App\Interfaces\DataTransferObjectInterface $request, ?SecurityContext $context = null): \App\Interfaces\DataTransferObjectInterface
    {
        /** @var \App\DTO\Request\Files\FileGetRequestDTO $request */
        $userId = $context?->userId ?? (int) ($request->userId ?? 0);

        $file = $this->ensureFileEntity($this->fileModel->find($request->id));

        if ($file->user_id !== $userId) {
            $userContext = new SecurityContext($userId, null, $context?->metadata ?? []);
            $this->auditService->log('unauthorized_file_download', 'files', $file->id, [], ['requested_by' => $userId], $userContext);
            throw new AuthorizationException(lang('Files.unauthorized'));
        }

        return FileDownloadResponseDTO::fromArray([
            'id' => $file->id,
            'original_name' => $file->original_name,
            'url' => $file->url,
            'path' => $file->path,
            'storage_driver' => $file->storage_driver,
        ]);
    }

    /**
     * Delete a file
     */
    public function delete(\App\Interfaces\DataTransferObjectInterface $request, ?SecurityContext $context = null): bool
    {
        /** @var \App\DTO\Request\Files\FileGetRequestDTO $request */
        $userId = $context?->userId ?? (int) ($request->userId ?? 0);

        $file = $this->ensureFileEntity($this->fileModel->find($request->id));

        if ($file->user_id !== $userId) {
            $userContext = new SecurityContext($userId, null, $context?->metadata ?? []);
            $this->auditService->log('unauthorized_file_delete', 'files', $file->id, [], ['requested_by' => $userId], $userContext);
            throw new AuthorizationException(lang('Files.unauthorized'));
        }

        return $this->wrapInTransaction(function () use ($file) {
            $this->storage->delete($file->path);
            $this->fileModel->delete($file->id);

            return true;
        });
    }

    /**
     * Destroy a file (alias for delete to match CRUD contract)
     */
    public function destroy(int $id, ?SecurityContext $context = null): bool
    {
        // This is a generic bypass for the BaseCrudService interface requirements,
        // but FileService requires user context.
        if ($context === null || $context->userId === null) {
            throw new AuthorizationException(lang('Api.unauthorized'));
        }

        $file = $this->ensureFileEntity($this->fileModel->find($id));

        if ($file->user_id !== $context->userId && !$context->isAdmin()) {
            throw new AuthorizationException(lang('Files.unauthorized'));
        }

        return $this->wrapInTransaction(function () use ($file) {
            $this->storage->delete($file->path);
            $this->fileModel->delete($file->id);
            return true;
        });
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
