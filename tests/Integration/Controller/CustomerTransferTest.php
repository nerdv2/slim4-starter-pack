<?php

declare(strict_types=1);

namespace Tests\Integration\Controller;

use App\Helper\Storage;
use Oeltima\SimpleQueue\Worker;
use Oeltima\SimpleQueue\WorkerOptions;
use Slim\Psr7\Factory\StreamFactory;
use Slim\Psr7\UploadedFile;
use Tests\TestCase;
use Tests\TestFactory;

final class CustomerTransferTest extends TestCase
{
    /** @var array<string, string> */
    private array $adminHeaders = [];

    /** @var array<string, string> */
    private array $staffHeaders = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->adminHeaders = ['Authorization' => $this->authHeader('admin', 1)];
        $this->staffHeaders = ['Authorization' => $this->authHeader('staff', 2)];
    }

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

    public function testExportRequiresAuthentication(): void
    {
        self::assertSame(401, $this->handle($this->createRequest('POST', '/customer/export'))->getStatusCode());
    }

    public function testExportQueueDownloadFlow(): void
    {
        $this->seedCustomer(['name' => 'Exportable One']);
        $this->seedCustomer(['name' => 'Exportable Two']);

        $queued = $this->handle($this->createRequest('POST', '/customer/export', $this->staffHeaders, []));
        $queuedPayload = $this->json($queued);

        self::assertSame(202, $queued->getStatusCode());
        self::assertSame(2, $queuedPayload['data']['total_rows']);
        $jobId = (int) $queuedPayload['data']['job_id'];

        $pending = $this->json($this->handle($this->createRequest('GET', '/jobs/' . $jobId, $this->staffHeaders)))['data'];
        self::assertSame('pending', $pending['status']);
        self::assertArrayNotHasKey('payload', $pending, 'Job payloads stay private.');

        self::assertTrue($this->processOneJob());

        $completed = $this->json($this->handle($this->createRequest('GET', '/jobs/' . $jobId, $this->staffHeaders)))['data'];
        self::assertSame('completed', $completed['status']);
        self::assertSame(2, $completed['result']['row_count']);
        self::assertStringContainsString('/customer/export/' . $jobId . '/download', (string) $completed['download_url']);

        $download = $this->handle($this->createRequest(
            'GET',
            '/customer/export/' . $jobId . '/download',
            $this->staffHeaders
        ));
        $body = (string) $download->getBody();

        self::assertSame(200, $download->getStatusCode());
        self::assertStringContainsString('text/csv', $download->getHeaderLine('Content-Type'));
        self::assertStringContainsString('attachment;', $download->getHeaderLine('Content-Disposition'));
        self::assertStringContainsString('Exportable One', $body);
        self::assertStringContainsString('Exportable Two', $body);
    }

    public function testDownloadBeforeCompletionIsAConflict(): void
    {
        $queued = $this->handle($this->createRequest('POST', '/customer/export', $this->staffHeaders, []));
        $jobId = (int) $this->json($queued)['data']['job_id'];

        $download = $this->handle($this->createRequest(
            'GET',
            '/customer/export/' . $jobId . '/download',
            $this->staffHeaders
        ));

        self::assertSame(409, $download->getStatusCode());
    }

    public function testDownloadOfAnUnknownExportIsNotFound(): void
    {
        $download = $this->handle($this->createRequest(
            'GET',
            '/customer/export/99999/download',
            $this->staffHeaders
        ));

        self::assertSame(404, $download->getStatusCode());
    }

    public function testImportRequiresAdmin(): void
    {
        $response = $this->handle(
            $this->createRequest('POST', '/customer/import', $this->staffHeaders)
                ->withUploadedFiles(['file' => $this->csvUpload("name\nStaff Import\n")])
        );

        self::assertSame(403, $response->getStatusCode());
    }

    public function testImportRejectsNonCsvAndHeaderlessFiles(): void
    {
        $wrongType = $this->handle(
            $this->createRequest('POST', '/customer/import', $this->adminHeaders)
                ->withUploadedFiles(['file' => $this->csvUpload("name\nAcme\n", 'customers.txt')])
        );
        $missingName = $this->handle(
            $this->createRequest('POST', '/customer/import', $this->adminHeaders)
                ->withUploadedFiles(['file' => $this->csvUpload("email,status\nfoo@example.com,lead\n")])
        );
        $missingFile = $this->handle($this->createRequest('POST', '/customer/import', $this->adminHeaders));

        self::assertSame(400, $wrongType->getStatusCode());
        self::assertArrayHasKey('file', $this->json($wrongType)['data']);
        self::assertSame(400, $missingName->getStatusCode());
        self::assertArrayHasKey('file', $this->json($missingName)['data']);
        self::assertSame(400, $missingFile->getStatusCode());
    }

    public function testImportQueueAndProcessFlow(): void
    {
        $csv = "name,email,company,status\n"
            . "Imported Alpha,alpha@import.example,Alpha Co,active\n"
            . "Bad Status,bad@import.example,Bad Co,weird\n";

        $queued = $this->handle(
            $this->createRequest('POST', '/customer/import', $this->adminHeaders)
                ->withUploadedFiles(['file' => $this->csvUpload($csv)])
        );
        $payload = $this->json($queued);

        self::assertSame(202, $queued->getStatusCode());
        $jobId = (int) $payload['data']['job_id'];

        self::assertTrue($this->processOneJob());

        $completed = $this->json($this->handle($this->createRequest('GET', '/jobs/' . $jobId, $this->adminHeaders)))['data'];
        self::assertSame('completed', $completed['status']);
        self::assertSame(1, $completed['result']['imported']);
        self::assertSame(1, $completed['result']['failed']);
        self::assertSame(3, $completed['result']['errors'][0]['row']);

        $stored = $this->connection()->table('customer')
            ->where('customer.email', '=', 'alpha@import.example')
            ->first();
        self::assertNotNull($stored);
        self::assertSame('Imported Alpha', $stored->name);

        self::assertSame([], (array) glob(Storage::path('imports') . '/*.csv'), 'Uploads are removed after processing.');
    }

    private function processOneJob(): bool
    {
        $worker = new Worker(
            storage: $this->app->getContainer()->get('jobStorage'),
            queueManager: $this->app->getContainer()->get('queueManager'),
            registry: $this->app->getContainer()->get('jobRegistry'),
            queue: 'default',
            options: new WorkerOptions(stopWhenEmpty: true)
        );

        return $worker->processOne();
    }

    private function csvUpload(string $contents, string $filename = 'customers.csv'): UploadedFile
    {
        $path = sys_get_temp_dir() . '/slim4-import-' . bin2hex(random_bytes(4)) . '.csv';
        file_put_contents($path, $contents);

        return new UploadedFile(
            (new StreamFactory())->createStreamFromFile($path),
            $filename,
            'text/csv',
            (int) filesize($path),
            UPLOAD_ERR_OK
        );
    }

    /**
     * @param array<string, mixed> $overrides
     */
    private function seedCustomer(array $overrides = []): int
    {
        return (int) $this->connection()->table('customer')->insertGetId(TestFactory::customer($overrides));
    }
}
