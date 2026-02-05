<?php

declare(strict_types=1);

namespace App\Validations;

/**
 * Auth Validation
 *
 * Validation rules for authentication-related actions.
 */
class AuthValidation extends BaseValidation
{
    /**
     * {@inheritDoc}
     */
    public function getRules(string $action): array
    {
        return match ($action) {
            'login' => [
                'username' => 'required|string|max_length[255]',
                'password' => 'required|string',
            ],

            'register' => [
                'username' => 'required|alpha_numeric|min_length[3]|max_length[100]',
                'email'    => 'required|valid_email_idn|max_length[255]',
                'password' => 'required|strong_password',
            ],

            'forgot_password' => [
                'email' => 'required|valid_email_idn|max_length[255]',
            ],

            'reset_password' => [
                'token'    => 'required|valid_token[64]',
                'email'    => 'required|valid_email_idn|max_length[255]',
                'password' => 'required|strong_password',
            ],

            'verify_email' => [
                'token' => 'required|valid_token[64]',
                'email' => 'required|valid_email_idn|max_length[255]',
            ],

            'refresh' => [
                'refresh_token' => 'required|string|min_length[10]',
            ],

            default => [],
        };
    }

    /**
     * {@inheritDoc}
     */
    public function getMessages(string $action): array
    {
        return match ($action) {
            'login' => [
                'username.required'   => 'Username or email is required',
                'username.max_length' => 'Username is too long',
                'password.required'   => 'Password is required',
            ],

            'register' => [
                'username.required'      => 'Username is required',
                'username.alpha_numeric' => 'Username can only contain letters and numbers',
                'username.min_length'    => 'Username must be at least 3 characters',
                'username.max_length'    => 'Username cannot exceed 100 characters',
                'email.required'         => 'Email is required',
                'email.valid_email_idn'  => 'Please provide a valid email address',
                'email.max_length'       => 'Email cannot exceed 255 characters',
                'password.required'      => 'Password is required',
                'password.strong_password' => 'Password must be 8-128 characters with uppercase, lowercase, number, and special character',
            ],

            'forgot_password' => [
                'email.required'        => 'Email is required',
                'email.valid_email_idn' => 'Please provide a valid email address',
            ],

            'reset_password' => [
                'token.required'           => 'Reset token is required',
                'token.valid_token'        => 'Invalid reset token format',
                'email.required'           => 'Email is required',
                'email.valid_email_idn'    => 'Please provide a valid email address',
                'password.required'        => 'New password is required',
                'password.strong_password' => 'Password must be 8-128 characters with uppercase, lowercase, number, and special character',
            ],

            'verify_email' => [
                'token.required'        => 'Verification token is required',
                'token.valid_token'     => 'Invalid verification token format',
                'email.required'        => 'Email is required',
                'email.valid_email_idn' => 'Please provide a valid email address',
            ],

            'refresh' => [
                'refresh_token.required'   => 'Refresh token is required',
                'refresh_token.min_length' => 'Invalid refresh token',
            ],

            default => [],
        };
    }
}
