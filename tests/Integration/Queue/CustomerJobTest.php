<?php

declare(strict_types=1);

namespace Tests\Integration\Queue;

use App\Helper\Storage;
use Oeltima\SimpleQueue\JobDispatcher;
use Oeltima\SimpleQueue\JobRegistry;
use Oeltima\SimpleQueue\QueueManager;
use Oeltima\SimpleQueue\Storage\PdoJobStorage;
use Oeltima\SimpleQueue\Worker;
use Oeltima\SimpleQueue\WorkerOptions;
use Tests\TestCase;
use Tests\TestFactory;

final class CustomerJobTest extends TestCase
{
    protected function tearDown(): void
    {
        foreach ([Storage::path('exports'), Storage::path('imports')] as $directory) {
            if (!is_dir($directory)) {
                continue;
            }
            foreach ((array) glob($directory . '/*.csv') as $file) {
                @unlink((string) $file);
            }
        }

        parent::tearDown();
    }

    public function testExportJobWritesOnlyMatchingRows(): void
    {
        $this->seedCustomer(['name' => 'Active Export Co', 'status' => 'active']);
        $this->seedCustomer(['name' => 'Second Active Co', 'status' => 'active']);
        $this->seedCustomer(['name' => 'Lead Export Co', 'status' => 'lead']);

        $dispatcher = $this->dispatcher();
        $jobId = $dispatcher->dispatch('customer.export', [
            'keywords' => '',
            'status' => 'active',
            'requested_by' => 1,
        ]);

        self::assertTrue($this->processOneJob());

        $job = $dispatcher->getStatus($jobId);
        self::assertNotNull($job);
        self::assertSame('completed', $job->status->value);
        self::assertSame(2, $job->result['row_count'] ?? null);

        $path = Storage::customerExportFile($jobId);
        self::assertFileExists($path);
        $csv = (string) file_get_contents($path);
        self::assertStringContainsString('Active Export Co', $csv);
        self::assertStringContainsString('Second Active Co', $csv);
        self::assertStringNotContainsString('Lead Export Co', $csv);
    }

    public function testImportJobImportsValidRowsAndReportsTheRest(): void
    {
        $this->seedCustomer(['name' => 'Existing Co', 'email' => 'existing@example.com']);

        Storage::ensureDirectory(Storage::path('imports'));
        $fileName = 'queue-import-' . bin2hex(random_bytes(4)) . '.csv';
        file_put_contents(
            Storage::customerImportFile($fileName),
            "name,email,status\n"
            . "Fresh Co,fresh@example.com,prospect\n"
            . "Existing Co,existing@example.com,active\n"
            . "Fresh Co,fresh2@example.com,lead\n"
            . ",missing-name@example.com,lead\n"
            . "Bad Status Co,bad@example.com,invalid-status\n"
        );

        $dispatcher = $this->dispatcher();
        $jobId = $dispatcher->dispatch('customer.import', ['file' => $fileName, 'requested_by' => 1]);

        self::assertTrue($this->processOneJob());

        $job = $dispatcher->getStatus($jobId);
        self::assertNotNull($job);
        self::assertSame('completed', $job->status->value);
        self::assertSame(1, $job->result['imported'] ?? null);
        self::assertSame(1, $job->result['skipped'] ?? null);
        self::assertSame(3, $job->result['failed'] ?? null);
        self::assertCount(3, $job->result['errors'] ?? []);

        $imported = $this->connection()->table('customer')
            ->where('customer.email', '=', 'fresh@example.com')
            ->first();
        self::assertNotNull($imported);
        self::assertSame('Fresh Co', $imported->name);

        self::assertFileDoesNotExist(Storage::customerImportFile($fileName));
    }

    public function testIdempotentDispatchReusesTheActiveJob(): void
    {
        $dispatcher = $this->dispatcher();

        $first = $dispatcher->dispatchIdempotent('customer.export', [], 'request-1');
        $second = $dispatcher->dispatchIdempotent('customer.export', [], 'request-1');

        self::assertTrue($first['created']);
        self::assertFalse($second['created']);
        self::assertSame($first['job_id'], $second['job_id']);
    }

    private function processOneJob(): bool
    {
        $worker = new Worker(
            storage: $this->storage(),
            queueManager: $this->queueManager(),
            registry: $this->registry(),
            queue: 'default',
            options: new WorkerOptions(stopWhenEmpty: true)
        );

        return $worker->processOne();
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

    /**
     * @param array<string, mixed> $overrides
     */
    private function seedCustomer(array $overrides = []): int
    {
        return (int) $this->connection()->table('customer')->insertGetId(TestFactory::customer($overrides));
    }
}
