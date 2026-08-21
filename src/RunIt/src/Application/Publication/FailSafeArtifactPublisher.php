<?php

declare(strict_types=1);

namespace ItPack\RunIt\Application\Publication;

use ItPack\RunIt\Contract\Application\Publication\InternalHealthReporterInterface;
use ItPack\RunIt\Contract\Application\Publication\PublicationFailurePolicyInterface;
use ItPack\RunIt\Contract\Domain\ExecutionArtifactInterface;
use ItPack\RunIt\Contract\Port\ArtifactPublisherInterface;
use ItPack\RunIt\Contract\Port\CloseablePublisherInterface;
use ItPack\RunIt\Contract\Port\FlushablePublisherInterface;

/**
 * Artifact publisher that applies a failure policy when the inner publisher throws.
 *
 * The decorator catches the original adapter exception and hands it to the policy. A
 * fail-open policy swallows it; a fail-closed policy rethrows it (the original adapter
 * error, never a business exception); a collect-and-continue policy records it; a
 * report-to-fallback policy forwards the artifact elsewhere. Whatever the policy does,
 * the original business exception and business result are preserved.
 *
 * @see https://w3id.org/itpack/runit#Execution
 */
final readonly class FailSafeArtifactPublisher implements ArtifactPublisherInterface, FlushablePublisherInterface, CloseablePublisherInterface
{
    public function __construct(
        private ArtifactPublisherInterface        $inner,
        private PublicationFailurePolicyInterface $policy,
        private ?InternalHealthReporterInterface  $health = null,
    ) {
    }

    public function publish(ExecutionArtifactInterface $artifact): void
    {
        try {
            $this->inner->publish($artifact);
        } catch (\Throwable $publicationError) {
            $this->policy->handle($publicationError, $artifact);
            $this->health?->report($publicationError, $artifact);
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
