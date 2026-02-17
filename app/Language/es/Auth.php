<?php

/**
 * Cadenas de autenticación (Español)
 */
return [
    // Mensajes JWT
    'headerMissing'           => 'Falta el encabezado de autorización',
    'invalidFormat'           => 'Formato de encabezado de autorización inválido',
    'invalidToken'            => 'Token inválido o expirado',
    'tokenRevoked'            => 'El token ha sido revocado',
    'emailNotVerified'        => 'El correo electrónico no está verificado',
    'accountPendingApproval'  => 'La cuenta está pendiente de aprobación por un administrador',
    'accountSetupRequired'    => 'La cuenta requiere configuración inicial. Revisa tu correo de invitación para definir tu contraseña.',
    'registrationPendingApproval' => 'Registro recibido. Verifica tu correo y espera la aprobación del administrador.',
    'registrationPendingApprovalNoVerification' => 'Registro recibido. Espera la aprobación del administrador.',

    // Mensajes de autenticación
    'authRequired'            => 'Autenticación requerida',
    'insufficientPermissions' => 'Permisos insuficientes',

    // Límite de velocidad
    'rateLimitExceeded'       => 'Límite de solicitudes excedido. Por favor, intente más tarde.',
    'tooManyRequests'         => 'Demasiadas solicitudes. Máximo {0} solicitudes por {1} segundos permitidas.',
    'tooManyLoginAttempts'    => 'Demasiados intentos de autenticación. Máximo {0} intentos por {1} minutos. Por favor, intente más tarde.',
];
