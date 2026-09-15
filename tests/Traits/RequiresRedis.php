<?php

declare(strict_types=1);

namespace Tests\Traits;

use Predis\Client;
use Throwable;

trait RequiresRedis
{
    /**
     * Skip the test unless Redis is configured and reachable. Set
     * REDIS_SERVER_HOST/REDIS_SERVER_PORT to run cache tests locally.
     */
    protected function requireRedis(): void
    {
        $host = trim((string) ($_SERVER['REDIS_SERVER_HOST'] ?? $_ENV['REDIS_SERVER_HOST'] ?? ''));
        $port = trim((string) ($_SERVER['REDIS_SERVER_PORT'] ?? $_ENV['REDIS_SERVER_PORT'] ?? ''));

        if ($host === '' || $port === '') {
            self::markTestSkipped('Set REDIS_SERVER_HOST and REDIS_SERVER_PORT to run cache tests.');
        }

        try {
            $client = new Client([
                'scheme' => 'tcp',
                'host' => $host,
                'port' => (int) $port,
                'timeout' => 0.5,
                'read_write_timeout' => 0.5,
            ]);
            $client->ping();
        } catch (Throwable $exception) {
            self::markTestSkipped(sprintf(
                'Redis is not reachable at %s:%s (%s).',
                $host,
                $port,
                $exception->getMessage()
            ));
        }
    }
}
