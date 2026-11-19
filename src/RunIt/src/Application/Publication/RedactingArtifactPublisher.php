<?php

declare(strict_types=1);

namespace ItPack\RunIt\Application\Publication;

use InvalidArgumentException;
use ItPack\RunIt\Application\Factory\RedactedRedactedArtifactFactory;
use ItPack\RunIt\Behavior\Application\Publication\DelegatesPublisherLifecycleBehavior;
use ItPack\RunIt\Contract\Application\Publication\InternalHealthReporterInterface;
use ItPack\RunIt\Contract\Application\Publication\RedactedArtifactFactoryInterface;
use ItPack\RunIt\Contract\Application\Publication\RedactorInterface;
use ItPack\RunIt\Contract\Domain\ActivityInterface;
use ItPack\RunIt\Contract\Domain\ArtifactType;
use ItPack\RunIt\Contract\Domain\ExecutionArtifactInterface;
use ItPack\RunIt\Contract\Domain\ExecutionInterface;
use ItPack\RunIt\Contract\Domain\MeasurementInterface;
use ItPack\RunIt\Contract\Domain\ObservationInterface;
use ItPack\RunIt\Contract\Domain\OccurrenceInterface;
use ItPack\RunIt\Contract\Domain\RedactedArtifactInterface;
use ItPack\RunIt\Contract\Domain\SnapshotInterface;
use ItPack\RunIt\Contract\Port\ArtifactPublisherInterface;
use ItPack\RunIt\Contract\Port\CloseablePublisherInterface;
use ItPack\RunIt\Contract\Port\FlushablePublisherInterface;
use ItPack\RunIt\Domain\Shared\ArtifactField;
use ItPack\RunIt\Domain\Snapshot\SnapshotClassification;
use RuntimeException;
use Throwable;

