# Queue System

Background processing is essential for long-running tasks like sending emails, processing images, or communicating with external APIs.

## Supported Drivers

Configure your preferred driver in `.env`:

```env
# Options: database, redis, sync
QUEUE_DRIVER=database
# For database driver
QUEUE_DATABASE_CONNECTION=default
# For redis driver
QUEUE_REDIS_HOST=127.0.0.1
QUEUE_REDIS_PORT=6379
```

### Drivers Explained
- **`database`**: Recommended for most applications. Jobs are stored in the `queue_jobs` table. Easy to set up and monitor.
- **`redis`**: Best for high-performance requirements. Requires a Redis server.
- **`sync`**: Executes jobs immediately (synchronously). Useful for local development and testing.

---

## Running the Worker

To process jobs, you must run the worker command. In production, this should be managed by a process monitor like **Supervisor**.

```bash
php spark queue:work --queue default
```

**Options**:
- `--queue`: Specific queue to process (defaults to `default`).
- `--rest`: Seconds to sleep when no jobs are available (defaults to 1).
- `--max-runtime`: Seconds the worker should run before exiting (useful for preventing memory leaks).

---

## Dispatching Jobs

You can dispatch jobs using the `QueueManager` library:

```php
use App\Libraries\Queue\QueueManager;

$queue = service('queue');
$queue->push('App\Jobs\SendWelcomeEmail', [
    'email' => 'user@example.com',
    'user_id' => 123
]);
```

## Error Handling & Retries

The system automatically handles failures:
- **Max Attempts**: Configurable via `QUEUE_MAX_ATTEMPTS` (default: 3).
- **Retry Delay**: Configurable via `QUEUE_RETRY_AFTER` (default: 90 seconds).
- **Failed Jobs**: If all attempts fail, the job is moved to the `queue_failed_jobs` table for manual inspection.
