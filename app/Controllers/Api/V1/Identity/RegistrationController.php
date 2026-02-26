<?php

declare(strict_types=1);

namespace App\Controllers\Api\V1\Identity;

use App\Controllers\ApiController;
use CodeIgniter\HTTP\ResponseInterface;

/**
 * Registration Controller
 */
class RegistrationController extends ApiController
{
    protected string $serviceName = 'authService';

    /**
     * @var array<string, int>
     */
    protected array $statusCodes = [
        'register' => 201,
    ];

    public function register(): ResponseInterface
    {
        $dto = $this->getDTO(\App\DTO\Request\Auth\RegisterRequestDTO::class);

        return $this->handleRequest(
            fn () => $this->getService()->register($dto)
        );
    }
}
