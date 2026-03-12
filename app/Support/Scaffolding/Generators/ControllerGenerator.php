<?php

declare(strict_types=1);

namespace App\Support\Scaffolding\Generators;

use App\Support\Scaffolding\ResourceSchema;

/**
 * ControllerGenerator
 * Generates the API Controller and its corresponding OpenAPI Documentation class.
 */
class ControllerGenerator
{
    public function generate(ResourceSchema $schema): array
    {
        $domain = $schema->domain;
        $resource = $schema->resource;

        return [
            APPPATH . "Controllers/Api/V1/{$domain}/{$resource}Controller.php" => $this->controllerTemplate($schema),
            APPPATH . "Documentation/{$domain}/{$resource}Endpoints.php" => $this->docEndpointsTemplate($schema),
        ];
    }

    private function controllerTemplate(ResourceSchema $schema): string
    {
        $resource = $schema->resource;
        $resourceLower = $schema->getResourceLower();
        $domain = $schema->domain;

        return <<<PHP
<?php

declare(strict_types=1);

namespace App\Controllers\Api\V1\\{$domain};

use App\Controllers\ApiController;
use App\DTO\Request\\{$domain}\\{$resource}IndexRequestDTO;
use App\DTO\Request\\{$domain}\\{$resource}CreateRequestDTO;
use App\DTO\Request\\{$domain}\\{$resource}UpdateRequestDTO;
use App\Traits\Controllers\HasCrudActions;
use Config\Services;

class {$resource}Controller extends ApiController
{
    use HasCrudActions;

    protected function resolveDefaultService(): object
    {
        return Services::{$resourceLower}Service();
    }

    protected string \$indexDto = {$resource}IndexRequestDTO::class;
    protected string \$createDto = {$resource}CreateRequestDTO::class;
    protected string \$updateDto = {$resource}UpdateRequestDTO::class;

    protected array \$statusCodes = [
        'store' => 201,
    ];
}
PHP;
    }

    private function docEndpointsTemplate(ResourceSchema $schema): string
    {
        $resource = $schema->resource;
        $domain = $schema->domain;
        $route = $schema->route;
        $plural = $schema->getResourcePlural();

        return <<<PHP
<?php

declare(strict_types=1);

namespace App\Documentation\\{$domain};

use OpenApi\Attributes as OA;

/**
 * OpenAPI definitions for {$resource} endpoints.
 *
 * @OA\Tag(name="{$domain}", description="{$domain} management")
 */
class {$resource}Endpoints
{
    #[OA\Get(
        path: '/api/v1/{$route}',
        tags: ['{$domain}'],
        summary: 'List {$plural}',
        responses: [
            new OA\Response(
                response: 200,
                description: 'List retrieved successfully',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'status', type: 'string', example: 'success'),
                        new OA\Property(
                            property: 'data',
                            type: 'array',
                            items: new OA\Items(ref: '#/components/schemas/{$resource}Response')
                        ),
                    ],
                    type: 'object'
                )
            ),
        ]
    )]
    public function index() {}

    #[OA\Post(
        path: '/api/v1/{$route}',
        tags: ['{$domain}'],
        summary: 'Create new {$resource}',
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(ref: '#/components/schemas/{$resource}CreateRequest')
        ),
        responses: [
            new OA\Response(response: 201, description: 'Created successfully'),
            new OA\Response(response: 422, description: 'Validation error')
        ]
    )]
    public function store() {}

    #[OA\Get(
        path: '/api/v1/{$route}/{id}',
        tags: ['{$domain}'],
        summary: 'Get {$resource} by ID',
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))
        ],
        responses: [
            new OA\Response(response: 200, description: 'Found'),
            new OA\Response(response: 404, description: 'Not found')
        ]
    )]
    public function show() {}
}
PHP;
    }
}
