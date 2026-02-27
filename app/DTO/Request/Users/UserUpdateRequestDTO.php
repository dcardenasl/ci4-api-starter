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
            'email'     => 'permit_empty|valid_email_idn|max_length[255]',
            'firstName' => 'permit_empty|string|max_length[100]',
            'lastName'  => 'permit_empty|string|max_length[100]',
            'password'  => 'permit_empty|strong_password',
            'role'      => 'permit_empty|in_list[user,admin,superadmin]',
        ];
    }

    protected function map(array $data): void
    {
        $this->email = isset($data['email']) ? strtolower(trim((string) $data['email'])) : null;
        $this->firstName = $data['firstName'] ?? null;
        $this->lastName = $data['lastName'] ?? null;
        $this->password = $data['password'] ?? null;
        $this->role = $data['role'] ?? null;

        $this->actorId = isset($data['userId']) ? (int) $data['userId'] : null;
        $this->actorRole = $data['userRole'] ?? null;
    }

    public function toArray(): array
    {
        return array_filter([
            'email'     => $this->email,
            'firstName' => $this->firstName,
            'lastName'  => $this->lastName,
            'password'  => $this->password,
            'role'      => $this->role,
            'userId'    => $this->actorId,
            'userRole'  => $this->actorRole,
        ], fn ($v) => $v !== null);
    }
}
