<?php

declare(strict_types=1);

namespace App\DTO\Request\Files;

use App\DTO\Request\BaseRequestDTO;
use App\Exceptions\AuthenticationException;

/**
 * File Index Request DTO
 *
 * Validates pagination and ensures user_id is present for security.
 */
readonly class FileIndexRequestDTO extends BaseRequestDTO
{
    public int $page;
    public int $perPage;
    public int $userId;

    protected function rules(): array
    {
        return [
            'page'    => 'permit_empty|is_natural_no_zero',
            'perPage' => 'permit_empty|is_natural_no_zero|less_than[101]',
        ];
    }

    protected function map(array $data): void
    {
        $userId = $data['userId'] ?? $data['user_id'] ?? null;
        if ($userId === null || !is_numeric($userId)) {
            throw new AuthenticationException(lang('Auth.unauthorized'));
        }

        $this->userId = (int) $userId;
        $this->page = isset($data['page']) ? (int) $data['page'] : 1;
        $this->perPage = isset($data['perPage']) ? (int) $data['perPage'] : 20;
    }

    public function toArray(): array
    {
        return [
            'page'    => $this->page,
            'perPage' => $this->perPage,
            'userId'  => $this->userId,
        ];
    }
}
