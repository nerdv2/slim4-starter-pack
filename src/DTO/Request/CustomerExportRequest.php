<?php

declare(strict_types=1);

namespace App\DTO\Request;

use App\Constants\CustomerStatus;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Filters accepted by the customer CSV export endpoint.
 */
final readonly class CustomerExportRequest
{
    public function __construct(
        public string $keywords,
        public ?string $status
    ) {
    }

    public static function fromRequest(ServerRequestInterface $request): self
    {
        $body = $request->getParsedBody();
        $body = is_array($body) ? $body : [];
        $rawStatus = strtolower(trim((string) ($body['status'] ?? '')));

        return new self(
            keywords: trim((string) ($body['keywords'] ?? '')),
            status: $rawStatus === '' ? null : $rawStatus
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
