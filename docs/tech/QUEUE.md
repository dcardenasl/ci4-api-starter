# Queue & Jobs (CI4)

This project ships with a simple database-backed queue and a CLI worker. It is used by the email system (verification, password reset, invitations, etc.).

## How It Works

- Jobs are stored in the `jobs` table.
- Failed jobs are moved to `failed_jobs`.
- A CLI worker processes jobs from a named queue.
- Email jobs use the `emails` queue.

Main code locations:
- Queue config: `app/Config/Queue.php`
- Queue manager: `app/Libraries/Queue/QueueManager.php`
- Worker command: `app/Commands/QueueWork.php`
- Email jobs: `app/Libraries/Queue/Jobs/SendEmailJob.php`, `SendTemplateEmailJob.php`
- Email enqueuing: `app/Services/EmailService.php`

## Configuration (.env)

```
QUEUE_DRIVER = database
QUEUE_MAX_ATTEMPTS = 3
QUEUE_RETRY_AFTER = 90
```

Notes:
- The current `QueueManager` implementation uses **database** tables.
- If you set `QUEUE_DRIVER=redis`, it will still use the database unless the manager is extended.

## Required Migrations

Run migrations to create queue tables:

```
php spark migrate
```

Migrations are:
- `app/Database/Migrations/2026-01-29-200038_CreateJobsTable.php`
- `app/Database/Migrations/2026-01-29-200102_CreateFailedJobsTable.php`

## Running the Worker

Process the `emails` queue continuously:

```
php spark queue:work --queue=emails
```

Process one job and exit:

```
php spark queue:work --queue=emails --once
```

Limit processing:

```
php spark queue:work --queue=emails --max-jobs=10
```

## Verifying It Works

1. Trigger a job:
   - Register a user (verification email).
   - Request password reset.
2. Confirm a row exists in `jobs` (queue `emails`).
3. Run the worker:
   - `php spark queue:work --queue=emails --once`
4. Confirm job is removed from `jobs` and email is sent.
5. If it fails, it will retry and then move to `failed_jobs`.

## Troubleshooting

- No jobs in `jobs`:
  - The action that should enqueue the job didn’t run or failed before enqueue.
- Jobs never process:
  - Worker not running, or wrong `--queue` name.
- Jobs keep failing:
  - Check email configuration and app logs.
- Queue health:
  - `app/Libraries/Monitoring/HealthChecker.php` validates `jobs` and reports counts.

## Recommended Process (Dev/Prod)

Development:
- Run the worker in a separate terminal while testing features.

Production:
- Run `php spark queue:work --queue=emails` under a process supervisor (systemd, supervisor, PM2, etc.).

