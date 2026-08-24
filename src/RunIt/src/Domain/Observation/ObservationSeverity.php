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
    /**
     * PSR-3/monolog-compatible numeric levels; `trace` extends below `debug`.
     */
    private const int LEVEL_TRACE = 50;
    private const int LEVEL_DEBUG = 100;
    private const int LEVEL_INFO = 200;
    private const int LEVEL_NOTICE = 250;
    private const int LEVEL_WARNING = 300;
    private const int LEVEL_ERROR = 400;
    private const int LEVEL_CRITICAL = 500;
    private const int LEVEL_ALERT = 550;
    private const int LEVEL_EMERGENCY = 600;

    case Trace = 'trace';
    case Debug = 'debug';
    case Info = 'info';
    case Notice = 'notice';
    case Warning = 'warning';
    case Error = 'error';
    case Critical = 'critical';
    case Alert = 'alert';
    case Emergency = 'emergency';

    public function label(): string
    {
        return $this->value;
    }

    public function level(): int
    {
        return match ($this) {
            self::Trace => self::LEVEL_TRACE,
            self::Debug => self::LEVEL_DEBUG,
            self::Info => self::LEVEL_INFO,
            self::Notice => self::LEVEL_NOTICE,
            self::Warning => self::LEVEL_WARNING,
            self::Error => self::LEVEL_ERROR,
            self::Critical => self::LEVEL_CRITICAL,
            self::Alert => self::LEVEL_ALERT,
            self::Emergency => self::LEVEL_EMERGENCY,
        };
    }

    public function isError(): bool
    {
        return $this->level() >= self::Error->level();
    }
}
