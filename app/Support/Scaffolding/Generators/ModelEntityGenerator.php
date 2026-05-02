<?php

declare(strict_types=1);

namespace App\Support\Scaffolding\Generators;

use App\Support\Scaffolding\ResourceSchema;
use App\Support\Scaffolding\TypeMapper;

/**
 * ModelEntityGenerator
 * Generates the Entity and Model with full support for Searchable/Filterable traits.
 */
class ModelEntityGenerator
{
    public function generate(ResourceSchema $schema): array
    {
        $resource = $schema->resource;

        return [
            APPPATH . "Entities/{$resource}Entity.php" => $this->entityTemplate($schema),
            APPPATH . "Models/{$resource}Model.php" => $this->modelTemplate($schema),
        ];
    }

    private function entityTemplate(ResourceSchema $schema): string
    {
        $casts = "'id' => 'integer',\n";
        foreach ($schema->fields as $field) {
            $mapping = TypeMapper::get($field->type);
            $phpType = $mapping['php'];
            // CI4 Casts use specific names
            $castType = $phpType === 'float' ? 'decimal' : $phpType;
            if ($castType === 'array') {
                $castType = 'json';
            }

            $casts .= "        '{$field->name}' => '{$castType}',\n";
        }

        $dates = $schema->softDelete
            ? "['created_at', 'updated_at', 'deleted_at']"
            : "['created_at', 'updated_at']";

        return <<<PHP
<?php

declare(strict_types=1);

namespace App\Entities;

use CodeIgniter\Entity\Entity;

class {$schema->resource}Entity extends Entity
{
    protected \$casts = [
{$casts}    ];

    protected \$dates = {$dates};
}
PHP;
    }

    private function modelTemplate(ResourceSchema $schema): string
    {
        $table = $schema->getResourcePluralSnakeCase();
        $softDelete = $schema->softDelete ? 'true' : 'false';

        $allowedFields = [];
        $searchableFields = [];
        $filterableFields = ["'id'"];
        $sortableFields = ["'id'", "'created_at'"];
        $validationRules = "";

        foreach ($schema->fields as $field) {
            $allowedFields[] = "'{$field->name}'";
            if ($field->searchable) {
                $searchableFields[] = "'{$field->name}'";
                $sortableFields[] = "'{$field->name}'";
            }
            if ($field->filterable) {
                $filterableFields[] = "'{$field->name}'";
                $sortableFields[] = "'{$field->name}'";
            }

            // Pass the table name so TypeMapper can emit is_unique[table.col] for unique fields.
            $rules = TypeMapper::getValidationRules($field, $table);
            $validationRules .= "        '{$field->name}' => '{$rules}',\n";
        }

        $allowedFieldsStr = implode(", ", $allowedFields);
        $searchableFieldsStr = implode(", ", $searchableFields);
        $filterableFieldsStr = implode(", ", $filterableFields);
        $sortableFieldsStr = implode(", ", array_unique($sortableFields));

        return <<<PHP
<?php

declare(strict_types=1);

namespace App\Models;

use App\Entities\\{$schema->resource}Entity;
use App\Traits\Filterable;
use App\Traits\Searchable;

class {$schema->resource}Model extends BaseAuditableModel
{
    use Filterable;
    use Searchable;

    protected \$table = '{$table}';
    protected \$primaryKey = 'id';
    protected \$returnType = {$schema->resource}Entity::class;
    protected \$useSoftDeletes = {$softDelete};
    protected \$useTimestamps = true;
    
    protected \$allowedFields = [{$allowedFieldsStr}];

    /** @var array<int, string> */
    protected array \$searchableFields = [{$searchableFieldsStr}];

    /** @var array<int, string> */
    protected array \$filterableFields = [{$filterableFieldsStr}];

    /** @var array<int, string> */
    protected array \$sortableFields = [{$sortableFieldsStr}];

    protected \$validationRules = [
{$validationRules}    ];
}
PHP;
    }
}
