<?php

declare(strict_types=1);

namespace App\Libraries\Files;

use App\Exceptions\BadRequestException;
use App\Exceptions\ValidationException;
use App\Support\Files\ProcessedFile;

class Base64Processor implements FileProcessorInterface
{
    public function process(mixed $input, array $options = []): ProcessedFile
    {
        if (!is_string($input) || str_contains($input, 'Resource id #')) {
            throw new BadRequestException(lang('Files.invalid_file_object'));
        }

        // Detect Data URI or Raw Base64
        if (preg_match('/^data:(\w+\/[-+.\w]+);base64,(.+)$/', $input, $matches)) {
            $mimeType = $matches[1];
            $base64Data = $matches[2];
        } else {
            $base64Data = $input;
            $mimeType = $options['mimeType'] ?? 'application/octet-stream';
        }

        $contents = base64_decode($base64Data, true);
        if ($contents === false) {
            throw new BadRequestException(lang('Files.invalid_file_object'));
        }

        if ($mimeType === 'application/octet-stream') {
            $finfo = new \finfo(FILEINFO_MIME_TYPE);
            $mimeType = $finfo->buffer($contents);
        }

        $size = strlen($contents);
        $this->validate($size, $mimeType, $options);

        $extension = \Config\Mimes::guessExtensionFromType($mimeType) ?? 'bin';
        $originalName = $options['filename'] ?? ('file.' . $extension);

        $stream = fopen('php://temp', 'r+b');
        if ($stream === false) {
            throw new \RuntimeException(lang('Files.storage_error'));
        }

        fwrite($stream, $contents);
        rewind($stream);

        return new ProcessedFile(
            originalName: $originalName,
            mimeType: $mimeType,
            size: $size,
            extension: $extension,
            contents: $stream
        );
    }

    private function validate(int $size, string $mimeType, array $options): void
    {
        $maxSize = $options['maxSize'] ?? (int) env('FILE_MAX_SIZE', 10485760);
        if ($size > $maxSize) {
            throw new ValidationException(lang('Files.file_too_large'), ['file' => lang('Files.file_too_large')]);
        }

        $extension = \Config\Mimes::guessExtensionFromType($mimeType) ?? 'bin';
        $allowedTypes = $options['allowedTypes'] ?? explode(',', env('FILE_ALLOWED_TYPES', 'jpg,jpeg,png,gif,pdf,doc,docx,txt,zip'));
        if (!in_array(strtolower($extension), $allowedTypes, true)) {
            throw new ValidationException(lang('Files.invalid_file_type'), ['file' => lang('Files.invalid_file_type')]);
        }
    }
}
