<?php

declare(strict_types=1);

namespace App\DTO\Request\Users;

use App\DTO\Request\BaseRequestDTO;

/**
 * User Store Request DTO
 *
 * Validates data for creating a new user.
 */
readonly class UserCreateRequestDTO extends BaseRequestDTO
{
    public string $email;
    public ?string $first_name;
    public ?string $last_name;
    public ?string $role;
    public ?string $oauth_provider;
    public ?string $oauth_provider_id;
    public ?string $avatar_url;

    protected function rules(): array
    {
        return [
            'email'     => 'required|valid_email_idn|max_length[255]',
            'first_name' => 'permit_empty|string|max_length[100]',
            'last_name'  => 'permit_empty|string|max_length[100]',
            'role'      => 'permit_empty|in_list[user,admin,superadmin]',
            'password'  => 'permit_empty|max_length[0]', // Ensure password is not provided
            'oauth_provider' => 'permit_empty|in_list[google,github]',
            'oauth_provider_id' => 'permit_empty|string|max_length[255]',
            'avatar_url' => 'permit_empty|valid_url|max_length[255]',
        ];
    }

    protected function map(array $data): void
    {
        $this->email = (string) $data['email'];
        $this->first_name = $data['first_name'] ?? null;
        $this->last_name = $data['last_name'] ?? null;
        $this->role = $data['role'] ?? 'user';
        $this->oauth_provider = $data['oauth_provider'] ?? null;
        $this->oauth_provider_id = $data['oauth_provider_id'] ?? null;
        $this->avatar_url = $data['avatar_url'] ?? null;
    }

    public function toArray(): array
    {
        return [
            'email'     => $this->email,
            'first_name' => $this->first_name,
            'last_name'  => $this->last_name,
            'role'      => $this->role,
            'oauth_provider' => $this->oauth_provider,
            'oauth_provider_id' => $this->oauth_provider_id,
            'avatar_url' => $this->avatar_url,
        ];
    }
}
