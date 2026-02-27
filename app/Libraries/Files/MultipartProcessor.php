<?php

declare(strict_types=1);

namespace App\Libraries\Files;

use App\Exceptions\BadRequestException;
use App\Exceptions\ValidationException;
use App\Support\Files\ProcessedFile;
use CodeIgniter\HTTP\Files\UploadedFile;

class MultipartProcessor implements FileProcessorInterface
{
    public function process(mixed $input, array $options = []): ProcessedFile
    {
        if (!$input instanceof UploadedFile) {
            throw new BadRequestException(lang('Files.invalidFileObject'));
        }

        if (!$input->isValid()) {
            throw new BadRequestException(lang('Files.uploadFailed', [$input->getErrorString()]));
        }

        $this->validate($input, $options);

        $stream = fopen($input->getTempName(), 'rb');
        if ($stream === false) {
            throw new \RuntimeException(lang('Files.storageError'));
        }

        return new ProcessedFile(
            originalName: $input->getName(),
            mimeType: $input->getMimeType(),
            size: (int) $input->getSize(),
            extension: $input->getExtension(),
            contents: $stream
        );
    }

    private function validate(UploadedFile $file, array $options): void
    {
        $maxSize = $options['maxSize'] ?? (int) env('FILE_MAX_SIZE', 20971520);
        if ($file->getSize() > $maxSize) {
            throw new ValidationException(lang('Files.fileTooLarge'), ['file' => lang('Files.fileTooLarge')]);
        }

        $allowedTypes = $options['allowedTypes'] ?? explode(',', env('FILE_ALLOWED_TYPES', 'jpg,jpeg,png,gif,pdf'));
        if (!in_array(strtolower($file->getExtension()), $allowedTypes, true)) {
            throw new ValidationException(lang('Files.invalidFileType'), ['file' => lang('Files.invalidFileType')]);
        }
    }
}
