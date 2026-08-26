<?php

declare(strict_types=1);

namespace ItPack\RunIt\Application\Recorder;

use Closure;
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

/**
 * Default occurrence recorder.
 *
 * It mints identity, fills ownership, timing, and sequence automatically, and publishes each
 * occurrence it creates. The active activity (if any) is bound from the supplied closure.
 *
 * @see https://w3id.org/itpack/runit#Occurrence
 */
final class DefaultOccurrenceRecorder implements OccurrenceRecorderInterface
{
    use InstantClockBehavior;

    /**
     * @param Closure(): ?ActivityIdInterface $currentActivityId
     */
    public function __construct(
        private readonly ExecutionIdInterface $executionId,
        private readonly Closure $currentActivityId,
        private readonly SequenceAllocatorInterface $sequenceAllocator,
        private readonly MonotonicClockInterface $clock,
        private readonly ArtifactPublisherInterface $publisher,
        /** @var StringIdentifierFactoryInterface<OccurrenceIdInterface> */
        private readonly StringIdentifierFactoryInterface $occurrenceIdFactory,
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
