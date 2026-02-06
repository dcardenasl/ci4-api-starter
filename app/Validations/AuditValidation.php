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
                    'action.alpha_dash'          => lang('InputValidation.audit.actionInvalidChars'),
                    'action.max_length'          => lang('InputValidation.audit.actionTooLong'),
                    'entity_type.alpha_dash'     => lang('InputValidation.audit.entityTypeInvalidChars'),
                    'entity_type.max_length'     => lang('InputValidation.audit.entityTypeTooLong'),
                    'user_id.is_natural_no_zero' => lang('InputValidation.common.userIdMustBePositive'),
                    'from_date.valid_date'       => lang('InputValidation.audit.fromDateInvalid'),
                    'to_date.valid_date'         => lang('InputValidation.audit.toDateInvalid'),
                ]
            ),

            'show' => $this->idMessages(),

            'by_entity' => [
                'entity_type.required'         => lang('InputValidation.audit.entityTypeRequired'),
                'entity_type.alpha_dash'       => lang('InputValidation.audit.entityTypeInvalidChars'),
                'entity_type.max_length'       => lang('InputValidation.audit.entityTypeTooLong'),
                'entity_id.required'           => lang('InputValidation.audit.entityIdRequired'),
                'entity_id.is_natural_no_zero' => lang('InputValidation.audit.entityIdMustBePositive'),
            ],

            'by_user' => [
                'user_id.required'             => lang('InputValidation.common.userIdRequired'),
                'user_id.is_natural_no_zero'   => lang('InputValidation.common.userIdMustBePositive'),
            ],

            default => [],
        };
    }
}
