<?php

declare(strict_types=1);

namespace Tests\Unit\Support\Scaffolding;

use App\Support\Scaffolding\Field;
use App\Support\Scaffolding\Generators\ControllerGenerator;
use App\Support\Scaffolding\Generators\DtoGenerator;
use App\Support\Scaffolding\Generators\ModelEntityGenerator;
use App\Support\Scaffolding\ResourceSchema;
use App\Support\Scaffolding\ScaffoldingOrchestrator;
use CodeIgniter\Test\CIUnitTestCase;

/**
 * Regression tests for scaffolding engine bugs.
 */
class ScaffoldingRegressionTest extends CIUnitTestCase
{
    /**
     * @var string[]
     */
    private array $createdFiles = [];

    protected function tearDown(): void
    {
        parent::tearDown();
        $this->cleanupGeneratedFiles();
    }

    public function testOrchestratorAllowsRouteUpsertWithinSameDomain(): void
    {
        $fields = [
            new Field(name: 'name', type: 'string', required: true),
        ];

        $schemaA = new ResourceSchema(
            resource: 'AlphaWidget',
            domain: 'RouteTest',
            route: 'alpha-widgets',
            fields: $fields
        );

        $schemaB = new ResourceSchema(
            resource: 'BetaWidget',
            domain: 'RouteTest',
            route: 'beta-widgets',
            fields: $fields
        );

        $orchestrator = new ScaffoldingOrchestrator();
        $this->createdFiles = array_merge(
            $this->createdFiles,
            $orchestrator->orchestrate($schemaA)
        );

        // Ensure migration filenames don't collide when called quickly.
        usleep(1100000);

        $this->createdFiles = array_merge(
            $this->createdFiles,
            $orchestrator->orchestrate($schemaB)
        );

        $routeFile = APPPATH . 'Config/Routes/v1/route-test.php';
        $this->assertFileExists($routeFile);
        $content = (string) file_get_contents($routeFile);

        $this->assertStringContainsString('AlphaWidgetController::index', $content);
        $this->assertStringContainsString('BetaWidgetController::index', $content);
    }

    public function testRequestDtosIncludeSchemaAttribute(): void
    {
        $schema = new ResourceSchema(
            resource: 'DtoSchemaProbe',
            domain: 'Docs',
            route: 'dto-schema-probes',
            fields: [
                new Field(name: 'name', type: 'string', required: true),
            ]
        );

        $generator = new DtoGenerator();
        $files = $generator->generate($schema);

        $indexContent = $files[APPPATH . 'DTO/Request/Docs/DtoSchemaProbeIndexRequestDTO.php'];
        $createContent = $files[APPPATH . 'DTO/Request/Docs/DtoSchemaProbeCreateRequestDTO.php'];
        $updateContent = $files[APPPATH . 'DTO/Request/Docs/DtoSchemaProbeUpdateRequestDTO.php'];

        $this->assertStringContainsString("#[OA\\Schema(schema: 'DtoSchemaProbeIndexRequest')]", $indexContent);
        $this->assertStringContainsString("#[OA\\Schema(schema: 'DtoSchemaProbeCreateRequest')]", $createContent);
        $this->assertStringContainsString("#[OA\\Schema(schema: 'DtoSchemaProbeUpdateRequest')]", $updateContent);
    }

    /**
     * Regression: ModelEntityGenerator must use snake_case for the table name on compound resources
     * (e.g. SchoolCategory → school_categories), matching the migration fix from commit fdac166.
     */
    public function testModelTableIsSnakeCaseForCompoundResource(): void
    {
        $schema = new ResourceSchema(
            resource: 'SchoolCategory',
            domain: 'Education',
            route: 'school-categories',
            fields: [new Field(name: 'name', type: 'string', required: true)]
        );

        $files = (new ModelEntityGenerator())->generate($schema);
        $modelContent = $files[APPPATH . 'Models/SchoolCategoryModel.php'];

        $this->assertStringContainsString("protected \$table = 'school_categories';", $modelContent);
        $this->assertStringNotContainsString("schoolCategories", $modelContent);
    }

    /**
     * Regression: Entity $dates must omit deleted_at when soft-delete is disabled,
     * mirroring the migration behavior.
     */
    public function testEntityDatesRespectSoftDeleteFlag(): void
    {
        $withSoftDelete = new ResourceSchema(
            resource: 'WithSoftDelete',
            domain: 'Probe',
            route: 'with-soft-deletes',
            fields: [new Field(name: 'name', type: 'string')],
            softDelete: true
        );

        $withoutSoftDelete = new ResourceSchema(
            resource: 'WithoutSoftDelete',
            domain: 'Probe',
            route: 'without-soft-deletes',
            fields: [new Field(name: 'name', type: 'string')],
            softDelete: false
        );

        $withFiles = (new ModelEntityGenerator())->generate($withSoftDelete);
        $withoutFiles = (new ModelEntityGenerator())->generate($withoutSoftDelete);

        $withEntity = $withFiles[APPPATH . 'Entities/WithSoftDeleteEntity.php'];
        $withoutEntity = $withoutFiles[APPPATH . 'Entities/WithoutSoftDeleteEntity.php'];

        $this->assertStringContainsString("['created_at', 'updated_at', 'deleted_at']", $withEntity);
        $this->assertStringContainsString("['created_at', 'updated_at']", $withoutEntity);
        $this->assertStringNotContainsString('deleted_at', $withoutEntity);
    }

