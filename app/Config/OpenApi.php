<?php

namespace App\Config;

use OpenApi\Attributes as OA;

#[OA\OpenApi(
    openapi: '3.0.0',
)]
#[OA\Info(
    version: '1.0.0',
    title: 'CodeIgniter 4 API Starter',
    description: 'RESTful API built with CodeIgniter 4, featuring JWT authentication, standardized responses, and comprehensive documentation.',
)]
#[OA\Server(
    url: 'http://localhost:8080',
    description: 'Local development server'
)]
#[OA\SecurityScheme(
    securityScheme: 'bearerAuth',
    type: 'http',
    scheme: 'bearer',
    bearerFormat: 'JWT',
    description: 'Enter your JWT token in the format: Bearer {token}'
)]
#[OA\Tag(
    name: 'Authentication',
    description: 'User authentication endpoints'
)]
#[OA\Tag(
    name: 'Users',
    description: 'User management endpoints'
)]
#[OA\Tag(
    name: 'Files',
    description: 'File management endpoints'
)]
#[OA\Tag(
    name: 'Metrics',
    description: 'Operational metrics endpoints'
)]
#[OA\Tag(
    name: 'Audit',
    description: 'Audit log endpoints'
)]
#[OA\Tag(
    name: 'Health',
    description: 'Health and readiness endpoints'
)]
class OpenApi
{
    // This class only holds OpenAPI annotations
}
