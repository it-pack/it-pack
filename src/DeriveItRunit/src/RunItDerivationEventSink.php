<?php

declare(strict_types=1);

namespace ItPack\DeriveItRunit;

use ItPack\DeriveIt\Contract\Telemetry\DeriveEventSinkInterface;
use ItPack\DeriveIt\Contract\Telemetry\DeriveObservationSeverity;
use ItPack\DeriveIt\Contract\Telemetry\DeriveUnits;
use ItPack\RunIt\Contract\Application\ExecutionScopeInterface;
use ItPack\RunIt\Domain\Measurement\MeasurementName;
use ItPack\RunIt\Domain\Measurement\MeasurementUnit;
use ItPack\RunIt\Domain\Observation\ObservationSeverity;
use ItPack\RunIt\Domain\Occurrence\OccurrenceType;
use ItPack\RunIt\Domain\Shared\AttributeCollection;

/**
 * Bridges DeriveIt derivation telemetry onto a RunIt execution
 * (CONSOLIDATION_PLAN.md §5.4 / C4).
 *
 * Every emitted fact is forwarded to the ambient execution's recorders, which
 * fill ownership, timing, and sequence automatically — this sink therefore
 * transports facts only and holds no identity state of its own:
 *
 * | DeriveIt fact            | RunIt artifact                                        |
 * |--------------------------|-------------------------------------------------------|
 * | activityStarted/Finished | occurrence `deriveit.activity.{started,finished}.v1`  |
 * | measured                 | measurement (`count`→counter, `By`→size, `ms`→duration,
 *     `%`→ratio, else gauge), name prefixed `deriveit.` |
 * | observed                 | observation with the mapped severity                  |
 * | occurred                 | occurrence `deriveit.<type>.v1`                       |
 *
 * Occurrence types are namespaced + versioned per RunIt's CloudEvents rules.
 */
final readonly class RunItDerivationEventSink implements DeriveEventSinkInterface
{
    /**
     * Namespace prefix applied to every forwarded fact so derivations are
     * attributable in mixed-workload executions.
     */
    private const FACT_PREFIX = 'deriveit.';
    private const OCCURRENCE_VERSION = '.v1';
    private const SOURCE = 'it-pack/derive-it';

    public function __construct(
        private ExecutionScopeInterface $scope,
    ) {
    }

    public function activityStarted(string $name, array $attributes = []): void
    {
        $this->occur('activity.started', $attributes + ['name' => $name]);
    }

    public function activityFinished(string $name, bool $successful = true, array $attributes = []): void
    {
        $this->occur('activity.finished', $attributes + ['name' => $name, 'successful' => $successful]);
    }

    public function measured(string $name, int|float $value, string $unit, array $attributes = []): void
    {
        $measurementName = MeasurementName::fromString(self::FACT_PREFIX . $name);
        $collection = $this->attributes($attributes);

        match ($unit) {
            DeriveUnits::UNIT_COUNT => $this->scope->measurements()->counter(
                $measurementName,
                (int) $value,
                MeasurementUnit::Count,
                $collection,
            ),
            DeriveUnits::UNIT_BYTES => $this->scope->measurements()->size(
                $measurementName,
                (int) $value,
                MeasurementUnit::Bytes,
                $collection,
            ),
            DeriveUnits::UNIT_MILLISECONDS => $this->scope->measurements()->duration(
                $measurementName,
                (float) $value,
                MeasurementUnit::Milliseconds,
                $collection,
            ),
            DeriveUnits::UNIT_PERCENT => $this->scope->measurements()->ratio(
                $measurementName,
                (float) $value,
                MeasurementUnit::Percent,
                $collection,
            ),
            default => $this->scope->measurements()->gauge($measurementName, (float) $value, null, $collection),
        };
    }

    public function observed(string $message, DeriveObservationSeverity $severity, array $attributes = []): void
    {
        $this->scope->observations()->record(
            $message,
            self::severity($severity),
            $this->attributes($attributes),
        );
    }

    public function occurred(string $type, array $attributes = []): void
    {
        $this->occur($type, $attributes);
    }

    /**
     * Records an occurrence under the namespaced, versioned type.
     *
     * @param array<string, string|int|float|bool|null> $attributes
     */
    private function occur(string $type, array $attributes): void
    {
        $subject = isset($attributes['target']) && is_string($attributes['target']) ? $attributes['target'] : null;

        $this->scope->occurrences()->record(
            OccurrenceType::fromString(self::FACT_PREFIX . $type . self::OCCURRENCE_VERSION),
            self::SOURCE,
            $attributes === [] ? null : $attributes,
            $subject,
        );
    }

    /**
     * @param array<string, string|int|float|bool|null> $attributes
     */
    private function attributes(array $attributes): AttributeCollection
    {
        return $attributes === [] ? AttributeCollection::empty() : AttributeCollection::fromArray($attributes);
    }

    private static function severity(DeriveObservationSeverity $severity): ObservationSeverity
    {
        return match ($severity) {
            DeriveObservationSeverity::DEBUG => ObservationSeverity::Debug,
            DeriveObservationSeverity::INFO => ObservationSeverity::Info,
            DeriveObservationSeverity::WARNING => ObservationSeverity::Warning,
            DeriveObservationSeverity::ERROR => ObservationSeverity::Error,
        };
    }
}
