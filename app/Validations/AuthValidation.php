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
                'username.required'   => lang('InputValidation.auth.usernameOrEmailRequired'),
                'username.max_length' => lang('InputValidation.common.usernameTooLong'),
                'password.required'   => lang('InputValidation.common.passwordRequired'),
            ],

            'register' => [
                'username.required'        => lang('InputValidation.common.usernameRequired'),
                'username.alpha_numeric'   => lang('InputValidation.common.usernameAlphaNumeric'),
                'username.min_length'      => lang('InputValidation.common.usernameMinLength'),
                'username.max_length'      => lang('InputValidation.common.usernameMaxLength'),
                'email.required'           => lang('InputValidation.common.emailRequired'),
                'email.valid_email_idn'    => lang('InputValidation.common.emailInvalid'),
                'email.max_length'         => lang('InputValidation.common.emailMaxLength'),
                'password.required'        => lang('InputValidation.common.passwordRequired'),
                'password.strong_password' => lang('InputValidation.common.passwordStrength'),
            ],

            'forgot_password' => [
                'email.required'        => lang('InputValidation.common.emailRequired'),
                'email.valid_email_idn' => lang('InputValidation.common.emailInvalid'),
            ],

            'reset_password' => [
                'token.required'           => lang('InputValidation.auth.resetTokenRequired'),
                'token.valid_token'        => lang('InputValidation.auth.resetTokenInvalid'),
                'email.required'           => lang('InputValidation.common.emailRequired'),
                'email.valid_email_idn'    => lang('InputValidation.common.emailInvalid'),
                'password.required'        => lang('InputValidation.common.newPasswordRequired'),
                'password.strong_password' => lang('InputValidation.common.passwordStrength'),
            ],

            'verify_email' => [
                'token.required'        => lang('InputValidation.auth.verificationTokenRequired'),
                'token.valid_token'     => lang('InputValidation.auth.verificationTokenInvalid'),
                'email.required'        => lang('InputValidation.common.emailRequired'),
                'email.valid_email_idn' => lang('InputValidation.common.emailInvalid'),
            ],

            'refresh' => [
                'refresh_token.required'   => lang('InputValidation.auth.refreshTokenRequired'),
                'refresh_token.min_length' => lang('InputValidation.auth.refreshTokenInvalid'),
            ],

            default => [],
        };
    }
}
