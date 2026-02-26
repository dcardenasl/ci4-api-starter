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
    public ?int $actorId;
    public ?string $actorRole;

    protected function rules(): array
    {
        return [
            'email'      => 'required|valid_email_idn|max_length[255]',
            'first_name' => 'permit_empty|string|max_length[100]',
            'last_name'  => 'permit_empty|string|max_length[100]',
            'role'       => 'permit_empty|in_list[user,admin,superadmin]',
            'password'   => 'permit_empty|max_length[0]', // Ensure password is not provided
            'oauth_provider' => 'permit_empty|in_list[google,github]',
            'oauth_provider_id' => 'permit_empty|string|max_length[255]',
            'avatar_url' => 'permit_empty|valid_url|max_length[255]',
        ];
    }

    protected function map(array $data): void
    {
        $this->email = (string) $data['email'];
        $this->firstName = $data['first_name'] ?? null;
        $this->lastName = $data['last_name'] ?? null;
        $this->role = $data['role'] ?? 'user';
        $this->oauthProvider = $data['oauth_provider'] ?? null;
        $this->oauthProviderId = $data['oauth_provider_id'] ?? null;
        $this->avatarUrl = $data['avatar_url'] ?? null;

        // Context information from the controller/auth
        $this->actorId = isset($data['user_id']) ? (int) $data['user_id'] : null;
        $this->actorRole = $data['user_role'] ?? null;
    }

    public function toArray(): array
    {
        return [
            'email'      => $this->email,
            'first_name' => $this->firstName,
            'last_name'  => $this->lastName,
            'role'       => $this->role,
            'oauth_provider' => $this->oauthProvider,
            'oauth_provider_id' => $this->oauthProviderId,
            'avatar_url' => $this->avatarUrl,
            'user_id'    => $this->actorId,
            'user_role'  => $this->actorRole,
        ];
    }
}
