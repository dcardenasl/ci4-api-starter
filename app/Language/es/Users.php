<?php

/**
 * Cadenas de idioma relacionadas con usuarios (Español)
 */
return [
    // Mensajes generales
    'notFound'        => 'Usuario no encontrado',
    'idRequired'      => 'El ID del usuario es obligatorio',
    'emailRequired'   => 'El email es obligatorio',
    'usernameRequired' => 'El nombre de usuario es obligatorio',
    'passwordRequired' => 'La contraseña es obligatoria',
    'fieldRequired'   => 'Se requiere al menos un campo (email o nombre de usuario)',

    // Mensajes de éxito
    'deletedSuccess'  => 'Usuario eliminado correctamente',

    // Mensajes de error
    'deleteError'     => 'Error al eliminar el usuario',

    // Mensajes de validación
    'validation' => [
        'email' => [
            'required'    => 'El email es obligatorio',
            'valid'       => 'Debe proporcionar un email válido',
            'unique'      => 'Este email ya está registrado',
        ],
        'username' => [
            'required'     => 'El nombre de usuario es obligatorio',
            'alphaNumeric' => 'El nombre de usuario solo puede contener letras y números',
            'minLength'    => 'El nombre de usuario debe tener al menos {0} caracteres',
            'unique'       => 'Este nombre de usuario ya está en uso',
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
        'credentialsRequired' => 'Se requiere nombre de usuario y contraseña',
    ],
];
