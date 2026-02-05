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
            'username.required'        => lang('InputValidation.common.usernameRequired'),
            'username.alpha_numeric'   => lang('InputValidation.common.usernameAlphaNumeric'),
            'username.min_length'      => lang('InputValidation.common.usernameMinLength'),
            'username.max_length'      => lang('InputValidation.common.usernameMaxLength'),
            'email.required'           => lang('InputValidation.common.emailRequired'),
            'email.valid_email_idn'    => lang('InputValidation.common.emailInvalid'),
            'email.max_length'         => lang('InputValidation.common.emailMaxLength'),
            'password.required'        => lang('InputValidation.common.passwordRequired'),
            'password.strong_password' => lang('InputValidation.common.passwordStrength'),
            'role.in_list'             => lang('InputValidation.common.roleInvalid'),
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
