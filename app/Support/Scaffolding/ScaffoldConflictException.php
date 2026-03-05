<?php

declare(strict_types=1);

namespace App\Support\Scaffolding;

use RuntimeException;

class ScaffoldConflictException extends RuntimeException
{
    public function __construct(array $existingFiles)
    {
        $fileList = implode("\n - ", $existingFiles);
        parent::__construct(
            "Scaffolding aborted to prevent overwriting existing work. The following files already exist:\n - {$fileList}\n\nPlease remove them or use a different resource name."
        );
    }
}
