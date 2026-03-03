<?php

declare(strict_types=1);

namespace App\Services\System;

use App\DTO\Response\Common\PayloadResponseDTO;
use App\Interfaces\DataTransferObjectInterface;
use App\Models\AuditLogModel;

readonly class CatalogService
{
    public function __construct(
        private AuditLogModel $auditLogModel
    ) {
    }

    public function index(): PayloadResponseDTO
    {
        return PayloadResponseDTO::fromArray([
            'users' => [
                'roles' => [
                    ['value' => 'user', 'label_key' => 'Users.user_role'],
                    ['value' => 'admin', 'label_key' => 'Users.admin_role'],
                    ['value' => 'superadmin', 'label_key' => 'Users.super_admin_role'],
                ],
                'statuses' => [
                    ['value' => 'active', 'label_key' => 'App.yes'],
                    ['value' => 'inactive', 'label_key' => 'App.no'],
                    ['value' => 'pending_approval', 'label_key' => 'Users.pending_approval'],
                    ['value' => 'invited', 'label_key' => 'Users.invited'],
                ],
            ],
            'api_keys' => [
                'statuses' => [
                    ['value' => '1', 'label_key' => 'ApiKeys.active'],
                    ['value' => '0', 'label_key' => 'ApiKeys.inactive'],
                ],
            ],
            'files' => [
                'visibility' => [
                    ['value' => 'private', 'label_key' => 'Files.private'],
                    ['value' => 'public', 'label_key' => 'Files.public'],
                ],
            ],
            'metrics' => [
                'periods' => [
                    ['value' => '1h', 'label' => '1h'],
                    ['value' => '24h', 'label' => '24h'],
                    ['value' => '7d', 'label' => '7d'],
                    ['value' => '30d', 'label' => '30d'],
                ],
            ],
            'pagination' => [
                'limit_options' => [10, 25, 50, 100],
            ],
        ]);
    }

    public function auditFacets(DataTransferObjectInterface $request): PayloadResponseDTO
    {
        /** @var \App\DTO\Request\Catalogs\AuditFacetsRequestDTO $request */
        return PayloadResponseDTO::fromArray([
            'window_days'  => $request->window_days,
            'actions'      => $this->auditLogModel->getActionFacets($request->window_days, $request->limit),
            'entity_types' => $this->auditLogModel->getEntityTypeFacets($request->window_days, $request->limit),
        ]);
    }
}
