<?php

declare(strict_types=1);

namespace App\Service;

use App\DTO\Request\CustomerExportRequest;
use App\Exceptions\AppException;
use App\Exceptions\ValidationException;
use App\Helper\Storage;
use App\Helper\UploadHelper;
use App\Model\CustomerModel;
use Oeltima\SimpleQueue\JobDispatcher;
use Psr\Http\Message\UploadedFileInterface;

/**
 * Customer CSV transfer (export/import).
 *
 * Both directions run as background jobs so large datasets never block a
 * request. Export files and uploaded import files live under storage/ and are
 * only reachable through authenticated endpoints.
 */
final class CustomerTransferService
{
    /**
     * Upper bound for a single export, keeping the queue payload and the
     * generated file bounded.
     */
    private const int EXPORT_MAX_ROWS = 100000;

    /** @var list<string> */
    private const array IMPORT_EXTENSIONS = ['csv'];

    public function __construct(
        private readonly CustomerModel $customerModel,
        private readonly JobDispatcher $jobDispatcher
    ) {
    }

    /**
     * Queue a CSV export and return the job id together with the matching row
     * count (used for optimistic UI feedback).
     *
     * @return array{job_id: int, status: string, total_rows: int}
     */
    public function requestExport(CustomerExportRequest $dto, int|string $userId): array
    {
        $total = $this->customerModel->countGet($dto->keywords, $dto->status);
        if ($total > self::EXPORT_MAX_ROWS) {
            throw new ValidationException('The selection is too large to export.', [
                'keywords' => sprintf(
                    'Export is limited to %d customers per file; narrow the filters and try again.',
                    self::EXPORT_MAX_ROWS
                ),
            ]);
        }

        $jobId = $this->jobDispatcher->dispatch('customer.export', [
            'keywords' => $dto->keywords,
            'status' => $dto->status,
            'requested_by' => $userId,
        ]);

        return [
            'job_id' => $jobId,
            'status' => 'pending',
            'total_rows' => $total,
        ];
    }

    /**
     * Validate and store an uploaded CSV, then queue the import job.
     *
     * @return array{job_id: int, status: string, file_name: string}
     */
    public function requestImport(UploadedFileInterface $file, int|string $userId): array
    {
        $this->assertImportFile($file);

        $uploadHelper = new UploadHelper(Storage::path('imports'));
        $relativePath = $uploadHelper->store($file);
        if ($relativePath === false) {
            throw new AppException('The import file could not be stored.');
        }

        $fileName = basename($relativePath);
        $this->assertImportHeader(Storage::customerImportFile($fileName));

        $jobId = $this->jobDispatcher->dispatch('customer.import', [
            'file' => $fileName,
            'requested_by' => $userId,
        ]);

        return [
            'job_id' => $jobId,
            'status' => 'pending',
            'file_name' => $fileName,
        ];
    }

    public function exportFilePath(int $jobId): string
    {
        return Storage::customerExportFile($jobId);
    }

    private function assertImportFile(UploadedFileInterface $file): void
    {
        if ($file->getError() !== UPLOAD_ERR_OK) {
            throw new ValidationException('Import upload failed.', [
                'file' => 'The file could not be uploaded; please try again.',
            ]);
        }

        $maxBytes = (int) ($_SERVER['UPLOAD_IMPORT_MAX_BYTES'] ?? $_ENV['UPLOAD_IMPORT_MAX_BYTES'] ?? 5242880);
        $size = $file->getSize();
        if ($size !== null && $size > $maxBytes) {
            throw new ValidationException('Import file is too large.', [
                'file' => sprintf('Import files must not exceed %d MB.', (int) ceil($maxBytes / 1048576)),
            ]);
        }

        $extension = strtolower(pathinfo((string) $file->getClientFilename(), PATHINFO_EXTENSION));
        if (!in_array($extension, self::IMPORT_EXTENSIONS, true)) {
            throw new ValidationException('Import file type is not allowed.', [
                'file' => 'Only .csv files can be imported.',
            ]);
        }
    }

    /**
     * Reject files that do not even carry a name column before queueing work.
     */
    private function assertImportHeader(string $absolutePath): void
    {
        $handle = @fopen($absolutePath, 'rb');
        if ($handle === false) {
            throw new AppException('The import file could not be read.');
        }

        try {
            $header = fgetcsv($handle, escape: '');
        } finally {
            fclose($handle);
        }

        if ($header === false || !in_array('name', $this->normalizeHeader($header), true)) {
            throw new ValidationException('Import CSV is missing a name column.', [
                'file' => 'The CSV header must contain a name column.',
            ]);
        }
    }

    /**
     * @param array<int, string|null> $header
     * @return list<string>
     */
    private function normalizeHeader(array $header): array
    {
        return array_values(array_map(
            static fn (mixed $column): string => strtolower(trim(preg_replace(
                '/^\xEF\xBB\xBF/',
                '',
                (string) $column
            ) ?? '')),
            $header
        ));
    }
}
