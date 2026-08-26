<?php

declare(strict_types=1);

namespace ItPack\AsyncIt\Conformance\Verifier;

use ItPack\AsyncIt\Conformance\Contract\VerifierInterface;
use ItPack\AsyncIt\Conformance\Fixture\AsyncRuntimeFixtureInterface;
use ItPack\AsyncIt\Conformance\Result\ConformanceResult;
use ItPack\AsyncIt\Contract\SchedulerInterface;

/**
 * The AsyncIt conformance suite.
 *
 * Composes every port verifier into a single runnable check. Phase 1 wires the
 * two AsyncIt-owned assertions — A (context isolation) and C (deterministic
 * cleanup). Reused RunIt verifiers (monotonic clock, context storage,
 * artifact publisher, execution engine) are added in later phases behind the
 * same {@see VerifierInterface} surface, so a single result reports the whole
 * runtime.
 *
 * @see https://w3id.org/itpack/asyncit#ConformanceResult
 * @see https://w3id.org/itpack/runit#Execution
 */
final readonly class AsyncRuntimeVerifier implements VerifierInterface
{
    /**
     * @param iterable<VerifierInterface> $verifiers
     */
    public function __construct(
        private ContextIsolationVerifier $contextIsolation,
        private DeterministicCleanupVerifier $cleanup,
    ) {
    }

    public function port(): string
    {
        return SchedulerInterface::class;
    }

    public function verify(AsyncRuntimeFixtureInterface $fixture): ConformanceResult
    {
        $result = new ConformanceResult($this->port());

        foreach ([$this->contextIsolation, $this->cleanup] as $verifier) {
            foreach ($verifier->verify($fixture) as $violation) {
                $result = $result->add($violation);
            }
        }

        return $result;
    }
}
