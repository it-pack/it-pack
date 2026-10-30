<?php

declare(strict_types=1);

namespace Tests\Unit\Conformance\Verifier;

use ItPack\AsyncIt\Conformance\Fixture\AsyncRuntimeFixtureInterface;
use ItPack\AsyncIt\Conformance\Fixture\ReferenceRuntimeFixture;
use ItPack\AsyncIt\Conformance\Verifier\AsyncRuntimeVerifier;
use ItPack\AsyncIt\Conformance\Verifier\ContextIsolationVerifier;
use ItPack\AsyncIt\Conformance\Verifier\DeterministicCleanupVerifier;
use ItPack\AsyncIt\Contract\PollerPortInterface;
use ItPack\AsyncIt\Contract\Port\StreamPollHandle;
use ItPack\RunIt\Contract\Application\ExecutionScopeInterface;
use ItPack\RunIt\Contract\Port\ContextStorageInterface;
use ItPack\RunIt\Contract\Port\ContextTokenInterface;
use PHPUnit\Framework\TestCase;
use Tests\Unit\Conformance\Fixture\FakeExecutionScope;
use Tests\Unit\Conformance\Fixture\FakeRuntimeFixture;

/**
 * Wires the AsyncIt conformance suite (Assertion A + C) and proves the
 * orchestrator aggregates every violation from its member verifiers.
 *
 * The authoritative green run against the real reference runtime is deferred to
 * Phase 2 (the runtime fixture is built there); it is recorded as an incomplete
 * test so the suite remains honest about the "no impls" Phase 1 gate.
 *
 * @covers \ItPack\AsyncIt\Conformance\Verifier\AsyncRuntimeVerifier
 * @covers \ItPack\AsyncIt\Conformance\Verifier\ContextIsolationVerifier
 * @covers \ItPack\AsyncIt\Conformance\Verifier\DeterministicCleanupVerifier
 */
final class AsyncRuntimeVerifierTest extends TestCase
{
    public function testAggregatesEveryAssertionViolation(): void
    {
        $verifier = new AsyncRuntimeVerifier(
            new ContextIsolationVerifier(),
            new DeterministicCleanupVerifier(),
        );

        $result = $verifier->verify($this->doublyBrokenFixture());

        self::assertFalse($result->isConformant());
        self::assertCount(2, $result->errors());
        self::assertSame('ctx-bleed', $result->errors()[0]->id);
        self::assertSame('handle-leak', $result->errors()[1]->id);
    }

    public function testReferenceRuntimeConforms(): void
    {
        $verifier = new AsyncRuntimeVerifier(
            new ContextIsolationVerifier(),
            new DeterministicCleanupVerifier(),
        );

        $result = $verifier->verify(new ReferenceRuntimeFixture());

        self::assertTrue(
            $result->isConformant(),
            $result->summary(),
        );
    }

    private function doublyBrokenFixture(): AsyncRuntimeFixtureInterface
    {
        $storage = new class () implements ContextStorageInterface {
            private ?ExecutionScopeInterface $sentinel = null;

            public function current(): ExecutionScopeInterface
            {
                $this->sentinel ??= new FakeExecutionScope();

                return $this->sentinel;
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

        $poller = new class () implements PollerPortInterface {
            public function register(mixed $stream): StreamPollHandle
            {
                return new StreamPollHandle('stub');
            }

            public function release(StreamPollHandle $handle): void
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

        return new FakeRuntimeFixture($storage, 1, poller: $poller);
    }
}
