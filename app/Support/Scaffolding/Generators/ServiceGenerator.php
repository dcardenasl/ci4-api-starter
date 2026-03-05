<?php

declare(strict_types=1);

namespace App\Support\Scaffolding\Generators;

use App\Support\Scaffolding\ResourceSchema;

/**
 * ServiceGenerator
 * Generates the Service Interface and the Service Implementation.
 */
class ServiceGenerator
{
    public function generate(ResourceSchema $schema): array
    {
        $domain = $schema->domain;
        $resource = $schema->resource;

        return [
            APPPATH . "Interfaces/{$domain}/{$resource}ServiceInterface.php" => $this->interfaceTemplate($schema),
            APPPATH . "Services/{$domain}/{$resource}Service.php" => $this->serviceTemplate($schema),
        ];
    }

    private function interfaceTemplate(ResourceSchema $schema): string
    {
        return <<<PHP
<?php

declare(strict_types=1);

namespace App\Interfaces\\{$schema->domain};

use App\Interfaces\Core\CrudServiceContract;

interface {$schema->resource}ServiceInterface extends CrudServiceContract
{
    // Add resource-specific service methods here if needed.
}
PHP;
    }

    private function serviceTemplate(ResourceSchema $schema): string
    {
        $resourceLower = $schema->getResourceLower();

        return <<<PHP
<?php

declare(strict_types=1);

namespace App\Services\\{$schema->domain};

use App\Interfaces\Core\RepositoryInterface;
use App\Interfaces\Mappers\ResponseMapperInterface;
use App\Interfaces\\{$schema->domain}\\{$schema->resource}ServiceInterface;
use App\Services\Core\BaseCrudService;

class {$schema->resource}Service extends BaseCrudService implements {$schema->resource}ServiceInterface
{
    public function __construct(
        RepositoryInterface \${$resourceLower}Repository,
        ResponseMapperInterface \$responseMapper
    ) {
        parent::__construct(\${$resourceLower}Repository, \$responseMapper);
    }

    /**
     * Domain Hooks
     * 
     * Implement beforeStore, afterStore, beforeUpdate, etc., 
     * to add specific business logic while keeping the service layer clean.
     */
}
PHP;
    }
}
