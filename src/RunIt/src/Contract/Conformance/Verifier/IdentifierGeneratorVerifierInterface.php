<?php

declare(strict_types=1);

namespace ItPack\RunIt\Contract\Conformance\Verifier;

use ItPack\RunIt\Contract\Conformance\PortFixtureInterface;
use ItPack\RunIt\Contract\Conformance\Result\ConformanceResultInterface;
use ItPack\RunIt\Contract\Port\IdentifierGeneratorInterface;

/**
 * Contract for conformance checks of IdentifierGeneratorInterface.
 *
 * @see https://w3id.org/itpack/runit#executionId
 */
interface IdentifierGeneratorVerifierInterface
{
    public function port(): string;

    public function verify(
        IdentifierGeneratorInterface $generator,
        PortFixtureInterface $fixture
    ): ConformanceResultInterface;
}
