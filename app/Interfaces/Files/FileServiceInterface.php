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
     * Soft-delete a file: sets `deleted_at` and `deleted_by_user_id` but keeps
     * the bytes on disk so a later `restore()` is non-destructive.
     */
    public function destroy(int $id, ?SecurityContext $context = null): bool;

    /**
     * Restore a previously trashed file. Throws NotFoundException if the
     * file is not currently trashed.
     */
    public function restore(int $id, ?SecurityContext $context = null): bool;

    /**
     * Permanently delete a file: removes the storage object and the DB row.
     * Refuses if the file is not currently trashed (force-delete is only
     * reachable from the trash UI).
     */
    public function forceDestroy(int $id, ?SecurityContext $context = null): bool;

    /**
     * Bulk variants. Return per-item outcomes so the admin can show a partial
     * success summary: each entry is `{id: int, ok: bool, error?: string}`.
     *
     * @param list<int> $ids
     * @return list<array{id:int, ok:bool, error?:string}>
     */
    public function bulkDestroy(array $ids, ?SecurityContext $context = null): array;

    /**
     * @param list<int> $ids
     * @return list<array{id:int, ok:bool, error?:string}>
     */
    public function bulkRestore(array $ids, ?SecurityContext $context = null): array;

    /**
     * @param list<int> $ids
     * @return list<array{id:int, ok:bool, error?:string}>
     */
    public function bulkForceDestroy(array $ids, ?SecurityContext $context = null): array;
}
