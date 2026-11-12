<?php

declare(strict_types=1);

namespace ItPack\RunIt\Adapter\Psr\Log;

use ItPack\RunIt\Adapter\Psr\Contract\ObservationToPsr3LevelMapperInterface;
use ItPack\RunIt\Contract\Application\Publication\RedactorInterface;
use ItPack\RunIt\Contract\Domain\ExecutionArtifactInterface;
use ItPack\RunIt\Contract\Domain\ObservationInterface;
use ItPack\RunIt\Contract\Port\ArtifactPublisherInterface;
use ItPack\RunIt\Domain\Shared\ArtifactField;
use Psr\Log\LoggerInterface;

/**
 * Publishes RunIt observations to a PSR-3 logger.
 *
 * The publisher maps a RunIt observation to a PSR-3 log record: the stable
 * message template becomes the log message, the observation severity becomes the
 * PSR-3 level, the observation attributes become logger context, and the safe
 * throwable description is placed under a configurable context key (by default
 * `exception`). Execution and activity correlation identifiers are added to the
 * context so log lines stay joinable with executions and activities.
 *
 * Redaction is applied before logging when a {@see RedactorInterface} is
 * supplied. When this publisher is used as the sink of a RunIt publication
 * pipeline, the pipeline already redacts upstream and hands a redacted artifact;
 * supplying a redactor here is only required when the publisher is used
 * standalone. The original artifact is never mutated.
 *
 * @api
 *
 * @see https://w3id.org/itpack/runit#Observation
 */
final readonly class ObservationLoggerPublisher implements ArtifactPublisherInterface
{
    private const string KEY_ARTIFACT = 'artifact';

    private const int DEFAULT_LEVEL = 200;

    public function __construct(
        private LoggerInterface                        $logger,
        private ?RedactorInterface                     $redactor = null,
        private ObservationToPsr3LevelMapperInterface  $levelMapper = new DefaultObservationToPsr3LevelMapper(),
        private string                                 $exceptionKey = ArtifactField::EXCEPTION,
    ) {
    }

    public function publish(ExecutionArtifactInterface $artifact): void
    {
        $artifact = $this->redactor instanceof RedactorInterface
            ? $this->redactor->redact($artifact)
            : $artifact;

        $extracted = $this->extract($artifact);

        $context = $extracted[ArtifactField::ATTRIBUTES];

        foreach ($extracted[ArtifactField::CORRELATION] as $key => $value) {
            unset($context[$key]);
            if ($value !== null) {
                $context[$key] = $value;
            }
        }

        if (!array_key_exists($this->exceptionKey, $extracted[ArtifactField::CORRELATION])) {
            unset($context[$this->exceptionKey]);
            if ($extracted[ArtifactField::THROWABLE] !== null) {
                $context[$this->exceptionKey] = $extracted[ArtifactField::THROWABLE];
            }
        }

        $this->logger->log($extracted[ArtifactField::LEVEL], $extracted[ArtifactField::MESSAGE], $context);
    }

    /**
     * @return array{message: string, level: string, attributes: array<array-key, mixed>, correlation: array<string, mixed>, throwable: mixed}
     */
    private function extract(ExecutionArtifactInterface $artifact): array
    {
        return $artifact instanceof ObservationInterface
            ? $this->extractObservation($artifact)
            : $this->extractDefault($artifact);
    }

    private function mapLevel(int $level): string
    {
        return $this->levelMapper->toLevelFromNumeric($level);
    }

    /**
     * @return array{message: string, level: string, attributes: array<array-key, mixed>, correlation: array<string, mixed>, throwable: mixed}
     */
    private function extractObservation(ObservationInterface $artifact): array
    {
        return [
            ArtifactField::MESSAGE => $artifact->message(),
            ArtifactField::LEVEL => $this->mapLevel($artifact->severity()->level()),
            ArtifactField::ATTRIBUTES => $artifact->attributes(),
            ArtifactField::CORRELATION => [
                ArtifactField::EXECUTION_ID => $artifact->executionId()->toString(),
                ArtifactField::ACTIVITY_ID => $artifact->activityId()?->toString(),
                ArtifactField::OBSERVATION_ID => $artifact->id()->toString(),
                ArtifactField::DIAGNOSTIC_CODE => $artifact->diagnosticCode(),
                ArtifactField::SEQUENCE => $artifact->sequence(),
                ArtifactField::TIMESTAMP => $artifact->timestamp(),
                ArtifactField::SEVERITY => $artifact->severity()->label(),
            ],
            ArtifactField::THROWABLE => $artifact->throwableDescription()?->toArray(),
        ];
    }

    /**
     * @return array{message: string, level: string, attributes: array<array-key, mixed>, correlation: array<string, mixed>, throwable: mixed}
     */
    private function extractDefault(ExecutionArtifactInterface $artifact): array
    {
        $normalized = $artifact->normalize();

        $message = match (true) {
            is_string($normalized[ArtifactField::MESSAGE] ?? null) => $normalized[ArtifactField::MESSAGE],
            is_string($normalized[ArtifactField::KIND] ?? null) => $normalized[ArtifactField::KIND],
            default => self::KEY_ARTIFACT,
        };

        $level = is_int($normalized[ArtifactField::LEVEL] ?? null)
            ? $normalized[ArtifactField::LEVEL]
            : self::DEFAULT_LEVEL;

        $attributes = is_array($normalized[ArtifactField::ATTRIBUTES] ?? null)
            ? $normalized[ArtifactField::ATTRIBUTES]
            : [];

        return [
            ArtifactField::MESSAGE => $message,
            ArtifactField::LEVEL => $this->mapLevel($level),
            ArtifactField::ATTRIBUTES => $attributes,
            ArtifactField::CORRELATION => [
                ArtifactField::EXECUTION_ID => $normalized[ArtifactField::EXECUTION_ID] ?? null,
                ArtifactField::ACTIVITY_ID => $normalized[ArtifactField::ACTIVITY_ID] ?? null,
                ArtifactField::OBSERVATION_ID => $normalized[ArtifactField::ID] ?? null,
                ArtifactField::DIAGNOSTIC_CODE => $normalized[ArtifactField::DIAGNOSTIC_CODE] ?? null,
                ArtifactField::SEQUENCE => $normalized[ArtifactField::SEQUENCE] ?? null,
                ArtifactField::TIMESTAMP => $normalized[ArtifactField::TIMESTAMP] ?? null,
                ArtifactField::SEVERITY => $normalized[ArtifactField::SEVERITY] ?? null,
            ],
            ArtifactField::THROWABLE => $normalized[ArtifactField::THROWABLE] ?? null,
        ];
    }
}
