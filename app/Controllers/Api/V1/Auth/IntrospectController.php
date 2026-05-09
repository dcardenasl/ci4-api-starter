<?php

declare(strict_types=1);

namespace App\Controllers\Api\V1\Auth;

use App\DTO\Request\Auth\IntrospectRequestDTO;
use App\Interfaces\Auth\TokenIntrospectionServiceInterface;
use CodeIgniter\HTTP\ResponseInterface;
use Config\Services;
use dcardenasl\Ci4ApiCore\Http\ApiController;

/**
 * Token Introspection Controller
 *
 * Exposes JWT validation as an HTTP endpoint so domain apps can verify
 * user tokens without sharing the JWT secret. Caller is authenticated
 * via X-App-Key (appKeyRequired filter), which also identifies which
 * application the response scope must be resolved against.
 *
 * The controller re-reads the X-App-Key header (instead of trusting an
 * `appKeyId` previously stamped on the request by the filter) so it stays
 * decoupled from the concrete request subclass — see ServiceTokenController
 * for the same pattern. By the time this method runs, the filter has
 * already validated the header, so the lookup below is guaranteed to hit.
 */
class IntrospectController extends ApiController
{
    protected TokenIntrospectionServiceInterface $introspectionService;

    protected function resolveDefaultService(): object
    {
        $this->introspectionService = Services::tokenIntrospectionService();

        return $this->introspectionService;
    }

    public function introspect(): ResponseInterface
    {
        return $this->handleRequest(
            function (IntrospectRequestDTO $dto) {
                return $this->introspectionService->introspect(
                    $dto,
                    $this->callerApplicationId()
                );
            },
            IntrospectRequestDTO::class
        );
    }

    /**
     * Resolve the caller's application id from the X-App-Key header. Returns
     * null when the API key is not bound to an application — callers in that
     * state get the JWT-baked scope verbatim.
     */
    private function callerApplicationId(): ?int
    {
        $rawKey = (string) $this->request->getHeaderLine('X-App-Key');
        if ($rawKey === '') {
            return null;
        }

        $hash   = Services::apiKeyMaterialService()->hash($rawKey);
        $apiKey = Services::apiKeyRepository()->findByHash($hash);
        if ($apiKey === null) {
            return null;
        }

        $applicationId = $apiKey->application_id ?? null;

        return $applicationId !== null ? (int) $applicationId : null;
    }
}
