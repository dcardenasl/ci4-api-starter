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
        $value = trim($value);
        if ($value === '') {
            return $value;
        }

        // Already a single alphanumeric identifier (e.g. `SchoolCategory`, `product`, `APIKey`)?
        // Preserve its internal casing — just ensure the first letter is uppercase.
        // Previously this branch was missing, so `SchoolCategory` became `Schoolcategory`,
        // breaking every compound-name resource at the class/filename level.
        if (preg_match('/^[a-zA-Z0-9]+$/', $value) === 1) {
            return ucfirst($value);
        }

        // Split on non-alphanumeric separators (_, -, space) and capitalize each part.
        $parts = preg_split('/[^a-zA-Z0-9]+/', $value) ?: [];
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
