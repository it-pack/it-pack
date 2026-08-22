<?php

declare(strict_types=1);

namespace ItPack\RunIt\Conformance\Contract;

/**
 * Common surface for a port conformance verifier.
 *
 * Each verifier checks one RunIt port contract (publisher, context storage, execution
 * engine, identifier generator, clock, redactor, sampling policy, serializer, snapshot
 * store). The {@see self::port()} method returns the fully-qualified port interface the
 * verifier targets, which lets a conformance harness register and report verifiers by
 * the contract they exercise.
 *
 * @see https://w3id.org/itpack/runit#Execution
 */
interface VerifierInterface
{
    /**
     * Returns the fully-qualified port interface this verifier targets.
     */
    public function port(): string;
}
