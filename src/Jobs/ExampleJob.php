<?php

declare(strict_types=1);

namespace App\Jobs;

use Oeltima\SimpleQueue\Contract\JobHandlerInterface;

/**
 * Example background job: echoes a payload message and reports progress.
 *
 * Replace the body with real work; the returned value is stored as the job
 * result and can be read through the job status endpoint.
 */
final class ExampleJob implements JobHandlerInterface
{
    /**
     * @param array<string, mixed> $payload
     */
    public function handle(int $jobId, array $payload, ?callable $progressCallback = null): mixed
    {
        $message = (string) ($payload['message'] ?? 'Hello from the background queue');

        if ($progressCallback !== null) {
            $progressCallback(50, 'Processing example job');
        }

        $result = [
            'message' => $message,
            'job_id' => $jobId,
            'handled_at' => date(DATE_ATOM),
        ];

        if ($progressCallback !== null) {
            $progressCallback(100, 'Example job completed');
        }

        return $result;
    }
}
