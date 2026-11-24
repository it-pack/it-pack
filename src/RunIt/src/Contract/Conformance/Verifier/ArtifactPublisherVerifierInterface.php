<?php

declare(strict_types=1);

namespace ItPack\RunIt\Contract\Conformance\Verifier;

use ItPack\RunIt\Contract\Conformance\PortFixtureInterface;
use ItPack\RunIt\Contract\Conformance\Result\ConformanceResultInterface;
use ItPack\RunIt\Contract\Port\ArtifactPublisherInterface;

/**
 * Contract for conformance checks of ArtifactPublisherInterface.
 *
 * @see https://w3id.org/itpack/runit#Execution
 */
interface ArtifactPublisherVerifierInterface
{
    public function port(): string;

    public function verify(
        ArtifactPublisherInterface $publisher,
        PortFixtureInterface $fixture
    ): ConformanceResultInterface;
}
