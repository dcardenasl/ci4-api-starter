<?php

declare(strict_types=1);

namespace App\DTO\Request\Auth;

use App\DTO\Request\BaseRequestDTO;

/**
 * Login Request DTO
 *
 * Validates credentials for user authentication.
 */
readonly class LoginRequestDTO extends BaseRequestDTO
{
    public string $email;
    public string $password;

    protected function rules(): array
    {
        return [
            'email'    => 'required|valid_email|max_length[255]',
            'password' => 'required|string',
        ];
    }

    protected function map(array $data): void
    {
        $this->email = strtolower(trim((string) $data['email']));
        $this->password = (string) $data['password'];
    }

    public function toArray(): array
    {
        return [
            'email'    => $this->email,
            'password' => $this->password,
        ];
    }
}
