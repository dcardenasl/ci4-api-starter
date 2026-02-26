<?php

declare(strict_types=1);

namespace App\Services;

use App\Exceptions\BadRequestException;
use App\Exceptions\ValidationException;
use App\Interfaces\ApiKeyServiceInterface;
use App\Interfaces\DataTransferObjectInterface;
use App\Models\ApiKeyModel;

/**
 * API Key Service
 *
 * Handles CRUD operations for API keys.
 * Extends BaseCrudService to automate index, show, and destroy operations.
 */
class ApiKeyService extends BaseCrudService implements ApiKeyServiceInterface
{
    use \App\Traits\AppliesQueryOptions;

    protected string $responseDtoClass = \App\DTO\Response\ApiKeys\ApiKeyResponseDTO::class;

    public function __construct(
        protected ApiKeyModel $apiKeyModel
    ) {
        $this->model = $apiKeyModel;
    }

    /**
     * Create a new API key
     */
    public function store(DataTransferObjectInterface $request): DataTransferObjectInterface
    {
        return $this->wrapInTransaction(function () use ($request) {
            $data = $request->toArray();

            // Generate secure random key
            $rawKey    = 'apk_' . bin2hex(random_bytes(24));   // 52 chars total
            $keyPrefix = substr($rawKey, 0, 12);
            $keyHash   = hash('sha256', $rawKey);

            $insertData = [
                'name'                => $data['name'],
                'key_prefix'          => $keyPrefix,
                'key_hash'            => $keyHash,
                'is_active'           => 1,
                'rate_limit_requests' => $data['rate_limit_requests'] ?? (int) env('API_KEY_RATE_LIMIT_DEFAULT', 600),
                'rate_limit_window'   => $data['rate_limit_window'] ?? (int) env('API_KEY_WINDOW_DEFAULT', 60),
                'user_rate_limit'     => $data['user_rate_limit'] ?? (int) env('API_KEY_USER_RATE_LIMIT_DEFAULT', 60),
                'ip_rate_limit'       => $data['ip_rate_limit'] ?? (int) env('API_KEY_IP_RATE_LIMIT_DEFAULT', 200),
            ];

            $newId = $this->model->insert($insertData);

            if (!$newId) {
                throw new ValidationException(lang('Api.validationFailed'), $this->model->errors());
            }

            /** @var object $apiKey */
            $apiKey = $this->model->find($newId);
            $responseData = (array) $apiKey->toArray();

            // Include raw key only in the creation response
            $responseData['key'] = $rawKey;

            return \App\DTO\Response\ApiKeys\ApiKeyResponseDTO::fromArray($responseData);
        });
    }

    /**
     * Update an existing API key
     */
    public function update(int $id, DataTransferObjectInterface $request): DataTransferObjectInterface
    {
        return $this->wrapInTransaction(function () use ($id, $request) {
            /** @var object|null $apiKey */
            $apiKey = $this->model->find($id);
            if (!$apiKey) {
                throw new \App\Exceptions\NotFoundException(lang('ApiKeys.notFound'));
            }

            $updateData = $request->toArray();

            if (empty($updateData)) {
                throw new BadRequestException(lang('Api.invalidRequest'), ['fields' => lang('ApiKeys.fieldRequired')]);
            }

            if (!$this->model->update($id, $updateData)) {
                throw new ValidationException(lang('Api.validationFailed'), $this->model->errors());
            }

            /** @var object $updatedApiKey */
            $updatedApiKey = $this->model->find($id);
            return $this->mapToResponse($updatedApiKey);
        });
    }

}
