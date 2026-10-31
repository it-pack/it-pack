<?php

declare(strict_types=1);

namespace Tests\Unit\Domain;

use ItPack\AsyncIt\Contract\Port\CircuitState;
use ItPack\AsyncIt\Domain\CircuitBreaker;
use ItPack\AsyncIt\Domain\Exception\CircuitBreakerOpenException;
use ItPack\RunIt\Contract\Domain\DurationInterface;
use ItPack\RunIt\Contract\Port\MonotonicClockInterface;
use ItPack\RunIt\Domain\Shared\Duration;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Throwable;

/**
 * @covers \ItPack\AsyncIt\Domain\CircuitBreaker
 */
final class CircuitBreakerTest extends TestCase
{
    private MonotonicClockInterface&MockObject $clock;

    protected function setUp(): void
    {
        $this->clock = $this->createMock(MonotonicClockInterface::class);
        $this->clock->method('monotonicNanoseconds')->willReturn(0);
    }

    public function testStartsClosed(): void
    {
        $breaker = new CircuitBreaker($this->clock);

        self::assertSame(CircuitState::Closed, $breaker->state());
        self::assertTrue($breaker->canExecute());
    }

    public function testAllowsExecutionOnSuccess(): void
    {
        $breaker = new CircuitBreaker($this->clock);

        self::assertSame('ok', $breaker->allowExecution(static fn (): string => 'ok'));
        self::assertSame(CircuitState::Closed, $breaker->state());
    }

    public function testOpensAfterFailureThreshold(): void
    {
        $breaker = new CircuitBreaker($this->clock, failureThreshold: 2);

        $this->failingCall($breaker);
        self::assertSame(CircuitState::Closed, $breaker->state());

        $this->failingCall($breaker);
        self::assertSame(CircuitState::Open, $breaker->state());
        self::assertFalse($breaker->canExecute());
    }

    public function testThrowsOnOpenCircuit(): void
    {
        $breaker = new CircuitBreaker($this->clock, failureThreshold: 1);

        $this->failingCall($breaker);
        self::assertSame(CircuitState::Open, $breaker->state());

        $this->expectException(CircuitBreakerOpenException::class);

        $breaker->allowExecution(static fn (): string => 'should not run');
    }

    public function testPreservesOriginalException(): void
    {
        $breaker = new CircuitBreaker($this->clock, failureThreshold: 3);

        $original = new RuntimeException('original');

        try {
            $breaker->allowExecution(static function () use ($original): never {
                throw $original;
            });
        } catch (Throwable $throwable) {
            self::assertSame($original, $throwable);
            self::assertSame('original', $throwable->getMessage());
        }
    }

    public function testTransitionsToHalfOpenAfterCooldown(): void
    {
        $clock = new class () implements MonotonicClockInterface {
            public int $time = 0;

            public function nowSeconds(): int
            {
                return $this->time;
            }

            public function monotonicNanoseconds(): int
            {
                return $this->time;
            }

            public function measure(int $fromInclusive, int $toInclusive): DurationInterface
            {
                return Duration::ofNanoseconds($toInclusive - $fromInclusive);
            }
        };

        $breaker = new CircuitBreaker(
            $clock,
            failureThreshold: 1,
            cooldown: Duration::ofNanoseconds(1_000_000),
        );

        $this->failingCall($breaker);
        self::assertSame(CircuitState::Open, $breaker->state());

        // Before cooldown elapses, still Open
        self::assertFalse($breaker->canExecute());

        // Advance past cooldown
        $clock->time = 1_000_001;

        // Now should be HalfOpen
        self::assertSame(CircuitState::HalfOpen, $breaker->state());
        self::assertTrue($breaker->canExecute());
    }

    public function testReturnsToClosedAfterHalfOpenSuccess(): void
    {
        $clock = new class () implements MonotonicClockInterface {
            public int $time = 0;

            public function nowSeconds(): int
            {
                return $this->time;
            }

            public function monotonicNanoseconds(): int
            {
                return $this->time;
            }

            public function measure(int $fromInclusive, int $toInclusive): DurationInterface
            {
                return Duration::ofNanoseconds($toInclusive - $fromInclusive);
            }
        };

        $breaker = new CircuitBreaker(
            $clock,
            failureThreshold: 1,
            cooldown: Duration::ofNanoseconds(1_000_000),
        );

        $this->failingCall($breaker);
        self::assertSame(CircuitState::Open, $breaker->state());

        $clock->time = 1_000_001;

        // HalfOpen → Closed on success
        $breaker->allowExecution(static fn (): string => 'ok');
        self::assertSame(CircuitState::Closed, $breaker->state());
    }

    private function failingCall(CircuitBreaker $breaker): void
    {
        try {
            $breaker->allowExecution(static function (): never {
                throw new RuntimeException('fail');
            });
        } catch (RuntimeException) {
        }
    }
}
