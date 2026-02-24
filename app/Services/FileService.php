<?php

declare(strict_types=1);

namespace App\Services;

use App\Exceptions\AuthorizationException;
use App\Exceptions\BadRequestException;
use App\Exceptions\NotFoundException;
use App\Exceptions\ValidationException;
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
        protected StorageManager $storage
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
        $this->validateInputOrBadRequest($data, 'upload');

        $file = $data['file'];
        $userId = (int) $data['user_id'];

        // Validate file object
        if (!is_object($file) || !method_exists($file, 'isValid')) {
            throw new BadRequestException(
                lang('Files.invalidRequest'),
                ['file' => lang('Files.invalidFileObject')]
            );
        }

        // Check if file is valid
        if (!$file->isValid()) {
            throw new BadRequestException(
                lang('Files.uploadFailed', [$file->getErrorString()]),
                ['file' => lang('Files.uploadFailed', [$file->getErrorString()])]
            );
        }

        // Validate file size
        $maxSize = (int) env('FILE_MAX_SIZE', 10485760); // 10MB default
        if ($file->getSize() > $maxSize) {
            throw new ValidationException(
                lang('Files.fileTooLarge'),
                ['file' => lang('Files.fileTooLarge')]
            );
        }

        // Validate file type
        $allowedTypes = explode(',', env('FILE_ALLOWED_TYPES', 'jpg,jpeg,png,gif,pdf'));
        $extension = $file->getExtension();

        if (!in_array(strtolower($extension), $allowedTypes, true)) {
            throw new ValidationException(
                lang('Files.invalidFileType'),
                ['file' => lang('Files.invalidFileType')]
            );
        }

        // Generate unique filename
        $storedName = $this->generateUniqueFilename($file->getName(), $extension);
        $path = date('Y/m/d') . '/' . $storedName;

        // Store file
        $contents = file_get_contents($file->getTempName());
        $stored = $this->storage->put($path, $contents);

        if (!$stored) {
            throw new \RuntimeException(lang('Files.storageError'));
        }

        // Save metadata to database
        $fileData = [
            'user_id' => $userId,
            'original_name' => sanitize_filename($file->getName(), false),
            'stored_name' => $storedName,
            'mime_type' => $file->getMimeType(),
            'size' => $file->getSize(),
            'storage_driver' => $this->storage->getDriverName(),
            'path' => $path,
            'url' => $this->storage->url($path),
            'metadata' => json_encode([
                'extension' => $extension,
                'uploaded_by' => $userId,
            ]),
            'uploaded_at' => date('Y-m-d H:i:s'),
        ];

        $fileId = $this->fileModel->insert($fileData);

        if (!$fileId) {
            // Rollback: delete file from storage
            $this->storage->delete($path);

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
     * Generate unique filename
     *
     * @param string $originalName Original filename
     * @param string $extension File extension
     * @return string
     */
    protected function generateUniqueFilename(string $originalName, string $extension): string
    {
        $basename = pathinfo($originalName, PATHINFO_FILENAME);
        $basename = preg_replace('/[^a-zA-Z0-9_-]/', '', $basename);
        $basename = substr($basename, 0, 50); // Limit length

        // If basename is empty after sanitization, use a default
        if (empty($basename)) {
            $basename = 'file';
        }

        return $basename . '_' . uniqid() . '.' . $extension;
    }
}
