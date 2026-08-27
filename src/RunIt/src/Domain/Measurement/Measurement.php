<?php

declare(strict_types=1);

namespace ItPack\RunIt\Domain\Measurement;

use ItPack\RunIt\Behavior\Domain\SequencedBehavior;
use ItPack\RunIt\Contract\Domain\ActivityIdInterface;
use ItPack\RunIt\Contract\Domain\ArtifactSequenceInterface;
use ItPack\RunIt\Contract\Domain\AttributeCollectionInterface;
use ItPack\RunIt\Contract\Domain\ExecutionIdInterface;
use ItPack\RunIt\Contract\Domain\InstantInterface;
use ItPack\RunIt\Contract\Domain\MeasurementInterface;
use ItPack\RunIt\Contract\Domain\MeasurementKindInterface;
use ItPack\RunIt\Contract\Domain\MeasurementNameInterface;
use ItPack\RunIt\Contract\Domain\MeasurementUnitInterface;
use ItPack\RunIt\Contract\Domain\StringIdentifierInterface;
use ItPack\RunIt\Domain\Exception\InvalidMeasurementValueException;
use ItPack\RunIt\Domain\Shared\ArtifactField;
use ItPack\RunIt\Domain\Shared\ArtifactSequence;
use ItPack\RunIt\Domain\Shared\AttributeCollection;
use ItPack\RunIt\Domain\Shared\Instant;

/**
 * An immutable measurement artifact.
 *
 * A measurement records a named, typed, unit-bearing numeric value at a point in time
 * within an execution. Counters and sizes carry integer values; gauges, durations, and
 * ratios carry floating-point values, which must be finite. The measurement never
 * retains a mutable recorder session.
 *
 * @api
 *
 * @see https://w3id.org/itpack/runit#Measurement
 */
final readonly class Measurement implements MeasurementInterface
{
    use SequencedBehavior;

    private function __construct(
        private MeasurementNameInterface $name,
        private MeasurementKindInterface $kind,
        private MeasurementUnitInterface $unit,
        private int|float $value,
        private ExecutionIdInterface $executionId,
        private ?ActivityIdInterface $activityId,
        private InstantInterface $instant,
        private AttributeCollectionInterface $attributes,
        private ArtifactSequenceInterface $sequence,
    ) {
    }

    public static function counter(
        MeasurementNameInterface $name,
        ExecutionIdInterface $executionId,
        int $value,
        ?ActivityIdInterface $activityId = null,
        ?InstantInterface $instant = null,
        ?ArtifactSequenceInterface $sequence = null,
        ?AttributeCollectionInterface $attributes = null,
        MeasurementUnitInterface $unit = MeasurementUnit::Count,
    ): self {
        return self::record(
            MeasurementKind::Counter,
            $unit,
            $name,
            $executionId,
            $value,
            $activityId,
            $instant,
            $sequence,
            $attributes
        );
    }

    public static function gauge(
        MeasurementNameInterface $name,
        ExecutionIdInterface $executionId,
        float $value,
        ?ActivityIdInterface $activityId = null,
        ?InstantInterface $instant = null,
        ?ArtifactSequenceInterface $sequence = null,
        ?AttributeCollectionInterface $attributes = null,
        MeasurementUnitInterface $unit = MeasurementUnit::Percent,
    ): self {
        return self::record(
            MeasurementKind::Gauge,
            $unit,
            $name,
            $executionId,
            $value,
            $activityId,
            $instant,
            $sequence,
            $attributes
        );
    }

    public static function duration(
        MeasurementNameInterface $name,
        ExecutionIdInterface $executionId,
        float $value,
        ?ActivityIdInterface $activityId = null,
        ?InstantInterface $instant = null,
        ?ArtifactSequenceInterface $sequence = null,
        ?AttributeCollectionInterface $attributes = null,
        MeasurementUnitInterface $unit = MeasurementUnit::Milliseconds,
    ): self {
        return self::record(
            MeasurementKind::Duration,
            $unit,
            $name,
            $executionId,
            $value,
            $activityId,
            $instant,
            $sequence,
            $attributes
        );
    }

    public static function size(
        MeasurementNameInterface $name,
        ExecutionIdInterface $executionId,
        int $value,
        ?ActivityIdInterface $activityId = null,
        ?InstantInterface $instant = null,
        ?ArtifactSequenceInterface $sequence = null,
        ?AttributeCollectionInterface $attributes = null,
        MeasurementUnitInterface $unit = MeasurementUnit::Bytes,
    ): self {
        return self::record(
            MeasurementKind::Size,
            $unit,
            $name,
            $executionId,
            $value,
            $activityId,
            $instant,
            $sequence,
            $attributes
        );
    }

    public static function ratio(
        MeasurementNameInterface $name,
        ExecutionIdInterface $executionId,
        float $value,
        ?ActivityIdInterface $activityId = null,
        ?InstantInterface $instant = null,
        ?ArtifactSequenceInterface $sequence = null,
        ?AttributeCollectionInterface $attributes = null,
        MeasurementUnitInterface $unit = MeasurementUnit::Ratio,
    ): self {
        return self::record(
            MeasurementKind::Ratio,
            $unit,
            $name,
            $executionId,
            $value,
            $activityId,
            $instant,
            $sequence,
            $attributes
        );
    }

    private static function record(
        MeasurementKindInterface $kind,
        MeasurementUnitInterface $unit,
        MeasurementNameInterface $name,
        ExecutionIdInterface $executionId,
        int|float $value,
        ?ActivityIdInterface $activityId,
        ?InstantInterface $instant,
        ?ArtifactSequenceInterface $sequence,
        ?AttributeCollectionInterface $attributes,
    ): self {
        if (is_float($value)) {
            self::assertFinite($value);
        }

        return new self(
            $name,
            $kind,
            $unit,
            $value,
            $executionId,
            $activityId,
            $instant ?? Instant::now(),
            $attributes ?? AttributeCollection::empty(),
            $sequence ?? ArtifactSequence::initial()
        );
    }

    private static function assertFinite(float $value): void
    {
        if (!is_finite($value)) {
            throw InvalidMeasurementValueException::forNonFiniteValue();
        }
    }

    public function name(): MeasurementNameInterface
    {
        return $this->name;
    }

    public function kind(): MeasurementKindInterface
    {
        return $this->kind;
    }

    public function unit(): MeasurementUnitInterface
    {
        return $this->unit;
    }

    public function value(): int|float
    {
        return $this->value;
    }

    public function executionId(): ExecutionIdInterface
    {
        return $this->executionId;
    }

    public function activityId(): ?StringIdentifierInterface
    {
        return $this->activityId;
    }

    public function timestamp(): int
    {
        return $this->instant->epochSeconds();
    }

    public function sequence(): int
    {
        return $this->sequence->value();
    }

    public function attributes(): array
    {
        return $this->attributes->toArray();
    }

    public function normalize(): array
    {
        return [
            ArtifactField::NAME => $this->name->toString(),
            ArtifactField::KIND => $this->kind->label(),
            ArtifactField::UNIT => $this->unit->label(),
            ArtifactField::VALUE => $this->value,
            ArtifactField::EXECUTION_ID => $this->executionId->toString(),
            ArtifactField::ACTIVITY_ID => $this->activityId?->toString(),
            ArtifactField::TIMESTAMP => $this->instant->epochSeconds(),
            ArtifactField::SEQUENCE => $this->sequence->value(),
            ArtifactField::ATTRIBUTES => $this->attributes->toArray(),
        ];
    }
}
