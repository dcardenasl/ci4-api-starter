<?php

declare(strict_types=1);

namespace App\DTO\Request\Users;

use App\DTO\Request\BaseRequestDTO;

/**
 * User Update Request DTO
 *
 * Validates data for updating an existing user.
 */
readonly class UserUpdateRequestDTO extends BaseRequestDTO
{
    public ?string $email;
    public ?string $first_name;
    public ?string $last_name;
    public ?string $password;
    public ?string $role;

    protected function rules(): array
    {
        return [
            'email'     => 'permit_empty|valid_email_idn|max_length[255]',
            'first_name' => 'permit_empty|string|max_length[100]',
            'last_name'  => 'permit_empty|string|max_length[100]',
            'password'  => 'permit_empty|strong_password',
            'role'      => 'permit_empty|in_list[user,admin,superadmin]',
        ];
    }

    protected function map(array $data): void
    {
        $this->email = isset($data['email']) ? strtolower(trim((string) $data['email'])) : null;
        $this->first_name = $data['first_name'] ?? null;
        $this->last_name = $data['last_name'] ?? null;
        $this->password = $data['password'] ?? null;
        $this->role = $data['role'] ?? null;
    }

    public function toArray(): array
    {
        return array_filter([
            'email'     => $this->email,
            'first_name' => $this->first_name,
            'last_name'  => $this->last_name,
            'password'  => $this->password,
            'role'      => $this->role,
        ], fn ($v) => $v !== null);
    }
}
