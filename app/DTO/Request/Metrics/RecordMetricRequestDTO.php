<?php

declare(strict_types=1);

namespace App\DTO\Request\Metrics;

use App\Interfaces\DataTransferObjectInterface;

/**
 * Record Metric Request DTO
 */
readonly class RecordMetricRequestDTO implements DataTransferObjectInterface
{
    public string $name;
    public float $value;
    public array $tags;

    public function __construct(array $data)
    {
        // REUTILIZACIÓN: Validación 'metrics.record'
        if (empty($data['name'])) {
            throw new \App\Exceptions\ValidationException(
                lang('Api.validationFailed'),
                ['name' => lang('Metrics.nameRequired')]
            );
        }

        $this->name = (string) $data['name'];
        $this->value = (float) ($data['value'] ?? 0);
        $this->tags = (array) ($data['tags'] ?? []);
    }

    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'value' => $this->value,
            'tags' => $this->tags,
        ];
    }
}
