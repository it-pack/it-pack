<?php

declare(strict_types=1);

namespace Tests\Concurrency;

use ItPack\AsyncIt\Application\Scope\ReferenceExecutionScope;
use ItPack\AsyncIt\SDK\AsyncRuntime;
use ItPack\RunIt\Domain\Shared\Duration;
use PHPUnit\Framework\TestCase;
use RuntimeException;

/**
 * Fiber/coroutine isolation and persistent-worker reuse tests.
 *
 * These prove the scheduler cooperatively interleaves tasks without leaking
 * execution context or scheduler state between runs (the persistent-worker
 * case), which is the unit-level complement of conformance Assertion A
 * (context isolation) and Assertion C (deterministic cleanup).
 *
 * @see https://w3id.org/itpack/asyncit#Scheduler
 * @see https://w3id.org/itpack/runit#Execution
 */
final class FiberConcurrencyTest extends TestCase
{
    public function testConcurrentTasksAllComplete(): void
    {
        $runtime = AsyncRuntime::discover();

        $results = $runtime->run(
            new ReferenceExecutionScope('concurrent'),
            static fn (): array => $runtime->all([
                static fn (): int => 1,
                static fn (): int => 2,
                static fn (): int => 3,
            ]),
        );

        self::assertSame([1, 2, 3], $results);
    }

    public function testRaceCancelsRemainingOnFirstCompletion(): void
    {
        $runtime = AsyncRuntime::discover();

        $value = $runtime->run(
            new ReferenceExecutionScope('race'),
            static fn (): mixed => $runtime->race([
                static fn (): int => 42,
                static fn (): int => 99,
            ]),
        );

        self::assertSame(42, $value);
    }

    public function testPersistentWorkerReuseDoesNotLeakState(): void
    {
        $runtime = AsyncRuntime::discover();

        self::assertNull($runtime->health());

        $first = $runtime->run(
            new ReferenceExecutionScope('run-one'),
            static fn (): string => 'one',
        );

        self::assertSame('one', $first);
        self::assertNull($runtime->health(), 'scheduler state must be released after run one');

        $second = $runtime->run(
            new ReferenceExecutionScope('run-two'),
            static fn (): string => 'two',
        );

        self::assertSame('two', $second);
        self::assertNull($runtime->health(), 'scheduler state must be released after run two');
    }

    public function testAwaitDelayWithinRunningExecution(): void
    {
        $runtime = AsyncRuntime::discover();

        $value = $runtime->run(
            new ReferenceExecutionScope('delay'),
            static function () use ($runtime): int {
                $runtime->awaitDelay(Duration::ofNanoseconds(1));

                return 7;
            },
        );

        self::assertSame(7, $value);
    }

    public function testTaskFailurePropagatesOriginalException(): void
    {
        $runtime = AsyncRuntime::discover();
        $original = new RuntimeException('boom');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('boom');

        $runtime->run(
            new ReferenceExecutionScope('failure'),
            static function () use ($original): never {
                throw $original;
            },
        );
    }
}
