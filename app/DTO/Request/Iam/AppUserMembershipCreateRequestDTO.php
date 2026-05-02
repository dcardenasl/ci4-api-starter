<?php

declare(strict_types=1);

namespace App\DTO\Request\Iam;

use App\DTO\Request\BaseRequestDTO;
use OpenApi\Attributes as OA;

#[OA\Schema(schema: 'AppUserMembershipCreateRequest')]
readonly class AppUserMembershipCreateRequestDTO extends BaseRequestDTO
{
    #[OA\Property(description: 'user_id', type: 'integer')]
    public int $user_id;
    #[OA\Property(description: 'application_id', type: 'integer')]
    public int $application_id;
    #[OA\Property(description: 'status', type: 'string')]
    public string $status;

    public function rules(): array
    {
        return [
            'user_id' => 'required|integer',
            'application_id' => 'required|integer',
            'status' => 'permit_empty|string|max_length[255]',
        ];
    }

    protected function map(array $data): void
    {
        $this->user_id = (int) ($data['user_id'] ?? 0);
        $this->application_id = (int) ($data['application_id'] ?? 0);
        $this->status = (string) ($data['status'] ?? '');
    }

    public function toArray(): array
    {
        return [
            'user_id' => $this->user_id,
            'application_id' => $this->application_id,
            'status' => $this->status,
        ];
    }
}
