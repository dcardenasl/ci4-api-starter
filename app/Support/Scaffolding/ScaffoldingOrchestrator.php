<?php

declare(strict_types=1);

namespace App\Support\Scaffolding;

use App\Support\Scaffolding\Generators\ControllerGenerator;
use App\Support\Scaffolding\Generators\DtoGenerator;
use App\Support\Scaffolding\Generators\MigrationGenerator;
use App\Support\Scaffolding\Generators\ModelEntityGenerator;
use App\Support\Scaffolding\Generators\ServiceGenerator;

/**
 * ScaffoldingOrchestrator
 * Coordinates all modular generators to produce a complete CRUD module.
 */
class ScaffoldingOrchestrator
{
    private DtoGenerator $dtoGenerator;
    private MigrationGenerator $migrationGenerator;
    private ModelEntityGenerator $modelEntityGenerator;
    private ServiceGenerator $serviceGenerator;
    private ControllerGenerator $controllerGenerator;

    public function __construct()
    {
        $this->dtoGenerator = new DtoGenerator();
        $this->migrationGenerator = new MigrationGenerator();
        $this->modelEntityGenerator = new ModelEntityGenerator();
        $this->serviceGenerator = new ServiceGenerator();
        $this->controllerGenerator = new ControllerGenerator();
    }

    /**
     * @return string[] List of created files
     * @throws ScaffoldConflictException
     */
    public function orchestrate(ResourceSchema $schema): array
    {
        $filesToCreate = array_merge(
            $this->dtoGenerator->generate($schema),
            $this->migrationGenerator->generate($schema),
            $this->modelEntityGenerator->generate($schema),
            $this->serviceGenerator->generate($schema),
            $this->controllerGenerator->generate($schema)
        );

        $this->validateFilesDoNotExist($filesToCreate);

        $createdFiles = [];
        foreach ($filesToCreate as $path => $content) {
            $this->ensureDirectoryExists(dirname($path));
            file_put_contents($path, $content);
            $createdFiles[] = $path;
        }

        return $createdFiles;
    }

    private function validateFilesDoNotExist(array $files): void
    {
        $existing = [];
        foreach (array_keys($files) as $path) {
            if (file_exists($path)) {
                $existing[] = $path;
            }
        }

        if (!empty($existing)) {
            throw new ScaffoldConflictException($existing);
        }
    }

    private function ensureDirectoryExists(string $dir): void
    {
        if (!is_dir($dir) && !mkdir($dir, 0775, true) && !is_dir($dir)) {
            throw new \RuntimeException("Failed to create directory: {$dir}");
        }
    }
}
