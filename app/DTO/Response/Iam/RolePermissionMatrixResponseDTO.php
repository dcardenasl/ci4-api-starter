<?php

declare(strict_types=1);

namespace App\DTO\Response\Iam;

/**
 * @readonly
 */
final class RolePermissionMatrixResponseDTO
{
    /**
     * @param list<array{id: int, code: string, name: string, permissions: list<array{id: int, code: string, resource: string, action: string, description: string}>}> $applications
     * @param list<array{id: int, code: string, name: string, description: string, is_system: int}> $roles
     * @param array<int, list<int>> $assignments
     */
    public function __construct(
        public array $applications,
        public array $roles,
        public array $assignments,
    ) {
    }

    /**
     * @param array{applications:list<array<string,mixed>>,roles:list<array<string,mixed>>,assignments:array<int,list<int>>} $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            applications: $data['applications'] ?? [],
            roles: $data['roles'] ?? [],
            assignments: $data['assignments'] ?? [],
        );
    }
}
