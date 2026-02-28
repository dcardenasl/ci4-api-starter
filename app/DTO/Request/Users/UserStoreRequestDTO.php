<?php

declare(strict_types=1);

namespace App\DTO\Request\Users;

use App\DTO\Request\BaseRequestDTO;

/**
 * User Store Request DTO
 *
 * Validates data for creating a new user.
 */
readonly class UserStoreRequestDTO extends BaseRequestDTO
{
    public string $email;
    public ?string $firstName;
    public ?string $lastName;
    public ?string $role;
    public ?string $oauthProvider;
    public ?string $oauthProviderId;
    public ?string $avatarUrl;

    protected function rules(): array
    {
        return [
            'email'     => 'required|valid_email_idn|max_length[255]',
            'firstName' => 'permit_empty|string|max_length[100]',
            'lastName'  => 'permit_empty|string|max_length[100]',
            'role'      => 'permit_empty|in_list[user,admin,superadmin]',
            'password'  => 'permit_empty|max_length[0]', // Ensure password is not provided
            'oauthProvider' => 'permit_empty|in_list[google,github]',
            'oauthProviderId' => 'permit_empty|string|max_length[255]',
            'avatarUrl' => 'permit_empty|valid_url|max_length[255]',
        ];
    }

    protected function map(array $data): void
    {
        $this->email = (string) $data['email'];
        $this->firstName = $data['firstName'] ?? null;
        $this->lastName = $data['lastName'] ?? null;
        $this->role = $data['role'] ?? 'user';
        $this->oauthProvider = $data['oauthProvider'] ?? null;
        $this->oauthProviderId = $data['oauthProviderId'] ?? null;
        $this->avatarUrl = $data['avatarUrl'] ?? null;
    }

    public function toArray(): array
    {
        return [
            'email'     => $this->email,
            'firstName' => $this->firstName,
            'lastName'  => $this->lastName,
            'role'      => $this->role,
            'oauthProvider' => $this->oauthProvider,
            'oauthProviderId' => $this->oauthProviderId,
            'avatarUrl' => $this->avatarUrl,
        ];
    }
}
