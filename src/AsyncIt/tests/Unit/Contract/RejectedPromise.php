<?php

declare(strict_types=1);

namespace Tests\Unit\Contract;

use ItPack\AsyncIt\Contract\PromiseInterface;
use ItPack\RunIt\Contract\Domain\DurationInterface;
use Throwable;

final readonly class RejectedPromise implements PromiseInterface
{
    public function __construct(
        private Throwable $error,
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

    /**
     * @throws Throwable
     */
    public function get(): mixed
    {
        throw $this->error;
    }
}
