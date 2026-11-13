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
    readonly private ?LoggerProviderInterface $loggerProvider;

    private function loggerProvider(): LoggerProviderInterface
    {
        return $this->loggerProvider;
    }

    private function initLoggerProvider(?LoggerProviderInterface $loggerProvider): void
    {
        $this->loggerProvider = $loggerProvider;

        if ($this->loggerProvider instanceof NoopLoggerProvider) {
            throw new \RuntimeException(
                'noop logger provider'
            );
        }
    }

    private function logger(
        string $name = BridgeConfig::INSTRUMENTATION_SCOPE,
        ?string $version = null,
        ?string $schemaUrl = null,
        iterable $attributes = [], //instrumentation scope attributes
    ): LoggerInterface {

        return $this->loggerProvider()->getLogger($name, $version, $schemaUrl, $attributes);
    }

    private function logRecordBuilder(
        string $name = BridgeConfig::INSTRUMENTATION_SCOPE,
        ?string $version = null,
        ?string $schemaUrl = null,
        iterable $attributes = [], //instrumentation scope attributes
    ): LogRecordBuilderInterface {

        return $this->logger($name, $version, $schemaUrl, $attributes)
            ->logRecordBuilder();
    }

    private function infoLogRecordBuilder(
        string $name = BridgeConfig::INSTRUMENTATION_SCOPE,
        ?string $version = null,
        ?string $schemaUrl = null,
        iterable $attributes = [], //instrumentation scope attributes
    ): LogRecordBuilderInterface {

        return $this->logRecordBuilder($name, $version, $schemaUrl, $attributes)
            ->setSeverityNumber(Severity::INFO)
            ->setSeverityText(ObservationSeverity::Info->value);
    }
}
