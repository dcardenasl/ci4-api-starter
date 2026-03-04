<?php

declare(strict_types=1);

namespace App\Commands;

use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;

class ModuleCheck extends BaseCommand
{
    protected $group = 'Scaffold';
    protected $name = 'module:check';
    protected $description = 'Validate module bootstrap compliance for template architecture.';
    protected $usage = 'module:check <Resource> [--domain <Domain>]';
    protected $arguments = [
        'Resource' => 'Resource singular name (e.g. Product, InvoiceItem)',
    ];
    protected $options = [
        '--domain' => 'Domain folder (default: Catalog)',
    ];

    public function run(array $params)
    {
        $resourceInput = (string) ($params[0] ?? '');
        if ($resourceInput === '') {
            CLI::error('Resource argument is required. Example: php spark module:check Product --domain Catalog');
            return EXIT_ERROR;
        }

        $resource = $this->studly($resourceInput);
        $resourceLower = lcfirst($resource);
        $resourcePlural = $this->pluralize($resource);
        $domain = $this->studly((string) (CLI::getOption('domain') ?: 'Catalog'));

        $checks = [
            APPPATH . "Controllers/Api/V1/{$domain}/{$resource}Controller.php",
            APPPATH . "Services/{$domain}/{$resource}Service.php",
            APPPATH . "Interfaces/{$domain}/{$resource}ServiceInterface.php",
            APPPATH . "DTO/Request/{$domain}/{$resource}IndexRequestDTO.php",
            APPPATH . "DTO/Request/{$domain}/{$resource}CreateRequestDTO.php",
            APPPATH . "DTO/Request/{$domain}/{$resource}UpdateRequestDTO.php",
            APPPATH . "DTO/Response/{$domain}/{$resource}ResponseDTO.php",
            APPPATH . "Documentation/{$domain}/{$resource}Endpoints.php",
            APPPATH . "Language/en/{$resourcePlural}.php",
            APPPATH . "Language/es/{$resourcePlural}.php",
            ROOTPATH . "tests/Unit/Services/{$domain}/{$resource}ServiceTest.php",
            ROOTPATH . "tests/Integration/Models/{$resource}ModelTest.php",
            ROOTPATH . "tests/Feature/Controllers/{$domain}/{$resource}ControllerTest.php",
        ];

        $missing = [];
        foreach ($checks as $path) {
            if (!file_exists($path)) {
                $missing[] = $path;
            }
        }

        $placeholderPatterns = [
            'markTestIncomplete',
            'TODO',
            'FIXME',
        ];
        foreach ($checks as $path) {
            if (!is_file($path)) {
                continue;
            }

            $source = (string) file_get_contents($path);
            foreach ($placeholderPatterns as $pattern) {
                if (str_contains($source, $pattern)) {
                    $missing[] = "Placeholder `{$pattern}` found in {$path}";
                }
            }
        }

        $servicesPath = APPPATH . 'Config/Services.php';
        $servicesSource = is_file($servicesPath) ? (string) file_get_contents($servicesPath) : '';
        $serviceMethod = "function {$resourceLower}Service(";
        $mapperMethod = "function {$resourceLower}ResponseMapper(";
        if (!str_contains($servicesSource, $serviceMethod)) {
            $missing[] = "Missing service registration method in app/Config/Services.php: {$serviceMethod}";
        }
        if (!str_contains($servicesSource, $mapperMethod)) {
            $missing[] = "Missing mapper registration method in app/Config/Services.php: {$mapperMethod}";
        }

        $routesPath = APPPATH . 'Config/Routes.php';
        $routesSource = is_file($routesPath) ? (string) file_get_contents($routesPath) : '';
        $controllerRef = "{$resource}Controller::";
        if (!str_contains($routesSource, $controllerRef)) {
            $missing[] = "Missing route reference in app/Config/Routes.php: {$controllerRef}";
        }

        if ($missing !== []) {
            CLI::error('Module bootstrap check failed.');
            foreach ($missing as $item) {
                CLI::write("- {$item}", 'red');
            }
            return EXIT_ERROR;
        }

        CLI::write('Module bootstrap check passed.', 'green');
        return EXIT_SUCCESS;
    }

    private function studly(string $value): string
    {
        $normalized = preg_replace('/[^a-zA-Z0-9]+/', ' ', trim($value)) ?? '';
        $parts = preg_split('/\s+/', $normalized) ?: [];
        $parts = array_map(static fn (string $part): string => ucfirst(strtolower($part)), $parts);

        return implode('', $parts);
    }

    private function pluralize(string $value): string
    {
        if (preg_match('/y$/i', $value)) {
            return preg_replace('/y$/i', 'ies', $value) ?? ($value . 's');
        }
        if (preg_match('/(s|x|z|ch|sh)$/i', $value)) {
            return $value . 'es';
        }

        return $value . 's';
    }
}
