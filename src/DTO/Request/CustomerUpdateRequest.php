<?php

declare(strict_types=1);

namespace App\DTO\Request;

use Psr\Http\Message\ServerRequestInterface;

final readonly class CustomerUpdateRequest
{
    public function __construct(
        public int $id,
        public string $name
    ) {
    }

    public static function fromRequest(ServerRequestInterface $request): self
    {
        $body = $request->getParsedBody();
        $body = is_array($body) ? $body : [];
        $id = filter_var($body['id'] ?? null, FILTER_VALIDATE_INT);

        return new self(
            id: is_int($id) && $id > 0 ? $id : 0,
            name: trim((string) ($body['name'] ?? ''))
        );
    }

    /**
     * @return array<string, string>
     */
    public function validate(): array
    {
        $errors = [];

        if ($this->id <= 0) {
            $errors['id'] = 'A positive id is required.';
        }
        if ($this->name === '') {
            $errors['name'] = 'Name is required.';
        }

        return $errors;
    }

    public function isValid(): bool
    {
        return $this->validate() === [];
    }
}
