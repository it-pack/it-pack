<?php

declare(strict_types=1);

namespace ItPack\RunIt\Conformance\Verifier;

use ItPack\RunIt\Conformance\Contract\PortFixtureInterface;
use ItPack\RunIt\Conformance\Contract\VerifierInterface;
use ItPack\RunIt\Conformance\Result\ConformanceResult;
use ItPack\RunIt\Conformance\Result\ConformanceViolation;
use ItPack\RunIt\Contract\Port\SnapshotStoreInterface;
use Throwable;

/**
 * Conformance checks for {@see SnapshotStoreInterface}.
 *
 * The contract requires that {@see SnapshotStoreInterface::store()} is safe to call
 * repeatedly for the same snapshot, never throws for a valid snapshot, and never
 * mutates the snapshot it is given.
 *
 * @see https://w3id.org/itpack/runit#Snapshot
 */
final class SnapshotStoreVerifier implements VerifierInterface
{
    public function port(): string
    {
        return SnapshotStoreInterface::class;
    }

    public function verify(SnapshotStoreInterface $store, PortFixtureInterface $fixture): ConformanceResult
    {
        $result = new ConformanceResult($this->port());

        $snapshot = $fixture->snapshot();
        $before = $snapshot->normalize();

        try {
            $store->store($snapshot);
            $store->store($snapshot);
        } catch (Throwable $e) {
            return $result->add(new ConformanceViolation('store.throws', sprintf('store() threw: %s', $e->getMessage())));
        }

        if ($snapshot->normalize() !== $before) {
            $result = $result->add(new ConformanceViolation('store.mutates-snapshot', 'store() mutated the supplied snapshot'));
        }

        $patched = $fixture->snapshotPatcher()($snapshot);
        try {
            $store->store($patched);
        } catch (Throwable $e) {
            $result = $result->add(new ConformanceViolation('store.patched.throws', sprintf('store() threw for a re-stored snapshot: %s', $e->getMessage())));
        }

        return $result;
    }
}
