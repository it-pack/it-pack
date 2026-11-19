<?php

declare(strict_types=1);

namespace Tests\Support\Fixture\LeadingBackslash;

class ExampleClass
{
    public function imported(): \DateTimeImmutable
    {
        return new \DateTimeImmutable();
    }

    public function explicit(): \DateTimeImmutable
    {
        return new \DateTimeImmutable();
    }
}
