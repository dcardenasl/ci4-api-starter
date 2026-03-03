<?php

/**
 * File management language strings (English)
 */
return [
    // Success messages
    'upload_success'      => 'File uploaded successfully',
    'delete_success'      => 'File deleted successfully',

    // Error messages
    'file_required'       => 'File is required',
    'invalid_file_object'  => 'Invalid file object',
    'upload_failed'       => 'File upload failed: {0}',
    'file_too_large'       => 'File size exceeds maximum allowed size',
    'invalid_file_type'    => 'File type not allowed',
    'storage_failed'      => 'Failed to store file',
    'file_not_found'       => 'File not found or access denied',
    'id_required'         => 'File ID is required',
    'unauthorized'       => 'You are not authorized to access this file',
    'save_failed'         => 'Failed to save file metadata',

    // Request messages
    'invalid_request'     => 'Invalid request',
    'storage_error'       => 'Storage error',
    'notFound'           => 'Not found',
    'useDeleteWithContext' => 'Use delete() with FileGetRequestDTO to enforce ownership checks',
    'upload' => [
        'noFile' => 'No file was uploaded or file is invalid',
    ],
];
