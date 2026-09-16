<?php

declare(strict_types=1);

namespace App\DTO\Request;

use Psr\Http\Message\ServerRequestInterface;

final readonly class ChangePasswordRequest
{
    public function __construct(
        public string $currentPassword,
        public string $newPassword,
        public string $newPasswordConfirmation
    ) {
    }

    public static function fromRequest(ServerRequestInterface $request): self
    {
        $body = $request->getParsedBody();
        $body = is_array($body) ? $body : [];

        return new self(
            currentPassword: (string) ($body['current_password'] ?? ''),
            newPassword: (string) ($body['new_password'] ?? ''),
            newPasswordConfirmation: (string) ($body['new_password_confirmation'] ?? '')
        );
    }

    /**
     * @return array<string, string>
     */
    public function validate(): array
    {
        $errors = [];

        if ($this->currentPassword === '') {
            $errors['current_password'] = 'Current password is required.';
        }

        $newLength = strlen($this->newPassword);
        if ($this->newPassword === '') {
            $errors['new_password'] = 'New password is required.';
        } elseif ($newLength < RegisterRequest::MIN_PASSWORD_LENGTH) {
            $errors['new_password'] = sprintf(
                'New password must be at least %d characters.',
                RegisterRequest::MIN_PASSWORD_LENGTH
            );
        } elseif ($newLength > RegisterRequest::MAX_PASSWORD_LENGTH) {
            $errors['new_password'] = sprintf(
                'New password must not exceed %d characters.',
                RegisterRequest::MAX_PASSWORD_LENGTH
            );
        } elseif ($this->newPassword === $this->currentPassword) {
            $errors['new_password'] = 'New password must differ from the current password.';
        }

        if ($this->newPasswordConfirmation === '') {
            $errors['new_password_confirmation'] = 'New password confirmation is required.';
        } elseif ($this->newPassword !== $this->newPasswordConfirmation) {
            $errors['new_password_confirmation'] = 'New password confirmation does not match.';
        }

        return $errors;
    }

    public function isValid(): bool
    {
        return $this->validate() === [];
    }
}
