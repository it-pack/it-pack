<?php

declare(strict_types=1);

namespace ItPack\AsyncIt\Conformance\Verifier;

use ItPack\AsyncIt\Conformance\Contract\ConformanceResultInterface;
use ItPack\AsyncIt\Conformance\Contract\VerifierInterface;
use ItPack\AsyncIt\Conformance\Fixture\AsyncRuntimeFixtureInterface;
use ItPack\AsyncIt\Conformance\Result\ConformanceResult;
use ItPack\AsyncIt\Conformance\Result\ConformanceViolation;
use ItPack\AsyncIt\Conformance\Result\ViolationSeverity;
use ItPack\AsyncIt\Contract\PollerPortInterface;
use ItPack\AsyncIt\Contract\Port\ViolationVocabulary;

/**
 * Conformance Assertion C.2 — Timer Handle Cleanup.
 *
 * Verifies that every timer registered by the scheduler (via awaitDelay, timeout,
 * or deadline) is released on or before the reactor exits, so no timer descriptors
 * leak across runs in a persistent worker.
 *
 * This is a failing scaffold: the full assertion requires timer-handle tracking and
 * cancellation-cascade propagation (M12–M14), which is not yet assembled. The scaffold
 * reports a {@see ViolationVocabulary::FIXTURE_UNSUPPORTED} finding so the
 * conformance gap is visible in CI until {@see \ItPack\AsyncIt\Contract\EngineSchedulerInterface::awaitDelay()}
 * is fully wired with the poller's handle registry and cancellation scope.
 *
 * @see https://w3id.org/itpack/asyncit#Timer
 * @see https://w3id.org/itpack/asyncit#StreamPollHandle
 * @see https://w3id.org/itpack/runit#Execution
 * @api
 */
final class TimerCleanupVerifier implements VerifierInterface
{
    public const string ASSERTION = 'C.2';

    public function port(): string
    {
        return PollerPortInterface::class;
    }

    public function verify(AsyncRuntimeFixtureInterface $fixture): ConformanceResultInterface
    {
        $result = new ConformanceResult($this->port());

        $result = $result->add(new ConformanceViolation(
            ViolationVocabulary::FIXTURE_UNSUPPORTED,
            'TimerCleanupVerifier: timer-handle cleanup not yet implemented (M12–M14). '
            . 'This assertion will pass once awaitDelay() deadline timers are tracked '
            . 'and released on cancellation/timeout.',
            ViolationSeverity::Warning,
        ));

        return $result;
    }
}
