<?php

declare(strict_types=1);

namespace Tests\Support\Fixture;

/**
 * Plain sample target used by the CLI argument-parser tests as a derivable
 * class-string (R4-7 / F-5 — extracted from ArgParserTest).
 */
class ArgParserSample
{
    public function hello(string $name): string
    {
        return $name;
    }
}
