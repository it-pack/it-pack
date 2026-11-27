<?php

declare(strict_types=1);

namespace ItPack\RunIt\Application\Recorder;

use Closure;
use ItPack\RunIt\Application\Publication\ProvenanceAuthority;
use ItPack\RunIt\Behavior\Application\InstantClockBehavior;
use ItPack\RunIt\Contract\Application\SnapshotRecorderInterface;
use ItPack\RunIt\Contract\Domain\ActivityIdInterface;
use ItPack\RunIt\Contract\Domain\ArtifactSequenceInterface;
use ItPack\RunIt\Contract\Domain\AttributeCollectionInterface;
use ItPack\RunIt\Contract\Domain\ExecutionIdInterface;
use ItPack\RunIt\Contract\Domain\Factory\StringIdentifierFactoryInterface;
use ItPack\RunIt\Contract\Domain\HashCalculatorInterface;
use ItPack\RunIt\Contract\Domain\SequenceAllocatorInterface;
use ItPack\RunIt\Contract\Domain\SnapshotClassificationInterface;
use ItPack\RunIt\Contract\Domain\SnapshotIdInterface;
use ItPack\RunIt\Contract\Domain\SnapshotInterface;
use ItPack\RunIt\Contract\Domain\SnapshotTypeInterface;
use ItPack\RunIt\Contract\Port\ArtifactPublisherInterface;
use ItPack\RunIt\Contract\Port\MonotonicClockInterface;
use ItPack\RunIt\Contract\Port\SerializerInterface;
use ItPack\RunIt\Contract\Port\SnapshotStoreInterface;
use ItPack\RunIt\Domain\Snapshot\ExecutionSnapshot;
use ItPack\RunIt\Domain\Snapshot\SnapshotClassification;
use RuntimeException;
use Throwable;

/**
 * Default snapshot recorder.
 *
 * It mints identity, calculates the content hash, fills ownership, timing, and sequence
 * automatically, and publishes each snapshot it creates. The active activity (if any) is
 * bound from the supplied closure.
 *
 * @see https://w3id.org/itpack/runit#Snapshot
 */
