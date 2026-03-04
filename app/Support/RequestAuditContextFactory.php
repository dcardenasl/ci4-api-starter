<?php

declare(strict_types=1);

namespace App\Support;

use App\DTO\SecurityContext;
use CodeIgniter\HTTP\RequestInterface;

/**
 * Builds consistent audit context metadata from HTTP requests.
 */
class RequestAuditContextFactory
{
    /**
     * @return array<string, mixed>
     */
    public function buildMetadata(RequestInterface $request, array $overrides = []): array
    {
        $requestId = trim((string) $request->getHeaderLine('X-Request-Id'));
        if ($requestId === '') {
            $requestId = $this->generateRequestId();
        }

        $metadata = [
            'ip_address' => $request->getIPAddress(),
            'user_agent' => $request->getHeaderLine('User-Agent'),
            'request_id' => $requestId,
        ];

        if (method_exists($request, 'getLocale')) {
            $metadata['locale'] = (string) $request->getLocale();
        }

        return array_merge($metadata, $overrides);
    }

    public function createContext(RequestInterface $request, ?int $userId = null, ?string $userRole = null, array $overrides = []): SecurityContext
    {
        return new SecurityContext(
            $userId,
            $userRole,
            $this->buildMetadata($request, $overrides)
        );
    }

    private function generateRequestId(): string
    {
        try {
            return bin2hex(random_bytes(16));
        } catch (\Throwable) {
            return uniqid('req_', true);
        }
    }
}
