<?php

declare(strict_types=1);

namespace App\DTO\Response\Iam;

use App\Interfaces\DataTransferObjectInterface;
use OpenApi\Attributes as OA;

#[OA\Schema(
    schema: 'AppUserMembershipResponse',
    title: 'AppUserMembership Response',
    required: ["id","user_id","application_id"]
)]
readonly class AppUserMembershipResponseDTO implements DataTransferObjectInterface
{
    public function __construct(
        #[OA\Property(description: 'Unique identifier', example: 1)]
        public int $id,
        #[OA\Property(description: 'user_id', type: 'integer')]
        public int $user_id,
        #[OA\Property(description: 'application_id', type: 'integer')]
        public int $application_id,
        #[OA\Property(description: 'status', type: 'string')]
        public string $status,
        #[OA\Property(property: 'created_at', description: 'Creation timestamp', example: '2026-02-26 12:00:00', nullable: true)]
        public ?string $createdAt = null,
        #[OA\Property(property: 'updated_at', description: 'Last update timestamp', example: '2026-02-26 12:00:00', nullable: true)]
        public ?string $updatedAt = null
    ) {
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'user_id' => $this->user_id,
            'application_id' => $this->application_id,
            'status' => $this->status,
            'created_at' => $this->createdAt,
            'updated_at' => $this->updatedAt,
        ];
    }
}
