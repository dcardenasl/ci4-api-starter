<?php

declare(strict_types=1);

namespace App\Services;

use App\Libraries\ApiResponse;
use App\Libraries\Storage\StorageManager;
use App\Models\FileModel;

/**
 * File Service
 *
 * Handles file upload, download, and deletion with storage abstraction
 */
class FileService
{
    protected FileModel $fileModel;
    protected StorageManager $storage;

    public function __construct(FileModel $fileModel, StorageManager $storage)
    {
        $this->fileModel = $fileModel;
        $this->storage = $storage;
    }

    /**
     * Upload a file
     *
     * @param array $data Request data with 'file' and 'user_id'
     * @return array
     */
    public function upload(array $data): array
    {
        // Validate required fields
        if (empty($data['file']) || empty($data['user_id'])) {
            return ApiResponse::error(
                ['file' => 'File is required'],
                'Invalid request'
            );
        }

        $file = $data['file'];
        $userId = (int) $data['user_id'];

        // Validate file object
        if (!is_object($file) || !method_exists($file, 'isValid')) {
            return ApiResponse::error(
                ['file' => 'Invalid file object'],
                'Invalid request'
            );
        }

        // Check if file is valid
        if (!$file->isValid()) {
            return ApiResponse::error(
                ['file' => 'File upload failed: ' . $file->getErrorString()],
                'Upload failed'
            );
        }

        // Validate file size
        $maxSize = (int) env('FILE_MAX_SIZE', 10485760); // 10MB default
        if ($file->getSize() > $maxSize) {
            return ApiResponse::error(
                ['file' => 'File size exceeds maximum allowed size'],
                'File too large'
            );
        }

        // Validate file type
        $allowedTypes = explode(',', env('FILE_ALLOWED_TYPES', 'jpg,jpeg,png,gif,pdf'));
        $extension = $file->getExtension();

        if (!in_array(strtolower($extension), $allowedTypes, true)) {
            return ApiResponse::error(
                ['file' => 'File type not allowed'],
                'Invalid file type'
            );
        }

        // Generate unique filename
        $storedName = $this->generateUniqueFilename($file->getName(), $extension);
        $path = date('Y/m/d') . '/' . $storedName;

        // Store file
        $contents = file_get_contents($file->getTempName());
        $stored = $this->storage->put($path, $contents);

        if (!$stored) {
            return ApiResponse::error(
                ['file' => 'Failed to store file'],
                'Storage error'
            );
        }

        // Save metadata to database
        $fileData = [
            'user_id' => $userId,
            'original_name' => $file->getName(),
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

            return ApiResponse::validationError($this->fileModel->errors());
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
        if (empty($data['user_id'])) {
            return ApiResponse::error(
                ['user_id' => 'User ID is required'],
                'Invalid request'
            );
        }

        $files = $this->fileModel->getByUser((int) $data['user_id']);

        $filesArray = array_map(function ($file) {
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
        }, $files);

        return ApiResponse::success($filesArray);
    }

    /**
     * Download a file
     *
     * @param array $data Request data with 'id' and 'user_id'
     * @return array
     */
    public function download(array $data): array
    {
        if (empty($data['id']) || empty($data['user_id'])) {
            return ApiResponse::error(
                ['id' => 'File ID and User ID are required'],
                'Invalid request'
            );
        }

        $file = $this->fileModel->getByIdAndUser(
            (int) $data['id'],
            (int) $data['user_id']
        );

        if (!$file) {
            return ApiResponse::error(
                ['file' => 'File not found or access denied'],
                'Not found',
                404
            );
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
        if (empty($data['id']) || empty($data['user_id'])) {
            return ApiResponse::error(
                ['id' => 'File ID and User ID are required'],
                'Invalid request'
            );
        }

        $file = $this->fileModel->getByIdAndUser(
            (int) $data['id'],
            (int) $data['user_id']
        );

        if (!$file) {
            return ApiResponse::error(
                ['file' => 'File not found or access denied'],
                'Not found',
                404
            );
        }

        // Delete from storage
        $deleted = $this->storage->delete($file->path);

        if (!$deleted) {
            log_message('warning', "Failed to delete file from storage: {$file->path}");
        }

        // Delete from database
        $this->fileModel->delete($file->id);

        return ApiResponse::deleted('File deleted successfully');
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

        return $basename . '_' . uniqid() . '.' . $extension;
    }
}
