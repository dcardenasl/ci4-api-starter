# Gestión de Archivos

Descripción funcional del subsistema de archivos.

## Capacidades

1. Carga multipart y base64.
2. Listado paginado de archivos por usuario.
3. Descarga/local o URL remota según driver.
4. Eliminación con validaciones de autorización.

## Contratos

1. Request DTOs para upload/index/show/delete.
2. Response DTOs para metadatos y descarga.
3. Services puros con validación de ownership.

## Calidad

1. Unit tests del servicio.
2. Feature tests de endpoints.
3. Quality gates (`composer quality`).

## Referencia

Documento completo en inglés: `FILE_MANAGEMENT.md`.
