<?php

declare(strict_types=1);

namespace ItPack\DeriveIt\Contract\Telemetry;

/**
 * Severity vocabulary for {@see DeriveEventSinkInterface::observed()}.
 *
 * Deliberately PSR-3-shaped so host-side adapters (PSR-3 logger, RunIt
 * observation recorder) map cases 1:1 without this package depending on any
 * of them. Cases carry their canonical string value for wire formats.
 */
enum DeriveObservationSeverity: string
{
    case DEBUG = 'debug';
    case INFO = 'info';
    case WARNING = 'warning';
    case ERROR = 'error';
}
