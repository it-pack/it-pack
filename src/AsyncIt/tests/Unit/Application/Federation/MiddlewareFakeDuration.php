<?php

declare(strict_types=1);

namespace Tests\Unit\Application\Federation;

use ItPack\RunIt\Contract\Domain\DurationInterface;

final class MiddlewareFakeDuration implements DurationInterface
{
    public function nanoseconds(): int
    {
        return 0;
    }

    public function seconds(): int
    {
        return 0;
    }

    public function toString(): string
    {
        return '0s';
    }
}
