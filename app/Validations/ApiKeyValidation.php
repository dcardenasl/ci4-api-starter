<?php

declare(strict_types=1);

namespace App\Validations;

/**
 * ApiKey Validation Rules
 */
class ApiKeyValidation extends BaseValidation
{
    public function getRules(string $action): array
    {
        $rules = [
            'store' => [
                'name' => 'required|min_length[3]|max_length[100]',
                'rate_limit_requests' => 'permit_empty|is_natural_no_zero',
                'rate_limit_window' => 'permit_empty|is_natural_no_zero',
                'user_rate_limit' => 'permit_empty|is_natural_no_zero',
                'ip_rate_limit' => 'permit_empty|is_natural_no_zero',
            ],
            'update' => [
                'name' => 'permit_empty|min_length[3]|max_length[100]',
                'is_active' => 'permit_empty|in_list[0,1]',
                'rate_limit_requests' => 'permit_empty|is_natural_no_zero',
                'rate_limit_window' => 'permit_empty|is_natural_no_zero',
                'user_rate_limit' => 'permit_empty|is_natural_no_zero',
                'ip_rate_limit' => 'permit_empty|is_natural_no_zero',
            ],
        ];

        return $rules[$action] ?? [];
    }

    public function getMessages(string $action): array
    {
        $messages = [
            'store' => [
                'name.required' => lang('InputValidation.api_key.nameRequired'),
            ],
        ];

        return $messages[$action] ?? [];
    }
}
