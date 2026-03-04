<?php

declare(strict_types=1);

namespace App\Services\Tokens;

use App\DTO\Response\ApiKeys\ApiKeyResponseDTO;
use App\DTO\SecurityContext;
use App\Interfaces\Mappers\ResponseMapperInterface;
use App\Interfaces\Tokens\ApiKeyServiceInterface;
use App\Models\ApiKeyModel;
use App\Services\Core\BaseCrudService;
use App\Services\Tokens\Actions\CreateApiKeyAction;
use App\Services\Tokens\Actions\UpdateApiKeyAction;

class ApiKeyService extends BaseCrudService implements ApiKeyServiceInterface
{
    public function __construct(
        protected ApiKeyModel $apiKeyModel,
        ResponseMapperInterface $responseMapper,
        protected CreateApiKeyAction $createApiKeyAction,
        protected UpdateApiKeyAction $updateApiKeyAction
    ) {
        parent::__construct(new \App\Repositories\GenericRepository($apiKeyModel), $responseMapper);
    }

    /**
     * Create a new API key
     */
    public function store(\App\Interfaces\DataTransferObjectInterface $request, ?SecurityContext $context = null): \App\Interfaces\DataTransferObjectInterface
    {
        /** @var \App\DTO\Request\ApiKeys\ApiKeyCreateRequestDTO $request */
        return $this->wrapInTransaction(function () use ($request) {
            ['entity' => $apiKey, 'key' => $rawKey] = $this->createApiKeyAction->execute($request);

            $apiKeyData = $apiKey->toArray();
            $apiKeyData['key'] = $rawKey;

            return ApiKeyResponseDTO::fromArray($apiKeyData);
        });
    }

    /**
     * Update an API key
     */
    public function update(int $id, \App\Interfaces\DataTransferObjectInterface $request, ?SecurityContext $context = null): \App\Interfaces\DataTransferObjectInterface
    {
        /** @var \App\DTO\Request\ApiKeys\ApiKeyUpdateRequestDTO $request */
        return $this->wrapInTransaction(function () use ($id, $request) {
            $updatedApiKey = $this->updateApiKeyAction->execute($id, $request);
            return $this->mapToResponse($updatedApiKey);
        });
    }
}
