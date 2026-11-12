<?php

declare(strict_types=1);

namespace ItPack\RunIt\Adapter\OpenTelemetry\Logs;

use ItPack\RunIt\Adapter\OpenTelemetry\BridgeConfig;
use ItPack\RunIt\Adapter\OpenTelemetry\Contract\ObservationLogMapperInterface;
use ItPack\RunIt\Adapter\OpenTelemetry\Contract\RedactedObservationLogMapperInterface;
use ItPack\RunIt\Adapter\OpenTelemetry\Contract\SeverityMapInterface;
use ItPack\RunIt\Adapter\OpenTelemetry\Telemetry\AttributeKeys;
use ItPack\RunIt\Contract\Domain\ObservationInterface;
use ItPack\RunIt\Contract\Domain\StringIdentifierInterface;
use ItPack\RunIt\Contract\Domain\ThrowableDescriptionInterface;
use ItPack\RunIt\Domain\Shared\ArtifactField;
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
final readonly class DefaultObservationLogMapper implements
    ObservationLogMapperInterface,
    RedactedObservationLogMapperInterface
{
    public function __construct(
        private ?LoggerProviderInterface $loggerProvider,
        private SeverityMapInterface     $severityMap,
        private bool                     $mapToSpanEvents = false,
    ) {
    }

    public function map(ObservationInterface $observation): void
    {
        if (!$this->loggerProvider instanceof LoggerProviderInterface && !$this->mapToSpanEvents) {
            return;
        }

        $attributes = $this->buildObservationAttributes(
            $observation->id()->toString(),
            $observation->executionId()->toString(),
            $observation->activityId(),
            $observation->diagnosticCode(),
            $observation->attributes(),
        );

        $throwable = $observation->throwableDescription();
        if ($throwable instanceof ThrowableDescriptionInterface) {
            $attributes['exception.type'] = $throwable->class();
            $attributes['exception.message'] = $throwable->message();
            $trace = $throwable->trace();
            if ($trace !== null) {
                $attributes['exception.stacktrace'] = $trace;
            }
        }

        if ($this->loggerProvider instanceof LoggerProviderInterface) {
            $this->loggerProvider->getLogger(BridgeConfig::INSTRUMENTATION_SCOPE)
                ->logRecordBuilder()
                ->setTimestamp($observation->timestamp() * Duration::NANOS_PER_SECOND)
                ->setSeverityNumber($this->severityMap->toNumber($observation->severity()))
                ->setSeverityText($this->severityMap->toText($observation->severity()))
                ->setBody($observation->message())
                ->setAttributes($attributes)
                ->emit()
            ;
        }

        if ($this->mapToSpanEvents) {
            Span::getCurrent()->addEvent('runit.observation', $attributes);
        }
    }

    /**
     * @param array<string, mixed> $normalized
     */
    public function mapRedactedObservation(array $normalized): void
    {
        if (!$this->loggerProvider instanceof LoggerProviderInterface && !$this->mapToSpanEvents) {
            return;
        }

        $message = match (true) {
            is_string($normalized[ArtifactField::MESSAGE]) => $normalized[ArtifactField::MESSAGE],
            is_string($normalized[ArtifactField::NAME]) => $normalized[ArtifactField::NAME],
            default => '',
        };

        $level = match (true) {
            is_int($normalized[ArtifactField::LEVEL]) => $normalized[ArtifactField::LEVEL],
            is_int($normalized[ArtifactField::SEVERITY] ?? null) => $normalized[ArtifactField::SEVERITY],
            default => 0,
        };

        $timestamp = is_int($normalized[ArtifactField::TIMESTAMP] ?? null)
            ? $normalized[ArtifactField::TIMESTAMP]
            : 0;

        /** @var array<string, mixed> $otelAttributes */
        $otelAttributes = $this->buildRedactedObservationAttributes($normalized);

        // Free-form attributes are suppressed entirely in redacted observations: they
        // are caller-controlled and cannot be classified safe without redaction config.
        // Only the validated identity/correlation fields above are exported.

        $this->appendThrowableAttributes($otelAttributes, $normalized[ArtifactField::THROWABLE] ?? null);

        if ($this->loggerProvider instanceof LoggerProviderInterface) {
            $this->loggerProvider->getLogger(BridgeConfig::INSTRUMENTATION_SCOPE)
                ->logRecordBuilder()
                ->setTimestamp($timestamp * Duration::NANOS_PER_SECOND)
                ->setSeverityNumber($this->severityLevelToNumber($level))
                ->setSeverityText($this->severityLevelToText($level))
                ->setBody($message)
                ->setAttributes($otelAttributes)
                ->emit()
            ;
        }

        if ($this->mapToSpanEvents) {
            Span::getCurrent()->addEvent('runit.observation', $otelAttributes);
        }
    }

    /**
     * Builds the classified, safe attribute set for a redacted observation projection.
     *
     * Only identity and correlation fields that can be established safe are exported;
     * free-form attributes are suppressed entirely.
     *
     * @param array<string, mixed> $normalized
     * @return array<string, mixed>
     */
    private function buildRedactedObservationAttributes(array $normalized): array
    {
        /** @var array<string, mixed> $attributes */
        $attributes = [];

        $id = $normalized[ArtifactField::ID] ?? null;
        if (is_string($id)) {
            $attributes[AttributeKeys::OBSERVATION_ID] = $id;
        }

        $executionId = $normalized[ArtifactField::EXECUTION_ID] ?? null;
        if (is_string($executionId)) {
            $attributes[AttributeKeys::EXECUTION_ID] = $executionId;
        }

        $activityId = $normalized[ArtifactField::ACTIVITY_ID] ?? null;
        if (is_string($activityId)) {
            $attributes[AttributeKeys::ACTIVITY_ID] = $activityId;
        }

        $diagnosticCode = $normalized[ArtifactField::DIAGNOSTIC_CODE] ?? null;
        if (is_string($diagnosticCode)) {
            $attributes[AttributeKeys::DIAGNOSTIC_CODE] = $diagnosticCode;
        }

        return $attributes;
    }

    /**
     * @param iterable<mixed, mixed> $attributes
     * @return array<string, mixed>
     */
    private function buildObservationAttributes(
        string $observationId,
        string $executionId,
        mixed $activityId,
        mixed $diagnosticCode,
        iterable $attributes,
    ): array {
        $otelAttributes = [
            AttributeKeys::OBSERVATION_ID => $observationId,
            AttributeKeys::EXECUTION_ID => $executionId,
        ];

        if ($activityId instanceof StringIdentifierInterface) {
            $otelAttributes[AttributeKeys::ACTIVITY_ID] = $activityId->toString();
        }

        if (is_string($activityId)) {
            $otelAttributes[AttributeKeys::ACTIVITY_ID] = $activityId;
        }

        if ($diagnosticCode !== null) {
            $otelAttributes[AttributeKeys::DIAGNOSTIC_CODE] = $diagnosticCode;
        }

        foreach ($attributes as $key => $value) {
            if (!is_string($key)) {
                continue;
            }

            $otelAttributes[$key] = $value;
        }

        return $otelAttributes;
    }

    /**
     * @param array<string, mixed> $otelAttributes
     */
    private function appendThrowableAttributes(array &$otelAttributes, mixed $throwable): void
    {
        if (!is_array($throwable)) {
            return;
        }

        $throwableClass = $throwable[ArtifactField::THROWABLE_CLASS] ?? null;
        $throwableMessage = $throwable[ArtifactField::MESSAGE] ?? null;
        if (is_string($throwableClass)) {
            $otelAttributes['exception.type'] = $throwableClass;
        }

        if (is_string($throwableMessage)) {
            $otelAttributes['exception.message'] = $throwableMessage;
        }

        $trace = $throwable[ArtifactField::TRACE] ?? null;
        if (is_string($trace) && $trace !== '') {
            $otelAttributes['exception.stacktrace'] = $trace;
        }
    }

    private function severityLevelToNumber(int $level): int
    {
        return match (true) {
            $level >= 600 => 23,
            $level >= 550 => 22,
            $level >= 500 => 21,
            $level >= 400 => 17,
            $level >= 300 => 13,
            $level >= 250 => 10,
            $level >= 200 => 9,
            $level >= 100 => 5,
            default => 1,
        };
    }

    private function severityLevelToText(int $level): string
    {
        return match (true) {
            $level >= 600 => 'emergency',
            $level >= 550 => 'alert',
            $level >= 500 => 'critical',
            $level >= 400 => 'error',
            $level >= 300 => 'warning',
            $level >= 250 => 'notice',
            $level >= 200 => 'info',
            $level >= 100 => 'debug',
            default => 'trace',
        };
    }
}
