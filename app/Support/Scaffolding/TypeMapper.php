<?php

declare(strict_types=1);

namespace App\Support\Scaffolding;

/**
 * TypeMapper
 * The core brain that maps abstract types to DB/PHP/Validation/OpenAPI specifics.
 */
class TypeMapper
{
    /**
     * @var array<string, array{
     *   db: string,
     *   php: string,
     *   val: string,
     *   oa: string,
     *   oa_format?: string
     * }>
     */
    private static array $map = [
        'string' => [
            'db' => 'VARCHAR',
            'php' => 'string',
            'val' => 'string|max_length[255]',
            'oa' => 'string'
        ],
        'text' => [
            'db' => 'TEXT',
            'php' => 'string',
            'val' => 'string',
            'oa' => 'string'
        ],
        'int' => [
            'db' => 'INT',
            'php' => 'int',
            'val' => 'is_natural_no_zero',
            'oa' => 'integer'
        ],
        'bool' => [
            'db' => 'TINYINT',
            'php' => 'bool',
            'val' => 'boolean',
            'oa' => 'boolean'
        ],
        'decimal' => [
            'db' => 'DECIMAL',
            'php' => 'float',
            'val' => 'decimal',
            'oa' => 'number',
            'oa_format' => 'float'
        ],
        'email' => [
            'db' => 'VARCHAR',
            'php' => 'string',
            'val' => 'string|valid_email|max_length[255]',
            'oa' => 'string',
            'oa_format' => 'email'
        ],
        'date' => [
            'db' => 'DATE',
            'php' => 'string',
            'val' => 'valid_date',
            'oa' => 'string',
            'oa_format' => 'date'
        ],
        'datetime' => [
            'db' => 'DATETIME',
            'php' => 'string',
            'val' => 'valid_date',
            'oa' => 'string',
            'oa_format' => 'date-time'
        ],
        'fk' => [
            'db' => 'INT',
            'php' => 'int',
            'val' => 'is_natural_no_zero',
            'oa' => 'integer'
        ],
        'json' => [
            'db' => 'JSON',
            'php' => 'array',
            'val' => 'permit_empty',
            'oa' => 'object'
        ]
    ];

    /**
     * @return array{
     *   db: string,
     *   php: string,
     *   val: string,
     *   oa: string,
     *   oa_format?: string
     * }
     */
    public static function get(string $type): array
    {
        return self::$map[$type] ?? self::$map['string'];
    }

    public static function getPhpType(string $type, bool $nullable): string
    {
        $phpType = self::get($type)['php'];
        return ($nullable ? '?' : '') . $phpType;
    }

    public static function getValidationRules(Field $field): string
    {
        $mapping = self::get($field->type);
        $rules = $field->required ? 'required|' : 'permit_empty|';
        $rules .= $mapping['val'];

        if ($field->validationRules) {
            $rules .= '|' . $field->validationRules;
        }

        if ($field->fkTable) {
            $rules .= "|is_not_unique[{$field->fkTable}.id]";
        }

        return $rules;
    }
}
