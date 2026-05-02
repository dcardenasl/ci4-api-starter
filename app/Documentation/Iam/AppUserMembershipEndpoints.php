<?php

declare(strict_types=1);

namespace App\Documentation\Iam;

use OpenApi\Attributes as OA;

/**
 * OpenAPI definitions for AppUserMembership endpoints.
 *
 * @OA\Tag(name="Iam", description="Iam management")
 */
class AppUserMembershipEndpoints
{
    #[OA\Get(
        path: '/api/v1/memberships',
        tags: ['Iam'],
        summary: 'List AppUserMemberships',
        responses: [
            new OA\Response(
                response: 200,
                description: 'List retrieved successfully',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'status', type: 'string', example: 'success'),
                        new OA\Property(
                            property: 'data',
                            type: 'array',
                            items: new OA\Items(ref: '#/components/schemas/AppUserMembershipResponse')
                        ),
                    ],
                    type: 'object'
                )
            ),
        ]
    )]
    public function index()
    {
    }

    #[OA\Post(
        path: '/api/v1/memberships',
        tags: ['Iam'],
        summary: 'Create new AppUserMembership',
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(ref: '#/components/schemas/AppUserMembershipCreateRequest')
        ),
        responses: [
            new OA\Response(response: 201, description: 'Created successfully'),
            new OA\Response(response: 422, description: 'Validation error')
        ]
    )]
    public function store()
    {
    }

    #[OA\Get(
        path: '/api/v1/memberships/{id}',
        tags: ['Iam'],
        summary: 'Get AppUserMembership by ID',
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Found',
                content: new OA\JsonContent(ref: '#/components/schemas/AppUserMembershipResponse')
            ),
            new OA\Response(response: 404, description: 'Not found')
        ]
    )]
    public function show()
    {
    }

    #[OA\Put(
        path: '/api/v1/memberships/{id}',
        tags: ['Iam'],
        summary: 'Update existing AppUserMembership',
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(ref: '#/components/schemas/AppUserMembershipUpdateRequest')
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'Updated successfully',
                content: new OA\JsonContent(ref: '#/components/schemas/AppUserMembershipResponse')
            ),
            new OA\Response(response: 404, description: 'Not found'),
            new OA\Response(response: 422, description: 'Validation error')
        ]
    )]
    public function update()
    {
    }

    #[OA\Delete(
        path: '/api/v1/memberships/{id}',
        tags: ['Iam'],
        summary: 'Delete AppUserMembership by ID',
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))
        ],
        responses: [
            new OA\Response(response: 204, description: 'Deleted successfully'),
            new OA\Response(response: 404, description: 'Not found')
        ]
    )]
    public function delete()
    {
    }
}
