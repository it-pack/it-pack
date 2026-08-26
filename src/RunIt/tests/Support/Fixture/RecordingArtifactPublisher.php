<?php

declare(strict_types=1);

namespace Tests\Support\Fixture;

use ItPack\RunIt\Contract\Domain\ExecutionArtifactInterface;
use ItPack\RunIt\Contract\Port\ArtifactPublisherInterface;

/**
 * Publisher spy that records every published artifact in order.
 *
 * @see https://w3id.org/itpack/runit#Execution
 */
final class RecordingArtifactPublisher implements ArtifactPublisherInterface
{
    /** @var list<ExecutionArtifactInterface> */
    public array $published = [];

    public function publish(ExecutionArtifactInterface $artifact): void
    {
        $this->published[] = $artifact;
    }

    /**
     * @return list<ExecutionArtifactInterface>
     */
    public function published(): array
    {
        return $this->published;
    }
}
