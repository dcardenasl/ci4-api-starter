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
        #[OA\Property(property: 'application_name', description: 'Display name of the related application', type: 'string', nullable: true)]
        public ?string $application_name = null,
        #[OA\Property(property: 'user_email', description: 'Email of the related user', type: 'string', nullable: true)]
        public ?string $user_email = null,
        #[OA\Property(property: 'user_label', description: 'Human-readable label "First Last <email>" for the related user', type: 'string', nullable: true)]
        public ?string $user_label = null,
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
            'application_name' => $this->application_name,
            'user_email' => $this->user_email,
            'user_label' => $this->user_label,
            'created_at' => $this->createdAt,
            'updated_at' => $this->updatedAt,
        ];
    }
}
