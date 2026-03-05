<?php

declare(strict_types=1);

namespace App\Support\Scaffolding;

/**
 * ConfigWireman
 * Automates the "wiring" of services and domains in the Config files.
 */
class ConfigWireman
{
    private const SERVICES_FILE = APPPATH . 'Config/Services.php';

    public function wire(ResourceSchema $schema): void
    {
        $domain = $schema->domain;
        $domainTraitFile = APPPATH . "Config/{$domain}DomainServices.php";

        // 1. If domain trait file doesn't exist, create it and register in main Services.php
        if (!file_exists($domainTraitFile)) {
            $this->createDomainTrait($domain, $domainTraitFile);
            $this->registerDomainInMainServices($domain);
        }

        // 2. Inject the Service and Mapper into the domain trait
        $this->injectServiceAndMapper($schema, $domainTraitFile);
    }

    private function createDomainTrait(string $domain, string $path): void
    {
        $content = <<<PHP
<?php

declare(strict_types=1);

namespace Config;

trait {$domain}DomainServices
{
}
PHP;
        file_put_contents($path, $content);
    }

    private function registerDomainInMainServices(string $domain): void
    {
        $content = (string) file_get_contents(self::SERVICES_FILE);
        $requireLine = "require_once __DIR__ . '/{$domain}DomainServices.php';";
        $useLine = "    use {$domain}DomainServices;";

        // Inject require_once if not present
        if (!str_contains($content, $requireLine)) {
            $content = preg_replace(
                '/(require_once __DIR__ \. \'\/[a-zA-Z]+Services\.php\';)/',
                "$0\n" . $requireLine,
                $content,
                1
            );
        }

        // Inject use trait if not present
        if (!str_contains($content, $useLine)) {
            $content = preg_replace(
                '/(    use [a-zA-Z]+DomainServices;)/',
                "$0\n" . $useLine,
                $content,
                1
            );
        }

        file_put_contents(self::SERVICES_FILE, $content);
    }

    private function injectServiceAndMapper(ResourceSchema $schema, string $path): void
    {
        $content = (string) file_get_contents($path);
        $resource = $schema->resource;
        $resourceLower = $schema->getResourceLower();
        $domain = $schema->domain;

        $mapperName = "{$resourceLower}ResponseMapper";
        $serviceName = "{$resourceLower}Service";

        if (str_contains($content, "function {$serviceName}")) {
            return; // Already exists
        }

        $code = "\n    public static function {$mapperName}(bool \$getShared = true): \\App\\Interfaces\\Mappers\\ResponseMapperInterface\n" .
                "    {\n" .
                "        if (\$getShared) {\n" .
                "            return static::getSharedInstance('{$mapperName}');\n" .
                "        }\n\n" .
                "        return new \\App\\Services\\Core\\Mappers\\DtoResponseMapper(\n" .
                "            \\App\\DTO\\Response\\{$domain}\\{$resource}ResponseDTO::class\n" .
                "        );\n" .
                "    }\n\n" .
                "    public static function {$serviceName}(bool \$getShared = true): \\App\\Interfaces\\{$domain}\\{$resource}ServiceInterface\n" .
                "    {\n" .
                "        if (\$getShared) {\n" .
                "            return static::getSharedInstance('{$serviceName}');\n" .
                "        }\n\n" .
                "        return new \\App\\Services\\{$domain}\\{$resource}Service(\n" .
                "            new \\App\\Repositories\\GenericRepository(model(\\App\\Models\\{$resource}Model::class)),\n" .
                "            static::{$mapperName}()\n" .
                "        );\n" .
                "    }\n";

        // Inject before the last closing brace of the trait
        $pos = strrpos($content, '}');
        if ($pos !== false) {
            $content = substr($content, 0, $pos) . $code . substr($content, $pos);
            file_put_contents($path, $content);
        }
    }
}
