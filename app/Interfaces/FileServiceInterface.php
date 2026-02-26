<?php

declare(strict_types=1);

namespace App\Interfaces;

/**
 * File Service Interface
 *
 * Contract for file upload, download, and deletion.
 */
interface FileServiceInterface
{
    /**
     * Upload a file
     */
    public function upload(\App\Interfaces\DataTransferObjectInterface $request): \App\Interfaces\DataTransferObjectInterface;

    /**
     * List user's files
     */
    public function index(\App\Interfaces\DataTransferObjectInterface $request): array;

    /**
     * Download a file
     */
    public function download(\App\Interfaces\DataTransferObjectInterface $request): array;

    /**
     * Delete a file
     */
    public function delete(\App\Interfaces\DataTransferObjectInterface $request): array;

    /**
     * Destroy a file (alias for delete to match CRUD contract)
     */
    public function destroy(int $id): array;
}
