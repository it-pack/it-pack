<?php

declare(strict_types=1);

namespace Tests\Unit\Domain;

use ItPack\AsyncIt\Contract\Port\CircuitState;
use ItPack\AsyncIt\Domain\CircuitBreaker;
use ItPack\AsyncIt\Domain\Exception\CircuitBreakerOpenException;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Throwable;

/**
 * @covers \ItPack\AsyncIt\Domain\CircuitBreaker
 */
final class CircuitBreakerTest extends TestCase
{
    public function testStartsClosed(): void
    {
        $breaker = new CircuitBreaker();

        self::assertSame(CircuitState::Closed, $breaker->state());
        self::assertTrue($breaker->canExecute());
    }

    public function testAllowsExecutionOnSuccess(): void
    {
        $breaker = new CircuitBreaker();

        self::assertSame('ok', $breaker->allowExecution(static fn (): string => 'ok'));
        self::assertSame(CircuitState::Closed, $breaker->state());
    }

    public function testOpensAfterFailureThreshold(): void
    {
        $breaker = new CircuitBreaker(failureThreshold: 2);

        $this->failingCall($breaker);
        self::assertSame(CircuitState::Closed, $breaker->state());

        $this->failingCall($breaker);
        self::assertSame(CircuitState::Open, $breaker->state());
        self::assertFalse($breaker->canExecute());
    }

    public function testThrowsOnOpenCircuit(): void
    {
        $breaker = new CircuitBreaker(failureThreshold: 1);

        $this->failingCall($breaker);
        self::assertSame(CircuitState::Open, $breaker->state());

        $this->expectException(CircuitBreakerOpenException::class);

        $breaker->allowExecution(static fn (): string => 'should not run');
    }

    public function testPreservesOriginalException(): void
    {
        $breaker = new CircuitBreaker(failureThreshold: 3);

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
