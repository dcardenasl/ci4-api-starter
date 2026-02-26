<?php

declare(strict_types=1);

namespace App\DTO\Request\Auth;

use App\DTO\Request\BaseRequestDTO;

/**
 * Register Request DTO
 *
 * Validates data for user self-registration.
 */
readonly class RegisterRequestDTO extends BaseRequestDTO
{
    public string $email;
    public string $firstName;
    public string $lastName;
    public string $password;

    protected function rules(): array
    {
        return [
            'email'      => 'required|valid_email_idn|max_length[255]|is_unique[users.email]',
            'first_name' => 'required|string|max_length[100]',
            'last_name'  => 'required|string|max_length[100]',
            'password'   => 'required|strong_password',
        ];
    }

    protected function map(array $data): void
    {
        $this->email = strtolower(trim((string) $data['email']));
        $this->firstName = trim((string) $data['first_name']);
        $this->lastName = trim((string) $data['last_name']);
        $this->password = (string) $data['password'];
    }

    public function toArray(): array
    {
        return [
            'email'      => $this->email,
            'first_name' => $this->firstName,
            'last_name'  => $this->lastName,
            'password'   => $this->password,
        ];
    }
}
