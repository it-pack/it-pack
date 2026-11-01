<?php

declare(strict_types=1);

namespace Tests\Unit\Application\Federation;

use ItPack\AsyncIt\Contract\PromiseInterface;
use ItPack\RunIt\Contract\Domain\DurationInterface;
use RuntimeException;

final readonly class PendingPromise implements PromiseInterface
{
    public function isDone(): bool
    {
        return false;
    }

    public function wait(?DurationInterface $timeout = null): bool
    {
        return false;
    }

    public function get(): mixed
    {
        throw new RuntimeException('Promise never resolved.');
    }
}
