<?php

declare(strict_types=1);

namespace Tests\Unit\Conformance\Verifier;

use ItPack\AsyncIt\Conformance\Fixture\AsyncRuntimeFixtureInterface;
use ItPack\AsyncIt\Conformance\Verifier\AsyncRuntimeVerifier;
use ItPack\AsyncIt\Conformance\Verifier\ContextIsolationVerifier;
use ItPack\AsyncIt\Conformance\Verifier\DeterministicCleanupVerifier;
use ItPack\AsyncIt\Contract\Port\StreamPollHandle;
use ItPack\AsyncIt\Contract\PollerPortInterface;
use ItPack\RunIt\Contract\Application\ExecutionScopeInterface;
use ItPack\RunIt\Contract\Port\ContextStorageInterface;
use ItPack\RunIt\Contract\Port\ContextTokenInterface;
use Tests\Unit\Conformance\Fixture\FakeExecutionScope;
use Tests\Unit\Conformance\Fixture\FakeRuntimeFixture;
use PHPUnit\Framework\TestCase;

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
        self::markTestIncomplete(
            'The reference runtime fixture is implemented in Phase 2; until then '
            . 'no conformant AsyncRuntime exists to drive the suite green.',
        );
    }

    private function doublyBrokenFixture(): AsyncRuntimeFixtureInterface
    {
        $storage = new class implements ContextStorageInterface {
            private ?ExecutionScopeInterface $sentinel = null;

            public function current(): ?ExecutionScopeInterface
            {
                if ($this->sentinel === null) {
                    $this->sentinel = new FakeExecutionScope('sentinel');
                }

                return $this->sentinel;
            }

            public function attach(ExecutionScopeInterface $scope): ContextTokenInterface
            {
                return new class implements ContextTokenInterface {
                };
            }

            public function detach(ContextTokenInterface $token): void
            {
            }
        };

        $poller = new class implements PollerPortInterface {
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
        };

        return new FakeRuntimeFixture($storage, 1, poller: $poller);
    }
}
