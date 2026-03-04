# Gestión de Archivos

Descripción funcional del subsistema de archivos.

## Capacidades

1. Carga multipart y base64.
2. Listado paginado de archivos por usuario mediante `FileRepository`.
3. Descarga/local o URL remota según driver.
4. Eliminación atómica con el método `destroy` y validaciones de `SecurityContext`.

## Contratos

1. Request DTOs para upload/index/show/destroy.
2. Response DTOs para metadatos y descarga.
3. Services puros con validación de ownership mediante `SecurityContext`.


## Calidad

1. Unit tests del servicio.
2. Feature tests de endpoints.
3. Quality gates (`composer quality`).

## Referencia

Documento completo en inglés: `FILE_MANAGEMENT.md`.
