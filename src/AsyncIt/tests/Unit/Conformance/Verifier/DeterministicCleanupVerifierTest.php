<?php

declare(strict_types=1);

namespace Tests\Unit\Conformance\Verifier;

use ItPack\AsyncIt\Conformance\Fixture\AsyncRuntimeFixtureInterface;
use ItPack\AsyncIt\Conformance\Result\ViolationSeverity;
use ItPack\AsyncIt\Conformance\Verifier\DeterministicCleanupVerifier;
use ItPack\AsyncIt\Contract\PollerPortInterface;
use ItPack\AsyncIt\Contract\Port\StreamPollHandleInterface;
use ItPack\AsyncIt\Domain\StreamPollHandle;
use ItPack\RunIt\Contract\Application\ExecutionScopeInterface;
use ItPack\RunIt\Contract\Port\ContextStorageInterface;
use ItPack\RunIt\Contract\Port\ContextTokenInterface;
use PHPUnit\Framework\TestCase;
use Tests\Unit\Conformance\Fixture\FakeRuntimeFixture;

/**
 * Negative (and logic) siblings for conformance Assertion C — Deterministic
 * Cleanup.
 *
 * A runtime that retains a poll handle after `release()` must be reported with
 * the `handle-leak` id at error severity; a runtime that releases it must pass.
 *
 * @covers \ItPack\AsyncIt\Conformance\Verifier\DeterministicCleanupVerifier
 */
final class DeterministicCleanupVerifierTest extends TestCase
{
    public function testDetectsLeakedHandle(): void
    {
        $verifier = new DeterministicCleanupVerifier();
        $result = $verifier->verify($this->leakingFixture());

        self::assertFalse($result->isConformant());
        self::assertCount(1, $result->errors());
        self::assertSame('handle-leak', $result->errors()[0]->id);
        self::assertSame(ViolationSeverity::Error, $result->errors()[0]->severity);
    }

    public function testPassesWhenHandleReleased(): void
    {
        $verifier = new DeterministicCleanupVerifier();
        $result = $verifier->verify($this->cleanFixture());

        self::assertTrue($result->isConformant());
        self::assertCount(0, $result->errors());
    }

    private function leakingFixture(): AsyncRuntimeFixtureInterface
    {
        return new FakeRuntimeFixture($this->idleStorage(), 1, poller: $this->stubPoller());
    }

    private function cleanFixture(): AsyncRuntimeFixtureInterface
    {
        return new FakeRuntimeFixture($this->idleStorage(), 0, poller: $this->stubPoller());
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
}
