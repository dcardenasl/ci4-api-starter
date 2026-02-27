# Sistema de Colas

Resumen operativo del sistema de colas.

## Objetivo

Ejecutar tareas asíncronas (emails, logging, trabajos de infraestructura) sin bloquear el request.

## Principios

1. Jobs idempotentes.
2. Reintentos controlados.
3. Observabilidad de fallos y jobs pendientes.
4. Servicios desacoplados de detalles de transporte.

## Validación

1. Integration tests de `QueueManager`.
2. Unit tests de jobs críticos.
3. Verificación en `composer quality`.

## Referencia

Documento completo en inglés: `QUEUE_SYSTEM.md`.
