<?php

declare(strict_types=1);

namespace App\Services\Files;

use App\DTO\Response\Common\PaginatedResponseDTO;
use App\DTO\Response\Files\FileDownloadResponseDTO;
use App\DTO\Response\Files\FileResponseDTO;
use App\DTO\SecurityContext;
use App\Exceptions\AuthorizationException;
use App\Exceptions\NotFoundException;
use App\Exceptions\ValidationException;
use App\Interfaces\Files\FileServiceInterface;
use App\Interfaces\System\AuditServiceInterface;
use App\Libraries\Files\Base64Processor;
use App\Libraries\Files\FilenameGenerator;
use App\Libraries\Files\MultipartProcessor;
use App\Libraries\Query\QueryBuilder;
use App\Libraries\Storage\StorageManager;
use App\Models\FileModel;
use App\Support\Files\ProcessedFile;
use App\Traits\AppliesQueryOptions;

/**
 * File Service (Refactored)
 *
 * Orchestrates file operations using specialized processors and generators.
 */
class FileService implements FileServiceInterface
{
    use AppliesQueryOptions;
    use \App\Traits\HandlesTransactions;

    public function __construct(
        protected FileModel $fileModel,
        protected StorageManager $storage,
        protected AuditServiceInterface $auditService,
        protected FilenameGenerator $filenameGenerator,
        protected MultipartProcessor $multipartProcessor,
        protected Base64Processor $base64Processor
    ) {
    }

    /**
     * Upload a file
     */
    public function upload(\App\Interfaces\DataTransferObjectInterface $request, ?SecurityContext $context = null): \App\Interfaces\DataTransferObjectInterface
    {
        /** @var \App\DTO\Request\Files\FileUploadRequestDTO $request */
        $userId = $this->resolveUserId($request, $context);

        // 1. Process Input into a standardized ProcessedFile
        $processedFile = $request->isBase64()
            ? $this->base64Processor->process($request->file, $request->toArray())
            : $this->multipartProcessor->process($request->file);

        // 2. Delegate to storage and metadata persistence
        return $this->storeAndSaveMetadata($processedFile, $userId);
    }

    /**
     * Common logic to store file and save to database
     */
    protected function storeAndSaveMetadata(ProcessedFile $file, int $userId): FileResponseDTO
    {
        $datePath = date('Y/m/d');
        $storedName = $this->filenameGenerator->generate($file->originalName, $file->extension, $datePath);
        $path = $datePath . '/' . $storedName;

        // Save physical file
        if (!$this->storage->put($path, $file->contents)) {
            throw new \RuntimeException(lang('Files.storageError'));
        }

        // Save metadata
        $fileId = $this->fileModel->insert([
            'user_id' => $userId,
            'original_name' => sanitize_filename($file->originalName, false),
            'stored_name' => $storedName,
            'mime_type' => $file->mimeType,
            'size' => $file->size,
            'storage_driver' => $this->storage->getDriverName(),
            'path' => $path,
            'url' => $this->storage->url($path),
            'metadata' => json_encode(['extension' => $file->extension, 'uploaded_by' => $userId]),
            'uploaded_at' => date('Y-m-d H:i:s'),
        ]);

        if (!$fileId) {
            $this->storage->delete($path);
            throw new ValidationException(lang('Files.saveFailed'), $this->fileModel->errors());
        }

        $savedFile = $this->fileModel->find($fileId);
        return FileResponseDTO::fromArray($savedFile->toArray());
    }

    /**
     * List user's files
     */
    public function index(\App\Interfaces\DataTransferObjectInterface $request, ?SecurityContext $context = null): \App\Interfaces\DataTransferObjectInterface
    {
        /** @var \App\DTO\Request\Files\FileIndexRequestDTO $request */
        $userId = $this->resolveUserId($request, $context);

        $builder = new QueryBuilder($this->fileModel);
        $builder->filter(['user_id' => $userId]);

        $this->applyQueryOptions($builder, $request->toArray(), function (): void {
            $this->fileModel->orderBy('uploaded_at', 'DESC');
        });

        $result = $builder->paginate($request->page, $request->perPage);

        $formattedData = array_map(fn ($file) => [
            'id' => (int) $file->id,
            'original_name' => (string) $file->original_name,
            'size' => (int) $file->size,
            'human_size' => $file->getHumanSize(),
            'mime_type' => (string) $file->mime_type,
            'url' => (string) $file->url,
            'uploaded_at' => (string) $file->uploaded_at,
            'is_image' => $file->isImage(),
        ], (array) $result['data']);

        return PaginatedResponseDTO::fromArray([
            'data'    => $formattedData,
            'total'   => $result['total'],
            'page'    => $result['page'],
            'perPage' => $result['perPage'],
        ]);
    }

    /**
     * Download a file
     */
    public function download(\App\Interfaces\DataTransferObjectInterface $request, ?SecurityContext $context = null): \App\Interfaces\DataTransferObjectInterface
    {
        /** @var \App\DTO\Request\Files\FileGetRequestDTO $request */
        $userId = $this->resolveUserId($request, $context);
        $file = $this->findFileAndAuthorize($request->id, $userId, 'download');

        return FileDownloadResponseDTO::fromArray($file->toArray());
    }

    /**
     * Delete a file
     */
    public function delete(\App\Interfaces\DataTransferObjectInterface $request, ?SecurityContext $context = null): bool
    {
        /** @var \App\DTO\Request\Files\FileGetRequestDTO $request */
        $userId = $this->resolveUserId($request, $context);
        $file = $this->findFileAndAuthorize($request->id, $userId, 'delete');

        return $this->wrapInTransaction(function () use ($file) {
            $this->storage->delete($file->path);
            return $this->fileModel->delete($file->id);
        });
    }

    /**
     * CRUD compatibility alias
     */
    public function destroy(int $id, ?SecurityContext $context = null): bool
    {
        if ($context?->userId === null) {
            throw new AuthorizationException(lang('Api.unauthorized'));
        }

        $file = $this->findFileAndAuthorize($id, $context->userId, 'delete', $context->isAdmin());

        return $this->wrapInTransaction(function () use ($file) {
            $this->storage->delete($file->path);
            return $this->fileModel->delete($file->id);
        });
    }

    protected function resolveUserId(object|array $request, ?SecurityContext $context): int
    {
        $userId = $context?->userId ?? (int) (($request instanceof \App\Interfaces\DataTransferObjectInterface ? $request->toArray() : (array)$request)['user_id'] ?? 0);
        if ($userId === 0) {
            throw new AuthorizationException(lang('Api.unauthorized'));
        }
        return $userId;
    }

    protected function findFileAndAuthorize(int $id, int $userId, string $action, bool $bypassOwnership = false): \App\Entities\FileEntity
    {
        /** @var \App\Entities\FileEntity|null $file */
        $file = $this->fileModel->find($id);
        if (!$file) {
            throw new NotFoundException(lang('Files.fileNotFound'));
        }

        if (!$bypassOwnership && (int) $file->user_id !== $userId) {
            $this->auditService->log("unauthorized_file_{$action}", 'files', $id, [], ['requested_by' => $userId]);
            throw new AuthorizationException(lang('Files.unauthorized'));
        }

        return $file;
    }
}
