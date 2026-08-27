<?php

declare(strict_types=1);

namespace ItPack\RunIt\Adapter\OpenTelemetry;

/**
 * Detects which OpenTelemetry signals the bridge can emit for a configuration.
 *
 * A signal is enabled only when its provider is present. This is the single
 * source of truth used by the bridge components to decide whether to create
 * spans, record metrics, or emit logs, keeping the "missing provider" behavior
 * consistent across the whole bridge.
 *
 * @see https://opentelemetry.io/
 */
final readonly class BridgeCapability
{
    public function __construct(
        public bool $trace,
        public bool $metrics,
        public bool $logs,
    ) {
    }

    public static function detect(BridgeConfig $config): self
    {
        return new self(
            $config->tracerProvider !== null,
            $config->meterProvider !== null,
            $config->loggerProvider !== null,
        );
    }

    public function any(): bool
    {
        return $this->trace || $this->metrics || $this->logs;
    }
}
