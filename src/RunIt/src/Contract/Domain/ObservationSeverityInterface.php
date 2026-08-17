<?php

declare(strict_types=1);

namespace ItPack\RunIt\Contract\Domain;

/**
 * Contract for the severity of an observation.
 *
 * Severities mirror the PSR-3 log levels so they integrate with existing logging
 * without redefining the scale. Each severity has a stable name and a numeric
 * level; observation consumers use the level to filter and route.
 *
 * @api
 *
 * @see https://w3id.org/itpack/runit#Observation
 */
interface ObservationSeverityInterface
{
    /**
     * Returns the stable, lower-case severity name.
     */
    public function name(): string;

    /**
     * Returns the PSR-3 numeric level (higher means more severe).
     */
    public function level(): int;

    /**
     * Returns whether this severity represents an error or above.
     */
    public function isError(): bool;
}
