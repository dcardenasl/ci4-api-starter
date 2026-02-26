<?php

declare(strict_types=1);

namespace App\DTO\Request\Auth;

use App\Interfaces\DataTransferObjectInterface;

/**
 * Login Request DTO
 *
 * Validates and encapsulates login credentials.
 */
readonly class LoginRequestDTO implements DataTransferObjectInterface
{
    public string $email;
    public string $password;
    public ?int $userId;
    public ?string $userRole;

    public function __construct(array $data)
    {
        // Reuse existing validation logic
        validateOrFail($data, 'auth', 'login');

        $this->email = (string) $data['email'];
        $this->password = (string) $data['password'];

        // Context data (optional)
        $this->userId = isset($data['user_id']) ? (int) $data['user_id'] : null;
        $this->userRole = $data['user_role'] ?? null;
    }

    public function toArray(): array
    {
        return [
            'email' => $this->email,
            'password' => $this->password,
            'user_id' => $this->userId,
            'user_role' => $this->userRole,
        ];
    }
}
