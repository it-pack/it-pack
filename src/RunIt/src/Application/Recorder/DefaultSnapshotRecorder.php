<?php

declare(strict_types=1);

namespace ItPack\RunIt\Application\Recorder;

use Closure;
use ItPack\RunIt\Contract\Application\SnapshotRecorderInterface;
use ItPack\RunIt\Contract\Domain\ActivityIdInterface;
use ItPack\RunIt\Contract\Domain\ArtifactSequenceInterface;
use ItPack\RunIt\Contract\Domain\AttributeCollectionInterface;
use ItPack\RunIt\Contract\Domain\ExecutionIdInterface;
use ItPack\RunIt\Contract\Domain\Factory\StringIdentifierFactoryInterface;
use ItPack\RunIt\Contract\Domain\HashCalculatorInterface;
use ItPack\RunIt\Contract\Domain\InstantInterface;
use ItPack\RunIt\Contract\Domain\SequenceAllocatorInterface;
use ItPack\RunIt\Contract\Domain\SnapshotClassificationInterface;
use ItPack\RunIt\Contract\Domain\SnapshotInterface;
use ItPack\RunIt\Contract\Domain\SnapshotTypeInterface;
use ItPack\RunIt\Contract\Port\ArtifactPublisherInterface;
use ItPack\RunIt\Contract\Port\MonotonicClockInterface;
use ItPack\RunIt\Contract\Port\SerializerInterface;
use ItPack\RunIt\Contract\Port\SnapshotStoreInterface;
use ItPack\RunIt\Domain\Shared\Instant;
use ItPack\RunIt\Domain\Snapshot\ExecutionSnapshot;
use ItPack\RunIt\Domain\Snapshot\SnapshotClassification;
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
final class DefaultSnapshotRecorder implements SnapshotRecorderInterface
{
    /**
     * @param Closure(): ?ActivityIdInterface $currentActivityId
     */
    public function __construct(
        private readonly ExecutionIdInterface $executionId,
        private readonly Closure $currentActivityId,
        private readonly SequenceAllocatorInterface $sequenceAllocator,
        private readonly MonotonicClockInterface $clock,
        private readonly ArtifactPublisherInterface $publisher,
        private readonly SerializerInterface $serializer,
        /** @var StringIdentifierFactoryInterface<\ItPack\RunIt\Contract\Domain\SnapshotIdInterface> */
        private readonly StringIdentifierFactoryInterface $snapshotIdFactory,
        private readonly HashCalculatorInterface $hashCalculator,
        private readonly ?SnapshotStoreInterface $store = null,
        private readonly ?Closure $onStoreFailure = null,
    ) {
    }

    public function record(
        SnapshotTypeInterface $type,
        mixed $payload,
        ?SnapshotClassificationInterface $classification = null,
        int $schemaVersion = 1,
        ?AttributeCollectionInterface $attributes = null,
    ): SnapshotInterface {
        $snapshot = ExecutionSnapshot::create(
            $this->snapshotIdFactory->build(),
            $this->executionId,
            $type,
            $schemaVersion,
            $this->serializer->normalize($payload),
            $this->hashCalculator,
            $this->activityId(),
            null,
            $classification ?? SnapshotClassification::Checkpoint,
            $this->now(),
            $this->serializer->contentType(),
            $attributes,
            $this->sequence(),
        );

        $this->publisher->publish($snapshot);
        $this->persist($snapshot);

        return $snapshot;
    }

    private function persist(SnapshotInterface $snapshot): void
    {
        if (!$this->store instanceof SnapshotStoreInterface) {
            return;
        }

        try {
            $this->store->store($snapshot);
        } catch (Throwable $storeError) {
            if ($this->onStoreFailure instanceof Closure) {
                ($this->onStoreFailure)($storeError);
            }
        }
    }

    private function activityId(): ?ActivityIdInterface
    {
        return ($this->currentActivityId)();
    }

    private function now(): InstantInterface
    {
        return Instant::of($this->clock->nowSeconds());
    }

    private function sequence(): ArtifactSequenceInterface
    {
        return $this->sequenceAllocator->allocate($this->executionId);
    }
}
