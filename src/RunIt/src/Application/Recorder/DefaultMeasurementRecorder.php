<?php

declare(strict_types=1);

namespace ItPack\RunIt\Application\Recorder;

use Closure;
use ItPack\RunIt\Contract\Application\MeasurementRecorderInterface;
use ItPack\RunIt\Contract\Domain\ActivityIdInterface;
use ItPack\RunIt\Contract\Domain\ArtifactSequenceInterface;
use ItPack\RunIt\Contract\Domain\AttributeCollectionInterface;
use ItPack\RunIt\Contract\Domain\ExecutionIdInterface;
use ItPack\RunIt\Contract\Domain\InstantInterface;
use ItPack\RunIt\Contract\Domain\MeasurementInterface;
use ItPack\RunIt\Contract\Domain\MeasurementNameInterface;
use ItPack\RunIt\Contract\Domain\MeasurementUnitInterface;
use ItPack\RunIt\Contract\Domain\SequenceAllocatorInterface;
use ItPack\RunIt\Contract\Port\ArtifactPublisherInterface;
use ItPack\RunIt\Contract\Port\MonotonicClockInterface;
use ItPack\RunIt\Domain\Measurement\Measurement;
use ItPack\RunIt\Domain\Measurement\MeasurementName;
use ItPack\RunIt\Domain\Measurement\MeasurementUnit;
use ItPack\RunIt\Domain\Shared\Instant;

/**
 * Default measurement recorder.
 *
 * It fills ownership, timing, and sequence automatically and publishes each measurement it
 * creates. The active activity (if any) is bound from the supplied closure so recordings
 * inside an activity attach to that activity.
 *
 * @see https://w3id.org/itpack/runit#measurement
 */
final class DefaultMeasurementRecorder implements MeasurementRecorderInterface
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
    ) {
    }

    public function counter(string|MeasurementNameInterface $name, int $value, ?MeasurementUnitInterface $unit = null, ?AttributeCollectionInterface $attributes = null): MeasurementInterface
    {
        return $this->publish(Measurement::counter($this->name($name), $this->executionId, $value, $this->activityId(), $this->now(), $this->sequence(), $attributes, $unit ?? MeasurementUnit::Count));
    }

    public function gauge(string|MeasurementNameInterface $name, float $value, ?MeasurementUnitInterface $unit = null, ?AttributeCollectionInterface $attributes = null): MeasurementInterface
    {
        return $this->publish(Measurement::gauge($this->name($name), $this->executionId, $value, $this->activityId(), $this->now(), $this->sequence(), $attributes, $unit ?? MeasurementUnit::Percent));
    }

    public function duration(string|MeasurementNameInterface $name, float $value, ?MeasurementUnitInterface $unit = null, ?AttributeCollectionInterface $attributes = null): MeasurementInterface
    {
        return $this->publish(Measurement::duration($this->name($name), $this->executionId, $value, $this->activityId(), $this->now(), $this->sequence(), $attributes, $unit ?? MeasurementUnit::Milliseconds));
    }

    public function size(string|MeasurementNameInterface $name, int $value, ?MeasurementUnitInterface $unit = null, ?AttributeCollectionInterface $attributes = null): MeasurementInterface
    {
        return $this->publish(Measurement::size($this->name($name), $this->executionId, $value, $this->activityId(), $this->now(), $this->sequence(), $attributes, $unit ?? MeasurementUnit::Bytes));
    }

    public function ratio(string|MeasurementNameInterface $name, float $value, ?MeasurementUnitInterface $unit = null, ?AttributeCollectionInterface $attributes = null): MeasurementInterface
    {
        return $this->publish(Measurement::ratio($this->name($name), $this->executionId, $value, $this->activityId(), $this->now(), $this->sequence(), $attributes, $unit ?? MeasurementUnit::Ratio));
    }

    private function name(string|MeasurementNameInterface $name): MeasurementNameInterface
    {
        return $name instanceof MeasurementNameInterface ? $name : MeasurementName::fromString($name);
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

    private function publish(MeasurementInterface $measurement): MeasurementInterface
    {
        $this->publisher->publish($measurement);

        return $measurement;
    }
}
