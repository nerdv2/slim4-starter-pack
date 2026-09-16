<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Constants\CustomerStatus;
use App\Helper\Storage;
use App\Model\CustomerModel;
use Oeltima\SimpleQuery\Connection;
use Oeltima\SimpleQueue\Contract\JobHandlerInterface;
use RuntimeException;

/**
 * Imports a customer CSV previously stored by CustomerTransferService.
 *
 * Rows are validated individually and failures are reported per row instead of
 * aborting the whole file. Rows whose name or email already exists are skipped,
 * which also makes a retried job safe to run again. The uploaded file is
 * removed in every outcome.
 */
final class CustomerImportJob implements JobHandlerInterface
{
    private const int CHUNK_SIZE = 100;

    private const int MAX_ERRORS = 100;

    /** @var list<string> */
    private const array COLUMNS = [
        'name',
        'email',
        'phone',
        'company',
        'status',
        'address',
        'notes',
    ];

    public function __construct(
        private readonly CustomerModel $customerModel
    ) {
    }

    /**
     * @param array<string, mixed> $payload
     * @return array{total: int, imported: int, skipped: int, failed: int, errors: list<array{row: int, message: string}>}
     */
    public function handle(int $jobId, array $payload, ?callable $progressCallback = null): mixed
    {
        $fileName = basename((string) ($payload['file'] ?? ''));
        $path = Storage::customerImportFile($fileName);
        if ($fileName === '' || !is_file($path)) {
            throw new RuntimeException('The import file is missing.');
        }

        try {
            return $this->process($path, $progressCallback);
        } finally {
            @unlink($path);
        }
    }

    /**
     * @return array{total: int, imported: int, skipped: int, failed: int, errors: list<array{row: int, message: string}>}
     */
    private function process(string $path, ?callable $progressCallback): array
    {
        $total = $this->countDataRows($path);
        $handle = fopen($path, 'rb');
        if ($handle === false) {
            throw new RuntimeException('Could not open the import file.');
        }

        $imported = 0;
        $skipped = 0;
        $failed = 0;
        $processed = 0;
        /** @var list<array{row: int, message: string}> $errors */
        $errors = [];
        /** @var array<string, true> $seenNames */
        $seenNames = [];
        /** @var array<string, true> $seenEmails */
        $seenEmails = [];
        /** @var list<array<string, mixed>> $batch */
        $batch = [];

        try {
            $header = $this->headerMap($handle);
            if (!isset($header['name'])) {
                throw new RuntimeException('Import CSV is missing a name column.');
            }

            while (($line = fgetcsv($handle, escape: '')) !== false) {
                $processed++;
                if ($this->isEmptyRow($line)) {
                    continue;
                }

                $rowNumber = $processed + 1; // +1 for the header row
                $parsed = $this->parseRow($line, $header);

                $error = $this->validateRow($parsed, $seenNames, $seenEmails);
                if ($error !== null) {
                    $failed++;
                    $this->addError($errors, $rowNumber, $error);
                    continue;
                }

                if (
                    $this->customerModel->existsByName($parsed['name'])
                    || ($parsed['email'] !== null && $this->customerModel->existsByEmail($parsed['email']))
                ) {
                    // Skipped rows are counted in the result; only failures are itemized.
                    $skipped++;
                    continue;
                }

                $batch[] = $parsed;
                $seenNames[mb_strtolower($parsed['name'])] = true;
                if ($parsed['email'] !== null) {
                    $seenEmails[$parsed['email']] = true;
                }

                if (count($batch) >= self::CHUNK_SIZE) {
                    $imported += $this->flush($batch);
                    $batch = [];
                }

                if ($progressCallback !== null && $processed % self::CHUNK_SIZE === 0) {
                    $progressCallback(
                        $total > 0 ? min(99, (int) floor($processed * 100 / $total)) : 99,
                        sprintf('Imported %d of %d rows', $imported, $total)
                    );
                }
            }

            if ($batch !== []) {
                $imported += $this->flush($batch);
            }
        } finally {
            fclose($handle);
        }

        if ($progressCallback !== null) {
            $progressCallback(100, sprintf('Import complete (%d imported)', $imported));
        }

        return [
            'total' => $total,
            'imported' => $imported,
            'skipped' => $skipped,
            'failed' => $failed,
            'errors' => $errors,
        ];
    }

