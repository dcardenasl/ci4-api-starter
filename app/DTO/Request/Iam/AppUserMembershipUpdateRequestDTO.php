<?php

declare(strict_types=1);

namespace App\DTO\Request\Iam;

use App\DTO\Request\BaseRequestDTO;
use OpenApi\Attributes as OA;

#[OA\Schema(schema: 'AppUserMembershipUpdateRequest')]
readonly class AppUserMembershipUpdateRequestDTO extends BaseRequestDTO
{
    #[OA\Property(description: 'user_id', type: 'integer', nullable: true)]
    public ?int $user_id;
    #[OA\Property(description: 'application_id', type: 'integer', nullable: true)]
    public ?int $application_id;
    #[OA\Property(description: 'status', type: 'string', nullable: true)]
    public ?string $status;

    public function rules(): array
    {
        return [
            'user_id' => 'permit_empty|integer',
            'application_id' => 'permit_empty|integer',
            'status' => 'permit_empty|string|max_length[255]',
        ];
    }

    protected function map(array $data): void
    {
        $this->user_id = isset($data['user_id']) ? (int) $data['user_id'] : null;
        $this->application_id = isset($data['application_id']) ? (int) $data['application_id'] : null;
        $this->status = $data['status'] ?? null;
    }

    public function toArray(): array
    {
        return array_filter([
            'user_id' => $this->user_id,
            'application_id' => $this->application_id,
            'status' => $this->status,
        ], fn ($v) => $v !== null);
    }
}
