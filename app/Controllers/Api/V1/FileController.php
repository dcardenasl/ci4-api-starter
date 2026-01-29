<?php

namespace App\Controllers\Api\V1;

use App\Controllers\ApiController;
use App\Libraries\Storage\StorageManager;
use App\Models\FileModel;
use App\Services\FileService;
use CodeIgniter\HTTP\ResponseInterface;

/**
 * File Controller
 *
 * Handles file upload, download, and management
 */
class FileController extends ApiController
{
    protected FileService $fileService;

    public function __construct()
    {
        $fileModel = new FileModel();
        $storage = new StorageManager();
        $this->fileService = new FileService($fileModel, $storage);
    }

    /**
     * Get the service instance
     *
     * @return object
     */
    protected function getService(): object
    {
        return $this->fileService;
    }

    /**
     * Get success status code for the given method
     *
     * @param string $method Service method name
     * @return int HTTP status code
     */
    protected function getSuccessStatus(string $method): int
    {
        return match ($method) {
            'upload' => 201,
            'delete' => 200,
            default => 200,
        };
    }

    /**
     * List user's files
     *
     * GET /api/v1/files
     *
     * @return ResponseInterface
     */
    public function index(): ResponseInterface
    {
        // Add user_id from authenticated user
        $data = ['user_id' => $this->request->userId];

        return $this->handleRequest('index', $data);
    }

    /**
     * Upload a file
     *
     * POST /api/v1/files/upload
     *
     * @return ResponseInterface
     */
    public function upload(): ResponseInterface
    {
        $file = $this->request->getFile('file');

        $data = [
            'file' => $file,
            'user_id' => $this->request->userId,
        ];

        return $this->handleRequest('upload', $data);
    }

    /**
     * Download a file
     *
     * GET /api/v1/files/{id}
     *
     * @param int $id File ID
     * @return ResponseInterface
     */
    public function show(int $id): ResponseInterface
    {
        $data = [
            'id' => $id,
            'user_id' => $this->request->userId,
        ];

        $result = $this->fileService->download($data);

        // If successful and local storage, send file for download
        if ($result['status'] === 'success' && $result['data']['storage_driver'] === 'local') {
            $filePath = FCPATH . env('FILE_UPLOAD_PATH', 'writable/uploads/') . $result['data']['path'];

            if (file_exists($filePath)) {
                return $this->response->download($filePath, null)->setFileName($result['data']['original_name']);
            }
        }

        // For S3 or if file not found, return JSON with URL
        return $this->response
            ->setJSON($result)
            ->setStatusCode($result['status'] === 'success' ? 200 : 404);
    }

    /**
     * Delete a file
     *
     * DELETE /api/v1/files/{id}
     *
     * @param int $id File ID
     * @return ResponseInterface
     */
    public function delete(int $id): ResponseInterface
    {
        $data = [
            'id' => $id,
            'user_id' => $this->request->userId,
        ];

        return $this->handleRequest('delete', $data);
    }
}
