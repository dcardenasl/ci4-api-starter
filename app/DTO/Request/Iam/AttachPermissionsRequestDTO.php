<?php

declare(strict_types=1);

namespace App\DTO\Request\Iam;

use dcardenasl\Ci4ApiCore\Dto\BaseRequestDTO;
use OpenApi\Attributes as OA;

#[OA\Schema(schema: 'AttachPermissionsRequest', required: ['permission_ids'])]
readonly class AttachPermissionsRequestDTO extends BaseRequestDTO
{
    /** @var int[] */
    #[OA\Property(description: 'Permission ids to attach to the role', type: 'array', items: new OA\Items(type: 'integer'))]
    public array $permission_ids;

    public function rules(): array
    {
        return [
            'permission_ids'   => 'required',
            'permission_ids.*' => 'required|integer|is_natural_no_zero',
        ];
    }

    protected function map(array $data): void
    {
        $ids = $data['permission_ids'] ?? [];
        if (! is_array($ids)) {
            $ids = [];
        }
        $this->permission_ids = array_values(array_unique(array_map(static fn ($v) => (int) $v, $ids)));
    }

    public function toArray(): array
    {
        return ['permission_ids' => $this->permission_ids];
    }
}
