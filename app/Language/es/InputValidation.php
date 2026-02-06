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
        'idRequired'         => '{0} es obligatorio',
        'idMustBePositive'   => '{0} debe ser un entero positivo',

        // Paginación
        'pageMustBePositive'     => 'La página debe ser un entero positivo',
        'perPageMustBePositive'  => 'Los elementos por página deben ser un entero positivo',
        'perPageExceedsMax'      => 'Los elementos por página no pueden exceder 100',
        'sortFieldInvalid'       => 'El campo de ordenamiento contiene caracteres inválidos',
        'sortDirInvalid'         => 'La dirección de ordenamiento debe ser asc o desc',
        'searchTooLong'          => 'La consulta de búsqueda es demasiado larga',

        // Nombres
        'firstNameMaxLength'     => 'El nombre no puede exceder 100 caracteres',
        'lastNameMaxLength'      => 'El apellido no puede exceder 100 caracteres',

        // Email
        'emailRequired'          => 'El email es obligatorio',
        'emailInvalid'           => 'Proporcione una dirección de email válida',
        'emailMaxLength'         => 'El email no puede exceder 255 caracteres',

        // Contraseña
        'passwordRequired'       => 'La contraseña es obligatoria',
        'passwordStrength'       => 'La contraseña debe tener 8-128 caracteres con mayúscula, minúscula, número y carácter especial',
        'newPasswordRequired'    => 'La nueva contraseña es obligatoria',

        // Rol
        'roleInvalid'            => 'El rol debe ser user o admin',

        // OAuth
        'oauthProviderInvalid'     => 'El proveedor OAuth no es compatible',
        'oauthProviderIdMaxLength' => 'El ID del proveedor OAuth no puede exceder 255 caracteres',
        'avatarUrlInvalid'         => 'La URL del avatar debe ser válida',
        'avatarUrlMaxLength'       => 'La URL del avatar no puede exceder 255 caracteres',

        // ID de usuario
        'userIdMustBePositive'   => 'El ID de usuario debe ser un entero positivo',
        'userIdRequired'         => 'El ID de usuario es obligatorio',
    ],

    // ========================================
    // Dominio Auth
    // ========================================
    'auth' => [
        // Login
        'emailRequired'          => 'El email es obligatorio',

        // Token
        'resetTokenRequired'      => 'El token de restablecimiento es obligatorio',
        'resetTokenInvalid'       => 'Formato de token de restablecimiento inválido',
        'verificationTokenRequired' => 'El token de verificación es obligatorio',
        'verificationTokenInvalid'  => 'Formato de token de verificación inválido',
        'refreshTokenRequired'    => 'El token de actualización es obligatorio',
        'refreshTokenInvalid'     => 'Token de actualización inválido',
    ],

    // ========================================
    // Dominio File
    // ========================================
    'file' => [
        'noFileUploaded'         => 'No se subió ningún archivo',
        'fileTooLarge'           => 'El tamaño del archivo no puede exceder 10MB',
        'fileTypeNotAllowed'     => 'El tipo de archivo no está permitido',
    ],

    // ========================================
    // Dominio Token
    // ========================================
    'token' => [
        'refreshTokenRequired'   => 'El token de actualización es obligatorio',
        'refreshTokenInvalid'    => 'Formato de token de actualización inválido',
    ],

    // ========================================
    // Dominio Audit
    // ========================================
    'audit' => [
        'actionInvalidChars'     => 'La acción contiene caracteres inválidos',
        'actionTooLong'          => 'La acción no puede exceder 50 caracteres',
        'entityTypeRequired'     => 'El tipo de entidad es obligatorio',
        'entityTypeInvalidChars' => 'El tipo de entidad contiene caracteres inválidos',
        'entityTypeTooLong'      => 'El tipo de entidad no puede exceder 50 caracteres',
        'entityIdRequired'       => 'El ID de entidad es obligatorio',
        'entityIdMustBePositive' => 'El ID de entidad debe ser un entero positivo',
        'fromDateInvalid'        => 'La fecha desde debe estar en formato Y-m-d',
        'toDateInvalid'          => 'La fecha hasta debe estar en formato Y-m-d',
        'auditLogIdRequired'     => 'El ID del registro de auditoría es obligatorio',
        'auditLogEntityRequired' => 'El tipo de entidad y el ID son obligatorios',
        'auditLogNotFound'       => 'Registro de auditoría no encontrado',
    ],
];
