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
    public const int LEVEL_TRACE = 50;

    public const int LEVEL_DEBUG = 100;

    public const int LEVEL_INFO = 200;

    public const int LEVEL_NOTICE = 250;

    public const int LEVEL_WARNING = 300;

    public const int LEVEL_ERROR = 400;

    public const int LEVEL_CRITICAL = 500;

    public const int LEVEL_ALERT = 550;

    public const int LEVEL_EMERGENCY = 600;

    /**
     * PSR-3/monolog-compatible numeric levels; `trace` extends below `debug`.
     */
    private const array LEVEL_BY_CASE = [
        self::Trace->value => self::LEVEL_TRACE,
        self::Debug->value => self::LEVEL_DEBUG,
        self::Info->value => self::LEVEL_INFO,
        self::Notice->value => self::LEVEL_NOTICE,
        self::Warning->value => self::LEVEL_WARNING,
        self::Error->value => self::LEVEL_ERROR,
        self::Critical->value => self::LEVEL_CRITICAL,
        self::Alert->value => self::LEVEL_ALERT,
        self::Emergency->value => self::LEVEL_EMERGENCY,
    ];

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
        return self::LEVEL_BY_CASE[$this->value];
    }

    public function isError(): bool
    {
        return $this->level() >= self::Error->level();
    }
}
