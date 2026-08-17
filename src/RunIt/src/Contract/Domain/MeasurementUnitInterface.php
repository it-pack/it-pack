<?php

declare(strict_types=1);

namespace ItPack\RunIt\Contract\Domain;

/**
 * Contract for the unit of a measurement value.
 *
 * Units are validated, bounded strings drawn from a curated set so they stay
 * low-cardinality and safe to publish. New units extend the enum rather than
 * becoming free-form strings.
 *
 * @api
 *
 * @see https://w3id.org/itpack/runit#measurement
 */
interface MeasurementUnitInterface
{
    public function name(): string;

    /**
     * Returns the display symbol for the unit (for example `ms`, `%`, `B`).
     */
    public function symbol(): string;
}
