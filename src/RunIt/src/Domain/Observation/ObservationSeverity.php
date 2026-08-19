<?php

declare(strict_types=1);

namespace ItPack\RunIt\Domain\Observation;

use ItPack\RunIt\Contract\Domain\ObservationSeverityInterface;

/**
 * The closed set of observation severities.
 *
 * Severities align with the PSR-3 log levels so observations integrate with existing
 * logging without redefining the scale. The vocabulary extends PSR-3 with a `trace`
 * severity (below `debug`) for fine-grained diagnostics.
 *
 * @api
 *
 * @see https://w3id.org/itpack/runit#ObservationSeverity
 */
enum ObservationSeverity: string implements ObservationSeverityInterface
{
    case Trace = 'trace';
    case Debug = 'debug';
    case Info = 'info';
    case Notice = 'notice';
    case Warning = 'warning';
    case Error = 'error';
    case Critical = 'critical';
    case Alert = 'alert';
    case Emergency = 'emergency';

    public function name(): string
    {
        return $this->value;
    }

    public function level(): int
    {
        return match ($this) {
            self::Trace => 50,
            self::Debug => 100,
            self::Info => 200,
            self::Notice => 250,
            self::Warning => 300,
            self::Error => 400,
            self::Critical => 500,
            self::Alert => 550,
            self::Emergency => 600,
        };
    }

    public function isError(): bool
    {
        return $this->level() >= self::Error->level();
    }
}
