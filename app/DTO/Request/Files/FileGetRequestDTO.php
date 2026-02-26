<?php

declare(strict_types=1);

namespace App\DTO\Request\Files;

use App\DTO\Request\BaseRequestDTO;
use App\Exceptions\AuthenticationException;

/**
 * File Get Request DTO
 *
 * Validates the request to fetch or delete a file, enforcing user ownership check.
 */
readonly class FileGetRequestDTO extends BaseRequestDTO
{
    public int $id;
    public int $userId;

    protected function rules(): array
    {
        return [
            'id' => 'required|is_natural_no_zero',
        ];
    }

    protected function map(array $data): void
    {
        if (!isset($data['user_id']) || !is_numeric($data['user_id'])) {
            throw new AuthenticationException(lang('Auth.unauthorized'));
        }

        $this->id = (int) $data['id'];
        $this->userId = (int) $data['user_id'];
    }

    public function toArray(): array
    {
        return [
            'id'      => $this->id,
            'user_id' => $this->userId,
        ];
    }
}
