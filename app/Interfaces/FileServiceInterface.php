<?php

declare(strict_types=1);

namespace App\Interfaces;

use App\DTO\Request\Files\FileUploadRequestDTO;
use App\DTO\Response\Files\FileResponseDTO;

/**
 * File Service Interface
 *
 * Contract for file upload, download, and deletion operations
 */
interface FileServiceInterface
{
    /**
     * Upload a file
     */
    public function upload(FileUploadRequestDTO $request): FileResponseDTO;

    /**
     * List user's files
     */
    public function index(array $data): array;

    /**
     * Download a file
     */
    public function download(array $data): array;

    /**
     * Delete a file
     */
    public function delete(array $data): array;

    /**
     * Destroy a file (alias for delete, used by ApiController)
     */
    public function destroy(array $data): array;
}
