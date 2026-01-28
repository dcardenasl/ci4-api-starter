<?php

namespace App\Filters;

use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;
use App\Services\JwtService;
use Config\Services;

class JwtAuthFilter implements FilterInterface
{
    public function before(RequestInterface $request, $arguments = null)
    {
        $jwtService = new JwtService();

        $authHeader = $request->getHeaderLine('Authorization');

        if (empty($authHeader)) {
            return Services::response()
                ->setJSON([
                    'success' => false,
                    'message' => 'Authorization header missing',
                ])
                ->setStatusCode(401);
        }

        if (!preg_match('/Bearer\s+(.*)$/i', $authHeader, $matches)) {
            return Services::response()
                ->setJSON([
                    'success' => false,
                    'message' => 'Invalid authorization header format',
                ])
                ->setStatusCode(401);
        }

        $token = $matches[1];

        if (!$jwtService->validate($token)) {
            return Services::response()
                ->setJSON([
                    'success' => false,
                    'message' => 'Invalid or expired token',
                ])
                ->setStatusCode(401);
        }

        $decoded = $jwtService->decode($token);
        $request->userId = $decoded->uid;
        $request->userRole = $decoded->role;
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
        // Do nothing
    }
}
