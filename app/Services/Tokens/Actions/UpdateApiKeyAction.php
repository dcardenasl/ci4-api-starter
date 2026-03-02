<?php

declare(strict_types=1);

namespace App\Services\Tokens\Actions;

use App\DTO\Request\ApiKeys\ApiKeyUpdateRequestDTO;
use App\Exceptions\BadRequestException;
use App\Exceptions\NotFoundException;
use App\Exceptions\ValidationException;
use App\Models\ApiKeyModel;

class UpdateApiKeyAction
{
    public function __construct(
        protected ApiKeyModel $apiKeyModel
    ) {
    }

    public function execute(int $id, ApiKeyUpdateRequestDTO $request): \App\Entities\ApiKeyEntity
    {
        /** @var \App\Entities\ApiKeyEntity|null $existing */
        $existing = $this->apiKeyModel->find($id);
        if ($existing === null) {
            throw new NotFoundException(lang('Api.resourceNotFound'));
        }

        $data = array_filter([
            'name' => $request->name,
            'is_active' => $request->isActive,
            'rate_limit_requests' => $request->rateLimitRequests,
            'rate_limit_window' => $request->rateLimitWindow,
            'user_rate_limit' => $request->userRateLimit,
            'ip_rate_limit' => $request->ipRateLimit,
        ], fn ($value) => $value !== null);

        if ($data === []) {
            throw new BadRequestException(lang('Api.noFieldsToUpdate'));
        }

        if (!$this->apiKeyModel->update($id, $data)) {
            throw new ValidationException(lang('Api.validationFailed'), $this->apiKeyModel->errors());
        }

        /** @var \App\Entities\ApiKeyEntity|null $updated */
        $updated = $this->apiKeyModel->find($id);
        if ($updated === null) {
            throw new NotFoundException(lang('Api.resourceNotFound'));
        }

        return $updated;
    }
}
