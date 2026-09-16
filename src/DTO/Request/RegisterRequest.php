<?php

declare(strict_types=1);

namespace App\DTO\Request;

use Psr\Http\Message\ServerRequestInterface;

final readonly class RegisterRequest
{
    public const int MIN_PASSWORD_LENGTH = 8;

    /**
     * BCrypt truncates at 72 bytes; reject longer input instead of silently
     * ignoring the tail.
     */
    public const int MAX_PASSWORD_LENGTH = 72;

    public function __construct(
        public string $name,
        public string $email,
        public string $password,
        public string $passwordConfirmation
    ) {
    }

    public static function fromRequest(ServerRequestInterface $request): self
    {
        $body = $request->getParsedBody();
        $body = is_array($body) ? $body : [];

        return new self(
            name: trim((string) ($body['name'] ?? '')),
            email: strtolower(trim((string) ($body['email'] ?? ''))),
            password: (string) ($body['password'] ?? ''),
            passwordConfirmation: (string) ($body['password_confirmation'] ?? '')
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

        if ($this->email === '') {
            $errors['email'] = 'Email is required.';
        } elseif (filter_var($this->email, FILTER_VALIDATE_EMAIL) === false) {
            $errors['email'] = 'A valid email address is required.';
        } elseif (mb_strlen($this->email) > 190) {
            $errors['email'] = 'Email must not exceed 190 characters.';
        }

        $passwordLength = strlen($this->password);
        if ($this->password === '') {
            $errors['password'] = 'Password is required.';
        } elseif ($passwordLength < self::MIN_PASSWORD_LENGTH) {
            $errors['password'] = sprintf(
                'Password must be at least %d characters.',
                self::MIN_PASSWORD_LENGTH
            );
        } elseif ($passwordLength > self::MAX_PASSWORD_LENGTH) {
            $errors['password'] = sprintf(
                'Password must not exceed %d characters.',
                self::MAX_PASSWORD_LENGTH
            );
        }

        if ($this->passwordConfirmation === '') {
            $errors['password_confirmation'] = 'Password confirmation is required.';
        } elseif ($this->password !== $this->passwordConfirmation) {
            $errors['password_confirmation'] = 'Password confirmation does not match.';
        }

        return $errors;
    }

    public function isValid(): bool
    {
        return $this->validate() === [];
    }
}
