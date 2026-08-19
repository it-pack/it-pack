<?php

declare(strict_types=1);

namespace ItPack\RunIt\Application\Publication;

use ItPack\RunIt\Contract\Application\Publication\RedactorInterface;
use ItPack\RunIt\Contract\Domain\ExecutionArtifactInterface;
use ItPack\RunIt\Contract\Port\ArtifactPublisherInterface;
use ItPack\RunIt\Contract\Port\CloseablePublisherInterface;
use ItPack\RunIt\Contract\Port\FlushablePublisherInterface;

/**
 * Artifact publisher that redacts before delegating to the inner publisher.
 *
 * Redaction happens before any external publication, so secrets and restricted
 * values never reach an adapter. The original immutable artifact is never mutated:
 * the redactor returns either the same instance (nothing matched) or a redacted
 * projection, and only that result is published.
 *
 * @see https://w3id.org/itpack/runit#Execution
 */
final class RedactingArtifactPublisher implements ArtifactPublisherInterface, FlushablePublisherInterface, CloseablePublisherInterface
{
    public function __construct(
        private readonly RedactorInterface $redactor,
        private readonly ArtifactPublisherInterface $inner,
    ) {
    }

    public function publish(ExecutionArtifactInterface $artifact): void
    {
        $this->inner->publish($this->redactor->redact($artifact));
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
