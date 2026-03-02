<?php

declare(strict_types=1);

namespace App\Controllers\Api\V1\Identity;

use App\Controllers\ApiController;
use App\DTO\Request\Auth\RegisterRequestDTO;
use App\Interfaces\Auth\AuthServiceInterface;
use CodeIgniter\HTTP\ResponseInterface;
use Config\Services;

/**
 * Modernized Registration Controller
 *
 * Uses automated DTO validation for user self-registration.
 */
class RegistrationController extends ApiController
{
    protected AuthServiceInterface $authService;

    protected function resolveDefaultService(): object
    {
        $this->authService = Services::authService();

        return $this->authService;
    }

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
