<?php

declare(strict_types=1);

namespace App\Controllers\Api\V1\Files;

use App\Controllers\ApiController;
use CodeIgniter\HTTP\ResponseInterface;

/**
 * File Controller - Upload, download, delete files
 */
class FileController extends ApiController
{
    protected string $serviceName = 'fileService';

    public function upload(): ResponseInterface
    {
        return $this->handleRequest('upload');
    }

    public function show($id = null): ResponseInterface
    {
        try {
            $result = $this->getService()->download([
                'id'      => $id,
                'user_id' => $this->getUserId(),
            ]);

            // For local storage, send file for download
            if ($result['status'] === 'success' && $result['data']['storage_driver'] === 'local') {
                $filePath = FCPATH . env('FILE_UPLOAD_PATH', 'writable/uploads/') . $result['data']['path'];

                if (file_exists($filePath)) {
                    return $this->response->download($filePath, null)->setFileName($result['data']['original_name']);
                }
            }

            return $this->respond($result, $result['status'] === 'success' ? 200 : 404);
        } catch (\Exception $e) {
            return $this->handleException($e);
        }
    }
}
