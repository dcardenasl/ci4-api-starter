<?php

declare(strict_types=1);

namespace App\Controllers\Api\V1\Files;

use App\Controllers\ApiController;
use App\DTO\Request\Files\FileGetRequestDTO;
use App\DTO\Request\Files\FileIndexRequestDTO;
use App\DTO\Request\Files\FileUploadRequestDTO;
use App\Libraries\ApiResponse;
use CodeIgniter\HTTP\ResponseInterface;

/**
 * File Controller
 *
 * Handles file uploads, listing, downloading, and deletion.
 * Uses automated DTO validation and user context injection.
 */
class FileController extends ApiController
{
    protected string $serviceName = 'fileService';

    /**
     * Map upload to 201 Created status
     */
    protected array $statusCodes = [
        'upload' => 201,
        'store'  => 201,
    ];

    /**
     * List user's files
     */
    public function index(): ResponseInterface
    {
        return $this->handleRequest('index', FileIndexRequestDTO::class);
    }

    /**
     * Upload a new file
     */
    public function upload(): ResponseInterface
    {
        return $this->handleRequest('upload', FileUploadRequestDTO::class);
    }

    /**
     * Download a file
     */
    public function show(int $id): ResponseInterface
    {
        return $this->handleRequest(function ($dto, $context) {
            $result = $this->getService()->download($dto, $context);

            // For local storage, send file for direct download
            if (isset($result['storage_driver']) && $result['storage_driver'] === 'local') {
                $filePath = FCPATH . env('FILE_UPLOAD_PATH', 'writable/uploads/') . $result['path'];

                if (file_exists($filePath)) {
                    return $this->response->download($filePath, null)->setFileName($result['original_name']);
                }
            }

            // For external storage like S3, just return the data (with URL)
            return ApiResponse::success($result);
        }, FileGetRequestDTO::class, ['id' => $id]);
    }

    /**
     * Delete a file
     */
    public function delete(int $id): ResponseInterface
    {
        return $this->handleRequest('delete', FileGetRequestDTO::class, ['id' => $id]);
    }

}
