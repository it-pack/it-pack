<?php

declare(strict_types=1);

namespace ItPack\RunIt\Application\Scope;

use Closure;
use ItPack\RunIt\Application\Recorder\DefaultMeasurementRecorder;
use ItPack\RunIt\Application\Recorder\DefaultObservationRecorder;
use ItPack\RunIt\Application\Recorder\DefaultOccurrenceRecorder;
use ItPack\RunIt\Application\Recorder\DefaultSnapshotRecorder;
use ItPack\RunIt\Application\Runner\DefaultActivityRunner;
use ItPack\RunIt\Contract\Application\ActivityRunnerInterface;
use ItPack\RunIt\Contract\Application\ExecutionScopeInterface;
use ItPack\RunIt\Contract\Application\MeasurementRecorderInterface;
use ItPack\RunIt\Contract\Application\ObservationRecorderInterface;
use ItPack\RunIt\Contract\Application\OccurrenceRecorderInterface;
use ItPack\RunIt\Contract\Application\SnapshotRecorderInterface;
use ItPack\RunIt\Contract\Domain\ActivityIdInterface;
use ItPack\RunIt\Contract\Domain\ActivityInterface;
use ItPack\RunIt\Contract\Domain\ExecutionContextInterface;
use ItPack\RunIt\Contract\Domain\ExecutionIdInterface;
use ItPack\RunIt\Contract\Domain\ExecutionInterface;
use ItPack\RunIt\Contract\Domain\Factory\StringIdentifierFactoryInterface;
use ItPack\RunIt\Contract\Domain\HashCalculatorInterface;
use ItPack\RunIt\Contract\Domain\SequenceAllocatorInterface;
use ItPack\RunIt\Contract\Domain\ThrowableMapperInterface;
use ItPack\RunIt\Contract\Port\ArtifactPublisherInterface;
use ItPack\RunIt\Contract\Port\MonotonicClockInterface;
use ItPack\RunIt\Contract\Port\SerializerInterface;
use ItPack\RunIt\Contract\Port\SnapshotStoreInterface;
use ItPack\RunIt\Domain\Shared\SecurityLimits;

/**
 * The runtime collaborator handed to a consumer operation during an execution.
 *
 * It exposes the running execution record, its immutable context, and live recorders and
 * runners. The recorders bind every artifact they create to the execution (and to any
 * active activity) automatically, so the consumer never supplies ownership or timing.
 *
 * The scope is a runtime object, not a domain value: the active activity identity changes
 * as activities start and complete, and open activities are tracked for completion policy.
 *
 * @see https://w3id.org/itpack/runit#Execution
 */
final class ExecutionScope implements ExecutionScopeInterface
{
    private ?ActivityIdInterface $currentActivityId = null;

    /**
     * @var list<ActivityInterface>
     */
    private array $openActivities = [];

    private MeasurementRecorderInterface $measurementRecorder;
    private ObservationRecorderInterface $observationRecorder;
    private OccurrenceRecorderInterface $occurrenceRecorder;
    private SnapshotRecorderInterface $snapshotRecorder;
    private ActivityRunnerInterface $activityRunner;

    /**
     * @param Closure(): ?ActivityIdInterface $currentActivityId
     */
    public function __construct(
        private readonly ExecutionInterface $execution,
        ExecutionIdInterface $executionId,
        private readonly ExecutionContextInterface $context,
        private readonly SequenceAllocatorInterface $sequenceAllocator,
        private readonly MonotonicClockInterface $clock,
        private readonly ArtifactPublisherInterface $publisher,
        private readonly SerializerInterface $serializer,
        private readonly ThrowableMapperInterface $throwableMapper,
        /** @var StringIdentifierFactoryInterface<\ItPack\RunIt\Contract\Domain\ObservationIdInterface> */
        private readonly StringIdentifierFactoryInterface $observationIdFactory,
        /** @var StringIdentifierFactoryInterface<\ItPack\RunIt\Contract\Domain\OccurrenceIdInterface> */
        private readonly StringIdentifierFactoryInterface $occurrenceIdFactory,
        /** @var StringIdentifierFactoryInterface<\ItPack\RunIt\Contract\Domain\SnapshotIdInterface> */
        private readonly StringIdentifierFactoryInterface $snapshotIdFactory,
        /** @var StringIdentifierFactoryInterface<\ItPack\RunIt\Contract\Domain\ActivityIdInterface> */
        private readonly StringIdentifierFactoryInterface $activityIdFactory,
        private readonly HashCalculatorInterface $hashCalculator,
        private readonly ?SnapshotStoreInterface $snapshotStore = null,
        private readonly ?Closure $onSnapshotStoreFailure = null,
        private readonly SecurityLimits $limits = new SecurityLimits(),
        ?Closure $currentActivityId = null,
    ) {
        $activityId = $currentActivityId ?? (fn (): ?ActivityIdInterface => $this->currentActivityId);

        $this->measurementRecorder = new DefaultMeasurementRecorder($executionId, $activityId, $this->sequenceAllocator, $this->clock, $this->publisher);
        $this->observationRecorder = new DefaultObservationRecorder($executionId, $activityId, $this->sequenceAllocator, $this->clock, $this->publisher, $this->throwableMapper, $this->observationIdFactory);
        $this->occurrenceRecorder = new DefaultOccurrenceRecorder($executionId, $activityId, $this->sequenceAllocator, $this->clock, $this->publisher, $this->occurrenceIdFactory);
        $this->snapshotRecorder = new DefaultSnapshotRecorder($executionId, $activityId, $this->sequenceAllocator, $this->clock, $this->publisher, $this->serializer, $this->snapshotIdFactory, $this->hashCalculator, $this->snapshotStore, $this->onSnapshotStoreFailure);
        $this->activityRunner = new DefaultActivityRunner($this, $executionId, $this->sequenceAllocator, $this->clock, $this->publisher, $this->throwableMapper, $this->activityIdFactory, $this->limits);
    }

    public function execution(): ExecutionInterface
    {
        return $this->execution;
    }

    public function context(): ExecutionContextInterface
    {
        return $this->context;
    }

    public function activities(): ActivityRunnerInterface
    {
        return $this->activityRunner;
    }

    public function measurements(): MeasurementRecorderInterface
    {
        return $this->measurementRecorder;
    }

    public function observations(): ObservationRecorderInterface
    {
        return $this->observationRecorder;
    }

    public function occurrences(): OccurrenceRecorderInterface
    {
        return $this->occurrenceRecorder;
    }

    public function snapshots(): SnapshotRecorderInterface
    {
        return $this->snapshotRecorder;
    }

    public function currentActivityId(): ?ActivityIdInterface
    {
        return $this->currentActivityId;
    }

    public function setCurrentActivityId(?ActivityIdInterface $currentActivityId): void
    {
        $this->currentActivityId = $currentActivityId;
    }

    public function trackOpenActivity(ActivityInterface $activity): void
    {
        $this->openActivities[] = $activity;
    }

    public function clearOpenActivity(ActivityIdInterface $activityId): void
    {
        $this->openActivities = array_values(array_filter(
            $this->openActivities,
            static fn (ActivityInterface $activity): bool => !($activity->activityId()?->equals($activityId) ?? false),
        ));
    }

    /**
     * @return list<ActivityInterface>
     */
    public function openActivities(): array
    {
        return $this->openActivities;
    }
}
