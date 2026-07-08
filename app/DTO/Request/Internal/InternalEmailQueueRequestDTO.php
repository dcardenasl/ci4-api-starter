<?php

declare(strict_types=1);

namespace App\DTO\Request\Internal;

use dcardenasl\Ci4ApiCore\Dto\BaseRequestDTO;
use OpenApi\Attributes as OA;

/**
 * Internal Email Queue Request DTO
 *
 * Carries the payload a trusted Domain app sends to have the Hub queue an
 * email on its behalf. Authentication of the caller is handled by the
 * appKeyRequired filter (X-App-Key header), not by this DTO.
 */
#[OA\Schema(
    schema: 'InternalEmailQueueRequest',
    title: 'Internal Email Queue Request',
    description: 'Email payload to be queued by the Hub on behalf of a trusted Domain app',
    required: ['to', 'subject', 'message']
)]
readonly class InternalEmailQueueRequestDTO extends BaseRequestDTO
{
    #[OA\Property(description: 'Recipient email address', example: 'user@example.com')]
    public string $to;

    #[OA\Property(description: 'Email subject line', example: 'Your order has shipped')]
    public string $subject;

    #[OA\Property(description: 'Email body (HTML)', example: '<p>Your order is on its way.</p>')]
    public string $message;

    #[OA\Property(description: 'Plain-text fallback body', example: 'Your order is on its way.', nullable: true)]
    public ?string $text_message;

    /**
     * @return array<string, string>
     */
    public function rules(): array
    {
        return [
            'to'      => 'required|valid_email',
            'subject' => 'required|string|max_length[500]',
            'message' => 'required|string',
        ];
    }

    /**
     * @param array<string, mixed> $data
     */
    protected function map(array $data): void
    {
        $this->to           = (string) ($data['to'] ?? '');
        $this->subject      = (string) ($data['subject'] ?? '');
        $this->message      = (string) ($data['message'] ?? '');
        $this->text_message = isset($data['text_message']) && $data['text_message'] !== '' ? (string) $data['text_message'] : null;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'to'           => $this->to,
            'subject'      => $this->subject,
            'message'      => $this->message,
            'text_message' => $this->text_message,
        ];
    }
}
