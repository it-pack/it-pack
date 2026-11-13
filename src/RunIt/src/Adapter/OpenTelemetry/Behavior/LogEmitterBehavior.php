<?php

declare(strict_types=1);

namespace ItPack\RunIt\Adapter\OpenTelemetry\Behavior;

use ItPack\RunIt\Adapter\OpenTelemetry\BridgeConfig;
use ItPack\RunIt\Domain\Observation\ObservationSeverity;
use OpenTelemetry\API\Logs\LoggerInterface;
use OpenTelemetry\API\Logs\LoggerProviderInterface;
use OpenTelemetry\API\Logs\LogRecordBuilderInterface;
use OpenTelemetry\API\Logs\NoopLoggerProvider;
use OpenTelemetry\API\Logs\Severity;

trait LogEmitterBehavior
{
    private readonly ?LoggerProviderInterface $loggerProvider;

    private function loggerProvider(): ?LoggerProviderInterface
    {
        return $this->loggerProvider;
    }

    /**
     * @param iterable<string, mixed> $attributes instrumentation scope attributes
     */
    private function logger(
        string $name = BridgeConfig::INSTRUMENTATION_SCOPE,
        ?string $version = null,
        ?string $schemaUrl = null,
        iterable $attributes = [],
    ): LoggerInterface {

        return ($this->loggerProvider() ?? new NoopLoggerProvider())
            ->getLogger($name, $version, $schemaUrl, $attributes)
        ;
    }

    /**
     * @param iterable<string, mixed> $attributes instrumentation scope attributes
     */
    private function logRecordBuilder(
        string $name = BridgeConfig::INSTRUMENTATION_SCOPE,
        ?string $version = null,
        ?string $schemaUrl = null,
        iterable $attributes = [],
    ): LogRecordBuilderInterface {

        return $this->logger($name, $version, $schemaUrl, $attributes)
            ->logRecordBuilder()
        ;
    }

    /**
     * @param iterable<string, mixed> $attributes instrumentation scope attributes
     */
    private function infoLogRecordBuilder(
        string $name = BridgeConfig::INSTRUMENTATION_SCOPE,
        ?string $version = null,
        ?string $schemaUrl = null,
        iterable $attributes = [],
    ): LogRecordBuilderInterface {

        return $this->logRecordBuilder($name, $version, $schemaUrl, $attributes)
            ->setSeverityNumber(Severity::INFO)
            ->setSeverityText(ObservationSeverity::Info->value)
        ;
    }
}
