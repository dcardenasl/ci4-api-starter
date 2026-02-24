<?php

declare(strict_types=1);

namespace App\Interfaces;

interface CrudServiceContract
{
    public function index(array $data): array;

    public function show(array $data): array;

    public function store(array $data): array;

    public function update(array $data): array;

    public function destroy(array $data): array;
}
