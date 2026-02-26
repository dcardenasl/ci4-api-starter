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
            'page'     => 'permit_empty|is_natural_no_zero',
            'per_page' => 'permit_empty|is_natural_no_zero|less_than[101]',
        ];
    }

    protected function map(array $data): void
    {
        if (!isset($data['user_id']) || !is_numeric($data['user_id'])) {
            throw new AuthenticationException(lang('Auth.unauthorized'));
        }

        $this->userId = (int) $data['user_id'];
        $this->page = isset($data['page']) ? (int) $data['page'] : 1;
        $this->perPage = isset($data['per_page']) ? (int) $data['per_page'] : 20;
    }

    public function toArray(): array
    {
        return [
            'page'     => $this->page,
            'per_page' => $this->perPage,
            'user_id'  => $this->userId,
        ];
    }
}
