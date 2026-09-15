<?php

declare(strict_types=1);

namespace App\DTO\Request;

use Psr\Http\Message\ServerRequestInterface;

final readonly class CustomerIdRequest
{
    public function __construct(
        public int $id
    ) {
    }

    public static function fromRequest(ServerRequestInterface $request): self
    {
        $body = $request->getParsedBody();
        $body = is_array($body) ? $body : [];
        $id = filter_var($body['id'] ?? null, FILTER_VALIDATE_INT);

        return new self(
            id: is_int($id) && $id > 0 ? $id : 0
        );
    }

    /**
     * @return array<string, string>
     */
    public function validate(): array
    {
        return $this->id <= 0 ? ['id' => 'A positive id is required.'] : [];
    }

    public function isValid(): bool
    {
        return $this->validate() === [];
    }
}
