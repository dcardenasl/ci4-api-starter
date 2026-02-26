<?php

declare(strict_types=1);

namespace App\Services;

use App\Exceptions\BadRequestException;
use App\Exceptions\NotFoundException;
use App\Exceptions\ValidationException;
use App\Interfaces\ApiKeyServiceInterface;
use App\Libraries\Query\QueryBuilder;
use App\Models\ApiKeyModel;
use App\Traits\AppliesQueryOptions;
use App\Traits\ValidatesRequiredFields;

/**
 * API Key Service
 *
 * Handles CRUD operations for API keys.
 *
 * Key generation strategy:
 *   - Raw key: 'apk_' . bin2hex(random_bytes(24))  → 52 chars total
 *   - key_prefix: first 12 chars of raw key (human-readable identifier)
 *   - key_hash: SHA-256 of raw key (stored for lookup)
 *   - The raw key is returned ONCE at creation time and never stored.
 */
class ApiKeyService implements ApiKeyServiceInterface
{
    use AppliesQueryOptions;
    use ValidatesRequiredFields;

    public function __construct(
        protected ApiKeyModel $apiKeyModel
    ) {
    }

    /**
     * List all API keys with pagination, filters, search and sorting
     */
    public function index(array $data): array
    {
        $builder = new QueryBuilder($this->apiKeyModel);

        $this->applyQueryOptions($builder, $data);

        [$page, $limit] = $this->resolvePagination(
            $data,
            (int) env('PAGINATION_DEFAULT_LIMIT', 20)
        );

        $result = $builder->paginate($page, $limit);

        // Convert to Response DTOs
        $result['data'] = array_map(
            fn ($key) => \App\DTO\Response\ApiKeys\ApiKeyResponseDTO::fromArray($key->toArray()),
            $result['data']
        );

        return [
            'data' => $result['data'],
            'total' => $result['total'],
            'page' => $result['page'],
            'perPage' => $result['perPage']
        ];
    }

    /**
     * Get a single API key by ID
     */
    public function show(array $data): \App\DTO\Response\ApiKeys\ApiKeyResponseDTO
    {
        $id = $this->validateRequiredId($data);

        $apiKey = $this->apiKeyModel->find($id);

        if (!$apiKey) {
            throw new NotFoundException(lang('ApiKeys.notFound'));
        }

        return \App\DTO\Response\ApiKeys\ApiKeyResponseDTO::fromArray($apiKey->toArray());
    }

    /**
     * Create a new API key
     */
    public function store(\App\DTO\Request\ApiKeys\ApiKeyCreateRequestDTO $request): \App\DTO\Response\ApiKeys\ApiKeyResponseDTO
    {
        $data = $request->toArray();

        // Generate secure random key
        $rawKey    = 'apk_' . bin2hex(random_bytes(24));   // 52 chars total
        $keyPrefix = substr($rawKey, 0, 12);
        $keyHash   = hash('sha256', $rawKey);

        $insertData = [
            'name'                => trim($data['name']),
            'key_prefix'          => $keyPrefix,
            'key_hash'            => $keyHash,
            'is_active'           => 1,
            'rate_limit_requests' => $data['rate_limit_requests'] ?? (int) env('API_KEY_RATE_LIMIT_DEFAULT', 600),
            'rate_limit_window'   => $data['rate_limit_window'] ?? (int) env('API_KEY_WINDOW_DEFAULT', 60),
            'user_rate_limit'     => $data['user_rate_limit'] ?? (int) env('API_KEY_USER_RATE_LIMIT_DEFAULT', 60),
            'ip_rate_limit'       => $data['ip_rate_limit'] ?? (int) env('API_KEY_IP_RATE_LIMIT_DEFAULT', 200),
        ];

        $newId = $this->apiKeyModel->insert($insertData);

        if (!$newId) {
            throw new ValidationException(
                lang('Api.validationFailed'),
                $this->apiKeyModel->errors() ?: ['general' => lang('ApiKeys.createError')]
            );
        }

        $apiKey = $this->apiKeyModel->find($newId);

        if (!$apiKey) {
            throw new \RuntimeException(lang('ApiKeys.retrieveError'));
        }

        $responseData = $apiKey->toArray();
        // Include raw key only in the creation response
        $responseData['key'] = $rawKey;

        return \App\DTO\Response\ApiKeys\ApiKeyResponseDTO::fromArray($responseData);
    }

    /**
     * Update an existing API key (name, active status, rate limits)
     */
    public function update(array $data): \App\DTO\Response\ApiKeys\ApiKeyResponseDTO
    {
        $id = $this->validateRequiredId($data);

        $apiKey = $this->apiKeyModel->find($id);

        if (!$apiKey) {
            throw new NotFoundException(lang('ApiKeys.notFound'));
        }

        $updateData = array_filter([
            'name'                => isset($data['name']) ? trim($data['name']) : null,
            'is_active'           => isset($data['is_active']) ? (int) (bool) $data['is_active'] : null,
            'rate_limit_requests' => isset($data['rate_limit_requests']) ? (int) $data['rate_limit_requests'] : null,
            'rate_limit_window'   => isset($data['rate_limit_window']) ? (int) $data['rate_limit_window'] : null,
            'user_rate_limit'     => isset($data['user_rate_limit']) ? (int) $data['user_rate_limit'] : null,
            'ip_rate_limit'       => isset($data['ip_rate_limit']) ? (int) $data['ip_rate_limit'] : null,
        ], fn ($value) => $value !== null);

        if (empty($updateData)) {
            throw new BadRequestException(
                lang('Api.invalidRequest'),
                ['fields' => lang('ApiKeys.fieldRequired')]
            );
        }

        $success = $this->apiKeyModel->update($id, $updateData);

        if (!$success) {
            throw new ValidationException(
                lang('Api.validationFailed'),
                $this->apiKeyModel->errors()
            );
        }

        $updated = $this->apiKeyModel->find($id);

        return \App\DTO\Response\ApiKeys\ApiKeyResponseDTO::fromArray($updated->toArray());
    }

    /**
     * Delete an API key permanently (hard delete)
     */
    public function destroy(array $data): array
    {
        $id = $this->validateRequiredId($data);

        if (!$this->apiKeyModel->find($id)) {
            throw new NotFoundException(lang('ApiKeys.notFound'));
        }

        if (!$this->apiKeyModel->delete($id)) {
            throw new \RuntimeException(lang('ApiKeys.deleteError'));
        }

        return ['status' => 'success', 'message' => lang('ApiKeys.deletedSuccess')];
    }
}
