<?php

declare(strict_types=1);

namespace ItPack\RunIt\Application\Scope;

use Closure;
use InvalidArgumentException;
use ItPack\RunIt\Application\Publication\ProvenanceAuthority;
use ItPack\RunIt\Application\Recorder\DefaultMeasurementRecorder;
use ItPack\RunIt\Application\Recorder\DefaultObservationRecorder;
use ItPack\RunIt\Application\Recorder\DefaultOccurrenceRecorder;
use ItPack\RunIt\Application\Recorder\DefaultSnapshotRecorder;
use ItPack\RunIt\Application\Runner\DefaultActivityRunner;
use ItPack\RunIt\Contract\Application\ActivityRunnerInterface;
use ItPack\RunIt\Contract\Application\ActivityScopeInterface;
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
use ItPack\RunIt\Contract\Domain\ObservationIdInterface;
use ItPack\RunIt\Contract\Domain\OccurrenceIdInterface;
use ItPack\RunIt\Contract\Domain\SequenceAllocatorInterface;
use ItPack\RunIt\Contract\Domain\SnapshotIdInterface;
use ItPack\RunIt\Contract\Domain\ThrowableMapperInterface;
use ItPack\RunIt\Contract\Port\ActivityContextStorageInterface;
use ItPack\RunIt\Contract\Port\ArtifactPublisherInterface;
use ItPack\RunIt\Contract\Port\ContextStorageInterface;
use ItPack\RunIt\Contract\Port\MonotonicClockInterface;
use ItPack\RunIt\Contract\Port\SerializerInterface;
use ItPack\RunIt\Contract\Port\SnapshotStoreInterface;
use ItPack\RunIt\Domain\Shared\SecurityLimits;
use RuntimeException;
use Throwable;

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

    private int $activityContextSuppression = 0;

    private int $activityContextFailureReporting = 0;

    /**
     * @var list<ActivityInterface>
     */
    private array $openActivities = [];

    private readonly MeasurementRecorderInterface $measurementRecorder;

    private readonly ObservationRecorderInterface $observationRecorder;

    private readonly OccurrenceRecorderInterface $occurrenceRecorder;

    private readonly SnapshotRecorderInterface $snapshotRecorder;

    private readonly ActivityRunnerInterface $activityRunner;

    /** @param (Closure(): ?ActivityIdInterface)|null $currentActivityId */
    public function __construct(
        private readonly ExecutionInterface $execution,
        ExecutionIdInterface $executionId,
        private readonly ExecutionContextInterface $context,
        private readonly SequenceAllocatorInterface $sequenceAllocator,
        private readonly MonotonicClockInterface $clock,
        private readonly ArtifactPublisherInterface $publisher,
        private readonly SerializerInterface $serializer,
        private readonly ThrowableMapperInterface $throwableMapper,
        /** @var StringIdentifierFactoryInterface<ObservationIdInterface> */
        private readonly StringIdentifierFactoryInterface $observationIdFactory,
        /** @var StringIdentifierFactoryInterface<OccurrenceIdInterface> */
        private readonly StringIdentifierFactoryInterface $occurrenceIdFactory,
        /** @var StringIdentifierFactoryInterface<SnapshotIdInterface> */
        private readonly StringIdentifierFactoryInterface $snapshotIdFactory,
        /** @var StringIdentifierFactoryInterface<ActivityIdInterface> */
        private readonly StringIdentifierFactoryInterface $activityIdFactory,
        private readonly HashCalculatorInterface $hashCalculator,
        private readonly ?SnapshotStoreInterface $snapshotStore = null,
        private readonly ?Closure $onSnapshotStoreFailure = null,
        private readonly SecurityLimits $limits = new SecurityLimits(),
        ?Closure $currentActivityId = null,
        private readonly ProvenanceAuthority $authority = new ProvenanceAuthority(),
        private readonly ?ContextStorageInterface $contextStorage = null,
        private readonly ?ActivityContextStorageInterface $activityStorage = null,
        private readonly string $activityContextFailurePolicy = 'fallback',
        private readonly ?Closure $onActivityContextFailure = null,
        bool $runOnActivityContextAttachFailure = false,
    ) {
        if (!in_array($activityContextFailurePolicy, ['fallback', 'drop', 'throw'], true)) {
            throw new InvalidArgumentException('Invalid activity context failure policy.');
        }

        $legacyActivityId = $currentActivityId ?? $this->currentActivityId(...);
        $activityId = $contextStorage instanceof ContextStorageInterface
            ? (fn (): ?ActivityIdInterface => $this->resolveActivityContext()[1])
            : fn (): ?ActivityIdInterface => $this->activityContextSuppression > 0 ? null : $legacyActivityId();
        $shouldDrop = $contextStorage instanceof ContextStorageInterface ? $this->shouldDrop(...) : null;
        $verifiedActivityExportId = $contextStorage instanceof ContextStorageInterface
            ? $this->resolveActivityContext(...) : null;

        $this->measurementRecorder = new DefaultMeasurementRecorder(
            $executionId,
            $activityId,
            $this->sequenceAllocator,
            $this->clock,
            $this->publisher,
            $this->authority,
            $shouldDrop,
            $verifiedActivityExportId
        );
        $this->observationRecorder = new DefaultObservationRecorder(
            $executionId,
            $activityId,
            $this->sequenceAllocator,
            $this->clock,
            $this->publisher,
            $this->throwableMapper,
            $this->observationIdFactory,
            $this->authority,
            $shouldDrop,
            $verifiedActivityExportId
        );
        $this->occurrenceRecorder = new DefaultOccurrenceRecorder(
            $executionId,
            $activityId,
            $this->sequenceAllocator,
            $this->clock,
            $this->publisher,
            $this->occurrenceIdFactory,
            $this->authority,
            $shouldDrop,
            $verifiedActivityExportId
        );
        $this->snapshotRecorder = new DefaultSnapshotRecorder(
            $executionId,
            $activityId,
            $this->sequenceAllocator,
            $this->clock,
            $this->publisher,
            $this->serializer,
            $this->snapshotIdFactory,
            $this->hashCalculator,
            $this->snapshotStore,
            $this->onSnapshotStoreFailure,
            $this->authority,
            $shouldDrop,
            $verifiedActivityExportId
        );
        $this->activityRunner = new DefaultActivityRunner(
            $this,
            $executionId,
            $this->sequenceAllocator,
            $this->clock,
            $this->publisher,
            $this->throwableMapper,
            $this->activityIdFactory,
            $this->limits,
            $this->authority,
            $contextStorage instanceof ContextStorageInterface ? $activityStorage : null,
            $this->reportActivityContextFailure(...),
            $runOnActivityContextAttachFailure
        );
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

    public function runWithoutActivityContext(callable $operation): mixed
    {
        // Suppression is execution-wide: overlapping activities also lose attribution until restored.
        ++$this->activityContextSuppression;
        try {
            return $operation();
        } finally {
            --$this->activityContextSuppression;
        }
    }

    /** @return array{bool, ?ActivityIdInterface, ?string} */
    private function resolveActivityContext(): array
    {
        if ($this->activityContextSuppression > 0) {
            return [false, null, null];
        }

        try {
            if ($this->contextStorage?->current() !== $this) {
                return [false, null, null];
            }

            $activityScope = $this->activityStorage?->current();
            if (!$activityScope instanceof ActivityScopeInterface) {
                return [true, null, null];
            }

            $activity = $activityScope->activity();
            $id = $activity->activityId();
            $exportId = $this->authority->exportIdForArtifact($activity);
            if ($activityScope->parentScope() !== $this
                || !in_array($activity, $this->openActivities, true)
                || $exportId === null
                || !$this->authority->verifyExportId($exportId, 'activity', $this->execution->id()->toString())
                || !$id instanceof ActivityIdInterface) {
                return [false, null, null];
            }

            return [true, $id, $exportId];
        } catch (Throwable) {
            return [false, null, null];
        }
    }

    private function reportActivityContextFailure(string $reason): void
    {
        // Guard against re-entry: a callback that records through this scope while the
        // context is invalid would invoke this reporter again before the first call
        // returns. Bound nested failures are suppressed so health reporting cannot
        // recurse into the same publication path or replace the operation result.
        if ($this->activityContextFailureReporting > 0) {
            return;
        }

        $this->activityContextFailureReporting++;
        try {
            ($this->onActivityContextFailure)?->__invoke($reason);
        } catch (Throwable) {
            // Health reporting must not replace the operation's result or exception.
        } finally {
            $this->activityContextFailureReporting--;
        }
    }

    private function shouldDrop(?string $recordedActivityId, ?bool $captureValid = null): bool
    {
        [$valid, $currentActivityId] = $this->resolveActivityContext();
        if ($recordedActivityId !== null && $recordedActivityId !== $currentActivityId?->toString()) {
            $this->reportActivityContextFailure('activity_context_changed');
            return true;
        }

        if ($valid && $captureValid !== false) {
            return false;
        }

        $this->reportActivityContextFailure('activity_context_unavailable');
        if ($this->activityContextFailurePolicy === 'throw') {
            throw new RuntimeException('Activity context is unavailable or invalid.');
        }

        return $this->activityContextFailurePolicy === 'drop';
    }
}
