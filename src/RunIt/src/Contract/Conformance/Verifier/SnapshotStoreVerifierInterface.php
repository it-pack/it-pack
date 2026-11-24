<?php

declare(strict_types=1);

namespace ItPack\RunIt\Contract\Conformance\Verifier;

use ItPack\RunIt\Contract\Conformance\PortFixtureInterface;
use ItPack\RunIt\Contract\Conformance\Result\ConformanceResultInterface;
use ItPack\RunIt\Contract\Port\SnapshotStoreInterface;

/**
 * Contract for conformance checks of SnapshotStoreInterface.
 *
 * @see https://w3id.org/itpack/runit#Snapshot
 */
interface SnapshotStoreVerifierInterface
{
    public function port(): string;

    public function verify(SnapshotStoreInterface $store, PortFixtureInterface $fixture): ConformanceResultInterface;
}
