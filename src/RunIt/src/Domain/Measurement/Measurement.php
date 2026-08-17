<?php

declare(strict_types=1);

namespace ItPack\RunIt\Domain\Measurement;

use ItPack\RunIt\Contract\Domain\ActivityIdInterface;
use ItPack\RunIt\Contract\Domain\ArtifactSequenceInterface;
use ItPack\RunIt\Contract\Domain\AttributeCollectionInterface;
use ItPack\RunIt\Contract\Domain\ExecutionIdInterface;
use ItPack\RunIt\Contract\Domain\InstantInterface;
use ItPack\RunIt\Contract\Domain\MeasurementInterface;
use ItPack\RunIt\Contract\Domain\MeasurementKindInterface;
use ItPack\RunIt\Contract\Domain\MeasurementNameInterface;
use ItPack\RunIt\Contract\Domain\MeasurementUnitInterface;
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
 * @see https://w3id.org/itpack/runit#measurement
 */
final readonly class Measurement implements MeasurementInterface
{
    /**
     * @param int|float $value
     */
    private function __construct(
        private readonly MeasurementNameInterface $name,
        private readonly MeasurementKindInterface $kind,
        private readonly MeasurementUnitInterface $unit,
        private readonly int|float $value,
        private readonly ExecutionIdInterface $executionId,
        private readonly ?ActivityIdInterface $activityId,
        private readonly InstantInterface $instant,
        private readonly AttributeCollectionInterface $attributes,
        private readonly ArtifactSequenceInterface $sequence,
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
        return new self($name, MeasurementKind::Counter, $unit, $value, $executionId, $activityId, $instant ?? Instant::now(), $attributes ?? AttributeCollection::empty(), $sequence ?? ArtifactSequence::initial());
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
        self::assertFinite($value);

        return new self($name, MeasurementKind::Gauge, $unit, $value, $executionId, $activityId, $instant ?? Instant::now(), $attributes ?? AttributeCollection::empty(), $sequence ?? ArtifactSequence::initial());
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
        self::assertFinite($value);

        return new self($name, MeasurementKind::Duration, $unit, $value, $executionId, $activityId, $instant ?? Instant::now(), $attributes ?? AttributeCollection::empty(), $sequence ?? ArtifactSequence::initial());
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
        return new self($name, MeasurementKind::Size, $unit, $value, $executionId, $activityId, $instant ?? Instant::now(), $attributes ?? AttributeCollection::empty(), $sequence ?? ArtifactSequence::initial());
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
        self::assertFinite($value);

        return new self($name, MeasurementKind::Ratio, $unit, $value, $executionId, $activityId, $instant ?? Instant::now(), $attributes ?? AttributeCollection::empty(), $sequence ?? ArtifactSequence::initial());
    }

    private static function assertFinite(float $value): void
    {
        if (!is_finite($value)) {
            throw new \InvalidArgumentException('Measurement value must be finite.');
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

    public function executionId(): \ItPack\RunIt\Contract\Domain\StringIdentifierInterface
    {
        return $this->executionId;
    }

    public function activityId(): ?\ItPack\RunIt\Contract\Domain\StringIdentifierInterface
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

    public function count(): int
    {
        return $this->sequence->value();
    }

    /**
     * @return array<string, int|float|string|bool|null|array<int|string, int|float|string|bool|null>>
     */
    public function attributes(): array
    {
        return $this->attributes->toArray();
    }

    /**
     * @return array<string, mixed>
     */
    public function normalize(): array
    {
        return [
            'name' => $this->name->toString(),
            'kind' => $this->kind->name(),
            'unit' => $this->unit->name(),
            'value' => $this->value,
            'execution_id' => $this->executionId->toString(),
            'activity_id' => $this->activityId?->toString(),
            'timestamp' => $this->instant->epochSeconds(),
            'sequence' => $this->sequence->value(),
            'attributes' => $this->attributes->toArray(),
        ];
    }
}
