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
                'email'      => 'required|valid_email_idn|max_length[255]',
                'first_name' => 'permit_empty|string|max_length[100]',
                'last_name'  => 'permit_empty|string|max_length[100]',
                'password'   => 'permit_empty|strong_password',
                'role'       => 'permit_empty|in_list[user,admin]',
                'oauth_provider' => 'permit_empty|in_list[google,github]',
                'oauth_provider_id' => 'permit_empty|string|max_length[255]',
                'avatar_url' => 'permit_empty|valid_url|max_length[255]',
            ],

            'update' => $this->mergeRules(
                $this->idRules(),
                [
                    'email'      => 'permit_empty|valid_email_idn|max_length[255]',
                    'first_name' => 'permit_empty|string|max_length[100]',
                    'last_name'  => 'permit_empty|string|max_length[100]',
                    'password'   => 'permit_empty|strong_password',
                    'role'       => 'permit_empty|in_list[user,admin]',
                    'oauth_provider' => 'permit_empty|in_list[google,github]',
                    'oauth_provider_id' => 'permit_empty|string|max_length[255]',
                    'avatar_url' => 'permit_empty|valid_url|max_length[255]',
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
            'email.required'           => lang('InputValidation.common.emailRequired'),
            'email.valid_email_idn'    => lang('InputValidation.common.emailInvalid'),
            'email.max_length'         => lang('InputValidation.common.emailMaxLength'),
            'first_name.max_length'    => lang('InputValidation.common.firstNameMaxLength'),
            'last_name.max_length'     => lang('InputValidation.common.lastNameMaxLength'),
            'password.required'        => lang('InputValidation.common.passwordRequired'),
            'password.strong_password' => lang('InputValidation.common.passwordStrength'),
            'role.in_list'             => lang('InputValidation.common.roleInvalid'),
            'oauth_provider.in_list'   => lang('InputValidation.common.oauthProviderInvalid'),
            'oauth_provider_id.max_length' => lang('InputValidation.common.oauthProviderIdMaxLength'),
            'avatar_url.valid_url'     => lang('InputValidation.common.avatarUrlInvalid'),
            'avatar_url.max_length'    => lang('InputValidation.common.avatarUrlMaxLength'),
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
