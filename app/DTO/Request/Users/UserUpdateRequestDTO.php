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
    public ?string $firstName;
    public ?string $lastName;
    public ?string $password;
    public ?string $role;
    public ?int $actorId;
    public ?string $actorRole;

    protected function rules(): array
    {
        return [
            'email'      => 'permit_empty|valid_email_idn|max_length[255]',
            'first_name' => 'permit_empty|string|max_length[100]',
            'last_name'  => 'permit_empty|string|max_length[100]',
            'password'   => 'permit_empty|strong_password',
            'role'       => 'permit_empty|in_list[user,admin,superadmin]',
        ];
    }

    protected function map(array $data): void
    {
        $this->email = isset($data['email']) ? strtolower(trim((string) $data['email'])) : null;
        $this->firstName = $data['first_name'] ?? null;
        $this->lastName = $data['last_name'] ?? null;
        $this->password = $data['password'] ?? null;
        $this->role = $data['role'] ?? null;

        $this->actorId = isset($data['user_id']) ? (int) $data['user_id'] : null;
        $this->actorRole = $data['user_role'] ?? null;
    }

    public function toArray(): array
    {
        return array_filter([
            'email'      => $this->email,
            'first_name' => $this->firstName,
            'last_name'  => $this->lastName,
            'password'   => $this->password,
            'role'       => $this->role,
            'user_id'    => $this->actorId,
            'user_role'  => $this->actorRole,
        ], fn ($v) => $v !== null);
    }
}
