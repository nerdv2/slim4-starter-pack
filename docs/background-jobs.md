# Background Jobs

Durable background processing with
[oeltimacreation/php-simplequeue](https://github.com/oeltimacreation/php-simplequeue).

## Architecture

The `background_job` table is authoritative for job state; the database queue driver polls it for
pending work. The web process dispatches, the worker process executes:

```text
Controller / Service ──dispatch──► background_job table ◄──poll── worker ──► job handler
```

Queue services are registered in `src/App/Services.php`:

| Service | Class | Purpose |
|---------|-------|---------|
| `jobStorage` | `PdoJobStorage` | Durable job storage; reuses the application PDO connection. |
| `queueManager` | `QueueManager` | Delivery driver selected by `QUEUE_DRIVER`. |
| `jobRegistry` | `JobRegistry` | Maps job types to handler classes. |
| `jobDispatcher` | `JobDispatcher` | Dispatch, schedule and inspect jobs. |

## Configuration

| Variable | Default | Purpose |
|----------|---------|---------|
| `QUEUE_DRIVER` | `auto` | `auto` (database fallback), `database`/`db` or `redis`. Redis delivery is not wired in the starter yet. |
| `QUEUE_REDIS_PREFIX` | `slim4` | Prefix reserved for Redis delivery. |
| `JOB_POLL_TIMEOUT_SECONDS` | `5` | Blocking poll timeout for the database driver. |
| `JOB_STUCK_TTL_SECONDS` | `900` | A running job older than this is recovered and retried. |
| `JOB_RETRY_BASE_DELAY_SECONDS` | `2` | Exponential backoff base for retries. |
| `JOB_RETRY_MAX_DELAY_SECONDS` | `300` | Retry backoff ceiling. |
| `JOB_MAX_JOBS` | `500` | Jobs per worker lifecycle before recycling (`0` disables). |
| `JOB_MAX_TIME_SECONDS` | `3600` | Seconds before worker recycling (`0` disables). |
| `JOB_MEMORY_LIMIT_BYTES` | `268435456` | Memory ceiling before worker recycling (`0` disables). |
| `JOB_PROMOTE_INTERVAL_SECONDS` | `5` | How often delayed jobs are promoted. |
| `JOB_PROMOTE_LIMIT` | `100` | Delayed jobs promoted per pass. |
| `JOB_RECOVERY_INTERVAL_SECONDS` | `60` | Stale-lease recovery interval. |

## Dispatching

From a controller or service with the dispatcher injected:

```php
$jobId = $this->jobDispatcher->dispatch('example.hello', ['message' => 'Hi']);
$jobId = $this->jobDispatcher->dispatchAfter(300, 'example.hello', ['message' => 'Later']);
$result = $this->jobDispatcher->dispatchIdempotent('example.hello', $payload, 'request-id-123');
```

- `dispatch()` returns the job id.
- `dispatchAfter()` / `dispatchAt()` schedule delayed work.
- `dispatchIdempotent()` reuses the active job for a request id instead of creating a duplicate
  (backed by a unique index on active request ids).
- `getStatus($jobId)` returns a `JobData` value object (`status`, `progress`, `result`, `attempts`,
  `errorMessage`, ...).

The example module exposes the flow through admin endpoints (JWT + admin required):

| Method | Path | Purpose |
|--------|------|---------|
| `POST` | `/admin/background-jobs/example` | Dispatch the example job (optional `message` field). |
| `GET` | `/admin/background-jobs/{id}` | Job status, progress and result. |

## Writing a Job

1. Create a handler in `src/Jobs/` implementing `Oeltima\SimpleQueue\Contract\JobHandlerInterface`:

   ```php
   final class MyJob implements JobHandlerInterface
   {
       public function handle(int $jobId, array $payload, ?callable $progressCallback = null): mixed
       {
           if ($progressCallback !== null) {
               $progressCallback(100, 'Done');
           }

           return ['processed' => $payload['id'] ?? null]; // stored as the job result
       }
   }
   ```

2. Register the type in `src/App/Services.php` (`jobRegistry`):

   ```php
   $registry->register('my.job', \App\Jobs\MyJob::class);
   ```

3. Dispatch it with `jobDispatcher` and monitor it through the status endpoint.

Job handlers may resolve dependencies from the container (the registry receives the PSR-11
container). Throw an exception to fail the job; SimpleQueue retries it with backoff up to
`max_attempts` and records the error.

## Running the Worker

```bash
composer run worker                 # consume the default queue
php bin/background-worker emails    # consume another queue
```

The worker:

- takes a single-worker lock in `storage/locks/`;
- recovers stale leases (`JOB_STUCK_TTL_SECONDS`) and promotes delayed jobs;
- recycles itself after `JOB_MAX_JOBS`, `JOB_MAX_TIME_SECONDS` or the memory limit;
- logs to `storage/log/worker.log` and stdout, with queue lifecycle events.

## Development Runner

```bash
composer run dev                    # webserver + worker, prefixed output, worker restarts
php bin/dev-server --no-worker      # webserver only
php bin/dev-server --worker-only    # worker only
php bin/dev-server --port=8081      # custom port
```

`composer run serve` remains available for a plain webserver-only dev process.

## Tests

Queue behaviour is covered on SQLite in `tests/Integration/Queue/` (dispatch → process →
completed, idempotent dispatch) and through the admin endpoints in
`tests/Integration/Controller/BackgroundJobTest.php`. No Redis or external worker is required.

## Related Docs

- [Architecture](architecture.md)
- [Development](development.md)
- [Database](database.md)
