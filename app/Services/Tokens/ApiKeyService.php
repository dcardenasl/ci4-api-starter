<?php

declare(strict_types=1);

namespace App\Services\Tokens;

use App\DTO\SecurityContext;
use App\Exceptions\BadRequestException;
use App\Exceptions\ValidationException;
use App\Interfaces\Tokens\ApiKeyServiceInterface;
use App\Models\ApiKeyModel;
use App\Services\Core\BaseCrudService;

/**
 * Api Key Service
 *
 * Manages the lifecycle of API keys for server-to-server or third-party access.
 */
class ApiKeyService extends BaseCrudService implements ApiKeyServiceInterface
{
    protected string $responseDtoClass = \App\DTO\Response\ApiKeys\ApiKeyResponseDTO::class;

    public function __construct(protected ApiKeyModel $apiKeyModel)
    {
        $this->model = $apiKeyModel;
        helper('security');
    }

    /**
     * Create a new API key
     */
    public function store(\App\Interfaces\DataTransferObjectInterface $request, ?SecurityContext $context = null): \App\Interfaces\DataTransferObjectInterface
    {
        /** @var \App\DTO\Request\ApiKeys\ApiKeyCreateRequestDTO $request */
        return $this->wrapInTransaction(function () use ($request) {
            $rawKey = \generate_api_key();
            $hash = \hash_api_key($rawKey);

            $data = [
                'name' => $request->name,
                'key_prefix' => substr($rawKey, 0, 12),
                'key_hash' => $hash,
                'is_active' => 1,
                'rate_limit_requests' => $request->rateLimitRequests ?? 600,
                'rate_limit_window' => $request->rateLimitWindow ?? 60,
                'user_rate_limit' => $request->userRateLimit ?? 60,
                'ip_rate_limit' => $request->ipRateLimit ?? 200,
            ];

            $id = $this->model->insert($data);

            if (!$id) {
                throw new ValidationException(lang('Api.validationFailed'), $this->model->errors());
            }

            /** @var object $apiKey */
            $apiKey = $this->model->find($id);

            // Return full key only at creation time.
            $apiKeyData = $apiKey->toArray();
            $apiKeyData['key'] = $rawKey;

            return \App\DTO\Response\ApiKeys\ApiKeyResponseDTO::fromArray($apiKeyData);
        });
    }

    /**
     * Update an API key
     */
    public function update(int $id, \App\Interfaces\DataTransferObjectInterface $request, ?SecurityContext $context = null): \App\Interfaces\DataTransferObjectInterface
    {
        $existing = $this->model->find($id);
        if (!$existing) {
            throw new \App\Exceptions\NotFoundException(lang('Api.resourceNotFound'));
        }

        return $this->wrapInTransaction(function () use ($id, $request) {
            $data = array_filter($request->toArray(), fn ($val) => $val !== null);

            if (empty($data)) {
                throw new BadRequestException(lang('Api.noFieldsToUpdate'));
            }

            if (!$this->model->update($id, $data)) {
                throw new ValidationException(lang('Api.validationFailed'), $this->model->errors());
            }

            /** @var object $updatedApiKey */
            $updatedApiKey = $this->model->find($id);
            return $this->mapToResponse($updatedApiKey);
        });
    }
}
