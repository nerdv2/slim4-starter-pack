<?php

declare(strict_types=1);

namespace App\Exceptions;

use App\Constants\HttpStatus;

final class ValidationException extends AppException
{
    /**
     * @param array<string, string> $errors Field-keyed validation messages.
     */
    public function __construct(
        string $message = 'Validation failed.',
        private readonly array $errors = []
    ) {
        parent::__construct($message, HttpStatus::BAD_REQUEST);
    }

    /**
     * @return array<string, string>
     */
    public function errors(): array
    {
        return $this->errors;
    }
}
