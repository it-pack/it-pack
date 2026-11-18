<?php

declare(strict_types=1);

namespace Tests\Support\Fixture\LeadingBackslash;

use DateTimeImmutable;

final class Consumer
{
    use ExampleTrait;

    public function normalImported(): DateTimeImmutable
    {
        return new DateTimeImmutable();
    }

    public function normalExplicit(): \DateTimeImmutable
    {
        return new \DateTimeImmutable();
    }
}
