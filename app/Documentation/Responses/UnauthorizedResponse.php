<?php

declare(strict_types=1);

namespace App\Documentation\Responses;

use OpenApi\Attributes as OA;

/**
 * Unauthorized Response
 *
 * Standard 401 Unauthorized response used by all protected endpoints.
 * Returned when JWT token is missing, invalid, or expired.
 */
#[OA\Response(
    response: 'UnauthorizedResponse',
    description: 'Unauthorized - Invalid or missing authentication token',
    content: new OA\JsonContent(
        properties: [
            new OA\Property(
                property: 'success',
                type: 'boolean',
                description: 'Operation success status',
                example: false
            ),
            new OA\Property(
                property: 'message',
                type: 'string',
                description: 'Error message',
                example: 'Authorization header missing'
            ),
        ]
    )
)]
class UnauthorizedResponse
{
}
