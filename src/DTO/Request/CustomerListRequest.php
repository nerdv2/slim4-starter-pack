<?php

declare(strict_types=1);

namespace App\DTO\Request;

use App\Constants\CustomerStatus;
use App\Helper\Pagination;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Customer list filters and pagination, sanitized from the query string.
 */
final readonly class CustomerListRequest
{
    public function __construct(
        public string $keywords,
        public ?string $status,
        public int $page,
        public int $limit
    ) {
    }

    public static function fromRequest(ServerRequestInterface $request): self
    {
        $query = $request->getQueryParams();
        [$page, $limit] = Pagination::sanitize($query['page'] ?? null, $query['limit'] ?? null);
        $rawStatus = strtolower(trim((string) ($query['status'] ?? '')));

        return new self(
            keywords: trim((string) ($query['keywords'] ?? '')),
            status: $rawStatus === '' ? null : $rawStatus,
            page: $page,
            limit: $limit
        );
    }

    /**
     * @return array<string, string>
     */
    public function validate(): array
    {
        if ($this->status !== null && !CustomerStatus::isValid($this->status)) {
            return ['status' => 'Status must be one of: ' . implode(', ', CustomerStatus::ALL) . '.'];
        }

        return [];
    }

    public function isValid(): bool
    {
        return $this->validate() === [];
    }
}
