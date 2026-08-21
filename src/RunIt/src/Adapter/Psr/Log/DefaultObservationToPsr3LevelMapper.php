<?php

declare(strict_types=1);

namespace ItPack\RunIt\Adapter\Psr\Log;

use ItPack\RunIt\Adapter\Psr\Contract\ObservationToPsr3LevelMapperInterface;
use ItPack\RunIt\Contract\Domain\ObservationSeverityInterface;

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
            $level >= 600 => 'emergency',
            $level >= 550 => 'alert',
            $level >= 500 => 'critical',
            $level >= 400 => 'error',
            $level >= 300 => 'warning',
            $level >= 250 => 'notice',
            $level >= 200 => 'info',
            $level >= 100 => 'debug',
            default => 'debug',
        };
    }
}
