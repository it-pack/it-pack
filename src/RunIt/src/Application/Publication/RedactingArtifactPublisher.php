<?php

declare(strict_types=1);

namespace ItPack\RunIt\Application\Publication;

use InvalidArgumentException;
use ItPack\RunIt\Behavior\Application\Publication\DelegatesPublisherLifecycleBehavior;
use ItPack\RunIt\Contract\Application\Publication\InternalHealthReporterInterface;
use ItPack\RunIt\Contract\Application\Publication\RedactorInterface;
use ItPack\RunIt\Contract\Domain\ExecutionArtifactInterface;
use ItPack\RunIt\Contract\Domain\ObservationInterface;
use ItPack\RunIt\Contract\Domain\RedactedArtifactInterface;
use ItPack\RunIt\Contract\Port\ArtifactPublisherInterface;
use ItPack\RunIt\Contract\Port\CloseablePublisherInterface;
use ItPack\RunIt\Contract\Port\FlushablePublisherInterface;
use ItPack\RunIt\Domain\Shared\ArtifactField;
use Throwable;

/**
 * Artifact publisher that redacts before delegating to the inner publisher.
 *
 * Redaction happens before any external publication, so secrets and restricted
 * values never reach an adapter. The original immutable artifact is never mutated:
 * the redactor returns either the same instance (nothing matched) or a redacted
 * projection, and only that result is published.
 *
 * When `dropRedactedObservations` is enabled and redaction produces a
 * {@see RedactedArtifact} from an observation, the redacted observation is not
 * published: a constant, payload-free notice is reported
 * through the injected internal health reporter instead. This keeps redacted
 * observations out of every sink while remaining observable through the health
 * channel. A health reporter is required when dropping is enabled; otherwise the
 * constructor throws.
 *
 * @see https://w3id.org/itpack/runit#Execution
 */
final class RedactingArtifactPublisher implements
    ArtifactPublisherInterface,
    FlushablePublisherInterface,
    CloseablePublisherInterface
{
    use DelegatesPublisherLifecycleBehavior;

    private bool $publishingHealthReport = false;

    public function __construct(
        private readonly RedactorInterface          $redactor,
        private readonly ArtifactPublisherInterface $inner,
        private readonly ?InternalHealthReporterInterface $health = null,
        private readonly bool                       $dropRedactedObservations = false,
    ) {
        if ($dropRedactedObservations && !$health instanceof InternalHealthReporterInterface) {
            throw new InvalidArgumentException('dropRedactedObservations requires a health reporter.');
        }
    }

    public function publish(ExecutionArtifactInterface $artifact): void
    {
        $redacted = $this->redactor->redact($artifact);

        if ($this->dropRedactedObservations
            && $redacted instanceof RedactedArtifactInterface
            && $artifact instanceof ObservationInterface
        ) {
            if ($this->publishingHealthReport) {
                // Re-entry guard: health reporting must not re-enter the same
                // publication path. Drop the notice fail-open.
                return;
            }

            try {
                $this->publishingHealthReport = true;
                $this->health?->report(
                    BoundDropException::forReason('drop-redacted-observation'),
                    new RedactedArtifact([
                        ArtifactField::ID => 'redacted',
                        ArtifactField::TYPE => 'runit.artifact.redacted',
                    ]),
                );
            } catch (Throwable) {
                // Contain health reporter failures so the drop path stays fail-open:
                // a throwing reporter must not change the business result or let a
                // publication exception escape to enclosing failure recovery, which
                // could receive the original observation instead of this identity-only notice.
            } finally {
                $this->publishingHealthReport = false;
            }

            return;
        }

        $this->inner->publish($redacted);
    }

}
