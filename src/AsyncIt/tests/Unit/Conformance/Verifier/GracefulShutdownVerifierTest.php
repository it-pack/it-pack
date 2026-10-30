<?php

declare(strict_types=1);

namespace Tests\Unit\Conformance\Verifier;

use ItPack\AsyncIt\Conformance\Verifier\GracefulShutdownVerifier;
use ItPack\AsyncIt\Contract\CancellationScopeInterface;
use ItPack\AsyncIt\Contract\EngineSchedulerInterface;
use ItPack\AsyncIt\Contract\PollerPortInterface;
use ItPack\AsyncIt\Contract\Port\CancellationReason;
use ItPack\AsyncIt\Contract\Port\StreamPollHandleInterface;
use ItPack\AsyncIt\Domain\StreamPollHandle;
use ItPack\RunIt\Contract\Application\ExecutionScopeInterface;
use ItPack\RunIt\Contract\Domain\DurationInterface;
use ItPack\RunIt\Contract\Port\ContextStorageInterface;
use ItPack\RunIt\Contract\Port\ContextTokenInterface;
use ItPack\RunIt\Contract\Port\MonotonicClockInterface;
use ItPack\RunIt\Domain\Shared\Duration;
use PHPUnit\Framework\TestCase;
use Tests\Unit\Conformance\Fixture\FakeRuntimeFixture;

/**
 * Negative siblings for conformance Assertion F — Graceful Shutdown.
 *
 * A fixture whose scheduler does not implement EngineSchedulerInterface must be
 * reported as unsupported; a fixture that leaks handles after shutdown must be
 * reported with the `handle-leak` id.
 *
 * @covers \ItPack\AsyncIt\Conformance\Verifier\GracefulShutdownVerifier
 */
final class GracefulShutdownVerifierTest extends TestCase
{
    public function testReportsUnsupportedWhenSchedulerNotEngineScheduler(): void
    {
        $verifier = new GracefulShutdownVerifier();

        $fixture = new FakeRuntimeFixture(
            $this->idleStorage(),
            0,
        );

        $result = $verifier->verify($fixture);

        self::assertFalse($result->isConformant());
        self::assertCount(1, $result->errors());
        self::assertSame('fixture-unsupported', $result->errors()[0]->id);
    }

    public function testPassesWhenSchedulerDrainsHandles(): void
    {
        $verifier = new GracefulShutdownVerifier();

        $scheduler = $this->createMock(EngineSchedulerInterface::class);
        $scheduler->expects(self::once())->method('runUntil');

        $fixture = new FakeRuntimeFixture(
            $this->idleStorage(),
            0,
            scheduler: $scheduler,
            poller: $this->stubPoller(),
            clock: $this->stubClock(),
            cancellationScope: $this->stubScope(),
        );

        $result = $verifier->verify($fixture);

        self::assertTrue($result->isConformant());
        self::assertCount(0, $result->errors());
    }

    public function testDetectsHandleLeakAfterShutdown(): void
    {
        $verifier = new GracefulShutdownVerifier();

        $scheduler = $this->createMock(EngineSchedulerInterface::class);
        $scheduler->expects(self::once())->method('runUntil');

        $fixture = new FakeRuntimeFixture(
            $this->idleStorage(),
            1,
            scheduler: $scheduler,
            poller: $this->leakPoller(),
            clock: $this->stubClock(),
            cancellationScope: $this->stubScope(),
        );

        $result = $verifier->verify($fixture);

        self::assertFalse($result->isConformant());
        self::assertCount(1, $result->errors());
        self::assertSame('handle-leak', $result->errors()[0]->id);
    }

    private function idleStorage(): ContextStorageInterface
    {
        return new class () implements ContextStorageInterface {
            public function current(): ?ExecutionScopeInterface
            {
                return null;
            }

            public function attach(ExecutionScopeInterface $scope): ContextTokenInterface
            {
                return new class () implements ContextTokenInterface {
                    public function id(): int
                    {
                        return 0;
                    }
                };
            }

            public function detach(ContextTokenInterface $token): void
            {
            }
        };
    }

    private function stubScope(): CancellationScopeInterface
    {
        return new class () implements CancellationScopeInterface {
            public function isCancelled(): bool
            {
                return false;
            }

            public function cancel(?CancellationReason $reason = null): void
            {
            }

            public function reason(): ?CancellationReason
            {
                return null;
            }

            public function throwIfCancelled(): void
            {
            }

            public function onCancel(callable $callback): void
            {
            }
        };
    }

    private function stubClock(): MonotonicClockInterface
    {
        return new class () implements MonotonicClockInterface {
            public function nowSeconds(): int
            {
                return 0;
            }

            public function monotonicNanoseconds(): int
            {
                return 0;
            }

            public function measure(int $fromInclusive, int $toInclusive): DurationInterface
            {
                return Duration::ofNanoseconds(0);
            }
        };
    }

    private function stubPoller(): PollerPortInterface
    {
        return new class () implements PollerPortInterface {
            public function register(mixed $stream): StreamPollHandleInterface
            {
                return new StreamPollHandle('stub');
            }

            public function release(StreamPollHandleInterface $handle): void
            {
            }

            public function await(int $timeoutNanoseconds): void
            {
            }

            public function activeHandleCount(): int
            {
                return 0;
            }
        };
    }

    private function leakPoller(): PollerPortInterface
    {
        return new class () implements PollerPortInterface {
            public function register(mixed $stream): StreamPollHandleInterface
            {
                return new StreamPollHandle('leak');
            }

            public function release(StreamPollHandleInterface $handle): void
            {
            }

            public function await(int $timeoutNanoseconds): void
            {
            }

            public function activeHandleCount(): int
            {
                return 1;
            }
        };
    }
}
