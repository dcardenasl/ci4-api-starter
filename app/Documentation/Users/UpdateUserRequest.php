<?php

declare(strict_types=1);

namespace App\Documentation\Users;

use OpenApi\Attributes as OA;

/**
 * Update User Request Body
 *
 * Request schema for updating an existing user.
 * All fields are optional - only include fields to update.
 */
#[OA\RequestBody(
    request: 'UpdateUserRequest',
    description: 'Data for updating a user',
    required: true,
    content: new OA\JsonContent(
        properties: [
            new OA\Property(
                property: 'username',
                type: 'string',
                description: 'Updated username',
                example: 'updateduser'
            ),
            new OA\Property(
                property: 'email',
                type: 'string',
                format: 'email',
                description: 'Updated email address',
                example: 'updated@example.com'
            ),
        ]
    )
)]
class UpdateUserRequest
{
}
