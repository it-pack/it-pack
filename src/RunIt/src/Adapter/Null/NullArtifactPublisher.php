<?php

declare(strict_types=1);

namespace ItPack\RunIt\Adapter\Null;

use ItPack\RunIt\Contract\Domain\ExecutionArtifactInterface;
use ItPack\RunIt\Contract\Port\ArtifactPublisherInterface;

/**
 * Artifact publisher that discards everything it is given.
 *
 * Useful as a disabled-instrumentation default and as a test double where emission must not be
 * observed. It never throws and retains nothing.
 *
 * @see https://w3id.org/itpack/runit#Execution
 */
final class NullArtifactPublisher implements ArtifactPublisherInterface
{
    public function publish(ExecutionArtifactInterface $artifact): void
    {
    }
}
