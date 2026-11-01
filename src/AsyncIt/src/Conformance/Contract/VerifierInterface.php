<?php

declare(strict_types=1);

namespace ItPack\AsyncIt\Conformance\Contract;

use ItPack\AsyncIt\Conformance\Fixture\AsyncRuntimeFixtureInterface;

/**
 * Common surface for a port conformance verifier.
 *
 * Each verifier checks one AsyncIt port contract (scheduler, poller, cancellation
 * scope, capability descriptor, runtime discovery). The {@see self::port()} method
 * returns the fully-qualified port interface the verifier targets, which lets a
 * conformance harness register and report verifiers by the contract they exercise.
 *
 * @see https://w3id.org/itpack/asyncit#ConformanceResult
 * @see https://w3id.org/itpack/runit#Execution
 */
interface VerifierInterface
{
    /**
     * Returns the fully-qualified port interface this verifier targets.
     */
    public function port(): string;

    /**
     * Runs the conformance check against the supplied fixture and returns the
     * collected result.
     */
    public function verify(AsyncRuntimeFixtureInterface $fixture): ConformanceResultInterface;
}
