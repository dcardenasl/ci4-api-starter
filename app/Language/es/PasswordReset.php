<?php

/**
 * Cadenas de restablecimiento de contraseña (Español)
 */
return [
    // Mensajes de éxito
    'linkSent'           => 'Si existe una cuenta con ese correo, se ha enviado un enlace de restablecimiento de contraseña.',
    'tokenValid'         => 'Token válido',
    'passwordReset'      => 'Tu contraseña ha sido restablecida exitosamente',

    // Mensajes de error
    'emailRequired'            => 'Se requiere un correo electrónico válido',
    'tokenRequired'            => 'Se requiere el token de restablecimiento y el correo',
    'invalidToken'             => 'Token de restablecimiento inválido o expirado',
    'userNotFound'             => 'Usuario no encontrado',
    'allFieldsRequired'        => 'Todos los campos son requeridos',
    'passwordMinLength'        => 'La contraseña debe tener al menos 8 caracteres',
    'passwordMaxLength'        => 'La contraseña no debe exceder 128 caracteres',
    'passwordComplexity'       => 'La contraseña debe contener al menos una letra mayúscula, una minúscula, un número y un carácter especial',
    'passwordValidationFailed' => 'La validación de la contraseña falló',

    // Mensajes de datos de respuesta
    'sentMessage'        => 'Enlace de restablecimiento enviado',
    'resetMessage'       => 'Contraseña restablecida exitosamente',
    'reactivationRequested' => 'Reactivación de cuenta solicitada y pendiente de aprobación del administrador',
];