/**
 * Artifact publisher that redacts before delegating to the inner publisher.
 *
 * The configured redactor is applied before delegation. It may return the same
 * artifact when nothing matches, so this decorator alone does not guarantee that
 * unclassified values are withheld from downstream publishers.
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

    private readonly RedactedArtifactFactoryInterface $redactedFactory;

    public function __construct(
        private readonly ?RedactorInterface         $redactor,
        private readonly ArtifactPublisherInterface $inner,
        private readonly ?InternalHealthReporterInterface $health = null,
        private readonly bool                       $dropRedactedObservations = false,
        private readonly ?ProvenanceAuthority       $authority = null,
        ?RedactedArtifactFactoryInterface $redactedFactory = null,
    ) {
        if ($dropRedactedObservations && !$health instanceof InternalHealthReporterInterface) {
            throw new InvalidArgumentException('dropRedactedObservations requires a health reporter.');
        }

        $this->redactedFactory =
            $redactedFactory?->withArtifactType(ArtifactType::UNKNOWN)
            ?? new RedactedRedactedArtifactFactory(ArtifactType::UNKNOWN);
    }

    public function publish(ExecutionArtifactInterface $artifact): void
    {
        $wasRedacted = false;
        $redacted = $this->authority instanceof ProvenanceAuthority
            ? ($this->verifiedRedaction($artifact, $this->authority, $wasRedacted))
            : $this->redactor?->redact($artifact) ?? $artifact;
        if (!$redacted instanceof ExecutionArtifactInterface) {
            return;
        }

        if ($this->dropRedactedObservations
            && ($this->authority instanceof ProvenanceAuthority
                ? $wasRedacted : $redacted instanceof RedactedArtifactInterface)
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
                    $this->redactedFactory->create([
                        ArtifactField::ID => 'redacted',
                        ArtifactField::TYPE => 'runit.artifact.redacted',
                    ]),
                );
            } catch (Throwable) {
                // Contain health reporter failures so the drop path stays fail-open.
            } finally {
                $this->publishingHealthReport = false;
            }

            return;
        }

        $this->inner->publish($redacted);
    }

    private function verifiedRedaction(
        ExecutionArtifactInterface $artifact,
        ProvenanceAuthority $authority,
        bool &$wasRedacted,
    ): ?ExecutionArtifactInterface {
        try {
            if ($authority->exportIdForArtifact($artifact) === null) {
                throw new RuntimeException();
            }

            $original = $artifact->normalize();
            $kind = $this->resolveArtifactKind($artifact);

            $redacted = $this->redactor?->redact($artifact) ?? $artifact;
            $wasRedacted = $redacted !== $artifact && $redacted instanceof RedactedArtifactInterface;
            $normalized = $redacted->normalize();
            $fields = array_flip([
                ArtifactField::ID, ArtifactField::EXECUTION_ID, ArtifactField::ACTIVITY_ID,
                ArtifactField::OBSERVATION_ID, ArtifactField::PARENT_EXECUTION_ID,
                ArtifactField::LINKED_EXECUTION_IDS, ArtifactField::PARENT_ACTIVITY_ID,
                ArtifactField::PREDECESSOR_ID,
            ]);

            if (!$this->redactionStillVerified(
                $authority,
                $artifact,
                $original,
                $normalized,
                $fields,
                $kind,
                $redacted,
            )) {
                throw new RuntimeException();
            }

            $exportFields = $authority->exportIdentityFields($artifact);
            if ($exportFields === null) {
                throw new RuntimeException();
            }

            if ($artifact instanceof SnapshotInterface && !$this->validSnapshotStructure($artifact, $normalized)) {
                throw new RuntimeException();
            }

            return $this->buildExportProjection(
                $normalized,
                $fields,
                $exportFields,
                $kind,
                $redacted instanceof RedactedArtifactInterface,
                $artifact instanceof SnapshotInterface,
            );
        } catch (Throwable) {
            $authority->reportUnverified(new RuntimeException('Redaction provenance verification failed.'));
            return null;
        }
    }

    private function resolveArtifactKind(ExecutionArtifactInterface $artifact): string
    {
        return match (true) {
            $artifact instanceof ObservationInterface => 'observation',
            $artifact instanceof ActivityInterface => 'activity',
            $artifact instanceof MeasurementInterface => 'measurement',
            $artifact instanceof OccurrenceInterface => 'occurrence',
            $artifact instanceof ExecutionInterface => 'execution',
            $artifact instanceof SnapshotInterface => 'snapshot',
            default => '',
        };
    }

    /**
     * @param array<string, mixed> $original
     * @param array<string, mixed> $normalized
     * @param array<string, mixed> $fields
     */
    private function redactionStillVerified(
        ProvenanceAuthority $authority,
        ExecutionArtifactInterface $artifact,
        array $original,
        array $normalized,
        array $fields,
        string $kind,
        ExecutionArtifactInterface $redacted,
    ): bool {
        return $kind !== ''
            && $this->stillVerified($authority, $artifact)
            && array_intersect_key($original, $fields) === array_intersect_key($normalized, $fields)
            && (!$redacted instanceof RedactedArtifactInterface || $redacted->artifactType() === $kind)
            && ($kind !== 'snapshot' || ($original[ArtifactField::CLASSIFICATION] ?? null)
                === ($normalized[ArtifactField::CLASSIFICATION] ?? null));
    }

    private function stillVerified(ProvenanceAuthority $authority, ExecutionArtifactInterface $artifact): bool
    {
        return $authority->exportIdForArtifact($artifact) !== null;
    }

    /** @param array<string, mixed> $normalized */
    private function validSnapshotStructure(SnapshotInterface $artifact, array $normalized): bool
    {
        $classification = $normalized[ArtifactField::CLASSIFICATION] ?? null;
        $safeClassification = is_string($classification) ? SnapshotClassification::tryFrom($classification) : null;

        return $safeClassification !== null
            && $safeClassification->isTerminal() === $artifact->classification()->isTerminal()
            && is_int($normalized[ArtifactField::CREATED_AT] ?? null)
            && $normalized[ArtifactField::CREATED_AT] === $artifact->timestamp()
            && is_int($normalized[ArtifactField::SEQUENCE] ?? null)
            && $normalized[ArtifactField::SEQUENCE] >= 0;
    }

    /**
     * @param array<string, mixed> $normalized
     * @param array<string, int> $fields
     * @param array<string, mixed> $exportFields
     */
    private function buildExportProjection(
        array $normalized,
        array $fields,
        array $exportFields,
        string $kind,
        bool $isRedacted,
        bool $isDefaultSnapshot,
    ): ExecutionArtifactInterface {
        $nullable = [
            ArtifactField::ACTIVITY_ID, ArtifactField::OBSERVATION_ID,
            ArtifactField::PARENT_EXECUTION_ID, ArtifactField::PARENT_ACTIVITY_ID,
            ArtifactField::PREDECESSOR_ID,
        ];
        $projection = array_diff_key($normalized, $fields);
        foreach ($nullable as $field) {
            if (array_key_exists($field, $normalized)) {
                $projection[$field] = null;
            }
        }

        if (array_key_exists(ArtifactField::LINKED_EXECUTION_IDS, $normalized)) {
            $projection[ArtifactField::LINKED_EXECUTION_IDS] = [];
        }

        /** @var array<string, mixed> $projection */
        $projection = array_replace($projection, $exportFields);

        // Only structural fields and authority-issued identities are classified safe.
        if ($isDefaultSnapshot) {
            $projection = array_intersect_key($projection, array_flip([
                ArtifactField::ID, ArtifactField::EXECUTION_ID, ArtifactField::ACTIVITY_ID,
                ArtifactField::PREDECESSOR_ID, ArtifactField::CLASSIFICATION,
                ArtifactField::CREATED_AT, ArtifactField::SEQUENCE,
            ]));
        }

        // Freeze the validated projection so a mutable redactor result cannot
        // substitute different identifiers when sampled or classified.
        return $isRedacted
            ? $this->redactedFactory->create($projection, $kind)
            : new VerifiedArtifactProjection($projection, $kind);
    }
}
