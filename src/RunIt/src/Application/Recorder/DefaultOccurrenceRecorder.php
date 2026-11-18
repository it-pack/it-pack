<?php

declare(strict_types=1);

namespace ItPack\RunIt\Application\Recorder;

use Closure;
use ItPack\RunIt\Application\Publication\ProvenanceAuthority;
use ItPack\RunIt\Behavior\Application\InstantClockBehavior;
use ItPack\RunIt\Contract\Application\OccurrenceRecorderInterface;
use ItPack\RunIt\Contract\Domain\ActivityIdInterface;
use ItPack\RunIt\Contract\Domain\ArtifactSequenceInterface;
use ItPack\RunIt\Contract\Domain\AttributeCollectionInterface;
use ItPack\RunIt\Contract\Domain\ExecutionIdInterface;
use ItPack\RunIt\Contract\Domain\Factory\StringIdentifierFactoryInterface;
use ItPack\RunIt\Contract\Domain\OccurrenceIdInterface;
use ItPack\RunIt\Contract\Domain\OccurrenceInterface;
use ItPack\RunIt\Contract\Domain\OccurrenceTypeInterface;
use ItPack\RunIt\Contract\Domain\SequenceAllocatorInterface;
use ItPack\RunIt\Contract\Port\ArtifactPublisherInterface;
use ItPack\RunIt\Contract\Port\MonotonicClockInterface;
use ItPack\RunIt\Contract\Shared\MediaTypes;
use ItPack\RunIt\Domain\Occurrence\Occurrence;
use RuntimeException;
use Throwable;

/**
 * Default occurrence recorder.
 *
 * It mints identity, fills ownership, timing, and sequence automatically, and publishes each
 * occurrence it creates. The active activity (if any) is bound from the supplied closure.
 *
 * @see https://w3id.org/itpack/runit#Occurrence
 */
final readonly class DefaultOccurrenceRecorder implements OccurrenceRecorderInterface
{
    use InstantClockBehavior;

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
        private ExecutionIdInterface $executionId,
        private Closure $currentActivityId,
        private SequenceAllocatorInterface $sequenceAllocator,
        private MonotonicClockInterface $clock,
        private ArtifactPublisherInterface $publisher,
        /** @var StringIdentifierFactoryInterface<OccurrenceIdInterface> */
        private StringIdentifierFactoryInterface $occurrenceIdFactory,
        private ProvenanceAuthority              $authority = new ProvenanceAuthority(),
        private ?Closure                         $shouldDrop = null,
        private ?Closure                         $verifiedActivityExportId = null,
    ) {
    }

    public function record(
        OccurrenceTypeInterface $type,
        string $source,
        mixed $data = null,
        ?string $subject = null,
        ?AttributeCollectionInterface $extensionAttributes = null,
        ?string $dataSchema = null,
        string $contentType = MediaTypes::APPLICATION_JSON,
    ): OccurrenceInterface {
        [$activityId, $activityExportId, $valid] = $this->activity();
        $occurrence = Occurrence::create(
            $this->occurrenceIdFactory->build(),
            $this->executionId,
            $type,
            $source,
            $this->now(),
            $data,
            $activityId,
            $subject,
            $dataSchema,
            $contentType,
            $extensionAttributes,
            $this->sequence(),
        );

        if ($this->shouldDrop instanceof Closure
            && ($this->shouldDrop)($occurrence->activityId()?->toString(), $valid)) {
            return $occurrence;
        }

        // Issue an SDK-controlled export ID for the occurrence and register the
        // execution→occurrence edge so provenance can be verified at publication.
        try {
            $exportId = $this->authority->bindArtifact(
                'occurrence',
                'occurrence',
                $this->executionId->toString(),
                $occurrence->id()->toString(),
                'execution-occurrence',
            );

            $this->authority->attest($occurrence, $exportId);
            if ($exportId !== null && $activityExportId !== null) {
                $this->authority->bindActivityRelationship($exportId, $activityExportId);
            }
        } catch (Throwable) {
            $this->authority->reportUnverified(new RuntimeException('Occurrence publication preparation failed.'));
            return $occurrence;
        }

        $this->publisher->publish($occurrence);

        return $occurrence;
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
