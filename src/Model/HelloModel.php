<?php

declare(strict_types=1);

namespace App\Model;

/**
 * HelloModel class
 */
final class HelloModel
{
    public function getHello()
    {
        $data = "Hello world!";

        return $data;
    }
}
