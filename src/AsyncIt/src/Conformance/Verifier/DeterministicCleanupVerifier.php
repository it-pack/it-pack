<?php

declare(strict_types=1);

namespace ItPack\AsyncIt\Conformance\Verifier;

use ItPack\AsyncIt\Conformance\Contract\VerifierInterface;
use ItPack\AsyncIt\Conformance\Fixture\AsyncRuntimeFixtureInterface;
use ItPack\AsyncIt\Conformance\Result\ConformanceResult;
use ItPack\AsyncIt\Conformance\Result\ConformanceViolation;
use ItPack\AsyncIt\Contract\PollerPortInterface;
use ItPack\AsyncIt\Contract\Port\ViolationVocabulary;

/**
 * Conformance Assertion C — Deterministic Cleanup.
 *
 * Proves the runtime releases every {@see \ItPack\AsyncIt\Contract\Port\StreamPollHandle}
 * it registered: after a register/await/release cycle the runtime must hold zero
 * unreleased handles. A single leaked descriptor is a conformance failure.
 *
 * @see https://w3id.org/itpack/asyncit#StreamPollHandle
 * @see https://w3id.org/itpack/asyncit#Poller
  * @api

  */
final class DeterministicCleanupVerifier implements VerifierInterface
{
    public const ASSERTION = 'C';

    public function port(): string
    {
        return PollerPortInterface::class;
    }

    public function verify(AsyncRuntimeFixtureInterface $fixture): ConformanceResult
    {
        $result = new ConformanceResult($this->port());

        $poller = $fixture->poller();
        $stream = fopen('php://memory', 'r+');

        $handle = $poller->register($stream);
        $poller->await(0);
        $poller->release($handle);

        if ($fixture->activePollHandleCount() !== 0) {
            $result = $result->add(new ConformanceViolation(
                ViolationVocabulary::HANDLE_LEAK,
                'A registered poll handle was not released on cleanup; a descriptor leaked.',
            ));
        }

        return $result;
    }
}
