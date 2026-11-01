<?php

declare(strict_types=1);

namespace Tests\Unit\Application\Federation;

use ItPack\AsyncIt\Contract\PromiseInterface;
use ItPack\RunIt\Contract\Domain\DurationInterface;

final readonly class ResolvedPromise implements PromiseInterface
{
    public function __construct(
        private mixed $value,
    ) {
    }

    public function isDone(): bool
    {
        return true;
    }

    public function wait(?DurationInterface $timeout = null): bool
    {
        return true;
    }

    public function get(): mixed
    {
        return $this->value;
    }
}
