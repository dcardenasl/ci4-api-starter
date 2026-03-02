<?php

declare(strict_types=1);

namespace App\DTO\Request\Catalog;

use App\DTO\Request\BaseRequestDTO;

readonly class DemoproductUpdateRequestDTO extends BaseRequestDTO
{
    public ?string $name;

    protected function rules(): array
    {
        return [
            'name' => 'permit_empty|string|max_length[255]',
        ];
    }

    protected function map(array $data): void
    {
        $this->name = isset($data['name']) ? (string) $data['name'] : null;
    }

    public function toArray(): array
    {
        return array_filter([
            'name' => $this->name,
        ], fn ($v) => $v !== null);
    }
}
