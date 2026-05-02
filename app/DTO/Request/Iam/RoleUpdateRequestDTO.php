<?php

declare(strict_types=1);

namespace App\DTO\Request\Iam;

use App\DTO\Request\BaseRequestDTO;
use OpenApi\Attributes as OA;

#[OA\Schema(schema: 'RoleUpdateRequest')]
readonly class RoleUpdateRequestDTO extends BaseRequestDTO
{
    #[OA\Property(description: 'Application id; null for global roles', type: 'integer', nullable: true)]
    public ?int $application_id;
    #[OA\Property(description: 'Role code (unique within application)', type: 'string', nullable: true)]
    public ?string $code;
    #[OA\Property(description: 'Display name', type: 'string', nullable: true)]
    public ?string $name;
    #[OA\Property(description: 'Free-form description', type: 'string', nullable: true)]
    public ?string $description;
    #[OA\Property(description: 'System role (cannot be deleted)', type: 'boolean', nullable: true)]
    public ?bool $is_system;

    public function rules(): array
    {
        return [
            'application_id' => 'permit_empty|integer',
            'code' => 'permit_empty|string|max_length[100]',
            'name' => 'permit_empty|string|max_length[100]',
            'description' => 'permit_empty|string',
            'is_system' => 'permit_empty|in_list[0,1]',
        ];
    }

    protected function map(array $data): void
    {
        $this->application_id = isset($data['application_id']) ? (int) $data['application_id'] : null;
        $this->code = isset($data['code']) ? (string) $data['code'] : null;
        $this->name = isset($data['name']) ? (string) $data['name'] : null;
        $this->description = isset($data['description']) ? (string) $data['description'] : null;
        $this->is_system = isset($data['is_system']) ? (bool) $data['is_system'] : null;
    }

    public function toArray(): array
    {
        return array_filter([
            'application_id' => $this->application_id,
            'code' => $this->code,
            'name' => $this->name,
            'description' => $this->description,
            'is_system' => $this->is_system,
        ], fn ($v) => $v !== null);
    }
}
