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
    private const array LEVEL_THRESHOLDS_TO_PSR3 = [
        ObservationSeverity::LEVEL_EMERGENCY => ObservationSeverity::Emergency->value,
        ObservationSeverity::LEVEL_ALERT => ObservationSeverity::Alert->value,
        ObservationSeverity::LEVEL_CRITICAL => ObservationSeverity::Critical->value,
        ObservationSeverity::LEVEL_ERROR => ObservationSeverity::Error->value,
        ObservationSeverity::LEVEL_WARNING => ObservationSeverity::Warning->value,
        ObservationSeverity::LEVEL_NOTICE => ObservationSeverity::Notice->value,
        ObservationSeverity::LEVEL_INFO => ObservationSeverity::Info->value,
    ];

    public function toLevel(ObservationSeverityInterface $severity): string
    {
        return $this->toLevelFromNumeric($severity->level());
    }

    public function toLevelFromNumeric(int $level): string
    {
        foreach (self::LEVEL_THRESHOLDS_TO_PSR3 as $threshold => $psr3Level) {
            if ($level >= $threshold) {
                return $psr3Level;
            }
        }

        return ObservationSeverity::Debug->value;
    }
}
