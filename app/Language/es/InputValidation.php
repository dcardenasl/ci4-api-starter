<?php

/**
 * Cadenas de validación de entrada (Español)
 *
 * Contiene todos los mensajes de error de validación para la capa InputValidation.
 * Organizado por dominio (auth, user, file, token, audit) y mensajes comunes.
 */
return [
    // ========================================
    // Mensajes Comunes/Compartidos
    // ========================================
    'common' => [
        // Validación de ID
        'id_required'         => '{0} es obligatorio',
        'id_must_be_positive'   => '{0} debe ser un entero positivo',

        // Paginación
        'page_must_be_positive'     => 'La página debe ser un entero positivo',
        'per_page_must_be_positive'  => 'Los elementos por página deben ser un entero positivo',
        'per_page_exceeds_max'      => 'Los elementos por página no pueden exceder 100',
        'sort_field_invalid'       => 'El campo de ordenamiento contiene caracteres inválidos',
        'sort_dir_invalid'         => 'La dirección de ordenamiento debe ser asc o desc',
        'search_too_long'          => 'La consulta de búsqueda es demasiado larga',

        // Nombres
        'first_name_max_length'     => 'El nombre no puede exceder 100 caracteres',
        'last_name_max_length'      => 'El apellido no puede exceder 100 caracteres',

        // Email
        'email_required'          => 'El email es obligatorio',
        'email_invalid'           => 'Proporcione una dirección de email válida',
        'email_max_length'         => 'El email no puede exceder 255 caracteres',
        'email_already_registered' => 'Este email ya está registrado',

        // Contraseña
        'password_required'       => 'La contraseña es obligatoria',
        'password_strength'       => 'La contraseña debe tener 8-128 caracteres con mayúscula, minúscula, número y carácter especial',
        'new_password_required'    => 'La nueva contraseña es obligatoria',

        // Rol
        'role_invalid'            => 'El rol debe ser user, admin o superadmin',

        // OAuth
        'oauth_provider_invalid'     => 'El proveedor OAuth no es compatible',
        'oauth_provider_id_max_length' => 'El ID del proveedor OAuth no puede exceder 255 caracteres',
        'avatar_url_invalid'         => 'La URL del avatar debe ser válida',
        'avatar_url_max_length'       => 'La URL del avatar no puede exceder 255 caracteres',

        // ID de usuario
        'user_id_must_be_integer'    => 'El ID de usuario debe ser un entero',
        'user_id_must_be_positive'   => 'El ID de usuario debe ser un entero positivo',
        'user_id_required'         => 'El ID de usuario es obligatorio',

        // Configuración de validación
        'unknown_validation_domain' => 'El dominio de validación "{0}" no está registrado',
        'unknown_validation_action' => 'La acción de validación "{0}" no está definida para el dominio "{1}"',
    ],

    // ========================================
    // Dominio Auth
    // ========================================
    'auth' => [
        // Login
        'email_required'          => 'El email es obligatorio',
        'id_token_required'        => 'El token de Google es obligatorio',
        'id_token_invalid'         => 'El token de Google es inválido',
        'client_base_url_invalid'   => 'La URL base del cliente debe ser válida',

        // Token
        'reset_token_required'      => 'El token de restablecimiento es obligatorio',
        'reset_token_invalid'       => 'Formato de token de restablecimiento inválido',
        'verification_token_required' => 'El token de verificación es obligatorio',
        'verification_token_invalid'  => 'Formato de token de verificación inválido',
        'verification_token_min_length' => 'El token de verificación debe tener al menos 10 caracteres',
        'refresh_token_required'    => 'El token de actualización es obligatorio',
        'refresh_token_invalid'     => 'Token de actualización inválido',
    ],

    // ========================================
    // Dominio File
    // ========================================
    'file' => [
        'no_file_uploaded'         => 'No se subió ningún archivo',
        'file_too_large'           => 'El tamaño del archivo no puede exceder 10MB',
        'file_type_not_allowed'     => 'El tipo de archivo no está permitido',
    ],

    // ========================================
    // Dominio Token
    // ========================================
    'token' => [
        'refresh_token_required'   => 'El token de actualización es obligatorio',
        'refresh_token_invalid'    => 'Formato de token de actualización inválido',
    ],

    // ========================================
    // Dominio API Key
    // ========================================
    'apiKey' => [
        'name_required'                => 'El nombre de la clave de API es obligatorio',
        'name_max_length'               => 'El nombre no puede exceder {0} caracteres',
        'key_prefix_required'           => 'El prefijo de la clave es obligatorio',
        'key_prefix_max_length'          => 'El prefijo de la clave no puede exceder {0} caracteres',
        'key_hash_required'             => 'El hash de la clave es obligatorio',
        'key_hash_max_length'            => 'El hash de la clave no puede exceder {0} caracteres',
        'rate_limit_requests_integer'    => 'El límite de solicitudes debe ser un entero',
        'rate_limit_requests_greater_than' => 'El límite de solicitudes debe ser mayor que 0',
        'rate_limit_window_integer'      => 'La ventana de límite debe ser un entero',
        'rate_limit_window_greater_than'  => 'La ventana de límite debe ser mayor que 0',
        'user_rate_limit_integer'        => 'El límite por usuario debe ser un entero',
        'user_rate_limit_greater_than'    => 'El límite por usuario debe ser mayor que 0',
        'ip_rate_limit_integer'          => 'El límite por IP debe ser un entero',
        'ip_rate_limit_greater_than'      => 'El límite por IP debe ser mayor que 0',
    ],

    // ========================================
    // Dominio Audit
    // ========================================
    'audit' => [
        'action_invalid_chars'     => 'La acción contiene caracteres inválidos',
        'action_too_long'          => 'La acción no puede exceder 50 caracteres',
        'entity_type_required'     => 'El tipo de entidad es obligatorio',
        'entity_type_invalid_chars' => 'El tipo de entidad contiene caracteres inválidos',
        'entity_type_too_long'      => 'El tipo de entidad no puede exceder 50 caracteres',
        'entity_id_required'       => 'El ID de entidad es obligatorio',
        'entity_id_must_be_positive' => 'El ID de entidad debe ser un entero positivo',
        'from_date_invalid'        => 'La fecha desde debe estar en formato Y-m-d',
        'to_date_invalid'          => 'La fecha hasta debe estar en formato Y-m-d',
        'audit_log_id_required'     => 'El ID del registro de auditoría es obligatorio',
        'audit_log_entity_required' => 'El tipo de entidad y el ID son obligatorios',
        'audit_log_not_found'       => 'Registro de auditoría no encontrado',
    ],
];
