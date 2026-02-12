# Cola y Jobs (CI4)

Este proyecto incluye una cola en base de datos y un worker CLI. Se usa principalmente para el sistema de email (verificación, reset de contraseña, invitaciones, etc.).

## Cómo funciona

- Los jobs se guardan en la tabla `jobs`.
- Los jobs fallidos se mueven a `failed_jobs`.
- Un worker CLI procesa jobs desde una cola con nombre.
- Los emails usan la cola `emails`.

Ubicaciones principales:
- Config de cola: `app/Config/Queue.php`
- Queue manager: `app/Libraries/Queue/QueueManager.php`
- Comando worker: `app/Commands/QueueWork.php`
- Jobs de email: `app/Libraries/Queue/Jobs/SendEmailJob.php`, `SendTemplateEmailJob.php`
- Encolado de email: `app/Services/EmailService.php`

## Configuración (.env)

```
QUEUE_DRIVER = database
QUEUE_MAX_ATTEMPTS = 3
QUEUE_RETRY_AFTER = 90
```

Notas:
- La implementación actual de `QueueManager` usa tablas de **database**.
- Si pones `QUEUE_DRIVER=redis`, seguirá usando database a menos que se extienda el manager.

## Migraciones requeridas

Ejecuta migraciones para crear las tablas de la cola:

```
php spark migrate
```

Migraciones:
- `app/Database/Migrations/2026-01-29-200038_CreateJobsTable.php`
- `app/Database/Migrations/2026-01-29-200102_CreateFailedJobsTable.php`

## Ejecutar el worker

Procesar continuamente la cola `emails`:

```
php spark queue:work --queue=emails
```

Procesar un solo job y salir:

```
php spark queue:work --queue=emails --once
```

Limitar procesamiento:

```
php spark queue:work --queue=emails --max-jobs=10
```

## Verificar que funciona

1. Genera un job:
   - Registrar un usuario (email de verificación).
   - Solicitar reset de contraseña.
2. Confirma que exista un registro en `jobs` (cola `emails`).
3. Corre el worker:
   - `php spark queue:work --queue=emails --once`
4. Confirma que el job se elimina de `jobs` y el email se envía.
5. Si falla, se reintenta y luego pasa a `failed_jobs`.

## Troubleshooting

- No hay jobs en `jobs`:
  - La acción que debía encolar el job no se ejecutó o falló antes del enqueue.
- Los jobs no se procesan:
  - Worker no corriendo, o `--queue` incorrecto.
- Los jobs fallan continuamente:
  - Revisar configuración de email y logs.
- Estado de la cola:
  - `app/Libraries/Monitoring/HealthChecker.php` valida `jobs` y reporta conteos.

## Recomendado (Dev/Prod)

Desarrollo:
- Correr el worker en otra terminal mientras se prueban features.

Producción:
- Ejecutar `php spark queue:work --queue=emails` bajo un supervisor (systemd, supervisor, PM2, etc.).

