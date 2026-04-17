<?php

declare(strict_types=1);

namespace App\Support\Scaffolding;

/**
 * Parses the `--fields` string accepted by `php spark make:crud` into typed Field objects.
 *
 * Syntax:
 *   name:type:modifier1|modifier2|modifier3
 *
 * FK type uses a 4-segment form:
 *   name:fk:target_table:modifier1|modifier2
 *
 * Multiple fields are comma-separated. The caller is responsible for shell-quoting.
 */
final class FieldStringParser
{
    /**
     * @return list<Field>
     */
    public function parse(string $fieldsArg): array
    {
        $fields = [];
        if (trim($fieldsArg) === '') {
            return $fields;
        }

        foreach (explode(',', $fieldsArg) as $part) {
            $segments = explode(':', trim($part));
            if (count($segments) < 2) {
                continue;
            }

            $name = $segments[0];
            $type = $segments[1];

            if ($type === 'fk') {
                $fkTable = $segments[2] ?? null;
                $options = explode('|', $segments[3] ?? '');
            } else {
                $fkTable = null;
                $options = explode('|', $segments[2] ?? '');
            }

            $fields[] = new Field(
                name: $name,
                type: $type,
                required: in_array('required', $options, true),
                nullable: in_array('nullable', $options, true),
                searchable: in_array('searchable', $options, true),
                filterable: in_array('filterable', $options, true),
                fkTable: $fkTable,
                unique: in_array('unique', $options, true),
                index: in_array('index', $options, true)
            );
        }

        return $fields;
    }
}
