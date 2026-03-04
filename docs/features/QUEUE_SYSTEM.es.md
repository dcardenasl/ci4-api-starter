# Sistema de Colas (Playbook)

Este documento es un playbook funcional para capacidades que usan colas.

## Propósito

Usar colas para tareas asincronas (email, request logging, audit logging, jobs de infraestructura) sin bloquear requests HTTP.

## Checklist de implementación

1. Confirmar que el job sea idempotente.
2. Confirmar estrategia de reintentos (`QUEUE_MAX_ATTEMPTS`, `QUEUE_RETRY_AFTER`).
3. Confirmar convencion de nombres de cola (`emails`, `logs`, `audit` o cola de dominio).
4. Agregar/ajustar tests (unit para lógica del job, integration para ejecución en cola).
5. Ejecutar `composer quality`.

## Criterios de aceptación

1. El flujo dispara el enqueue del job esperado.
2. El worker procesa correctamente con `queue:work`.
3. Los fallos son trazables en almacenamiento de failed jobs.
4. No hay lógica de negocio en controllers.

## Referencia técnica canónica

1. Setup runtime, worker, migraciones y troubleshooting: `../tech/QUEUE.md`.
2. Documentos relacionados: `../tech/email.md`, `../tech/request-logging.md`, `../tech/audit-logging.es.md`.

## Notas de la cola de auditoria

- Nombre de cola: `audit` (configurable con `AUDIT_QUEUE_NAME`).
- Politica hibrida:
  - eventos criticos de auditoria se escriben en sincronico;
  - eventos no criticos se encolan en `audit`.
- Si falla el enqueue, la persistencia hace fallback a escritura sincronica.
