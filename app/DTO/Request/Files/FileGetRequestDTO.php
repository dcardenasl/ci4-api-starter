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
        $userId = $data['userId'] ?? $data['user_id'] ?? null;
        if ($userId === null || !is_numeric($userId)) {
            throw new AuthenticationException(lang('Auth.unauthorized'));
        }

        $this->id = (int) $data['id'];
        $this->userId = (int) $userId;
    }

    public function toArray(): array
    {
        return [
            'id'     => $this->id,
            'userId' => $this->userId,
        ];
    }
}
