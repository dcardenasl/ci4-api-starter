# Queue System (Feature Playbook)

This document is a feature playbook for queue-enabled capabilities.

## Purpose

Use queues for asynchronous tasks (email, request logging, infrastructure jobs) without blocking HTTP requests.

## Implementation Checklist

1. Confirm job class is idempotent.
2. Confirm retry strategy (`QUEUE_MAX_ATTEMPTS`, `QUEUE_RETRY_AFTER`).
3. Confirm queue name conventions (`emails`, `logs`, or domain queue).
4. Add/adjust tests (unit for job logic, integration for queue execution).
5. Run `composer quality`.

## Acceptance Criteria

1. Triggering flow enqueues the expected job.
2. Worker processes the job successfully with `queue:work`.
3. Failed jobs are traceable in failure storage.
4. No business logic leaks into controllers.

## Canonical Technical Reference

1. Runtime setup, workers, migrations, and troubleshooting: `../tech/QUEUE.md`.
2. Related subsystem docs: `../tech/email.md`, `../tech/request-logging.md`.
