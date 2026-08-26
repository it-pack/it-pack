<?php

declare(strict_types=1);

namespace ItPack\AsyncIt\Conformance\Contract;

/**
 * Framework-agnostic material a port verifier needs to exercise an AsyncIt port.
 *
 * A verifier depends only on the port type and this fixture interface, so a
 * third-party adapter author supplies fixtures from their own domain objects
 * while reusing AsyncIt's verifiers. Implementers decide how to build the port
 * under test; AsyncIt's bundled reference runtime provides the first-party
 * implementation.
 *
 * @see https://w3id.org/itpack/asyncit#ConformanceResult
 * @see https://w3id.org/itpack/runit#Execution
 */
interface PortFixtureInterface
{
    /**
     * Human-readable name of the port being exercised (e.g. "Scheduler").
     */
    public function portName(): string;
}
