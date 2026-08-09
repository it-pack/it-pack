<?php

declare(strict_types=1);

namespace ItPack\RunIt\Contract\Domain;

/**
 * Contract for the name of a measurement.
 *
 * Measurement names are stable, dot-separated strings such as `files.loaded`
 * or `memory.peak`. They are extensible and validated, never a closed enum.
 *
 * @api
 *
 * @see https://w3id.org/itpack/runit#measurementName
 */
interface MeasurementNameInterface extends StringValueInterface
{
}
