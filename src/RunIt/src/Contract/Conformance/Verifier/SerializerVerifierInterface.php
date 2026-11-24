<?php

declare(strict_types=1);

namespace ItPack\RunIt\Contract\Conformance\Verifier;

use ItPack\RunIt\Contract\Conformance\PortFixtureInterface;
use ItPack\RunIt\Contract\Conformance\Result\ConformanceResultInterface;
use ItPack\RunIt\Contract\Port\SerializerInterface;

/**
 * Contract for conformance checks of SerializerInterface.
 *
 * @see https://w3id.org/itpack/runit#Snapshot
 */
interface SerializerVerifierInterface
{
    public function port(): string;

    public function verify(SerializerInterface $serializer, PortFixtureInterface $fixture): ConformanceResultInterface;
}
