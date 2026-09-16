<?php

declare(strict_types=1);

namespace App\DTO\Request;

use Psr\Http\Message\ServerRequestInterface;

final readonly class LoginRequest
{
    public function __construct(
        public string $email,
        public string $password
    ) {
    }

    public static function fromRequest(ServerRequestInterface $request): self
    {
        $body = $request->getParsedBody();
        $body = is_array($body) ? $body : [];

        return new self(
            email: strtolower(trim((string) ($body['email'] ?? ''))),
            password: (string) ($body['password'] ?? '')
        );
    }

    /**
     * @return array<string, string>
     */
    public function validate(): array
    {
        $errors = [];

        if ($this->email === '') {
            $errors['email'] = 'Email is required.';
        } elseif (filter_var($this->email, FILTER_VALIDATE_EMAIL) === false) {
            $errors['email'] = 'A valid email address is required.';
        }

        if ($this->password === '') {
            $errors['password'] = 'Password is required.';
        }

        return $errors;
    }

    public function isValid(): bool
    {
        return $this->validate() === [];
    }
}
