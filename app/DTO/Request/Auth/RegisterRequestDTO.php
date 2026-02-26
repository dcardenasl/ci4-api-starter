<?php

declare(strict_types=1);

namespace App\DTO\Request\Auth;

use App\Interfaces\DataTransferObjectInterface;

/**
 * Register Request DTO
 *
 * Validates and encapsulates user registration data.
 */
readonly class RegisterRequestDTO implements DataTransferObjectInterface
{
    public string $email;
    public string $password;
    public string $firstName;
    public string $lastName;
    public ?string $role;

    public function __construct(array $data)
    {
        // REUTILIZACIÓN: Usa tus reglas actuales en Config/Validation.php
        validateOrFail($data, 'auth', 'register');

        $this->email = (string) $data['email'];
        $this->password = (string) $data['password'];
        $this->firstName = (string) ($data['first_name'] ?? '');
        $this->lastName = (string) ($data['last_name'] ?? '');
        $this->role = $data['role'] ?? 'user';
    }

    public function toArray(): array
    {
        return [
            'email' => $this->email,
            'password' => $this->password,
            'first_name' => $this->firstName,
            'last_name' => $this->lastName,
            'role' => $this->role,
        ];
    }
}
