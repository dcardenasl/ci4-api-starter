<?php

declare(strict_types=1);

namespace App\Validations;

/**
 * Audit Validation
 *
 * Validation rules for audit log actions.
 */
class AuditValidation extends BaseValidation
{
    /**
     * {@inheritDoc}
     */
    public function getRules(string $action): array
    {
        return match ($action) {
            'index' => $this->mergeRules(
                $this->paginationRules(),
                [
                    'action'      => 'permit_empty|alpha_dash|max_length[50]',
                    'entity_type' => 'permit_empty|alpha_dash|max_length[50]',
                    'user_id'     => 'permit_empty|is_natural_no_zero',
                    'from_date'   => 'permit_empty|valid_date[Y-m-d]',
                    'to_date'     => 'permit_empty|valid_date[Y-m-d]',
                ]
            ),

            'show' => $this->idRules(),

            'by_entity' => [
                'entity_type' => 'required|alpha_dash|max_length[50]',
                'entity_id'   => 'required|is_natural_no_zero',
            ],

            'by_user' => [
                'user_id' => 'required|is_natural_no_zero',
            ],

            default => [],
        };
    }

    /**
     * {@inheritDoc}
     */
    public function getMessages(string $action): array
    {
        return match ($action) {
            'index' => $this->mergeMessages(
                $this->paginationMessages(),
                [
                    'action.alpha_dash'           => 'Action contains invalid characters',
                    'action.max_length'           => 'Action cannot exceed 50 characters',
                    'entity_type.alpha_dash'      => 'Entity type contains invalid characters',
                    'entity_type.max_length'      => 'Entity type cannot exceed 50 characters',
                    'user_id.is_natural_no_zero'  => 'User ID must be a positive integer',
                    'from_date.valid_date'        => 'From date must be in Y-m-d format',
                    'to_date.valid_date'          => 'To date must be in Y-m-d format',
                ]
            ),

            'show' => $this->idMessages(),

            'by_entity' => [
                'entity_type.required'           => 'Entity type is required',
                'entity_type.alpha_dash'         => 'Entity type contains invalid characters',
                'entity_type.max_length'         => 'Entity type cannot exceed 50 characters',
                'entity_id.required'             => 'Entity ID is required',
                'entity_id.is_natural_no_zero'   => 'Entity ID must be a positive integer',
            ],

            'by_user' => [
                'user_id.required'              => 'User ID is required',
                'user_id.is_natural_no_zero'    => 'User ID must be a positive integer',
            ],

            default => [],
        };
    }
}
