<?php

declare(strict_types=1);

namespace App\Controllers\Api\V1\Auth;

use App\Controllers\ApiController;
use App\Interfaces\Auth\ServiceTokenServiceInterface;
use CodeIgniter\HTTP\ResponseInterface;
use Config\Services;

/**
 * Service Token Controller
 *
 * Issues OAuth client_credentials-style JWTs to domain applications. The
 * caller authenticates with `X-App-Key` (validated by AppKeyRequiredFilter);
 * the service re-reads the header so it stays decoupled from the request
 * subclass used in tests vs. production.
 */
class ServiceTokenController extends ApiController
{
    protected ServiceTokenServiceInterface $serviceTokenService;

    protected function resolveDefaultService(): object
    {
        $this->serviceTokenService = Services::serviceTokenService();

        return $this->serviceTokenService;
    }

    public function issue(): ResponseInterface
    {
        return $this->handleRequest(function (): mixed {
            $rawKey = (string) $this->request->getHeaderLine('X-App-Key');

            return $this->serviceTokenService->issue($rawKey);
        });
    }
}
