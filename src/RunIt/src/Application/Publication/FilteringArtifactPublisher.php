<?php

declare(strict_types=1);

namespace ItPack\RunIt\Application\Publication;

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
final readonly class FilteringArtifactPublisher implements ArtifactPublisherInterface, FlushablePublisherInterface, CloseablePublisherInterface
{
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

    public function flush(): void
    {
        if ($this->inner instanceof FlushablePublisherInterface) {
            $this->inner->flush();
        }
    }

    public function close(): void
    {
        if ($this->inner instanceof CloseablePublisherInterface) {
            $this->inner->close();
        }
    }
}
