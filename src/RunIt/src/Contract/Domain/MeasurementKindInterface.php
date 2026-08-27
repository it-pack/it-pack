<?php

declare(strict_types=1);

namespace ItPack\RunIt\Contract\Domain;

/**
 * Contract for the semantic kind of a measurement.
 *
 * The kind decides how the value is interpreted and which value type is valid:
 * counters and sizes are integers, while gauges, durations, and ratios are
 * floating-point values.
 *
 * @api
 *
 * @see https://w3id.org/itpack/runit#Measurement
 */
interface MeasurementKindInterface
{
    public function label(): string;
}
