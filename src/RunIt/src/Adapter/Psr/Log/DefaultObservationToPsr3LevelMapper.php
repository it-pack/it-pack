<?php

declare(strict_types=1);

namespace ItPack\RunIt\Adapter\Psr\Log;

use ItPack\RunIt\Adapter\Psr\Contract\ObservationToPsr3LevelMapperInterface;
use ItPack\RunIt\Contract\Domain\ObservationSeverityInterface;
use ItPack\RunIt\Domain\Observation\ObservationSeverity;

/**
 * Maps RunIt observation severities to PSR-3 log levels by numeric threshold.
 *
 * RunIt extends the PSR-3 scale with a `trace` tier below `debug`; it maps to
 * `debug`. The remaining tiers map to their PSR-3 equivalent by level.
 *
 * @api
 */
final class DefaultObservationToPsr3LevelMapper implements ObservationToPsr3LevelMapperInterface
{
    public function toLevel(ObservationSeverityInterface $severity): string
    {
        return $this->toLevelFromNumeric($severity->level());
    }

    public function toLevelFromNumeric(int $level): string
    {
        return match (true) {
            $level >= ObservationSeverity::Emergency->level() => ObservationSeverity::Emergency->value,
            $level >= ObservationSeverity::Alert->level() => ObservationSeverity::Alert->value,
            $level >= ObservationSeverity::Critical->level() => ObservationSeverity::Critical->value,
            $level >= ObservationSeverity::Error->level() => ObservationSeverity::Error->value,
            $level >= ObservationSeverity::Warning->level() => ObservationSeverity::Warning->value,
            $level >= ObservationSeverity::Notice->level() => ObservationSeverity::Notice->value,
            $level >= ObservationSeverity::Info->level() => ObservationSeverity::Info->value,
            default => ObservationSeverity::Debug->value,
        };
    }
}
