<?php

declare(strict_types=1);

namespace App\Controllers\Api\V1;

use App\Controllers\ApiController;
use CodeIgniter\HTTP\ResponseInterface;

/**
 * User Controller - CRUD operations
 */
class UserController extends ApiController
{
    protected string $serviceName = 'userService';

    public function approve($id = null): ResponseInterface
    {
        return $this->handleRequest('approve', ['id' => $id]);
    }
}
