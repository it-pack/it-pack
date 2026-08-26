<?php

declare(strict_types=1);

namespace ItPack\RunIt\Adapter\OpenTelemetry\Telemetry;

use ItPack\RunIt\Adapter\OpenTelemetry\Contract\SeverityMapInterface;
use ItPack\RunIt\Contract\Domain\ObservationSeverityInterface;
use OpenTelemetry\API\Logs\Severity;

/**
 * Maps RunIt observation severities to OpenTelemetry log severities.
 *
 * RunIt severities extend the PSR-3 scale with a `trace` level below `debug`,
 * so the bridge maps `trace` to the OpenTelemetry `TRACE` band and keeps the
 * remaining severities aligned with the OpenTelemetry severity number scale.
 *
 * @see https://w3id.org/itpack/runit#ObservationSeverity
 */
final class SeverityMap implements SeverityMapInterface
{
    public function toSeverity(ObservationSeverityInterface $severity): Severity
    {
        return match ($severity->label()) {
            'trace' => Severity::TRACE,
            'debug' => Severity::DEBUG,
            'notice' => Severity::INFO2,
            'warning' => Severity::WARN,
            'error' => Severity::ERROR,
            'critical' => Severity::FATAL,
            'alert' => Severity::FATAL2,
            'emergency' => Severity::FATAL3,
            default => Severity::INFO,
        };
    }

    public function toNumber(ObservationSeverityInterface $severity): int
    {
        return $this->toSeverity($severity)->value;
    }

    public function toText(ObservationSeverityInterface $severity): string
    {
        return $severity->label();
    }
}
