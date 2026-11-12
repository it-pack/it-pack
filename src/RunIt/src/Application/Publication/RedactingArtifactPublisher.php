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
use ItPack\RunIt\Domain\Shared\StringIdentifierValidator;

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
 * published: a bounded, payload-free notice (carrying only identity) is reported
 * through the injected internal health reporter instead. This keeps redacted
 * observations out of every sink while remaining observable through the health
 * channel. A health reporter is required when dropping is enabled; otherwise the
 * constructor throws.
 *
 * @see https://w3id.org/itpack/runit#Execution
 */
final readonly class RedactingArtifactPublisher implements
    ArtifactPublisherInterface,
    FlushablePublisherInterface,
    CloseablePublisherInterface
{
    use DelegatesPublisherLifecycleBehavior;

    public function __construct(
        private RedactorInterface          $redactor,
        private ArtifactPublisherInterface $inner,
        private ?InternalHealthReporterInterface $health = null,
        private bool                       $dropRedactedObservations = false,
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
            $redactedNormalized = $redacted->normalize();
            $id = is_string($redactedNormalized[ArtifactField::ID] ?? null)
                ? $redactedNormalized[ArtifactField::ID]
                : 'redacted';
            if (strlen($id) > StringIdentifierValidator::MAX_LENGTH) {
                $id = substr($id, 0, StringIdentifierValidator::MAX_LENGTH - 3) . '...';
            }

            $this->health?->report(
                BoundDropException::forReason('drop-redacted-observation'),
                new RedactedArtifact([
                    ArtifactField::ID => $id,
                    ArtifactField::TYPE => 'runit.artifact.redacted',
                ]),
            );

            return;
        }

        $this->inner->publish($redacted);
    }

}
