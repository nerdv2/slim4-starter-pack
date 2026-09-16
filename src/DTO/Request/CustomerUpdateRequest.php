<?php

declare(strict_types=1);

namespace App\DTO\Request;

use Psr\Http\Message\ServerRequestInterface;

/**
 * Customer update payload. The route supplies the id; the field payload reuses
 * CustomerCreateRequest so create and update validate identically.
 */
final readonly class CustomerUpdateRequest
{
    public function __construct(
        public int $id,
        public CustomerCreateRequest $payload
    ) {
    }

    public static function fromRequest(ServerRequestInterface $request, int|string|null $id = null): self
    {
        $validatedId = filter_var($id, FILTER_VALIDATE_INT);

        return new self(
            id: is_int($validatedId) && $validatedId > 0 ? $validatedId : 0,
            payload: CustomerCreateRequest::fromRequest($request)
        );
    }

    /**
     * @return array<string, string>
     */
    public function validate(): array
    {
        $errors = $this->payload->validate();

        if ($this->id <= 0) {
            $errors = ['id' => 'A positive id is required.'] + $errors;
        }

        return $errors;
    }

    public function isValid(): bool
    {
        return $this->validate() === [];
    }
}
