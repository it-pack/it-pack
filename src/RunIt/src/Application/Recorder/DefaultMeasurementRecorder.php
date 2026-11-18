<?php

declare(strict_types=1);

namespace ItPack\RunIt\Application\Recorder;

use Closure;
use ItPack\RunIt\Application\Publication\ProvenanceAuthority;
use ItPack\RunIt\Behavior\Application\InstantClockBehavior;
use ItPack\RunIt\Contract\Application\MeasurementRecorderInterface;
use ItPack\RunIt\Contract\Domain\ActivityIdInterface;
use ItPack\RunIt\Contract\Domain\ArtifactSequenceInterface;
use ItPack\RunIt\Contract\Domain\AttributeCollectionInterface;
use ItPack\RunIt\Contract\Domain\ExecutionIdInterface;
use ItPack\RunIt\Contract\Domain\MeasurementInterface;
use ItPack\RunIt\Contract\Domain\MeasurementNameInterface;
use ItPack\RunIt\Contract\Domain\MeasurementUnitInterface;
use ItPack\RunIt\Contract\Domain\SequenceAllocatorInterface;
use ItPack\RunIt\Contract\Port\ArtifactPublisherInterface;
use ItPack\RunIt\Contract\Port\MonotonicClockInterface;
use ItPack\RunIt\Domain\Measurement\Measurement;
use ItPack\RunIt\Domain\Measurement\MeasurementName;
use ItPack\RunIt\Domain\Measurement\MeasurementUnit;
use RuntimeException;
use Throwable;

/**
 * Default measurement recorder.
 *
 * It fills ownership, timing, and sequence automatically and publishes each measurement it
 * creates. The active activity (if any) is bound from the supplied closure so recordings
 * inside an activity attach to that activity.
 *
 * @see https://w3id.org/itpack/runit#Measurement
 */
final readonly class DefaultMeasurementRecorder implements MeasurementRecorderInterface
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
        private ExecutionIdInterface       $executionId,
        private Closure                    $currentActivityId,
        private SequenceAllocatorInterface $sequenceAllocator,
        private MonotonicClockInterface    $clock,
        private ArtifactPublisherInterface $publisher,
        private ProvenanceAuthority        $authority = new ProvenanceAuthority(),
        private ?Closure                   $shouldDrop = null,
        private ?Closure                   $verifiedActivityExportId = null,
    ) {
    }

    public function counter(
        string|MeasurementNameInterface $name,
        int $value,
        ?MeasurementUnitInterface $unit = null,
        ?AttributeCollectionInterface $attributes = null
    ): MeasurementInterface {
        [$activityId, $activityExportId, $valid] = $this->activity();
        return $this->publish(Measurement::counter(
            $this->name($name),
            $this->executionId,
            $value,
            $activityId,
            $this->now(),
            $this->sequence(),
            $attributes,
            $unit ?? MeasurementUnit::Count
        ), $activityExportId, $valid);
    }

    public function gauge(
        string|MeasurementNameInterface $name,
        float $value,
        ?MeasurementUnitInterface $unit = null,
        ?AttributeCollectionInterface $attributes = null
    ): MeasurementInterface {
        [$activityId, $activityExportId, $valid] = $this->activity();
        return $this->publish(Measurement::gauge(
            $this->name($name),
            $this->executionId,
            $value,
            $activityId,
            $this->now(),
            $this->sequence(),
            $attributes,
            $unit ?? MeasurementUnit::Percent
        ), $activityExportId, $valid);
    }

    /**
     * @throws Throwable
     */
    public function duration(
        string|MeasurementNameInterface $name,
        float $value,
        ?MeasurementUnitInterface $unit = null,
        ?AttributeCollectionInterface $attributes = null
    ): MeasurementInterface {
        [$activityId, $activityExportId, $valid] = $this->activity();
        return $this->publish(Measurement::duration(
            $this->name($name),
            $this->executionId,
            $value,
            $activityId,
            $this->now(),
            $this->sequence(),
            $attributes,
            $unit ?? MeasurementUnit::Milliseconds
        ), $activityExportId, $valid);
    }

    /**
     * @throws Throwable
     */
    public function size(
        string|MeasurementNameInterface $name,
        int $value,
        ?MeasurementUnitInterface $unit = null,
        ?AttributeCollectionInterface $attributes = null
    ): MeasurementInterface {
        [$activityId, $activityExportId, $valid] = $this->activity();
        return $this->publish(Measurement::size(
            $this->name($name),
            $this->executionId,
            $value,
            $activityId,
            $this->now(),
            $this->sequence(),
            $attributes,
            $unit ?? MeasurementUnit::Bytes
        ), $activityExportId, $valid);
    }

    /**
     * @throws Throwable
     */
    public function ratio(
        string|MeasurementNameInterface $name,
        float $value,
        ?MeasurementUnitInterface $unit = null,
        ?AttributeCollectionInterface $attributes = null
    ): MeasurementInterface {
        [$activityId, $activityExportId, $valid] = $this->activity();
        return $this->publish(Measurement::ratio(
            $this->name($name),
            $this->executionId,
            $value,
            $activityId,
            $this->now(),
            $this->sequence(),
            $attributes,
            $unit ?? MeasurementUnit::Ratio
        ), $activityExportId, $valid);
    }

    private function name(string|MeasurementNameInterface $name): MeasurementNameInterface
    {
        return $name instanceof MeasurementNameInterface ? $name : MeasurementName::fromString($name);
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

    /**
     * @throws Throwable
     */
    private function publish(
        MeasurementInterface $measurement,
        ?string $activityExportId,
        ?bool $valid,
    ): MeasurementInterface {
        if ($this->shouldDrop instanceof Closure
            && ($this->shouldDrop)($measurement->activityId()?->toString(), $valid)) {
            return $measurement;
        }

        // Issue an SDK-controlled export ID bound to the measurement's unique
        // sequence (each measurement gets a distinct sequence from the allocator)
        // and register the execution→measurement relationship edge. The name is
        // repeatable across calls and cannot serve as a distinct artifact identity.
        try {
            $businessId = (string) $measurement->sequence();
            $exportId = $this->authority->bindArtifact(
                'measurement',
                'measurement',
                $this->executionId->toString(),
                $businessId,
                'execution-measurement',
            );

            $this->authority->attest($measurement, $exportId);
            if ($exportId !== null && $activityExportId !== null) {
                $this->authority->bindActivityRelationship($exportId, $activityExportId);
            }
        } catch (Throwable) {
            $this->authority->reportUnverified(new RuntimeException('Measurement publication preparation failed.'));
            return $measurement;
        }

        $this->publisher->publish($measurement);

        return $measurement;
    }
}
