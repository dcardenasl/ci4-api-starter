<?php

declare(strict_types=1);

namespace App\Support\Scaffolding;

/**
 * Single source of truth for the string transformations the scaffolding engine
 * performs on resource names: studly/pluralize/snake/kebab.
 *
 * Previously these helpers were duplicated across MakeCrud, ModuleCheck, and
 * ResourceSchema — one bug fix had to be applied three times.
 */
final class StringHelper
{
    public static function studly(string $value): string
    {
        $normalized = preg_replace('/[^a-zA-Z0-9]+/', ' ', trim($value)) ?? '';
        $parts = preg_split('/\s+/', $normalized) ?: [];
        $parts = array_map(static fn (string $part): string => ucfirst(strtolower($part)), $parts);

        return implode('', $parts);
    }

    public static function pluralize(string $value): string
    {
        if ($value === '') {
            return $value;
        }

        if (preg_match('/[^aeiouAEIOU]y$/', $value) === 1) {
            return substr($value, 0, -1) . 'ies';
        }
        if (preg_match('/(s|x|z|ch|sh)$/i', $value) === 1) {
            return $value . 'es';
        }

        return $value . 's';
    }

    public static function toKebab(string $value): string
    {
        return strtolower((string) preg_replace('/(?<!^)[A-Z]/', '-$0', $value));
    }

    public static function toSnakeCase(string $value): string
    {
        return strtolower((string) preg_replace('/(?<!^)[A-Z]/', '_$0', $value));
    }
}
