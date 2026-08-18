<?php

declare(strict_types=1);

namespace Tests\Support\Fixture;

use ItPack\RunIt\Contract\Domain\ExecutionArtifactInterface;
use ItPack\RunIt\Contract\Port\ArtifactPublisherInterface;
use RuntimeException;

/**
 * Artifact publisher that always throws, for exercising publication-failure handling.
 *
 * @see https://w3id.org/itpack/runit#Execution
 */
final class ThrowingArtifactPublisher implements ArtifactPublisherInterface
{
    public function publish(ExecutionArtifactInterface $artifact): void
    {
        throw new RuntimeException('publisher down');
    }
}
