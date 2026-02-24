<?php

declare(strict_types=1);

namespace App\Services;

use App\Exceptions\BadRequestException;
use App\Exceptions\NotFoundException;
use App\Exceptions\ValidationException;
use App\Interfaces\ApiKeyServiceInterface;
use App\Libraries\ApiResponse;
use App\Libraries\Query\QueryBuilder;
use App\Models\ApiKeyModel;
use App\Traits\AppliesQueryOptions;

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
class ApiKeyService extends BaseCrudService implements ApiKeyServiceInterface
{
    use AppliesQueryOptions;

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

        $result['data'] = array_map(
            fn ($key) => $key->toArray(),
            $result['data']
        );

        return ApiResponse::paginated(
            $result['data'],
            $result['total'],
            $result['page'],
            $result['perPage']
        );
    }

    /**
     * Get a single API key by ID
     */
    public function show(array $data): array
    {
        $id = $this->requireId($data);

        $apiKey = $this->apiKeyModel->find($id);

        if (!$apiKey) {
            throw new NotFoundException(lang('ApiKeys.notFound'));
        }

        return ApiResponse::success($apiKey->toArray());
    }

    /**
     * Create a new API key
     *
     * The raw key is returned only once in the response. It is never
     * stored in the database; only the prefix and SHA-256 hash are persisted.
     */
    public function store(array $data): array
    {
        if (empty($data['name'])) {
            throw new ValidationException(
                lang('Api.validationFailed'),
                ['name' => lang('ApiKeys.nameRequired')]
            );
        }

        // Generate secure random key
        $rawKey    = 'apk_' . bin2hex(random_bytes(24));   // 52 chars total
        $keyPrefix = substr($rawKey, 0, 12);
        $keyHash   = hash('sha256', $rawKey);

        $insertData = [
            'name'                => trim($data['name']),
            'key_prefix'          => $keyPrefix,
            'key_hash'            => $keyHash,
            'is_active'           => 1,
            'rate_limit_requests' => isset($data['rate_limit_requests'])
                ? (int) $data['rate_limit_requests']
                : (int) env('API_KEY_RATE_LIMIT_DEFAULT', 600),
            'rate_limit_window'   => isset($data['rate_limit_window'])
                ? (int) $data['rate_limit_window']
                : (int) env('API_KEY_WINDOW_DEFAULT', 60),
            'user_rate_limit'     => isset($data['user_rate_limit'])
                ? (int) $data['user_rate_limit']
                : (int) env('API_KEY_USER_RATE_LIMIT_DEFAULT', 60),
            'ip_rate_limit'       => isset($data['ip_rate_limit'])
                ? (int) $data['ip_rate_limit']
                : (int) env('API_KEY_IP_RATE_LIMIT_DEFAULT', 200),
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

        return ApiResponse::created(
            $responseData,
            lang('ApiKeys.createdSuccess')
        );
    }

    /**
     * Update an existing API key (name, active status, rate limits)
     */
    public function update(array $data): array
    {
        $id = $this->requireId($data);

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

        return ApiResponse::success($updated->toArray());
    }

    /**
     * Delete an API key permanently (hard delete)
     */
    public function destroy(array $data): array
    {
        $id = $this->requireId($data);

        if (!$this->apiKeyModel->find($id)) {
            throw new NotFoundException(lang('ApiKeys.notFound'));
        }

        if (!$this->apiKeyModel->delete($id)) {
            throw new \RuntimeException(lang('ApiKeys.deleteError'));
        }

        return ApiResponse::deleted(lang('ApiKeys.deletedSuccess'));
    }
}
