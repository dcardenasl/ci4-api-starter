<?php

declare(strict_types=1);

namespace App\Services\Tokens\Actions;

use App\DTO\Request\ApiKeys\ApiKeyCreateRequestDTO;
use App\Exceptions\ValidationException;
use App\Models\ApiKeyModel;

class CreateApiKeyAction
{
    public function __construct(
        protected ApiKeyModel $apiKeyModel
    ) {
    }

    public function execute(ApiKeyCreateRequestDTO $request): array
    {
        helper('security');

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

        $id = $this->apiKeyModel->insert($data);

        if (!$id) {
            throw new ValidationException(lang('Api.validationFailed'), $this->apiKeyModel->errors());
        }

        /** @var \App\Entities\ApiKeyEntity|null $apiKey */
        $apiKey = $this->apiKeyModel->find($id);
        if ($apiKey === null) {
            throw new ValidationException(lang('Api.validationFailed'), ['apiKey' => lang('Api.resourceNotFound')]);
        }

        return [
            'entity' => $apiKey,
            'key' => $rawKey,
        ];
    }
}
