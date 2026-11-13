<?php

declare(strict_types=1);

namespace ItPack\RunIt\Adapter\OpenTelemetry\Logs;

use ItPack\RunIt\Adapter\OpenTelemetry\Behavior\LogEmitterBehavior;
use ItPack\RunIt\Adapter\OpenTelemetry\Contract\ObservationLogMapperInterface;
use ItPack\RunIt\Adapter\OpenTelemetry\Contract\RedactedObservationLogMapperInterface;
use ItPack\RunIt\Adapter\OpenTelemetry\Contract\SeverityMapInterface;
use ItPack\RunIt\Adapter\OpenTelemetry\Telemetry\AttributeKeys;
use ItPack\RunIt\Contract\Domain\ObservationInterface;
use ItPack\RunIt\Contract\Domain\StringIdentifierInterface;
use ItPack\RunIt\Contract\Domain\ThrowableDescriptionInterface;
use ItPack\RunIt\Domain\Observation\ObservationSeverity;
use ItPack\RunIt\Domain\Shared\ArtifactField;
use ItPack\RunIt\Domain\Shared\Duration;
use OpenTelemetry\API\Logs\LoggerProviderInterface;
use OpenTelemetry\API\Logs\Severity;
use OpenTelemetry\API\Trace\Span;
use OpenTelemetry\SemConv\Attributes\ExceptionAttributes;

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
    use LogEmitterBehavior;

    public function __construct(
        ?LoggerProviderInterface         $loggerProvider,
        private SeverityMapInterface     $severityMap,
        private bool                     $mapToSpanEvents = false,
    ) {
        $this->initLoggerProvider($loggerProvider);
    }

    public function map(ObservationInterface $observation): void
    {
        if (!$this->mapToSpanEvents && $this->loggerProvider() instanceof LoggerProviderInterface) {
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
            $attributes[ExceptionAttributes::EXCEPTION_TYPE] = $throwable->class();
            $attributes[ExceptionAttributes::EXCEPTION_MESSAGE] = $throwable->message();
            $trace = $throwable->trace();
            if (is_string($trace)) {
                $attributes[ExceptionAttributes::EXCEPTION_STACKTRACE] = $trace;
            }
        }

        $this->logRecordBuilder()
            ->setTimestamp($observation->timestamp() * Duration::NANOS_PER_SECOND)
            ->setSeverityNumber($this->severityMap->toNumber($observation->severity()))
            ->setSeverityText($this->severityMap->toText($observation->severity()))
            ->setBody($observation->message())
            ->setAttributes($attributes)
            ->emit()
        ;

        if ($this->mapToSpanEvents) {
            Span::getCurrent()->addEvent('runit.observation', $attributes);
        }
    }

    /**
     * @param array<string, mixed> $normalized
     */
    public function mapRedactedObservation(array $normalized): void
    {
        if (!$this->mapToSpanEvents) {
            return;
        }

        $message = match (true) {
            is_string($normalized[ArtifactField::MESSAGE] ?? null) => $normalized[ArtifactField::MESSAGE],
            is_string($normalized[ArtifactField::NAME] ?? null) => $normalized[ArtifactField::NAME],
            default => '',
        };

        $level = match (true) {
            is_int($normalized[ArtifactField::LEVEL] ?? null) => $normalized[ArtifactField::LEVEL],
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


        $this->logRecordBuilder()
            ->setTimestamp($timestamp * Duration::NANOS_PER_SECOND)
            ->setSeverityNumber($this->severityLevelToNumber($level))
            ->setSeverityText($this->severityLevelToText($level))
            ->setBody($message)
            ->setAttributes($otelAttributes)
            ->emit()
        ;


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

        if (is_string($throwable[ArtifactField::THROWABLE_CLASS] ?? null)) {
            $otelAttributes[ExceptionAttributes::EXCEPTION_TYPE] = $throwable[ArtifactField::THROWABLE_CLASS];
        }

        if (is_string($throwable[ArtifactField::MESSAGE] ?? null)) {
            $otelAttributes[ExceptionAttributes::EXCEPTION_MESSAGE] = $throwable[ArtifactField::MESSAGE];
        }

        if (is_string($throwable[ArtifactField::TRACE] ?? null)) {
            $otelAttributes[ExceptionAttributes::EXCEPTION_STACKTRACE] = $throwable[ArtifactField::TRACE];
        }
    }

    private function severityLevelToNumber(int $level): int
    {
        return match (true) {
            $level >= ObservationSeverity::Emergency->level() => Severity::FATAL3->value,
            $level >= ObservationSeverity::Alert->level() => Severity::FATAL2->value,
            $level >= ObservationSeverity::Critical->level() => Severity::FATAL->value,
            $level >= ObservationSeverity::Error->level() => Severity::ERROR->value,
            $level >= ObservationSeverity::Warning->level() => Severity::WARN->value,
            $level >= ObservationSeverity::Notice->level() => Severity::INFO2->value,
            $level >= ObservationSeverity::Info->level() => Severity::INFO->value,
            $level >= ObservationSeverity::Debug->level() => Severity::DEBUG->value,
            default => Severity::TRACE->value,
        };
    }

    private function severityLevelToText(int $level): string
    {
        return match (true) {
            $level >= ObservationSeverity::Emergency->level() => ObservationSeverity::Emergency->value,
            $level >= ObservationSeverity::Alert->level() => ObservationSeverity::Alert->value,
            $level >= ObservationSeverity::Critical->level() => ObservationSeverity::Critical->value,
            $level >= ObservationSeverity::Error->level() => ObservationSeverity::Error->value,
            $level >= ObservationSeverity::Warning->level() => ObservationSeverity::Warning->value,
            $level >= ObservationSeverity::Notice->level() => ObservationSeverity::Notice->value,
            $level >= ObservationSeverity::Info->level() => ObservationSeverity::Info->value,
            $level >= ObservationSeverity::Debug->level() => ObservationSeverity::Debug->value,
            default => ObservationSeverity::Trace->value,
        };
    }
}
