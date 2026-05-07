<?php

declare(strict_types=1);

namespace App\Interfaces\Files;

use dcardenasl\Ci4ApiCore\Dto\SecurityContext;

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
    public function upload(\dcardenasl\Ci4ApiCore\Dto\DataTransferObjectInterface $request, ?SecurityContext $context = null): \dcardenasl\Ci4ApiCore\Dto\DataTransferObjectInterface;

    /**
     * List user's files
     */
    public function index(\dcardenasl\Ci4ApiCore\Dto\DataTransferObjectInterface $request, ?SecurityContext $context = null): \dcardenasl\Ci4ApiCore\Dto\DataTransferObjectInterface;

    /**
     * Download a file
     */
    public function download(\dcardenasl\Ci4ApiCore\Dto\DataTransferObjectInterface $request, ?SecurityContext $context = null): \dcardenasl\Ci4ApiCore\Dto\DataTransferObjectInterface;

    /**
     * Return JSON metadata for a single file without downloading the binary.
     */
    public function findById(int $id, ?SecurityContext $context = null): \App\DTO\Response\Files\FileResponseDTO;

    /**
     * Destroy a file (alias for delete to match CRUD contract)
     */
    public function destroy(int $id, ?SecurityContext $context = null): bool;
}
