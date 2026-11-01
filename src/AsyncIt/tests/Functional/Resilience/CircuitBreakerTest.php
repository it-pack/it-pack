<?php

declare(strict_types=1);

namespace Tests\Functional\Resilience;

use ItPack\AsyncIt\Application\Clock\DeterministicMonotonicClock;
use ItPack\AsyncIt\Contract\Port\CircuitState;
use ItPack\AsyncIt\Domain\CircuitBreaker;
use ItPack\AsyncIt\Domain\Exception\CircuitBreakerOpenException;
use ItPack\RunIt\Domain\Shared\Duration;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Throwable;

/**
 * @covers \ItPack\AsyncIt\Domain\CircuitBreaker
 */
final class CircuitBreakerTest extends TestCase
{
    public function testCircuitBreakerStartsClosed(): void
    {
        $clock = new DeterministicMonotonicClock();
        $breaker = new CircuitBreaker($clock);

        self::assertSame(CircuitState::Closed, $breaker->state());
        self::assertTrue($breaker->canExecute());
    }

    public function testCircuitBreakerOpensAfterFailureThreshold(): void
    {
        $clock = new DeterministicMonotonicClock();
        $breaker = new CircuitBreaker($clock, failureThreshold: 2);

        try {
            $breaker->allowExecution(static fn (): never => throw new RuntimeException('fail'));
        } catch (RuntimeException) {
        }

        self::assertSame(CircuitState::Closed, $breaker->state());

        try {
            $breaker->allowExecution(static fn (): never => throw new RuntimeException('fail'));
        } catch (RuntimeException) {
        }

        self::assertSame(CircuitState::Open, $breaker->state());
        self::assertFalse($breaker->canExecute());
    }

    public function testCircuitBreakerThrowsOnOpenCircuit(): void
    {
        $clock = new DeterministicMonotonicClock();
        $breaker = new CircuitBreaker($clock, failureThreshold: 1);

        try {
            $breaker->allowExecution(static fn (): never => throw new RuntimeException('fail'));
        } catch (RuntimeException) {
        }

        $this->expectException(CircuitBreakerOpenException::class);
        $breaker->allowExecution(static fn (): string => 'should not run');
    }

    public function testCircuitBreakerPreservesOriginalException(): void
    {
        $clock = new DeterministicMonotonicClock();
        $breaker = new CircuitBreaker($clock, failureThreshold: 3);
        $original = new RuntimeException('original');

        try {
            $breaker->allowExecution(static function () use ($original): never {
                throw $original;
            });
        } catch (Throwable $throwable) {
            self::assertSame($original, $throwable);
        }
    }

    public function testCircuitBreakerTransitionsToHalfOpenAfterCooldown(): void
    {
        $clock = new DeterministicMonotonicClock();
        $breaker = new CircuitBreaker(
            $clock,
            failureThreshold: 1,
            cooldown: Duration::ofNanoseconds(1_000_000),
        );

        try {
            $breaker->allowExecution(static fn (): never => throw new RuntimeException('fail'));
        } catch (RuntimeException) {
        }

        self::assertSame(CircuitState::Open, $breaker->state());

        $clock->advance(1_000_001);

        self::assertSame(CircuitState::HalfOpen, $breaker->state());
        self::assertTrue($breaker->canExecute());
    }

    public function testCircuitBreakerReturnsToClosedAfterHalfOpenSuccess(): void
    {
        $clock = new DeterministicMonotonicClock();
        $breaker = new CircuitBreaker(
            $clock,
            failureThreshold: 1,
            cooldown: Duration::ofNanoseconds(1_000_000),
        );

        try {
            $breaker->allowExecution(static fn (): never => throw new RuntimeException('fail'));
        } catch (RuntimeException) {
        }

        $clock->advance(1_000_001);
        $breaker->allowExecution(static fn (): string => 'ok');

        self::assertSame(CircuitState::Closed, $breaker->state());
    }
}
