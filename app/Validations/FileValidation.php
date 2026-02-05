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
            'index' => $this->paginationRules(),

            'show' => $this->idRules(),

            'upload' => [
                'file' => 'uploaded[file]|max_size[file,10240]',
            ],

            'delete' => $this->idRules(),

            default => [],
        };
    }

    /**
     * {@inheritDoc}
     */
    public function getMessages(string $action): array
    {
        return match ($action) {
            'index' => $this->paginationMessages(),

            'show', 'delete' => $this->idMessages(),

            'upload' => [
                'file.uploaded' => lang('InputValidation.file.noFileUploaded'),
                'file.max_size' => lang('InputValidation.file.fileTooLarge'),
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
