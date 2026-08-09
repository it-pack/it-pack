<?php

declare(strict_types=1);

namespace ItPack\RunIt\Domain\Measurement;

use ItPack\RunIt\Behavior\Domain\ValidatedStringValueBehavior;
use ItPack\RunIt\Contract\Domain\MeasurementNameInterface;

/**
 * The name of a measurement, for example `files.loaded` or `memory.peak`.
 *
 * Measurement names are stable and extensible. They become metric instrument
 * names, so they stay low-cardinality by convention and are validated to keep
 * unsafe characters out of exported telemetry.
 *
 * @api
 *
 * @see https://w3id.org/itpack/runit#measurementName
 */
final readonly class MeasurementName implements MeasurementNameInterface
{
    use ValidatedStringValueBehavior;
}
