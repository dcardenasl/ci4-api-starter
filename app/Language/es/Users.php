<?php

/**
 * Cadenas de idioma relacionadas con usuarios (Español)
 */
return [
    // Mensajes generales
    'notFound'        => 'Usuario no encontrado',
    'idRequired'      => 'El ID del usuario es obligatorio',
    'emailRequired'   => 'El email es obligatorio',
    'passwordRequired' => 'La contraseña es obligatoria',
    'adminPasswordForbidden' => 'Los administradores no pueden definir contraseñas al crear usuarios. Se enviará un correo de invitación.',
    'fieldRequired'   => 'Se requiere al menos un campo (email, nombre, apellido, contraseña, rol)',

    // Mensajes de éxito
    'deletedSuccess'  => 'Usuario eliminado correctamente',
    'invitationSent'  => 'Invitación enviada correctamente',
    'approvedSuccess' => 'Usuario aprobado correctamente',
    'alreadyApproved' => 'El usuario ya está aprobado',
    'cannotApproveInvited' => 'Los usuarios invitados ya están aprobados y deben completar su contraseña desde el enlace de invitación.',
    'invalidApprovalState' => 'No se puede aprobar al usuario desde su estado actual.',

    // Mensajes de error
    'deleteError'     => 'Error al eliminar el usuario',
    'createError'     => 'Error al crear el usuario',

    // Mensajes de validación
    'validation' => [
        'email' => [
            'required'    => 'El email es obligatorio',
            'valid'       => 'Debe proporcionar un email válido',
            'unique'      => 'Este email ya está registrado',
        ],
        'first_name' => [
            'maxLength' => 'El nombre no puede exceder {0} caracteres',
        ],
        'last_name' => [
            'maxLength' => 'El apellido no puede exceder {0} caracteres',
        ],
        'password' => [
            'required'   => 'La contraseña es obligatoria',
            'minLength'  => 'La contraseña debe tener al menos {0} caracteres',
            'complexity' => 'La contraseña debe contener al menos una mayúscula, una minúscula y un número',
        ],
    ],

    // Autenticación
    'auth' => [
        'invalidCredentials' => 'Credenciales inválidas',
        'loginSuccess'       => 'Inicio de sesión exitoso',
        'registerSuccess'    => 'Usuario registrado exitosamente',
        'notAuthenticated'   => 'Usuario no autenticado',
        'credentialsRequired' => 'Se requiere email y contraseña',
    ],
];
