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

        return <<<PHP
<?php

declare(strict_types=1);

namespace App\Entities;

use CodeIgniter\Entity\Entity;

class {$schema->resource}Entity extends Entity
{
    protected \$casts = [
{$casts}    ];

    protected \$dates = ['created_at', 'updated_at', 'deleted_at'];
}
PHP;
    }

    private function modelTemplate(ResourceSchema $schema): string
    {
        $table = $schema->getResourcePluralLower();
        $softDelete = $schema->softDelete ? 'true' : 'false';

        $allowedFields = [];
        $searchableFields = [];
        $filterableFields = ["'id'"];
        $validationRules = "";

        foreach ($schema->fields as $field) {
            $allowedFields[] = "'{$field->name}'";
            if ($field->searchable) {
                $searchableFields[] = "'{$field->name}'";
            }
            if ($field->filterable) {
                $filterableFields[] = "'{$field->name}'";
            }

            $rules = TypeMapper::getValidationRules($field);
            $validationRules .= "        '{$field->name}' => '{$rules}',\n";
        }

        $allowedFieldsStr = implode(", ", $allowedFields);
        $searchableFieldsStr = implode(", ", $searchableFields);
        $filterableFieldsStr = implode(", ", $filterableFields);

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

    protected array \$searchableFields = [{$searchableFieldsStr}];
    protected array \$filterableFields = [{$filterableFieldsStr}];
    protected array \$sortableFields = ['id', 'created_at'];

    protected \$validationRules = [
{$validationRules}    ];
}
PHP;
    }
}
