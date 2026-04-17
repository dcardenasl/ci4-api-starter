<?php

declare(strict_types=1);

namespace App\Commands;

use App\Support\Scaffolding\ConfigWireman;
use App\Support\Scaffolding\Field;
use App\Support\Scaffolding\FieldNameValidator;
use App\Support\Scaffolding\FieldStringParser;
use App\Support\Scaffolding\ResourceSchema;
use App\Support\Scaffolding\ScaffoldConflictException;
use App\Support\Scaffolding\ScaffoldingOrchestrator;
use App\Support\Scaffolding\StringHelper;
use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;
use InvalidArgumentException;

/**
 * MakeCrud Command (Evolution)
 *
 * Orchestrates the modular scaffolding engine to generate 100% functional CRUDs.
 */
class MakeCrud extends BaseCommand
{
    protected $group = 'Scaffold';
    protected $name = 'make:crud';
    protected $description = 'Generate a complete CRUD module following the DTO-first architecture.';
    protected $usage = 'make:crud <Resource> [--domain <Domain>] [--fields <fields_string>]';
    protected $arguments = [
        'Resource' => 'Resource singular name (e.g. Product, InvoiceItem)',
    ];
    protected $options = [
        '--domain' => 'Domain folder (default: Catalog)',
        '--route' => 'Route slug plural (default: kebab-case plural of resource)',
        '--fields' => 'Fields definition string (name:type:options,...)',
        '--soft-delete' => 'Enable soft deletes yes|no (default: yes)',
    ];

    public function run(array $params)
    {
        $resourceInput = (string) ($params[0] ?? '');
        if ($resourceInput === '') {
            CLI::error('Resource argument is required. Example: php spark make:crud Product');
            return EXIT_ERROR;
        }

        $resource = StringHelper::studly($resourceInput);
        $domain = StringHelper::studly((string) (CLI::getOption('domain') ?: 'Catalog'));
        $route = (string) (CLI::getOption('route') ?: StringHelper::toKebab(StringHelper::pluralize($resource)));
        $fieldsArg = (string) (CLI::getOption('fields') ?: '');
        $softDelete = $this->yesNoOption('soft-delete', true);

        CLI::write("🚀 Preparing to scaffold resource: {$resource} in Domain: {$domain}", 'cyan');

        try {
            // 1. Gather Fields (CLI or Interactive)
            $fields = $this->gatherFields($fieldsArg);

            if (empty($fields)) {
                CLI::error('No fields defined. Aborting.');
                return EXIT_ERROR;
            }

            // 1b. Reject field names that would silently break generation
            // (reserved words, duplicates, collisions with engine-managed columns).
            (new FieldNameValidator())->validate($fields);

            // 2. Build Schema
            $schema = new ResourceSchema(
                resource: $resource,
                domain: $domain,
                route: $route,
                fields: $fields,
                softDelete: $softDelete
            );

            // 3. Orchestrate File Generation
            $orchestrator = new ScaffoldingOrchestrator();
            $createdFiles = $orchestrator->orchestrate($schema);

            foreach ($createdFiles as $file) {
                CLI::write("CREATED: {$file}", 'green');
            }

            // 4. Wire Services and Config
            $wireman = new ConfigWireman();
            $wireman->wire($schema);
            CLI::write("WIRING: Services and Mappers registered successfully.", 'green');

            CLI::newLine();
            CLI::write('✅ CRUD Module files generated!', 'white', 'green');
            CLI::newLine();

            // 5. Automatic Validation
            CLI::write("🔍 Running module bootstrap check...", 'yellow');
            $this->call('module:check', [$resource, '--domain' => $domain]);

            CLI::newLine();
            CLI::write("🚀 Next Steps:", 'cyan');
            CLI::write("1. Run 'php spark migrate' to create the table.", 'yellow');
            CLI::write("2. Run 'php spark swagger:generate' to update OpenAPI docs.", 'yellow');
            CLI::write("3. Start exploring: " . base_url("api/v1/{$route}"), 'white');
            CLI::newLine();

        } catch (ScaffoldConflictException | InvalidArgumentException $e) {
            CLI::error($e->getMessage());
            return EXIT_ERROR;
        } catch (\Exception $e) {
            CLI::error("An error occurred: " . $e->getMessage());
            return EXIT_ERROR;
        }

        return EXIT_SUCCESS;
    }

    private function gatherFields(string $fieldsArg): array
    {
        if ($fieldsArg !== '') {
            return $this->parseFieldsString($fieldsArg);
        }

        return $this->gatherFieldsInteractively();
    }

    private function parseFieldsString(string $fieldsArg): array
    {
        return (new FieldStringParser())->parse($fieldsArg);
    }

    private function gatherFieldsInteractively(): array
    {
        $fields = [];
        CLI::write('--- Interactive Field Definition ---', 'yellow');

        while (true) {
            $name = CLI::prompt('Field name (or leave empty to finish)');
            if ($name === null || trim($name) === '') {
                break;
            }

            $type = CLI::prompt('Field type', ['string', 'text', 'int', 'bool', 'decimal', 'email', 'date', 'datetime', 'fk', 'json'], 'string');
            $required = CLI::prompt('Is required?', ['y', 'n'], 'y') === 'y';
            $searchable = CLI::prompt('Is searchable?', ['y', 'n'], 'n') === 'y';
            $filterable = CLI::prompt('Is filterable?', ['y', 'n'], 'n') === 'y';

            $fkTable = null;
            if ($type === 'fk') {
                $fkTable = CLI::prompt('Foreign key table name');
            }

            $fields[] = new Field(
                name: $name,
                type: $type,
                required: $required,
                nullable: !$required,
                searchable: $searchable,
                filterable: $filterable,
                fkTable: $fkTable
            );

            CLI::write("Field '{$name}' added.", 'cyan');
        }

        return $fields;
    }

    private function yesNoOption(string $name, bool $default): bool
    {
        $raw = CLI::getOption($name);
        if ($raw === null || $raw === true) {
            return $default;
        }
        return in_array(strtolower((string) $raw), ['yes', 'y', 'true', '1'], true);
    }
}
