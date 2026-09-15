<?php

declare(strict_types=1);

namespace App\Exceptions;

use App\Constants\HttpStatus;

final class NotFoundException extends AppException
{
    public function __construct(string $message = 'Resource not found.')
    {
        parent::__construct($message, HttpStatus::NOT_FOUND);
    }
}
