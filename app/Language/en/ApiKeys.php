<?php

/**
 * API Key language strings (English)
 */
return [
    // General
    'notFound'    => 'API key not found',
    'idRequired'  => 'API key ID is required',

    // Validation
    'nameRequired'   => 'API key name is required',
    'fieldRequired'  => 'At least one field must be provided for update',

    // Success messages
    'createdSuccess' => 'API key created successfully. Store the key securely — it will not be shown again.',
    'deletedSuccess' => 'API key deleted successfully',

    // Error messages
    'createError'  => 'Failed to create API key',
    'deleteError'  => 'Failed to delete API key',
    'retrieveError' => 'Failed to retrieve created API key',

    // Auth / rate limiting
    'invalidKey'   => 'The provided API key is invalid or inactive',
    'unauthorized' => 'Unauthorized',
];
