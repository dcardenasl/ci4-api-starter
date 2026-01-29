<?php

namespace App\Filters;

use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;

class CorsFilter implements FilterInterface
{
    /**
     * Handle CORS preflight requests
     *
     * @param RequestInterface $request
     * @param array|null $arguments
     * @return RequestInterface|ResponseInterface|null
     */
    public function before(RequestInterface $request, $arguments = null)
    {
        // Handle preflight OPTIONS request
        if ($request->getMethod() === 'OPTIONS') {
            $response = service('response');

            // Get allowed origins
            $allowedOrigins = $this->getAllowedOrigins();
            $origin = $request->header('Origin') ? $request->header('Origin')->getValue() : '';

            // Set CORS headers for preflight
            if ($this->isOriginAllowed($origin, $allowedOrigins)) {
                $response->setHeader('Access-Control-Allow-Origin', $origin);
                $response->setHeader('Access-Control-Allow-Credentials', 'true');
            }

            $response->setHeader('Access-Control-Allow-Methods', 'GET, POST, PUT, PATCH, DELETE, OPTIONS');
            $response->setHeader('Access-Control-Allow-Headers', 'Content-Type, Authorization, X-Requested-With, Accept, Origin');
            $response->setHeader('Access-Control-Max-Age', '86400');
            $response->setStatusCode(200);
            $response->setBody('');

            return $response;
        }

        return $request;
    }

    /**
     * Add CORS headers to all responses
     *
     * @param RequestInterface $request
     * @param ResponseInterface $response
     * @param array|null $arguments
     * @return ResponseInterface
     */
    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
        // Get allowed origins
        $allowedOrigins = $this->getAllowedOrigins();
        $origin = $request->header('Origin') ? $request->header('Origin')->getValue() : '';

        // Set CORS headers if origin is allowed
        if ($this->isOriginAllowed($origin, $allowedOrigins)) {
            $response->setHeader('Access-Control-Allow-Origin', $origin);
            $response->setHeader('Access-Control-Allow-Credentials', 'true');
        }

        // Set other CORS headers for all requests
        $response->setHeader('Access-Control-Allow-Methods', 'GET, POST, PUT, PATCH, DELETE, OPTIONS');
        $response->setHeader('Access-Control-Allow-Headers', 'Content-Type, Authorization, X-Requested-With, Accept, Origin');
        $response->setHeader('Access-Control-Max-Age', '86400');

        return $response;
    }

    /**
     * Get allowed origins from environment or use defaults
     *
     * @return array
     */
    private function getAllowedOrigins(): array
    {
        $originsEnv = env('CORS_ALLOWED_ORIGINS', '');

        if (!empty($originsEnv)) {
            return array_map('trim', explode(',', $originsEnv));
        }

        // Default allowed origins
        $defaults = [
            'http://localhost:3000',
            'http://localhost:8080',
            'http://localhost:5173', // Vite default
            'http://127.0.0.1:3000',
            'http://127.0.0.1:8080',
        ];

        // Add production origin if set
        $appUrl = env('app.baseURL', '');
        if (!empty($appUrl)) {
            $defaults[] = rtrim($appUrl, '/');
        }

        return $defaults;
    }

    /**
     * Check if the origin is in the allowed list
     *
     * @param string $origin
     * @param array $allowedOrigins
     * @return bool
     */
    private function isOriginAllowed(string $origin, array $allowedOrigins): bool
    {
        if (empty($origin)) {
            return false;
        }

        // Check for wildcard
        if (in_array('*', $allowedOrigins)) {
            return true;
        }

        // Check exact match
        if (in_array($origin, $allowedOrigins)) {
            return true;
        }

        // Check wildcard subdomain patterns (e.g., *.example.com)
        foreach ($allowedOrigins as $allowed) {
            if (str_contains($allowed, '*')) {
                $pattern = '/^' . str_replace(['*', '.'], ['.*', '\.'], $allowed) . '$/';
                if (preg_match($pattern, $origin)) {
                    return true;
                }
            }
        }

        return false;
    }
}