    /**
     * @param list<array<string, mixed>> $batch
     */
    private function flush(array $batch): int
    {
        if ($batch === []) {
            return 0;
        }

        $rows = [];
        $now = date('Y-m-d H:i:s');
        foreach ($batch as $parsed) {
            $row = [];
            foreach (self::COLUMNS as $column) {
                $row[$column] = $parsed[$column];
            }
            $row['avatar_path'] = null;
            $row['created_at'] = $now;
            $row['updated_at'] = $now;
            $rows[] = $row;
        }

        $this->customerModel->transaction(function (Connection $connection) use ($rows): int {
            return $this->customerModel->createMany($rows);
        });

        return count($rows);
    }

    /**
     * @param resource $handle
     * @return array<string, int>
     */
    private function headerMap($handle): array
    {
        $header = fgetcsv($handle, escape: '');
        if ($header === false) {
            return [];
        }

        $map = [];
        foreach ($header as $index => $column) {
            $name = $this->normalizeColumnName($column);
            if ($name !== '' && !isset($map[$name])) {
                $map[$name] = $index;
            }
        }

        return $map;
    }

    /**
     * @param list<string|null> $line
     * @param array<string, int> $header
     * @return array<string, mixed>
     */
    private function parseRow(array $line, array $header): array
    {
        $value = function (string $column) use ($line, $header): ?string {
            if (!isset($header[$column])) {
                return null;
            }

            $raw = $line[$header[$column]] ?? null;
            if (!is_string($raw)) {
                return null;
            }

            $trimmed = trim($raw);

            return $trimmed === '' ? null : $trimmed;
        };

        $status = $value('status');

        return [
            'name' => $value('name') ?? '',
            'email' => ($email = $value('email')) === null ? null : strtolower($email),
            'phone' => $value('phone'),
            'company' => $value('company'),
            'status' => $status === null ? CustomerStatus::DEFAULT : strtolower($status),
            'address' => $value('address'),
            'notes' => $value('notes'),
        ];
    }

    /**
     * @param array<string, mixed> $parsed
     * @param array<string, true> $seenNames
     * @param array<string, true> $seenEmails
     */
    private function validateRow(array $parsed, array $seenNames, array $seenEmails): ?string
    {
        $name = (string) $parsed['name'];
        $email = $parsed['email'];
        $status = (string) $parsed['status'];

        if ($name === '') {
            return 'Name is required.';
        }
        if (!CustomerStatus::isValid($status)) {
            return 'Status must be one of: ' . implode(', ', CustomerStatus::ALL) . '.';
        }
        if ($email !== null && filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            return 'Email is invalid.';
        }
        if (isset($seenNames[mb_strtolower($name)])) {
            return 'Duplicate customer in file.';
        }
        if ($email !== null && isset($seenEmails[$email])) {
            return 'Duplicate email in file.';
        }

        return null;
    }

    private function countDataRows(string $path): int
    {
        $handle = fopen($path, 'rb');
        if ($handle === false) {
            return 0;
        }

        $lines = 0;
        try {
            while (fgets($handle) !== false) {
                $lines++;
            }
        } finally {
            fclose($handle);
        }

        return max(0, $lines - 1); // Exclude the header row.
    }

    /**
     * @param list<string|null> $line
     */
    private function isEmptyRow(array $line): bool
    {
        foreach ($line as $value) {
            if (is_string($value) && trim($value) !== '') {
                return false;
            }
        }

        return true;
    }

    /**
     * @param list<array{row: int, message: string}> $errors
     */
    private function addError(array &$errors, int $row, string $message): void
    {
        if (count($errors) >= self::MAX_ERRORS) {
            return;
        }

        $errors[] = ['row' => $row, 'message' => $message];
    }

    private function normalizeColumnName(mixed $column): string
    {
        $normalized = preg_replace('/^\xEF\xBB\xBF/', '', (string) $column);

        return strtolower(trim($normalized ?? ''));
    }
}
