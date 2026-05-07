<?php

declare(strict_types=1);

namespace App\Services\Files;

use App\DTO\Response\Files\FileDownloadResponseDTO;
use App\DTO\Response\Files\FileResponseDTO;
use App\Exceptions\AuthorizationException;
use App\Interfaces\Files\FileRepositoryInterface;
use App\Interfaces\Files\FileServiceInterface;
use App\Interfaces\Files\VirusScannerServiceInterface;
use App\Libraries\Files\Base64Processor;
use App\Libraries\Files\FilenameGenerator;
use App\Libraries\Files\MultipartProcessor;
use App\Libraries\Storage\StorageManager;
use App\Support\Files\ProcessedFile;
use App\Traits\AppliesQueryOptions;
use dcardenasl\Ci4ApiCore\Dto\PaginatedResponseDTO;
use dcardenasl\Ci4ApiCore\Dto\SecurityContext;
use dcardenasl\Ci4ApiCore\Exceptions\BadRequestException;
use dcardenasl\Ci4ApiCore\Exceptions\NotFoundException;
use dcardenasl\Ci4ApiCore\Exceptions\ValidationException;
use dcardenasl\Ci4ApiCore\Services\AuditServiceInterface;

/**
 * File Service (Refactored)
 *
 * Orchestrates file operations using specialized processors and generators.
 */
class FileService implements FileServiceInterface
{
    use AppliesQueryOptions;
    use \dcardenasl\Ci4ApiCore\Services\HandlesTransactions;

    public function __construct(
        protected FileRepositoryInterface $fileRepository,
        protected \dcardenasl\Ci4ApiCore\Mappers\ResponseMapperInterface $responseMapper,
        protected StorageManager $storage,
        protected AuditServiceInterface $auditService,
        protected FilenameGenerator $filenameGenerator,
        protected MultipartProcessor $multipartProcessor,
        protected Base64Processor $base64Processor,
        protected ?VirusScannerServiceInterface $virusScanner = null,
        private bool $userScopedFiles = true
    ) {
    }

    /**
     * Upload a file
     */
    public function upload(\dcardenasl\Ci4ApiCore\Dto\DataTransferObjectInterface $request, ?SecurityContext $context = null): \dcardenasl\Ci4ApiCore\Dto\DataTransferObjectInterface
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
        // 1. Virus Scanning Phase
        if ($this->virusScanner !== null) {
            $tempPath = tempnam(sys_get_temp_dir(), 'api_upload_');
            if ($tempPath === false) {
                throw new \RuntimeException(lang('Files.temp_file_creation_failed'));
            }
            $tempStream = fopen($tempPath, 'wb');

            if ($tempStream !== false) {
                // Rewind the stream to ensure we read from start
                rewind($file->contents);
                stream_copy_to_stream($file->contents, $tempStream);
                fclose($tempStream);

                try {
                    if (!$this->virusScanner->isSafe($tempPath)) {
                        throw new BadRequestException(lang('Files.malware_detected'));
                    }
                } finally {
                    @unlink($tempPath);
                    // Rewind again for the final storage process
                    rewind($file->contents);
                }
            }
        }

        $datePath = date('Y/m/d');
        $storedName = $this->filenameGenerator->generate($file->originalName, $file->extension, $datePath);
        $path = $datePath . '/' . $storedName;

        // Save physical file
        if (!$this->storage->put($path, $file->contents)) {
            throw new \RuntimeException(lang('Files.storage_error'));
        }