final readonly class DefaultSnapshotRecorder implements SnapshotRecorderInterface
{
    use InstantClockBehavior;

    private const int DEFAULT_SCHEMA_VERSION = 1;

    /**
     * The capture resolver returns context validity, activity ID, and its verified export ID.
     * Invalid captures discard both IDs; a missing resolver uses the legacy raw ID only.
     * The drop callback receives the captured validity (null for the legacy path).
     *
     * @param Closure(): ?ActivityIdInterface $currentActivityId
     * @param null|Closure(?string, ?bool): bool $shouldDrop
     * @param null|Closure(): array{bool, ?ActivityIdInterface, ?string} $verifiedActivityExportId
     */
    public function __construct(
        private ExecutionIdInterface             $executionId,
        private Closure                          $currentActivityId,
        private SequenceAllocatorInterface       $sequenceAllocator,
        private MonotonicClockInterface          $clock,
        private ArtifactPublisherInterface       $publisher,
        private SerializerInterface              $serializer,
        /** @var StringIdentifierFactoryInterface<SnapshotIdInterface> */
        private StringIdentifierFactoryInterface $snapshotIdFactory,
        private HashCalculatorInterface          $hashCalculator,
        private ?SnapshotStoreInterface          $store = null,
        private ?Closure                         $onStoreFailure = null,
        private ProvenanceAuthority              $authority = new ProvenanceAuthority(),
        private ?Closure                         $shouldDrop = null,
        private ?Closure                         $verifiedActivityExportId = null,
    ) {
    }

    public function record(
        SnapshotTypeInterface $type,
        mixed $payload,
        ?SnapshotClassificationInterface $classification = null,
        int $schemaVersion = self::DEFAULT_SCHEMA_VERSION,
        ?AttributeCollectionInterface $attributes = null,
    ): SnapshotInterface {
        [$activityId, $activityExportId, $valid] = $this->activity();
        $snapshot = $this->build(
            $type,
            $payload,
            $classification ?? SnapshotClassification::Checkpoint,
            $schemaVersion,
            $attributes,
            $activityId,
        );

        if ($this->shouldDrop instanceof Closure && ($this->shouldDrop)($snapshot->activityId()?->toString(), $valid)) {
            return $snapshot;
        }

        // Issue an SDK-controlled export ID for the snapshot and register the
        // execution→snapshot edge so provenance can be verified at publication.
        try {
            $exportId = $this->authority->bindArtifact(
                'snapshot',
                'snapshot',
                $this->executionId->toString(),
                $snapshot->id()->toString(),
                'execution-snapshot',
            );

            $this->authority->attest($snapshot, $exportId);
            if ($exportId !== null && $activityExportId !== null) {
                $this->authority->bindActivityRelationship($exportId, $activityExportId);
            }
        } catch (Throwable) {
            $this->authority->reportUnverified(new RuntimeException('Snapshot publication preparation failed.'));
            $this->persist($snapshot);
            return $snapshot;
        }

        $this->publisher->publish($snapshot);
        $this->persist($snapshot);

        return $snapshot;
    }

    /**
     * Normalizes and builds the snapshot, isolating a serializer failure so it can never abort the
     * calling operation. Failed serialization is reported, and a best-effort empty snapshot is
     * returned so the recording call always completes.
     */
    private function build(
        SnapshotTypeInterface $type,
        mixed $payload,
        SnapshotClassificationInterface $classification,
        int $schemaVersion,
        ?AttributeCollectionInterface $attributes,
        ?ActivityIdInterface $activityId,
    ): SnapshotInterface {
        try {
            $normalized = $this->serializer->normalize($payload);
        } catch (Throwable $throwable) {
            if ($this->onStoreFailure instanceof Closure) {
                ($this->onStoreFailure)($throwable);
            }

            return ExecutionSnapshot::create(
                $this->snapshotIdFactory->build(),
                $this->executionId,
                $type,
                $schemaVersion,
                [],
                $this->hashCalculator,
                $activityId,
                classification: $classification,
                createdAt: $this->now(),
                contentType: $this->serializer->contentType(),
                attributes: $attributes,
                sequence: $this->sequence(),
            );
        }

        return ExecutionSnapshot::create(
            $this->snapshotIdFactory->build(),
            $this->executionId,
            $type,
            $schemaVersion,
            $normalized,
            $this->hashCalculator,
            $activityId,
            classification: $classification,
            createdAt: $this->now(),
            contentType: $this->serializer->contentType(),
            attributes: $attributes,
            sequence: $this->sequence(),
        );
    }

    private function persist(SnapshotInterface $snapshot): void
    {
        if (!$this->store instanceof SnapshotStoreInterface) {
            return;
        }

        try {
            $this->store->store($snapshot);
        } catch (Throwable $throwable) {
            if ($this->onStoreFailure instanceof Closure) {
                ($this->onStoreFailure)($throwable);
            }
        }
    }

    /** @return array{?ActivityIdInterface, ?string, ?bool} */
    private function activity(): array
    {
        if ($this->verifiedActivityExportId instanceof Closure) {
            [$valid, $id, $exportId] = ($this->verifiedActivityExportId)();
            return [$valid ? $id : null, $valid && $id !== null ? $exportId : null, $valid];
        }

        return $this->activityLegacy(($this->currentActivityId)());
    }

    /** @return array{?ActivityIdInterface, ?string, ?bool} */
    private function activityLegacy(?ActivityIdInterface $activityId): array
    {
        return [$activityId, null, null];
    }

    private function sequence(): ArtifactSequenceInterface
    {
        return $this->sequenceAllocator->allocate($this->executionId);
    }
}
