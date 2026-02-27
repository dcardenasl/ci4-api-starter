<?php

declare(strict_types=1);

namespace App\Interfaces;

use App\DTO\SecurityContext;

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
    public function upload(\App\Interfaces\DataTransferObjectInterface $request, ?SecurityContext $context = null): \App\Interfaces\DataTransferObjectInterface;

    /**
     * List user's files
     */
    public function index(\App\Interfaces\DataTransferObjectInterface $request, ?SecurityContext $context = null): \App\Interfaces\DataTransferObjectInterface;

    /**
     * Download a file
     */
    public function download(\App\Interfaces\DataTransferObjectInterface $request, ?SecurityContext $context = null): \App\Interfaces\DataTransferObjectInterface;

    /**
     * Delete a file
     */
    public function delete(\App\Interfaces\DataTransferObjectInterface $request, ?SecurityContext $context = null): bool;

    /**
     * Destroy a file (alias for delete to match CRUD contract)
     */
    public function destroy(int $id, ?SecurityContext $context = null): bool;
}
