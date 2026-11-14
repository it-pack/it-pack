<?php

declare(strict_types=1);

namespace ItPack\RunIt\Application\Publication;

use ItPack\RunIt\Behavior\Application\Publication\DelegatesPublisherLifecycleBehavior;
use ItPack\RunIt\Behavior\Application\Publication\DropsWithHealthReport;
use ItPack\RunIt\Contract\Application\Publication\InternalHealthReporterInterface;
use ItPack\RunIt\Contract\Domain\ExecutionArtifactInterface;
use ItPack\RunIt\Contract\Domain\MeasurementInterface;
use ItPack\RunIt\Contract\Domain\TypedExecutionArtifactInterface;
use ItPack\RunIt\Contract\Port\ArtifactPublisherInterface;
use ItPack\RunIt\Contract\Port\CloseablePublisherInterface;
use ItPack\RunIt\Contract\Port\FlushablePublisherInterface;
use ItPack\RunIt\Domain\Activity\ActivityId;
use ItPack\RunIt\Domain\Execution\ExecutionId;
use ItPack\RunIt\Domain\Measurement\Measurement;
use ItPack\RunIt\Domain\Measurement\MeasurementKind;
use ItPack\RunIt\Domain\Measurement\MeasurementName;
use ItPack\RunIt\Domain\Measurement\MeasurementUnit;
use ItPack\RunIt\Domain\Shared\ArtifactField;
use ItPack\RunIt\Domain\Shared\ArtifactSequence;
use ItPack\RunIt\Domain\Shared\AttributeCollection;
use ItPack\RunIt\Domain\Shared\Instant;
use Throwable;

/** @internal Classifies verified measurements before sampling. */
final class DefaultMeasurementClassifier implements
    ArtifactPublisherInterface,
    FlushablePublisherInterface,
    CloseablePublisherInterface
{
    use DelegatesPublisherLifecycleBehavior;
    use DropsWithHealthReport;

    /** Approved measurement name prefixes established from existing SDK/dogfood usage. */
    private const array APPROVED_NAME_PREFIXES = ['runit.'];

    /** Approved specific measurement names from existing test/code usage. */
    private const array APPROVED_SPECIFIC_NAMES = ['dogfood-metric', 'limit.test', 'secret-metric'];

    public function __construct(
        private readonly ArtifactPublisherInterface $inner,
        private readonly ?InternalHealthReporterInterface $health,
    ) {
    }

    public function publish(ExecutionArtifactInterface $artifact): void
    {
        if (!$this->isMeasurement($artifact)) {
            $this->inner->publish($artifact);

            return;
        }

        $fields = $artifact->normalize();
        $measurement = $this->classify($fields);

        if (!$measurement instanceof MeasurementInterface) {
            $this->reportDrop('Default measurement classification failed.');

            return;
        }

        $this->inner->publish($measurement);
    }

    private function isMeasurement(ExecutionArtifactInterface $artifact): bool
    {
        return $artifact instanceof MeasurementInterface
            || ($artifact instanceof TypedExecutionArtifactInterface && $artifact->artifactType() === 'measurement');
    }

    /**
     * @param array<string, mixed> $fields
     */
    private function classify(array $fields): ?MeasurementInterface
    {
        $required = [
            ArtifactField::NAME,
            ArtifactField::KIND,
            ArtifactField::UNIT,
            ArtifactField::VALUE,
            ArtifactField::EXECUTION_ID,
            ArtifactField::ACTIVITY_ID,
            ArtifactField::TIMESTAMP,
            ArtifactField::SEQUENCE,
            ArtifactField::ATTRIBUTES,
        ];

        if (array_any($required, fn (string $key): bool => !array_key_exists($key, $fields))) {
            return null;
        }

        if (
            !is_string($fields[ArtifactField::NAME])
            || !is_string($fields[ArtifactField::KIND])
            || !is_string($fields[ArtifactField::UNIT])
            || !is_string($fields[ArtifactField::EXECUTION_ID])
            || !is_int($fields[ArtifactField::TIMESTAMP])
            || !is_int($fields[ArtifactField::SEQUENCE])
            || !is_array($fields[ArtifactField::ATTRIBUTES])
            || (!is_string($fields[ArtifactField::ACTIVITY_ID] ?? null)
                && $fields[ArtifactField::ACTIVITY_ID] !== null)
        ) {
            return null;
        }

        $kind = MeasurementKind::tryFrom($fields[ArtifactField::KIND]);
        $unit = MeasurementUnit::tryFrom($fields[ArtifactField::UNIT]);
        $value = $fields[ArtifactField::VALUE];

        if ($kind === null || $unit === null) {
            return null;
        }

        if (!$this->isApprovedMeasurementName($fields[ArtifactField::NAME])) {
            return null;
        }

        $isIntKind = in_array($kind, [MeasurementKind::Counter, MeasurementKind::Size], true);
        if ($isIntKind && !is_int($value)) {
            return null;
        }

        if (!$isIntKind && (!is_float($value) || !is_finite($value))) {
            return null;
        }

        /** @var array<string, mixed> $rawAttributes */
        $rawAttributes = $fields[ArtifactField::ATTRIBUTES];
        $attributes = $this->validateAttributes($rawAttributes);
        if ($attributes === null) {
            return null;
        }
        /** @var array<string, int|float|string|bool|null|array<int|string, mixed>> $attributes */

        $factory = match ($kind) {
            MeasurementKind::Counter => Measurement::counter(...),
            MeasurementKind::Size => Measurement::size(...),
            MeasurementKind::Gauge => Measurement::gauge(...),
            MeasurementKind::Duration => Measurement::duration(...),
            MeasurementKind::Ratio => Measurement::ratio(...),
        };

        try {
            $measurement = $factory(
                MeasurementName::fromString($fields[ArtifactField::NAME]),
                ExecutionId::fromString($fields[ArtifactField::EXECUTION_ID]),
                $value,
                $fields[ArtifactField::ACTIVITY_ID] === null
                    ? null
                    : ActivityId::fromString($fields[ArtifactField::ACTIVITY_ID]),
                Instant::of($fields[ArtifactField::TIMESTAMP]),
                ArtifactSequence::of($fields[ArtifactField::SEQUENCE]),
                AttributeCollection::fromArray($attributes),
                $unit,
            );

            $normalized = $measurement->normalize();
            ksort($normalized);
            ksort($fields);

            return $normalized === $fields ? $measurement : null;
        } catch (Throwable) {
            return null;
        }
    }

    private function isApprovedMeasurementName(string $name): bool
    {
        foreach (self::APPROVED_NAME_PREFIXES as $prefix) {
            if (str_starts_with($name, $prefix)) {
                return true;
            }
        }

        return in_array($name, self::APPROVED_SPECIFIC_NAMES, true);
    }

    private function isApprovedAttributeKey(mixed $key): bool
    {
        return is_string($key);
    }

    private function isApprovedAttributeValue(mixed $value): bool
    {
        return is_scalar($value) || is_array($value) || $value === null;
    }

    /**
     * @param array<string, mixed> $attributes
     * @return array<string, mixed>|null
     */
    private function validateAttributes(array $attributes): ?array
    {
        $result = [];
        foreach ($attributes as $key => $attribute) {
            if (!$this->isApprovedAttributeKey($key)) {
                return null;
            }
            if (!$this->isApprovedAttributeValue($attribute)) {
                return null;
            }
            $result[$key] = $attribute;
        }

        return $result;
    }
}
