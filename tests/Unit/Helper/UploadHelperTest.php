<?php

declare(strict_types=1);

namespace Tests\Unit\Helper;

use App\Helper\UploadHelper;
use FilesystemIterator;
use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use Slim\Psr7\Factory\StreamFactory;
use Slim\Psr7\UploadedFile;

final class UploadHelperTest extends TestCase
{
    private string $tempDirectory;

    private string $sourceFile;

    /** @var array<string, mixed> */
    private array $originalFiles;

    protected function setUp(): void
    {
        $this->originalFiles = $_FILES;
        $this->tempDirectory = sys_get_temp_dir() . '/slim4-upload-' . bin2hex(random_bytes(4));
        $this->sourceFile = $this->tempDirectory . '/source.jpg';

        mkdir($this->tempDirectory, 0775, true);
        // JPEG magic bytes so mime_content_type() detects image/jpeg.
        file_put_contents($this->sourceFile, "\xFF\xD8\xFF\xE0" . str_repeat("\x00", 16));

        $_FILES['avatar'] = [
            'name' => 'My Photo.JPG',
            'type' => 'image/jpeg',
            'tmp_name' => $this->sourceFile,
            'error' => UPLOAD_ERR_OK,
            'size' => filesize($this->sourceFile),
        ];
    }

    protected function tearDown(): void
    {
        $_FILES = $this->originalFiles;
        $this->removeDirectory($this->tempDirectory);

        parent::tearDown();
    }

    public function testValidateFileSanitizesTheNameWithoutDoublingTheExtension(): void
    {
        $result = (new UploadHelper())->validateFile('avatar', 'avatars', false, ['jpg', 'jpeg']);

        self::assertTrue($result['status']);
        self::assertSame('MyPhoto.jpg', $result['file_name']);
        self::assertSame('avatars/MyPhoto.jpg', $result['file_path']);
        self::assertSame('jpg', $result['extension']);
        self::assertSame('My Photo.JPG', $result['original_filename']);
        self::assertSame('image/jpeg', $result['content_type']);
    }

    public function testValidateFileRejectsDisallowedExtensions(): void
    {
        $result = (new UploadHelper())->validateFile('avatar', 'avatars', false, ['png']);

        self::assertFalse($result['status']);
        self::assertSame('File extension not allowed.', $result['message']);
        self::assertSame('', $result['file_path']);
    }

    public function testValidateFileRandomizesWhenRequested(): void
    {
        $result = (new UploadHelper())->validateFile('avatar', 'avatars', true);

        self::assertTrue($result['status']);
        self::assertMatchesRegularExpression('/^MyPhoto_[a-f0-9]{32}\.jpg$/', $result['file_name']);
    }

    public function testValidateFileWithMissingEntryReturnsEmptyResult(): void
    {
        $result = (new UploadHelper())->validateFile('missing', 'avatars');

        self::assertFalse($result['status']);
        self::assertSame('', $result['file_name']);
    }

    public function testMoveUploadedFileCreatesTheTargetDirectory(): void
    {
        $stream = (new StreamFactory())->createStreamFromFile($this->sourceFile);
        $uploadedFile = new UploadedFile(
            $stream,
            'photo.jpg',
            'image/jpeg',
            (int) filesize($this->sourceFile),
            UPLOAD_ERR_OK
        );

        $url = (new UploadHelper($this->tempDirectory . '/uploads'))->moveUploadedFile($uploadedFile, 'avatars');

        self::assertIsString($url);
        self::assertStringStartsWith('uploads/avatars/', $url);
        self::assertFileExists($this->tempDirectory . '/uploads/avatars/' . basename($url));
    }

    public function testStoreKeepsFilesPrivateAndSupportsDeletion(): void
    {
        $helper = new UploadHelper($this->tempDirectory . '/private');
        $uploadedFile = new UploadedFile(
            (new StreamFactory())->createStreamFromFile($this->sourceFile),
            'data.csv',
            'text/csv',
            (int) filesize($this->sourceFile),
            UPLOAD_ERR_OK
        );

        $relative = $helper->store($uploadedFile, 'imports');

        self::assertIsString($relative);
        self::assertMatchesRegularExpression('#^imports/[a-f0-9]{16}\.csv$#', $relative);
        self::assertFileExists($this->tempDirectory . '/private/' . $relative);
        self::assertNull($helper->absolutePath($relative), 'Private paths have no public URL mapping.');

        // store() moves the source file, so create a fresh one for the public upload.
        $secondSource = $this->tempDirectory . '/second.jpg';
        file_put_contents($secondSource, "\xFF\xD8\xFF\xE0" . str_repeat("\x00", 16));
        $publicFile = new UploadedFile(
            (new StreamFactory())->createStreamFromFile($secondSource),
            'photo.jpg',
            'image/jpeg',
            (int) filesize($secondSource),
            UPLOAD_ERR_OK
        );
        $publicPath = $helper->moveUploadedFile($publicFile, 'avatars');

        self::assertIsString($publicPath);
        self::assertFileExists($helper->absolutePath($publicPath) ?? '');
        self::assertTrue($helper->deleteUploadedFile($publicPath));
        self::assertFalse($helper->deleteUploadedFile($publicPath));
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
            if ($item->isDir()) {
                rmdir($item->getPathname());
            } else {
                unlink($item->getPathname());
            }
        }

        rmdir($directory);
    }
}
