<?php

declare(strict_types=1);

namespace App\Helper;

use Aws\Credentials\Credentials;
use Aws\S3\Exception\S3Exception;
use Aws\S3\S3Client;
use Psr\Http\Message\UploadedFileInterface;

final class UploadHelper
{
    private string $directory;

    public function __construct(?string $directory = null)
    {
        // Default filesystem upload path; object storage uploads ignore it.
        $this->directory = $directory ?? __DIR__ . '/../../public/uploads';
    }

    /**
     * Validate a classic $_FILES entry and build the stored file metadata.
     *
     * @param list<string> $allowed_extensions Empty means any extension.
     * @return array{status: bool, message: string, file_path: string, file_name: string, extension: string, original_filename: string, size: int, content_type: string}
     */
    public function validateFile(
        string $source_file,
        string $target_folder,
        bool $randomize_filename = false,
        array $allowed_extensions = []
    ): array {
        $result = [
            'status' => false,
            'message' => '',
            'file_path' => '',
            'file_name' => '',
            'extension' => '',
            'original_filename' => '',
            'size' => 0,
            'content_type' => '',
        ];

        if (!isset($_FILES[$source_file]) || (int) ($_FILES[$source_file]['size'] ?? 0) === 0) {
            return $result;
        }

        $upload = $_FILES[$source_file];
        $original = (string) ($upload['name'] ?? '');
        $extension = strtolower(pathinfo($original, PATHINFO_EXTENSION));

        if ($allowed_extensions !== [] && !in_array($extension, array_map('strtolower', $allowed_extensions), true)) {
            $result['message'] = 'File extension not allowed.';

            return $result;
        }

        $base = (string) preg_replace('/[^A-Za-z0-9_-]/', '', pathinfo($original, PATHINFO_FILENAME));
        if ($base === '') {
            $base = 'file';
        }
        if ($randomize_filename) {
            $base .= '_' . bin2hex(random_bytes(16));
        }

        $folder = trim($target_folder, '/');
        $file_name = $extension === '' ? $base : $base . '.' . $extension;
        $tmpName = (string) ($upload['tmp_name'] ?? '');

        $result['status'] = true;
        $result['file_name'] = $file_name;
        $result['file_path'] = ($folder === '' ? '' : $folder . '/') . $file_name;
        $result['extension'] = $extension;
        $result['original_filename'] = $original;
        $result['size'] = (int) ($upload['size'] ?? 0);
        $result['content_type'] = $tmpName !== '' ? (string) (mime_content_type($tmpName) ?: '') : '';

        return $result;
    }

    /**
     * Store an uploaded file on the configured target and return its public URL.
     *
     * `$path` is a directory below the upload root (for example `avatars`).
     */
    public function moveUploadedFile(UploadedFileInterface $uploadedFile, string $path = ''): string|false
    {
        $extension = strtolower(pathinfo((string) $uploadedFile->getClientFilename(), PATHINFO_EXTENSION));
        $filename = bin2hex(random_bytes(8)) . ($extension === '' ? '' : '.' . $extension);
        $folder = trim($path, '/');
        $relative = ($folder === '' ? '' : $folder . '/') . $filename;

        if ($this->uploadTarget() === 's3') {
            $result = $this->objectStorageUpload((string) $uploadedFile->getStream()->getMetadata('uri'), $relative);

            return $result['status'] ? $result['url'] : false;
        }

        $targetDirectory = $this->directory . ($folder === '' ? '' : DIRECTORY_SEPARATOR . $folder);
        $this->ensureDirectory($targetDirectory);
        $uploadedFile->moveTo($targetDirectory . DIRECTORY_SEPARATOR . $filename);

        return 'uploads/' . $relative;
    }

    /**
     * Upload a file that already exists below the upload root.
     */
    public function uploadFileFromPath(string $path = '', string $filename = ''): string|false
    {
        $folder = trim($path, '/');
        $relative = ($folder === '' ? '' : $folder . '/') . $filename;
        $sourceFile = $this->directory
            . ($folder === '' ? '' : DIRECTORY_SEPARATOR . $folder)
            . DIRECTORY_SEPARATOR . $filename;

        if ($this->uploadTarget() === 's3') {
            $result = $this->objectStorageUpload($sourceFile, $relative);

            return $result['status'] ? $result['url'] : false;
        }

        return 'uploads/' . $relative;
    }

    /**
     * @return array{status: bool, message: string, url: string}
     */
    public function objectStorageUpload(string $source_file, string $target_path): array
    {
        $result = ['status' => false, 'message' => '', 'url' => ''];

        $credentials = new Credentials(
            (string) ($_SERVER['S3_KEY'] ?? ''),
            (string) ($_SERVER['S3_SECRET'] ?? '')
        );

        $options = [
            'region' => (string) ($_SERVER['S3_REGION'] ?? ''),
            'endpoint' => (string) ($_SERVER['S3_ENDPOINT'] ?? ''),
            'version' => 'latest',
            'credentials' => $credentials,
        ];

        try {
            $s3Client = new S3Client($options);

            $s3Client->putObject([
                'Bucket' => (string) ($_SERVER['S3_BUCKET'] ?? ''),
                'Key' => $target_path,
                'SourceFile' => $source_file,
            ]);

            $result['status'] = true;
            $result['url'] = rtrim((string) ($_SERVER['S3_CDN_DOMAIN'] ?? ''), '/') . '/' . $target_path;
        } catch (S3Exception $exception) {
            $result['message'] = $exception->getMessage();
        }

        return $result;
    }

    private function uploadTarget(): string
    {
        return (string) ($_SERVER['DEFAULT_UPLOAD_TARGET'] ?? 'filesystem');
    }

    private function ensureDirectory(string $directory): void
    {
        if (!is_dir($directory)) {
            @mkdir($directory, 0775, true);
        }
    }
}
