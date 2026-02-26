<?php

declare(strict_types=1);

namespace App\Controllers\Api\V1\Identity;

use App\Controllers\ApiController;
use App\DTO\Request\Auth\RegisterRequestDTO;
use CodeIgniter\HTTP\ResponseInterface;

/**
 * Modernized Registration Controller
 *
 * Uses automated DTO validation for user self-registration.
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

    /**
     * Register a new user
     */
    public function register(): ResponseInterface
    {
        return $this->handleRequest('register', RegisterRequestDTO::class);
    }
}
