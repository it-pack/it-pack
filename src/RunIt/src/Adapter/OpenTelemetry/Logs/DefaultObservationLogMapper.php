<?php

declare(strict_types=1);

namespace ItPack\RunIt\Adapter\OpenTelemetry\Logs;

use ItPack\RunIt\Adapter\OpenTelemetry\BridgeConfig;
use ItPack\RunIt\Adapter\OpenTelemetry\Contract\ObservationLogMapperInterface;
use ItPack\RunIt\Adapter\OpenTelemetry\Telemetry\AttributeKeys;
use ItPack\RunIt\Adapter\OpenTelemetry\Telemetry\SeverityMap;
use ItPack\RunIt\Contract\Domain\ObservationInterface;
use ItPack\RunIt\Domain\Shared\Duration;
use OpenTelemetry\API\Logs\LoggerProviderInterface;
use OpenTelemetry\API\Trace\Span;

/**
 * Maps a RunIt observation to an OpenTelemetry log record.
 *
 * Severity, message template, and approved attributes are preserved, and the
 * observation is correlated with its execution (and activity, when present). A
 * throwable is recorded under the OpenTelemetry exception attribute convention
 * only when the observation actually carries one. Observations can also be
 * mirrored as span events on the currently active span when enabled.
 *
 * @see https://w3id.org/itpack/runit#Observation
 */
final readonly class DefaultObservationLogMapper implements ObservationLogMapperInterface
{
    public function __construct(
        private ?LoggerProviderInterface $loggerProvider,
        private SeverityMap              $severityMap,
        private bool                     $mapToSpanEvents = false,
    ) {
    }

    public function map(ObservationInterface $observation): void
    {
        if ($this->loggerProvider === null) {
            return;
        }

        $logger = $this->loggerProvider->getLogger(BridgeConfig::INSTRUMENTATION_SCOPE);

        $builder = $logger
            ->logRecordBuilder()
            ->setTimestamp($observation->timestamp() * Duration::NANOS_PER_SECOND)
            ->setSeverityNumber($this->severityMap->toNumber($observation->severity()))
            ->setSeverityText($this->severityMap->toText($observation->severity()))
            ->setBody($observation->message())
        ;

        /** @var array<string, int|float|string|bool|null|array<int|string, int|float|string|bool|null>> $attributes */
        $attributes = [
            AttributeKeys::OBSERVATION_ID => $observation->id()->toString(),
            AttributeKeys::EXECUTION_ID => $observation->executionId()->toString(),
        ];

        $activityId = $observation->activityId();
        if ($activityId !== null) {
            $attributes[AttributeKeys::ACTIVITY_ID] = $activityId->toString();
        }

        $diagnosticCode = $observation->diagnosticCode();
        if ($diagnosticCode !== null) {
            $attributes[AttributeKeys::DIAGNOSTIC_CODE] = $diagnosticCode;
        }

        foreach ($observation->attributes() as $key => $value) {
            $attributes[$key] = $value;
        }

        $throwable = $observation->throwableDescription();
        if ($throwable !== null) {
            $attributes['exception.type'] = $throwable->class();
            $attributes['exception.message'] = $throwable->message();
            $trace = $throwable->trace();
            if ($trace !== null) {
                $attributes['exception.stacktrace'] = $trace;
            }
        }

        $builder->setAttributes($attributes);
        $builder->emit();

        if ($this->mapToSpanEvents) {
            Span::getCurrent()->addEvent('runit.observation', $attributes);
        }
    }
}
