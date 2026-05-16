<?php

declare(strict_types=1);

// Strips the `repositories` block from composer.json so CI resolves
// dcardenasl/ci4-api-core and dcardenasl/ci4-api-scaffolding from
// Packagist instead of the sibling workspace paths that only exist
// on a developer's machine.
//
// Safe to call unconditionally — exits 0 when no `repositories` key
// is present.

$file = dirname(__DIR__) . '/composer.json';

if (! is_file($file)) {
    fwrite(STDERR, "composer.json not found at {$file}\n");
    exit(1);
}

$data = json_decode((string) file_get_contents($file), true, flags: JSON_THROW_ON_ERROR);

if (! array_key_exists('repositories', $data)) {
    echo "No `repositories` block present; nothing to strip.\n";
    exit(0);
}

unset($data['repositories']);

file_put_contents(
    $file,
    json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . "\n",
);

echo "Stripped local path `repositories` from composer.json.\n";
