<?php

declare(strict_types=1);

namespace ItPack\AsyncIt\Domain;

use ItPack\AsyncIt\Contract\AsyncItExceptionInterface;
use ItPack\AsyncIt\Contract\Port\JitterStrategy;
use ItPack\AsyncIt\Contract\Port\RetryStrategy;
use ItPack\AsyncIt\Contract\RetryPolicyInterface;
use ItPack\RunIt\Contract\Domain\DurationInterface;
use ItPack\RunIt\Domain\Shared\Duration;
use Random\RandomException;

/**
 * Immutable retry policy describing how the scheduler should retry a task
 * after a transient failure.
 *
 * @see https://w3id.org/itpack/asyncit#RetryPolicy
 * @see https://w3id.org/itpack/asyncit-taxonomy#retry-strategies
 * @see https://w3id.org/itpack/asyncit-taxonomy#jitter-strategies
 */
final readonly class RetryPolicy implements RetryPolicyInterface
{
    public int $maxAttempts;

    public DurationInterface $baseDelay;

    public DurationInterface $maxDelay;

    public RetryStrategy $strategy;

    public JitterStrategy $jitter;

    /**
     * @var list<class-string>
     */
    public array $retryable;

    /**
     * @param list<class-string> $retryable
     */
    public function __construct(
        ?int $maxAttempts = null,
        ?DurationInterface $baseDelay = null,
        ?DurationInterface $maxDelay = null,
        ?RetryStrategy $strategy = null,
        ?JitterStrategy $jitter = null,
        ?array $retryable = null,
    ) {
        $this->maxAttempts = $maxAttempts ?? 3;
        $this->baseDelay = $baseDelay ?? Duration::ofNanoseconds(1_000_000_000);
        $this->maxDelay = $maxDelay ?? Duration::ofNanoseconds(30_000_000_000);
        $this->strategy = $strategy ?? RetryStrategy::Exponential;
        $this->jitter = $jitter ?? JitterStrategy::Full;
        $this->retryable = $retryable ?? [
            'RuntimeException',
            AsyncItExceptionInterface::class,
        ];
    }

    /**
     * @param class-string $exceptionClass
     */
    public function isRetryable(string $exceptionClass): bool
    {
        return $this->retryable === [] || in_array($exceptionClass, $this->retryable, strict: true);
    }

    /**
     * Computes the delay for the given attempt number (1-indexed) using
     * exponential backoff with optional jitter.
     */
    public function delayForAttempt(int $attempt): DurationInterface
    {
        $delayNanoseconds = match ($this->strategy) {
            RetryStrategy::Exponential => (int) ($this->baseDelay->nanoseconds() * (2 ** ($attempt - 1))),
            RetryStrategy::Fixed => $this->baseDelay->nanoseconds(),
            RetryStrategy::Fibonacci => $this->fibonacciDelay($attempt),
        };

        $delayNanoseconds = min($delayNanoseconds, $this->maxDelay->nanoseconds());

        return match ($this->jitter) {
            JitterStrategy::None => Duration::ofNanoseconds($delayNanoseconds),
            JitterStrategy::Full => $this->applyFullJitter($delayNanoseconds),
            JitterStrategy::Equal => $this->applyEqualJitter($delayNanoseconds),
        };
    }

    private function fibonacciDelay(int $attempt): int
    {
        $base = $this->baseDelay->nanoseconds();
        $a = 0;
        $b = 1;
        for ($i = 1; $i < $attempt; $i++) {
            $next = $a + $b;
            $a = $b;
            $b = $next;
        }

        return $base * $b;
    }

    /**
     * @throws RandomException
     */
    private function applyFullJitter(int $delayNanoseconds): DurationInterface
    {
        if ($delayNanoseconds <= 0) {
            return Duration::ofNanoseconds(0);
        }

        $jitter = random_int(0, $delayNanoseconds);

        return Duration::ofNanoseconds($jitter);
    }

    /**
     * @throws RandomException
     */
    private function applyEqualJitter(int $delayNanoseconds): DurationInterface
    {
        if ($delayNanoseconds <= 0) {
            return Duration::ofNanoseconds(0);
        }

        $half = intdiv($delayNanoseconds, 2);
        $jitter = random_int(0, $half);

        return Duration::ofNanoseconds($half + $jitter);
    }
}
