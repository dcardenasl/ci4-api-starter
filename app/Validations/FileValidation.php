<?php

declare(strict_types=1);

namespace App\Validations;

/**
 * File Validation
 *
 * Validation rules for file upload and management actions.
 */
class FileValidation extends BaseValidation
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
                    'user_id' => 'required|is_natural_no_zero',
                ]
            ),

            'show' => $this->mergeRules(
                $this->idRules(),
                [
                    'user_id' => 'required|is_natural_no_zero',
                ]
            ),

            'upload' => [
                'user_id' => 'required|is_natural_no_zero',
                'file' => 'required',
            ],

            'delete' => $this->mergeRules(
                $this->idRules(),
                [
                    'user_id' => 'required|is_natural_no_zero',
                ]
            ),

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
                    'user_id.required'           => lang('InputValidation.common.userIdRequired'),
                    'user_id.is_natural_no_zero' => lang('InputValidation.common.userIdMustBePositive'),
                ]
            ),

            'show', 'delete' => $this->mergeMessages(
                $this->idMessages(),
                [
                    'user_id.required'           => lang('InputValidation.common.userIdRequired'),
                    'user_id.is_natural_no_zero' => lang('InputValidation.common.userIdMustBePositive'),
                ]
            ),

            'upload' => [
                'user_id.required' => lang('InputValidation.common.userIdRequired'),
                'user_id.is_natural_no_zero' => lang('InputValidation.common.userIdMustBePositive'),
                'file.required' => lang('InputValidation.file.noFileUploaded'),
            ],

            default => [],
        };
    }

    /**
     * Get allowed file types rule string
     *
     * @param array<string> $types Array of allowed extensions (e.g., ['jpg', 'png', 'pdf'])
     * @return string
     */
    public function getAllowedTypesRule(array $types): string
    {
        return 'ext_in[file,' . implode(',', $types) . ']';
    }

    /**
     * Get allowed MIME types rule string
     *
     * @param array<string> $mimeTypes Array of allowed MIME types
     * @return string
     */
    public function getAllowedMimeTypesRule(array $mimeTypes): string
    {
        return 'mime_in[file,' . implode(',', $mimeTypes) . ']';
    }
}
