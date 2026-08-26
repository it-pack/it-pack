<?php

declare(strict_types=1);

namespace ItPack\AsyncIt\Application\Publisher;

use ItPack\RunIt\Contract\Domain\ExecutionArtifactInterface;
use ItPack\RunIt\Contract\Port\ArtifactPublisherInterface;

/**
 * Reference artifact publisher — a fail-open no-op.
 *
 * It satisfies the RunIt {@see ArtifactPublisherInterface} so the reference
 * runtime can be exercised without an external telemetry sink. Phase 3 swaps
 * this for RunIt's first-party adapters (FailSafeArtifactPublisher,
 * recursion-guarded) behind the same contract.
 *
 * @see https://w3id.org/itpack/runit#Execution
 */
final class NullArtifactPublisher implements ArtifactPublisherInterface
{
    public function publish(ExecutionArtifactInterface $artifact): void
    {
    }
}
