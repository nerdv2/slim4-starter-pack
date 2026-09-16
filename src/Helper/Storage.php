<?php

declare(strict_types=1);

namespace App\Helper;

/**
 * Filesystem locations below the writable storage directory.
 *
 * Exports and imports live under storage/ (not public/) so they are never
 * served as static files: downloads go through an authenticated endpoint.
 */
final class Storage
{
    public static function root(): string
    {
        $configured = $_SERVER['STORAGE_PATH'] ?? $_ENV['STORAGE_PATH'] ?? '';
        if (is_string($configured) && trim($configured) !== '') {
            return rtrim($configured, DIRECTORY_SEPARATOR);
        }

        return dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'storage';
    }

    public static function path(string ...$segments): string
    {
        return self::root() . DIRECTORY_SEPARATOR . implode(DIRECTORY_SEPARATOR, $segments);
    }

    /**
     * Absolute path of the CSV produced by a customer export job.
     */
    public static function customerExportFile(int $jobId): string
    {
        return self::path('exports', 'customer-export-' . $jobId . '.csv');
    }

    /**
     * Absolute path of an uploaded customer import file.
     */
    public static function customerImportFile(string $fileName): string
    {
        return self::path('imports', basename($fileName));
    }

    public static function ensureDirectory(string $path): void
    {
        if (!is_dir($path)) {
            @mkdir($path, 0775, true);
        }
    }
}
