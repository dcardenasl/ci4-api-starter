<?php

declare(strict_types=1);

namespace App\Services\System;

/**
 * Removes sensitive fields from audit payloads recursively.
 */
class AuditPayloadSanitizer
{
    /**
     * @param array<int, string> $sensitiveFields
     */
    public function __construct(
        private array $sensitiveFields = [
            'password',
            'password_confirmation',
            'token',
            'accesstoken',
            'refreshtoken',
            'apikey',
            'access_token',
            'refresh_token',
            'api_key',
            'key_hash',
        ]
    ) {
    }

    public function sanitize(array $values): array
    {
        $sanitized = [];

        foreach ($values as $key => $value) {
            if (is_string($key) && in_array(strtolower($key), $this->sensitiveFields, true)) {
                continue;
            }

            $sanitized[$key] = is_array($value)
                ? $this->sanitize($value)
                : $value;
        }

        return $sanitized;
    }
}
