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
     * @param Closure(): ?ActivityIdInterface $currentActivityId
     */
    public function __construct(
        private ExecutionIdInterface       $executionId,
        private Closure                    $currentActivityId,
        private SequenceAllocatorInterface $sequenceAllocator,
        private MonotonicClockInterface    $clock,
        private ArtifactPublisherInterface $publisher,
        private ProvenanceAuthority        $authority = new ProvenanceAuthority(),
    ) {
    }

    public function counter(
        string|MeasurementNameInterface $name,
        int $value,
        ?MeasurementUnitInterface $unit = null,
        ?AttributeCollectionInterface $attributes = null
    ): MeasurementInterface {
        return $this->publish(Measurement::counter(
            $this->name($name),
            $this->executionId,
            $value,
            $this->activityId(),
            $this->now(),
            $this->sequence(),
            $attributes,
            $unit ?? MeasurementUnit::Count
        ));
    }

    public function gauge(
        string|MeasurementNameInterface $name,
        float $value,
        ?MeasurementUnitInterface $unit = null,
        ?AttributeCollectionInterface $attributes = null
    ): MeasurementInterface {
        return $this->publish(Measurement::gauge(
            $this->name($name),
            $this->executionId,
            $value,
            $this->activityId(),
            $this->now(),
            $this->sequence(),
            $attributes,
            $unit ?? MeasurementUnit::Percent
        ));
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
        return $this->publish(Measurement::duration(
            $this->name($name),
            $this->executionId,
            $value,
            $this->activityId(),
            $this->now(),
            $this->sequence(),
            $attributes,
            $unit ?? MeasurementUnit::Milliseconds
        ));
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
        return $this->publish(Measurement::size(
            $this->name($name),
            $this->executionId,
            $value,
            $this->activityId(),
            $this->now(),
            $this->sequence(),
            $attributes,
            $unit ?? MeasurementUnit::Bytes
        ));
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
        return $this->publish(Measurement::ratio(
            $this->name($name),
            $this->executionId,
            $value,
            $this->activityId(),
            $this->now(),
            $this->sequence(),
            $attributes,
            $unit ?? MeasurementUnit::Ratio
        ));
    }

    private function name(string|MeasurementNameInterface $name): MeasurementNameInterface
    {
        return $name instanceof MeasurementNameInterface ? $name : MeasurementName::fromString($name);
    }

    private function activityId(): ?ActivityIdInterface
    {
        return ($this->currentActivityId)();
    }

    private function sequence(): ArtifactSequenceInterface
    {
        return $this->sequenceAllocator->allocate($this->executionId);
    }

    /**
     * @throws Throwable
     */
    private function publish(MeasurementInterface $measurement): MeasurementInterface
    {
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
            $this->authority->bindActivityRelationship($exportId, $measurement->activityId()?->toString());
        } catch (Throwable) {
            $this->authority->reportUnverified(new RuntimeException('Measurement publication preparation failed.'));
            return $measurement;
        }

        $this->publisher->publish($measurement);

        return $measurement;
    }
}
