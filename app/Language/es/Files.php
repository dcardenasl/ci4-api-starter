<?php

/**
 * Cadenas de gestión de archivos (Español)
 */
return [
    // Mensajes de éxito
    'upload_success'      => 'Archivo subido exitosamente',
    'delete_success'      => 'Archivo eliminado exitosamente',

    // Mensajes de error
    'file_required'       => 'El archivo es requerido',
    'invalid_file_object'  => 'Objeto de archivo inválido',
    'upload_failed'       => 'Error al subir el archivo: {0}',
    'file_too_large'       => 'El tamaño del archivo excede el máximo permitido',
    'invalid_file_type'    => 'Tipo de archivo no permitido',
    'storage_failed'      => 'Error al almacenar el archivo',
    'file_not_found'       => 'Archivo no encontrado o acceso denegado',
    'id_required'         => 'El ID del archivo es obligatorio',
    'unauthorized'       => 'No está autorizado para acceder a este archivo',
    'save_failed'         => 'Error al guardar los metadatos del archivo',
    'malware_detected'    => 'Se detectó malware o virus en el archivo subido',
    'temp_file_creation_failed' => 'No se pudo crear un archivo temporal para el escaneo de virus',
    'virus_scan_read_error' => 'No se puede leer el archivo para el escaneo de virus',

    // Mensajes de solicitud
    'invalid_request'     => 'Solicitud inválida',
    'storage_error'       => 'Error de almacenamiento',
    'notFound'           => 'No encontrado',
    'useDeleteWithContext' => 'Use delete() con FileGetRequestDTO para forzar la validación de propiedad',
    'upload' => [
        'noFile' => 'No se subió ningún archivo o el archivo no es válido',
    ],
];
