<?php

declare(strict_types=1);

namespace ItPack\RunIt\Application\Propagation;

use Closure;
use ItPack\RunIt\Contract\Domain\ExecutionArtifactInterface;
use ItPack\RunIt\Contract\Port\ArtifactPublisherInterface;
use Throwable;

/**
 * Artifact publisher that never lets a publication failure escape the caller.
 *
 * Recorders publish observations, occurrences, measurements, and snapshots directly through
 * the publisher. When the transport fails, that failure must not replace the operation's
 * business result or the original business exception. This decorator swallows the publication
 * error and forwards it to an optional health callback, mirroring the fail-open behavior the
 * execution runner guarantees for execution and snapshot artifacts.
 *
 * @see https://w3id.org/itpack/runit#Execution
 */
final class SafeArtifactPublisher implements ArtifactPublisherInterface
{
    public function __construct(
        private ArtifactPublisherInterface $inner,
        private ?Closure $onFailure = null,
    ) {
    }

    public function publish(ExecutionArtifactInterface $artifact): void
    {
        try {
            $this->inner->publish($artifact);
        } catch (Throwable $publicationError) {
            if ($this->onFailure instanceof Closure) {
                ($this->onFailure)($publicationError);
            }
        }
    }
}
