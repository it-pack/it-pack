<?php

declare(strict_types=1);

namespace ItPack\RunIt\Adapter\Psr\Log;

use ItPack\RunIt\Contract\Domain\ObservationSeverityInterface;

/**
 * Maps a RunIt observation severity to a PSR-3 log level.
 *
 * PSR-3 defines eight levels (`emergency`, `alert`, `critical`, `error`,
 * `warning`, `notice`, `info`, `debug`). RunIt severities mirror those and add a
 * finer `trace` tier below `debug`, so the mapper folds `trace` into `debug` and
 * otherwise keeps the PSR-3 ordering.
 *
 * @api
 */
interface ObservationToPsr3LevelMapperInterface
{
    /**
     * Returns the PSR-3 level name (for example `error`) for the severity.
     */
    public function toLevel(ObservationSeverityInterface $severity): string;

    /**
     * Returns the PSR-3 level name for a numeric severity level.
     */
    public function toLevelFromNumeric(int $level): string;
}
