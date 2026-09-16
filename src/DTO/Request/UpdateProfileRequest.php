<?php

declare(strict_types=1);

namespace App\DTO\Request;

use Psr\Http\Message\ServerRequestInterface;

final readonly class UpdateProfileRequest
{
    public function __construct(
        public string $name
    ) {
    }

    public static function fromRequest(ServerRequestInterface $request): self
    {
        $body = $request->getParsedBody();
        $body = is_array($body) ? $body : [];

        return new self(
            name: trim((string) ($body['name'] ?? ''))
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
        } elseif (mb_strlen($this->name) > 120) {
            $errors['name'] = 'Name must not exceed 120 characters.';
        }

        return $errors;
    }

    public function isValid(): bool
    {
        return $this->validate() === [];
    }
}
