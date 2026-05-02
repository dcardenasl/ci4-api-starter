<?php

declare(strict_types=1);

namespace App\DTO\Request\Iam;

use App\DTO\Request\BaseRequestDTO;
use OpenApi\Attributes as OA;

#[OA\Schema(schema: 'RoleCreateRequest')]
readonly class RoleCreateRequestDTO extends BaseRequestDTO
{
    #[OA\Property(description: 'Application id; null for global roles', type: 'integer', nullable: true)]
    public ?int $application_id;
    #[OA\Property(description: 'Role code (unique within application)', type: 'string')]
    public string $code;
    #[OA\Property(description: 'Display name', type: 'string')]
    public string $name;
    #[OA\Property(description: 'Free-form description', type: 'string')]
    public string $description;
    #[OA\Property(description: 'System role (cannot be deleted)', type: 'boolean')]
    public bool $is_system;

    public function rules(): array
    {
        return [
            'application_id' => 'permit_empty|integer',
            'code' => 'required|string|max_length[100]',
            'name' => 'required|string|max_length[100]',
            'description' => 'permit_empty|string',
            'is_system' => 'permit_empty|in_list[0,1]',
        ];
    }

    protected function map(array $data): void
    {
        $this->application_id = isset($data['application_id']) ? (int) $data['application_id'] : null;
        $this->code = (string) ($data['code'] ?? '');
        $this->name = (string) ($data['name'] ?? '');
        $this->description = (string) ($data['description'] ?? '');
        $this->is_system = (bool) ($data['is_system'] ?? false);
    }

    public function toArray(): array
    {
        return [
            'application_id' => $this->application_id,
            'code' => $this->code,
            'name' => $this->name,
            'description' => $this->description,
            'is_system' => $this->is_system,
        ];
    }
}
