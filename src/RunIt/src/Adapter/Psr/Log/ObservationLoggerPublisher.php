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
    public function __construct(
        private LoggerInterface                        $logger,
        private ?RedactorInterface                     $redactor = null,
        private ?ObservationToPsr3LevelMapperInterface $levelMapper = null,
        private string                                 $exceptionKey = 'exception',
    ) {
    }

    public function publish(ExecutionArtifactInterface $artifact): void
    {
        $artifact = $this->redactor instanceof RedactorInterface
            ? $this->redactor->redact($artifact)
            : $artifact;

        $extracted = $this->extract($artifact);

        $context = $extracted['attributes'];

        foreach ($extracted['correlation'] as $key => $value) {
            if ($value !== null && !array_key_exists($key, $context)) {
                $context[$key] = $value;
            }
        }

        if ($extracted['throwable'] !== null && !array_key_exists($this->exceptionKey, $context)) {
            $context[$this->exceptionKey] = $extracted['throwable'];
        }

        $this->logger->log($extracted['level'], $extracted['message'], $context);
    }

    /**
     * @return array{message: string, level: string, attributes: array<array-key, mixed>, correlation: array<string, mixed>, throwable: mixed}
     */
    private function extract(ExecutionArtifactInterface $artifact): array
    {
        if ($artifact instanceof ObservationInterface) {
            return [
                'message' => $artifact->message(),
                'level' => $this->mapLevel($artifact->severity()->level()),
                'attributes' => $artifact->attributes(),
                'correlation' => [
                    ArtifactField::EXECUTION_ID => $artifact->executionId()->toString(),
                    ArtifactField::ACTIVITY_ID => $artifact->activityId()?->toString(),
                    'observation_id' => $artifact->id()->toString(),
                    ArtifactField::DIAGNOSTIC_CODE => $artifact->diagnosticCode(),
                    'sequence' => $artifact->sequence(),
                    'timestamp' => $artifact->timestamp(),
                    'severity' => $artifact->severity()->label(),
                ],
                'throwable' => $artifact->throwableDescription()?->toArray(),
            ];
        }

        $normalized = $artifact->normalize();

        $message = $normalized[ArtifactField::MESSAGE] ?? null;
        $message = is_string($message) ? $message : (is_string($normalized['kind'] ?? null) ? $normalized['kind'] : 'artifact');
        $level = $normalized['level'] ?? null;
        $level = is_int($level) ? $level : 200;
        $attributes = $normalized[ArtifactField::ATTRIBUTES] ?? null;
        $attributes = is_array($attributes) ? $attributes : [];

        return [
            'message' => $message,
            'level' => $this->mapLevel($level),
            'attributes' => $attributes,
            'correlation' => [
                ArtifactField::EXECUTION_ID => $normalized[ArtifactField::EXECUTION_ID] ?? null,
                ArtifactField::ACTIVITY_ID => $normalized[ArtifactField::ACTIVITY_ID] ?? null,
                'observation_id' => $normalized[ArtifactField::ID] ?? null,
                ArtifactField::DIAGNOSTIC_CODE => $normalized[ArtifactField::DIAGNOSTIC_CODE] ?? null,
                'sequence' => $normalized['sequence'] ?? null,
                'timestamp' => $normalized['timestamp'] ?? null,
                'severity' => $normalized['severity'] ?? null,
            ],
            'throwable' => $normalized[ArtifactField::THROWABLE] ?? null,
        ];
    }

    private function mapLevel(int $level): string
    {
        return ($this->levelMapper ?? new DefaultObservationToPsr3LevelMapper())->toLevelFromNumeric($level);
    }
}
