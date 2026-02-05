<?php

declare(strict_types=1);

namespace App\Validations;

/**
 * User Validation
 *
 * Validation rules for user management actions.
 * Note: is_unique validation is handled by the Model layer.
 */
class UserValidation extends BaseValidation
{
    /**
     * {@inheritDoc}
     */
    public function getRules(string $action): array
    {
        return match ($action) {
            'index' => $this->paginationRules(),

            'show' => $this->idRules(),

            'store' => [
                'username' => 'required|alpha_numeric|min_length[3]|max_length[100]',
                'email'    => 'required|valid_email_idn|max_length[255]',
                'password' => 'required|strong_password',
                'role'     => 'permit_empty|in_list[user,admin]',
            ],

            'update' => $this->mergeRules(
                $this->idRules(),
                [
                    'username' => 'permit_empty|alpha_numeric|min_length[3]|max_length[100]',
                    'email'    => 'permit_empty|valid_email_idn|max_length[255]',
                    'password' => 'permit_empty|strong_password',
                    'role'     => 'permit_empty|in_list[user,admin]',
                ]
            ),

            'destroy' => $this->idRules(),

            default => [],
        };
    }

    /**
     * {@inheritDoc}
     */
    public function getMessages(string $action): array
    {
        $commonMessages = [
            'username.required'        => 'Username is required',
            'username.alpha_numeric'   => 'Username can only contain letters and numbers',
            'username.min_length'      => 'Username must be at least 3 characters',
            'username.max_length'      => 'Username cannot exceed 100 characters',
            'email.required'           => 'Email is required',
            'email.valid_email_idn'    => 'Please provide a valid email address',
            'email.max_length'         => 'Email cannot exceed 255 characters',
            'password.required'        => 'Password is required',
            'password.strong_password' => 'Password must be 8-128 characters with uppercase, lowercase, number, and special character',
            'role.in_list'             => 'Role must be either user or admin',
        ];

        return match ($action) {
            'index' => $this->paginationMessages(),
            'show', 'destroy' => $this->idMessages(),
            'store' => $commonMessages,
            'update' => $this->mergeMessages($this->idMessages(), $commonMessages),
            default => [],
        };
    }
}
