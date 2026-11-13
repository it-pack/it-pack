<?php

declare(strict_types=1);

namespace ItPack\RunIt\Application\Publication;

use ItPack\RunIt\Behavior\Application\Publication\DelegatesPublisherLifecycleBehavior;
use ItPack\RunIt\Contract\Domain\ExecutionArtifactInterface;
use ItPack\RunIt\Contract\Port\ArtifactPublisherInterface;
use ItPack\RunIt\Contract\Port\CloseablePublisherInterface;
use ItPack\RunIt\Contract\Port\FlushablePublisherInterface;
use RuntimeException;
use Throwable;

/** @internal Verifies issuance before invoking any configured publication stack. */
final readonly class ProvenanceArtifactPublisher implements
    ArtifactPublisherInterface,
    FlushablePublisherInterface,
    CloseablePublisherInterface
{
    use DelegatesPublisherLifecycleBehavior;

    public function __construct(
        private ProvenanceAuthority $authority,
        private ArtifactPublisherInterface $inner,
    ) {
    }

    public function publish(ExecutionArtifactInterface $artifact): void
    {
        try {
            $verified = $this->authority->exportIdForArtifact($artifact) !== null;
        } catch (Throwable) {
            $verified = false;
        }

        if (!$verified) {
            $this->authority->reportUnverified(new RuntimeException('Artifact provenance verification failed.'));
            return;
        }

        $this->inner->publish($artifact);
    }
}
