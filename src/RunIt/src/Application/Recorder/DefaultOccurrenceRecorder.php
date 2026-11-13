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
     * @param Closure(): ?ActivityIdInterface $currentActivityId
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
        $occurrence = Occurrence::create(
            $this->occurrenceIdFactory->build(),
            $this->executionId,
            $type,
            $source,
            $this->now(),
            $data,
            $this->activityId(),
            $subject,
            $dataSchema,
            $contentType,
            $extensionAttributes,
            $this->sequence(),
        );

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
            $this->authority->bindActivityRelationship($exportId, $occurrence->activityId()?->toString());
        } catch (Throwable) {
            $this->authority->reportUnverified(new RuntimeException('Occurrence publication preparation failed.'));
            return $occurrence;
        }

        $this->publisher->publish($occurrence);

        return $occurrence;
    }

    private function activityId(): ?ActivityIdInterface
    {
        return ($this->currentActivityId)();
    }

    private function sequence(): ArtifactSequenceInterface
    {
        return $this->sequenceAllocator->allocate($this->executionId);
    }
}
