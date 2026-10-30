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
 * Composes port verifiers into a single runnable check. Phase 1 wires the
 * three AsyncIt-owned assertions — A (context isolation), C (deterministic
 * cleanup), and the scaffolds A.2 (group cancellation isolation) and C.2
 * (timer handle cleanup) — both currently reporting a fixture-unsupported
 * finding until TaskGroup (M12) and timer-handle tracking (M14) land. Reused
 * RunIt verifiers (monotonic clock, context storage, artifact publisher,
 * execution engine) are added in later phases behind the same
 * {@see VerifierInterface} surface, so a single result reports the whole
 * runtime.
 *
 * @see https://w3id.org/itpack/asyncit#ConformanceResult
 * @see https://w3id.org/itpack/runit#Execution
 */
final readonly class AsyncRuntimeVerifier implements VerifierInterface
{
    public function __construct(
        private ContextIsolationVerifier $contextIsolation,
        private DeterministicCleanupVerifier $cleanup,
        private GroupCancellationIsolationVerifier $groupCancellation = new GroupCancellationIsolationVerifier(),
        private TimerCleanupVerifier $timerCleanup = new TimerCleanupVerifier(),
        private TraceParentageVerifier $traceParentage = new TraceParentageVerifier(),
        private GracefulShutdownVerifier $gracefulShutdown = new GracefulShutdownVerifier(),
    ) {
    }

    public function port(): string
    {
        return SchedulerInterface::class;
    }

    public function verify(AsyncRuntimeFixtureInterface $fixture): ConformanceResult
    {
        $result = new ConformanceResult($this->port());

        $verifiers = [
            $this->contextIsolation,
            $this->cleanup,
            $this->groupCancellation,
            $this->timerCleanup,
            $this->traceParentage,
            $this->gracefulShutdown,
        ];

        foreach ($verifiers as $verifier) {
            foreach ($verifier->verify($fixture) as $violation) {
                $result = $result->add($violation);
            }
        }

        return $result;
    }
}
