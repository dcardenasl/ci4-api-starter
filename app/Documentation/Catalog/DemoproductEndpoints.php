<?php

declare(strict_types=1);

namespace App\Documentation\Catalog;

use OpenApi\Attributes as OA;

/**
 * OpenAPI placeholders for Demoproduct endpoints.
 *
 * @OA\Tag(name="Demoproducts", description="Demoproduct management")
 */
class DemoproductEndpoints
{
    #[OA\Get(
        path: '/api/v1/demo-products',
        tags: ['Demoproducts'],
        summary: 'List Demoproducts',
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
                            items: new OA\Items(ref: '#/components/schemas/DemoproductResponse')
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
}