        // Save metadata
        $fileId = $this->fileRepository->insert([
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

        if ($fileId === false || $fileId === true) {
            $this->storage->delete($path);
            throw new ValidationException(lang('Files.save_failed'), $this->fileRepository->errors());
        }

        $savedFile = $this->fileRepository->find($fileId);
        if ($savedFile === null) {
            throw new \RuntimeException(sprintf('File row %d disappeared after insert.', (int) $fileId));
        }
        /** @var FileResponseDTO $response */
        $response = $this->responseMapper->map($savedFile);
        return $response;
    }

    /**
     * List user's files
     */
    public function index(\dcardenasl\Ci4ApiCore\Dto\DataTransferObjectInterface $request, ?SecurityContext $context = null): \dcardenasl\Ci4ApiCore\Dto\DataTransferObjectInterface
    {
        /** @var \App\DTO\Request\Files\FileIndexRequestDTO $request */
        $userId = $this->resolveUserId($request, $context);

        $baseCriteria = $this->userScopedFiles
            ? fn ($model) => $model->where('user_id', $userId)
            : null;

        $result = $this->fileRepository->paginateCriteria(
            $request->toArray(),
            $request->page,
            $request->per_page,
            $baseCriteria
        );

        $result['data'] = array_map(
            fn ($entity) => $this->responseMapper->map($entity),
            (array) $result['data']
        );

        return PaginatedResponseDTO::fromArray($result);
    }

    /**
     * Return JSON metadata for a single file without downloading the binary.
     */
    public function findById(int $id, ?SecurityContext $context = null): FileResponseDTO
    {
        if ($context?->user_id === null) {
            throw new AuthorizationException(lang('Api.unauthorized'));
        }

        $file = $this->findFileAndAuthorize($id, $context->user_id, 'view', $context->hasPermission('files.read'), $context);

        /** @var FileResponseDTO $response */
        $response = $this->responseMapper->map($file);
        return $response;
    }

    /**
     * Download a file
     */
    public function download(\dcardenasl\Ci4ApiCore\Dto\DataTransferObjectInterface $request, ?SecurityContext $context = null): \dcardenasl\Ci4ApiCore\Dto\DataTransferObjectInterface
    {
        /** @var \App\DTO\Request\Files\FileGetRequestDTO $request */
        $userId = $this->resolveUserId($request, $context);
        $file = $this->findFileAndAuthorize($request->id, $userId, 'download', false, $context);

        return FileDownloadResponseDTO::fromArray($file->toArray());
    }

    /**
     * Delete a file by identifier.
     */
    public function destroy(int $id, ?SecurityContext $context = null): bool
    {
        if ($context?->user_id === null) {
            throw new AuthorizationException(lang('Api.unauthorized'));
        }

        $file = $this->findFileAndAuthorize($id, $context->user_id, 'delete', $context->hasPermission('files.read'), $context);

        return $this->wrapInTransaction(function () use ($file) {
            $this->storage->delete($file->path);
            return $this->fileRepository->delete($file->id);
        });
    }

    protected function resolveUserId(object|array $request, ?SecurityContext $context): int
    {
        $data = $request instanceof \dcardenasl\Ci4ApiCore\Dto\DataTransferObjectInterface ? $request->toArray() : (array)$request;
        $context ??= SecurityContext::anonymous();
        $userId = $context->user_id ?? (int) ($data['user_id'] ?? 0);

        if ($userId === 0) {
            throw new AuthorizationException(lang('Api.unauthorized'));
        }
        return $userId;
    }
    protected function findFileAndAuthorize(
        int $id,
        int $userId,
        string $action,
        bool $bypassOwnership = false,
        ?SecurityContext $context = null
    ): \App\Entities\FileEntity {
        /** @var \App\Entities\FileEntity|null $file */
        $file = $this->fileRepository->find($id);
        if (!$file) {
            throw new NotFoundException(lang('Files.file_not_found'));
        }

        $effectiveBypass = $bypassOwnership
            || (in_array($action, ['download', 'view'], true) && !$this->userScopedFiles);

        if (!$effectiveBypass && (int) $file->user_id !== $userId) {
            $deniedAction = match ($action) {
                'download' => 'unauthorized_file_download',
                'delete'   => 'unauthorized_file_delete',
                default    => 'unauthorized_file_access',
            };
            $this->auditService->log(
                $deniedAction,
                'files',
                $id,
                [],
                ['requested_by' => $userId, 'owner_id' => (int) $file->user_id],
                $context,
                'denied',
                'critical'
            );
            throw new AuthorizationException(lang('Files.unauthorized'));
        }

        return $file;
    }
}
