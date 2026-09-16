<?php

declare(strict_types=1);

namespace Tests\Traits;

trait OverridesEnvironment
{
    /** @var array<string, string|false|null> */
    private array $originalEnvironment = [];

    /**
     * Override environment variables for the current test, remembering the
     * original values for restoreEnvironment().
     *
     * @param array<string, string> $values
     */
    protected function overrideEnvironment(array $values): void
    {
        foreach ($values as $name => $value) {
            if (!array_key_exists($name, $this->originalEnvironment)) {
                $this->originalEnvironment[$name] = $_SERVER[$name] ?? $_ENV[$name] ?? getenv($name);
            }

            $_SERVER[$name] = $_ENV[$name] = $value;
            putenv($name . '=' . $value);
        }
    }

    protected function restoreEnvironment(): void
    {
        foreach ($this->originalEnvironment as $name => $original) {
            unset($_SERVER[$name], $_ENV[$name]);
            putenv($name);

            if (is_string($original)) {
                $_SERVER[$name] = $_ENV[$name] = $original;
                putenv($name . '=' . $original);
            }
        }

        $this->originalEnvironment = [];
    }
}
