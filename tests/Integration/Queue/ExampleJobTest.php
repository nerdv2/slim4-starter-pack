<?php

declare(strict_types=1);

namespace Tests\Integration\Queue;

use Oeltima\SimpleQueue\JobDispatcher;
use Oeltima\SimpleQueue\JobRegistry;
use Oeltima\SimpleQueue\QueueManager;
use Oeltima\SimpleQueue\Storage\PdoJobStorage;
use Oeltima\SimpleQueue\Worker;
use Oeltima\SimpleQueue\WorkerOptions;
use Tests\TestCase;

final class ExampleJobTest extends TestCase
{
    public function testDispatchedJobIsProcessedToCompletion(): void
    {
        $dispatcher = $this->dispatcher();
        $jobId = $dispatcher->dispatch('example.hello', ['message' => 'Hello queue']);

        $pending = $dispatcher->getStatus($jobId);
        self::assertNotNull($pending);
        self::assertSame('pending', $pending->status->value);
        self::assertSame('example.hello', $pending->type);
        self::assertSame('default', $pending->queue);

        $worker = new Worker(
            storage: $this->storage(),
            queueManager: $this->queueManager(),
            registry: $this->registry(),
            queue: 'default',
            options: new WorkerOptions(stopWhenEmpty: true)
        );

        self::assertTrue($worker->processOne());

        $completed = $dispatcher->getStatus($jobId);
        self::assertNotNull($completed);
        self::assertSame('completed', $completed->status->value);
        self::assertSame('Hello queue', $completed->result['message'] ?? null);
    }

    public function testIdempotentDispatchReusesTheActiveJob(): void
    {
        $dispatcher = $this->dispatcher();

        $first = $dispatcher->dispatchIdempotent('example.hello', [], 'request-1');
        $second = $dispatcher->dispatchIdempotent('example.hello', [], 'request-1');

        self::assertTrue($first['created']);
        self::assertFalse($second['created']);
        self::assertSame($first['job_id'], $second['job_id']);
    }

    private function dispatcher(): JobDispatcher
    {
        /** @var JobDispatcher $dispatcher */
        $dispatcher = $this->app->getContainer()->get('jobDispatcher');

        return $dispatcher;
    }

    private function storage(): PdoJobStorage
    {
        /** @var PdoJobStorage $storage */
        $storage = $this->app->getContainer()->get('jobStorage');

        return $storage;
    }

    private function queueManager(): QueueManager
    {
        /** @var QueueManager $manager */
        $manager = $this->app->getContainer()->get('queueManager');

        return $manager;
    }

    private function registry(): JobRegistry
    {
        /** @var JobRegistry $registry */
        $registry = $this->app->getContainer()->get('jobRegistry');

        return $registry;
    }
}
