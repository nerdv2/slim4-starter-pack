<?php

declare(strict_types=1);

namespace App\DTO\Request;

use App\Constants\CustomerStatus;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Customer create payload. Email is normalized to lowercase so the uniqueness
 * check stays case-insensitive across drivers.
 */
final readonly class CustomerCreateRequest
{
    public const int MAX_TEXT_LENGTH = 5000;

    public function __construct(
        public string $name,
        public ?string $email,
        public ?string $phone,
        public ?string $company,
        public string $status,
        public ?string $address,
        public ?string $notes
    ) {
    }

    public static function fromRequest(ServerRequestInterface $request): self
    {
        $body = $request->getParsedBody();
        $body = is_array($body) ? $body : [];

        $rawStatus = strtolower(trim((string) ($body['status'] ?? '')));

        return new self(
            name: trim((string) ($body['name'] ?? '')),
            email: self::nullableString($body['email'] ?? null, lowercase: true),
            phone: self::nullableString($body['phone'] ?? null),
            company: self::nullableString($body['company'] ?? null),
            status: $rawStatus === '' ? CustomerStatus::DEFAULT : $rawStatus,
            address: self::nullableString($body['address'] ?? null),
            notes: self::nullableString($body['notes'] ?? null)
        );
    }

    /**
     * @return array<string, string>
     */
    public function validate(): array
    {
        $errors = [];

        if ($this->name === '') {
            $errors['name'] = 'Name is required.';
        } elseif (mb_strlen($this->name) > 150) {
            $errors['name'] = 'Name must not exceed 150 characters.';
        }

        if ($this->email !== null) {
            if (filter_var($this->email, FILTER_VALIDATE_EMAIL) === false) {
                $errors['email'] = 'A valid email address is required.';
            } elseif (mb_strlen($this->email) > 190) {
                $errors['email'] = 'Email must not exceed 190 characters.';
            }
        }

        if ($this->phone !== null && mb_strlen($this->phone) > 40) {
            $errors['phone'] = 'Phone must not exceed 40 characters.';
        }

        if ($this->company !== null && mb_strlen($this->company) > 150) {
            $errors['company'] = 'Company must not exceed 150 characters.';
        }

        if (!CustomerStatus::isValid($this->status)) {
            $errors['status'] = 'Status must be one of: ' . implode(', ', CustomerStatus::ALL) . '.';
        }

        if ($this->address !== null && mb_strlen($this->address) > self::MAX_TEXT_LENGTH) {
            $errors['address'] = sprintf('Address must not exceed %d characters.', self::MAX_TEXT_LENGTH);
        }

        if ($this->notes !== null && mb_strlen($this->notes) > self::MAX_TEXT_LENGTH) {
            $errors['notes'] = sprintf('Notes must not exceed %d characters.', self::MAX_TEXT_LENGTH);
        }

        return $errors;
    }

    public function isValid(): bool
    {
        return $this->validate() === [];
    }

    /**
     * @return array{name: string, email: ?string, phone: ?string, company: ?string, status: string, address: ?string, notes: ?string}
     */
    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'email' => $this->email,
            'phone' => $this->phone,
            'company' => $this->company,
            'status' => $this->status,
            'address' => $this->address,
            'notes' => $this->notes,
        ];
    }

    private static function nullableString(mixed $value, bool $lowercase = false): ?string
    {
        if (!is_string($value)) {
            return null;
        }

        $trimmed = trim($value);
        if ($trimmed === '') {
            return null;
        }

        return $lowercase ? strtolower($trimmed) : $trimmed;
    }
}
