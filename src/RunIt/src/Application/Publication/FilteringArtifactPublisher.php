<?php

declare(strict_types=1);

namespace ItPack\RunIt\Application\Publication;

use ItPack\RunIt\Behavior\Application\Publication\DelegatesPublisherLifecycleBehavior;
use ItPack\RunIt\Contract\Application\Publication\ArtifactFilterInterface;
use ItPack\RunIt\Contract\Domain\ExecutionArtifactInterface;
use ItPack\RunIt\Contract\Port\ArtifactPublisherInterface;
use ItPack\RunIt\Contract\Port\CloseablePublisherInterface;
use ItPack\RunIt\Contract\Port\FlushablePublisherInterface;

/**
 * Artifact publisher that applies a filter before publishing.
 *
 * Filtering runs before any expensive adapter mapping, so the pipeline drops
 * uninteresting artifacts cheaply. Artifacts that the filter rejects are never
 * offered to the inner publisher and are never retained.
 *
 * @see https://w3id.org/itpack/runit#Execution
 */
final readonly class FilteringArtifactPublisher implements
    ArtifactPublisherInterface,
    FlushablePublisherInterface,
    CloseablePublisherInterface
{
    use DelegatesPublisherLifecycleBehavior;

    public function __construct(
        private ArtifactFilterInterface    $filter,
        private ArtifactPublisherInterface $inner,
    ) {
    }

    public function publish(ExecutionArtifactInterface $artifact): void
    {
        if ($this->filter->accepts($artifact)) {
            $this->inner->publish($artifact);
        }
    }
}
