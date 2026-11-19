<?php

declare(strict_types=1);

namespace Tests\Support\Fixture\LeadingBackslash;

final class Consumer
{
    public function normalImported(): \DateTimeImmutable
    {
        return new \DateTimeImmutable();
    }

    public function normalExplicit(): \DateTimeImmutable
    {
        return new \DateTimeImmutable();
    }
}
