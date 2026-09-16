<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Helper\Storage;
use App\Model\CustomerModel;
use Oeltima\SimpleQueue\Contract\JobHandlerInterface;
use RuntimeException;

/**
 * Streams the filtered customer list into a CSV file under storage/exports/.
 *
 * The file name is deterministic (`customer-export-{jobId}.csv`) so the
 * authenticated download endpoint can resolve it from the job id without
 * trusting a path from the job payload.
 */
final class CustomerExportJob implements JobHandlerInterface
{
    private const int PROGRESS_INTERVAL = 200;

    /** @var list<string> */
    private const array COLUMNS = [
        'id',
        'name',
        'email',
        'phone',
        'company',
        'status',
        'address',
        'notes',
        'created_at',
        'updated_at',
    ];

    public function __construct(
        private readonly CustomerModel $customerModel
    ) {
    }

    /**
     * @param array<string, mixed> $payload
     * @return array{file_name: string, row_count: int, generated_at: string}
     */
    public function handle(int $jobId, array $payload, ?callable $progressCallback = null): mixed
    {
        $keywords = trim((string) ($payload['keywords'] ?? ''));
        $status = $payload['status'] ?? null;
        $status = is_string($status) && $status !== '' ? $status : null;

        $total = $this->customerModel->countGet($keywords, $status);
        $path = Storage::customerExportFile($jobId);
        Storage::ensureDirectory(dirname($path));

        $handle = fopen($path, 'wb');
        if ($handle === false) {
            throw new RuntimeException('Could not open the export file for writing.');
        }

        $processed = 0;
        try {
            // UTF-8 BOM so spreadsheet applications detect the encoding.
            fwrite($handle, "\xEF\xBB\xBF");
            fputcsv($handle, self::COLUMNS, escape: '');

            foreach ($this->customerModel->iterateForExport($keywords, $status) as $row) {
                fputcsv($handle, [
                    (string) ($row['id'] ?? ''),
                    (string) ($row['name'] ?? ''),
                    (string) ($row['email'] ?? ''),
                    (string) ($row['phone'] ?? ''),
                    (string) ($row['company'] ?? ''),
                    (string) ($row['status'] ?? ''),
                    (string) ($row['address'] ?? ''),
                    (string) ($row['notes'] ?? ''),
                    (string) ($row['created_at'] ?? ''),
                    (string) ($row['updated_at'] ?? ''),
                ], escape: '');

                $processed++;
                if ($progressCallback !== null && $processed % self::PROGRESS_INTERVAL === 0) {
                    $progressCallback(
                        $total > 0 ? min(99, (int) floor($processed * 100 / $total)) : 99,
                        sprintf('Exported %d of %d rows', $processed, $total)
                    );
                }
            }
        } finally {
            fclose($handle);
        }

        if ($progressCallback !== null) {
            $progressCallback(100, sprintf('Export complete (%d rows)', $processed));
        }

        return [
            'file_name' => basename($path),
            'row_count' => $processed,
            'generated_at' => date(DATE_ATOM),
        ];
    }
}
