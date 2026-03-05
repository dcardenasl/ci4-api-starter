<?php

declare(strict_types=1);

namespace App\Support\Scaffolding\Generators;

use App\Support\Scaffolding\ResourceSchema;
use App\Support\Scaffolding\TypeMapper;

/**
 * DtoGenerator
 * Generates all 4 DTOs: Index, Create, Update, and Response.
 */
class DtoGenerator
{
    public function generate(ResourceSchema $schema): array
    {
        $domain = $schema->domain;
        $resource = $schema->resource;

        return [
            APPPATH . "DTO/Request/{$domain}/{$resource}IndexRequestDTO.php" => $this->indexRequestDto($schema),
            APPPATH . "DTO/Request/{$domain}/{$resource}CreateRequestDTO.php" => $this->createRequestDto($schema),
            APPPATH . "DTO/Request/{$domain}/{$resource}UpdateRequestDTO.php" => $this->updateRequestDto($schema),
            APPPATH . "DTO/Response/{$domain}/{$resource}ResponseDTO.php" => $this->responseDto($schema),
        ];
    }

    private function indexRequestDto(ResourceSchema $schema): string
    {
        return <<<PHP
<?php

declare(strict_types=1);

namespace App\DTO\Request\\{$schema->domain};

use App\DTO\Request\BaseRequestDTO;

readonly class {$schema->resource}IndexRequestDTO extends BaseRequestDTO
{
    public int \$page;
    public int \$per_page;
    public ?string \$search;

    public function rules(): array
    {
        return [
            'page'      => 'permit_empty|is_natural_no_zero',
            'per_page'  => 'permit_empty|is_natural_no_zero|less_than[101]',
            'search'    => 'permit_empty|string|max_length[100]',
        ];
    }

    protected function map(array \$data): void
    {
        \$this->page = isset(\$data['page']) ? (int) \$data['page'] : 1;
        \$this->per_page = isset(\$data['per_page']) ? (int) \$data['per_page'] : 20;
        \$this->search = \$data['search'] ?? null;
    }

    public function toArray(): array
    {
        return [
            'page' => \$this->page,
            'per_page' => \$this->per_page,
            'search' => \$this->search,
        ];
    }
}
PHP;
    }

    private function createRequestDto(ResourceSchema $schema): string
    {
        $properties = '';
        $rules = '';
        $mappings = '';
        $toArray = '';

        foreach ($schema->fields as $field) {
            $phpType = TypeMapper::getPhpType($field->type, $field->nullable);
            $validation = TypeMapper::getValidationRules($field);

            $properties .= "    public {$phpType} \${$field->name};\n";
            $rules .= "            '{$field->name}' => '{$validation}',\n";

            $cast = ($field->type === 'int') ? "(int) " : "";
            $mappings .= "        \$this->{$field->name} = {$cast}(\$data['{$field->name}'] ?? '');\n";
            $toArray .= "            '{$field->name}' => \$this->{$field->name},\n";
        }

        return <<<PHP
<?php

declare(strict_types=1);

namespace App\DTO\Request\\{$schema->domain};

use App\DTO\Request\BaseRequestDTO;

readonly class {$schema->resource}CreateRequestDTO extends BaseRequestDTO
{
{$properties}
    public function rules(): array
    {
        return [
{$rules}        ];
    }

    protected function map(array \$data): void
    {
{$mappings}    }

    public function toArray(): array
    {
        return [
{$toArray}        ];
    }
}
PHP;
    }

    private function updateRequestDto(ResourceSchema $schema): string
    {
        $properties = '';
        $rules = '';
        $mappings = '';
        $toArray = '';

        foreach ($schema->fields as $field) {
            $phpType = TypeMapper::getPhpType($field->type, true); // Update fields are usually optional
            $validation = str_replace('required', 'permit_empty', TypeMapper::getValidationRules($field));

            $properties .= "    public {$phpType} \${$field->name};\n";
            $rules .= "            '{$field->name}' => '{$validation}',\n";

            $mappings .= "        \$this->{$field->name} = isset(\$data['{$field->name}']) ? \$data['{$field->name}'] : null;\n";
            $toArray .= "            '{$field->name}' => \$this->{$field->name},\n";
        }

        return <<<PHP
<?php

declare(strict_types=1);

namespace App\DTO\Request\\{$schema->domain};

use App\DTO\Request\BaseRequestDTO;

readonly class {$schema->resource}UpdateRequestDTO extends BaseRequestDTO
{
{$properties}
    public function rules(): array
    {
        return [
{$rules}        ];
    }

    protected function map(array \$data): void
    {
{$mappings}    }

    public function toArray(): array
    {
        return array_filter([
{$toArray}        ], fn(\$v) => \$v !== null);
    }
}
PHP;
    }

    private function responseDto(ResourceSchema $schema): string
    {
        $params = '';
        $toArray = '';
        $requiredFields = ['id'];

        foreach ($schema->fields as $field) {
            $mapping = TypeMapper::get($field->type);
            $phpType = TypeMapper::getPhpType($field->type, $field->nullable);
            $oaType = $mapping['oa'];
            $oaFormat = isset($mapping['oa_format']) ? ", format: '{$mapping['oa_format']}'" : "";
            $nullable = $field->nullable ? ", nullable: true" : "";

            if ($field->required) {
                $requiredFields[] = $field->name;
            }

            $params .= "\n        #[OA\Property(description: '{$field->name}', type: '{$oaType}'{$oaFormat}{$nullable})]\n";
            $params .= "        public {$phpType} \${$field->name},";

            $toArray .= "            '{$field->name}' => \$this->{$field->name},\n";
        }

        $requiredJson = json_encode($requiredFields);

        return <<<PHP
<?php

declare(strict_types=1);

namespace App\DTO\Response\\{$schema->domain};

use App\Interfaces\DataTransferObjectInterface;
use OpenApi\Attributes as OA;

#[OA\Schema(
    schema: '{$schema->resource}Response',
    title: '{$schema->resource} Response',
    required: {$requiredJson}
)]
readonly class {$schema->resource}ResponseDTO implements DataTransferObjectInterface
{
    public function __construct(
        #[OA\Property(description: 'Unique identifier', example: 1)]
        public int \$id,
{$params}
        #[OA\Property(property: 'created_at', description: 'Creation timestamp', example: '2026-02-26 12:00:00', nullable: true)]
        public ?string \$createdAt = null
    ) {}

    public function toArray(): array
    {
        return [
            'id' => \$this->id,
{$toArray}            'created_at' => \$this->createdAt,
        ];
    }
}
PHP;
    }
}
