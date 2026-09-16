<?php

declare(strict_types=1);

namespace Tests\Integration\Controller;

use App\Exceptions\NotFoundException;
use App\Exceptions\ValidationException;
use App\Helper\CacheRedis;
use App\Helper\UploadHelper;
use App\Model\CustomerModel;
use App\Service\CustomerService;
use FilesystemIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use Slim\Psr7\Factory\StreamFactory;
use Slim\Psr7\UploadedFile;
use Tests\TestCase;
use Tests\TestFactory;

final class AvatarUploadTest extends TestCase
{
    private string $uploadDirectory;

    protected function setUp(): void
    {
        parent::setUp();

        $this->uploadDirectory = sys_get_temp_dir() . '/slim4-avatars-' . bin2hex(random_bytes(4));
        mkdir($this->uploadDirectory, 0775, true);
    }

    protected function tearDown(): void
    {
        $this->removeDirectory($this->uploadDirectory);

        parent::tearDown();
    }

    public function testUploadStoresTheAvatarAndUpdatesTheCustomer(): void
    {
        $customer = $this->seedCustomer();

        $result = $this->service()->uploadAvatar($customer, $this->pngUpload());

        self::assertStringContainsString('/uploads/avatars/', (string) $result['avatar_url']);
        self::assertFileExists($this->uploadDirectory . '/avatars/' . basename((string) $result['avatar_url']));

        $stored = $this->connection()->table('customer')->where('customer.id', '=', $customer)->first();
        self::assertNotNull($stored);
        self::assertStringStartsWith('uploads/avatars/', (string) $stored->avatar_path);
    }

    public function testReplacingAndRemovingTheAvatarDeletesOldFiles(): void
    {
        $customer = $this->seedCustomer();
        $service = $this->service();

        $first = $service->uploadAvatar($customer, $this->pngUpload());
        $firstFile = $this->uploadDirectory . '/avatars/' . basename((string) $first['avatar_url']);

        $second = $service->uploadAvatar($customer, $this->pngUpload());
        $secondFile = $this->uploadDirectory . '/avatars/' . basename((string) $second['avatar_url']);

        self::assertFileDoesNotExist($firstFile);
        self::assertFileExists($secondFile);

        $removed = $service->removeAvatar($customer);

        self::assertNull($removed['avatar_url']);
        self::assertFileDoesNotExist($secondFile);
    }

    public function testUploadRejectsDisallowedTypesAndOversizedFiles(): void
    {
        $customer = $this->seedCustomer();
        $service = $this->service();

        try {
            $service->uploadAvatar($customer, $this->pngUpload('payload.php'));
            self::fail('A .php upload must be rejected.');
        } catch (ValidationException $exception) {
            self::assertArrayHasKey('avatar', $exception->errors());
        }

        $oversized = $this->pngUpload(size: 99_999_999);
        try {
            $service->uploadAvatar($customer, $oversized);
            self::fail('An oversized upload must be rejected.');
        } catch (ValidationException $exception) {
            self::assertArrayHasKey('avatar', $exception->errors());
        }
    }

    public function testUploadRequiresAnExistingCustomer(): void
    {
        $this->expectException(NotFoundException::class);

        $this->service()->uploadAvatar(99999, $this->pngUpload());
    }

    private function service(): CustomerService
    {
        /** @var CustomerModel $model */
        $model = $this->app->getContainer()->get('customerModel');
        /** @var CacheRedis $cache */
        $cache = $this->app->getContainer()->get('cacheRedis');

        return new CustomerService($model, $cache, new UploadHelper($this->uploadDirectory));
    }

    private function pngUpload(string $filename = 'avatar.png', ?int $size = null): UploadedFile
    {
        $path = $this->uploadDirectory . '/source-' . bin2hex(random_bytes(4)) . '.png';
        file_put_contents(
            $path,
            (string) base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==')
        );

        return new UploadedFile(
            (new StreamFactory())->createStreamFromFile($path),
            $filename,
            'image/png',
            $size ?? (int) filesize($path),
            UPLOAD_ERR_OK
        );
    }

    private function seedCustomer(): int
    {
        return (int) $this->connection()->table('customer')->insertGetId(TestFactory::customer());
    }

    private function removeDirectory(string $directory): void
    {
        if (!is_dir($directory)) {
            return;
        }

        $items = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST
        );

        foreach ($items as $item) {
            $item->isDir() ? rmdir($item->getPathname()) : unlink($item->getPathname());
        }

        rmdir($directory);
    }
}
