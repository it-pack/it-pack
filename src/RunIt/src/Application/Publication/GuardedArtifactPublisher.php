<?php

declare(strict_types=1);

namespace ItPack\RunIt\Application\Publication;

use ItPack\RunIt\Contract\Application\Publication\InternalHealthReporterInterface;
use ItPack\RunIt\Contract\Application\Publication\PublicationGuardInterface;
use ItPack\RunIt\Contract\Domain\ExecutionArtifactInterface;
use ItPack\RunIt\Contract\Port\ArtifactPublisherInterface;
use ItPack\RunIt\Contract\Port\CloseablePublisherInterface;
use ItPack\RunIt\Contract\Port\FlushablePublisherInterface;

/**
 * Artifact publisher that prevents recursive publication on its execution path.
 *
 * On {@see publish()} the decorator enters the guard, publishes through the inner
 * publisher, and leaves the guard in a `finally`. If publication is already active
 * (an exporter observing its own export, or a health reporter feeding the same
 * pipeline), the decorator detects the recursion, routes the artifact to the
 * internal health reporter, and returns without publishing again.
 *
 * Flush and close are delegated to the inner publisher so a buffered or composite
 * sink behind the guard still drains.
 *
 * @see https://w3id.org/itpack/runit#Execution
 */
final readonly class GuardedArtifactPublisher implements ArtifactPublisherInterface, FlushablePublisherInterface, CloseablePublisherInterface
{
    public function __construct(
        private PublicationGuardInterface        $guard,
        private ArtifactPublisherInterface       $inner,
        private ?InternalHealthReporterInterface $health = null,
    ) {
    }

    public function publish(ExecutionArtifactInterface $artifact): void
    {
        if ($this->guard->isActive()) {
            $this->health?->report(new RecursivePublicationException('Recursive publication suppressed.'), $artifact);

            return;
        }

        $token = $this->guard->enter();

        try {
            $this->inner->publish($artifact);
        } finally {
            $this->guard->leave($token);
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
