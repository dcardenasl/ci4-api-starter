<?php

declare(strict_types=1);

namespace App\Interfaces;

/**
 * Modernized CRUD Service Contract
 *
 * Enforces strict typing using Data Transfer Objects (DTOs)
 * for all input data.
 */
interface CrudServiceContract
{
    /**
     * Get a paginated list of resources
     */
    public function index(\App\Interfaces\DataTransferObjectInterface $request): array;

    /**
     * Get a single resource by ID
     */
    public function show(int $id): \App\Interfaces\DataTransferObjectInterface;

    /**
     * Create a new resource
     */
    public function store(\App\Interfaces\DataTransferObjectInterface $request): \App\Interfaces\DataTransferObjectInterface;

    /**
     * Update an existing resource
     */
    public function update(int $id, \App\Interfaces\DataTransferObjectInterface $request): \App\Interfaces\DataTransferObjectInterface;

    /**
     * Remove a resource (soft or hard delete)
     */
    public function destroy(int $id): array;
}
