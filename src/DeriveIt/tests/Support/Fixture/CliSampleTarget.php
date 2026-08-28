<?php

declare(strict_types=1);

namespace Tests\Support\Fixture;

/**
 * Sample CLI derivation target with a plain method, a void method, and a static
 * factory (R4-7 / F-5 — extracted from DeriveCommandTest).
 */
class CliSampleTarget
{
    public function hello(string $name): string
    {
        return $name;
    }

    public function widget(): void
    {
    }

    public static function make(): self
    {
        return new self();
    }
}
