<?php

declare(strict_types=1);

namespace App\DTO\Request\Users;

use App\DTO\Request\BaseRequestDTO;
use OpenApi\Attributes as OA;

/**
 * User Store Request DTO
 *
 * Validates data for creating a new user.
 */
#[OA\Schema(
    schema: 'UserCreateRequest',
    title: 'User Create Request',
    description: 'Data needed to create a new user',
    required: ['email']
)]
readonly class UserCreateRequestDTO extends BaseRequestDTO
{
    #[OA\Property(description: 'Unique email address', example: 'user@example.com')]
    public string $email;

    #[OA\Property(description: 'User first name', example: 'John', nullable: true)]
    public ?string $first_name;

    #[OA\Property(description: 'User last name', example: 'Doe', nullable: true)]
    public ?string $last_name;

    #[OA\Property(description: 'Account role', enum: ['user', 'admin', 'superadmin'], example: 'user')]
    public ?string $role;

    #[OA\Property(description: 'OAuth provider name', enum: ['google', 'github'], nullable: true)]
    public ?string $oauth_provider;

    #[OA\Property(description: 'OAuth unique identifier from provider', nullable: true)]
    public ?string $oauth_provider_id;

    #[OA\Property(description: 'URL to user avatar image', example: 'https://example.com/avatar.jpg', nullable: true)]
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
