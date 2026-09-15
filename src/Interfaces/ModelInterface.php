<?php

declare(strict_types=1);

namespace App\Interfaces;

use Oeltima\SimpleQuery\Connection;

interface ModelInterface
{
    public function __construct(Connection $database);
}
