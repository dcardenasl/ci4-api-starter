<?php

declare(strict_types=1);

namespace Tests\Unit\Support\Scaffolding;

use App\Support\Scaffolding\Field;
use App\Support\Scaffolding\Generators\ControllerGenerator;
use App\Support\Scaffolding\Generators\DtoGenerator;
use App\Support\Scaffolding\Generators\MigrationGenerator;
use App\Support\Scaffolding\Generators\ModelEntityGenerator;
use App\Support\Scaffolding\Generators\TestGenerator;
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

    /**
     * Regression: `unique` modifier should produce both an `addUniqueKey` in the
     * migration and an `is_unique[table.col]` validation rule in the model and
     * Create DTO, so uniqueness is enforced at every layer.
     */
    public function testUniqueModifierEmitsIndexAndValidationRule(): void
    {
        $schema = new ResourceSchema(
            resource: 'UniqueProbe',
            domain: 'Probe',
            route: 'unique-probes',
            fields: [
                new Field(name: 'email', type: 'email', required: true, unique: true),
            ]
        );

        $migration = current((new MigrationGenerator())->generate($schema));
        $this->assertStringContainsString("addUniqueKey('email')", $migration);

        $modelFiles = (new ModelEntityGenerator())->generate($schema);
        $model = $modelFiles[APPPATH . 'Models/UniqueProbeModel.php'];
        $this->assertStringContainsString('is_unique[unique_probes.email]', $model);

        $dtoFiles = (new DtoGenerator())->generate($schema);
        $create = $dtoFiles[APPPATH . 'DTO/Request/Probe/UniqueProbeCreateRequestDTO.php'];
        $this->assertStringContainsString('is_unique[unique_probes.email]', $create);
    }

    /**
     * Regression: searchable and filterable fields must receive a non-unique index
     * so `?search=…` (LIKE) and `?filter[col]=…` (exact) don't degrade on large tables.
     */
    public function testSearchableAndFilterableFieldsGetImplicitIndexes(): void
    {
        $schema = new ResourceSchema(
            resource: 'IndexProbe',
            domain: 'Probe',
            route: 'index-probes',
            fields: [
                new Field(name: 'title', type: 'string', required: true, searchable: true),
                new Field(name: 'status', type: 'string', required: true, filterable: true),
                new Field(name: 'plain', type: 'string', required: true),
            ]
        );

        $migration = current((new MigrationGenerator())->generate($schema));

        $this->assertStringContainsString("addKey('title')", $migration);
        $this->assertStringContainsString("addKey('status')", $migration);
        $this->assertStringNotContainsString("addKey('plain')", $migration);
    }

    /**
     * Regression: Create and Update request DTOs must annotate every property with
     * `#[OA\Property(...)]` so the generated OpenAPI schema carries descriptions,
     * types, formats, and nullable flags — matching the gold-standard UserCreateRequestDTO.
     */
    public function testRequestDtosAnnotatePropertiesWithOpenApiAttributes(): void
    {
        $schema = new ResourceSchema(
            resource: 'OaAttrProbe',
            domain: 'Probe',
            route: 'oa-attr-probes',
            fields: [
                new Field(name: 'title', type: 'string', required: true),
                new Field(name: 'published_at', type: 'datetime', required: false, nullable: true),
            ]
        );

        $dtoFiles = (new DtoGenerator())->generate($schema);
        $create = $dtoFiles[APPPATH . 'DTO/Request/Probe/OaAttrProbeCreateRequestDTO.php'];
        $update = $dtoFiles[APPPATH . 'DTO/Request/Probe/OaAttrProbeUpdateRequestDTO.php'];

        // Create DTO: title is required (not nullable), published_at is nullable.
        $this->assertStringContainsString("#[OA\\Property(description: 'title', type: 'string')]", $create);
        $this->assertStringContainsString("format: 'date-time'", $create);
        $this->assertStringContainsString('nullable: true', $create);

        // Update DTO: every property becomes nullable.
        $this->assertStringContainsString("#[OA\\Property(description: 'title', type: 'string', nullable: true)]", $update);
    }

    /**
     * Regression: generated test stubs must not use `markTestIncomplete()`.
     * Previously every scaffolded module produced failing tests, so `composer
     * quality` was red from the moment of generation.
     */
    public function testGeneratedTestsContainRealAssertions(): void
    {
        $schema = new ResourceSchema(
            resource: 'AssertProbe',
            domain: 'Probe',
            route: 'assert-probes',
            fields: [new Field(name: 'name', type: 'string', required: true)]
        );

        $files = (new TestGenerator())->generate($schema);

        foreach ($files as $path => $content) {
            $this->assertStringNotContainsString(
                'markTestIncomplete',
                $content,
                "Generated test {$path} still uses markTestIncomplete()."
            );
            // Accept both PHPUnit-style ($this->assertFoo) and fluent
            // test-result assertions ($result->assertStatus, etc.).
            $this->assertMatchesRegularExpression(
                '/\$(this|result)->assert\w+/',
                $content,
                "Generated test {$path} contains no assertion."
            );
        }
    }

    /**
     * Regression: nullable Create-DTO fields must map to `null` when absent,
     * not be coerced to `0` / `''` / `false` (which would hide the distinction
     * between "value omitted" and "value explicitly zero").
     */
    public function testNullableCreateDtoFieldsMapToNull(): void
    {
        $schema = new ResourceSchema(
            resource: 'NullableProbe',
            domain: 'Probe',
            route: 'nullable-probes',
            fields: [
                new Field(name: 'parent_id', type: 'fk', required: false, nullable: true, fkTable: 'nullable_probes'),
            ]
        );

        $dtoFiles = (new DtoGenerator())->generate($schema);
        $create = $dtoFiles[APPPATH . 'DTO/Request/Probe/NullableProbeCreateRequestDTO.php'];

        $this->assertStringContainsString(
            "isset(\$data['parent_id']) ? (int) \$data['parent_id'] : null",
            $create
        );
        $this->assertStringNotContainsString(
            "(int) (\$data['parent_id'] ?? 0)",
            $create
        );
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
