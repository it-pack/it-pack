<?php

declare(strict_types=1);

namespace ItPack\AsyncIt\Contract;

use ItPack\RunIt\Contract\Domain\DurationInterface;

interface RetryPolicyInterface
{
    /**
     * The maximum number of attempts before giving up.
     */
    public int $maxAttempts { get; }

    /**
     * @param class-string $exceptionClass
     */
    public function isRetryable(string $exceptionClass): bool;

    /**
     * Computes the delay for the given attempt number (1-indexed) using
     * exponential backoff with optional jitter.
     */
    public function delayForAttempt(int $attempt): DurationInterface;
}
