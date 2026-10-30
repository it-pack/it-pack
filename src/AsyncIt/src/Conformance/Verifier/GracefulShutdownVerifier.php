<?php

declare(strict_types=1);

namespace ItPack\AsyncIt\Conformance\Verifier;

use ItPack\AsyncIt\Conformance\Contract\VerifierInterface;
use ItPack\AsyncIt\Conformance\Fixture\AsyncRuntimeFixtureInterface;
use ItPack\AsyncIt\Conformance\Result\ConformanceResult;
use ItPack\AsyncIt\Conformance\Result\ConformanceViolation;
use ItPack\AsyncIt\Contract\CancellationScopeInterface;
use ItPack\AsyncIt\Contract\EngineSchedulerInterface;
use ItPack\AsyncIt\Contract\Port\ViolationVocabulary;
use LogicException;

/**
 * Conformance Assertion F — Graceful Shutdown.
 *
 * Proves that on shutdown, the scheduler releases every poll handle and drains
 * in-flight tasks within the deadline. The verifier cancels a root
 * {@see CancellationScopeInterface} (cascading to children), then drains the
 * reactor until idle, and asserts zero active handles remain.
 *
 * A leaked descriptor or a task left running is a conformance failure.
 *
 * @see https://w3id.org/itpack/asyncit#CancellationScope
 * @see https://w3id.org/itpack/asyncit#StreamPollHandle
 * @see https://w3id.org/itpack/asyncit#Scheduler
 */
final class GracefulShutdownVerifier implements VerifierInterface
{
    public const string ASSERTION = 'F';

    public function port(): string
    {
        return EngineSchedulerInterface::class;
    }

    public function verify(AsyncRuntimeFixtureInterface $fixture): ConformanceResult
    {
        $result = new ConformanceResult($this->port());

        try {
            $scheduler = $fixture->scheduler();
        } catch (LogicException) {
            $result = $result->add(new ConformanceViolation(
                ViolationVocabulary::FIXTURE_UNSUPPORTED,
                'Fixture scheduler not configured; cannot run shutdown assertion.',
            ));

            return $result;
        }

        if (!($scheduler instanceof EngineSchedulerInterface)) {
            $result = $result->add(new ConformanceViolation(
                ViolationVocabulary::FIXTURE_UNSUPPORTED,
                'Fixture scheduler does not implement EngineSchedulerInterface; cannot run shutdown assertion.',
            ));

            return $result;
        }

        $scope = $fixture->cancellationScope();
        $scope->cancel();

        $scheduler->run();

        if ($fixture->activePollHandleCount() !== 0) {
            $result = $result->add(new ConformanceViolation(
                ViolationVocabulary::HANDLE_LEAK,
                'Poll handles were not released after graceful shutdown; a descriptor leaked.',
            ));
        }

        return $result;
    }
}