    /**
     * Regression: Create DTO must cast all typed fields (int, float, bool, string),
     * not just int. Previously decimals and booleans arrived as strings from the request body.
     */
    public function testCreateDtoCastsAllTypes(): void
    {
        $schema = new ResourceSchema(
            resource: 'TypedProbe',
            domain: 'Probe',
            route: 'typed-probes',
            fields: [
                new Field(name: 'title', type: 'string', required: true),
                new Field(name: 'count', type: 'int', required: true),
                new Field(name: 'price', type: 'decimal', required: true),
                new Field(name: 'is_active', type: 'bool', required: true),
                new Field(name: 'meta', type: 'json', required: false, nullable: true),
            ]
        );

        $files = (new DtoGenerator())->generate($schema);
        $create = $files[APPPATH . 'DTO/Request/Probe/TypedProbeCreateRequestDTO.php'];

        $this->assertStringContainsString("\$this->title = (string)", $create);
        $this->assertStringContainsString("\$this->count = (int)", $create);
        $this->assertStringContainsString("\$this->price = (float)", $create);
        $this->assertStringContainsString("\$this->is_active = (bool)", $create);
    }

    /**
     * Regression: Update DTO must preserve compound validation rules like `required_if`
     * when relaxing `required` to `permit_empty`. A naive str_replace would produce the
     * invalid rule `permit_empty_if`.
     */
    public function testUpdateDtoPreservesCompoundRequiredRules(): void
    {
        $schema = new ResourceSchema(
            resource: 'CompoundRuleProbe',
            domain: 'Probe',
            route: 'compound-rule-probes',
            fields: [
                new Field(
                    name: 'status',
                    type: 'string',
                    required: true,
                    validationRules: 'required_if[other_field,active]'
                ),
            ]
        );

        $files = (new DtoGenerator())->generate($schema);
        $update = $files[APPPATH . 'DTO/Request/Probe/CompoundRuleProbeUpdateRequestDTO.php'];

        $this->assertStringNotContainsString('permit_empty_if', $update);
        $this->assertStringContainsString('required_if[other_field,active]', $update);
    }

    /**
     * Regression: Response DTO must expose both `created_at` and `updated_at` so clients
     * can detect when a resource was last modified.
     */
    public function testResponseDtoIncludesUpdatedAt(): void
    {
        $schema = new ResourceSchema(
            resource: 'TimestampProbe',
            domain: 'Probe',
            route: 'timestamp-probes',
            fields: [new Field(name: 'name', type: 'string', required: true)]
        );

        $files = (new DtoGenerator())->generate($schema);
        $response = $files[APPPATH . 'DTO/Response/Probe/TimestampProbeResponseDTO.php'];

        $this->assertStringContainsString("public ?string \$createdAt", $response);
        $this->assertStringContainsString("public ?string \$updatedAt", $response);
        $this->assertStringContainsString("'updated_at' => \$this->updatedAt", $response);
    }

    /**
     * Regression: OpenAPI documentation class must declare all five CRUD endpoints.
     * Previously update (PUT) and delete (DELETE) were missing from the generated docs.
     */
    public function testDocEndpointsCoverFullCrud(): void
    {
        $schema = new ResourceSchema(
            resource: 'OpenApiProbe',
            domain: 'Docs',
            route: 'open-api-probes',
            fields: [new Field(name: 'name', type: 'string', required: true)]
        );

        $files = (new ControllerGenerator())->generate($schema);
        $docs = $files[APPPATH . 'Documentation/Docs/OpenApiProbeEndpoints.php'];

        $this->assertStringContainsString('OA\Get(', $docs);
        $this->assertStringContainsString('OA\Post(', $docs);
        $this->assertStringContainsString('OA\Put(', $docs);
        $this->assertStringContainsString('OA\Delete(', $docs);
        $this->assertStringContainsString('public function update()', $docs);
        $this->assertStringContainsString('public function delete()', $docs);
    }

    private function cleanupGeneratedFiles(): void
    {
        $files = array_unique($this->createdFiles);
        foreach ($files as $file) {
            if (file_exists($file)) {
                @unlink($file);
            }
        }

        $this->cleanupEmptyDirectories($files);
        $this->createdFiles = [];
    }

    /**
     * @param string[] $files
     */
    private function cleanupEmptyDirectories(array $files): void
    {
        $dirs = [];
        foreach ($files as $file) {
            $dirs[] = dirname($file);
        }

        $dirs = array_unique($dirs);
        usort($dirs, static fn (string $a, string $b): int => strlen($b) <=> strlen($a));

        foreach ($dirs as $dir) {
            if (is_dir($dir) && count(scandir($dir)) === 2) {
                @rmdir($dir);
            }
        }
    }
}
